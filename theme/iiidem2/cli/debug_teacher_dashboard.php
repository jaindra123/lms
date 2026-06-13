<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

$teacher = $DB->get_record_sql(
    "SELECT u.id FROM {user} u
      JOIN {role_assignments} ra ON ra.userid = u.id
      JOIN {role} r ON r.id = ra.roleid
     WHERE r.shortname IN ('editingteacher', 'teacher') AND u.deleted = 0
  ORDER BY u.id ASC",
    null,
    IGNORE_MULTIPLE
);

\core\session\manager::set_user(\core_user::get_user($teacher->id));
$ctx = \theme_iiidem2\teacher_dashboard::get_context((int) $teacher->id);
echo 'Teacher dashboard OK — hasattendance: ' . (!empty($ctx['hasattendance']) ? 'yes' : 'no') . "\n";
echo 'hascertificates: ' . (!empty($ctx['hascertificates']) ? 'yes' : 'no') . "\n";
echo 'hasroster: ' . (!empty($ctx['hasroster']) ? 'yes' : 'no') . "\n";
echo 'totalstudents: ' . ($ctx['totalstudents'] ?? 0) . "\n";
if (!empty($ctx['rostercourses'])) {
    foreach ($ctx['rostercourses'] as $course) {
        echo "  {$course['coursename']}: {$course['studentcount']} students\n";
        foreach ($course['students'] as $student) {
            echo "    - {$student['name']} -> {$student['detailurl']}\n";
        }
    }
}
