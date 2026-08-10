<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * AJAX: verify registration email OTP.
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

\theme_iiidem2\rate_limit::require_json('register_verify_otp_ip', 20, 600);

$email = core_text::strtolower(trim(required_param('email', PARAM_EMAIL)));
$code = trim(required_param('code', PARAM_TEXT));
$code = clean_param($code, PARAM_ALPHANUM);
if ($code === '' || core_text::strlen($code) > 12) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'message' => get_string('registerotpinvalid', 'theme_iiidem2'),
    ]);
    exit;
}

$result = \theme_iiidem2\registration_otp::verify($email, $code);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
