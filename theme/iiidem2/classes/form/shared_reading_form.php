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
 * Logged-in student upload: file visible to others on the same workshop.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shared_reading_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $maxbytes = (int) ($this->_customdata['maxbytes'] ?? \theme_iiidem2\shared_readings::MAX_BYTES);
        $courseid = (int) ($this->_customdata['courseid'] ?? 0);

        $mform->disable_form_change_checker();

        $mform->addElement('hidden', 'id', $courseid);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'title', get_string('sharedreadingstitle', 'theme_iiidem2'), [
            'maxlength' => 255,
            'size' => 48,
        ]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('required'), 'required', null, 'client');
        $mform->addRule('title', get_string('required'), 'required', null, 'server');
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement(
            'filepicker',
            'sharedfile',
            get_string('sharedreadingsfile', 'theme_iiidem2'),
            null,
            [
                'maxbytes' => $maxbytes,
                'accepted_types' => \theme_iiidem2\upload_security::MATERIAL_EXTENSIONS,
            ]
        );
        $mform->addRule('sharedfile', get_string('required'), 'required', null, 'client');

        if ($maxbytes > 0) {
            $mform->addElement(
                'static',
                'sharedfilesizelimit',
                '',
                get_string('sharedreadingsmaxsize', 'theme_iiidem2', display_size($maxbytes))
            );
        }

        $this->add_action_buttons(false, get_string('sharedreadingsupload', 'theme_iiidem2'));
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $USER;

        $errors = parent::validation($data, $files);
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $errors['title'] = get_string('required');
        } else if (\core_text::strlen($title) > 255) {
            $errors['title'] = get_string('maximumchars', '', 255);
        }

        $draftid = (int) ($data['sharedfile'] ?? 0);
        if ($draftid <= 0) {
            $errors['sharedfile'] = get_string('required');
        } else {
            $reject = \theme_iiidem2\upload_security::validate_user_draft(
                (int) $USER->id,
                $draftid,
                \theme_iiidem2\upload_security::MATERIAL_EXTENSIONS
            );
            if ($reject === 'empty' || $reject === 'invalid') {
                $errors['sharedfile'] = get_string('required');
            } else if ($reject !== '') {
                $errors['sharedfile'] = get_string('teachermaterialsinvalidtype', 'theme_iiidem2', $reject);
            }
        }

        return $errors;
    }
}
