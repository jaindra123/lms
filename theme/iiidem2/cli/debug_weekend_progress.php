<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');
require_once($CFG->libdir . '/completionlib.php');

[$options, $unrecognized] = cli_get_params([], [], ['courseid', 'userid']);
$courseid = (int) ($unrecognized[0] ?? 4);
$userid = (int) ($unrecognized[1] ?? 5);

$course = get_course($courseid);
$user = core_user::get_user($userid, '*', MUST_EXIST);

cli_heading('Weekend progress debug — ' . fullname($user) . ' (course ' . $courseid . ')');

$completion = new completion_info($course);
cli_writeln('Course completion enabled: ' . ($completion->is_enabled() ? 'yes' : 'no'));
cli_writeln('User tracked for completion: ' . ($completion->is_tracked_user($userid) ? 'yes' : 'no'));

$modinfo = get_fast_modinfo($course, $userid);
foreach ($modinfo->get_section_info_all() as $section) {
    if ((int) $section->section === 0) {
        continue;
    }
    if (empty($modinfo->sections[$section->section])) {
        continue;
    }
    cli_writeln('');
    cli_writeln('Section ' . $section->section . ': ' . get_section_name($course, $section));
    foreach ($modinfo->sections[$section->section] as $cmid) {
        $cm = $modinfo->cms[$cmid];
        $enabled = $completion->is_enabled($cm) ? 'yes' : 'no';
        $visible = $cm->uservisible ? 'yes' : 'no';
        $data = $completion->get_data($cm, false, $userid);
        $state = (int) $data->completionstate;
        $statelabel = $state === COMPLETION_COMPLETE ? 'COMPLETE' : ($state === COMPLETION_COMPLETE_PASS ? 'PASS' : 'incomplete(' . $state . ')');
        cli_writeln('  cmid ' . $cmid . ' ' . $cm->modname . ': ' . $cm->name);
        cli_writeln('    tracked=' . $enabled . ' visible=' . $visible . ' state=' . $statelabel);
    }
}

$progress = theme_iiidem2_get_course_weekend_progress($course, $userid);
cli_writeln('');
cli_writeln('Dashboard: ' . $progress['progresslabel']);
cli_writeln('Completed weekends: ' . $progress['completedweekends'] . ' / ' . $progress['totalweekends']);
