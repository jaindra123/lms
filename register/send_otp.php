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

$email = core_text::strtolower(trim(required_param('email', PARAM_EMAIL)));
$firstname = trim(optional_param('firstname', '', PARAM_TEXT));

if (empty($CFG->allowaccountssameemail) && validate_email($email)
        && $DB->record_exists('user', [
            'email' => $email,
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0,
        ])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'reason' => 'exists',
        'message' => get_string('emailexists'),
        'toast' => get_string('emailexists'),
    ]);
    exit;
}

$quality = \theme_iiidem2\registration_email::validate($email);
if (!$quality['ok']) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'reason' => $quality['reason'] ?? 'invalid',
        'message' => $quality['message'],
        'toast' => $quality['toast'] ?? 'Please check the email',
    ]);
    exit;
}

$result = \theme_iiidem2\registration_otp::send($email, $firstname);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
