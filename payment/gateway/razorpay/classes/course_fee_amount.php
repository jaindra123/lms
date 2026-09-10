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
        // Charge amount is always the Moodle payable (enrol_fee cost). Gateway
        // coursefeeamount may sync enrol.cost via admin/CLI, but must never
        // undercharge by overriding a higher payable with a lower config value.
        $configured = self::get_for_fee_instance($feeinstanceid);
        if ($configured === null) {
            return $fallback > 0 ? $fallback : 0.0;
        }
        if ($fallback > 0) {
            return max($configured, $fallback);
        }
        return $configured;
    }

    /**
     * Keep enrol_fee instances in sync with the configured gateway amount.
     *
     * Never lowers an existing fee (CDAC #12 underpayment via fee sync).
     */
    public static function sync_enrol_fee_instances(int $accountid, float $amount): void {
        global $DB;

        if ($amount <= 0) {
            return;
        }

        $instances = $DB->get_records('enrol', [
            'enrol' => 'fee',
            'customint1' => $accountid,
            'status' => ENROL_INSTANCE_ENABLED,
        ]);

        foreach ($instances as $instance) {
            $current = (float) $instance->cost;
            if ($current === $amount) {
                continue;
            }
            // Do not sync a lower gateway test fee onto enrol.cost.
            if ($current > 0 && $current > $amount) {
                continue;
            }
            $DB->set_field('enrol', 'cost', $amount, ['id' => $instance->id]);
        }
    }
}
