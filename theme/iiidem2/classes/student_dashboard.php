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
        global $CFG;

        if ($userid === null) {
            global $USER;
            $userid = $USER->id;
        }

        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $courses = self::get_enrolled_courses($userid);
        $studentcourses = self::get_course_cards($courses, $userid);
        $progresscards = self::get_progress_cards($courses, $userid);
        $upcoming = self::get_upcoming_tasks($courses, $userid);
        $liveclasses = self::get_live_classes($courses, $userid);
        $notifications = self::get_notifications($userid);
        $recentactivity = self::get_recent_activity($userid, $courses);

        return [
            'studentcourses' => $studentcourses,
            'hasstudentcourses' => !empty($studentcourses),
            'progresscards' => $progresscards,
            'hasprogresscards' => !empty($progresscards),
            'upcoming' => $upcoming,
            'hasupcoming' => !empty($upcoming),
            'liveclasses' => $liveclasses,
            'hasliveclasses' => !empty($liveclasses),
            'notifications' => $notifications,
            'hasnotifications' => !empty($notifications),
            'recentactivity' => $recentactivity,
            'hasrecentactivity' => !empty($recentactivity),
            'calendarurl' => (new \moodle_url('/calendar/view.php'))->out(false),
            'badgesurl' => (new \moodle_url('/badges/mybadges.php'))->out(false),
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
     * @param array $courses
     * @param int $userid
     * @return array
     */
    protected static function get_progress_cards(array $courses, int $userid): array {
        global $DB, $CFG;

        $totalcourses = count($courses);
        $sumprogress = 0;
        $progresscount = 0;
        $completed = 0;

        foreach ($courses as $course) {
            $percent = \core_completion\progress::get_course_progress_percentage($course, $userid);
            if ($percent === null) {
                continue;
            }
            $progresscount++;
            $sumprogress += $percent;
            if ($percent >= 100) {
                $completed++;
            }
        }

        $avg = $progresscount > 0 ? (int) round($sumprogress / $progresscount) : 0;

        $badgescount = 0;
        if ($CFG->enablebadges) {
            $badgescount = $DB->count_records('badge_issued', ['userid' => $userid]);
        }

        return [
            [
                'icon' => 'fa-book',
                'label' => get_string('dashboardstatcourses', 'theme_iiidem2'),
                'value' => (string) $totalcourses,
            ],
            [
                'icon' => 'fa-chart-line',
                'label' => get_string('dashboardstatavgprogress', 'theme_iiidem2'),
                'value' => $progresscount > 0 ? $avg . '%' : '—',
            ],
            [
                'icon' => 'fa-check-circle',
                'label' => get_string('dashboardstatcompleted', 'theme_iiidem2'),
                'value' => (string) $completed,
            ],
            [
                'icon' => 'fa-award',
                'label' => get_string('dashboardstatbadges', 'theme_iiidem2'),
                'value' => (string) $badgescount,
            ],
        ];
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

        $sessions = array_slice($sessions, 0, 6);
        foreach ($sessions as &$session) {
            unset($session['sorttime']);
        }

        return $sessions;
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
