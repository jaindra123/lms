<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Certificate summaries for the instructor dashboard (mod_customcert).
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_certificates {

    /** @var int Max issued-certificate rows on the dashboard. */
    private const ISSUE_LIMIT = 20;

    /**
     * Dashboard context: certificate activities + recently issued certificates.
     *
     * @param array $courses Teaching courses for the teacher.
     * @param int $userid Teacher user id.
     * @return array
     */
    public static function get_dashboard_context(array $courses, int $userid): array {
        global $DB;

        $defaults = [
            'hascertificates' => false,
            'hascertificatedata' => false,
            'pluginenabled' => false,
            'needscertificate' => false,
            'certificates' => [],
            'issues' => [],
            'hasissues' => false,
            'totalissued' => 0,
            'totalissuedlabel' => '0',
            'primarycertname' => '',
            'primarycoursename' => '',
            'manageurl' => '',
            'reporturl' => '',
            'courseurl' => '',
        ];

        $plugin = \core_plugin_manager::instance()->get_plugin_info('mod_customcert');
        if (!$plugin || !$plugin->is_enabled()) {
            return $defaults;
        }

        $defaults['pluginenabled'] = true;

        if (empty($courses)) {
            $defaults['needscertificate'] = true;
            return $defaults;
        }

        $certificates = [];
        $issues = [];
        $totalissued = 0;
        $primarycourse = $courses[0];

        foreach ($courses as $course) {
            try {
                $modinfo = get_fast_modinfo($course, $userid);
            } catch (\Exception $e) {
                continue;
            }

            $coursecontext = \context_course::instance($course->id);
            $coursename = format_string($course->fullname, true, ['context' => $coursecontext]);

            foreach ($modinfo->get_instances_of('customcert') as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }

                $modcontext = \context_module::instance($cm->id);
                if (!has_capability('mod/customcert:viewreport', $modcontext, $userid) &&
                        !has_capability('mod/customcert:manage', $modcontext, $userid)) {
                    continue;
                }

                $customcert = $DB->get_record('customcert', ['id' => $cm->instance], '*', IGNORE_MISSING);
                if (!$customcert) {
                    continue;
                }

                $issuedcount = (int) $DB->count_records('customcert_issues', ['customcertid' => $customcert->id]);
                $totalissued += $issuedcount;

                $certificates[] = [
                    'name' => format_string($customcert->name, true, ['context' => $modcontext]),
                    'coursename' => $coursename,
                    'issuedcount' => $issuedcount,
                    'issuedlabel' => get_string('dashboardteachercertissuedcount', 'theme_iiidem2', $issuedcount),
                    'manageurl' => (new \moodle_url('/mod/customcert/view.php', ['id' => $cm->id]))->out(false),
                    'editurl' => (new \moodle_url('/course/mod.php', [
                        'update' => $cm->id,
                        'return' => true,
                    ]))->out(false),
                ];

                $certissues = $DB->get_records_sql(
                    "SELECT ci.id AS issueid, ci.timecreated, ci.code, u.id AS userid,
                            u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                            u.middlename, u.alternatename
                       FROM {customcert_issues} ci
                       JOIN {user} u ON u.id = ci.userid
                      WHERE ci.customcertid = :certid
                        AND u.deleted = 0
                   ORDER BY ci.timecreated DESC",
                    ['certid' => $customcert->id],
                    0,
                    self::ISSUE_LIMIT
                );

                foreach ($certissues as $issue) {
                    $studentname = fullname($issue);
                    $issues[] = [
                        'studentname' => $studentname,
                        'certname' => format_string($customcert->name, true, ['context' => $modcontext]),
                        'coursename' => $coursename,
                        'date' => userdate($issue->timecreated, get_string('strftimedatefullshort', 'core_langconfig')),
                        'code' => $issue->code ?? '',
                        'hascode' => !empty($issue->code),
                        'downloadurl' => (new \moodle_url('/mod/customcert/view.php', [
                            'id' => $cm->id,
                            'downloadissue' => $issue->issueid,
                        ]))->out(false),
                        'reporturl' => (new \moodle_url('/mod/customcert/view.php', ['id' => $cm->id]))->out(false),
                        'sorttime' => (int) $issue->timecreated,
                    ];
                }
            }
        }

        if (empty($certificates)) {
            $defaults['needscertificate'] = true;
            $defaults['courseurl'] = (new \moodle_url('/course/view.php', ['id' => $primarycourse->id]))->out(false);
            return $defaults;
        }

        usort($issues, static function(array $a, array $b): int {
            return $b['sorttime'] <=> $a['sorttime'];
        });
        $issues = array_slice($issues, 0, self::ISSUE_LIMIT);
        foreach ($issues as &$issue) {
            unset($issue['sorttime']);
        }
        unset($issue);

        $primary = $certificates[0];

        return [
            'hascertificates' => true,
            'hascertificatedata' => !empty($issues) || $totalissued > 0,
            'pluginenabled' => true,
            'needscertificate' => false,
            'certificates' => $certificates,
            'issues' => $issues,
            'hasissues' => !empty($issues),
            'totalissued' => $totalissued,
            'totalissuedlabel' => (string) $totalissued,
            'primarycertname' => $primary['name'],
            'primarycoursename' => $primary['coursename'],
            'manageurl' => $primary['manageurl'],
            'reporturl' => $primary['manageurl'],
            'courseurl' => (new \moodle_url('/course/view.php', ['id' => $primarycourse->id]))->out(false),
        ];
    }
}
