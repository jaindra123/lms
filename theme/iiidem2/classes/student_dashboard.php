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
        $livepanel = \theme_iiidem2\liveclass_sessions::split_today_upcoming($liveclasses);
        $joinnowurl = $livepanel['joinnowurl'] !== ''
            ? $livepanel['joinnowurl']
            : (!empty($liveclasses[0]['joinurl']) ? $liveclasses[0]['joinurl'] : '');

        $announcements = self::get_announcement_items($userid);
        $notifications = self::get_notifications($userid);
        $achievements = self::get_achievements($courses, $userid);
        $coursecards = self::get_course_cards($courses, $userid);
        $upcomingactivities = self::get_upcoming_activities($courses, $userid, $liveclasses);
        $weekendprogress = self::get_weekend_progress_context($courses, $userid);
        $calendarcourseid = theme_iiidem2_get_dashboard_calendar_course_id_for_user($userid);
        $calendarcontext = theme_iiidem2_get_dashboard_calendar_context($calendarcourseid);
        $learningstats = self::get_learning_statistics($userid, $courses);
        $supportcontext = self::get_support_context($userid);
        $livequizcontext = self::get_livequiz_results_context($userid);
        $notificationmeta = self::get_notification_meta($userid);

        return array_merge([
            'firstname' => $user->firstname,
            'dashboardurl' => \theme_iiidem2_get_dashboard_url()->out(false),
            'sidenav' => self::get_sidebar_nav($courses, $userid),
            'dashboardtabs' => self::get_dashboard_tabs(!empty($livequizcontext['haslivequizresults'])),
            'progresscards' => self::get_learning_progress_cards($courses, $userid),
            'coursecards' => $coursecards,
            'hascoursecards' => !empty($coursecards),
            'weekendprogress' => $weekendprogress,
            'hasweekendprogress' => !empty($weekendprogress['hasprogress']),
            'mycoursesurl' => (new \moodle_url('/my/courses.php'))->out(false),
            'upcomingactivities' => $upcomingactivities,
            'hasupcomingactivities' => !empty($upcomingactivities),
            'liveclassestoday' => $livepanel['today'],
            'hasliveclassestoday' => !empty($livepanel['today']),
            'liveclassesupcoming' => $livepanel['upcoming'],
            'hasliveclassesupcoming' => !empty($livepanel['upcoming']),
            'hasliveclasses' => !empty($livepanel['today']) || !empty($livepanel['upcoming']),
            'announcements' => $announcements,
            'hasannouncements' => !empty($announcements),
            'notifications' => $notifications,
            'hasnotifications' => !empty($notifications),
            'notificationcount' => count($notifications),
            'achievementbadgecount' => $achievements['badgecount'],
            'achievementcertificatecount' => $achievements['certificatecount'],
            'achievementbadgesurl' => $achievements['badgesurl'],
            'achievementcertificatesurl' => $achievements['certificatesurl'],
            'learningstats' => $learningstats['stats'],
            'hasrecommendedcourses' => false,
            'recommendedcoursesurl' => (new \moodle_url('/course/index.php'))->out(false),
            'hasjoinnow' => $joinnowurl !== '',
            'joinnowurl' => $joinnowurl,
            'badgesurl' => (new \moodle_url('/badges/mybadges.php'))->out(false),
            'gradesurl' => (new \moodle_url('/grade/report/overview/index.php'))->out(false),
            'messagesurl' => (new \moodle_url('/message/index.php'))->out(false),
            'notificationsurl' => $notificationmeta['notificationsurl'],
            'unreadnotificationcount' => $notificationmeta['unreadcount'],
            'hasunreadnotifications' => $notificationmeta['unreadcount'] > 0,
            'profileurl' => (new \moodle_url('/user/profile.php', ['id' => $userid]))->out(false),
            'searchurl' => (new \moodle_url('/course/search.php'))->out(false),
        ], $calendarcontext, $supportcontext, $livequizcontext);
    }

    /**
     * Support hub context (tickets, FAQs, chatbot).
     *
     * @param int $userid
     * @return array
     */
    protected static function get_support_context(int $userid): array {
        global $CFG;

        $pluginlib = $CFG->dirroot . '/local/iiidem_support/lib.php';
        if (!is_readable($pluginlib)) {
            return ['hassupport' => false];
        }

        require_once($pluginlib);

        if (!\local_iiidem_support\manager::is_installed()) {
            return ['hassupport' => false];
        }

        $context = local_iiidem_support_get_dashboard_context($userid);
        $context['sesskey'] = sesskey();

        return $context;
    }

    /**
     * Closed live-quiz results for the current student.
     *
     * @param int $userid
     * @return array
     */
    protected static function get_livequiz_results_context(int $userid): array {
        global $CFG;

        $pluginlib = $CFG->dirroot . '/local/iiidem_livequiz/lib.php';
        if (!is_readable($pluginlib)) {
            return ['haslivequizresults' => false, 'livequizresults' => []];
        }

        require_once($pluginlib);
        if (!function_exists('local_iiidem_livequiz_get_dashboard_context')) {
            return ['haslivequizresults' => false, 'livequizresults' => []];
        }

        return local_iiidem_livequiz_get_dashboard_context($userid);
    }

    /**
     * Enrolled-course data for any role (student participant enrollments).
     *
     * @param int|null $userid
     * @return array
     */
    public static function get_enrolled_courses_context(?int $userid = null): array {
        global $CFG, $USER;

        if ($userid === null) {
            $userid = (int) $USER->id;
        }

        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $courses = self::get_enrolled_courses($userid);
        $coursecards = self::get_course_cards($courses, $userid);

        return [
            'coursecards' => $coursecards,
            'hascoursecards' => !empty($coursecards),
            'progresscards' => self::get_learning_progress_cards($courses, $userid),
            'mycoursesurl' => (new \moodle_url('/my/courses.php'))->out(false),
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
     * @param \stdClass $course
     * @param int $userid
     * @return bool
     */
    protected static function user_can_access_enrolled_course(\stdClass $course, int $userid): bool {
        if (empty($course->id) || (int) $course->id === SITEID) {
            return false;
        }

        $context = \context_course::instance((int) $course->id, IGNORE_MISSING);
        if (!$context) {
            return false;
        }

        // Pending fee enrolments are suspended until payment succeeds and must
        // not appear as enrolled courses on the student dashboard.
        return is_enrolled($context, $userid, '', true);
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
            if (!self::user_can_access_enrolled_course($course, $userid)) {
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

            $progress = null;
            $progresslabel = get_string('dashboardnoprogress', 'theme_iiidem2');
            $weekendprogress = theme_iiidem2_get_course_weekend_progress($course, $userid);

            if (!empty($weekendprogress['hasprogress'])) {
                $progress = (int) $weekendprogress['progress'];
                $progresslabel = $weekendprogress['progresslabel'];
            } else {
                try {
                    $percent = \core_completion\progress::get_course_progress_percentage($course, $userid);
                    if ($percent !== null) {
                        $progress = (int) round($percent);
                        $progresslabel = get_string('dashboardprogresslabel', 'theme_iiidem2', $progress);
                    }
                } catch (\Throwable $e) {
                    $progress = null;
                }
            }

            $cards[] = [
                'fullname' => format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]),
                'shortname' => format_string($course->shortname, true, ['context' => \context_course::instance($course->id)]),
                'courseimage' => $courseimage,
                'viewurl' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                'hasprogress' => $progress !== null,
                'progress' => $progress ?? 0,
                'progresslabel' => $progresslabel,
                'continueurl' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            ];
        }

        return $cards;
    }

    /**
     * Weekend progress panel for the student's primary enrolled course.
     *
     * @param array $courses
     * @param int $userid
     * @return array
     */
    protected static function get_weekend_progress_context(array $courses, int $userid): array {
        foreach ($courses as $course) {
            if (!self::user_can_access_enrolled_course($course, $userid)) {
                continue;
            }
            $progress = theme_iiidem2_get_course_weekend_progress($course, $userid);
            if (!empty($progress['hasprogress'])) {
                return $progress;
            }
        }

        return [
            'hasprogress' => false,
            'weekends' => [],
        ];
    }

    /**
     * Learning progress summary cards (enrolled, completed, in progress, certificates).
     *
     * @param array $courses
     * @param int $userid
     * @return array
     */
    protected static function get_learning_progress_cards(array $courses, int $userid): array {
        global $DB;

        $enrolled = count($courses);
        $completed = 0;
        $inprogress = 0;

        foreach ($courses as $course) {
            $percent = \core_completion\progress::get_course_progress_percentage($course, $userid);
            if ($percent !== null && (int) round($percent) >= 100) {
                $completed++;
            } else {
                $inprogress++;
            }
        }

        $certificates = self::count_user_certificates($userid);

        return [
            [
                'value' => (string) $enrolled,
                'label' => get_string('dashboardstatcourses', 'theme_iiidem2'),
                'accent' => 'navy',
            ],
            [
                'value' => (string) $completed,
                'label' => get_string('dashboardstatcompleted', 'theme_iiidem2'),
                'accent' => 'teal',
            ],
            [
                'value' => (string) $inprogress,
                'label' => get_string('dashboardstatinprogress', 'theme_iiidem2'),
                'accent' => 'orange',
            ],
            [
                'value' => (string) $certificates,
                'label' => get_string('dashboardstatcertificates', 'theme_iiidem2'),
                'accent' => 'purple',
            ],
        ];
    }

    /**
     * Dashboard tab panels (Moodle-style my-home sections).
     *
     * @param bool $haslivequizresults
     * @return array
     */
    protected static function get_dashboard_tabs(bool $haslivequizresults = false): array {
        $tabs = [
            [
                'id' => 'overview',
                'icon' => 'fa-gauge-high',
                'label' => get_string('dashboardtaboverview', 'theme_iiidem2'),
                'active' => true,
            ],
            [
                'id' => 'learning',
                'icon' => 'fa-book-open',
                'label' => get_string('dashboardtablearning', 'theme_iiidem2'),
                'active' => false,
            ],
            [
                'id' => 'calendar',
                'icon' => 'fa-calendar',
                'label' => get_string('dashboardtabcalendar', 'theme_iiidem2'),
                'active' => false,
            ],
            [
                'id' => 'communication',
                'icon' => 'fa-comments',
                'label' => get_string('dashboardtabcommunication', 'theme_iiidem2'),
                'active' => false,
            ],
            [
                'id' => 'achievements',
                'icon' => 'fa-award',
                'label' => get_string('dashboardtabachievements', 'theme_iiidem2'),
                'active' => false,
            ],
        ];

        if ($haslivequizresults) {
            $tabs[] = [
                'id' => 'livequiz',
                'icon' => 'fa-question-circle',
                'label' => get_string('resultshistory', 'local_iiidem_livequiz'),
                'active' => false,
            ];
        }

        return $tabs;
    }

    /**
     * @deprecated Use get_dashboard_tabs().
     * @param int $userid
     * @param int $calendarcourseid
     * @return array
     */
    protected static function get_quick_links(int $userid, int $calendarcourseid = SITEID): array {
        return self::get_dashboard_tabs();
    }

    /**
     * Merged upcoming quizzes, assignments, and live sessions.
     *
     * @param array $courses
     * @param int $userid
     * @param array $liveclasses
     * @return array
     */
    protected static function get_upcoming_activities(array $courses, int $userid, array $liveclasses): array {
        $items = [];
        $now = time();

        foreach (self::collect_upcoming_tasks($courses, $userid) as $task) {
            $statusclass = 'navy';
            if ($task['sorttime'] < $now + DAYSECS) {
                $statusclass = 'orange';
            }
            $items[] = [
                'typelabel' => $task['typelabel'],
                'title' => $task['title'],
                'meta' => $task['coursefullname'] . ' · ' . $task['date'],
                'url' => $task['url'],
                'status' => $task['date'],
                'statusclass' => $statusclass,
                'sorttime' => $task['sorttime'],
            ];
        }

        foreach ($liveclasses as $session) {
            $sorttime = (int) ($session['sorttime'] ?? 0);
            $status = self::get_session_status($sorttime, $now, !empty($session['istoday']));
            $items[] = [
                'typelabel' => get_string('dashboardtypelive', 'theme_iiidem2'),
                'title' => $session['title'],
                'meta' => ($session['coursename'] ?? $session['coursefullname'] ?? '') . ' · ' . trim($session['date'] . ' · ' . $session['time']),
                'url' => $session['joinurl'] ?? '',
                'status' => $status['label'],
                'statusclass' => $status['class'],
                'sorttime' => $sorttime,
            ];
        }

        usort($items, static function(array $a, array $b): int {
            return $a['sorttime'] <=> $b['sorttime'];
        });

        $items = array_slice($items, 0, 6);
        foreach ($items as &$item) {
            unset($item['sorttime']);
        }

        return $items;
    }

    /**
     * Recent course announcements for the dashboard panel.
     *
     * @param int $userid
     * @return array
     */
    protected static function get_announcement_items(int $userid): array {
        $data = theme_iiidem2_get_student_announcements($userid, 5);
        $items = [];
        foreach ($data['announcements'] as $announcement) {
            $items[] = [
                'title' => $announcement['subject'],
                'meta' => $announcement['coursefullname'],
                'date' => $announcement['date'],
                'url' => $announcement['url'],
            ];
        }
        return $items;
    }

    /**
     * Certificates and badges summary.
     *
     * @param array $courses
     * @param int $userid
     * @return array
     */
    protected static function get_achievements(array $courses, int $userid): array {
        global $CFG;

        $badgecount = 0;
        if (!empty($CFG->enablebadges)) {
            require_once($CFG->libdir . '/badgeslib.php');
            $badges = badges_get_user_badges($userid, 0, 0, 0, true);
            $badgecount = count($badges);
        }

        $certificatecount = self::count_user_certificates($userid);
        $certificatesurl = self::get_certificates_url($courses, $userid);

        return [
            'badgecount' => $badgecount,
            'certificatecount' => $certificatecount,
            'hasbadges' => $badgecount > 0,
            'hascertificates' => $certificatecount > 0,
            'badgesurl' => (new \moodle_url('/badges/mybadges.php'))->out(false),
            'certificatesurl' => $certificatesurl->out(false),
        ];
    }

    /**
     * Moodle popup notification metadata for the dashboard header.
     *
     * @param int $userid
     * @return array{notificationsurl:string,unreadcount:int}
     */
    protected static function get_notification_meta(int $userid): array {
        $notificationsurl = (new \moodle_url('/message/output/popup/notifications.php'))->out(false);
        $unreadcount = 0;

        if (class_exists('\message_popup\api')) {
            $unreadcount = (int) \message_popup\api::count_unread_popup_notifications($userid);
        }

        return [
            'notificationsurl' => $notificationsurl,
            'unreadcount' => $unreadcount,
        ];
    }

    /**
     * @param int $userid
     * @return int
     */
    protected static function count_user_certificates(int $userid): int {
        global $DB, $CFG;

        $plugin = \core_plugin_manager::instance()->get_plugin_info('mod_customcert');
        if ($plugin && $plugin->is_enabled() && $DB->get_manager()->table_exists('customcert_issues')) {
            return (int) $DB->count_records('customcert_issues', ['userid' => $userid]);
        }

        return (int) $DB->count_records_select(
            'course_completions',
            'userid = ? AND timecompleted IS NOT NULL AND timecompleted > 0',
            [$userid]
        );
    }

    /**
     * @param array $courses
     * @param int $userid
     * @return \moodle_url
     */
    protected static function get_certificates_url(array $courses, int $userid): \moodle_url {
        global $CFG;

        $plugin = \core_plugin_manager::instance()->get_plugin_info('mod_customcert');
        if ($plugin && $plugin->is_enabled() && is_readable($CFG->dirroot . '/mod/customcert/my_certificates.php')) {
            return new \moodle_url('/mod/customcert/my_certificates.php');
        }

        $primary = self::get_primary_course($courses);
        if ($primary) {
            return new \moodle_url('/grade/report/user/index.php', ['id' => $primary->id]);
        }

        return new \moodle_url('/grade/report/overview/index.php');
    }

    /**
     * @param array $courses
     * @return \stdClass|null
     */
    protected static function get_primary_course(array $courses): ?\stdClass {
        return $courses[0] ?? null;
    }

    /**
     * @param array $courses
     * @param int $userid
     * @return \moodle_url
     */
    protected static function get_assignments_url(array $courses, int $userid): \moodle_url {
        $primary = self::get_primary_course($courses);
        if ($primary) {
            return new \moodle_url('/mod/assign/index.php', ['id' => $primary->id]);
        }

        return \theme_iiidem2_get_dashboard_url();
    }

    /**
     * @param array $courses
     * @param int $userid
     * @return \moodle_url
     */
    protected static function get_discussions_url(array $courses, int $userid): \moodle_url {
        foreach ($courses as $course) {
            try {
                $modinfo = get_fast_modinfo($course, $userid);
            } catch (\Exception $e) {
                continue;
            }

            foreach ($modinfo->get_instances_of('forum') as $cm) {
                if ($cm->uservisible) {
                    return new \moodle_url('/mod/forum/view.php', ['id' => $cm->id]);
                }
            }
        }

        return new \moodle_url('/my/courses.php');
    }

    /**
     * @param array $courses
     * @param int $userid
     * @return \moodle_url
     */
    protected static function get_recordings_url(array $courses, int $userid): \moodle_url {
        foreach ($courses as $course) {
            try {
                $modinfo = get_fast_modinfo($course, $userid);
            } catch (\Exception $e) {
                continue;
            }

            foreach ($modinfo->get_instances_of('page') as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                if (preg_match('/\b(record|recording|replay|archive)\b/i', (string) $cm->name)) {
                    return new \moodle_url('/mod/page/view.php', ['id' => $cm->id]);
                }
            }

            foreach ($modinfo->get_instances_of('url') as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                if (preg_match('/\b(record|recording|replay|archive)\b/i', (string) $cm->name)) {
                    return new \moodle_url('/mod/url/view.php', ['id' => $cm->id]);
                }
            }
        }

        $url = \theme_iiidem2_get_dashboard_url();
        $url->set_anchor('dashboard-learning');
        return $url;
    }

    /**
     * @param array $courses
     * @param int $userid
     * @return \moodle_url
     */
    protected static function get_grades_url(array $courses, int $userid): \moodle_url {
        $primary = self::get_primary_course($courses);
        if ($primary) {
            return new \moodle_url('/grade/report/user/index.php', ['id' => $primary->id]);
        }

        return new \moodle_url('/grade/report/overview/index.php');
    }

    /**
     * @param array $courses
     * @return \moodle_url
     */
    protected static function get_curriculum_url(array $courses): \moodle_url {
        $primary = self::get_primary_course($courses);
        if ($primary) {
            $url = new \moodle_url(theme_iiidem2_get_course_detail_url($primary));
            $url->set_anchor('curriculum');
            return $url;
        }

        return new \moodle_url('/my/courses.php');
    }

    /**
     * Learning statistics cards.
     *
     * @param int $userid
     * @param array $courses
     * @return array
     */
    protected static function get_learning_statistics(int $userid, array $courses): array {
        global $DB;

        $completed = 0;
        foreach ($courses as $course) {
            $percent = \core_completion\progress::get_course_progress_percentage($course, $userid);
            if ($percent !== null && (int) round($percent) >= 100) {
                $completed++;
            }
        }

        $avggrade = self::get_average_grade_percentage($userid, $courses);
        $quizattempts = (int) $DB->count_records('quiz_attempts', ['userid' => $userid, 'state' => 'finished']);

        $monthstart = usergetmidnight(strtotime('first day of this month'));
        $monthlyactivity = 0;
        if ($DB->get_manager()->table_exists('logstore_standard_log')) {
            $monthlyactivity = (int) $DB->count_records_select(
                'logstore_standard_log',
                'userid = ? AND timecreated >= ?',
                [$userid, $monthstart]
            );
        }

        return [
            'stats' => [
                [
                    'icon' => 'fa-check-circle',
                    'value' => (string) $completed,
                    'label' => get_string('dashboardstatcoursescompleted', 'theme_iiidem2'),
                ],
                [
                    'icon' => 'fa-chart-line',
                    'value' => $avggrade !== null ? $avggrade . '%' : '—',
                    'label' => get_string('dashboardstatquizperformance', 'theme_iiidem2'),
                ],
                [
                    'icon' => 'fa-question-circle',
                    'value' => (string) $quizattempts,
                    'label' => get_string('dashboardstatquizattempts', 'theme_iiidem2'),
                ],
                [
                    'icon' => 'fa-calendar-check',
                    'value' => (string) $monthlyactivity,
                    'label' => get_string('dashboardstatmonthlyactivity', 'theme_iiidem2'),
                ],
            ],
        ];
    }

    /**
     * Upcoming tasks with sort timestamps preserved.
     *
     * @param array $courses
     * @param int $userid
     * @return array
     */
    protected static function collect_upcoming_tasks(array $courses, int $userid): array {
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
                $assign = $DB->get_record('assign', ['id' => $cm->instance], 'id, name, duedate', IGNORE_MISSING);
                if (!$assign || empty($assign->duedate) || $assign->duedate < $now || $assign->duedate > $horizon) {
                    continue;
                }
                $tasks[] = [
                    'type' => 'assign',
                    'typelabel' => get_string('dashboardtypeassign', 'theme_iiidem2'),
                    'title' => format_string($assign->name, true, ['context' => \context_module::instance($cm->id)]),
                    'coursefullname' => $coursename,
                    'date' => userdate($assign->duedate, get_string('strftimedatefullshort', 'core_langconfig')),
                    'sorttime' => (int) $assign->duedate,
                    'url' => (new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]))->out(false),
                ];
            }

            foreach ($modinfo->get_instances_of('quiz') as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                $quiz = $DB->get_record('quiz', ['id' => $cm->instance], 'id, name, timeclose', IGNORE_MISSING);
                if (!$quiz || empty($quiz->timeclose) || $quiz->timeclose < $now || $quiz->timeclose > $horizon) {
                    continue;
                }
                $tasks[] = [
                    'type' => 'quiz',
                    'typelabel' => get_string('dashboardtypequiz', 'theme_iiidem2'),
                    'title' => format_string($quiz->name, true, ['context' => \context_module::instance($cm->id)]),
                    'coursefullname' => $coursename,
                    'date' => userdate($quiz->timeclose, get_string('strftimedatefullshort', 'core_langconfig')),
                    'sorttime' => (int) $quiz->timeclose,
                    'url' => (new \moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false),
                ];
            }
        }

        usort($tasks, static function(array $a, array $b): int {
            return $a['sorttime'] <=> $b['sorttime'];
        });

        return $tasks;
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
        $dashboardbase = \theme_iiidem2_get_dashboard_url()->out(false);

        $items = [
            [
                'key' => 'dashboard',
                'icon' => 'fa-gauge-high',
                'label' => get_string('dashboard', 'theme_iiidem2'),
                'url' => $dashboardbase,
                'panel' => 'overview',
                'isinpage' => true,
                'active' => true,
            ],
            [
                'key' => 'curriculum',
                'icon' => 'fa-book-open',
                'label' => get_string('dashboardnavcurriculum', 'theme_iiidem2'),
                'url' => self::get_curriculum_url($courses)->out(false),
                'panel' => '',
                'isinpage' => false,
                'active' => false,
            ],
            [
                'key' => 'assignments',
                'icon' => 'fa-clipboard-list',
                'label' => get_string('dashboardnavassignments', 'theme_iiidem2'),
                'url' => self::get_assignments_url($courses, $userid)->out(false),
                'panel' => 'learning',
                'isinpage' => false,
                'active' => false,
            ],
            [
                'key' => 'discussions',
                'icon' => 'fa-comments',
                'label' => get_string('dashboardnavdiscussions', 'theme_iiidem2'),
                'url' => self::get_discussions_url($courses, $userid)->out(false),
                'panel' => 'communication',
                'isinpage' => false,
                'active' => false,
            ],
            [
                'key' => 'recordings',
                'icon' => 'fa-circle-play',
                'label' => get_string('dashboardnavrecordings', 'theme_iiidem2'),
                'url' => self::get_recordings_url($courses, $userid)->out(false),
                'panel' => 'learning',
                'isinpage' => false,
                'active' => false,
            ],
            [
                'key' => 'grades',
                'icon' => 'fa-pen',
                'label' => get_string('dashboardnavgrades', 'theme_iiidem2'),
                'url' => self::get_grades_url($courses, $userid)->out(false),
                'panel' => 'achievements',
                'isinpage' => false,
                'active' => false,
            ],
            [
                'key' => 'certificate',
                'icon' => 'fa-certificate',
                'label' => get_string('dashboardnavcertificate', 'theme_iiidem2'),
                'url' => self::get_certificates_url($courses, $userid)->out(false),
                'panel' => 'achievements',
                'isinpage' => false,
                'active' => false,
            ],
            [
                'key' => 'support',
                'icon' => 'fa-life-ring',
                'label' => get_string('dashboardnavsupport', 'theme_iiidem2'),
                'url' => $dashboardbase,
                'panel' => 'overview',
                'section' => 'dashboard-support',
                'isinpage' => true,
                'active' => false,
            ],
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
        return liveclass_sessions::get_upcoming($courses, $userid, 30 * DAYSECS, 8);
    }

    /**
     * @param int $userid
     * @return array
     */
    protected static function get_notifications(int $userid): array {
        global $DB;

        $items = [];

        if (class_exists('\message_popup\api')) {
            $records = \message_popup\api::get_popup_notifications($userid, 'DESC', 8, 0);
            foreach ($records as $notification) {
                $title = trim(strip_tags((string) ($notification->smallmessage ?: $notification->subject)));
                if ($title === '') {
                    $title = get_string('dashboardnotificationgeneric', 'theme_iiidem2');
                }
                $items[] = [
                    'type' => 'notification',
                    'icon' => 'fa-bell',
                    'title' => $title,
                    'meta' => !empty($notification->contexturlname)
                        ? format_string($notification->contexturlname)
                        : get_string('dashboardnotifications', 'theme_iiidem2'),
                    'date' => userdate($notification->timecreated, get_string('strftimedatefullshort', 'core_langconfig')),
                    'url' => !empty($notification->contexturl)
                        ? $notification->contexturl
                        : (new \moodle_url('/message/output/popup/notifications.php'))->out(false),
                    'sorttime' => (int) $notification->timecreated,
                    'unread' => empty($notification->timeread),
                ];
            }
        }

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
            4
        );

        foreach ($grades as $g) {
            $items[] = [
                'type' => 'grade',
                'icon' => 'fa-star',
                'title' => get_string('dashboardgradeitem', 'theme_iiidem2', format_string($g->itemname)),
                'meta' => format_string($g->coursename),
                'date' => userdate($g->timemodified, get_string('strftimedatefullshort', 'core_langconfig')),
                'url' => (new \moodle_url('/grade/report/user/index.php', ['id' => $g->courseid]))->out(false),
                'sorttime' => (int) $g->timemodified,
                'unread' => false,
            ];
        }

        usort($items, static function(array $a, array $b): int {
            return $b['sorttime'] <=> $a['sorttime'];
        });

        $items = array_slice($items, 0, 6);
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
