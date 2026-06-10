<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/enrollib.php');

$courseid = (int) ($argv[1] ?? 4);
$username = $argv[2] ?? 'sanjay.iiidem@govcontractor.nic.in';

$user = $DB->get_record('user', ['username' => $username, 'deleted' => 0], '*', IGNORE_MISSING);
if (!$user) {
    cli_error('User not found: ' . $username);
}

cli_heading('Fee enrolments for ' . $username . ' in course ' . $courseid);

$records = $DB->get_records_sql(
    "SELECT ue.id AS ueid, e.enrol, e.id AS enrolid, ue.status, ue.timecreated
       FROM {user_enrolments} ue
       JOIN {enrol} e ON e.id = ue.enrolid
      WHERE ue.userid = ? AND e.courseid = ?",
    [$user->id, $courseid]
);

if (empty($records)) {
    cli_writeln('No enrolments found.');
    exit(0);
}

foreach ($records as $r) {
    cli_writeln("Method: {$r->enrol} | user_enrolment id (ue): {$r->ueid} | instance: {$r->enrolid}");
    if ($r->enrol === 'fee') {
        cli_writeln('  Unenrol URL (admin): ' . (new moodle_url('/enrol/unenroluser.php', [
            'ue' => $r->ueid,
            'id' => $courseid,
        ]))->out(false));
    }
}

$feeonly = ($argv[3] ?? '') === '--unenrol-fee';
if ($feeonly) {
    foreach ($records as $r) {
        if ($r->enrol !== 'fee') {
            continue;
        }
        $instance = $DB->get_record('enrol', ['id' => $r->enrolid], '*', MUST_EXIST);
        $plugin = enrol_get_plugin('fee');
        $plugin->unenrol_user($instance, $user->id);
        cli_writeln('Unenrolled ' . $username . ' from fee (ue ' . $r->ueid . ').');
    }
}
