<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * AJAX duplicate-phone check for the custom registration form.
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
require_once($CFG->dirroot . '/theme/iiidem2/classes/form/register_form.php');

require_sesskey();

$phone = trim(required_param('phone', PARAM_TEXT));
$country = optional_param('country', 'IN', PARAM_ALPHA);

$valid = false;
$exists = false;
$message = '';

$national = \theme_iiidem2\form\register_form::national_phone_digits($phone);
if ($national === '' || !\theme_iiidem2\form\register_form::is_valid_national_phone($national)) {
    $message = get_string('registerphoneinvalid', 'theme_iiidem2');
} else {
    $valid = true;
    $normalized = \theme_iiidem2\form\register_form::normalize_phone($national, $country);
    $exists = \theme_iiidem2\form\register_form::phone_exists($normalized, $national);
    if ($exists) {
        $message = get_string('registerphoneexists', 'theme_iiidem2');
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'valid' => $valid,
    'exists' => $exists,
    'message' => $message,
]);
