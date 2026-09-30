<?php
define('CLI_SCRIPT', true);
require __DIR__ . '/../config.php';
require_once($CFG->libdir . '/clilib.php');

$enabled = get_config('theme_iiidem2', 'sharedreadingcourses');
$disabled = get_config('theme_iiidem2', 'sharedreadingdisabled');
cli_writeln('sharedreadingcourses=' . (string) $enabled);
cli_writeln('sharedreadingdisabled=' . (string) $disabled);
cli_writeln('table_ready=' . (\theme_iiidem2\shared_readings::table_ready() ? '1' : '0'));

$courses = $DB->get_records_select('course', 'id > 1', [], 'id ASC', 'id,shortname,fullname');
foreach ($courses as $course) {
    $id = (int) $course->id;
    $on = \theme_iiidem2\shared_readings::is_enabled($id) ? 'yes' : 'no';
    $auto = \theme_iiidem2\shared_readings::should_auto_enable($id) ? 'yes' : 'no';
    cli_writeln("course {$id} [{$course->shortname}] enabled={$on} auto={$auto}");
}
