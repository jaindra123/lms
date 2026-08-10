<?php
/**
 * Webex OAuth connect / callback.
 *
 * @package local_iiidem_webexattendance
 */

require(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

global $SESSION;

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$code = optional_param('code', '', PARAM_RAW_TRIMMED);
$state = optional_param('state', '', PARAM_RAW_TRIMMED);
$error = optional_param('error', '', PARAM_TEXT);

$PAGE->set_url(new moodle_url('/local/iiidem_webexattendance/oauth.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'local_iiidem_webexattendance'));

$returnurl = new moodle_url('/admin/settings.php', ['section' => 'local_iiidem_webexattendance']);

if ($error !== '') {
    // Provider error codes may be verbose; never echo raw query values to the UI.
    error_log('local_iiidem_webexattendance oauth provider error: ' . substr(clean_param($error, PARAM_TEXT), 0, 200));
    redirect($returnurl, get_string('oauth_error_generic', 'local_iiidem_webexattendance'), null, \core\output\notification::NOTIFY_ERROR);
}

if ($code !== '') {
    // State must match the opaque value stored when starting authorize (not sesskey).
    $expected = (string) ($SESSION->local_iiidem_webexattendance_oauth_state ?? '');
    unset($SESSION->local_iiidem_webexattendance_oauth_state);
    if ($expected === '' || $state === '' || !hash_equals($expected, $state)) {
        throw new moodle_exception('invalidsesskey', 'error');
    }
    try {
        \local_iiidem_webexattendance\oauth::exchange_code($code);
        redirect($returnurl, get_string('oauth_success', 'local_iiidem_webexattendance'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (Throwable $e) {
        error_log('local_iiidem_webexattendance oauth exchange: ' . $e->getMessage());
        redirect($returnurl, get_string('oauth_error_generic', 'local_iiidem_webexattendance'), null, \core\output\notification::NOTIFY_ERROR);
    }
}

if ($action === 'connect') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('invalidrequest', 'error');
    }
    require_sesskey();
    if (!\local_iiidem_webexattendance\oauth::is_configured()) {
        redirect($returnurl, get_string('oauth_missingconfig', 'local_iiidem_webexattendance'), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect(\local_iiidem_webexattendance\oauth::authorize_url());
}

redirect($returnurl);
