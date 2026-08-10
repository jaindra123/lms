<?php
namespace paygw_razorpay;

use curl;

defined('MOODLE_INTERNAL') || die();

/**
 * Razorpay Orders API and signature verification helpers.
 */
class razorpay_helper {

    public static function generate_txnref(): string {
        return 'RZP' . time() . random_int(1000, 9999);
    }

    public static function amount_to_paise(float $amount): int {
        return (int) round($amount * 100);
    }

    public static function should_use_mock(\stdClass $config): bool {
        if (self::is_live_host()) {
            return false;
        }

        $keyid = trim($config->keyid ?? '');
        $secret = trim($config->keysecret ?? '');

        if ($keyid === '' || $secret === '') {
            return true;
        }

        if (stripos($keyid, 'CHANGE_ME') !== false || stripos($secret, 'CHANGE_ME') !== false) {
            return true;
        }

        return ($config->environment ?? 'test') === 'test'
            && stripos($keyid, 'rzp_test_mock') === 0;
    }

    /**
     * Production hosts must never accept mock orders or mock signatures.
     */
    public static function is_live_host(): bool {
        global $CFG;

        $host = strtolower((string) (parse_url($CFG->wwwroot ?? '', PHP_URL_HOST) ?: ''));
        $blocked = [
            'iiidemlms.eci.gov.in',
            'lms.eci.gov.in',
        ];
        return in_array($host, $blocked, true);
    }

    /**
     * Whether mock payment completion is permitted in this environment.
     */
    public static function mock_payments_allowed(): bool {
        return !self::is_live_host();
    }

    public static function get_api_base(\stdClass $config): string {
        return 'https://api.razorpay.com/v1';
    }

    /**
     * Fail fast when Razorpay Checkout cannot start (preferences API down).
     *
     * Checkout.js calls this endpoint after open(). If it is unhealthy we must NOT open
     * the Razorpay modal — otherwise the user sees blank modal + alert + "Uh! oh!" page.
     *
     * @param string $keyid
     * @return void
     * @throws \moodle_exception
     */
    public static function assert_checkout_available(string $keyid): void {
        global $CFG;

        $keyid = trim($keyid);
        if ($keyid === '') {
            throw new \moodle_exception('ordercreatefailed', 'paygw_razorpay');
        }

        require_once($CFG->libdir . '/filelib.php');

        $url = 'https://api.razorpay.com/v1/preferences?key_id=' . rawurlencode($keyid);
        $curl = new curl();
        $curl->setHeader(['Accept: application/json']);
        $raw = $curl->get($url, [], [
            'CURLOPT_TIMEOUT' => 12,
            'CURLOPT_CONNECTTIMEOUT' => 8,
        ]);

        if ($curl->get_errno()) {
            debugging('Razorpay checkout probe curl error: ' . $curl->error, DEBUG_DEVELOPER);
            throw new \moodle_exception('apiservererror', 'paygw_razorpay');
        }

        $info = $curl->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);
        $raw = is_string($raw) ? trim($raw) : '';
        $lower = strtolower($raw);
        $decoded = json_decode($raw, true);
        $errorcode = strtoupper((string) ($decoded['error']['code'] ?? ''));

        // Healthy enough for Checkout.js to proceed.
        if ($httpcode >= 200 && $httpcode < 300) {
            return;
        }

        // Razorpay outage / overload — same symptom as blank modal + "Uh! oh!".
        if ($httpcode >= 500
                || $errorcode === 'SERVER_ERROR'
                || str_contains($lower, 'service unavailable')
                || str_contains($lower, 'trouble completing your request')
                || str_contains($lower, 'unexpected error occurred')
                || $raw === '') {
            debugging('Razorpay checkout probe failed http=' . $httpcode . ' body=' . substr($raw, 0, 200), DEBUG_DEVELOPER);
            throw new \moodle_exception('apiservererror', 'paygw_razorpay');
        }

        // Other non-success responses still mean checkout is not usable.
        debugging('Razorpay checkout probe unexpected http=' . $httpcode . ' body=' . substr($raw, 0, 200), DEBUG_DEVELOPER);
        throw new \moodle_exception('apiservererror', 'paygw_razorpay');
    }

    /**
     * Create a Razorpay order (or mock order for local testing).
     *
     * @param \stdClass $config
     * @param string $receipt
     * @param float $amount
     * @param string $currency
     * @return array{id:string,amount:int,currency:string,mock?:bool}
     */
    public static function create_order(\stdClass $config, string $receipt, float $amount, string $currency): array {
        $paise = self::amount_to_paise($amount);

        if (self::should_use_mock($config)) {
            return [
                'id' => 'order_mock_' . $receipt,
                'amount' => $paise,
                'currency' => $currency,
                'mock' => true,
            ];
        }

        $keyid = trim($config->keyid ?? '');
        $secret = trim($config->keysecret ?? '');

        // Do not create an order / open Checkout when Razorpay Checkout itself is down.
        self::assert_checkout_available($keyid);

        $payload = json_encode([
            'amount' => $paise,
            'currency' => $currency,
            'receipt' => $receipt,
            'notes' => [
                'source' => 'moodle',
            ],
        ]);

        $response = self::api_request('POST', self::get_api_base($config) . '/orders', $keyid, $secret, $payload);
        if (empty($response['id'])) {
            throw new \moodle_exception('ordercreatefailed', 'paygw_razorpay');
        }

        return [
            'id' => (string) $response['id'],
            'amount' => (int) ($response['amount'] ?? $paise),
            'currency' => (string) ($response['currency'] ?? $currency),
        ];
    }

    public static function verify_signature(string $orderid, string $paymentid, string $signature, string $secret): bool {
        if ($orderid === '' || $paymentid === '' || $signature === '' || $secret === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $orderid . '|' . $paymentid, $secret);
        return hash_equals($expected, $signature);
    }

    public static function verify_mock_signature(string $orderid, string $paymentid, string $signature): bool {
        if (!self::mock_payments_allowed()) {
            return false;
        }
        $expected = hash_hmac('sha256', $orderid . '|' . $paymentid, 'razorpay_mock_secret');
        return hash_equals($expected, $signature);
    }

    /**
     * Server-side expected course fee for a stored txn (never trust client amount).
     *
     * @param \stdClass $txn
     * @return float
     */
    public static function expected_amount_for_txn(\stdClass $txn): float {
        $payable = \core_payment\helper::get_payable(
            $txn->component,
            $txn->paymentarea,
            (int) $txn->itemid
        );
        $surcharge = \core_payment\helper::get_gateway_surcharge('razorpay');
        $base = course_fee_amount::get_payment_amount((int) $txn->itemid, $payable->get_amount());
        return \core_payment\helper::get_rounded_cost($base, $payable->get_currency(), $surcharge);
    }

    /**
     * Require stored txn amount to match the current server-side payable.
     *
     * @param \stdClass $txn
     * @return void
     * @throws \moodle_exception
     */
    public static function assert_txn_matches_payable(\stdClass $txn): void {
        $expected = self::expected_amount_for_txn($txn);
        $stored = (float) $txn->amount;
        if ($expected <= 0 || abs($expected - $stored) >= 0.005) {
            throw new \moodle_exception('amountmismatch', 'paygw_razorpay');
        }
        $payable = \core_payment\helper::get_payable(
            $txn->component,
            $txn->paymentarea,
            (int) $txn->itemid
        );
        if (strcasecmp((string) $txn->currency, (string) $payable->get_currency()) !== 0) {
            throw new \moodle_exception('amountmismatch', 'paygw_razorpay');
        }
    }

    /**
     * Fetch a payment from Razorpay and assert amount/order/status vs local txn.
     *
     * @param \stdClass $config
     * @param \stdClass $txn
     * @param string $paymentid
     * @return void
     * @throws \moodle_exception
     */
    public static function assert_remote_payment_matches_txn(\stdClass $config, \stdClass $txn, string $paymentid): void {
        $keyid = trim($config->keyid ?? '');
        $secret = trim($config->keysecret ?? '');
        if ($keyid === '' || $secret === '') {
            throw new \moodle_exception('paymentfailed', 'paygw_razorpay');
        }

        $payment = self::api_request(
            'GET',
            self::get_api_base($config) . '/payments/' . rawurlencode($paymentid),
            $keyid,
            $secret,
            null
        );

        $remoteorder = (string) ($payment['order_id'] ?? '');
        $remoteamount = (int) ($payment['amount'] ?? -1);
        $remotecurrency = strtoupper((string) ($payment['currency'] ?? ''));
        $remotestatus = strtolower((string) ($payment['status'] ?? ''));

        $expectedpaise = self::amount_to_paise((float) $txn->amount);
        $okstatus = in_array($remotestatus, ['captured', 'authorized'], true);

        if ($remoteorder !== (string) $txn->orderid
                || $remoteamount !== $expectedpaise
                || $remotecurrency !== strtoupper((string) $txn->currency)
                || !$okstatus) {
            debugging(
                'Razorpay amount/status mismatch payment=' . $paymentid
                . ' order=' . $remoteorder
                . ' amount=' . $remoteamount
                . ' expected=' . $expectedpaise
                . ' status=' . $remotestatus,
                DEBUG_DEVELOPER
            );
            throw new \moodle_exception('amountmismatch', 'paygw_razorpay');
        }
    }

    /**
     * Map Razorpay API / transport failures to a clear Moodle exception.
     *
     * @param string $raw Raw response body (may be JSON or plain text)
     * @param array|null $errordecoded Decoded error object from Razorpay JSON, if any
     * @return void
     * @throws \moodle_exception
     */
    protected static function throw_api_failure(string $raw, ?array $errordecoded = null): void {
        $code = strtoupper((string) ($errordecoded['code'] ?? ''));
        $description = (string) ($errordecoded['description'] ?? '');
        $reason = (string) ($errordecoded['reason'] ?? '');
        $field = (string) ($errordecoded['field'] ?? '');
        $lowerraw = strtolower($raw);
        $lowerdesc = strtolower($description);

        debugging(
            'Razorpay API failure code=' . $code
            . ' reason=' . $reason
            . ' field=' . $field
            . ' desc=' . substr($description, 0, 200)
            . ' raw=' . substr($raw, 0, 300),
            DEBUG_DEVELOPER
        );

        // Razorpay-side outages / overload.
        if ($code === 'SERVER_ERROR'
                || $raw === ''
                || str_contains($lowerraw, 'service unavailable')
                || str_contains($lowerraw, 'bad gateway')
                || str_contains($lowerraw, 'gateway timeout')
                || str_contains($lowerdesc, 'unexpected error occurred')) {
            throw new \moodle_exception('apiservererror', 'paygw_razorpay');
        }

        if (str_contains($lowerraw, '<html')) {
            throw new \moodle_exception('apiunavailable', 'paygw_razorpay');
        }

        // Auth / key problems (often misconfigured test keys).
        if ($code === 'BAD_REQUEST_ERROR' && (
                str_contains($lowerdesc, 'authentication')
                || str_contains($lowerdesc, 'auth')
                || $reason === 'authentication_failed'
        )) {
            throw new \moodle_exception('ordercreatefailed', 'paygw_razorpay');
        }

        // Validation / business errors — keep Razorpay detail for admins, but readable.
        $detail = $description !== '' ? $description : get_string('paymentfailed', 'paygw_razorpay');
        if ($code !== '') {
            $detail .= ' [' . $code . ']';
        }
        if ($field !== '') {
            $detail .= ' (' . $field . ')';
        }
        throw new \moodle_exception('apierrorprefix', 'paygw_razorpay', '', $detail);
    }

    /**
     * @param string $method
     * @param string $url
     * @param string $keyid
     * @param string $secret
     * @param string|null $body
     * @return array
     */
    protected static function api_request(string $method, string $url, string $keyid, string $secret, ?string $body = null): array {
        global $CFG;

        require_once($CFG->libdir . '/filelib.php');

        $curl = new curl();
        $curl->setHeader([
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        $options = [
            'CURLOPT_USERPWD' => $keyid . ':' . $secret,
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CONNECTTIMEOUT' => 15,
        ];

        if (strtoupper($method) === 'POST') {
            $raw = $curl->post($url, $body ?? '{}', $options);
        } else {
            $raw = $curl->get($url, [], $options);
        }

        if ($curl->get_errno()) {
            debugging('Razorpay curl error: ' . $curl->error, DEBUG_DEVELOPER);
            throw new \moodle_exception('apiconnectionfailed', 'paygw_razorpay');
        }

        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') {
            self::throw_api_failure($raw);
        }

        // Plain-text / HTML outage pages (not JSON).
        if (stripos($raw, 'service unavailable') !== false
                || stripos($raw, 'bad gateway') !== false
                || stripos($raw, '<html') !== false) {
            self::throw_api_failure($raw);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            self::throw_api_failure($raw);
        }

        if (!empty($decoded['error']) && is_array($decoded['error'])) {
            self::throw_api_failure($raw, $decoded['error']);
        }

        return $decoded;
    }

    public static function complete_transaction(\stdClass $txn, string $razorpaypaymentid): void {
        global $DB;

        // Never enrol from a client-tampered or stale underpaid transaction.
        self::assert_txn_matches_payable($txn);

        $alreadycompleted = (($txn->status ?? '') === 'completed');

        $moodlepaymentid = \core_payment\helper::save_payment(
            (int) $txn->accountid,
            $txn->component,
            $txn->paymentarea,
            (int) $txn->itemid,
            (int) $txn->userid,
            (float) $txn->amount,
            $txn->currency,
            'razorpay'
        );

        \core_payment\helper::deliver_order(
            $txn->component,
            $txn->paymentarea,
            (int) $txn->itemid,
            $moodlepaymentid,
            (int) $txn->userid
        );

        $txn->status = 'completed';
        $txn->paymentid = $razorpaypaymentid;
        $txn->timemodified = time();
        $DB->update_record('paygw_razorpay_txn', $txn);

        if (!$alreadycompleted) {
            try {
                self::notify_payment_result($txn, true);
            } catch (\Throwable $e) {
                error_log('paygw_razorpay success notify failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * Mark a pending transaction as failed and email user + admins.
     *
     * @param \stdClass $txn
     * @param string $reason
     * @return void
     */
    public static function mark_transaction_failed(\stdClass $txn, string $reason = ''): void {
        global $DB;

        if (($txn->status ?? '') === 'completed') {
            return;
        }

        $txn->status = 'failed';
        $txn->timemodified = time();
        $DB->update_record('paygw_razorpay_txn', $txn);

        try {
            self::notify_payment_result($txn, false, $reason);
        } catch (\Throwable $e) {
            error_log('paygw_razorpay failure notify failed: ' . $e->getMessage());
        }
    }

    /**
     * Email payer and site admins after Razorpay success or failure.
     *
     * @param \stdClass $txn
     * @param bool $success
     * @param string $reason
     * @return void
     */
    public static function notify_payment_result(\stdClass $txn, bool $success, string $reason = ''): void {
        global $CFG, $DB, $SITE, $PAGE;

        // AJAX/webservice payment verify may not have $PAGE->context set yet.
        // set_context(null) only fills system context when unset (see moodle_page).
        $PAGE->set_context(null);

        $user = \core_user::get_user((int) $txn->userid);
        if (!$user || empty($user->email) || !validate_email($user->email)) {
            return;
        }

        $systemcontext = \context_system::instance();
        $coursename = get_string('unknowncourse', 'paygw_razorpay');
        $courseurl = $CFG->wwwroot;
        if (($txn->component ?? '') === 'enrol_fee' && ($txn->paymentarea ?? '') === 'fee') {
            $courseid = (int) $DB->get_field('enrol', 'courseid', ['id' => (int) $txn->itemid]);
            if ($courseid) {
                $course = get_course($courseid);
                $coursecontext = \context_course::instance($courseid);
                $coursename = format_string($course->fullname, true, ['context' => $coursecontext]);
                $courseurl = (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
            }
        }

        $amount = \core_payment\helper::get_cost_as_string((float) $txn->amount, (string) $txn->currency);
        $sitename = format_string($SITE->fullname, true, ['context' => $systemcontext]);
        $a = (object) [
            'sitename' => $sitename,
            'fullname' => fullname($user),
            'firstname' => $user->firstname,
            'email' => $user->email,
            'coursename' => $coursename,
            'courseurl' => $courseurl,
            'amount' => $amount,
            'currency' => (string) $txn->currency,
            'txnref' => (string) ($txn->txnref ?? ''),
            'orderid' => (string) ($txn->orderid ?? ''),
            'paymentid' => (string) ($txn->paymentid ?? ''),
            'invoicenumber' => (string) ($txn->invoicenumber ?? ''),
            'reason' => $reason !== '' ? $reason : get_string('paymentfailed', 'paygw_razorpay'),
            'admin' => '',
        ];

        $attachment = '';
        $attachname = '';
        if ($success) {
            try {
                $invoice = invoice::create_for_transaction($txn);
                if ($invoice) {
                    $attachment = $invoice['path'];
                    $attachname = $invoice['filename'];
                    $a->invoicenumber = $invoice['invoicenumber'];
                    $txn->invoicenumber = $invoice['invoicenumber'];
                }
            } catch (\Throwable $e) {
                error_log('paygw_razorpay invoice create failed: ' . $e->getMessage());
            }
        }

        if ($success) {
            $usersubject = self::email_string('paymentsuccessemailusersubject', $a);
            $userbody = self::email_string('paymentsuccessemailuserbody', $a);
            $userhtml = '';
            if (class_exists('\\theme_iiidem2\\notify_email')) {
                $userhtml = \theme_iiidem2\notify_email::render([
                    'sitename' => $sitename,
                    'firstname' => self::email_string('paymentsuccessemailusergreeting'),
                    'title' => self::email_string('paymentsuccessemailusertitle'),
                    'intro' => self::email_string('paymentsuccessemailuserintro'),
                    'rows' => [
                        [
                            'label' => self::email_string('paymentsuccessemailuserlabel_student'),
                            'value' => $a->fullname,
                        ],
                        [
                            'label' => self::email_string('paymentsuccessemailuserlabel_email'),
                            'valuehtml' => '<a href="mailto:' . s($a->email) . '" style="color:#0b3d91;text-decoration:underline;">'
                                . s($a->email) . '</a>',
                        ],
                        [
                            'label' => self::email_string('paymentsuccessemailuserlabel_course'),
                            'value' => $a->coursename,
                        ],
                        [
                            'label' => self::email_string('paymentsuccessemailuserlabel_amount'),
                            'value' => $a->amount,
                        ],
                        [
                            'label' => self::email_string('paymentsuccessemailuserlabel_invoice'),
                            'value' => $a->invoicenumber,
                        ],
                        [
                            'label' => self::email_string('paymentsuccessemailuserlabel_reference'),
                            'value' => $a->txnref,
                        ],
                        [
                            'label' => self::email_string('paymentsuccessemailuserlabel_orderid'),
                            'value' => $a->orderid,
                        ],
                        [
                            'label' => self::email_string('paymentsuccessemailuserlabel_paymentid'),
                            'value' => $a->paymentid,
                        ],
                    ],
                    'note' => self::email_string('paymentsuccessemailusernote', $a),
                    'ctaurl' => $courseurl,
                    'ctalabel' => self::email_string('paymentsuccessemailusercta'),
                    'signoff' => self::email_string('paymentsuccessemailusersignoff', $a),
                ]);
            }
            $adminsubject = get_string('paymentsuccessemailadminsubject', 'paygw_razorpay', $a);
            $adminbody = get_string('paymentsuccessemailadminbody', 'paygw_razorpay', $a);
        } else {
            $usersubject = get_string('paymentfailedemailusersubject', 'paygw_razorpay', $a);
            $userbody = get_string('paymentfailedemailuserbody', 'paygw_razorpay', $a);
            $userhtml = '';
            $adminsubject = get_string('paymentfailedemailadminsubject', 'paygw_razorpay', $a);
            $adminbody = get_string('paymentfailedemailadminbody', 'paygw_razorpay', $a);
        }

        $sender = \core_user::get_noreply_user();
        $olddebug = $CFG->debug ?? 0;
        $olddebugdisplay = $CFG->debugdisplay ?? false;
        $CFG->debug = 0;
        $CFG->debugdisplay = false;

        try {
            email_to_user($user, $sender, $usersubject, $userbody, $userhtml, $attachment, $attachname);
            foreach (get_admins() as $admin) {
                if (empty($admin->email) || !validate_email($admin->email)) {
                    continue;
                }
                if ((int) $admin->id === (int) $user->id) {
                    continue;
                }
                // Admins get the same PDF so finance has a copy.
                email_to_user($admin, $sender, $adminsubject, $adminbody, '', $attachment, $attachname);
            }
        } catch (\Throwable $e) {
            error_log('paygw_razorpay notify_payment_result: ' . $e->getMessage());
        } finally {
            $CFG->debug = $olddebug;
            $CFG->debugdisplay = $olddebugdisplay;
            if ($attachment !== '' && is_file($attachment)) {
                @unlink($attachment);
            }
        }
    }

    /**
     * Language string with English fallback (avoids [[identifier]] when lang cache is stale).
     *
     * @param string $identifier
     * @param mixed $a
     * @return string
     */
    protected static function email_string(string $identifier, $a = null): string {
        $fallbacks = [
            'paymentsuccessemailusergreeting' => 'Student',
            'paymentsuccessemailusersubject' => '{$a->sitename}: Payment successful — {$a->coursename}',
            'paymentsuccessemailusertitle' => 'Payment received successfully',
            'paymentsuccessemailuserintro' => 'This is to confirm that your course fee payment has been received successfully.',
            'paymentsuccessemailusernote' => 'Thank you for your payment. We wish you a rewarding learning experience with {$a->sitename}.',
            'paymentsuccessemailusercta' => 'Open course',
            'paymentsuccessemailusersignoff' => '{$a->sitename} Administrator',
            'paymentsuccessemailuserlabel_student' => 'Student Name',
            'paymentsuccessemailuserlabel_email' => 'Email',
            'paymentsuccessemailuserlabel_course' => 'Course',
            'paymentsuccessemailuserlabel_amount' => 'Amount Paid',
            'paymentsuccessemailuserlabel_invoice' => 'Invoice Number',
            'paymentsuccessemailuserlabel_reference' => 'Payment Reference',
            'paymentsuccessemailuserlabel_orderid' => 'Razorpay Order ID',
            'paymentsuccessemailuserlabel_paymentid' => 'Razorpay Payment ID',
            'paymentsuccessemailuserbody' => 'Dear Student,

This is to confirm that your course fee payment has been received successfully.

Student Name: {$a->fullname}
Email: {$a->email}

Course: {$a->coursename}

Payment Details:

Amount Paid: {$a->amount}
Invoice Number: {$a->invoicenumber}
Payment Reference: {$a->txnref}
Razorpay Order ID: {$a->orderid}
Razorpay Payment ID: {$a->paymentid}

You can access your course using the link below:

Course URL: {$a->courseurl}

Thank you for your payment. We wish you a rewarding learning experience with {$a->sitename}.

Regards,
{$a->sitename} Administrator
',
        ];

        $value = get_string($identifier, 'paygw_razorpay', $a);
        if ($value === '' || strpos($value, '[[') === 0) {
            $value = $fallbacks[$identifier] ?? $identifier;
            if (is_object($a) || is_array($a)) {
                $value = strtr($value, [
                    '{$a->sitename}' => (string) ($a->sitename ?? ''),
                    '{$a->fullname}' => (string) ($a->fullname ?? ''),
                    '{$a->firstname}' => (string) ($a->firstname ?? ''),
                    '{$a->email}' => (string) ($a->email ?? ''),
                    '{$a->coursename}' => (string) ($a->coursename ?? ''),
                    '{$a->courseurl}' => (string) ($a->courseurl ?? ''),
                    '{$a->amount}' => (string) ($a->amount ?? ''),
                    '{$a->invoicenumber}' => (string) ($a->invoicenumber ?? ''),
                    '{$a->txnref}' => (string) ($a->txnref ?? ''),
                    '{$a->orderid}' => (string) ($a->orderid ?? ''),
                    '{$a->paymentid}' => (string) ($a->paymentid ?? ''),
                ]);
            }
        }
        return $value;
    }
}