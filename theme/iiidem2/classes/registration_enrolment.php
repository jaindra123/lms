<?php
// This file is part of Moodle - http://moodle.org/.

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates course enrolments for users created by the custom registration form.
 *
 * @package theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registration_enrolment {

    /**
     * Configured courses that receive newly registered users.
     *
     * @return int[]
     */
    public static function get_target_course_ids(): array {
        $configured = trim((string) get_config('theme_iiidem2', 'registrationcourseids'));
        if ($configured === '') {
            $configured = '4';
        }

        $courseids = [];
        foreach (preg_split('/[\s,;]+/', $configured, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            $courseid = (int) $value;
            if ($courseid > SITEID) {
                $courseids[$courseid] = $courseid;
            }
        }

        return array_values($courseids);
    }

    /**
     * Enrol a registered user into every configured course.
     *
     * Payment-required users receive a suspended enrolment in the course fee
     * instance. Moodle's fee payment callback activates that same enrolment
     * after successful payment. Exempt users receive an active manual
     * enrolment.
     *
     * Errors are logged without invalidating an account already created.
     *
     * @param int $userid
     * @return array<int, string> course id => active|pending|skipped
     */
    public static function enrol_user(int $userid): array {
        global $CFG, $DB;

        require_once($CFG->libdir . '/enrollib.php');

        $results = [];
        $requirespayment = registration_profile::user_requires_course_fee_payment($userid);

        foreach (self::get_target_course_ids() as $courseid) {
            try {
                $course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);
                if (!$course) {
                    self::log_skip($userid, $courseid, 'course does not exist');
                    $results[$courseid] = 'skipped';
                    continue;
                }

                if ($requirespayment) {
                    self::suspend_manual_enrolment($userid, $course);
                    $results[$courseid] = self::create_pending_fee_enrolment($userid, $course);
                } else {
                    // Admin "Allow without payment course" only:
                    // grant active access and drop any pending fee enrolment.
                    self::remove_pending_fee_enrolment($userid, $course);
                    $results[$courseid] = self::create_active_manual_enrolment($userid, $course);
                }
            } catch (\Throwable $exception) {
                self::log_skip($userid, $courseid, $exception->getMessage());
                $results[$courseid] = 'skipped';
            }
        }

        return $results;
    }

    /**
     * Reconcile configured course access after an administrator updates a user.
     *
     * @param \core\event\user_updated $event
     */
    public static function user_updated(\core\event\user_updated $event): void {
        $userid = (int) $event->objectid;
        if ($userid > 0) {
            self::enrol_user($userid);
        }
    }

    /**
     * Add a suspended enrolment to the enabled paid enrolment instance.
     *
     * @param int $userid
     * @param \stdClass $course
     * @return string
     */
    private static function create_pending_fee_enrolment(int $userid, \stdClass $course): string {
        global $DB;

        $feeinstance = $DB->get_record_select(
            'enrol',
            'courseid = :courseid AND enrol = :enrol AND status = :status AND cost > 0',
            [
                'courseid' => $course->id,
                'enrol' => 'fee',
                'status' => ENROL_INSTANCE_ENABLED,
            ],
            '*',
            IGNORE_MULTIPLE
        );
        if (!$feeinstance) {
            self::log_skip($userid, (int) $course->id, 'enabled course fee enrolment is not configured');
            return 'skipped';
        }

        $existing = $DB->get_record('user_enrolments', [
            'enrolid' => $feeinstance->id,
            'userid' => $userid,
        ]);
        if ($existing && (int) $existing->status === ENROL_USER_ACTIVE) {
            return 'active';
        }

        $feeplugin = enrol_get_plugin('fee');
        if (!$feeplugin) {
            self::log_skip($userid, (int) $course->id, 'course fee enrolment plugin is unavailable');
            return 'skipped';
        }

        $feeplugin->enrol_user(
            $feeinstance,
            $userid,
            (int) $feeinstance->roleid,
            0,
            0,
            ENROL_USER_SUSPENDED
        );

        return 'pending';
    }

    /**
     * Add an active manual enrolment for a fee-waived user (admin-authorized).
     *
     * Always enrols as student. Teaching roles must be assigned by an
     * administrator — never derived from self-selected occupation.
     *
     * @param int $userid
     * @param \stdClass $course
     * @return string
     */
    private static function create_active_manual_enrolment(int $userid, \stdClass $course): string {
        global $DB;

        $manualinstance = $DB->get_record('enrol', [
            'courseid' => $course->id,
            'enrol' => 'manual',
            'status' => ENROL_INSTANCE_ENABLED,
        ], '*', IGNORE_MULTIPLE);
        if (!$manualinstance) {
            self::log_skip($userid, (int) $course->id, 'enabled manual enrolment is not configured');
            return 'skipped';
        }

        $manualplugin = enrol_get_plugin('manual');
        if (!$manualplugin) {
            self::log_skip($userid, (int) $course->id, 'manual enrolment plugin is unavailable');
            return 'skipped';
        }

        $roleid = self::resolve_enrolment_role_id($userid, $manualinstance);
        if ($roleid <= 0) {
            self::log_skip($userid, (int) $course->id, 'enrolment role is not configured');
            return 'skipped';
        }

        $manualplugin->enrol_user($manualinstance, $userid, $roleid, 0, 0, ENROL_USER_ACTIVE);

        return 'active';
    }

    /**
     * Role to assign on manual enrolment (always student archetype from instance).
     *
     * @param int $userid
     * @param \stdClass $manualinstance
     * @return int
     */
    private static function resolve_enrolment_role_id(int $userid, \stdClass $manualinstance): int {
        unset($userid); // Reserved for future policy; role is never client-driven.

        $roleid = (int) $manualinstance->roleid;
        if ($roleid > 0) {
            return $roleid;
        }

        $studentroles = get_archetype_roles('student');
        $studentrole = reset($studentroles);
        return $studentrole ? (int) $studentrole->id : 0;
    }

    /**
     * No-op: teaching roles are never auto-assigned from registration profile.
     *
     * Kept for call-site compatibility. Admins must assign editingteacher via
     * Moodle enrolments / role assignments after verifying the applicant.
     *
     * @param int $userid
     */
    public static function ensure_instructor_roles(int $userid): void {
        unset($userid);
    }

    /**
     * Remove a suspended (unpaid) fee enrolment when the user becomes fee-exempt.
     *
     * Already-active paid fee enrolments are left in place.
     *
     * @param int $userid
     * @param \stdClass $course
     */
    private static function remove_pending_fee_enrolment(int $userid, \stdClass $course): void {
        global $DB;

        $feeinstance = $DB->get_record_select(
            'enrol',
            'courseid = :courseid AND enrol = :enrol AND status = :status AND cost > 0',
            [
                'courseid' => $course->id,
                'enrol' => 'fee',
                'status' => ENROL_INSTANCE_ENABLED,
            ],
            '*',
            IGNORE_MULTIPLE
        );
        if (!$feeinstance) {
            return;
        }

        $existing = $DB->get_record('user_enrolments', [
            'enrolid' => $feeinstance->id,
            'userid' => $userid,
        ]);
        if (!$existing || (int) $existing->status !== ENROL_USER_SUSPENDED) {
            return;
        }

        $feeplugin = enrol_get_plugin('fee');
        if ($feeplugin) {
            $feeplugin->unenrol_user($feeinstance, $userid);
        }
    }

    /**
     * Suspend an active manual enrolment when the user is changed back to a
     * payment-required category.
     *
     * @param int $userid
     * @param \stdClass $course
     */
    private static function suspend_manual_enrolment(int $userid, \stdClass $course): void {
        global $DB;

        $manualinstance = $DB->get_record('enrol', [
            'courseid' => $course->id,
            'enrol' => 'manual',
            'status' => ENROL_INSTANCE_ENABLED,
        ], '*', IGNORE_MULTIPLE);
        if (!$manualinstance) {
            return;
        }

        $existing = $DB->get_record('user_enrolments', [
            'enrolid' => $manualinstance->id,
            'userid' => $userid,
        ]);
        if (!$existing || (int) $existing->status === ENROL_USER_SUSPENDED) {
            return;
        }

        $manualplugin = enrol_get_plugin('manual');
        if ($manualplugin) {
            $manualplugin->update_user_enrol(
                $manualinstance,
                $userid,
                ENROL_USER_SUSPENDED
            );
        }
    }

    /**
     * Record a non-fatal registration enrolment problem.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $reason
     */
    private static function log_skip(int $userid, int $courseid, string $reason): void {
        error_log(sprintf(
            'IIIDEM registration enrolment skipped: user=%d course=%d reason=%s',
            $userid,
            $courseid,
            $reason
        ));
    }
}
