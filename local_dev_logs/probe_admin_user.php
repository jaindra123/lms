<?php
define('CLI_SCRIPT', true);
require dirname(__DIR__) . '/config.php';
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/admin/user/user_bulk_forms.php');

global $DB, $USER, $PAGE, $OUTPUT, $CFG;

$admin = get_admin();
if (!$admin) {
    fwrite(STDERR, "no admin\n");
    exit(1);
}
\core\session\manager::set_user($admin);

echo "admin id={$admin->id} username={$admin->username}\n";
echo "wwwroot={$CFG->wwwroot}\n";
echo "debugdisplay=" . (int)$CFG->debugdisplay . " MOODLE_ENV=" . (defined('MOODLE_ENV') ? MOODLE_ENV : 'undef') . "\n";

try {
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url(new moodle_url('/admin/user.php'));
    $bulkactions = new user_bulk_action_form(
        new moodle_url('/admin/user/user_bulk.php'),
        ['excludeactions' => ['displayonpage', 'download'], 'passuserids' => true, 'hidesubmit' => true],
        'post',
        '',
        ['id' => 'user-bulk-action-form']
    );
    echo "bulk form ok, has_bulk=" . (int)$bulkactions->has_bulk_actions() . "\n";
    $report = \core_reportbuilder\system_report_factory::create(
        \core_admin\reportbuilder\local\systemreports\users::class,
        context_system::instance(),
        parameters: ['withcheckboxes' => $bulkactions->has_bulk_actions()]
    );
    echo "report created\n";
    $html = $report->output();
    echo "output bytes=" . strlen($html) . "\n";
    echo (str_contains($html, 'Something went wrong') ? "CONTAINS GENERIC ERROR\n" : "no generic error in report html\n");
} catch (Throwable $e) {
    echo "EXCEPTION " . get_class($e) . "\n";
    echo "code=" . ($e->errorcode ?? '') . "\n";
    echo "msg=" . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
