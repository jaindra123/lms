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
 * Teacher form to create an Assignment without course Edit mode.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_assignment_form extends \moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        $courseoptions = $this->_customdata['courseoptions'] ?? [];
        $sectionoptions = $this->_customdata['sectionoptions'] ?? [0 => get_string('general')];

        $mform->addElement('select', 'courseid', get_string('teacherassignmentcourse', 'theme_iiidem2'), $courseoptions);
        $mform->addRule('courseid', get_string('required'), 'required', null, 'client');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('select', 'section', get_string('teacherassignmentsection', 'theme_iiidem2'), $sectionoptions);
        $mform->setType('section', PARAM_INT);
        $mform->setDefault('section', 0);

        $mform->addElement('text', 'name', get_string('teacherassignmentname', 'theme_iiidem2'),
            ['size' => 64, 'maxlength' => 255]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addRule('name', get_string('required'), 'required', null, 'server');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');
        $mform->addHelpButton('name', 'teacherassignmentname', 'theme_iiidem2');

        $mform->addElement('editor', 'intro', get_string('teacherassignmentintro', 'theme_iiidem2'), [
            'rows' => 5,
        ], [
            'maxfiles' => 0,
            'noclean' => false,
        ]);
        $mform->setType('intro', PARAM_CLEANHTML);

        $mform->addElement('date_time_selector', 'allowsubmissionsfromdate',
            get_string('teacherassignmentallowfrom', 'theme_iiidem2'), ['optional' => true]);
        $mform->setDefault('allowsubmissionsfromdate', 0);

        $mform->addElement('date_time_selector', 'duedate',
            get_string('teacherassignmentduedate', 'theme_iiidem2'), ['optional' => true]);
        $mform->setDefault('duedate', 0);

        $mform->addElement('text', 'grade', get_string('teacherassignmentgrade', 'theme_iiidem2'), ['size' => 6]);
        $mform->setType('grade', PARAM_INT);
        $mform->setDefault('grade', 100);
        $mform->addRule('grade', null, 'numeric', null, 'client');

        $mform->addElement('text', 'maxfiles', get_string('teacherassignmentmaxfiles', 'theme_iiidem2'), ['size' => 4]);
        $mform->setType('maxfiles', PARAM_INT);
        $mform->setDefault('maxfiles', 5);

        $mform->addElement('advcheckbox', 'allowonlinetext', get_string('teacherassignmentonlinetext', 'theme_iiidem2'));
        $mform->setDefault('allowonlinetext', 0);

        $this->add_action_buttons(true, get_string('teacherassignmentsubmit', 'theme_iiidem2'));
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $courseid = (int) ($data['courseid'] ?? 0);
        $courseoptions = $this->_customdata['courseoptions'] ?? [];
        if ($courseid <= 0 || !isset($courseoptions[$courseid])) {
            $errors['courseid'] = get_string('required');
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

        $grade = (int) ($data['grade'] ?? 0);
        if ($grade < 0 || $grade > 1000) {
            $errors['grade'] = get_string('teacherassignmentgradeinvalid', 'theme_iiidem2');
        }
        $maxfiles = (int) ($data['maxfiles'] ?? 0);
        if ($maxfiles < 1 || $maxfiles > 20) {
            $errors['maxfiles'] = get_string('teacherassignmentmaxfilesinvalid', 'theme_iiidem2');
        }
        $allowfrom = (int) ($data['allowsubmissionsfromdate'] ?? 0);
        $duedate = (int) ($data['duedate'] ?? 0);
        if ($allowfrom > 0 && $duedate > 0 && $duedate < $allowfrom) {
            $errors['duedate'] = get_string('teacherassignmentduebeforeallow', 'theme_iiidem2');
        }

        return $errors;
    }
}
