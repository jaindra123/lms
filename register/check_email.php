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



$email = \theme_iiidem2\input_validation::request_email('email');

if ($email === null) {

    // Localized message only — never echo the submitted value (CDAC register XSS).

    \theme_iiidem2\input_validation::json_exit([

        'exists' => false,

        'ok' => false,

        'reason' => 'invalid',

        'message' => get_string('invalidemail'),

        'toast' => get_string_manager()->string_exists('registeremailtoast', 'theme_iiidem2')

            ? get_string('registeremailtoast', 'theme_iiidem2')

            : get_string('invalidemail'),

    ]);

}



$exists = false;



// Always check duplicates first so registered users see "already registered"

// instead of disposable / MX messages.

if (empty($CFG->allowaccountssameemail)) {

    $exists = $DB->record_exists('user', [

        'email' => $email,

        'mnethostid' => $CFG->mnet_localhost_id,

        'deleted' => 0,

    ]);

}



if ($exists) {

    \theme_iiidem2\input_validation::json_exit([

        'exists' => true,

        'ok' => false,

        'reason' => 'exists',

        'message' => get_string('emailexists'),

        'toast' => get_string('emailexists'),

    ]);

}



$quality = \theme_iiidem2\registration_email::validate($email);



\theme_iiidem2\input_validation::json_exit([

    'exists' => false,

    'ok' => $quality['ok'],

    'reason' => $quality['reason'],

    'message' => $quality['message'] ?? '',

    'toast' => $quality['toast'] ?? '',

]);


