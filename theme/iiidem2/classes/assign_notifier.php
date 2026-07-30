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
 * Email enrolled students when an assignment with a due date is created/updated,
 * and send a reminder ~24 hours before the due date.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_notifier {

    /**
     * Handle course module created/updated events for assignments.
     *
     * @param \core\event\base $event
     */
    public static function course_module_changed(\core\event\base $event): void {
        try {
            $cmid = (int) $event->objectid;
            if ($cmid <= 0) {
                return;
            }
            $action = ($event->action === 'created') ? 'created' : 'updated';
            self::maybe_notify($cmid, $action);
        } catch (\Throwable $e) {
            error_log('theme_iiidem2 assign_notifier: ' . $e->getMessage());
        }
    }

    /**
     * Notify students if this CM is an assignment with a due date.
     *
     * @param int $cmid
     * @param string $action created|updated
     */
    public static function maybe_notify(int $cmid, string $action): void {
        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm || empty($cm->course) || (int) $cm->course === SITEID) {
            return;
        }

        $details = self::extract_details($cm);
        if ($details === null) {
            return;
        }

        $hash = md5(json_encode([
            $details['name'],
            $details['duedate'],
            $details['allowfrom'],
            $details['introplain'],
        ]));
        $hashkey = 'assignnotifyhash_' . $cmid;
        $previous = get_config('theme_iiidem2', $hashkey);
        if ($action === 'updated' && $previous === $hash) {
            return;
        }
        set_config($hashkey, $hash, 'theme_iiidem2');

        self::send_emails($cm, $details, $action);
    }

    /**
     * @param \stdClass $cm
     * @return array<string,string>|null
     */
    public static function extract_details(\stdClass $cm): ?array {
        global $DB;

        if ($cm->modname !== 'assign') {
            return null;
        }

        $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', IGNORE_MISSING);
        if (!$assign) {
            return null;
        }

        $duedate = (int) ($assign->duedate ?? 0);
        if ($duedate <= 0) {
            // No due date — nothing useful to email about.
            return null;
        }

        $allowfrom = (int) ($assign->allowsubmissionsfromdate ?? 0);
        $introplain = trim(html_to_text((string) ($assign->intro ?? ''), 0, false));

        return [
            'name' => format_string((string) $assign->name),
            'duedate' => userdate($duedate),
            'duetimestamp' => (string) $duedate,
            'allowfrom' => $allowfrom > 0 ? userdate($allowfrom) : '',
            'allowfromtimestamp' => (string) $allowfrom,
            'introplain' => \core_text::substr($introplain, 0, 400),
        ];
    }

    /**
     * Send reminders for assignments due within the next ~24 hours.
     *
     * @return int Number of assignments reminded
     */
    public static function send_due_reminders(): int {
        global $DB;

        $now = time();
        $until = $now + DAYSECS;

        $modid = $DB->get_field('modules', 'id', ['name' => 'assign'], IGNORE_MISSING);
        if (!$modid) {
            return 0;
        }

        $sql = "SELECT cm.id AS cmid, a.duedate
                  FROM {assign} a
                  JOIN {course_modules} cm ON cm.instance = a.id AND cm.module = :moduleid
                 WHERE a.duedate > :after
                   AND a.duedate <= :until
                   AND cm.deletioninprogress = 0
                   AND cm.visible = 1";
        $rows = $DB->get_records_sql($sql, [
            'moduleid' => (int) $modid,
            'after' => $now,
            'until' => $until,
        ]);

        $sent = 0;
        foreach ($rows as $row) {
            $cmid = (int) $row->cmid;
            $duedate = (int) $row->duedate;
            $remindkey = 'assignreminded_' . $cmid;
            $already = get_config('theme_iiidem2', $remindkey);
            if ((string) $already === (string) $duedate) {
                continue;
            }

            $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);
            if (!$cm || empty($cm->course) || (int) $cm->course === SITEID) {
                continue;
            }

            $details = self::extract_details($cm);
            if ($details === null) {
                continue;
            }

            self::send_emails($cm, $details, 'reminder');
            set_config($remindkey, (string) $duedate, 'theme_iiidem2');
            $sent++;
        }

        return $sent;
    }

    /**
     * @param \stdClass $cm
     * @param array $details
     * @param string $action created|updated|reminder
     */
    protected static function send_emails(\stdClass $cm, array $details, string $action): void {
        global $CFG, $DB, $SITE;

        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = \context_course::instance((int) $course->id);
        $students = get_enrolled_users($context, '', 0, 'u.*', null, 0, 0, true);
        if (empty($students)) {
            return;
        }

        $recipients = [];
        foreach ($students as $student) {
            if (has_capability('moodle/course:manageactivities', $context, $student)) {
                continue;
            }
            $recipients[$student->id] = $student;
        }
        if (empty($recipients)) {
            $recipients = $students;
        }

        $sitename = format_string($SITE->fullname);
        $coursename = format_string($course->fullname, true, ['context' => $context]);
        $activityurl = (new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]))->out(false);
        $courseurl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);

        $a = (object) [
            'sitename' => $sitename,
            'coursename' => $coursename,
            'assignmentname' => $details['name'],
            'duedate' => $details['duedate'],
            'allowfrom' => $details['allowfrom'] !== ''
                ? $details['allowfrom']
                : get_string('assignnotify_opennow', 'theme_iiidem2'),
            'activityurl' => $activityurl,
            'courseurl' => $courseurl,
        ];

        $sender = \core_user::get_noreply_user();
        $olddebug = $CFG->debug ?? 0;
        $olddebugdisplay = $CFG->debugdisplay ?? false;
        $CFG->debug = 0;
        $CFG->debugdisplay = false;

        try {
            foreach ($recipients as $student) {
                if (empty($student->email) || !validate_email($student->email)) {
                    continue;
                }
                if (!empty($student->suspended)) {
                    continue;
                }
                $persona = (object) array_merge((array) $a, [
                    'firstname' => $student->firstname,
                    'fullname' => fullname($student),
                ]);
                if ($action === 'created') {
                    $usersubject = get_string('assignnotify_createdsubject', 'theme_iiidem2', $persona);
                    $userbody = get_string('assignnotify_createdbody', 'theme_iiidem2', $persona);
                } else if ($action === 'reminder') {
                    $usersubject = get_string('assignnotify_remindersubject', 'theme_iiidem2', $persona);
                    $userbody = get_string('assignnotify_reminderbody', 'theme_iiidem2', $persona);
                } else {
                    $usersubject = get_string('assignnotify_updatedsubject', 'theme_iiidem2', $persona);
                    $userbody = get_string('assignnotify_updatedbody', 'theme_iiidem2', $persona);
                }
                email_to_user($student, $sender, $usersubject, $userbody);
            }
        } catch (\Throwable $e) {
            error_log('theme_iiidem2 assign email failed: ' . $e->getMessage());
        } finally {
            $CFG->debug = $olddebug;
            $CFG->debugdisplay = $olddebugdisplay;
        }
    }
}
