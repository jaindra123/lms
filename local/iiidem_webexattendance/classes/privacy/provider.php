<?php
/**
 * Privacy provider (null — no user personal data stored beyond mapping metadata).
 *
 * @package local_iiidem_webexattendance
 */

namespace local_iiidem_webexattendance\privacy;

defined('MOODLE_INTERNAL') || die();

class provider implements \core_privacy\local\metadata\null_provider {
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
