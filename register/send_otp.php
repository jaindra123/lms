<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * AJAX: send / resend registration email OTP.
 *
 * @package theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

require_sesskey();

$PAGE->set_context(context_system::instance());

// Server-side rate limits (IP + email) — session cooldown alone is bypassable.
\theme_iiidem2\rate_limit::require_json('register_send_otp_ip', 3, 900);
\theme_iiidem2\rate_limit::require_json('register_send_otp_ip_hour', 10, 3600);

$email = \theme_iiidem2\input_validation::request_email('email');
if ($email === null) {
    \theme_iiidem2\input_validation::json_exit([
        'ok' => false,
        'reason' => 'invalid',
        'message' => get_string('invalidemail'),
        'toast' => get_string_manager()->string_exists('registeremailtoast', 'theme_iiidem2')
            ? get_string('registeremailtoast', 'theme_iiidem2')
            : get_string('invalidemail'),
    ]);
}
$firstname = trim(optional_param('firstname', '', PARAM_TEXT));
$firstname = \theme_iiidem2\input_validation::clean_text($firstname, 100, true) ?? '';
if (\theme_iiidem2\input_validation::contains_dangerous_markup(
        (string) optional_param('firstname', '', PARAM_RAW))) {
    $firstname = '';
}
\theme_iiidem2\rate_limit::require_json(
    'register_send_otp_email',
    1,
    30,
    \theme_iiidem2\rate_limit::email_identity($email),
    ['message' => get_string('registerotpwait', 'theme_iiidem2', 30), 'cooldown' => 30]
);

if (empty($CFG->allowaccountssameemail) && validate_email($email)
        && $DB->record_exists('user', [
            'email' => $email,
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0,
        ])) {
    \theme_iiidem2\input_validation::json_exit([
        'ok' => false,
        'reason' => 'exists',
        'message' => get_string('emailexists'),
        'toast' => get_string('emailexists'),
    ]);
}

$quality = \theme_iiidem2\registration_email::validate($email);
if (!$quality['ok']) {
    \theme_iiidem2\input_validation::json_exit([
        'ok' => false,
        'reason' => $quality['reason'] ?? 'invalid',
        'message' => $quality['message'],
        'toast' => $quality['toast'] ?? 'Please check the email',
    ]);
}

$result = \theme_iiidem2\registration_otp::send($email, $firstname);
\theme_iiidem2\input_validation::json_exit($result);
