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
 * IIIDEM custom registration form.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class register_form extends \moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        global $CFG;

        $mform = $this->_form;

        $mform->addElement('text', 'firstname', get_string('registerfirstname', 'theme_iiidem2'));
        $mform->setType('firstname', PARAM_TEXT);
        $mform->addRule('firstname', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'middlename', get_string('registermiddlename', 'theme_iiidem2'));
        $mform->setType('middlename', PARAM_TEXT);

        $mform->addElement('text', 'lastname', get_string('registerlastname', 'theme_iiidem2'));
        $mform->setType('lastname', PARAM_TEXT);
        $mform->addRule('lastname', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'email', get_string('email'));
        $mform->setType('email', \core_user::get_property_type('email'));
        $mform->addRule('email', get_string('required'), 'required', null, 'client');
        $mform->setForceLtr('email');

        $mform->addElement('text', 'phone1', get_string('registercontact', 'theme_iiidem2'));
        $mform->setType('phone1', \core_user::get_property_type('phone1'));
        $mform->addRule('phone1', get_string('required'), 'required', null, 'client');

        $countries = get_string_manager()->get_list_of_countries();
        $countryoptions = ['' => get_string('selectacountry')] + $countries;
        $mform->addElement('select', 'country', get_string('country'), $countryoptions);
        $mform->addRule('country', get_string('required'), 'required', null, 'client');
        if (!empty($CFG->country)) {
            $mform->setDefault('country', $CFG->country);
        }

        $mform->addElement('text', 'city', get_string('city'));
        $mform->setType('city', \core_user::get_property_type('city'));
        $mform->addRule('city', get_string('required'), 'required', null, 'client');

        $mform->addElement('header', 'occupationheader', get_string('registeroccupation', 'theme_iiidem2'));

        $mform->addElement('advcheckbox', 'occupation_working', '', get_string('registeroccupationworking', 'theme_iiidem2'));
        $mform->setType('occupation_working', PARAM_INT);

        $mform->addElement('advcheckbox', 'occupation_student', '', get_string('registeroccupationstudent', 'theme_iiidem2'));
        $mform->setType('occupation_student', PARAM_INT);

        $mform->addElement('advcheckbox', 'occupation_instructor', '', get_string('registeroccupationinstructor', 'theme_iiidem2'));
        $mform->setType('occupation_instructor', PARAM_INT);

        $mform->addElement('header', 'workingheader', get_string('registerworkingprofile', 'theme_iiidem2'));

        $mform->addElement('advcheckbox', 'emb', '', get_string('registeremb', 'theme_iiidem2'));
        $mform->setType('emb', PARAM_INT);

        $mform->addElement('text', 'organization', get_string('registerorganization', 'theme_iiidem2'));
        $mform->setType('organization', PARAM_TEXT);

        $mform->addElement('text', 'jobprofile', get_string('registerjobprofile', 'theme_iiidem2'));
        $mform->setType('jobprofile', PARAM_TEXT);

        $mform->addElement('text', 'jobpostingcountry', get_string('registerjobpostingcountry', 'theme_iiidem2'));
        $mform->setType('jobpostingcountry', PARAM_TEXT);

        $mform->addElement('header', 'studentheader', get_string('registerstudentprofile', 'theme_iiidem2'));

        $mform->addElement('text', 'university', get_string('registeruniversity', 'theme_iiidem2'));
        $mform->setType('university', PARAM_TEXT);

        $mform->addElement('text', 'position', get_string('registerposition', 'theme_iiidem2'));
        $mform->setType('position', PARAM_TEXT);

        $mform->addElement('text', 'specialization', get_string('registerspecialization', 'theme_iiidem2'));
        $mform->setType('specialization', PARAM_TEXT);

        $mform->addElement('header', 'instructorheader', get_string('registerinstructorprofile', 'theme_iiidem2'));

        $mform->addElement('text', 'instructor_university', get_string('registeruniversity', 'theme_iiidem2'));
        $mform->setType('instructor_university', PARAM_TEXT);

        $mform->addElement('text', 'instructor_course', get_string('registercourse', 'theme_iiidem2'));
        $mform->setType('instructor_course', PARAM_TEXT);

        $mform->addElement('text', 'presentcountry', get_string('registerpresentcountry', 'theme_iiidem2'));
        $mform->setType('presentcountry', PARAM_TEXT);

        $workingfields = ['workingheader', 'emb', 'organization', 'jobprofile', 'jobpostingcountry'];
        foreach ($workingfields as $field) {
            $mform->hideIf($field, 'occupation_working', 'notchecked');
        }

        $studentfields = ['studentheader', 'university', 'position', 'specialization'];
        foreach ($studentfields as $field) {
            $mform->hideIf($field, 'occupation_student', 'notchecked');
        }

        $instructorfields = ['instructorheader', 'instructor_university', 'instructor_course', 'presentcountry'];
        foreach ($instructorfields as $field) {
            $mform->hideIf($field, 'occupation_instructor', 'notchecked');
        }

        foreach (['workingheader', 'studentheader', 'instructorheader'] as $header) {
            $mform->setExpanded($header, true);
        }

        if (!empty($CFG->passwordpolicy)) {
            $mform->addElement('static', 'passwordpolicyinfo', '', print_password_policy());
        }

        $mform->addElement('password', 'password', get_string('password'), [
            'maxlength' => MAX_PASSWORD_CHARACTERS,
            'autocomplete' => 'new-password',
        ]);
        $mform->setType('password', \core_user::get_property_type('password'));
        $mform->addRule('password', get_string('required'), 'required', null, 'client');

        $mform->addElement('password', 'password2', get_string('passwordagain'), [
            'maxlength' => MAX_PASSWORD_CHARACTERS,
            'autocomplete' => 'new-password',
        ]);
        $mform->setType('password2', \core_user::get_property_type('password'));
        $mform->addRule('password2', get_string('required'), 'required', null, 'client');

        $this->add_action_buttons(true, get_string('registercreateaccount', 'theme_iiidem2'));
    }

    /**
     * Trim text fields.
     */
    public function definition_after_data() {
        foreach ([
            'firstname', 'middlename', 'lastname', 'email', 'phone1', 'city',
            'organization', 'jobprofile', 'jobpostingcountry', 'university', 'position',
            'specialization', 'instructor_university', 'instructor_course', 'presentcountry',
        ] as $field) {
            $this->_form->applyFilter($field, 'trim');
        }
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $CFG, $DB;

        $errors = parent::validation($data, $files);

        if (!validate_email($data['email'])) {
            $errors['email'] = get_string('invalidemail');
        } else if (empty($CFG->allowaccountssameemail)) {
            if ($DB->record_exists('user', ['email' => $data['email'], 'mnethostid' => $CFG->mnet_localhost_id])) {
                $errors['email'] = get_string('emailexists');
            }
        }

        if ($data['password'] !== $data['password2']) {
            $errors['password2'] = get_string('passwordsdiffer');
        } else if (!check_password_policy($data['password'], $errmsg)) {
            $errors['password'] = $errmsg;
        }

        $formdata = (object) $data;
        $occupation = \theme_iiidem2\registration_profile::get_occupation_type($formdata);
        if ($occupation === '') {
            $errors['occupation_working'] = get_string('registeroccupationrequired', 'theme_iiidem2');
        } else if ($occupation === 'working') {
            foreach (['organization', 'jobprofile', 'jobpostingcountry'] as $field) {
                if (trim(\theme_iiidem2\registration_profile::get_submitted_value($formdata, $field)) === '') {
                    $errors[$field] = get_string('required');
                }
            }
        } else if ($occupation === 'student') {
            foreach (['university', 'position', 'specialization'] as $field) {
                if (trim(\theme_iiidem2\registration_profile::get_submitted_value($formdata, $field)) === '') {
                    $errors[$field] = get_string('required');
                }
            }
        } else if ($occupation === 'instructor') {
            foreach (['instructor_university', 'instructor_course', 'presentcountry'] as $field) {
                if (trim(\theme_iiidem2\registration_profile::get_submitted_value($formdata, $field)) === '') {
                    $errors[$field] = get_string('required');
                }
            }
        }

        return $errors;
    }
}
