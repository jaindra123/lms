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
            'description' => new external_value(PARAM_TEXT, 'Payment description'),
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

        \theme_iiidem2\rate_limit::require_allowed('paygw_razorpay_checkout', 5, 600);
        \theme_iiidem2\rate_limit::require_allowed(
            'paygw_razorpay_checkout_ip',
            20,
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
        $order = razorpay_helper::create_order($config, $txnref, $amount, $currency);

        $now = time();
        $record = (object) [
            'txnref' => $txnref,
            'orderid' => $order['id'],
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

        $successurl = helper::get_success_url($component, $paymentarea, $itemid);
        $successurl->param('razorpaypayment', 'success');

        $usemock = !empty($order['mock']);
        $mockurl = '';
        if ($usemock) {
            $mockurl = (new \moodle_url('/payment/gateway/razorpay/mock.php', [
                'orderid' => $order['id'],
            ]))->out(false);
        }

        return [
            'keyid' => trim($config->keyid ?? 'rzp_test_mock'),
            'orderid' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $currency,
            'brandname' => !empty($config->brandname) ? $config->brandname : format_string($GLOBALS['SITE']->shortname),
            'description' => $description,
            'username' => fullname($USER),
            'useremail' => (string) $USER->email,
            'successurl' => $successurl->out(false),
            'mock' => $usemock,
            'mockurl' => $mockurl,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'keyid' => new external_value(PARAM_TEXT, 'Razorpay key id'),
            'orderid' => new external_value(PARAM_TEXT, 'Razorpay order id'),
            'amount' => new external_value(PARAM_INT, 'Amount in paise'),
            'currency' => new external_value(PARAM_ALPHA, 'Currency code'),
            'brandname' => new external_value(PARAM_TEXT, 'Brand name'),
            'description' => new external_value(PARAM_TEXT, 'Description'),
            'username' => new external_value(PARAM_TEXT, 'Payer name'),
            'useremail' => new external_value(PARAM_TEXT, 'Payer email'),
            'successurl' => new external_value(PARAM_URL, 'Redirect URL after success'),
            'mock' => new external_value(PARAM_BOOL, 'Use mock checkout'),
            'mockurl' => new external_value(PARAM_TEXT, 'Mock checkout URL'),
        ]);
    }
}
