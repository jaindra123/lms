<?php
/**
 * Manually re-link a course module and/or run recording import (CLI).
 *
 * php local/iiidem_webexattendance/cli/import_recordings.php [--cmid=123] [--force]
 *
 * @package local_iiidem_webexattendance
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params([
    'cmid' => 0,
    'force' => false,
    'help' => false,
], [
    'h' => 'help',
    'f' => 'force',
]);

if (!empty($options['help'])) {
    echo "Import Webex recording links into Class videos.\n\n";
    echo "Options:\n";
    echo "  --cmid=N   Re-scan this course module (URL / webexactivity) first\n";
    echo "  --force    Reset recordingstatus to pending for that cmid\n";
    echo "  -h         Help\n";
    exit(0);
}

if (!\local_iiidem_webexattendance\oauth::is_connected()) {
    cli_error('Webex is not connected. Authorize under Site administration → IIIDEM Webex attendance.');
}

$cmid = (int) $options['cmid'];
if ($cmid > 0) {
    if (!empty($options['force'])) {
        global $DB;
        $DB->set_field('local_iiidem_webexatt', 'recordingstatus', 'pending', ['cmid' => $cmid]);
        $DB->set_field('local_iiidem_webexatt', 'classvideoid', 0, ['cmid' => $cmid]);
    }
    \local_iiidem_webexattendance\attendance_sync::sync_from_cm($cmid);
    cli_writeln('Linked/updated mapping for cmid=' . $cmid);
}

$count = \local_iiidem_webexattendance\attendance_sync::import_due_recordings(50);
cli_writeln('Imported ' . $count . ' recording(s) into Class videos.');
exit(0);
