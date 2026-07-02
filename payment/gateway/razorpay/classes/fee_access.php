<?php
namespace paygw_razorpay;

defined('MOODLE_INTERNAL') || die();

class fee_access {

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

        $context = \context_system::instance();
        return user_has_role_assignment($userid, $context->id, 'student');
    }
}
