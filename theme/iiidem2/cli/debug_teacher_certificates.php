<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

$teacher = $DB->get_record_sql(
    "SELECT u.id, u.email FROM {user} u
      JOIN {role_assignments} ra ON ra.userid = u.id
      JOIN {role} r ON r.id = ra.roleid
     WHERE r.shortname IN ('editingteacher', 'teacher')
       AND u.deleted = 0
  ORDER BY u.id ASC",
    null,
    IGNORE_MULTIPLE
);

if (!$teacher) {
    echo "No teacher found\n";
    exit(1);
}

\core\session\manager::set_user(\core_user::get_user($teacher->id));

$courses = enrol_get_users_courses($teacher->id, true);
$teaching = [];
foreach ($courses as $course) {
    if ((int) $course->id === SITEID) {
        continue;
    }
    $context = context_course::instance($course->id);
    if (has_capability('moodle/course:manageactivities', $context, $teacher->id) ||
            has_capability('moodle/grade:edit', $context, $teacher->id)) {
        $teaching[] = $course;
    }
}

$ctx = \theme_iiidem2\teacher_certificates::get_dashboard_context($teaching, (int) $teacher->id);

echo "Teacher: {$teacher->email} (id {$teacher->id})\n";
echo 'hascertificates: ' . (!empty($ctx['hascertificates']) ? 'yes' : 'no') . "\n";
echo 'totalissued: ' . ($ctx['totalissued'] ?? 0) . "\n";
echo 'issues shown: ' . count($ctx['issues'] ?? []) . "\n";
foreach ($ctx['certificates'] ?? [] as $cert) {
    echo "  - {$cert['name']} ({$cert['issuedlabel']})\n";
}
