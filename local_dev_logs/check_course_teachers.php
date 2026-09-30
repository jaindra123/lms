<?php
define('CLI_SCRIPT', true);
require __DIR__ . '/../config.php';
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/enrollib.php');

$featured = (string) get_config('theme_iiidem2', 'featuredinstructors');
cli_writeln('featuredinstructors=' . str_replace("\n", ' | ', $featured));

$courses = $DB->get_records_select('course', 'id > 1', [], 'id ASC', 'id,shortname,fullname');
foreach ($courses as $course) {
    $context = context_course::instance($course->id);
    $roles = $DB->get_records_list('role', 'shortname', ['editingteacher', 'teacher']);
    $names = [];
    foreach ($roles as $role) {
        $users = get_role_users($role->id, $context, false, 'u.id, u.firstname, u.lastname, u.picture');
        foreach ($users as $u) {
            $names[] = $role->shortname . ':' . $u->id . ':' . fullname($u);
        }
    }
    cli_writeln('course ' . $course->id . ' [' . $course->shortname . '] teachers=' . ($names ? implode(', ', $names) : '(none)'));
}
