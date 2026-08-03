<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

$sql = "SELECT cm.id, cm.course, c.fullname as coursename, a.name
          FROM {course_modules} cm
          JOIN {modules} m ON m.id = cm.module
          JOIN {attendance} a ON a.id = cm.instance
          JOIN {course} c ON c.id = cm.course
         WHERE m.name = 'attendance' AND cm.deletioninprogress = 0
      ORDER BY cm.id DESC";
$cms = $DB->get_records_sql($sql, [], 0, 15);
if (!$cms) {
    echo "NONE\n";
    exit(0);
}
foreach ($cms as $cm) {
    echo $CFG->wwwroot . '/mod/attendance/view.php?id=' . $cm->id
        . ' | course=' . $cm->course . ' | ' . $cm->coursename . ' | ' . $cm->name . "\n";
}
