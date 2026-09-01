<?php
/**
 * Record that a logged-in user clicked Join for a Webex live-class CM.
 * Guests in Webex often have no real email; this click is used at sync time.
 *
 * @package local_iiidem_webexattendance
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$cmid = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
require_login($course, false, $cm);

if (isguestuser()) {
    echo json_encode(['ok' => false, 'error' => 'guest']);
    die;
}

\local_iiidem_webexattendance\attendance_sync::record_join_click($cmid, (int) $USER->id);

echo json_encode(['ok' => true]);
