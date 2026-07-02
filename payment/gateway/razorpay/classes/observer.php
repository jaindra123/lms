<?php
namespace paygw_razorpay;

defined('MOODLE_INTERNAL') || die();

class observer {

    public static function account_updated(\core_payment\event\account_updated $event): void {
        global $DB;

        $accountid = (int) $event->objectid;
        $gateway = $DB->get_record('payment_gateways', [
            'accountid' => $accountid,
            'gateway' => 'razorpay',
            'enabled' => 1,
        ]);

        if (!$gateway) {
            return;
        }

        $config = json_decode($gateway->config);
        if (!$config) {
            return;
        }

        $amount = course_fee_amount::parse_amount($config->coursefeeamount ?? null);
        if ($amount === null) {
            return;
        }

        course_fee_amount::sync_enrol_fee_instances($accountid, $amount);
    }
}
