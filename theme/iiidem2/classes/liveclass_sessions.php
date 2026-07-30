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
 * Collect upcoming live-class / Webex sessions for dashboards.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class liveclass_sessions {

    /**
     * Upcoming live sessions across the given courses.
     *
     * Includes Webex / Zoom / BBB modules plus URL/Page activities detected as live class
     * (Webex link or live-class name) that have a start timestamp (module start or Timeline reminder).
     *
     * @param array $courses Course records
     * @param int $userid Viewing user (visibility checks)
     * @param int|null $horizonseconds Lookahead window (default 14 days)
     * @param int $limit Max sessions to return
     * @return array<int,array<string,mixed>>
     */
    public static function get_upcoming(array $courses, int $userid, ?int $horizonseconds = null, int $limit = 8): array {
        global $DB;

        $now = time();
        $startofday = usergetmidnight($now);
        $endofday = $startofday + DAYSECS;
        $horizon = $now + ($horizonseconds ?? (14 * DAYSECS));
        $sessions = [];
        $seen = [];

        $native = [
            'bigbluebuttonbn' => ['table' => 'bigbluebuttonbn', 'start' => 'openingtime', 'label' => 'BigBlueButton'],
            'zoom' => ['table' => 'zoom', 'start' => 'start_time', 'label' => 'Zoom'],
            'webexactivity' => ['table' => 'webexactivity', 'start' => 'starttime', 'label' => 'Webex'],
        ];

        foreach ($courses as $course) {
            try {
                $modinfo = get_fast_modinfo($course, $userid);
            } catch (\Throwable $e) {
                continue;
            }

            $coursename = format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]);

            foreach ($native as $modname => $meta) {
                $plugin = \core_plugin_manager::instance()->get_plugin_info('mod_' . $modname);
                if (!$plugin || !$plugin->is_enabled()) {
                    continue;
                }
                foreach ($modinfo->get_instances_of($modname) as $cm) {
                    if (!$cm->uservisible) {
                        continue;
                    }
                    $instance = $DB->get_record($meta['table'], ['id' => $cm->instance], '*', IGNORE_MISSING);
                    if (!$instance) {
                        continue;
                    }
                    $startfield = $meta['start'];
                    $start = !empty($instance->$startfield) ? (int) $instance->$startfield : 0;
                    if ($start <= 0 || $start < $now - DAYSECS || $start > $horizon) {
                        continue;
                    }

                    $joinurl = (new \moodle_url('/mod/' . $modname . '/view.php', ['id' => $cm->id]))->out(false);
                    // Prefer external Webex join URL when available.
                    if ($modname === 'webexactivity' && !empty($instance->meetinglink)) {
                        $joinurl = trim((string) $instance->meetinglink);
                    }

                    $sessions[] = self::format_session([
                        'cmid' => (int) $cm->id,
                        'modname' => $modname,
                        'modlabel' => $meta['label'],
                        'title' => format_string($cm->name, true, ['context' => \context_module::instance($cm->id)]),
                        'coursename' => $coursename,
                        'courseid' => (int) $course->id,
                        'start' => $start,
                        'joinurl' => $joinurl,
                        'istoday' => ($start >= $startofday && $start < $endofday),
                    ]);
                    $seen[(int) $cm->id] = true;
                }
            }

            // URL / Page live classes (Webex link or live-class name + Timeline/start date).
            foreach (['url', 'page'] as $modname) {
                foreach ($modinfo->get_instances_of($modname) as $cm) {
                    if (!$cm->uservisible || isset($seen[(int) $cm->id])) {
                        continue;
                    }
                    $cmrecord = get_coursemodule_from_id($modname, $cm->id, 0, false, IGNORE_MISSING);
                    if (!$cmrecord) {
                        continue;
                    }
                    // Ensure completionexpected from cm_info is available for extract.
                    if (empty($cmrecord->completionexpected) && !empty($cm->completionexpected)) {
                        $cmrecord->completionexpected = (int) $cm->completionexpected;
                    }

                    $details = liveclass_notifier::extract_session_details($cmrecord);
                    if ($details === null) {
                        continue;
                    }
                    $start = (int) ($details['starttimestamp'] ?? 0);
                    if ($start <= 0 || $start < $now - DAYSECS || $start > $horizon) {
                        continue;
                    }

                    $joinurl = trim((string) ($details['joinurl'] ?? ''));
                    if ($joinurl === '') {
                        $joinurl = (new \moodle_url('/mod/' . $modname . '/view.php', ['id' => $cm->id]))->out(false);
                    }

                    $sessions[] = self::format_session([
                        'cmid' => (int) $cm->id,
                        'modname' => $modname,
                        'modlabel' => $modname === 'url' ? 'Webex URL' : 'Live class',
                        'title' => $details['name'] !== ''
                            ? $details['name']
                            : format_string($cm->name, true, ['context' => \context_module::instance($cm->id)]),
                        'coursename' => $coursename,
                        'courseid' => (int) $course->id,
                        'start' => $start,
                        'joinurl' => $joinurl,
                        'istoday' => ($start >= $startofday && $start < $endofday),
                    ]);
                    $seen[(int) $cm->id] = true;
                }
            }
        }

        usort($sessions, static function(array $a, array $b): int {
            return $a['sorttime'] <=> $b['sorttime'];
        });

        return array_slice($sessions, 0, max(1, $limit));
    }

    /**
     * Split sessions into today / upcoming lists for student live-classes panel.
     *
     * @param array $sessions
     * @return array{today:array,upcoming:array,hasjoinnow:bool,joinnowurl:string}
     */
    public static function split_today_upcoming(array $sessions): array {
        $today = [];
        $upcoming = [];
        $now = time();

        foreach ($sessions as $session) {
            $row = [
                'title' => $session['title'],
                'meta' => $session['coursename'] . ' · ' . $session['modlabel'],
                'datetime' => $session['datetime'],
                'date' => $session['date'],
                'time' => $session['time'],
                'status' => $session['status'],
                'statusclass' => $session['statusclass'],
                'joinurl' => $session['joinurl'],
                'hasjoinurl' => $session['joinurl'] !== '',
            ];
            if (!empty($session['istoday'])) {
                $today[] = $row;
            } else if ((int) $session['sorttime'] >= $now) {
                $upcoming[] = $row;
            } else {
                // Started recently (within lookback) — still show under today if same calendar day handled above.
                $upcoming[] = $row;
            }
        }

        $joinnowurl = '';
        if (!empty($sessions[0]['joinurl'])) {
            $joinnowurl = $sessions[0]['joinurl'];
        }

        return [
            'today' => $today,
            'upcoming' => $upcoming,
            'hasjoinnow' => $joinnowurl !== '',
            'joinnowurl' => $joinnowurl,
        ];
    }

    /**
     * @param array $raw
     * @return array<string,mixed>
     */
    protected static function format_session(array $raw): array {
        $start = (int) $raw['start'];
        $now = time();
        $istoday = !empty($raw['istoday']);
        $status = self::session_status($start, $now, $istoday);
        $date = userdate($start, get_string('strftimedatefullshort', 'core_langconfig'));
        $time = userdate($start, get_string('strftimetime', 'core_langconfig'));

        return [
            'cmid' => (int) $raw['cmid'],
            'modname' => (string) $raw['modname'],
            'modlabel' => (string) $raw['modlabel'],
            'title' => (string) $raw['title'],
            'coursename' => (string) $raw['coursename'],
            'coursefullname' => (string) $raw['coursename'], // Alias used by older dashboard code.
            'courseid' => (int) ($raw['courseid'] ?? 0),
            'date' => $date,
            'time' => $time,
            'datetime' => trim($date . ' · ' . $time),
            'istoday' => $istoday,
            'joinurl' => (string) ($raw['joinurl'] ?? ''),
            'sorttime' => $start,
            'status' => $status['label'],
            'statusclass' => $status['class'],
        ];
    }

    /**
     * @param int $start
     * @param int $now
     * @param bool $istoday
     * @return array{label:string,class:string}
     */
    protected static function session_status(int $start, int $now, bool $istoday): array {
        if ($start > 0 && $start <= $now && $start >= $now - HOURSECS) {
            return ['label' => get_string('dashboardstatuslive', 'theme_iiidem2'), 'class' => 'success'];
        }
        if ($istoday) {
            return ['label' => get_string('dashboardstatustoday', 'theme_iiidem2'), 'class' => 'teal'];
        }
        if ($start > $now && $start < $now + (3 * DAYSECS)) {
            return ['label' => get_string('dashboardstatusrsvp', 'theme_iiidem2'), 'class' => 'navy'];
        }
        return ['label' => get_string('dashboardstatussoon', 'theme_iiidem2'), 'class' => 'muted'];
    }
}
