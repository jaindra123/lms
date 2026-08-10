<?php
declare(strict_types=1);

namespace paygw_razorpay\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use paygw_razorpay\razorpay_helper;

/**
 * Report a cancelled/failed Razorpay checkout and notify user + admins.
 */
class report_payment_failure extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'orderid' => new external_value(PARAM_TEXT, 'Razorpay order id'),
            'reason' => new external_value(PARAM_TEXT, 'Failure reason', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(string $orderid, string $reason = ''): array {
        global $DB, $USER;

        self::validate_parameters(self::execute_parameters(), [
            'orderid' => $orderid,
            'reason' => $reason,
        ]);

        require_login();

        \theme_iiidem2\rate_limit::require_allowed('paygw_razorpay_report_failure', 5, 900);

        $orderid = trim(clean_param($orderid, PARAM_ALPHANUMEXT));
        $reason = trim(clean_param($reason, PARAM_TEXT));
        if ($orderid === '' || \core_text::strlen($orderid) > 64) {
            throw new \moodle_exception('invalidparameter', 'error');
        }
        if (\core_text::strlen($reason) > 500) {
            $reason = \core_text::substr($reason, 0, 500);
        }

        $txn = $DB->get_record('paygw_razorpay_txn', ['orderid' => $orderid]);
        if (!$txn) {
            throw new \moodle_exception('txnnotfound', 'paygw_razorpay');
        }

        if ((int) $txn->userid !== (int) $USER->id && !is_siteadmin()) {
            throw new \moodle_exception('paymentfailed', 'paygw_razorpay');
        }

        if (($txn->status ?? '') === 'completed') {
            return ['status' => 'completed'];
        }

        if (($txn->status ?? '') === 'failed') {
            return ['status' => 'failed'];
        }

        razorpay_helper::mark_transaction_failed($txn, $reason);

        return ['status' => 'failed'];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'Transaction status'),
        ]);
    }
}
