<?php
// This file is part of Moodle - http://moodle.org/
//
// Reset activity completion for a user on a course (weekend progress testing).

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');
require_once($CFG->libdir . '/completionlib.php');

[$options, $unrecognized] = cli_get_params(
    ['help' => false, 'email' => ''],
    ['h' => 'help', 'e' => 'email'],
    ['courseid', 'userid']
);

if (!empty($options['help'])) {
    echo <<<EOF
Reset activity completion for weekend progress testing.

Usage:
  php theme/iiidem2/cli/reset_weekend_progress.php [courseid] [userid] [--email=EMAIL]

Arguments:
  courseid    Course id (default: 4)
  userid      User id (default: 5 = Sanjay)

Options:
  -e, --email=EMAIL   Resolve user by email instead of userid
  -h, --help          Show this help

Examples:
  php theme/iiidem2/cli/reset_weekend_progress.php 4 5
  php theme/iiidem2/cli/reset_weekend_progress.php 4 --email=sanjay.iiidem@govcontractor.nic.in

EOF;
    exit(0);
}

$courseid = (int) ($unrecognized[0] ?? 4);
$userid = (int) ($unrecognized[1] ?? 5);

if (!empty($options['email'])) {
    $user = $DB->get_record('user', ['email' => $options['email'], 'deleted' => 0], '*', MUST_EXIST);
    $userid = (int) $user->id;
}

$course = get_course($courseid);
$user = core_user::get_user($userid, '*', MUST_EXIST);

cli_heading('Reset weekend progress — ' . fullname($user) . ' / course ' . $courseid);

$cmids = $DB->get_fieldset_sql(
    'SELECT cm.id
       FROM {course_modules} cm
      WHERE cm.course = ?
        AND cm.deletioninprogress = 0
        AND cm.completion > 0',
    [$courseid]
);

if (empty($cmids)) {
    cli_writeln('No completion-tracked activities found on this course.');
    exit(0);
}

list($insql, $params) = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
$params['userid'] = $userid;

$deletedcompletion = $DB->delete_records_select(
    'course_modules_completion',
    'userid = :userid AND coursemoduleid ' . $insql,
    $params
);

$deletedviewed = $DB->delete_records_select(
    'course_modules_viewed',
    'userid = :userid AND coursemoduleid ' . $insql,
    $params
);

// Clear course completion record if present.
$DB->delete_records('course_completions', ['course' => $courseid, 'userid' => $userid]);

cache::make('core', 'completion')->purge();
rebuild_course_cache($courseid, true);

cli_writeln('Removed ' . $deletedcompletion . ' completion record(s) and ' . $deletedviewed . ' viewed record(s).');

$progress = theme_iiidem2_get_course_weekend_progress($course, $userid);
cli_writeln('Dashboard should now show: ' . $progress['progresslabel']);
cli_writeln('');
cli_writeln('Next: log in as this user, open course ' . $courseid . ', click Preview per weekend, refresh dashboard.');
