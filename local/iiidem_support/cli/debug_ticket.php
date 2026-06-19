<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/iiidem_support/lib.php');

$t = $DB->get_record('local_iiidem_support_ticket', ['id' => 1]);
if (!$t) {
    echo "No ticket 1\n";
    exit(1);
}
echo "userid={$t->userid}\n";
echo "adminreply=" . var_export($t->adminreply, true) . "\n";
echo "status={$t->status}\n";
echo "timereplied=" . var_export($t->timereplied, true) . "\n";

$formatted = \local_iiidem_support\manager::get_user_ticket(1, (int) $t->userid);
echo "hasreply=" . ($formatted->hasreply ? 'yes' : 'no') . "\n";
