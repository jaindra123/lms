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
 * Builds Mustache context for the student role dashboard.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_dashboard {

    /**
     * @param int|null $userid
     * @return array
     */
    public static function get_context(?int $userid = null): array {
        global $CFG, $PAGE;

        if ($userid === null) {
            global $USER;
            $userid = $USER->id;
        }

        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $courses = self::get_enrolled_courses($userid);
        $liveclasses = self::get_live_classes($courses, $userid);
        $notifications = self::get_notifications($userid);
        $upcomingsessions = self::get_upcoming_sessions($liveclasses);
        $assignmentsgrades = self::get_assignments_grades($courses, $userid);
        $joinnowurl = !empty($upcomingsessions[0]['joinurl']) ? $upcomingsessions[0]['joinurl'] : '';

        $userpicture = new \user_picture($user);
        $userpicture->size = 80;

        return [
            'firstname' => $user->firstname,
            'useravatarurl' => $userpicture->get_url($PAGE)->out(false),
            'dashboardurl' => \theme_iiidem2_get_dashboard_url()->out(false),
            'statcards' => self::get_stat_cards($courses, $userid, $liveclasses),
            'sidenav' => self::get_sidebar_nav($courses, $userid),
            'upcomingsessions' => $upcomingsessions,
            'hasupcomingsessions' => !empty($upcomingsessions),
            'hasjoinnow' => $joinnowurl !== '',
            'joinnowurl' => $joinnowurl,
            'assignmentsgrades' => $assignmentsgrades,
            'hasassignmentsgrades' => !empty($assignmentsgrades),
            'notificationcount' => count($notifications),
            'hasnotifications' => !empty($notifications),
            'calendarurl' => (new \moodle_url('/calendar/view.php'))->out(false),
            'badgesurl' => (new \moodle_url('/badges/mybadges.php'))->out(false),
            'gradesurl' => (new \moodle_url('/grade/report/overview/index.php'))->out(false),
            'messagesurl' => (new \moodle_url('/message/index.php'))->out(false),
            'searchurl' => (new \moodle_url('/course/search.php'))->out(false),
        ];
    }

    /**
     * @param int $userid
     * @return array
     */
    protected static function get_enrolled_courses(int $userid): array {
        $courses = enrol_get_users_courses($userid, true, '*', 'visible DESC, fullname ASC');
        unset($courses[SITEID]);
        return array_values($courses);
    }

    /**
     * @param array $courses
     * @param int $userid
     * @return array
     */
    protected static function get_course_cards(array $courses, int $userid): array {
        global $CFG;

        $cards = [];
        $limit = 4;

        foreach ($courses as $course) {
            if (count($cards) >= $limit) {
                break;
            }
            if (!$course->visible) {
                continue;
            }

            $courseimage = '';
            try {
                $courseobj = new \core_course_list_element($course);
                foreach ($courseobj->get_course_overviewfiles() as $file) {
                    if ($file->is_valid_image()) {
                        $courseimage = \moodle_url::make_pluginfile_url(
                            $file->get_contextid(),
                            $file->get_component(),
                            $file->get_filearea(),
                            null,
                            $file->get_filepath(),
                            $file->get_filename()
                        )->out(false);
                        break;
                    }
                }
            } catch (\Exception $e) {
                // Ignore missing overview files.
            }

            if ($courseimage === '') {
                $courseimage = $CFG->wwwroot . '/theme/iiidem2/pix/ai.jpg';
            }

            $percent = \core_completion\progress::get_course_progress_percentage($course, $userid);
            $progress = $percent !== null ? (int) round($percent) : null;

            $cards[] = [
                'fullname' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
                'shortname' => format_string($course->shortname, true, ['context' => \context_course::instance($course->id)]),
                'courseimage' => $courseimage,
                'viewurl' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'hasprogress' => $progress !== null,
                'progress' => $progress ?? 0,
                'progresslabel' => $progress !== null
                    ? get_string('dashboardprogresslabel', 'theme_iiidem2', $progress)
                    : get_string('dashboardnoprogress', 'theme_iiidem2'),
            ];
        }

        return $cards;
    }

    /**
     * Top summary cards (course progress, average grade, next session).
     *
     * @param array $courses
     * @param int $userid
     * @param array $liveclasses
     * @return array
     */
    protected static function get_stat_cards(array $courses, int $userid, array $liveclasses): array {
        $sumprogress = 0;
        $progresscount = 0;

        foreach ($courses as $course) {
            $percent = \core_completion\progress::get_course_progress_percentage($course, $userid);
            if ($percent === null) {
                continue;
            }
            $progresscount++;
            $sumprogress += $percent;
        }

        $avgprogress = $progresscount > 0 ? (int) round($sumprogress / $progresscount) : 0;
        $avggrade = self::get_average_grade_percentage($userid, $courses);
        $nextsession = self::get_next_session_countdown($liveclasses);

        return [
            [
                'value' => $progresscount > 0 ? $avgprogress . '%' : '—',
                'label' => get_string('dashboardstatcourseprogress', 'theme_iiidem2'),
                'accent' => 'orange',
            ],
            [
                'value' => $avggrade !== null ? $avggrade . '%' : '—',
                'label' => get_string('dashboardstataveragegrade', 'theme_iiidem2'),
                'accent' => 'teal',
            ],
            [
                'value' => $nextsession,
                'label' => get_string('dashboardstatnextsession', 'theme_iiidem2'),
                'accent' => 'navy',
            ],
        ];
    }

    /**
     * URL for the live virtual classroom mod/page (sidebar "Live Sessions").
     *
     * @param int $userid
     * @return \moodle_url
     */
    public static function get_live_class_page_url(int $userid): \moodle_url {
        $fallback = \theme_iiidem2_get_dashboard_url();
        $fallback->set_anchor('upcoming-sessions');

        $resolvecmid = static function(int $cmid, int $userid): ?\moodle_url {
            $cmrecord = get_coursemodule_from_id('page', $cmid, 0, false, IGNORE_MISSING);
            if (!$cmrecord) {
                return null;
            }
            try {
                $course = get_course($cmrecord->course);
                $modinfo = get_fast_modinfo($course, $userid);
                $cm = $modinfo->get_cm($cmid);
                if ($cm->uservisible) {
                    return new \moodle_url('/mod/page/view.php', ['id' => $cmid]);
                }
            } catch (\Exception $e) {
                return null;
            }
            return null;
        };

        $raw = get_config('theme_iiidem2', 'liveclasscmids');
        if ($raw !== false && $raw !== null && trim((string) $raw) !== '') {
            foreach (preg_split('/\s*,\s*/', trim((string) $raw)) as $part) {
                if ($part === '' || !is_numeric($part)) {
                    continue;
                }
                $url = $resolvecmid((int) $part, $userid);
                if ($url) {
                    return $url;
                }
            }
        }

        foreach (self::get_enrolled_courses($userid) as $course) {
            try {
                $modinfo = get_fast_modinfo($course, $userid);
            } catch (\Exception $e) {
                continue;
            }
            foreach ($modinfo->get_instances_of('page') as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                if (self::page_name_matches_live_class($cm->name)) {
                    return new \moodle_url('/mod/page/view.php', ['id' => $cm->id]);
                }
            }
        }

        return $fallback;
    }

    /**
     * @param string $name
     * @return bool
     */
    protected static function page_name_matches_live_class(string $name): bool {
        if (trim($name) === '') {
            return false;
        }
        return (bool) preg_match('/\b(webex|live\s*class|online\s*class|virtual\s*class|bbb)\b/i', $name);
    }

    /**
     * @param array $courses
     * @param int $userid
     * @return array
     */
    protected static function get_sidebar_nav(array $courses, int $userid): array {
        $firstcourseid = !empty($courses[0]) ? (int) $courses[0]->id : 0;
        $courseurl = $firstcourseid
            ? (new \moodle_url('/course/view.php', ['id' => $firstcourseid]))->out(false)
            : (new \moodle_url('/my/courses.php'))->out(false);

        $items = [
            ['key' => 'dashboard', 'icon' => 'fa-gauge-high', 'label' => get_string('dashboard', 'theme_iiidem2'),
                'url' => \theme_iiidem2_get_dashboard_url()->out(false), 'active' => true],
            ['key' => 'livesessions', 'icon' => 'fa-video', 'label' => get_string('dashboardnavlivesessions', 'theme_iiidem2'),
                'url' => self::get_live_class_page_url($userid)->out(false), 'active' => false],
            ['key' => 'curriculum', 'icon' => 'fa-book-open', 'label' => get_string('dashboardnavcurriculum', 'theme_iiidem2'),
                'url' => $courseurl, 'active' => false],
            ['key' => 'assignments', 'icon' => 'fa-clipboard-list', 'label' => get_string('dashboardnavassignments', 'theme_iiidem2'),
                'url' => (new \moodle_url('/calendar/view.php'))->out(false), 'active' => false],
            ['key' => 'discussions', 'icon' => 'fa-comments', 'label' => get_string('dashboardnavdiscussions', 'theme_iiidem2'),
                'url' => (new \moodle_url('/my/courses.php'))->out(false), 'active' => false],
            ['key' => 'recordings', 'icon' => 'fa-circle-play', 'label' => get_string('dashboardnavrecordings', 'theme_iiidem2'),
                'url' => $courseurl, 'active' => false],
            ['key' => 'grades', 'icon' => 'fa-pen', 'label' => get_string('dashboardnavgrades', 'theme_iiidem2'),
                'url' => (new \moodle_url('/grade/report/overview/index.php'))->out(false), 'active' => false],
            ['key' => 'certificate', 'icon' => 'fa-certificate', 'label' => get_string('dashboardnavcertificate', 'theme_iiidem2'),
                'url' => (new \moodle_url('/badges/mybadges.php'))->out(false), 'active' => false],
        ];

        return $items;
    }

    /**
     * @param array $liveclasses
     * @return array
     */
    protected static function get_upcoming_sessions(array $liveclasses): array {
        $sessions = [];
        $now = time();

        foreach (array_slice($liveclasses, 0, 4) as $session) {
            $sorttime = $session['sorttime'] ?? 0;
            $status = self::get_session_status($sorttime, $now, !empty($session['istoday']));
            $sessions[] = [
                'title' => $session['title'],
                'meta' => trim($session['date'] . ' · ' . $session['time']),
                'status' => $status['label'],
                'statusclass' => $status['class'],
                'joinurl' => $session['joinurl'] ?? '',
            ];
        }

        return $sessions;
    }

    /**
     * @param array $courses
     * @param int $userid
     * @return array
     */
    protected static function get_assignments_grades(array $courses, int $userid): array {
        global $DB;

        $items = [];
        $tasks = self::get_upcoming_tasks($courses, $userid);

        foreach (array_slice($tasks, 0, 4) as $task) {
            $status = self::get_assignment_status($task, $userid, $DB);
            $items[] = [
                'title' => $task['title'],
                'meta' => get_string('dashboarddueon', 'theme_iiidem2', $task['date']),
                'status' => $status['label'],
                'statusclass' => $status['class'],
                'url' => $task['url'],
            ];
        }

        if (count($items) < 4) {
            $grades = $DB->get_records_sql(
                "SELECT gg.id, gg.finalgrade, gi.grademax, gi.itemname, gi.itemtype, c.fullname AS coursename, c.id AS courseid
                   FROM {grade_grades} gg
                   JOIN {grade_items} gi ON gi.id = gg.itemid
                   JOIN {course} c ON c.id = gi.courseid
                  WHERE gg.userid = :userid
                    AND gg.finalgrade IS NOT NULL
                    AND gi.itemtype != 'category'
               ORDER BY gg.timemodified DESC",
                ['userid' => $userid],
                0,
                4 - count($items)
            );

            foreach ($grades as $g) {
                $items[] = [
                    'title' => format_string($g->itemname),
                    'meta' => format_string($g->coursename),
                    'status' => get_string('dashboardstatusgraded', 'theme_iiidem2'),
                    'statusclass' => 'success',
                    'url' => (new \moodle_url('/grade/report/user/index.php', ['id' => $g->courseid]))->out(false),
                ];
            }
        }

        return array_slice($items, 0, 4);
    }

    /**
     * @param int $userid
     * @param array $courses
     * @return int|null
     */
    protected static function get_average_grade_percentage(int $userid, array $courses): ?int {
        global $DB;

        if (empty($courses)) {
            return null;
        }

        $courseids = array_map(static function($c) {
            return (int) $c->id;
        }, $courses);
        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;

        $avg = $DB->get_field_sql(
            "SELECT AVG((gg.finalgrade / gi.grademax) * 100)
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid
              WHERE gg.userid = :userid
                AND gg.finalgrade IS NOT NULL
                AND gi.grademax > 0
                AND gi.itemtype != 'category'
                AND gi.courseid $insql",
            $params
        );

        return $avg !== false ? (int) round((float) $avg) : null;
    }

    /**
     * @param array $liveclasses
     * @return string
     */
    protected static function get_next_session_countdown(array $liveclasses): string {
        if (empty($liveclasses)) {
            return '—';
        }

        $now = time();
        $next = null;
        foreach ($liveclasses as $session) {
            $start = (int) ($session['sorttime'] ?? 0);
            if ($start >= $now && ($next === null || $start < $next)) {
                $next = $start;
            }
        }

        if ($next === null) {
            return get_string('dashboardsessiontoday', 'theme_iiidem2');
        }

        $diff = $next - $now;
        $days = (int) floor($diff / DAYSECS);
        $hours = (int) floor(($diff % DAYSECS) / HOURSECS);

        if ($days > 0) {
            return $days . 'd ' . $hours . 'h';
        }
        if ($hours > 0) {
            return $hours . 'h';
        }

        return get_string('dashboardsessionsoon', 'theme_iiidem2');
    }

    /**
     * @param int $start
     * @param int $now
     * @param bool $istoday
     * @return array{label: string, class: string}
     */
    protected static function get_session_status(int $start, int $now, bool $istoday): array {
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

    /**
     * @param array $task
     * @param int $userid
     * @param \moodle_database $DB
     * @return array{label: string, class: string}
     */
    protected static function get_assignment_status(array $task, int $userid, \moodle_database $DB): array {
        if ($task['type'] === 'assign') {
            $parts = parse_url($task['url']);
            parse_str($parts['query'] ?? '', $query);
            $cmid = (int) ($query['id'] ?? 0);
            if ($cmid) {
                $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);
                if ($cm) {
                    $assign = $DB->get_record('assign', ['id' => $cm->instance], 'id', IGNORE_MISSING);
                    if ($assign) {
                        $submission = $DB->get_record('assign_submission', [
                            'assignment' => $assign->id,
                            'userid' => $userid,
                            'latest' => 1,
                        ], 'status', IGNORE_MISSING);
                        if ($submission && $submission->status === 'submitted') {
                            return ['label' => get_string('dashboardstatusgraded', 'theme_iiidem2'), 'class' => 'success'];
                        }
                    }
                }
            }
            return ['label' => get_string('dashboardstatuspending', 'theme_iiidem2'), 'class' => 'orange'];
        }

        return ['label' => get_string('dashboardstatusnotopen', 'theme_iiidem2'), 'class' => 'muted'];
    }

    /**
     * @param array $courses
     * @param int $userid
     * @return array
     */
    protected static function get_upcoming_tasks(array $courses, int $userid): array {
        global $DB;

        $now = time();
        $horizon = $now + (60 * 60 * 24 * 30);
        $tasks = [];

        foreach ($courses as $course) {
            try {
                $modinfo = get_fast_modinfo($course, $userid);
            } catch (\Exception $e) {
                continue;
            }

            $coursename = format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]);

            foreach ($modinfo->get_instances_of('assign') as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                $assign = $DB->get_record('assign', ['id' => $cm->instance], 'id, name, duedate, course', IGNORE_MISSING);
                if (!$assign || empty($assign->duedate) || $assign->duedate < $now || $assign->duedate > $horizon) {
                    continue;
                }
                $tasks[] = [
                    'type' => 'assign',
                    'typelabel' => get_string('dashboardtypeassign', 'theme_iiidem2'),
                    'title' => format_string($assign->name, true, ['context' => \context_module::instance($cm->id)]),
                    'coursefullname' => $coursename,
                    'date' => userdate($assign->duedate, get_string('strftimedatefullshort', 'core_langconfig')),
                    'sorttime' => $assign->duedate,
                    'url' => (new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]))->out(false),
                ];
            }

            foreach ($modinfo->get_instances_of('quiz') as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                $quiz = $DB->get_record('quiz', ['id' => $cm->instance], 'id, name, timeclose, course', IGNORE_MISSING);
                if (!$quiz || empty($quiz->timeclose) || $quiz->timeclose < $now || $quiz->timeclose > $horizon) {
                    continue;
                }
                $tasks[] = [
                    'type' => 'quiz',
                    'typelabel' => get_string('dashboardtypequiz', 'theme_iiidem2'),
                    'title' => format_string($quiz->name, true, ['context' => \context_module::instance($cm->id)]),
                    'coursefullname' => $coursename,
                    'date' => userdate($quiz->timeclose, get_string('strftimedatefullshort', 'core_langconfig')),
                    'sorttime' => $quiz->timeclose,
                    'url' => (new \moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false),
                ];
            }
        }

        usort($tasks, static function(array $a, array $b): int {
            return $a['sorttime'] <=> $b['sorttime'];
        });

        $tasks = array_slice($tasks, 0, 8);
        foreach ($tasks as &$task) {
            unset($task['sorttime']);
        }

        return $tasks;
    }

    /**
     * @param array $courses
     * @param int $userid
     * @return array
     */
    protected static function get_live_classes(array $courses, int $userid): array {
        global $DB;

        $now = time();
        $startofday = usergetmidnight($now);
        $endofday = $startofday + DAYSECS;
        $horizon = $now + (60 * 60 * 24 * 14);
        $sessions = [];

        $livemods = [
            'bigbluebuttonbn' => ['table' => 'bigbluebuttonbn', 'start' => 'openingtime', 'end' => 'closingtime', 'label' => 'BigBlueButton'],
            'zoom' => ['table' => 'zoom', 'start' => 'start_time', 'end' => 'duration', 'label' => 'Zoom'],
            'webexactivity' => ['table' => 'webexactivity', 'start' => 'starttime', 'end' => 'endtime', 'label' => 'Webex'],
        ];

        foreach ($courses as $course) {
            try {
                $modinfo = get_fast_modinfo($course, $userid);
            } catch (\Exception $e) {
                continue;
            }

            $coursename = format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]);

            foreach ($livemods as $modname => $meta) {
                $plugin = \core_plugin_manager::instance()->get_plugin_info('mod_' . $modname);
                if (!$plugin || !$plugin->is_enabled()) {
                    continue;
                }
                if (!$modinfo->get_instances_of($modname)) {
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

                    $sessions[] = [
                        'title' => format_string($cm->name, true, ['context' => \context_module::instance($cm->id)]),
                        'coursefullname' => $coursename,
                        'modlabel' => $meta['label'],
                        'date' => userdate($start, get_string('strftimedatefullshort', 'core_langconfig')),
                        'time' => userdate($start, get_string('strftimetime', 'core_langconfig')),
                        'istoday' => ($start >= $startofday && $start < $endofday),
                        'joinurl' => (new \moodle_url('/mod/' . $modname . '/view.php', ['id' => $cm->id]))->out(false),
                        'sorttime' => $start,
                    ];
                }
            }
        }

        usort($sessions, static function(array $a, array $b): int {
            return $a['sorttime'] <=> $b['sorttime'];
        });

        return array_slice($sessions, 0, 6);
    }

    /**
     * @param int $userid
     * @return array
     */
    protected static function get_notifications(int $userid): array {
        $items = [];

        $announcements = theme_iiidem2_get_student_announcements($userid, 5);
        foreach ($announcements['announcements'] as $a) {
            $items[] = [
                'type' => 'announcement',
                'icon' => 'fa-bullhorn',
                'title' => $a['subject'],
                'meta' => $a['coursefullname'],
                'date' => $a['date'],
                'url' => $a['url'],
                'sorttime' => $a['timemodified'] ?? time(),
            ];
        }

        global $DB;
        $since = time() - (14 * DAYSECS);
        $grades = $DB->get_records_sql(
            "SELECT gg.id, gg.timemodified, gg.finalgrade, gi.itemname, gi.itemtype, c.fullname AS coursename, c.id AS courseid
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid
               JOIN {course} c ON c.id = gi.courseid
              WHERE gg.userid = :userid
                AND gg.timemodified > :since
                AND gg.finalgrade IS NOT NULL
                AND gi.itemtype != 'category'
           ORDER BY gg.timemodified DESC",
            ['userid' => $userid, 'since' => $since],
            0,
            5
        );

        foreach ($grades as $g) {
            $items[] = [
                'type' => 'grade',
                'icon' => 'fa-star',
                'title' => get_string('dashboardgradeitem', 'theme_iiidem2', format_string($g->itemname)),
                'meta' => format_string($g->coursename),
                'date' => userdate($g->timemodified, get_string('strftimedatefullshort', 'core_langconfig')),
                'url' => (new \moodle_url('/grade/report/user/index.php', ['id' => $g->courseid]))->out(false),
                'sorttime' => $g->timemodified,
            ];
        }

        usort($items, static function(array $a, array $b): int {
            return $b['sorttime'] <=> $a['sorttime'];
        });

        $items = array_slice($items, 0, 8);
        foreach ($items as &$item) {
            unset($item['sorttime']);
        }

        return $items;
    }

    /**
     * @param int $userid
     * @param array $courses
     * @return array
     */
    protected static function get_recent_activity(int $userid, array $courses): array {
        global $DB;

        $items = [];

        $recentcourses = course_get_recent_courses($userid, 5, 0, 'timeaccess DESC');
        foreach ($recentcourses as $course) {
            if ((int) $course->id === SITEID) {
                continue;
            }
            $items[] = [
                'type' => 'course',
                'icon' => 'fa-play-circle',
                'title' => get_string('dashboardrecentcourse', 'theme_iiidem2', format_string($course->fullname)),
                'meta' => get_string('dashboardrecentopened', 'theme_iiidem2'),
                'date' => !empty($course->timeaccess)
                    ? userdate($course->timeaccess, get_string('strftimedatefullshort', 'core_langconfig'))
                    : '',
                'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'sorttime' => (int) ($course->timeaccess ?? 0),
            ];
        }

        $submissions = $DB->get_records_sql(
            "SELECT s.id, s.timemodified, a.name AS assignname, a.course, c.fullname AS coursename, cm.id AS cmid
               FROM {assign_submission} s
               JOIN {assign} a ON a.id = s.assignment
               JOIN {course} c ON c.id = a.course
               JOIN {course_modules} cm ON cm.instance = a.id AND cm.module = (
                    SELECT id FROM {modules} WHERE name = 'assign'
               )
              WHERE s.userid = :userid
                AND s.status = :status
                AND s.timemodified > :since
           ORDER BY s.timemodified DESC",
            [
                'userid' => $userid,
                'status' => 'submitted',
                'since' => time() - (30 * DAYSECS),
            ],
            0,
            5
        );

        foreach ($submissions as $s) {
            $items[] = [
                'type' => 'submission',
                'icon' => 'fa-file-upload',
                'title' => get_string('dashboardrecentassign', 'theme_iiidem2', format_string($s->assignname)),
                'meta' => format_string($s->coursename),
                'date' => userdate($s->timemodified, get_string('strftimedatefullshort', 'core_langconfig')),
                'url' => (new \moodle_url('/mod/assign/view.php', ['id' => $s->cmid]))->out(false),
                'sorttime' => $s->timemodified,
            ];
        }

        usort($items, static function(array $a, array $b): int {
            return $b['sorttime'] <=> $a['sorttime'];
        });

        $items = array_slice($items, 0, 8);
        foreach ($items as &$item) {
            unset($item['sorttime']);
        }

        return $items;
    }
}
