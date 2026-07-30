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
 * Email enrolled students when a Webex / live-class activity is created or updated.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class liveclass_notifier {

    /**
     * Handle course module created/updated events.
     *
     * @param \core\event\course_module_created|\core\event\course_module_updated $event
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
            error_log('theme_iiidem2 liveclass_notifier: ' . $e->getMessage());
        }
    }

    /**
     * Notify enrolled students if this CM is a live-class / Webex activity.
     *
     * @param int $cmid
     * @param string $action created|updated
     */
    public static function maybe_notify(int $cmid, string $action): void {
        global $DB;

        $cm = get_coursemodule_from_id(null, $cmid, 0, false, IGNORE_MISSING);
        if (!$cm || empty($cm->course) || (int) $cm->course === SITEID) {
            return;
        }

        $details = self::extract_session_details($cm);
        if ($details === null) {
            return;
        }

        // Skip update emails when content did not change (avoids spam on unrelated edits).
        $hash = md5(json_encode([
            $details['name'],
            $details['joinurl'],
            $details['meetingnumber'],
            $details['password'],
            $details['sessiontime'],
            $details['descriptionplain'],
        ]));
        $hashkey = 'liveclassnotifyhash_' . $cmid;
        $previous = get_config('theme_iiidem2', $hashkey);
        if ($action === 'updated' && $previous === $hash) {
            return;
        }
        set_config($hashkey, $hash, 'theme_iiidem2');

        self::send_emails($cm, $details, $action);
    }

    /**
     * Build session details for supported activity types, or null if not a live class.
     *
     * @param \stdClass $cm
     * @return array<string,string>|null
     */
    public static function extract_session_details(\stdClass $cm): ?array {
        global $DB;

        $name = (string) $cm->name;
        $joinurl = '';
        $introhtml = '';
        $sessiontime = '';
        $starttimestamp = 0;

        if ($cm->modname === 'url') {
            $record = $DB->get_record('url', ['id' => $cm->instance], '*', IGNORE_MISSING);
            if (!$record) {
                return null;
            }
            $joinurl = trim((string) $record->externalurl);
            $introhtml = (string) ($record->intro ?? '');
            $name = (string) $record->name;
            $iswebex = (bool) preg_match('#https?://[^/\s]*webex\.com/#i', $joinurl);
            $islivename = self::name_matches_live_class($name);
            if (!$iswebex && !$islivename) {
                return null;
            }
        } else if ($cm->modname === 'webexactivity') {
            $record = $DB->get_record('webexactivity', ['id' => $cm->instance], '*', IGNORE_MISSING);
            if (!$record) {
                return null;
            }
            $name = (string) $record->name;
            $joinurl = trim((string) ($record->meetinglink ?? ''));
            $introhtml = (string) ($record->intro ?? '');
            if (!empty($record->starttime)) {
                $starttimestamp = (int) $record->starttime;
                $sessiontime = userdate($starttimestamp);
                if (!empty($record->endtime) && (int) $record->endtime > $starttimestamp) {
                    $sessiontime .= ' – ' . userdate((int) $record->endtime);
                }
            }
            $meetingnumber = trim((string) ($record->meetingkey ?? ''));
            $password = trim((string) ($record->password ?? ''));
        } else if ($cm->modname === 'page') {
            $record = $DB->get_record('page', ['id' => $cm->instance], '*', IGNORE_MISSING);
            if (!$record || !self::name_matches_live_class((string) $record->name)) {
                return null;
            }
            $name = (string) $record->name;
            $introhtml = (string) ($record->content ?? '');
            $joinurl = self::extract_first_url($introhtml);
            if ($joinurl === '' || !preg_match('#https?://[^/\s]*webex\.com/#i', $joinurl)) {
                // Still allow named live-class pages even without webex host.
                if ($joinurl === '') {
                    $joinurl = (new \moodle_url('/mod/page/view.php', ['id' => $cm->id]))->out(false);
                }
            }
        } else {
            return null;
        }

        $plain = trim(html_to_text($introhtml, 0, false));
        $parsed = self::parse_meeting_fields($plain);

        if ($cm->modname === 'webexactivity') {
            if (empty($parsed['meetingnumber']) && !empty($meetingnumber)) {
                $parsed['meetingnumber'] = $meetingnumber;
            }
            if (empty($parsed['password']) && !empty($password)) {
                $parsed['password'] = $password;
            }
        }

        // Availability / expected completion as fallback time.
        if ($starttimestamp <= 0) {
            if (!empty($cm->availablefrom)) {
                $starttimestamp = (int) $cm->availablefrom;
            } else if (!empty($cm->completionexpected)) {
                $starttimestamp = (int) $cm->completionexpected;
            }
        }
        if ($sessiontime === '') {
            if ($starttimestamp > 0) {
                $sessiontime = userdate($starttimestamp);
            } else if (!empty($parsed['sessiontime'])) {
                $sessiontime = $parsed['sessiontime'];
            }
        }

        if ($joinurl === '' && empty($parsed['meetingnumber'])) {
            return null;
        }

        return [
            'name' => format_string($name),
            'joinurl' => $joinurl,
            'meetingnumber' => $parsed['meetingnumber'],
            'password' => $parsed['password'],
            'sessiontime' => $sessiontime,
            'starttimestamp' => (string) $starttimestamp,
            'descriptionplain' => \core_text::substr($plain, 0, 500),
        ];
    }

    /**
     * Send 1-hour-before reminder emails for upcoming live classes.
     *
     * Intended to run from a scheduled task every few minutes. Sends once per
     * activity + start time when the session is within the next hour.
     *
     * @return int Number of sessions for which reminders were sent
     */
    public static function send_due_reminders(): int {
        $now = time();
        $until = $now + HOURSECS;
        $candidates = self::find_upcoming_liveclass_starts($now, $until);
        $sent = 0;

        foreach ($candidates as $cmid => $startts) {
            $cmid = (int) $cmid;
            $startts = (int) $startts;
            if ($cmid <= 0 || $startts <= $now) {
                continue;
            }

            $remindkey = 'liveclassreminded_' . $cmid;
            $already = get_config('theme_iiidem2', $remindkey);
            if ((string) $already === (string) $startts) {
                continue;
            }

            $cm = get_coursemodule_from_id(null, $cmid, 0, false, IGNORE_MISSING);
            if (!$cm || empty($cm->course) || (int) $cm->course === SITEID) {
                continue;
            }
            if (!empty($cm->deletioninprogress)) {
                continue;
            }

            $details = self::extract_session_details($cm);
            if ($details === null) {
                continue;
            }

            $details['starttimestamp'] = (string) $startts;
            $details['sessiontime'] = userdate($startts);

            self::send_emails($cm, $details, 'reminder');
            set_config($remindkey, (string) $startts, 'theme_iiidem2');
            $sent++;
        }

        return $sent;
    }

    /**
     * Find live-class course modules starting between $after and $until (unix).
     *
     * @param int $after Exclusive lower bound (usually "now")
     * @param int $until Inclusive upper bound (usually now + 1 hour)
     * @return array<int,int> cmid => starttimestamp
     */
    public static function find_upcoming_liveclass_starts(int $after, int $until): array {
        global $DB;

        $found = [];

        // Webex activities with stored starttime.
        $webexmod = $DB->get_field('modules', 'id', ['name' => 'webexactivity'], IGNORE_MISSING);
        if ($webexmod) {
            $sql = "SELECT cm.id AS cmid, w.starttime
                      FROM {webexactivity} w
                      JOIN {course_modules} cm ON cm.instance = w.id AND cm.module = :moduleid
                     WHERE w.starttime > :after AND w.starttime <= :until
                       AND cm.deletioninprogress = 0
                       AND cm.visible = 1";
            $rows = $DB->get_records_sql($sql, [
                'moduleid' => (int) $webexmod,
                'after' => $after,
                'until' => $until,
            ]);
            foreach ($rows as $row) {
                $found[(int) $row->cmid] = (int) $row->starttime;
            }
        }

        // URL / Page activities using Timeline reminder (completionexpected).
        foreach (['url', 'page'] as $modname) {
            $modid = $DB->get_field('modules', 'id', ['name' => $modname], IGNORE_MISSING);
            if (!$modid) {
                continue;
            }
            $sql = "SELECT cm.id AS cmid, cm.completionexpected AS starttime, cm.instance
                      FROM {course_modules} cm
                     WHERE cm.module = :moduleid
                       AND cm.completionexpected > :after
                       AND cm.completionexpected <= :until
                       AND cm.deletioninprogress = 0
                       AND cm.visible = 1";
            $rows = $DB->get_records_sql($sql, [
                'moduleid' => (int) $modid,
                'after' => $after,
                'until' => $until,
            ]);
            foreach ($rows as $row) {
                $cmid = (int) $row->cmid;
                if (isset($found[$cmid])) {
                    continue;
                }
                $cm = get_coursemodule_from_id($modname, $cmid, 0, false, IGNORE_MISSING);
                if (!$cm) {
                    continue;
                }
                // Confirm it is a live-class / Webex activity (not every URL with a date).
                if (self::extract_session_details($cm) === null) {
                    continue;
                }
                $found[$cmid] = (int) $row->starttime;
            }
        }

        return $found;
    }

    /**
     * @param string $name
     * @return bool
     */
    protected static function name_matches_live_class(string $name): bool {
        return (bool) preg_match('/\b(webex|live\s*class|online\s*class|virtual\s*class)\b/i', $name);
    }

    /**
     * @param string $html
     * @return string
     */
    protected static function extract_first_url(string $html): string {
        if (preg_match('/https?:\/\/[^\s"\'<>]+/i', $html, $m)) {
            return html_entity_decode($m[0], ENT_QUOTES, 'UTF-8');
        }
        return '';
    }

    /**
     * Parse meeting number / password / time from plain description text.
     *
     * @param string $plain
     * @return array{meetingnumber:string,password:string,sessiontime:string}
     */
    protected static function parse_meeting_fields(string $plain): array {
        $meetingnumber = '';
        $password = '';
        $sessiontime = '';

        if (preg_match('/meeting\s*(?:number|id|#)?\s*[:\-–]?\s*([0-9][0-9\s\-]{5,})/i', $plain, $m)) {
            $meetingnumber = trim(preg_replace('/\s+/', ' ', $m[1]));
        }
        if (preg_match('/pass(?:word|code)?\s*[:\-–]?\s*([^\s,;]+)/i', $plain, $m)) {
            $password = trim($m[1]);
        }
        if (preg_match('/(?:date|time|when|schedule[d]?)\s*[:\-–]?\s*(.+)$/im', $plain, $m)) {
            $sessiontime = trim($m[1]);
        }

        return [
            'meetingnumber' => $meetingnumber,
            'password' => $password,
            'sessiontime' => $sessiontime,
        ];
    }

    /**
     * Email all enrolled students in the course.
     *
     * @param \stdClass $cm
     * @param array $details
     * @param string $action
     */
    protected static function send_emails(\stdClass $cm, array $details, string $action): void {
        global $CFG, $DB, $SITE;

        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = \context_course::instance((int) $course->id);
        // Prefer enrolled users who cannot manage the course (students / participants).
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
            // Fallback: all enrolled users with an email.
            $recipients = $students;
        }

        $sitename = format_string($SITE->fullname);
        $coursename = format_string($course->fullname, true, ['context' => $context]);
        $activityurl = (new \moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]))->out(false);
        $courseurl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);

        $a = (object) [
            'sitename' => $sitename,
            'coursename' => $coursename,
            'sessionname' => $details['name'],
            'joinurl' => $details['joinurl'] !== '' ? $details['joinurl'] : $activityurl,
            'meetingnumber' => $details['meetingnumber'] !== '' ? $details['meetingnumber'] : get_string('liveclassnotify_na', 'theme_iiidem2'),
            'password' => $details['password'] !== '' ? $details['password'] : get_string('liveclassnotify_na', 'theme_iiidem2'),
            'sessiontime' => $details['sessiontime'] !== '' ? $details['sessiontime'] : get_string('liveclassnotify_tbat', 'theme_iiidem2'),
            'activityurl' => $activityurl,
            'courseurl' => $courseurl,
            'action' => $action,
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
                    $usersubject = get_string('liveclassnotify_createdsubject', 'theme_iiidem2', $persona);
                    $userbody = get_string('liveclassnotify_createdbody', 'theme_iiidem2', $persona);
                } else if ($action === 'reminder') {
                    $usersubject = get_string('liveclassnotify_remindersubject', 'theme_iiidem2', $persona);
                    $userbody = get_string('liveclassnotify_reminderbody', 'theme_iiidem2', $persona);
                } else {
                    $usersubject = get_string('liveclassnotify_updatedsubject', 'theme_iiidem2', $persona);
                    $userbody = get_string('liveclassnotify_updatedbody', 'theme_iiidem2', $persona);
                }
                email_to_user($student, $sender, $usersubject, $userbody);
            }
        } catch (\Throwable $e) {
            error_log('theme_iiidem2 liveclass email failed: ' . $e->getMessage());
        } finally {
            $CFG->debug = $olddebug;
            $CFG->debugdisplay = $olddebugdisplay;
        }
    }
}
