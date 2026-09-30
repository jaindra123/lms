<?php
define('CLI_SCRIPT', true);
require __DIR__ . '/../config.php';
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

global $PAGE, $USER;

$users = $DB->get_records_select(
    'user',
    $DB->sql_like('username', ':q', false) . ' OR ' .
    $DB->sql_like('firstname', ':q2', false) . ' OR ' .
    $DB->sql_like('lastname', ':q3', false) . ' OR ' .
    $DB->sql_like('email', ':q4', false),
    ['q' => '%sanj%', 'q2' => '%sanj%', 'q3' => '%sanj%', 'q4' => '%sanj%'],
    'id ASC',
    'id,username,firstname,lastname,email,deleted,suspended,confirmed',
    0,
    20
);
echo "match_sanj=" . count($users) . PHP_EOL;
foreach ($users as $u) {
    echo "user {$u->id} {$u->username} {$u->firstname} {$u->lastname} del={$u->deleted} sus={$u->suspended} conf={$u->confirmed}" . PHP_EOL;
}

$total = $DB->count_records_select('user', 'deleted = 0 AND confirmed = 1 AND id > 1');
echo "confirmed_users={$total}" . PHP_EOL;

$enrols = $DB->get_records('enrol', ['courseid' => 8], 'id ASC', 'id,enrol,status');
foreach ($enrols as $e) {
    echo "enrol {$e->id} {$e->enrol} status={$e->status}" . PHP_EOL;
}

$admin = $DB->get_record('user', ['id' => 2], '*', MUST_EXIST);
\core\session\manager::set_user($admin);
$course = get_course(8);
$PAGE->set_context(context_course::instance(8));
$PAGE->set_course($course);

$sample = $DB->get_record_select('user', 'deleted = 0 AND confirmed = 1 AND id <> 2', [], '*', IGNORE_MULTIPLE);
if ($sample) {
    $cb = theme_iiidem2_control_view_profile($sample, $course);
    echo "sample_id={$sample->id} username={$sample->username} callback={$cb} prevent=" . (int) ($cb === \core_user::VIEWPROFILE_PREVENT) . PHP_EOL;
    $details = user_get_user_details($sample, $course, ['id', 'fullname']);
    echo "user_get_user_details=" . (is_array($details) ? 'array' : 'null') . PHP_EOL;
}

$sanjay = $DB->get_record('user', ['id' => 5]);
if ($sanjay) {
    $cb = theme_iiidem2_control_view_profile($sanjay, $course);
    echo "sanjay_callback={$cb}" . PHP_EOL;
    $details = user_get_user_details($sanjay, $course, ['id', 'fullname']);
    echo "sanjay_details=" . (is_array($details) ? ($details['fullname'] ?? 'array') : 'null') . PHP_EOL;
}

$manual = $DB->get_record('enrol', ['courseid' => 8, 'enrol' => 'manual']);
if ($manual) {
    require_once($CFG->dirroot . '/enrol/externallib.php');
    $ws = core_enrol_external::get_potential_users(8, (int) $manual->id, 'sanj', true, 0, 25);
    echo "ws_count=" . count($ws) . PHP_EOL;
    if (!empty($ws[0]['fullname'])) {
        echo "ws_first=" . $ws[0]['fullname'] . PHP_EOL;
    }
    $manager = new course_enrolment_manager($PAGE, $course);
    $result = $manager->get_potential_users($manual->id, 'sanj', true, 0, 25);
    echo "potential_sanj_sql=" . count($result['users']) . PHP_EOL;
    $result2 = $manager->get_potential_users($manual->id, '', true, 0, 5);
    echo "potential_blank_sql=" . count($result2['users']) . PHP_EOL;
}
