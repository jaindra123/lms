<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Admin approve / reject workflow for custom registration.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registration_approval {

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const FIELD_STATUS = 'iiidem_reg_status';

    /**
     * Registration status for a user (pending|approved|rejected|'').
     *
     * @param int $userid
     * @return string
     */
    public static function get_status(int $userid): string {
        $status = strtolower(trim(registration_profile::get_profile_value($userid, self::FIELD_STATUS)));
        if (in_array($status, [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED], true)) {
            return $status;
        }
        return '';
    }

    /**
     * Persist registration status.
     *
     * @param int $userid
     * @param string $status
     */
    public static function set_status(int $userid, string $status): void {
        registration_profile::set_profile_value($userid, self::FIELD_STATUS, $status);
    }

    /**
     * Pending registrations awaiting admin decision (newest first).
     *
     * @param int $limit
     * @return \stdClass[]
     */
    public static function get_pending_users(int $limit = 100): array {
        global $CFG, $DB;

        registration_profile::ensure_fields();
        $fieldid = (int) $DB->get_field('user_info_field', 'id', ['shortname' => self::FIELD_STATUS]);
        if ($fieldid <= 0) {
            return [];
        }

        $sql = "SELECT u.*
                  FROM {user} u
                  JOIN {user_info_data} d ON d.userid = u.id AND d.fieldid = :fieldid
                 WHERE u.deleted = 0
                   AND u.mnethostid = :mnet
                   AND d.data = :pending
              ORDER BY u.timecreated DESC";
        return $DB->get_records_sql($sql, [
            'fieldid' => $fieldid,
            'mnet' => $CFG->mnet_localhost_id,
            'pending' => self::STATUS_PENDING,
        ], 0, $limit);
    }

    /**
     * Approve a pending registration: confirm account, set password, enrol, email credentials.
     *
     * @param int $userid
     * @param int $adminid Approving admin user id
     * @return array{success:bool,message:string,password?:string}
     */
    public static function approve(int $userid, int $adminid = 0): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', IGNORE_MISSING);
        if (!$user) {
            return [
                'success' => false,
                'message' => get_string('pendingregusernotfound', 'theme_iiidem2'),
            ];
        }

        $status = self::get_status($userid);
        if ($status === self::STATUS_APPROVED && !empty($user->confirmed)) {
            return [
                'success' => false,
                'message' => get_string('pendingregalreadyapproved', 'theme_iiidem2'),
            ];
        }
        if ($status === self::STATUS_REJECTED) {
            return [
                'success' => false,
                'message' => get_string('pendingregalreadyrejected', 'theme_iiidem2'),
            ];
        }

        $plainpassword = generate_password(12);
        update_internal_user_password($user, $plainpassword);

        $update = (object) [
            'id' => $userid,
            'confirmed' => 1,
            'suspended' => 0,
        ];
        user_update_user($update, false, false);

        self::set_status($userid, self::STATUS_APPROVED);

        registration_enrolment::enrol_user($userid);

        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        \theme_iiidem2_send_approval_emails($user, $plainpassword);

        if ($adminid > 0) {
            error_log('IIIDEM registration approved userid=' . $userid . ' by admin=' . $adminid);
        }

        return [
            'success' => true,
            'message' => get_string('pendingregapprovedok', 'theme_iiidem2', fullname($user)),
            'password' => $plainpassword,
        ];
    }

    /**
     * Reject a pending registration and email the applicant.
     *
     * @param int $userid
     * @param int $adminid
     * @return array{success:bool,message:string}
     */
    public static function reject(int $userid, int $adminid = 0): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/user/lib.php');
        require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], '*', IGNORE_MISSING);
        if (!$user) {
            return [
                'success' => false,
                'message' => get_string('pendingregusernotfound', 'theme_iiidem2'),
            ];
        }

        $status = self::get_status($userid);
        if ($status === self::STATUS_APPROVED && !empty($user->confirmed)) {
            return [
                'success' => false,
                'message' => get_string('pendingregalreadyapproved', 'theme_iiidem2'),
            ];
        }
        if ($status === self::STATUS_REJECTED) {
            return [
                'success' => false,
                'message' => get_string('pendingregalreadyrejected', 'theme_iiidem2'),
            ];
        }

        // Email first — Moodle does not deliver mail to suspended accounts.
        self::set_status($userid, self::STATUS_REJECTED);
        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        \theme_iiidem2_send_rejection_email($user);

        $update = (object) [
            'id' => $userid,
            'confirmed' => 0,
            'suspended' => 1,
        ];
        user_update_user($update, false, false);

        if ($adminid > 0) {
            error_log('IIIDEM registration rejected userid=' . $userid . ' by admin=' . $adminid);
        }

        return [
            'success' => true,
            'message' => get_string('pendingregrejectedok', 'theme_iiidem2', fullname($user)),
        ];
    }
}
