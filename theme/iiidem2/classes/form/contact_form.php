<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace theme_iiidem2\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * IIIDEM public contact form.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contact_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        global $USER;

        $mform = $this->_form;

        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'email', get_string('email'));
        $mform->setType('email', \core_user::get_property_type('email'));
        $mform->addRule('email', get_string('required'), 'required', null, 'client');
        $mform->setForceLtr('email');

        $mform->addElement('text', 'subject', get_string('subject'));
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('required'), 'required', null, 'client');

        $mform->addElement('textarea', 'message', get_string('message'));
        $mform->setType('message', PARAM_RAW);
        $mform->addRule('message', get_string('required'), 'required', null, 'client');

        $this->add_action_buttons(false, get_string('contactussubmit', 'theme_iiidem2'));

        if (isloggedin() && !isguestuser()) {
            $mform->setDefault('name', fullname($USER));
            $mform->setDefault('email', $USER->email);
        }
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['email']) && !validate_email($data['email'])) {
            $errors['email'] = get_string('invalidemail');
        }

        return $errors;
    }
}
