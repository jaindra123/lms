<?php
declare(strict_types=1);

namespace paygw_razorpay\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_payment\helper;
use paygw_razorpay\course_fee_amount;
use paygw_razorpay\fee_access;
use paygw_razorpay\razorpay_helper;

class get_checkout_data extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Component'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area'),
            'itemid' => new external_value(PARAM_INT, 'Item id'),
            'description' => new external_value(PARAM_TEXT, 'Payment description (ignored; not sent to Razorpay)'),
        ]);
    }

    public static function execute(string $component, string $paymentarea, int $itemid, string $description): array {
        global $DB, $USER;

        self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'description' => $description,
        ]);

        require_login();
        if (!isloggedin() || isguestuser()) {
            throw new \require_login_exception(get_string('sessionerroruser', 'error'));
        }

        // CDAC prefill/encrypt + Sardine: LMS does not host those APIs. Cap how
        // often this origin can mint a Razorpay hosted session.
        \theme_iiidem2\rate_limit::require_allowed('paygw_razorpay_checkout', 3, 600);
        \theme_iiidem2\rate_limit::require_allowed(
            'paygw_razorpay_checkout_ip_burst',
            5,
            600,
            'ip:' . \theme_iiidem2\rate_limit::client_ip()
        );
        \theme_iiidem2\rate_limit::require_allowed(
            'paygw_razorpay_checkout_ip',
            8,
            3600,
            'ip:' . \theme_iiidem2\rate_limit::client_ip()
        );

        if (!fee_access::user_can_pay_course_fee((int) $USER->id)) {
            throw new \moodle_exception('paymentnotallowed', 'paygw_razorpay');
        }

        $payable = helper::get_payable($component, $paymentarea, $itemid);
        $config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'razorpay');
        $surcharge = helper::get_gateway_surcharge('razorpay');
        $baseamount = course_fee_amount::get_payment_amount($itemid, $payable->get_amount());
        $amount = helper::get_rounded_cost($baseamount, $payable->get_currency(), $surcharge);
        $currency = $payable->get_currency();

        if ($amount <= 0) {
            throw new \moodle_exception('paymentfailed', 'paygw_razorpay');
        }

        $txnref = razorpay_helper::generate_txnref();
        $callbackurl = new \moodle_url('/payment/gateway/razorpay/return.php');
        $link = razorpay_helper::create_payment_link($config, $txnref, $amount, $currency, $callbackurl);

        $now = time();
        $record = (object) [
            'txnref' => $txnref,
            'orderid' => $link['id'],
            'paymentid' => null,
            'userid' => (int) $USER->id,
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'accountid' => $payable->get_account_id(),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record('paygw_razorpay_txn', $record);

        $usemock = !empty($link['mock']);
        $mockurl = '';
        $redirecturl = (string) ($link['short_url'] ?? '');
        if ($usemock) {
            $mockurl = (new \moodle_url('/payment/gateway/razorpay/mock.php', [
                'orderid' => $link['id'],
            ]))->out(false);
            $redirecturl = $mockurl;
        }

        // CDAC Key ID exposure: only the hosted Payment Link URL leaves the server.
        return self::client_payload($redirecturl, $usemock, $mockurl);
    }

    /**
     * Browser payload — never Key ID, order id, amount, name, or email.
     *
     * @return array{redirecturl:string,mock:bool,mockurl:string}
     */
    private static function client_payload(string $redirecturl, bool $mock, string $mockurl): array {
        return [
            'redirecturl' => $redirecturl,
            'mock' => $mock,
            'mockurl' => $mockurl,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'redirecturl' => new external_value(PARAM_RAW_TRIMMED, 'Hosted Razorpay payment URL'),
            'mock' => new external_value(PARAM_BOOL, 'Use mock checkout'),
            'mockurl' => new external_value(PARAM_TEXT, 'Mock checkout URL'),
        ]);
    }
}
