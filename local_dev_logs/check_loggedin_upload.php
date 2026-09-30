<?php
define('CLI_SCRIPT', true);
require __DIR__ . '/../config.php';
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

foreach ([7, 8, 4] as $id) {
    $course = $DB->get_record('course', ['id' => $id], 'id,shortname,fullname');
    if (!$course) {
        cli_writeln("course {$id} missing");
        continue;
    }
    $allow = \theme_iiidem2\shared_readings::allows_loggedin_without_enrol($id) ? 'yes' : 'no';
    $on = \theme_iiidem2\shared_readings::is_enabled($id) ? 'yes' : 'no';
    cli_writeln("course {$id} [{$course->shortname}] enabled={$on} without_enrol={$allow}");
}

$admins = array_map('intval', explode(',', $CFG->siteadmins));
$candidates = $DB->get_records_select('user', 'deleted = 0 AND suspended = 0 AND id > 2', [], 'id ASC', 'id,username', 0, 40);
$course8 = get_course(8);
$course4 = get_course(4);
$ctx8 = context_course::instance(8);
$ctx4 = context_course::instance(4);
foreach ($candidates as $user) {
    if (in_array((int) $user->id, $admins, true)) {
        continue;
    }
    if (isguestuser($user)) {
        continue;
    }
    $en8 = is_enrolled($ctx8, $user, '', true) ? 'yes' : 'no';
    $en4 = is_enrolled($ctx4, $user, '', true) ? 'yes' : 'no';
    $a8 = \theme_iiidem2\shared_readings::can_access($course8, (int) $user->id) ? 'yes' : 'no';
    $a4 = \theme_iiidem2\shared_readings::can_access($course4, (int) $user->id) ? 'yes' : 'no';
    cli_writeln("user {$user->id} {$user->username} enrolled8={$en8} access8={$a8} enrolled4={$en4} access4={$a4}");
    if ($en8 === 'no') {
        break;
    }
}
