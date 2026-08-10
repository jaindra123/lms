<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Teacher attendance summaries for the instructor dashboard (mod_attendance).
 *
 * Student lists and session stats are scoped to learners under the viewing
 * teacher (excludes staff/admins; respects course/activity groups).
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_attendance {

    /** @var int Max session rows on the dashboard. */
    private const SESSION_LIMIT = 12;

    /** @var int Max student rows on the dashboard. */
    private const STUDENT_LIMIT = 15;

    /**
     * Dashboard context: date-wise sessions + per-student summary.
     *
     * @param array $courses Teaching courses for the teacher.
     * @param int $userid Teacher user id.
     * @return array
     */
    public static function get_dashboard_context(array $courses, int $userid): array {
        global $CFG, $DB;

        $defaults = [
            'hasattendance' => false,
            'hasattendancedata' => false,
            'pluginenabled' => false,
            'needsactivity' => false,
            'sessions' => [],
            'students' => [],
            'hassessions' => false,
            'hasstudents' => false,
        ];

        $plugin = \core_plugin_manager::instance()->get_plugin_info('mod_attendance');
        if (!$plugin || !$plugin->is_enabled()) {
            return $defaults;
        }

        $defaults['pluginenabled'] = true;

        require_once($CFG->dirroot . '/mod/attendance/locallib.php');
        require_once($CFG->libdir . '/enrollib.php');
        require_once($CFG->libdir . '/grouplib.php');

        foreach ($courses as $course) {
            $coursecontext = \context_course::instance($course->id);

            try {
                $modinfo = get_fast_modinfo($course, $userid);
            } catch (\Exception $e) {
                continue;
            }

            foreach ($modinfo->get_instances_of('attendance') as $cm) {
                $modcontext = \context_module::instance($cm->id);
                if (!has_capability('mod/attendance:viewreports', $modcontext, $userid) &&
                        !has_capability('mod/attendance:takeattendances', $modcontext, $userid) &&
                        !has_capability('mod/attendance:manageattendances', $modcontext, $userid)) {
                    continue;
                }

                $attrecord = $DB->get_record('attendance', ['id' => $cm->instance], '*', IGNORE_MISSING);
                if (!$attrecord) {
                    continue;
                }

                $students = teacher_students::get_course_students($course, $userid, $cm);
                $studentids = array_map('intval', array_keys($students));
                $studentcount = count($studentids);

                $att = new \mod_attendance_structure(
                    $attrecord,
                    $cm->get_course_module_record(),
                    $course,
                    null,
                    null
                );

                $allowedgroupids = self::get_teacher_group_ids($course, $cm, $userid);

                $sessions = $DB->get_records_sql(
                    "SELECT *
                       FROM {attendance_sessions}
                      WHERE attendanceid = :aid
                   ORDER BY sessdate DESC",
                    ['aid' => (int) $attrecord->id],
                    0,
                    self::SESSION_LIMIT * 3
                );

                $sessionrows = [];
                $takensessionids = [];

                foreach ($sessions as $session) {
                    if (!self::teacher_can_see_session($session, $allowedgroupids)) {
                        continue;
                    }
                    if (count($sessionrows) >= self::SESSION_LIMIT) {
                        break;
                    }

                    $stats = self::get_session_stats(
                        (int) $session->id,
                        $studentcount,
                        (int) $session->lasttaken,
                        $studentids
                    );
                    if (!empty($session->lasttaken)) {
                        $takensessionids[] = (int) $session->id;
                    }

                    $title = trim(strip_tags(format_text($session->description, $session->descriptionformat)));
                    if ($title === '') {
                        $title = get_string('dashboardteacherattendancesession', 'theme_iiidem2',
                            userdate($session->sessdate, get_string('strftimedatefullshort', 'core_langconfig')));
                    }

                    $sessionrows[] = [
                        'date' => userdate($session->sessdate, get_string('strftimedatefullshort', 'core_langconfig')),
                        'time' => userdate($session->sessdate, get_string('strftimetime', 'core_langconfig')),
                        'title' => $title,
                        'present' => $stats['present'],
                        'absent' => $stats['absent'],
                        'notmarked' => $stats['notmarked'],
                        'taken' => !empty($session->lasttaken),
                        'takenlabel' => !empty($session->lasttaken)
                            ? get_string('dashboardteacherattendancetaken', 'theme_iiidem2')
                            : get_string('dashboardteacherattendancenottaken', 'theme_iiidem2'),
                        'takenclass' => !empty($session->lasttaken) ? 'success' : 'orange',
                        'takeurl' => $att->url_take(['sessionid' => $session->id])->out(false),
                        'canviewreport' => has_capability('mod/attendance:viewreports', $modcontext, $userid),
                    ];
                }

                $studentrows = self::get_student_summary_rows(
                    $students,
                    $takensessionids,
                    $cm->id
                );

                return [
                    'hasattendance' => true,
                    'hasattendancedata' => !empty($sessionrows) || !empty($studentrows),
                    'pluginenabled' => true,
                    'needsactivity' => false,
                    'coursename' => format_string($course->fullname, true, ['context' => $coursecontext]),
                    'courseurl' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                    'manageurl' => $att->url_manage()->out(false),
                    'reporturl' => $att->url_report()->out(false),
                    'takeurl' => $att->url_manage()->out(false),
                    'studentcount' => $studentcount,
                    'sessions' => $sessionrows,
                    'students' => $studentrows,
                    'hassessions' => !empty($sessionrows),
                    'hasstudents' => !empty($studentrows),
                    'totalsessions' => count($sessionrows),
                ];
            }
        }

        if (!empty($courses)) {
            $defaults['needsactivity'] = true;
            $first = reset($courses);
            $defaults['courseurl'] = (new \moodle_url('/course/view.php', ['id' => $first->id]))->out(false);
            $defaults['coursename'] = format_string($first->fullname, true,
                ['context' => \context_course::instance($first->id)]);
        }

        return $defaults;
    }

    /**
     * Average attendance % across taken sessions for this teacher's learners only.
     *
     * @param array $courses
     * @param int $userid
     * @return string Display label e.g. "72%" or "—"
     */
    public static function get_average_percent_label(array $courses, int $userid): string {
        global $CFG, $DB;

        $plugin = \core_plugin_manager::instance()->get_plugin_info('mod_attendance');
        if (!$plugin || !$plugin->is_enabled()) {
            return '—';
        }

        require_once($CFG->libdir . '/grouplib.php');

        $present = 0;
        $total = 0;

        foreach ($courses as $course) {
            try {
                $modinfo = get_fast_modinfo($course, $userid);
            } catch (\Exception $e) {
                continue;
            }

            foreach ($modinfo->get_instances_of('attendance') as $cm) {
                $students = teacher_students::get_course_students($course, $userid, $cm);
                $studentids = array_map('intval', array_keys($students));
                if (empty($studentids)) {
                    continue;
                }

                $allowedgroupids = self::get_teacher_group_ids($course, $cm, $userid);
                $attendanceid = (int) $cm->instance;
                $sessions = $DB->get_records_select(
                    'attendance_sessions',
                    'attendanceid = ? AND lasttaken > 0',
                    [$attendanceid],
                    '',
                    'id, lasttaken, groupid'
                );

                foreach ($sessions as $session) {
                    if (!self::teacher_can_see_session($session, $allowedgroupids)) {
                        continue;
                    }
                    $stats = self::get_session_stats(
                        (int) $session->id,
                        count($studentids),
                        (int) $session->lasttaken,
                        $studentids
                    );
                    $present += $stats['present'];
                    $total += $stats['present'] + $stats['absent'] + $stats['notmarked'];
                }
            }
        }

        if ($total === 0) {
            return '—';
        }

        return (int) round(($present / $total) * 100) . '%';
    }

    /**
     * @param int $sessionid
     * @param int $enrolledcount Teacher-scoped learner count
     * @param int $lasttaken
     * @param int[] $studentids Empty = no learner filter (legacy); non-empty = only these users
     * @return array{present: int, absent: int, notmarked: int}
     */
    protected static function get_session_stats(
            int $sessionid,
            int $enrolledcount,
            int $lasttaken,
            array $studentids = []): array {
        global $DB;

        if ($lasttaken <= 0) {
            return [
                'present' => 0,
                'absent' => 0,
                'notmarked' => $enrolledcount,
            ];
        }

        if (empty($studentids)) {
            return [
                'present' => 0,
                'absent' => 0,
                'notmarked' => 0,
            ];
        }

        list($insql, $params) = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'sid');
        $params['sessionid'] = $sessionid;

        $rows = $DB->get_records_sql(
            "SELECT st.grade, COUNT(*) AS cnt
               FROM {attendance_log} al
               JOIN {attendance_statuses} st ON st.id = al.statusid
              WHERE al.sessionid = :sessionid
                AND al.studentid $insql
           GROUP BY st.grade",
            $params
        );

        $present = 0;
        $absent = 0;

        foreach ($rows as $row) {
            if ((float) $row->grade > 0) {
                $present += (int) $row->cnt;
            } else {
                $absent += (int) $row->cnt;
            }
        }

        $marked = $present + $absent;
        $notmarked = max(0, $enrolledcount - $marked);

        return [
            'present' => $present,
            'absent' => $absent,
            'notmarked' => $notmarked,
        ];
    }

    /**
     * Per-student attendance across taken sessions (teacher-scoped learners only).
     *
     * @param array<int,\stdClass> $students
     * @param array $takensessionids
     * @param int $cmid
     * @return array
     */
    protected static function get_student_summary_rows(
            array $students,
            array $takensessionids,
            int $cmid): array {
        global $DB;

        if (empty($takensessionids) || empty($students)) {
            return [];
        }

        list($insql, $params) = $DB->get_in_or_equal($takensessionids, SQL_PARAMS_NAMED);
        $totalsessions = count($takensessionids);

        $rows = [];
        foreach ($students as $student) {
            if (count($rows) >= self::STUDENT_LIMIT) {
                break;
            }

            $sqlparams = array_merge($params, ['studentid' => (int) $student->id]);
            $present = (int) $DB->get_field_sql(
                "SELECT COUNT(DISTINCT al.sessionid)
                   FROM {attendance_log} al
                   JOIN {attendance_statuses} st ON st.id = al.statusid
                  WHERE al.studentid = :studentid
                    AND al.sessionid $insql
                    AND st.grade > 0",
                $sqlparams
            );

            $percent = $totalsessions > 0 ? (int) round(($present / $totalsessions) * 100) : 0;

            $rows[] = [
                'name' => fullname($student),
                'presentcount' => $present,
                'totalsessions' => $totalsessions,
                'percent' => $percent,
                'percentlabel' => get_string('dashboardteacherstudentattendance', 'theme_iiidem2', (object) [
                    'present' => $present,
                    'total' => $totalsessions,
                    'percent' => $percent,
                ]),
                'detailurl' => (new \moodle_url('/mod/attendance/view.php', [
                    'id' => $cmid,
                    'studentid' => $student->id,
                ]))->out(false),
            ];
        }

        usort($rows, static function(array $a, array $b): int {
            return strcmp($a['name'], $b['name']);
        });

        return $rows;
    }

    /**
     * Group ids this teacher may use for attendance (null = all groups / no restriction).
     *
     * @param \stdClass $course
     * @param \cm_info $cm
     * @param int $teacherid
     * @return int[]|null null means all sessions/groups are visible
     */
    protected static function get_teacher_group_ids(\stdClass $course, \cm_info $cm, int $teacherid): ?array {
        $modcontext = \context_module::instance($cm->id);
        if (has_capability('moodle/site:accessallgroups', $modcontext, $teacherid)) {
            return null;
        }

        $groupmode = groups_get_activity_groupmode($cm, $course);
        $mygroups = groups_get_all_groups($course->id, $teacherid, (int) $cm->groupingid);

        if ((int) $groupmode === SEPARATEGROUPS) {
            return array_map('intval', array_keys($mygroups));
        }

        if (!empty($mygroups)) {
            return array_map('intval', array_keys($mygroups));
        }

        return null;
    }

    /**
     * @param \stdClass $session
     * @param int[]|null $allowedgroupids null = all visible
     * @return bool
     */
    protected static function teacher_can_see_session(\stdClass $session, ?array $allowedgroupids): bool {
        if ($allowedgroupids === null) {
            return true;
        }
        $groupid = (int) ($session->groupid ?? 0);
        // Common (all-class) sessions stay visible; group sessions need membership.
        if ($groupid === 0) {
            return true;
        }
        return in_array($groupid, $allowedgroupids, true);
    }
}
