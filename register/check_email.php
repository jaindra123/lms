<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * AJAX duplicate-email check for the custom registration form.
 *
 * Server-side form validation remains authoritative; this endpoint only
 * provides immediate feedback before the form is submitted.
 *
 * @package theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../config.php');

require_sesskey();

$email = core_text::strtolower(trim(required_param('email', PARAM_EMAIL)));
$exists = false;

if (validate_email($email) && empty($CFG->allowaccountssameemail)) {
    $exists = $DB->record_exists('user', [
        'email' => $email,
        'mnethostid' => $CFG->mnet_localhost_id,
    ]);
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['exists' => $exists]);
