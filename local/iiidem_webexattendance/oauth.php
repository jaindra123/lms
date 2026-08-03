<?php
/**
 * Webex OAuth connect / callback.
 *
 * @package local_iiidem_webexattendance
 */

require(__DIR__ . '/../../config.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

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
    redirect($returnurl, get_string('oauth_error', 'local_iiidem_webexattendance', $error), null, \core\output\notification::NOTIFY_ERROR);
}

if ($code !== '') {
    // State was set to sesskey() when starting authorize.
    if ($state === '' || $state !== sesskey()) {
        throw new moodle_exception('invalidsesskey', 'error');
    }
    try {
        \local_iiidem_webexattendance\oauth::exchange_code($code);
        redirect($returnurl, get_string('oauth_success', 'local_iiidem_webexattendance'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (Throwable $e) {
        redirect($returnurl, get_string('oauth_error', 'local_iiidem_webexattendance', $e->getMessage()), null, \core\output\notification::NOTIFY_ERROR);
    }
}

if ($action === 'connect') {
    require_sesskey();
    if (!\local_iiidem_webexattendance\oauth::is_configured()) {
        redirect($returnurl, get_string('oauth_missingconfig', 'local_iiidem_webexattendance'), null, \core\output\notification::NOTIFY_ERROR);
    }
    redirect(\local_iiidem_webexattendance\oauth::authorize_url());
}

redirect($returnurl);
