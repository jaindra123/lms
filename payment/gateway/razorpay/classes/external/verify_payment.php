<?php
declare(strict_types=1);

namespace paygw_razorpay\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_payment\helper;
use paygw_razorpay\razorpay_helper;

class verify_payment extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'orderid' => new external_value(PARAM_TEXT, 'Razorpay order id'),
            'paymentid' => new external_value(PARAM_TEXT, 'Razorpay payment id'),
            'signature' => new external_value(PARAM_TEXT, 'Razorpay signature'),
        ]);
    }

    public static function execute(string $orderid, string $paymentid, string $signature): array {
        global $DB, $USER;

        self::validate_parameters(self::execute_parameters(), [
            'orderid' => $orderid,
            'paymentid' => $paymentid,
            'signature' => $signature,
        ]);

        require_login();

        \theme_iiidem2\rate_limit::require_allowed('paygw_razorpay_verify', 20, 600);

        $orderid = trim(clean_param($orderid, PARAM_ALPHANUMEXT));
        $paymentid = trim(clean_param($paymentid, PARAM_ALPHANUMEXT));
        $signature = trim(clean_param($signature, PARAM_ALPHANUMEXT));
        if ($orderid === '' || \core_text::strlen($orderid) > 64
                || $paymentid === '' || \core_text::strlen($paymentid) > 64
                || $signature === '' || \core_text::strlen($signature) > 128) {
            throw new \moodle_exception('invalidparameter', 'error');
        }

        $txn = $DB->get_record('paygw_razorpay_txn', ['orderid' => $orderid]);
        if (!$txn) {
            throw new \moodle_exception('txnnotfound', 'paygw_razorpay');
        }

        if ((int) $txn->userid !== (int) $USER->id && !is_siteadmin()) {
            throw new \moodle_exception('paymentfailed', 'paygw_razorpay');
        }

        $redirecturl = helper::get_success_url($txn->component, $txn->paymentarea, (int) $txn->itemid);
        $redirecturl->param('razorpaypayment', 'success');

        if ($txn->status === 'completed') {
            return ['redirecturl' => $redirecturl->out(false)];
        }

        $config = (object) helper::get_gateway_configuration(
            $txn->component,
            $txn->paymentarea,
            (int) $txn->itemid,
            'razorpay'
        );

        $ismock = strpos($orderid, 'order_mock_') === 0;
        if ($ismock && !razorpay_helper::mock_payments_allowed()) {
            throw new \moodle_exception('nopermissions', 'error', '', 'mock payment');
        }

        $verified = $ismock
            ? razorpay_helper::verify_mock_signature($orderid, $paymentid, $signature)
            : razorpay_helper::verify_signature($orderid, $paymentid, $signature, $config->keysecret ?? '');

        if (!$verified) {
            throw new \moodle_exception('invalidsignature', 'paygw_razorpay');
        }

        // Server-side amount validation (never trust client / signature alone).
        razorpay_helper::assert_txn_matches_payable($txn);
        if (!$ismock) {
            razorpay_helper::assert_remote_payment_matches_txn($config, $txn, $paymentid);
        }

        razorpay_helper::complete_transaction($txn, $paymentid);

        return ['redirecturl' => $redirecturl->out(false)];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'redirecturl' => new external_value(PARAM_URL, 'Redirect URL'),
        ]);
    }
}
