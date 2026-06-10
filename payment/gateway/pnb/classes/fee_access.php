<?php
namespace paygw_pnb;

defined('MOODLE_INTERNAL') || die();

/**
 * Who may pay course fees via PNB (aligned with theme registration profile).
 */
class fee_access {

    /**
     * @param int $userid
     * @return bool
     */
    public static function user_can_pay_course_fee(int $userid): bool {
        global $CFG;

        if ($userid <= 0 || isguestuser($userid)) {
            return false;
        }

        $themefile = $CFG->dirroot . '/theme/iiidem2/classes/registration_profile.php';
        if (is_readable($themefile)) {
            require_once($themefile);
            return \theme_iiidem2\registration_profile::user_requires_course_fee_payment($userid);
        }

        // Fallback: Moodle student role at system level.
        $context = \context_system::instance();
        return user_has_role_assignment($userid, $context->id, 'student');
    }
}
