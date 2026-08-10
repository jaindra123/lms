<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Issues and stores IIIDEM completion certificates.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate_issuer {

    public const FILEAREA = 'certificate';
    public const COMPONENT = 'theme_iiidem2';

    /**
     * Whether certificate auto-issue is enabled.
     */
    public static function is_enabled(): bool {
        return (bool) get_config('theme_iiidem2', 'certificateenabled');
    }

    /**
     * Issue a certificate for a user after an assignment is completed.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $cmid Assignment course-module id (0 if not tied to a cm).
     * @return \stdClass|null Issue record, or null if skipped.
     */
    public static function issue_for_assignment_completion(int $userid, int $courseid, int $cmid = 0): ?\stdClass {
        global $DB;

        if (!self::is_enabled() || $userid <= 0 || $courseid <= SITEID) {
            return null;
        }

        if (!self::assignment_triggers_certificate($cmid, $courseid)) {
            return null;
        }

        $existing = $DB->get_record('theme_iiidem2_cert_issues', [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);
        if ($existing) {
            return $existing;
        }

        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

        $record = (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'code' => self::generate_code($userid, $courseid),
            'studentname' => fullname($user),
            'coursename' => format_string($course->fullname, true, [
                'context' => \context_course::instance($courseid),
            ]),
            'city' => trim((string) ($user->city ?? '')),
            'issuedate' => time(),
            'timecreated' => time(),
        ];

        $record->id = $DB->insert_record('theme_iiidem2_cert_issues', $record);
        certificate_pdf::store_pdf($record);

        return $record;
    }

    /**
     * @param int $cmid
     * @param int $courseid
     * @return bool
     */
    protected static function assignment_triggers_certificate(int $cmid, int $courseid): bool {
        $configured = trim((string) get_config('theme_iiidem2', 'certificateassigncmids'));
        if ($configured === '') {
            // Any assignment completion in the course issues a certificate.
            return true;
        }

        $allowed = [];
        foreach (preg_split('/[\s,;]+/', $configured, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $allowed[$id] = $id;
            }
        }

        return $cmid > 0 && isset($allowed[$cmid]);
    }

    /**
     * @param int $userid
     * @param int $courseid
     * @return string
     */
    protected static function generate_code(int $userid, int $courseid): string {
        return strtoupper(substr(sha1($userid . '-' . $courseid . '-' . time() . '-' . random_string(8)), 0, 12));
    }

    /**
     * Certificates issued to a user.
     *
     * @param int $userid
     * @return array
     */
    public static function get_user_issues(int $userid): array {
        global $DB;

        if (!$DB->get_manager()->table_exists('theme_iiidem2_cert_issues')) {
            return [];
        }

        $records = $DB->get_records('theme_iiidem2_cert_issues', ['userid' => $userid], 'issuedate DESC');
        $rows = [];
        foreach ($records as $record) {
            $rows[] = self::export_issue($record);
        }
        return $rows;
    }

    /**
     * @param \stdClass $record
     * @return array
     */
    public static function export_issue(\stdClass $record): array {
        return [
            'id' => (int) $record->id,
            'code' => $record->code,
            'studentname' => $record->studentname,
            'coursename' => $record->coursename,
            'city' => $record->city,
            'issuedatelabel' => userdate((int) $record->issuedate, get_string('strftimedatefullshort', 'langconfig')),
            'downloadurl' => (new \moodle_url('/theme/iiidem2/certificate/download.php', [
                'id' => $record->id,
            ]))->out(false),
        ];
    }

    /**
     * @param int $issueid
     * @param int $userid Viewer; must own the issue unless teacher/admin.
     * @return \stdClass
     */
    public static function require_issue_for_user(int $issueid, int $userid): \stdClass {
        global $DB;

        $issue = $DB->get_record('theme_iiidem2_cert_issues', ['id' => $issueid], '*', MUST_EXIST);
        if ((int) $issue->userid === $userid || is_siteadmin($userid)) {
            return $issue;
        }

        $coursecontext = \context_course::instance((int) $issue->courseid);
        if (has_capability('moodle/course:viewparticipants', $coursecontext, $userid) ||
                has_capability('mod/assign:grade', $coursecontext, $userid)) {
            return $issue;
        }

        throw new \moodle_exception('nopermissions', 'error', '', 'download certificate');
    }
}
