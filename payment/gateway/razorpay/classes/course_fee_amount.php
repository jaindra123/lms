<?php
namespace paygw_razorpay;

use core_payment\helper;

defined('MOODLE_INTERNAL') || die();

/**
 * Course fee amount stored in Razorpay gateway configuration.
 */
class course_fee_amount {

    public static function parse_amount($value): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        $amount = (float) $value;
        return $amount > 0 ? $amount : null;
    }

    public static function get_for_fee_instance(int $feeinstanceid): ?float {
        try {
            $config = helper::get_gateway_configuration('enrol_fee', 'fee', $feeinstanceid, 'razorpay');
        } catch (\moodle_exception $e) {
            return null;
        }

        return self::parse_amount($config['coursefeeamount'] ?? null);
    }

    public static function get_display_string(int $feeinstanceid, float $fallback, string $currency): string {
        $amount = self::get_for_fee_instance($feeinstanceid);
        if ($amount !== null) {
            return helper::get_cost_as_string($amount, $currency);
        }

        return helper::get_cost_as_string($fallback, $currency);
    }

    public static function get_payment_amount(int $feeinstanceid, float $fallback): float {
        $amount = self::get_for_fee_instance($feeinstanceid);
        return $amount ?? $fallback;
    }

    /**
     * Keep enrol_fee instances in sync with the configured gateway amount.
     */
    public static function sync_enrol_fee_instances(int $accountid, float $amount): void {
        global $DB;

        $instances = $DB->get_records('enrol', [
            'enrol' => 'fee',
            'customint1' => $accountid,
            'status' => ENROL_INSTANCE_ENABLED,
        ]);

        foreach ($instances as $instance) {
            if ((float) $instance->cost === $amount) {
                continue;
            }
            $DB->set_field('enrol', 'cost', $amount, ['id' => $instance->id]);
        }
    }
}
