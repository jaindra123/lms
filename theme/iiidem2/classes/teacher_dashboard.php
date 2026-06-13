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
        global $CFG, $PAGE;

        if ($userid === null) {
            global $USER;
            $userid = $USER->id;
        }

        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $user = \core_user::get_user($userid, '*', MUST_EXIST);
        $courses = self::get_teaching_courses($userid);
        $pendingtasks = self::get_pending_tasks($courses);
        $schedule = self::get_schedule($courses, $userid);
        $livesessions = self::get_upcoming_live_sessions($courses, $userid);

        $userpicture = new \user_picture($user);
        $userpicture->size = 100;

        $primarycourse = !empty($courses[0]) ? $courses[0] : null;
        $cohortlabel = $primarycourse
            ? format_string($primarycourse->shortname, true, ['context' => \context_course::instance($primarycourse->id)])
                . ' · ' . get_string('dashboardteacherteachingconsole', 'theme_iiidem2')
            : get_string('dashboardteacherteachingconsole', 'theme_iiidem2');

        $launchurl = '';
        if (!empty($livesessions[0]['joinurl'])) {
            $launchurl = $livesessions[0]['joinurl'];
        } else if ($primarycourse) {
            $launchurl = (new \moodle_url('/course/view.php', ['id' => $primarycourse->id]))->out(false);
        }

        $livecontrolitems = self::get_live_control_items($courses, $livesessions, $userid);
        $gradingqueueitems = self::get_grading_queue_items($courses, $pendingtasks);
        $teachercourses = self::get_course_cards($courses, $userid);
        $quickactions = self::get_quick_actions();
        $studentperformance = self::get_student_performance($courses);
        $attendancecontext = teacher_attendance::get_dashboard_context($courses, $userid);
        $certificatecontext = teacher_certificates::get_dashboard_context($courses, $userid);
        $rostercontext = teacher_students::get_dashboard_context($courses, $userid);
        $analytics = self::get_analytics($courses, $pendingtasks, $certificatecontext);
        $communications = self::get_communications($courses);

        return array_merge([
            'firstname' => $user->firstname,
            'cohortlabel' => $cohortlabel,
            'sidenav' => self::get_sidebar_nav($courses, $userid),
            'statcards' => self::get_stat_cards($courses, $pendingtasks, $userid),
            'livecontrolitems' => $livecontrolitems,
            'haslivecontrolitems' => !empty($livecontrolitems),
            'launchurl' => $launchurl,
            'haslaunchurl' => $launchurl !== '',
            'gradingqueueitems' => $gradingqueueitems,
            'hasgradingqueueitems' => !empty($gradingqueueitems),
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
            'coursesurl' => (new \moodle_url('/course/management.php'))->out(false),
            'messagesurl' => (new \moodle_url('/message/index.php'))->out(false),
            'searchurl' => (new \moodle_url('/course/search.php'))->out(false),
            'profileurl' => (new \moodle_url('/user/profile.php', ['id' => $userid]))->out(false),
            'profileimage' => $userpicture->get_url($PAGE)->out(false),
            'hasnotifications' => !empty($pendingtasks),
            'notificationcount' => min(count($pendingtasks), 99),
            'calendarurl' => (new \moodle_url('/calendar/view.php'))->out(false),
        ], $attendancecontext, $certificatecontext, $rostercontext);
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
     * @param array $certificatecontext
     * @return array
     */
    protected static function get_analytics(array $courses, array $pendingtasks, array $certificatecontext = []): array {
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
            [
                'icon' => 'fa-certificate',
                'label' => get_string('dashboardteacherstatcertificates', 'theme_iiidem2'),
                'value' => !empty($certificatecontext['hascertificates'])
                    ? $certificatecontext['totalissuedlabel']
                    : '—',
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

    /**
     * Sidebar navigation for the instructor dashboard.
     *
     * @param array $courses
     * @param int $userid
     * @return array
     */
    protected static function get_sidebar_nav(array $courses, int $userid): array {
        $firstcourseid = !empty($courses[0]) ? (int) $courses[0]->id : 0;
        $courseurl = $firstcourseid
            ? (new \moodle_url('/course/view.php', ['id' => $firstcourseid]))->out(false)
            : (new \moodle_url('/course/management.php'))->out(false);
        $participantsurl = $firstcourseid
            ? (new \moodle_url('/user/index.php', ['id' => $firstcourseid]))->out(false)
            : (new \moodle_url('/course/management.php'))->out(false);
        $gradeurl = $firstcourseid
            ? (new \moodle_url('/grade/report/grader/index.php', ['id' => $firstcourseid]))->out(false)
            : (new \moodle_url('/grade/report/grader/index.php'))->out(false);
        $forumurl = self::get_first_forum_url($courses);
        $attendanceurl = self::get_attendance_url($courses);
        $dashboardattendance = \theme_iiidem2_get_dashboard_url();
        $dashboardattendance->set_anchor('teacher-attendance');
        $dashboardcertificates = \theme_iiidem2_get_dashboard_url();
        $dashboardcertificates->set_anchor('teacher-certificates');
        $dashboardstudents = \theme_iiidem2_get_dashboard_url();
        $dashboardstudents->set_anchor('teacher-students');

        return [
            [
                'icon' => 'fa-gauge-high',
                'label' => get_string('dashboard', 'theme_iiidem2'),
                'url' => \theme_iiidem2_get_dashboard_url()->out(false),
                'active' => true,
            ],
            [
                'icon' => 'fa-video',
                'label' => get_string('dashboardteachernavsessions', 'theme_iiidem2'),
                'url' => student_dashboard::get_live_class_page_url($userid)->out(false),
                'active' => false,
            ],
            [
                'icon' => 'fa-user-check',
                'label' => get_string('dashboardteachernavroster', 'theme_iiidem2'),
                'url' => $dashboardstudents->out(false),
                'active' => false,
            ],
            [
                'icon' => 'fa-certificate',
                'label' => get_string('dashboardteachernavcertificates', 'theme_iiidem2'),
                'url' => $dashboardcertificates->out(false),
                'active' => false,
            ],
            [
                'icon' => 'fa-pen',
                'label' => get_string('dashboardteachernavgrading', 'theme_iiidem2'),
                'url' => $gradeurl,
                'active' => false,
            ],
            [
                'icon' => 'fa-comments',
                'label' => get_string('dashboardnavdiscussions', 'theme_iiidem2'),
                'url' => $forumurl ?: $courseurl,
                'active' => false,
            ],
            [
                'icon' => 'fa-folder-open',
                'label' => get_string('dashboardteachernavcontent', 'theme_iiidem2'),
                'url' => (new \moodle_url('/course/management.php'))->out(false),
                'active' => false,
            ],
            [
                'icon' => 'fa-chart-line',
                'label' => get_string('dashboardteacheranalytics', 'theme_iiidem2'),
                'url' => (new \moodle_url('/report/log/index.php'))->out(false),
                'active' => false,
            ],
            [
                'icon' => 'fa-diagram-project',
                'label' => get_string('dashboardteachernavcapstone', 'theme_iiidem2'),
                'url' => $gradeurl,
                'active' => false,
            ],
        ];
    }

    /**
     * @param array $courses
     * @param array $pendingtasks
     * @return array
     */
    protected static function get_stat_cards(array $courses, array $pendingtasks, int $userid): array {
        $activelearners = 0;
        foreach ($courses as $course) {
            $context = \context_course::instance($course->id);
            $count = count_enrolled_users($context, 'mod/assign:submit');
            if ($count === 0) {
                $count = count_enrolled_users($context);
            }
            $activelearners += $count;
        }

        return [
            [
                'value' => (string) $activelearners,
                'label' => get_string('dashboardteacheractivelearners', 'theme_iiidem2'),
                'accent' => 'teal',
            ],
            [
                'value' => (string) count($pendingtasks),
                'label' => get_string('dashboardteacherpendingreviews', 'theme_iiidem2'),
                'accent' => 'orange',
            ],
            [
                'value' => self::get_average_attendance_label($courses, $userid),
                'label' => get_string('dashboardteacheravgattendance', 'theme_iiidem2'),
                'accent' => 'navy',
            ],
        ];
    }

    /**
     * @param array $courses
     * @return string
     */
    protected static function get_average_attendance_label(array $courses, int $userid): string {
        $attendanceavg = teacher_attendance::get_average_percent_label($courses, $userid);
        if ($attendanceavg !== '—') {
            return $attendanceavg;
        }

        $sum = 0;
        $count = 0;

        foreach ($courses as $course) {
            $completion = self::get_course_average_completion($course);
            if ($completion['hascompletion']) {
                $sum += $completion['percent'];
                $count++;
            }
        }

        if ($count === 0) {
            return '—';
        }

        return (int) round($sum / $count) . '%';
    }

    /**
     * @param array $courses
     * @param int $userid
     * @return array
     */
    protected static function get_upcoming_live_sessions(array $courses, int $userid): array {
        global $DB;

        $now = time();
        $horizon = $now + (60 * 60 * 24 * 14);
        $sessions = [];
        $livemods = [
            'bigbluebuttonbn' => ['table' => 'bigbluebuttonbn', 'start' => 'openingtime'],
            'zoom' => ['table' => 'zoom', 'start' => 'start_time'],
            'webexactivity' => ['table' => 'webexactivity', 'start' => 'starttime'],
        ];

        foreach ($courses as $course) {
            try {
                $modinfo = get_fast_modinfo($course, $userid);
            } catch (\Exception $e) {
                continue;
            }

            foreach ($livemods as $modname => $meta) {
                $plugin = \core_plugin_manager::instance()->get_plugin_info('mod_' . $modname);
                if (!$plugin || !$plugin->is_enabled()) {
                    continue;
                }

                foreach ($modinfo->get_instances_of($modname) as $cm) {
                    if (!$cm->uservisible) {
                        continue;
                    }
                    $instance = $DB->get_record($meta['table'], ['id' => $cm->instance], '*', IGNORE_MISSING);
                    if (!$instance || empty($instance->{$meta['start']})) {
                        continue;
                    }
                    $start = (int) $instance->{$meta['start']};
                    if ($start < $now - DAYSECS || $start > $horizon) {
                        continue;
                    }

                    $sessions[] = [
                        'title' => format_string($cm->name, true, ['context' => \context_module::instance($cm->id)]),
                        'joinurl' => (new \moodle_url('/mod/' . $modname . '/view.php', ['id' => $cm->id]))->out(false),
                        'sorttime' => $start,
                        'cmid' => $cm->id,
                        'modname' => $modname,
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
     * @param array $courses
     * @param array $livesessions
     * @param int $userid
     * @return array
     */
    protected static function get_live_control_items(array $courses, array $livesessions, int $userid): array {
        global $DB;

        $items = [];
        $now = time();

        if (!empty($livesessions[0])) {
            $session = $livesessions[0];
            $minutes = max(0, (int) round(($session['sorttime'] - $now) / 60));
            $items[] = [
                'title' => $session['title'],
                'meta' => $minutes > 0
                    ? get_string('dashboardteacherstartsinn', 'theme_iiidem2', $minutes)
                    : get_string('dashboardteacherlivestarting', 'theme_iiidem2'),
                'buttonlabel' => get_string('dashboardteacherbtnstart', 'theme_iiidem2'),
                'buttonclass' => 'green',
                'url' => $session['joinurl'],
            ];
        }

        $bbbcm = null;
        foreach ($courses as $course) {
            try {
                $modinfo = get_fast_modinfo($course, $userid);
            } catch (\Exception $e) {
                continue;
            }
            foreach ($modinfo->get_instances_of('bigbluebuttonbn') as $cm) {
                if ($cm->uservisible) {
                    $bbbcm = $cm;
                    break 2;
                }
            }
        }

        if ($bbbcm) {
            $items[] = [
                'title' => get_string('dashboardteacherbreakoutrooms', 'theme_iiidem2'),
                'meta' => get_string('dashboardteacherbreakoutmeta', 'theme_iiidem2'),
                'buttonlabel' => get_string('dashboardteacherbtnsetup', 'theme_iiidem2'),
                'buttonclass' => 'teal',
                'url' => (new \moodle_url('/mod/bigbluebuttonbn/view.php', ['id' => $bbbcm->id]))->out(false),
            ];
        }

        $primarycourse = !empty($courses[0]) ? $courses[0] : null;
        if ($primarycourse) {
            $items[] = [
                'title' => get_string('dashboardteacherlivepoll', 'theme_iiidem2'),
                'meta' => get_string('dashboardteacherlivepollmeta', 'theme_iiidem2'),
                'buttonlabel' => get_string('dashboardteacherbtnenable', 'theme_iiidem2'),
                'buttonclass' => 'navy',
                'url' => (new \moodle_url('/course/view.php', ['id' => $primarycourse->id]))->out(false),
            ];
            $items[] = [
                'title' => get_string('dashboardteacherautorecord', 'theme_iiidem2'),
                'meta' => get_string('dashboardteacherautorecordmeta', 'theme_iiidem2'),
                'buttonlabel' => get_string('dashboardteacherbtnactive', 'theme_iiidem2'),
                'buttonclass' => 'green',
                'url' => (new \moodle_url('/course/view.php', ['id' => $primarycourse->id]))->out(false),
            ];
        }

        return array_slice($items, 0, 4);
    }

    /**
     * @param array $courses
     * @param array $pendingtasks
     * @return array
     */
    protected static function get_grading_queue_items(array $courses, array $pendingtasks): array {
        global $DB;

        $items = [];

        if (!empty($courses)) {
            $courseids = array_map(static function($c) {
                return $c->id;
            }, $courses);
            list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

            $assignments = $DB->get_records_sql(
                "SELECT a.id, a.name, a.course, COUNT(s.id) AS pendingcount, cm.id AS cmid
                   FROM {assign_submission} s
                   JOIN {assign} a ON a.id = s.assignment
                   JOIN {course_modules} cm ON cm.instance = a.id
                        AND cm.module = (SELECT id FROM {modules} WHERE name = 'assign')
                   LEFT JOIN {assign_grades} ag ON ag.assignment = s.assignment
                        AND ag.userid = s.userid
                        AND ag.attemptnumber = s.attemptnumber
                  WHERE s.status = :submitted
                    AND s.latest = 1
                    AND a.course $insql
                    AND (ag.id IS NULL OR ag.grade IS NULL)
               GROUP BY a.id, a.name, a.course, cm.id
               ORDER BY pendingcount DESC",
                array_merge(['submitted' => 'submitted'], $params),
                0,
                4
            );

            foreach ($assignments as $assign) {
                $name = format_string($assign->name);
                $lower = core_text::strtolower($name);
                if (strpos($lower, 'capstone') !== false) {
                    $buttonlabel = get_string('dashboardteacherbtncomment', 'theme_iiidem2');
                    $buttonclass = 'grey';
                } else if (strpos($lower, 'brief') !== false || strpos($lower, 'rubric') !== false) {
                    $buttonlabel = get_string('dashboardteacherbtnreview', 'theme_iiidem2');
                    $buttonclass = 'teal';
                } else {
                    $buttonlabel = get_string('dashboardteacherbtnopen', 'theme_iiidem2');
                    $buttonclass = 'orange';
                }

                $items[] = [
                    'title' => $name,
                    'meta' => get_string('dashboardteacheritemsgrade', 'theme_iiidem2', (int) $assign->pendingcount),
                    'buttonlabel' => $buttonlabel,
                    'buttonclass' => $buttonclass,
                    'url' => (new \moodle_url('/mod/assign/view.php', [
                        'id' => $assign->cmid,
                        'action' => 'grading',
                    ]))->out(false),
                ];
            }
        }

        if (count($items) < 4) {
            foreach ($courses as $course) {
                if (count($items) >= 4) {
                    break;
                }
                try {
                    $modinfo = get_fast_modinfo($course);
                } catch (\Exception $e) {
                    continue;
                }

                foreach ($modinfo->get_instances_of('forum') as $cm) {
                    if (!$cm->uservisible || count($items) >= 4) {
                        continue;
                    }
                    $discussions = $DB->count_records('forum_discussions', ['forum' => $cm->instance]);
                    if ($discussions === 0) {
                        continue;
                    }
                    $items[] = [
                        'title' => get_string('dashboardteacherdiscussionmod', 'theme_iiidem2'),
                        'meta' => get_string('dashboardteacherdiscussionmeta', 'theme_iiidem2', $discussions),
                        'buttonlabel' => get_string('dashboardteacherbtnmoderate', 'theme_iiidem2'),
                        'buttonclass' => 'navy',
                        'url' => (new \moodle_url('/mod/forum/view.php', ['id' => $cm->id]))->out(false),
                    ];
                    break;
                }
            }
        }

        if (empty($items) && !empty($pendingtasks)) {
            foreach (array_slice($pendingtasks, 0, 4) as $task) {
                $items[] = [
                    'title' => $task['title'],
                    'meta' => $task['coursefullname'] . ' · ' . $task['date'],
                    'buttonlabel' => get_string('dashboardteachergrade', 'theme_iiidem2'),
                    'buttonclass' => 'orange',
                    'url' => $task['url'],
                ];
            }
        }

        return array_slice($items, 0, 4);
    }

    /**
     * @param array $courses
     * @return string
     */
    protected static function get_first_forum_url(array $courses): string {
        foreach ($courses as $course) {
            try {
                $modinfo = get_fast_modinfo($course);
            } catch (\Exception $e) {
                continue;
            }
            foreach ($modinfo->get_instances_of('forum') as $cm) {
                if ($cm->uservisible) {
                    return (new \moodle_url('/mod/forum/view.php', ['id' => $cm->id]))->out(false);
                }
            }
        }
        return '';
    }

    /**
     * @param array $courses
     * @return string
     */
    protected static function get_attendance_url(array $courses): string {
        $plugin = \core_plugin_manager::instance()->get_plugin_info('mod_attendance');
        if (!$plugin || !$plugin->is_enabled()) {
            return '';
        }

        foreach ($courses as $course) {
            try {
                $modinfo = get_fast_modinfo($course);
            } catch (\Exception $e) {
                continue;
            }
            foreach ($modinfo->get_instances_of('attendance') as $cm) {
                if ($cm->uservisible) {
                    return (new \moodle_url('/mod/attendance/view.php', ['id' => $cm->id]))->out(false);
                }
            }
        }

        return '';
    }
}
