<?php
/**
 * Link Webex URL/activity to Attendance and optionally force-sync participants.
 *
 * Usage:
 *   php link_and_sync.php --webexcmid=49 --attendancecmid=57
 *   php link_and_sync.php --webexcmid=49 --attendancecmid=57 --sync=1
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

\core\cron::setup_user();

list($options, $unrecognized) = cli_get_params([
    'webexcmid' => 49,
    'attendancecmid' => 57,
    'sync' => 0,
    'help' => false,
], [
    'h' => 'help',
]);

if (!empty($options['help'])) {
    echo "Link Webex CM to Attendance and optionally sync.\n";
    echo "  --webexcmid=49 --attendancecmid=57 --sync=1\n";
    exit(0);
}

$webexcmid = (int) $options['webexcmid'];
$attendancecmid = (int) $options['attendancecmid'];
$dosync = (int) $options['sync'];

if ($webexcmid < 1 || $attendancecmid < 1) {
    cli_error('webexcmid and attendancecmid are required');
}

if (!\local_iiidem_webexattendance\oauth::is_connected()) {
    $url = (new moodle_url('/admin/settings.php', ['section' => 'local_iiidem_webexattendance']))->out(false);
    echo "BLOCKED: Webex OAuth is not connected.\n";
    echo "1) Open: {$url}\n";
    echo "2) Click Connect Webex and authorize.\n";
    echo "3) Re-run this script with --sync=1\n";
    // Still create the mapping/session so UI is ready.
}

// Temporarily ensure autosync path runs.
set_config('autosync', 1, 'local_iiidem_webexattendance');
set_config('enabled', 1, 'local_iiidem_webexattendance');

echo "Creating/updating attendance session from Webex CM {$webexcmid}...\n";
\local_iiidem_webexattendance\attendance_sync::sync_from_cm($webexcmid);

$record = $DB->get_record('local_iiidem_webexatt', ['cmid' => $webexcmid]);
if (!$record) {
    cli_error('No mapping created. Is CM a webexactivity or Webex URL?');
}

// Force mapping onto the Attendance activity the user created.
if ((int) $record->attendancecmid !== $attendancecmid) {
    $record->attendancecmid = $attendancecmid;
    $record->timemodified = time();
    $DB->update_record('local_iiidem_webexatt', $record);
    echo "Updated mapping attendancecmid -> {$attendancecmid}\n";
}

echo "Mapping OK: cmid={$record->cmid} attendancecmid={$record->attendancecmid}"
    . " sessionid={$record->sessionid} status={$record->status}\n";
echo "joinurl={$record->joinurl}\n";
echo "meetingnumber={$record->meetingnumber}\n";
echo "Manage: {$CFG->wwwroot}/mod/attendance/manage.php?id={$attendancecmid}\n";

if (!$dosync) {
    echo "Session linked. Add --sync=1 after Connect Webex to pull participant marks.\n";
    exit(0);
}

if (!\local_iiidem_webexattendance\oauth::is_connected()) {
    cli_error('Cannot sync participants until Webex is connected.');
}

// Allow re-sync even if previously synced/errored or endtime not reached yet.
$record->status = \local_iiidem_webexattendance\attendance_sync::STATUS_PENDING;
$record->endtime = min((int) $record->endtime, time() - HOURSECS);
$DB->update_record('local_iiidem_webexatt', $record);

echo "Syncing participants from Webex...\n";
try {
    \local_iiidem_webexattendance\attendance_sync::sync_one($record);
    $record = $DB->get_record('local_iiidem_webexatt', ['id' => $record->id], '*', MUST_EXIST);
    echo "DONE status={$record->status} message={$record->syncmessage}\n";
} catch (Throwable $e) {
    echo "SYNC ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
