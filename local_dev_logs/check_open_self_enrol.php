<?php
define('CLI_SCRIPT', true);
global $USER, $CFG, $DB;
require __DIR__ . '/../config.php';
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

echo 'open course 8=' . (\theme_iiidem2\open_self_enrol::is_open_course(8) ? 'yes' : 'no') . PHP_EOL;
echo 'open course 4=' . (\theme_iiidem2\open_self_enrol::is_open_course(4) ? 'yes' : 'no') . PHP_EOL;

$inst = \theme_iiidem2\open_self_enrol::ensure_instance(8);
echo 'course8 self status=' . (int) $inst->status . ' newenrols=' . (int) $inst->customint6 . ' role=' . (int) $inst->roleid . PHP_EOL;

$plugin = enrol_get_plugin('self');
$admins = array_map('intval', explode(',', $CFG->siteadmins));
$candidates = $DB->get_records_select('user', 'deleted = 0 AND suspended = 0 AND id > 2', [], 'id ASC', 'id,username', 0, 20);
foreach ($candidates as $user) {
    if (in_array((int) $user->id, $admins, true)) {
        continue;
    }
    $ctx = context_course::instance(8);
    if (is_enrolled($ctx, $user, '', true)) {
        echo "user {$user->id} {$user->username} already enrolled on 8\n";
        continue;
    }
    $USER = $user;
    $status = $plugin->can_self_enrol($inst);
    echo "user {$user->id} {$user->username} can_self_enrol=" . ($status === true ? 'true' : (string) $status) . PHP_EOL;
    break;
}
