<?php
define('CLI_SCRIPT', true);
require __DIR__ . '/../config.php';
require_once($CFG->libdir . '/enrollib.php');

$enabled = get_config('core', 'enrol_plugins_enabled');
echo "enrol_plugins_enabled={$enabled}\n";
echo "registrationcourseids=" . get_config('theme_iiidem2', 'registrationcourseids') . "\n";
echo "sharedreadingcourses=" . get_config('theme_iiidem2', 'sharedreadingcourses') . "\n";

$courses = $DB->get_records_select('course', 'id > 1', [], 'id', 'id,shortname,fullname,visible');
foreach ($courses as $course) {
    echo "\nCOURSE {$course->id} {$course->shortname} visible={$course->visible}\n";
    $instances = $DB->get_records('enrol', ['courseid' => $course->id], 'id');
    foreach ($instances as $inst) {
        echo "  enrol={$inst->enrol} status={$inst->status} roleid={$inst->roleid} cost={$inst->cost} password=" .
            (($inst->password ?? '') !== '' ? 'yes' : 'no') . " enrolperiod={$inst->enrolperiod}\n";
    }
    echo "  self_enabled=" . (enrol_is_enabled('self') ? '1' : '0') . "\n";
}
