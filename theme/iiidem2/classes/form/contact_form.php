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

        // pattern rejects < > " ' so XSS probes fail HTML5 validation (no green tick).
        $plainattrs = [
            'maxlength' => 100,
            'pattern' => '[^<>\"\']+',
            'title' => get_string('err_xss', 'theme_iiidem2'),
            'autocomplete' => 'name',
        ];
        $mform->addElement('text', 'name', get_string('name'), $plainattrs);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addRule('name', get_string('required'), 'required', null, 'server');
        $mform->addRule('name', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 100), 'maxlength', 100, 'server');

        $mform->addElement('text', 'email', get_string('email'));
        $mform->setType('email', \core_user::get_property_type('email'));
        $mform->addRule('email', get_string('required'), 'required', null, 'client');
        $mform->addRule('email', get_string('required'), 'required', null, 'server');
        $mform->setForceLtr('email');

        $mform->addElement('text', 'subject', get_string('subject'), [
            'maxlength' => 255,
            'pattern' => '[^<>\"\']+',
            'title' => get_string('err_xss', 'theme_iiidem2'),
        ]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('required'), 'required', null, 'client');
        $mform->addRule('subject', get_string('required'), 'required', null, 'server');
        $mform->addRule('subject', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addRule('subject', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');

        $mform->addElement(
            'textarea',
            'message',
            get_string('message'),
            'rows="6" cols="60" maxlength="5000" data-iiidem-no-markup="1"'
        );
        $mform->setType('message', PARAM_TEXT);
        $mform->addRule('message', get_string('required'), 'required', null, 'client');
        $mform->addRule('message', get_string('required'), 'required', null, 'server');

        $this->add_action_buttons(false, get_string('contactussubmit', 'theme_iiidem2'));

        if (isloggedin() && !isguestuser()) {
            $mform->setDefault('name', fullname($USER));
            $mform->setDefault('email', $USER->email);
        }
    }

    /**
     * Server-side validation — checks raw $_POST (formslib PARAM_TEXT strips tags).
     *
     * @param array $data
     * @param array $files
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['email']) && !validate_email($data['email'])) {
            $errors['email'] = get_string('invalidemail');
        }

        // Raw POST: PARAM_TEXT would turn <script>x</script> into "x" and look “valid”.
        foreach (['name' => 100, 'subject' => 255, 'message' => 5000] as $field => $max) {
            $raw = isset($_POST[$field]) && is_string($_POST[$field]) ? trim($_POST[$field]) : '';
            $cleaned = trim((string) ($data[$field] ?? ''));

            if ($raw === '' && $cleaned === '') {
                $errors[$field] = get_string('required');
                continue;
            }

            if ($raw !== '' && \theme_iiidem2\input_validation::contains_dangerous_markup($raw)) {
                $errors[$field] = get_string('err_xss', 'theme_iiidem2');
                continue;
            }
            if (str_contains($raw, '<') || str_contains($raw, '>')) {
                $errors[$field] = get_string('err_xss', 'theme_iiidem2');
                continue;
            }

            $value = $raw !== '' ? $raw : $cleaned;
            if (\core_text::strlen($value) > $max) {
                $errors[$field] = get_string('maximumchars', '', $max);
                continue;
            }

            if ($field === 'name') {
                if (!\theme_iiidem2\input_validation::is_safe_person_name($value)) {
                    $errors[$field] = get_string('err_xss', 'theme_iiidem2');
                }
                continue;
            }

            if (!\theme_iiidem2\input_validation::is_safe_plain_line($value, $max)
                    || !\theme_iiidem2\input_validation::has_alnum_content($value)) {
                // Reject punctuation-only subjects (@#$$$) and markup.
                $errors[$field] = get_string('err_xss', 'theme_iiidem2');
            }
        }

        return $errors;
    }
}
