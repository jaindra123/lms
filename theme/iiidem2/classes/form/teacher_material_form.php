<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace theme_iiidem2\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Teacher upload form: create a File resource students can view.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_material_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $courseoptions = $this->_customdata['courseoptions'] ?? [];
        $sectionoptions = $this->_customdata['sectionoptions'] ?? [0 => get_string('general')];
        $maxbytes = (int) ($this->_customdata['maxbytes'] ?? 0);

        $mform->addElement('select', 'courseid', get_string('teachermaterialscourse', 'theme_iiidem2'), $courseoptions);
        $mform->addRule('courseid', get_string('required'), 'required', null, 'client');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('select', 'section', get_string('teachermaterialssection', 'theme_iiidem2'), $sectionoptions);
        $mform->setType('section', PARAM_INT);
        $mform->setDefault('section', 0);

        $mform->addElement('text', 'name', get_string('teachermaterialstitle', 'theme_iiidem2'),
            ['size' => 64, 'maxlength' => 255]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addRule('name', get_string('required'), 'required', null, 'server');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');
        $mform->addHelpButton('name', 'teachermaterialstitle', 'theme_iiidem2');

        $mform->addElement('editor', 'intro', get_string('teachermaterialsintro', 'theme_iiidem2'), [
            'rows' => 4,
        ], [
            'maxfiles' => 0,
            'noclean' => false,
        ]);
        $mform->setType('intro', PARAM_CLEANHTML);

        $fileoptions = [
            'subdirs' => 0,
            'maxfiles' => 1,
            'maxbytes' => $maxbytes,
            'accepted_types' => \theme_iiidem2\upload_security::MATERIAL_EXTENSIONS,
            'return_types' => FILE_INTERNAL,
        ];
        $mform->addElement(
            'filemanager',
            'files',
            get_string('teachermaterialsfile', 'theme_iiidem2'),
            null,
            $fileoptions
        );
        $mform->addRule('files', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('files', 'teachermaterialsfile', 'theme_iiidem2');
        if ($maxbytes > 0) {
            $mform->addElement('static', 'filesizelimit', '',
                get_string('teachermaterialsmaxsize', 'theme_iiidem2', display_size($maxbytes)));
        }

        $this->add_action_buttons(true, get_string('teachermaterialssubmit', 'theme_iiidem2'));
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $USER;

        $errors = parent::validation($data, $files);

        $courseid = (int) ($data['courseid'] ?? 0);
        $courseoptions = $this->_customdata['courseoptions'] ?? [];
        if ($courseid <= 0 || !isset($courseoptions[$courseid])) {
            $errors['courseid'] = get_string('required');
        }

        $draftid = (int) ($data['files'] ?? 0);
        if ($draftid <= 0) {
            $errors['files'] = get_string('required');
        } else {
            $reject = \theme_iiidem2\upload_security::validate_user_draft(
                (int) $USER->id,
                $draftid,
                \theme_iiidem2\upload_security::MATERIAL_EXTENSIONS
            );
            if ($reject === 'empty' || $reject === 'invalid') {
                $errors['files'] = get_string('required');
            } else if ($reject !== '') {
                $errors['files'] = get_string('teachermaterialsinvalidtype', 'theme_iiidem2', $reject);
            }
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = get_string('required');
        } else if (\core_text::strlen($name) > 255) {
            $errors['name'] = get_string('maximumchars', '', 255);
        }

        $introtext = trim((string) ($data['intro']['text'] ?? ''));
        if (\core_text::strlen(strip_tags($introtext)) > 10000) {
            $errors['intro'] = get_string('maximumchars', '', 10000);
        }

        return $errors;
    }
}
