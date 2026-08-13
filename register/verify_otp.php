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

$email = \theme_iiidem2\input_validation::request_email('email');
if ($email === null) {
    \theme_iiidem2\input_validation::json_exit([
        'ok' => false,
        'message' => get_string('invalidemail'),
    ]);
}
$code = trim(optional_param('code', '', PARAM_ALPHANUM));
if ($code === '' || core_text::strlen($code) > 12) {
    \theme_iiidem2\input_validation::json_exit([
        'ok' => false,
        'message' => get_string('registerotpinvalid', 'theme_iiidem2'),
    ]);
}

$result = \theme_iiidem2\registration_otp::verify($email, $code);
\theme_iiidem2\input_validation::json_exit($result);
