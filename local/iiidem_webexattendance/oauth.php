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
    // Show a short, safe provider code to site admins (helps fix redirect_uri_mismatch etc.).
    $safeerror = clean_param($error, PARAM_ALPHANUMEXT);
    if ($safeerror === '') {
        $safeerror = 'unknown';
    }
    error_log('local_iiidem_webexattendance oauth provider error: ' . $safeerror);
    redirect(
        $returnurl,
        get_string('oauth_error', 'local_iiidem_webexattendance', $safeerror),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

if ($code !== '') {
    // State must match the opaque value stored when starting authorize (not sesskey).
    $expected = (string) ($SESSION->local_iiidem_webexattendance_oauth_state ?? '');
    unset($SESSION->local_iiidem_webexattendance_oauth_state);
    if ($expected === '' || $state === '' || !hash_equals($expected, $state)) {
        redirect(
            $returnurl,
            get_string('oauth_error', 'local_iiidem_webexattendance', 'invalid_state_or_session'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    try {
        \local_iiidem_webexattendance\oauth::exchange_code($code);
        redirect($returnurl, get_string('oauth_success', 'local_iiidem_webexattendance'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (Throwable $e) {
        error_log('local_iiidem_webexattendance oauth exchange: ' . $e->getMessage());
        // Prefer Webex error text when present (admin-only page).
        $raw = $e->getMessage();
        $hint = 'token_exchange_failed';
        if (preg_match('/invalid_client/i', $raw)) {
            $hint = 'invalid_client (check Client ID/Secret from Integration)';
        } else if (preg_match('/redirect_uri/i', $raw)) {
            $hint = 'redirect_uri_mismatch';
        } else if (preg_match('/invalid_grant/i', $raw)) {
            $hint = 'invalid_grant (code expired — click Connect again once)';
        } else if (preg_match('/invalid_scope/i', $raw)) {
            $hint = 'invalid_scope';
        } else if (preg_match('/cannot_reach_webex/i', $raw)) {
            $hint = 'cannot_reach_webexapis.com (firewall)';
        } else if ($raw !== '') {
            // Short safe snippet for admins.
            $hint = \core_text::substr(preg_replace('/[^a-zA-Z0-9_:\-\. \(\)]+/', ' ', $raw), 0, 120);
        }
        redirect(
            $returnurl,
            get_string('oauth_error', 'local_iiidem_webexattendance', $hint),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
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
