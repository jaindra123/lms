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

    public static function get_api_base(\stdClass $config): string {
        return 'https://api.razorpay.com/v1';
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
        $expected = hash_hmac('sha256', $orderid . '|' . $paymentid, 'razorpay_mock_secret');
        return hash_equals($expected, $signature);
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

        $options = [
            'CURLOPT_USERPWD' => $keyid . ':' . $secret,
            'CURLOPT_HTTPHEADER' => ['Content-Type: application/json'],
        ];

        $curl = new curl();
        if (strtoupper($method) === 'POST') {
            $raw = $curl->post($url, $body ?? '', $options);
        } else {
            $raw = $curl->get($url, [], $options);
        }

        if ($curl->get_errno()) {
            throw new \moodle_exception('apiconnectionfailed', 'paygw_razorpay');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \moodle_exception('apiinvalidresponse', 'paygw_razorpay');
        }

        if (!empty($decoded['error'])) {
            $message = $decoded['error']['description'] ?? $decoded['error']['code'] ?? 'Razorpay API error';
            throw new \moodle_exception('apierror', 'paygw_razorpay', '', $message);
        }

        return $decoded;
    }

    public static function complete_transaction(\stdClass $txn, string $razorpaypaymentid): void {
        global $DB;

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
    }
}
