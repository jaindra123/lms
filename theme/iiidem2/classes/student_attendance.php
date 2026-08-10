<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Student attendance for the learner dashboard (own records only).
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_attendance {

    /** @var int Max session rows shown on the dashboard. */
    private const SESSION_LIMIT = 20;

    /**
     * Dashboard context: this student's sessions only (never classmates).
     *
     * @param array $courses Enrolled courses.
     * @param int $userid Student user id.
     * @return array
     */
    public static function get_dashboard_context(array $courses, int $userid): array {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

        $defaults = [
            'hasattendance' => false,
            'hasattendancedata' => false,
            'pluginenabled' => false,
            'attendancesummary' => '',
            'hasattendancesummary' => false,
            'attendancesessions' => [],
            'hasattendancesessions' => false,
            'attendanceviewurl' => '',
            'hasattendanceviewurl' => false,
        ];

        $plugin = \core_plugin_manager::instance()->get_plugin_info('mod_attendance');
        if (!$plugin || !$plugin->is_enabled()) {
            return $defaults;
        }

        $defaults['pluginenabled'] = true;
        require_once($CFG->libdir . '/enrollib.php');

        $sessionrows = [];
        $presenttotal = 0;
        $markedtotal = 0;
        $viewurl = '';

        foreach ($courses as $course) {
            try {
                $modinfo = get_fast_modinfo($course, $userid);
            } catch (\Exception $e) {
                continue;
            }

            foreach ($modinfo->get_instances_of('attendance') as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }

                $modcontext = \context_module::instance($cm->id);
                if (!has_capability('mod/attendance:view', $modcontext, $userid)) {
                    continue;
                }

                // Teachers / managers use the teacher attendance UI, not this panel.
                if (has_capability('mod/attendance:takeattendances', $modcontext, $userid)
                        || has_capability('mod/attendance:manageattendances', $modcontext, $userid)
                        || has_capability('mod/attendance:viewreports', $modcontext, $userid)) {
                    continue;
                }

                $attendanceid = (int) $cm->instance;
                if ($viewurl === '') {
                    $viewurl = (new \moodle_url('/mod/attendance/view.php', [
                        'id' => $cm->id,
                        'studentid' => $userid,
                    ]))->out(false);
                }

                $sessions = $DB->get_records_sql(
                    "SELECT s.id, s.sessdate, s.duration, s.description, s.lasttaken,
                            al.statusid, st.acronym, st.description AS statusname, st.grade
                       FROM {attendance_sessions} s
                  LEFT JOIN {attendance_log} al
                         ON al.sessionid = s.id AND al.studentid = :studentid
                  LEFT JOIN {attendance_statuses} st ON st.id = al.statusid
                      WHERE s.attendanceid = :aid
                   ORDER BY s.sessdate DESC",
                    [
                        'aid' => $attendanceid,
                        'studentid' => $userid,
                    ],
                    0,
                    self::SESSION_LIMIT
                );

                foreach ($sessions as $session) {
                    $statuslabel = theme_iiidem2_str(
                        'dashboardstudentattendancenotmarked',
                        null,
                        'Not marked'
                    );
                    $statusclass = 'muted';
                    if (!empty($session->statusid)) {
                        $statuslabel = format_string((string) ($session->statusname ?: $session->acronym));
                        if ((float) $session->grade > 0) {
                            $statusclass = 'success';
                            $presenttotal++;
                        } else {
                            $statusclass = 'orange';
                        }
                        $markedtotal++;
                    }

                    $title = trim(html_to_text((string) $session->description, 0));
                    if ($title === '') {
                        $title = get_string('dashboardteacherattendancesession', 'theme_iiidem2',
                            userdate((int) $session->sessdate, get_string('strftimedate', 'langconfig')));
                    }

                    $sessionrows[] = [
                        'date' => userdate((int) $session->sessdate, get_string('strftimedate', 'langconfig')),
                        'time' => userdate((int) $session->sessdate, get_string('strftimetime', 'langconfig')),
                        'title' => $title,
                        'coursename' => format_string($course->fullname),
                        'statuslabel' => $statuslabel,
                        'statusclass' => $statusclass,
                    ];
                }
            }
        }

        if ($sessionrows === [] && $viewurl === '') {
            return $defaults;
        }

        $summary = '';
        if ($markedtotal > 0) {
            $percentmarked = (int) round(($presenttotal / $markedtotal) * 100);
            $summary = theme_iiidem2_str('dashboardstudentattendancesummary', (object) [
                'present' => $presenttotal,
                'total' => $markedtotal,
                'percent' => $percentmarked,
            ], 'Present {$a->present} of {$a->total} marked sessions ({$a->percent}%)');
        }

        return [
            'hasattendance' => true,
            'hasattendancedata' => !empty($sessionrows),
            'pluginenabled' => true,
            'attendancesummary' => $summary,
            'hasattendancesummary' => $summary !== '',
            'attendancesessions' => $sessionrows,
            'hasattendancesessions' => !empty($sessionrows),
            'attendanceviewurl' => $viewurl,
            'hasattendanceviewurl' => $viewurl !== '',
        ];
    }
}
