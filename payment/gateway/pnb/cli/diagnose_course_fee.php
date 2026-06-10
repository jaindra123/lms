<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/payment/classes/helper.php');

$courseid = 4;
$course = get_course($courseid);

echo "=== Course $courseid fee setup ===\n";
foreach (enrol_get_instances($courseid, true) as $i) {
    if ($i->enrol === 'fee') {
        echo "Fee instance id={$i->id} cost={$i->cost} {$i->currency} account={$i->customint1} status={$i->status}\n";
        $gw = \core_payment\helper::get_available_gateways('enrol_fee', 'fee', (int) $i->id);
        echo "Available gateways: " . (empty($gw) ? 'NONE' : implode(',', $gw)) . "\n";
    }
}

echo "\n=== Enrolled users (active) ===\n";
$context = context_course::instance($courseid);
$users = get_enrolled_users($context, '', 0, 'u.id,u.username,u.email', 'u.username');
foreach ($users as $u) {
    echo "{$u->id} {$u->username} {$u->email}\n";
}

echo "\n=== Students with student role ===\n";
$studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);
$enrolled = get_role_users($studentrole, $context, true, 'u.id,u.username,u.email');
foreach ($enrolled as $u) {
    echo "{$u->id} {$u->username} {$u->email}\n";
}

echo "\n=== All student-role users (system) ===\n";
$allstudents = $DB->get_records_sql(
    "SELECT DISTINCT u.id, u.username, u.email
       FROM {user} u
       JOIN {role_assignments} ra ON ra.userid = u.id
       JOIN {role} r ON r.id = ra.roleid
      WHERE r.shortname = 'student' AND u.deleted = 0
   ORDER BY u.username"
);
foreach ($allstudents as $u) {
    $isenrolled = is_enrolled($context, $u->id, '', true) ? 'enrolled' : 'NOT enrolled';
    echo "{$u->id} {$u->username} {$isenrolled}\n";
}

echo "\n=== Enrol instances (all) ===\n";
foreach (enrol_get_instances($courseid, false) as $i) {
    echo "id={$i->id} enrol={$i->enrol} status={$i->status}\n";
}

echo "\n=== User enrolment methods (course 4) ===\n";
$records = $DB->get_records_sql(
    "SELECT ue.userid, u.username, e.enrol, e.id AS enrolid
       FROM {user_enrolments} ue
       JOIN {enrol} e ON e.id = ue.enrolid
       JOIN {user} u ON u.id = ue.userid
      WHERE e.courseid = ?",
    [$courseid]
);
foreach ($records as $x) {
    echo "{$x->userid} {$x->username} via {$x->enrol} (instance {$x->enrolid})\n";
}

require_once($CFG->dirroot . '/theme/iiidem2/lib.php');
echo "\n=== Payment context simulation ===\n";
foreach ($allstudents as $u) {
    \core\session\manager::set_user($u);
    $ctx = theme_iiidem2_get_course_fee_payment_context($course);
    echo "{$u->username}: hascoursefee=" . ($ctx['hascoursefee'] ? 'yes' : 'no')
        . " showcoursepayment=" . ($ctx['showcoursepayment'] ?? false ? 'yes' : 'no') . "\n";
}
