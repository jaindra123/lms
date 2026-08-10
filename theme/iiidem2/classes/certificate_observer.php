<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Certificate-related event observers.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate_observer {

    /**
     * Issue certificate when an assignment activity is marked complete.
     *
     * @param \core\event\course_module_completion_updated $event
     */
    public static function course_module_completion_updated(
        \core\event\course_module_completion_updated $event
    ): void {
        global $DB, $CFG;

        if (!certificate_issuer::is_enabled()) {
            return;
        }

        $data = $event->get_record_snapshot('course_modules_completion', $event->objectid);
        if (!$data) {
            return;
        }

        $state = (int) ($data->completionstate ?? 0);
        if (!in_array($state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true)) {
            return;
        }

        $cmid = (int) ($data->coursemoduleid ?? 0);
        if ($cmid < 1) {
            return;
        }

        $cm = $DB->get_record('course_modules', ['id' => $cmid], 'id,course,module', IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $assignmoduleid = (int) $DB->get_field('modules', 'id', ['name' => 'assign']);
        if ($assignmoduleid < 1 || (int) $cm->module !== $assignmoduleid) {
            return;
        }

        $userid = (int) ($data->userid ?? $event->relateduserid ?? 0);
        if ($userid < 1) {
            return;
        }

        require_once($CFG->dirroot . '/theme/iiidem2/lib.php');
        certificate_issuer::issue_for_assignment_completion($userid, (int) $cm->course, $cmid);
    }

    /**
     * Fallback: issue when an assignment submission is graded with a passing/positive grade.
     *
     * @param \mod_assign\event\submission_graded $event
     */
    public static function submission_graded(\mod_assign\event\submission_graded $event): void {
        global $CFG;

        if (!certificate_issuer::is_enabled()) {
            return;
        }

        $userid = (int) $event->relateduserid;
        $courseid = (int) $event->courseid;
        $cmid = (int) $event->get_context()->instanceid;
        if ($userid < 1 || $courseid < 1) {
            return;
        }

        require_once($CFG->dirroot . '/theme/iiidem2/lib.php');
        certificate_issuer::issue_for_assignment_completion($userid, $courseid, $cmid);
    }
}
