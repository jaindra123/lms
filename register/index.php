<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Custom registration page (clean URL: /register/).
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

global $SESSION;
if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/'));
}

$PAGE->set_context(context_system::instance());
$PAGE->set_course($SITE);
$PAGE->set_url(new moodle_url('/register/'));
$PAGE->set_pagelayout('register');
$PAGE->set_cacheable(false);
$PAGE->set_title(get_string('registerpagetitle', 'theme_iiidem2'));
$PAGE->set_heading(get_string('registerpagetitle', 'theme_iiidem2'));
$PAGE->requires->css('/theme/iiidem2/style/intl-tel-input/intlTelInput.min.css');
$PAGE->requires->js(new moodle_url('/theme/iiidem2/javascript/intl-tel-input/intlTelInput.min.js'), true);
$PAGE->requires->js_call_amd('theme_iiidem2/register_occupation', 'init');

$form = new \theme_iiidem2\form\register_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/login/index.php'));
}

if ($data = $form->get_data()) {
    try {
        $submission = (object) array_merge((array) $_POST, (array) $data);
        $userid = theme_iiidem2_create_registered_user($submission);
        $user = core_user::get_user($userid);
        theme_iiidem2_send_registration_emails($user, $submission);
        complete_user_login($user);
        $redirecturl = new moodle_url('/course/view.php', ['id' => 4, 'registered' => 1]);
        $SESSION->wantsurl = $redirecturl->out(false);
        redirect($redirecturl);
    } catch (moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
}

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
