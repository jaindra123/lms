<?php
/**
 * Privacy provider — no personal data stored by this tombstone.
 *
 * @package local_custom_enroll
 */

namespace local_custom_enroll\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy null provider.
 */
class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * @return string
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
