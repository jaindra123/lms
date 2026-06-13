<?php
// This file is part of Moodle - http://moodle.org/
//
// Create Weekend-1 … Weekend-7 attendance sessions for a course (default: course 4).

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/attendance/lib.php');
require_once($CFG->dirroot . '/mod/attendance/locallib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'start' => '2026-06-07',
        'time' => '10:00',
        'duration' => 180,
        'count' => 7,
        'name' => 'Weekend Attendance',
        'calendar' => 1,
    ],
    [
        'h' => 'help',
        's' => 'start',
        't' => 'time',
        'd' => 'duration',
        'c' => 'count',
        'n' => 'name',
    ],
    ['courseid']
);

if (!empty($options['help'])) {
    $help = <<<EOF
Create Weekend-1 … Weekend-N attendance sessions for an IIIDEM course.

Usage:
  php theme/iiidem2/cli/setup_weekend_attendance.php [courseid] [options]

Arguments:
  courseid              Course id (default: 4)

Options:
  -s, --start=DATE      First weekend date YYYY-MM-DD (default: 2026-06-07)
  -t, --time=HH:MM      Session start time (default: 10:00)
  -d, --duration=MIN    Session length in minutes (default: 180)
  -c, --count=N         Number of weekend sessions (default: 7)
  -n, --name=NAME       Attendance activity name (default: Weekend Attendance)
      --calendar=0|1    Add Moodle calendar events (default: 1)
  -h, --help            Show this help

Examples:
  php theme/iiidem2/cli/setup_weekend_attendance.php 4
  php theme/iiidem2/cli/setup_weekend_attendance.php 4 --start=2026-09-06 --time=09:30

EOF;
    echo $help;
    exit(0);
}

$courseid = (int) ($unrecognized[0] ?? 4);
if ($courseid <= 0) {
    cli_error('Invalid course id.');
}

$plugin = core_plugin_manager::instance()->get_plugin_info('mod_attendance');
if (!$plugin || !$plugin->is_enabled()) {
    cli_error('The Attendance activity (mod_attendance) is not installed or enabled.');
}

$course = get_course($courseid);
cli_heading('Weekend attendance setup — course ' . $courseid . ': ' . format_string($course->fullname));

\core\session\manager::set_user(get_admin());

$startdate = strtotime($options['start']);
if ($startdate === false) {
    cli_error('Invalid --start date. Use YYYY-MM-DD.');
}

if (!preg_match('/^(\d{1,2}):(\d{2})$/', $options['time'], $timematch)) {
    cli_error('Invalid --time. Use HH:MM (e.g. 10:00).');
}

$hour = (int) $timematch[1];
$minute = (int) $timematch[2];
$duration = max(15, (int) $options['duration']);
$sessioncount = max(1, min(52, (int) $options['count']));
$activityname = trim($options['name']);
$addcalendar = !empty($options['calendar']);

$cm = theme_iiidem2_cli_find_attendance_cm($course);
if (!$cm) {
    cli_writeln('No Attendance activity found — creating "' . $activityname . '".');
    $cm = theme_iiidem2_cli_create_attendance_activity($course, $activityname);
    cli_writeln('Created Attendance activity (cmid ' . $cm->id . ').');
} else {
    cli_writeln('Using existing Attendance activity: ' . $cm->name . ' (cmid ' . $cm->id . ').');
}

$attrecord = $DB->get_record('attendance', ['id' => $cm->instance], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('attendance', $attrecord->id, $course->id, false, MUST_EXIST);
$context = context_module::instance($cm->id);
$att = new mod_attendance_structure($attrecord, $cm, $course, $context, null);

$created = 0;
$skipped = 0;

for ($i = 1; $i <= $sessioncount; $i++) {
    $dayoffset = ($i - 1) * 7;
    $sessiondate = strtotime('+' . $dayoffset . ' days', $startdate);
    $sessiondate = mktime($hour, $minute, 0, (int) date('n', $sessiondate), (int) date('j', $sessiondate), (int) date('Y', $sessiondate));

    $label = 'Weekend-' . $i;
    $description = $label;

    if (theme_iiidem2_cli_attendance_session_exists((int) $attrecord->id, $sessiondate, $label)) {
        cli_writeln('Skip ' . $label . ' — session already exists for ' . userdate($sessiondate, get_string('strftimedatefullshort', 'core_langconfig')));
        $skipped++;
        continue;
    }

    $sess = new stdClass();
    $sess->sessdate = $sessiondate;
    $sess->duration = $duration * MINSECS;
    $sess->descriptionitemid = 0;
    $sess->description = $description;
    $sess->descriptionformat = FORMAT_PLAIN;
    $sess->calendarevent = $addcalendar ? 1 : 0;
    $sess->timemodified = time();
    $sess->studentscanmark = 0;
    $sess->allowupdatestatus = 0;
    $sess->autoassignstatus = 0;
    $sess->subnet = $attrecord->subnet ?? '';
    $sess->studentpassword = '';
    $sess->automark = 0;
    $sess->automarkcompleted = 0;
    $sess->absenteereport = (int) get_config('attendance', 'absenteereport_default');
    $sess->includeqrcode = 0;
    $sess->statusset = 0;
    $sess->groupid = 0;
    $sess->automarkcmid = 0;
    $sess->studentsearlyopentime = (int) get_config('attendance', 'studentsearlyopentime');

    $sessionid = $att->add_session($sess);
    $created++;

    cli_writeln('Created ' . $label . ' (session id ' . $sessionid . ') — '
        . userdate($sessiondate, get_string('strftimedatefullshort', 'core_langconfig'))
        . ' ' . userdate($sessiondate, get_string('strftimetime', 'core_langconfig'))
        . ', ' . $duration . ' min');
}

cli_writeln('');
cli_writeln('Done. Created: ' . $created . ', skipped: ' . $skipped . '.');
cli_writeln('Manage sessions: ' . (new moodle_url('/mod/attendance/manage.php', ['id' => $cm->id]))->out(false));
cli_writeln('Attendance report: ' . (new moodle_url('/mod/attendance/report.php', ['id' => $cm->id]))->out(false));

/**
 * Find an attendance course-module in a course.
 *
 * @param stdClass $course
 * @return cm_info|null
 */
function theme_iiidem2_cli_find_attendance_cm(stdClass $course): ?cm_info {
    $modinfo = get_fast_modinfo($course);
    foreach ($modinfo->get_instances_of('attendance') as $cm) {
        return $cm;
    }
    return null;
}

/**
 * Create a new attendance activity in section 0.
 *
 * @param stdClass $course
 * @param string $name
 * @return cm_info
 */
function theme_iiidem2_cli_create_attendance_activity(stdClass $course, string $name): cm_info {
    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'attendance';
    $moduleinfo->course = $course->id;
    $moduleinfo->section = 0;
    $moduleinfo->name = $name;
    $moduleinfo->intro = '';
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->visible = 1;
    $moduleinfo->visibleoncoursepage = 0;
    $moduleinfo->grade = 100;
    $moduleinfo->subnet = '';

    $moduleinfo = add_moduleinfo($moduleinfo, $course);

    rebuild_course_cache($course->id, true);
    $modinfo = get_fast_modinfo($course);
    return $modinfo->get_cm($moduleinfo->coursemodule);
}

/**
 * Whether a matching session already exists.
 *
 * @param int $attendanceid
 * @param int $sessdate
 * @param string $label
 * @return bool
 */
function theme_iiidem2_cli_attendance_session_exists(int $attendanceid, int $sessdate, string $label): bool {
    global $DB;

    if ($DB->record_exists('attendance_sessions', [
        'attendanceid' => $attendanceid,
        'sessdate' => $sessdate,
    ])) {
        return true;
    }

    return $DB->record_exists_select(
        'attendance_sessions',
        'attendanceid = :aid AND ' . $DB->sql_compare_text('description') . ' = :desc',
        ['aid' => $attendanceid, 'desc' => $label]
    );
}
