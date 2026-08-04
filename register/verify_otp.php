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

$email = core_text::strtolower(trim(required_param('email', PARAM_EMAIL)));
$code = trim(required_param('code', PARAM_TEXT));

$result = \theme_iiidem2\registration_otp::verify($email, $code);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);
