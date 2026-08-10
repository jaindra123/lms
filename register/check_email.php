<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * AJAX email checks for custom registration (duplicate + disposable domains).
 *
 * @package theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../config.php');

require_sesskey();

\theme_iiidem2\rate_limit::require_json('register_check_email_ip', 30, 60);

$email = core_text::strtolower(trim(required_param('email', PARAM_EMAIL)));
$exists = false;

// Always check duplicates first so registered users see "already registered"
// instead of disposable / MX messages.
if (empty($CFG->allowaccountssameemail) && validate_email($email)) {
    $exists = $DB->record_exists('user', [
        'email' => $email,
        'mnethostid' => $CFG->mnet_localhost_id,
        'deleted' => 0,
    ]);
}

if ($exists) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'exists' => true,
        'ok' => false,
        'reason' => 'exists',
        'message' => get_string('emailexists'),
        'toast' => get_string('emailexists'),
    ]);
    exit;
}

$quality = \theme_iiidem2\registration_email::validate($email);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'exists' => false,
    'ok' => $quality['ok'],
    'reason' => $quality['reason'],
    'message' => $quality['message'] ?? '',
    'toast' => $quality['toast'] ?? '',
]);
