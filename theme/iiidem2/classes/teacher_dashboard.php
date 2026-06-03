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
 * Builds Mustache context for the teacher role dashboard.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_dashboard {

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

        $courses = self::get_teaching_courses($userid);
        $teachercourses = self::get_course_cards($courses, $userid);
        $quickactions = self::get_quick_actions();
        $pendingtasks = self::get_pending_tasks($courses);
        $studentperformance = self::get_student_performance($courses);
        $analytics = self::get_analytics($courses, $pendingtasks);
        $schedule = self::get_schedule($courses, $userid);
        $communications = self::get_communications($courses);

        return [
            'teachercourses' => $teachercourses,
            'hasteachercourses' => !empty($teachercourses),
            'quickactions' => $quickactions,
            'hasquickactions' => !empty($quickactions),
            'pendingtasks' => $pendingtasks,
            'haspendingtasks' => !empty($pendingtasks),
            'studentperformance' => $studentperformance,
            'hasstudentperformance' => !empty($studentperformance),
            'analytics' => $analytics,
            'hasanalytics' => !empty($analytics),
            'schedule' => $schedule,
            'hasschedule' => !empty($schedule),
            'communications' => $communications,
            'hascommunications' => !empty($communications),
            'calendarurl' => (new \moodle_url('/calendar/view.php'))->out(false),
            'messagesurl' => (new \moodle_url('/message/index.php'))->out(false),
        ];
    }

    /**
     * Courses the user can teach (manage activities or grade).
     *
     * @param int $userid
     * @return array
     */
    protected static function get_teaching_courses(int $userid): array {
        $courses = enrol_get_users_courses($userid, true, '*', 'visible DESC, fullname ASC');
        $teaching = [];

        foreach ($courses as $course) {
            if ((int) $course->id === SITEID) {
                continue;
            }
            $context = \context_course::instance($course->id);
            if (has_capability('moodle/course:manageactivities', $context, $userid) ||
                    has_capability('moodle/grade:edit', $context, $userid)) {
                $teaching[] = $course;
            }
        }

        return $teaching;
    }

    /**
     * @param array $courses
     * @param int $userid
     * @return array
     */
    protected static function get_course_cards(array $courses, int $userid): array {
        global $CFG;

        $cards = [];
        $limit = 6;

        foreach ($courses as $course) {
            if (count($cards) >= $limit) {
                break;
            }

            $context = \context_course::instance($course->id);
            $studentcount = count_enrolled_users($context, 'mod/assign:submit');

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
                // Ignore.
            }

            if ($courseimage === '') {
                $courseimage = $CFG->wwwroot . '/theme/iiidem2/pix/ai.jpg';
            }

            $cards[] = [
                'fullname' => format_string($course->fullname, true, ['context' => $context]),
                'shortname' => format_string($course->shortname, true, ['context' => $context]),
                'courseimage' => $courseimage,
                'studentcount' => $studentcount,
                'studentcountlabel' => get_string('dashboardteacherstudents', 'theme_iiidem2', $studentcount),
                'viewurl' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'editurl' => (new \moodle_url('/course/edit.php', ['id' => $course->id]))->out(false),
                'participantsurl' => (new \moodle_url('/user/index.php', ['id' => $course->id]))->out(false),
            ];
        }

        return $cards;
    }

    /**
     * @return array
     */
    protected static function get_quick_actions(): array {
        return [
            [
                'icon' => 'fa-folder-plus',
                'label' => get_string('dashboardteacheractioncreate', 'theme_iiidem2'),
                'url' => (new \moodle_url('/course/edit.php'))->out(false),
            ],
            [
                'icon' => 'fa-cogs',
                'label' => get_string('dashboardmanagecourses', 'theme_iiidem2'),
                'url' => (new \moodle_url('/course/management.php'))->out(false),
            ],
            [
                'icon' => 'fa-check-double',
                'label' => get_string('dashboardteacheractiongrade', 'theme_iiidem2'),
                'url' => (new \moodle_url('/grade/report/grader/index.php'))->out(false),
            ],
            [
                'icon' => 'fa-calendar-days',
                'label' => get_string('calendar', 'core_calendar'),
                'url' => (new \moodle_url('/calendar/view.php'))->out(false),
            ],
            [
                'icon' => 'fa-envelope',
                'label' => get_string('dashboardteacheractionmessages', 'theme_iiidem2'),
                'url' => (new \moodle_url('/message/index.php'))->out(false),
            ],
            [
                'icon' => 'fa-chart-bar',
                'label' => get_string('dashboardreports', 'theme_iiidem2'),
                'url' => (new \moodle_url('/report/log/index.php'))->out(false),
            ],
        ];
    }

    /**
     * @param array $courses
     * @return array
     */
    protected static function get_pending_tasks(array $courses): array {
        global $DB;

        if (empty($courses)) {
            return [];
        }

        $courseids = array_map(static function($c) {
            return $c->id;
        }, $courses);
        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        $submissions = $DB->get_records_sql(
            "SELECT s.id, s.timemodified, a.id AS assignid, a.name AS assignname, a.course,
                    c.fullname AS coursename, cm.id AS cmid
               FROM {assign_submission} s
               JOIN {assign} a ON a.id = s.assignment
               JOIN {course} c ON c.id = a.course
               JOIN {course_modules} cm ON cm.instance = a.id
                    AND cm.module = (SELECT id FROM {modules} WHERE name = 'assign')
               LEFT JOIN {assign_grades} ag ON ag.assignment = s.assignment
                    AND ag.userid = s.userid
                    AND ag.attemptnumber = s.attemptnumber
              WHERE s.status = :submitted
                AND s.latest = 1
                AND a.course $insql
                AND (ag.id IS NULL OR ag.grade IS NULL)
           ORDER BY s.timemodified DESC",
            array_merge(['submitted' => 'submitted'], $params),
            0,
            8
        );

        $tasks = [];
        foreach ($submissions as $s) {
            $tasks[] = [
                'type' => 'assign',
                'typelabel' => get_string('dashboardteachergradepending', 'theme_iiidem2'),
                'title' => format_string($s->assignname),
                'coursefullname' => format_string($s->coursename),
                'date' => userdate($s->timemodified, get_string('strftimedatefullshort', 'core_langconfig')),
                'url' => (new \moodle_url('/mod/assign/view.php', [
                    'id' => $s->cmid,
                    'action' => 'grading',
                ]))->out(false),
                'sorttime' => $s->timemodified,
            ];
        }

        $now = time();
        $horizon = $now + (60 * 60 * 24 * 14);

        foreach ($courses as $course) {
            try {
                $modinfo = get_fast_modinfo($course);
            } catch (\Exception $e) {
                continue;
            }

            $coursename = format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]);

            foreach ($modinfo->get_instances_of('quiz') as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                $quiz = $DB->get_record('quiz', ['id' => $cm->instance], 'id, name, timeclose', IGNORE_MISSING);
                if (!$quiz || empty($quiz->timeclose) || $quiz->timeclose < $now || $quiz->timeclose > $horizon) {
                    continue;
                }

                $attempts = $DB->count_records('quiz_attempts', [
                    'quiz' => $quiz->id,
                    'state' => 'finished',
                ]);
                if ($attempts === 0) {
                    continue;
                }

                $tasks[] = [
                    'type' => 'quiz',
                    'typelabel' => get_string('dashboardtypequiz', 'theme_iiidem2'),
                    'title' => format_string($quiz->name, true, ['context' => \context_module::instance($cm->id)]),
                    'coursefullname' => $coursename,
                    'date' => userdate($quiz->timeclose, get_string('strftimedatefullshort', 'core_langconfig')),
                    'url' => (new \moodle_url('/mod/quiz/report.php', [
                        'id' => $cm->id,
                        'mode' => 'overview',
                    ]))->out(false),
                    'sorttime' => $quiz->timeclose,
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
     * @return array
     */
    protected static function get_student_performance(array $courses): array {
        $items = [];
        $limit = 6;

        foreach ($courses as $course) {
            if (count($items) >= $limit) {
                break;
            }

            $context = \context_course::instance($course->id);
            $studentcount = count_enrolled_users($context, 'mod/assign:submit');
            $completion = self::get_course_average_completion($course);
            $participantsurl = (new \moodle_url('/user/index.php', ['id' => $course->id]))->out(false);
            $gradebookurl = (new \moodle_url('/grade/report/index.php', ['id' => $course->id]))->out(false);

            $attendanceurl = '';
            $plugin = \core_plugin_manager::instance()->get_plugin_info('mod_attendance');
            if ($plugin && $plugin->is_enabled()) {
                foreach (get_fast_modinfo($course)->get_instances_of('attendance') as $cm) {
                    $attendanceurl = (new \moodle_url('/mod/attendance/view.php', ['id' => $cm->id]))->out(false);
                    break;
                }
            }

            $items[] = [
                'coursename' => format_string($course->fullname, true, ['context' => $context]),
                'studentcount' => $studentcount,
                'studentcountlabel' => get_string('dashboardteacherstudents', 'theme_iiidem2', $studentcount),
                'completionlabel' => $completion['label'],
                'hascompletion' => $completion['hascompletion'],
                'completion' => $completion['percent'],
                'participantsurl' => $participantsurl,
                'gradebookurl' => $gradebookurl,
                'hasattendance' => !empty($attendanceurl),
                'attendanceurl' => $attendanceurl,
            ];
        }

        return $items;
    }

    /**
     * @param \stdClass $course
     * @return array{hascompletion: bool, percent: int, label: string}
     */
    protected static function get_course_average_completion(\stdClass $course): array {
        $info = new \completion_info($course);
        if (!$info->is_enabled()) {
            return [
                'hascompletion' => false,
                'percent' => 0,
                'label' => get_string('dashboardnoprogress', 'theme_iiidem2'),
            ];
        }

        $context = \context_course::instance($course->id);
        $students = get_enrolled_users($context, 'moodle/course:isincompletionreports', 0, 'u.id');
        if (empty($students)) {
            return [
                'hascompletion' => true,
                'percent' => 0,
                'label' => get_string('dashboardteachercompletion', 'theme_iiidem2', 0),
            ];
        }

        $completed = 0;
        foreach ($students as $student) {
            if ($info->is_course_complete($student->id)) {
                $completed++;
            }
        }

        $percent = (int) round(($completed / count($students)) * 100);

        return [
            'hascompletion' => true,
            'percent' => $percent,
            'label' => get_string('dashboardteachercompletion', 'theme_iiidem2', $percent),
        ];
    }

    /**
     * @param array $courses
     * @param array $pendingtasks
     * @return array
     */
    protected static function get_analytics(array $courses, array $pendingtasks): array {
        $totalstudents = 0;
        $sumcompletion = 0;
        $courseswithcompletion = 0;

        foreach ($courses as $course) {
            $context = \context_course::instance($course->id);
            $totalstudents += count_enrolled_users($context, 'mod/assign:submit');
            $completion = self::get_course_average_completion($course);
            if ($completion['hascompletion']) {
                $sumcompletion += $completion['percent'];
                $courseswithcompletion++;
            }
        }

        $avgcompletion = $courseswithcompletion > 0
            ? (int) round($sumcompletion / $courseswithcompletion)
            : 0;

        return [
            [
                'icon' => 'fa-book-open',
                'label' => get_string('dashboardteacherstatcourses', 'theme_iiidem2'),
                'value' => (string) count($courses),
            ],
            [
                'icon' => 'fa-users',
                'label' => get_string('dashboardteacherstatstudents', 'theme_iiidem2'),
                'value' => (string) $totalstudents,
            ],
            [
                'icon' => 'fa-chart-line',
                'label' => get_string('dashboardteacherstatavgcompletion', 'theme_iiidem2'),
                'value' => $courseswithcompletion > 0 ? $avgcompletion . '%' : '—',
            ],
            [
                'icon' => 'fa-clipboard-check',
                'label' => get_string('dashboardteacherstatpending', 'theme_iiidem2'),
                'value' => (string) count($pendingtasks),
            ],
        ];
    }

    /**
     * @param array $courses
     * @param int $userid
     * @return array
     */
    protected static function get_schedule(array $courses, int $userid): array {
        global $DB;

        $now = time();
        $horizon = $now + (60 * 60 * 24 * 30);
        $events = [];

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
                $assign = $DB->get_record('assign', ['id' => $cm->instance], 'duedate', IGNORE_MISSING);
                if (!$assign || empty($assign->duedate) || $assign->duedate < $now || $assign->duedate > $horizon) {
                    continue;
                }
                $events[] = [
                    'typelabel' => get_string('dashboardtypeassign', 'theme_iiidem2'),
                    'title' => format_string($cm->name, true, ['context' => \context_module::instance($cm->id)]),
                    'coursefullname' => $coursename,
                    'date' => userdate($assign->duedate, get_string('strftimedatefullshort', 'core_langconfig')),
                    'url' => (new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]))->out(false),
                    'sorttime' => $assign->duedate,
                ];
            }

            $livemods = ['bigbluebuttonbn', 'zoom', 'webexactivity'];
            foreach ($livemods as $modname) {
                $plugin = \core_plugin_manager::instance()->get_plugin_info('mod_' . $modname);
                if (!$plugin || !$plugin->is_enabled()) {
                    continue;
                }
                $tables = [
                    'bigbluebuttonbn' => 'openingtime',
                    'zoom' => 'start_time',
                    'webexactivity' => 'starttime',
                ];
                $startfield = $tables[$modname];

                foreach ($modinfo->get_instances_of($modname) as $cm) {
                    if (!$cm->uservisible) {
                        continue;
                    }
                    $instance = $DB->get_record($modname, ['id' => $cm->instance], $startfield, IGNORE_MISSING);
                    if (!$instance || empty($instance->$startfield)) {
                        continue;
                    }
                    $start = (int) $instance->$startfield;
                    if ($start < $now || $start > $horizon) {
                        continue;
                    }
                    $events[] = [
                        'typelabel' => get_string('dashboardteacherlive', 'theme_iiidem2'),
                        'title' => format_string($cm->name, true, ['context' => \context_module::instance($cm->id)]),
                        'coursefullname' => $coursename,
                        'date' => userdate($start, get_string('strftimedatefullshort', 'core_langconfig')),
                        'url' => (new \moodle_url('/mod/' . $modname . '/view.php', ['id' => $cm->id]))->out(false),
                        'sorttime' => $start,
                    ];
                }
            }
        }

        usort($events, static function(array $a, array $b): int {
            return $a['sorttime'] <=> $b['sorttime'];
        });

        $events = array_slice($events, 0, 8);
        foreach ($events as &$event) {
            unset($event['sorttime']);
        }

        return $events;
    }

    /**
     * @param array $courses
     * @return array
     */
    protected static function get_communications(array $courses): array {
        global $DB;

        $items = [
            [
                'icon' => 'fa-envelope',
                'title' => get_string('dashboardteacheractionmessages', 'theme_iiidem2'),
                'meta' => get_string('dashboardteachermsgdesc', 'theme_iiidem2'),
                'url' => (new \moodle_url('/message/index.php'))->out(false),
            ],
        ];

        $limit = 5;
        foreach ($courses as $course) {
            if (count($items) >= $limit + 1) {
                break;
            }

            $forums = $DB->get_records('forum', ['course' => $course->id, 'type' => 'news'], 'id ASC', 'id, name, course', 0, 1);
            if (!$forums) {
                continue;
            }

            $forum = reset($forums);
            try {
                $modinfo = get_fast_modinfo($course);
            } catch (\Exception $e) {
                continue;
            }

            if (empty($modinfo->instances['forum'][$forum->id])) {
                continue;
            }

            $cm = $modinfo->instances['forum'][$forum->id];
            $context = \context_course::instance($course->id);

            $items[] = [
                'icon' => 'fa-bullhorn',
                'title' => get_string('dashboardteacherannounce', 'theme_iiidem2', format_string($course->fullname)),
                'meta' => format_string($forum->name, true, ['context' => $context]),
                'url' => (new \moodle_url('/mod/forum/view.php', ['id' => $cm->id]))->out(false),
            ];
        }

        return $items;
    }
}
