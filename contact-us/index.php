<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Public Contact Us page (clean URL: /contact-us/).
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_course($SITE);
$PAGE->set_url(new moodle_url('/contact-us/'));
$PAGE->set_pagelayout('frontpage');
$PAGE->set_cacheable(false);
$PAGE->set_title(get_string('contactus', 'theme_iiidem2'));
$PAGE->set_heading(get_string('contactus', 'theme_iiidem2'));

$form = new \theme_iiidem2\form\contact_form(null, [
    'action' => new moodle_url('/contact-us/'),
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/'));
}

if ($data = $form->get_data()) {
    if (theme_iiidem2_send_contact_message($data)) {
        redirect(
            new moodle_url('/contact-us/', ['sent' => 1]),
            get_string('contactusformsent', 'theme_iiidem2'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    \core\notification::error(get_string('contactusformerror', 'theme_iiidem2'));
}

ob_start();
$form->display();
$formhtml = ob_get_clean();

theme_iiidem2_render_public_page('theme_iiidem2/pages/contact-us', array_merge(
    theme_iiidem2_get_contact_page_context(),
    [
        'pagetitle' => get_string('contactus', 'theme_iiidem2'),
        'pagesubtitle' => get_string('contactus_lead', 'theme_iiidem2'),
        'formhtml' => $formhtml,
        'formsent' => optional_param('sent', 0, PARAM_INT) === 1,
    ]
), 'pagelayout-marketing', false);
