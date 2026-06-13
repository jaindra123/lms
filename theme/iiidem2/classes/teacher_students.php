<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Enrolled student roster and detail views for the instructor dashboard.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_students {

    /** @var int Max students listed per course on the dashboard. */
    private const ROSTER_LIMIT = 50;

    /**
     * Roster context for the teacher dashboard.
     *
     * @param array $courses Teaching courses.
     * @param int $teacherid
     * @return array
     */
    public static function get_dashboard_context(array $courses, int $teacherid): array {
        global $PAGE;

        $defaults = [
            'hasroster' => false,
            'hasstudents' => false,
            'rostercourses' => [],
            'totalstudents' => 0,
            'studentcountlabel' => get_string('dashboardteacherstudents', 'theme_iiidem2', 0),
        ];

        if (empty($courses)) {
            return $defaults;
        }

        require_once(__DIR__ . '/../lib.php');

        $rostercourses = [];
        $total = 0;
        $courseindex = 0;

        foreach ($courses as $course) {
            $students = self::get_course_students($course, $teacherid);
            if (empty($students)) {
                continue;
            }

            $coursecontext = \context_course::instance($course->id);
            $coursename = format_string($course->fullname, true, ['context' => $coursecontext]);
            $rows = [];

            foreach ($students as $student) {
                if (count($rows) >= self::ROSTER_LIMIT) {
                    break;
                }

                $progress = theme_iiidem2_get_course_weekend_progress($course, (int) $student->id);
                $progresslabel = !empty($progress['hasprogress'])
                    ? $progress['progresslabel']
                    : get_string('dashboardnoprogress', 'theme_iiidem2');

                $userpicture = new \user_picture($student);
                $userpicture->size = 48;

                $rows[] = [
                    'id' => (int) $student->id,
                    'name' => fullname($student),
                    'email' => !empty($student->email) ? $student->email : '',
                    'hasemail' => !empty($student->email),
                    'progress' => !empty($progress['hasprogress']) ? (int) $progress['progress'] : null,
                    'hasprogress' => !empty($progress['hasprogress']),
                    'progresslabel' => $progresslabel,
                    'lastaccesslabel' => self::format_last_access($student->lastaccess ?? 0),
                    'detailurl' => self::get_student_detail_url((int) $course->id, (int) $student->id)->out(false),
                    'profileimage' => $userpicture->get_url($PAGE)->out(false),
                ];
            }

            $count = count($students);
            $total += $count;

            $rostercourses[] = [
                'courseid' => (int) $course->id,
                'coursename' => $coursename,
                'students' => $rows,
                'hasstudents' => !empty($rows),
                'studentcount' => $count,
                'studentcountlabel' => get_string('dashboardteacherstudents', 'theme_iiidem2', $count),
                'participantsurl' => (new \moodle_url('/user/index.php', ['id' => $course->id]))->out(false),
                'isfirst' => $courseindex === 0,
            ];
            $courseindex++;
        }

        if (empty($rostercourses)) {
            return $defaults;
        }

        return [
            'hasroster' => true,
            'hasstudents' => $total > 0,
            'rostercourses' => $rostercourses,
            'totalstudents' => $total,
            'studentcountlabel' => get_string('dashboardteacherstudents', 'theme_iiidem2', $total),
        ];
    }

    /**
     * Detail page context for a single enrolled student.
     *
     * @param int $courseid
     * @param int $studentid
     * @param int $teacherid
     * @return array
     * @throws \moodle_exception
     */
    public static function get_student_detail_context(int $courseid, int $studentid, int $teacherid): array {
        global $CFG, $DB, $PAGE;

        require_once($CFG->dirroot . '/theme/iiidem2/lib.php');
        require_once($CFG->libdir . '/completionlib.php');

        $course = get_course($courseid);
        self::require_teacher_student_access($course, $studentid, $teacherid);

        $student = \core_user::get_user($studentid, '*', MUST_EXIST);
        $coursecontext = \context_course::instance($course->id);
        $coursename = format_string($course->fullname, true, ['context' => $coursecontext]);

        $userpicture = new \user_picture($student);
        $userpicture->size = 120;

        $weekend = theme_iiidem2_get_course_weekend_progress($course, $studentid);
        $completionlabel = get_string('dashboardnoprogress', 'theme_iiidem2');
        $completionpercent = null;

        if (!empty($weekend['hasprogress'])) {
            $completionlabel = $weekend['progresslabel'];
            $completionpercent = (int) $weekend['progress'];
        } else {
            $percent = \core_completion\progress::get_course_progress_percentage($course, $studentid);
            if ($percent !== null) {
                $completionpercent = (int) round($percent);
                $completionlabel = get_string('dashboardprogresslabel', 'theme_iiidem2', $completionpercent);
            }
        }

        $enrolledon = self::get_enrolment_date($courseid, $studentid);
        $attendance = self::get_attendance_context($course, $studentid, $teacherid, $weekend);
        $certificates = self::get_certificate_summary($course, $studentid, $teacherid);

        $weekendrows = [];
        if (!empty($weekend['weekends'])) {
            foreach ($weekend['weekends'] as $weekendrow) {
                $weekendrows[] = [
                    'name' => $weekendrow['name'],
                    'statuslabel' => $weekendrow['statuslabel'],
                    'statusclass' => $weekendrow['statusclass'],
                    'complete' => !empty($weekendrow['complete']),
                ];
            }
        }

        return [
            'studentid' => $studentid,
            'courseid' => $courseid,
            'studentname' => fullname($student),
            'email' => $student->email ?? '',
            'hasemail' => !empty($student->email),
            'profileimage' => $userpicture->get_url($PAGE)->out(false),
            'coursename' => $coursename,
            'courseurl' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'dashboardurl' => \theme_iiidem2_get_dashboard_url()->out(false),
            'messageurl' => (new \moodle_url('/message/index.php', ['id' => $studentid]))->out(false),
            'moodleprofileurl' => (new \moodle_url('/user/view.php', [
                'id' => $studentid,
                'course' => $course->id,
            ]))->out(false),
            'lastaccesslabel' => self::format_last_access($student->lastaccess ?? 0),
            'enrolledonlabel' => $enrolledon,
            'hasprogress' => $completionpercent !== null,
            'progress' => $completionpercent ?? 0,
            'progresslabel' => $completionlabel,
            'hasweekends' => !empty($weekendrows),
            'weekends' => $weekendrows,
            'hasattendance' => !empty($attendance['hasattendance']),
            'attendancelabel' => $attendance['label'],
            'attendanceurl' => $attendance['url'],
            'attendancesessions' => $attendance['sessions'],
            'hasattendancesessions' => !empty($attendance['hassessions']),
            'attendancesublabel' => $attendance['sublabel'] ?? '',
            'hasattendancesublabel' => !empty($attendance['sublabel']),
            'attendancehelp' => $attendance['help'] ?? '',
            'hasattendancehelp' => !empty($attendance['help']),
            'attendanceupcoming' => $attendance['upcoming'] ?? 0,
            'hasattendanceupcoming' => !empty($attendance['upcoming']),
            'hascertificates' => !empty($certificates['hascertificates']),
            'certificates' => $certificates['items'],
            'certificatecount' => $certificates['count'],
        ];
    }

    /**
     * @param int $courseid
     * @param int $studentid
     * @return \moodle_url
     */
    public static function get_student_detail_url(int $courseid, int $studentid): \moodle_url {
        return new \moodle_url('/theme/iiidem2/dashboard/student.php', [
            'courseid' => $courseid,
            'studentid' => $studentid,
        ]);
    }

    /**
     * Ensure the teacher may view this student in the course.
     *
     * @param \stdClass $course
     * @param int $studentid
     * @param int $teacherid
     */
    public static function require_teacher_student_access(\stdClass $course, int $studentid, int $teacherid): void {
        $coursecontext = \context_course::instance($course->id);

        if (!has_capability('moodle/course:viewparticipants', $coursecontext, $teacherid)) {
            throw new \required_capability_exception($coursecontext, 'moodle/course:viewparticipants', 'nopermissions', '');
        }

        if (!self::is_teaching_user($coursecontext, $teacherid)) {
            throw new \moodle_exception('nopermissions', 'error');
        }

        if (!is_enrolled($coursecontext, $studentid)) {
            throw new \moodle_exception('usernotincourse', 'error');
        }

        if (self::is_teaching_user($coursecontext, $studentid)) {
            throw new \moodle_exception('nopermissions', 'error');
        }
    }

    /**
     * Enrolled learners in a course (excludes teachers / managers).
     *
     * @param \stdClass $course
     * @param int $teacherid
     * @return array<int,\stdClass>
     */
    public static function get_course_students(\stdClass $course, int $teacherid): array {
        $coursecontext = \context_course::instance($course->id);

        if (!self::is_teaching_user($coursecontext, $teacherid)) {
            return [];
        }

        $candidates = get_enrolled_users(
            $coursecontext,
            '',
            0,
            'u.id, u.firstname, u.lastname, u.email, u.lastaccess, u.deleted, u.suspended',
            'u.lastname ASC, u.firstname ASC'
        );

        $students = [];
        foreach ($candidates as $user) {
            if (!empty($user->deleted) || !empty($user->suspended)) {
                continue;
            }
            if (self::is_teaching_user($coursecontext, (int) $user->id)) {
                continue;
            }
            $students[(int) $user->id] = $user;
        }

        return $students;
    }

    /**
     * @param \context_course $coursecontext
     * @param int $userid
     * @return bool
     */
    protected static function is_teaching_user(\context_course $coursecontext, int $userid): bool {
        return has_capability('moodle/course:manageactivities', $coursecontext, $userid) ||
            has_capability('moodle/grade:edit', $coursecontext, $userid);
    }

    /**
     * @param int $lastaccess
     * @return string
     */
    protected static function format_last_access(int $lastaccess): string {
        if ($lastaccess <= 0) {
            return get_string('never');
        }
        return userdate($lastaccess, get_string('strftimedatefullshort', 'core_langconfig'));
    }

    /**
     * @param int $courseid
     * @param int $studentid
     * @return string
     */
    protected static function get_enrolment_date(int $courseid, int $studentid): string {
        global $DB;

        $timecreated = $DB->get_field_sql(
            "SELECT MIN(ue.timecreated)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
              WHERE ue.userid = :userid AND e.courseid = :courseid",
            ['userid' => $studentid, 'courseid' => $courseid]
        );

        if (empty($timecreated)) {
            return '—';
        }

        return userdate($timecreated, get_string('strftimedatefullshort', 'core_langconfig'));
    }

    /**
     * @param \stdClass $course
     * @param int $studentid
     * @param int $teacherid
     * @param array $weekendprogress
     * @return array
     */
    protected static function get_attendance_context(
            \stdClass $course,
            int $studentid,
            int $teacherid,
            array $weekendprogress = []): array {
        global $DB;

        $empty = [
            'hasattendance' => false,
            'label' => '',
            'sublabel' => '',
            'help' => '',
            'url' => '',
            'sessions' => [],
            'hassessions' => false,
            'upcoming' => 0,
        ];

        $plugin = \core_plugin_manager::instance()->get_plugin_info('mod_attendance');
        if (!$plugin || !$plugin->is_enabled()) {
            return $empty;
        }

        try {
            $modinfo = get_fast_modinfo($course, $teacherid);
        } catch (\Exception $e) {
            return $empty;
        }

        $weekendmap = self::build_weekend_completion_map($weekendprogress['weekends'] ?? []);

        foreach ($modinfo->get_instances_of('attendance') as $cm) {
            $modcontext = \context_module::instance($cm->id);
            if (!has_capability('mod/attendance:viewreports', $modcontext, $teacherid) &&
                    !has_capability('mod/attendance:takeattendances', $modcontext, $teacherid) &&
                    !has_capability('mod/attendance:manageattendances', $modcontext, $teacherid)) {
                continue;
            }

            $cantake = has_capability('mod/attendance:takeattendances', $modcontext, $teacherid) ||
                has_capability('mod/attendance:manageattendances', $modcontext, $teacherid);

            $attendanceid = (int) $cm->instance;
            $sessionsdata = $DB->get_records_sql(
                "SELECT s.id, s.sessdate, s.description, s.descriptionformat, s.lasttaken
                   FROM {attendance_sessions} s
                  WHERE s.attendanceid = :aid
               ORDER BY s.sessdate DESC",
                ['aid' => $attendanceid]
            );

            if (empty($sessionsdata)) {
                continue;
            }

            $sessionids = array_map('intval', array_keys($sessionsdata));
            list($insql, $params) = $DB->get_in_or_equal($sessionids, SQL_PARAMS_NAMED, 'sess');
            $sqlparams = array_merge($params, ['studentid' => $studentid]);

            $logrows = $DB->get_records_sql(
                "SELECT al.sessionid, st.description, st.acronym, st.grade
                   FROM {attendance_log} al
                   JOIN {attendance_statuses} st ON st.id = al.statusid
                  WHERE al.studentid = :studentid
                    AND al.sessionid $insql",
                $sqlparams
            );

            $logs = [];
            foreach ($logrows as $logrow) {
                $logs[(int) $logrow->sessionid] = $logrow;
            }

            $rows = [];
            $livepresent = 0;
            $livetaken = 0;
            $upcoming = 0;
            $now = time();

            foreach ($sessionsdata as $session) {
                $title = trim(strip_tags(format_text($session->description, $session->descriptionformat)));
                if ($title === '') {
                    $title = get_string('dashboardteacherattendancesession', 'theme_iiidem2',
                        userdate($session->sessdate, get_string('strftimedatefullshort', 'core_langconfig')));
                }

                $isfuture = (int) $session->sessdate > $now;
                $curriculumcomplete = self::session_matches_completed_weekend($title, $weekendmap);

                if ($isfuture && empty($session->lasttaken) && !$curriculumcomplete) {
                    $upcoming++;
                    continue;
                }

                $takeurl = '';
                $hastakeurl = false;

                if (!empty($session->lasttaken)) {
                    $livetaken++;
                    $log = $logs[(int) $session->id] ?? null;
                    if ($log === null) {
                        if ($curriculumcomplete) {
                            $statuslabel = get_string('dashboardteacherstudentattendancecurriculum', 'theme_iiidem2');
                            $statusclass = 'success';
                        } else {
                            $statuslabel = get_string('dashboardteacherattendancenotmarked', 'theme_iiidem2');
                            $statusclass = 'orange';
                        }
                    } else if ((float) $log->grade > 0) {
                        $livepresent++;
                        $statuslabel = !empty($log->acronym) ? $log->acronym : format_string($log->description);
                        $statusclass = 'success';
                    } else {
                        $statuslabel = !empty($log->acronym) ? $log->acronym : format_string($log->description);
                        $statusclass = 'orange';
                    }
                } else if ($curriculumcomplete) {
                    $statuslabel = get_string('dashboardteacherstudentattendancecurriculum', 'theme_iiidem2');
                    $statusclass = 'success';
                } else {
                    $statuslabel = get_string('dashboardteacherstudentattendanceawaiting', 'theme_iiidem2');
                    $statusclass = 'orange';
                    if ($cantake) {
                        $takeurl = (new \moodle_url('/mod/attendance/take.php', [
                            'id' => $cm->id,
                            'sessionid' => $session->id,
                        ]))->out(false);
                        $hastakeurl = true;
                    }
                }

                $rows[] = [
                    'date' => userdate($session->sessdate, get_string('strftimedatefullshort', 'core_langconfig')),
                    'time' => userdate($session->sessdate, get_string('strftimetime', 'core_langconfig')),
                    'title' => $title,
                    'statuslabel' => $statuslabel,
                    'statusclass' => $statusclass,
                    'takeurl' => $takeurl,
                    'hastakeurl' => $hastakeurl,
                ];
            }

            if (!empty($weekendprogress['hasprogress'])) {
                $label = $weekendprogress['progresslabel'];
            } else {
                $label = get_string('dashboardteacherstudentattendance', 'theme_iiidem2', (object) [
                    'present' => $livepresent,
                    'total' => $livetaken,
                    'percent' => $livetaken > 0 ? (int) round(($livepresent / $livetaken) * 100) : 0,
                ]);
            }

            $sublabel = '';
            if ($livetaken > 0 || $livepresent > 0) {
                $sublabel = get_string('dashboardteacherstudentattendancelivemarked', 'theme_iiidem2', (object) [
                    'present' => $livepresent,
                    'total' => $livetaken,
                ]);
            }

            return [
                'hasattendance' => true,
                'label' => $label,
                'sublabel' => $sublabel,
                'help' => get_string('dashboardteacherstudentattendancehelp', 'theme_iiidem2'),
                'url' => (new \moodle_url('/mod/attendance/view.php', [
                    'id' => $cm->id,
                    'studentid' => $studentid,
                ]))->out(false),
                'sessions' => $rows,
                'hassessions' => !empty($rows),
                'upcoming' => $upcoming,
            ];
        }

        return $empty;
    }

    /**
     * @param array $weekends
     * @return array<string, bool>
     */
    protected static function build_weekend_completion_map(array $weekends): array {
        $map = [];
        foreach ($weekends as $weekend) {
            $map[self::normalize_weekend_key($weekend['name'] ?? '')] = !empty($weekend['complete']);
        }
        return $map;
    }

    /**
     * @param string $name
     * @return string
     */
    protected static function normalize_weekend_key(string $name): string {
        return preg_replace('/[^a-z0-9]+/', '', \core_text::strtolower(trim($name)));
    }

    /**
     * @param string $sessiontitle
     * @param array<string, bool> $weekendmap
     * @return bool
     */
    protected static function session_matches_completed_weekend(string $sessiontitle, array $weekendmap): bool {
        if (empty($weekendmap)) {
            return false;
        }

        $sessionkey = self::normalize_weekend_key($sessiontitle);
        if ($sessionkey === '') {
            return false;
        }

        if (!empty($weekendmap[$sessionkey])) {
            return true;
        }

        foreach ($weekendmap as $weekendkey => $complete) {
            if (!$complete) {
                continue;
            }
            if (strpos($weekendkey, $sessionkey) !== false || strpos($sessionkey, $weekendkey) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \stdClass $course
     * @param int $studentid
     * @param int $teacherid
     * @return array{hascertificates: bool, count: int, items: array}
     */
    protected static function get_certificate_summary(\stdClass $course, int $studentid, int $teacherid): array {
        global $DB;

        $empty = ['hascertificates' => false, 'count' => 0, 'items' => []];

        $plugin = \core_plugin_manager::instance()->get_plugin_info('mod_customcert');
        if (!$plugin || !$plugin->is_enabled()) {
            return $empty;
        }

        try {
            $modinfo = get_fast_modinfo($course, $teacherid);
        } catch (\Exception $e) {
            return $empty;
        }

        $items = [];
        foreach ($modinfo->get_instances_of('customcert') as $cm) {
            $modcontext = \context_module::instance($cm->id);
            if (!has_capability('mod/customcert:viewreport', $modcontext, $teacherid) &&
                    !has_capability('mod/customcert:manage', $modcontext, $teacherid)) {
                continue;
            }

            $customcert = $DB->get_record('customcert', ['id' => $cm->instance], 'id, name', IGNORE_MISSING);
            if (!$customcert) {
                continue;
            }

            $issue = $DB->get_record('customcert_issues', [
                'customcertid' => $customcert->id,
                'userid' => $studentid,
            ], 'id, timecreated, code', IGNORE_MISSING);

            if (!$issue) {
                continue;
            }

            $items[] = [
                'name' => format_string($customcert->name, true, ['context' => $modcontext]),
                'date' => userdate($issue->timecreated, get_string('strftimedatefullshort', 'core_langconfig')),
                'code' => $issue->code ?? '',
                'hascode' => !empty($issue->code),
                'downloadurl' => (new \moodle_url('/mod/customcert/view.php', [
                    'id' => $cm->id,
                    'downloadissue' => $issue->id,
                ]))->out(false),
            ];
        }

        return [
            'hascertificates' => !empty($items),
            'count' => count($items),
            'items' => $items,
        ];
    }
}
