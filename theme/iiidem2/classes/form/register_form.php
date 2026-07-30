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

        $mform->addElement('text', 'email', get_string('email'), [
            'data-email-check-url' => (new \moodle_url('/register/check_email.php'))->out(false),
            'data-email-exists-message' => get_string('emailexists'),
        ]);
        $mform->setType('email', \core_user::get_property_type('email'));
        $mform->addRule('email', get_string('required'), 'required', null, 'client');
        $mform->setForceLtr('email');

        $mform->addElement('text', 'phone1', get_string('registercontact', 'theme_iiidem2'), [
            'maxlength' => 20,
            'autocomplete' => 'tel',
            'inputmode' => 'tel',
            'data-invalid-phone' => get_string('registerphoneinvalid', 'theme_iiidem2'),
            'placeholder' => get_string('registerphoneplaceholder', 'theme_iiidem2'),
        ]);
        $mform->setType('phone1', PARAM_TEXT);
        $mform->addRule('phone1', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('phone1', 'registercontact', 'theme_iiidem2');
        $mform->setForceLtr('phone1');

        $countries = get_string_manager()->get_list_of_countries();
        $countryoptions = ['' => get_string('selectacountry')] + $countries;
        $mform->addElement('select', 'country', get_string('country'), $countryoptions);
        $mform->addRule('country', get_string('required'), 'required', null, 'client');
        if (!empty($CFG->country)) {
            $mform->setDefault('country', $CFG->country);
        } else {
            $mform->setDefault('country', 'IN');
        }

        $mform->addElement('text', 'city', get_string('city'));
        $mform->setType('city', \core_user::get_property_type('city'));
        $mform->addRule('city', get_string('required'), 'required', null, 'client');

        $mform->addElement('header', 'occupationheader', get_string('registeroccupation', 'theme_iiidem2'));

        // Radios: only one occupation can be selected (no JS required).
        $mform->addElement('radio', 'occupation', '', get_string('registeroccupationworking', 'theme_iiidem2'), 'working');
        $mform->addElement('radio', 'occupation', '', get_string('registeroccupationworkingemb', 'theme_iiidem2'), 'workingemb');
        $mform->addElement('radio', 'occupation', '', get_string('registeroccupationstudent', 'theme_iiidem2'), 'student');
        $mform->addElement('radio', 'occupation', '', get_string('registeroccupationinstructor', 'theme_iiidem2'), 'instructor');
        $mform->setType('occupation', PARAM_ALPHA);
        $mform->addRule('occupation', get_string('registeroccupationrequired', 'theme_iiidem2'), 'required', null, 'client');

        $mform->addElement('header', 'workingheader', get_string('registerworkingprofile', 'theme_iiidem2'));

        // Radios: only one working category can be selected.
        $mform->addElement('radio', 'workingcategory', '', get_string('registerpolicymaker', 'theme_iiidem2'), 'policymaker');
        $mform->addElement('radio', 'workingcategory', '', get_string('registerjournalist', 'theme_iiidem2'), 'journalist');
        $mform->addElement('radio', 'workingcategory', '', get_string('registerresearcher', 'theme_iiidem2'), 'researcher');
        $mform->setType('workingcategory', PARAM_ALPHA);

        $mform->addElement('text', 'organization', get_string('registerorganization', 'theme_iiidem2'));
        $mform->setType('organization', PARAM_TEXT);

        $mform->addElement('text', 'jobprofile', get_string('registerjobprofile', 'theme_iiidem2'));
        $mform->setType('jobprofile', PARAM_TEXT);

        $mform->addElement('text', 'jobpostingcountry', get_string('registerjobpostingcountry', 'theme_iiidem2'));
        $mform->setType('jobpostingcountry', PARAM_TEXT);

        $mform->addElement('header', 'workingembheader', get_string('registerworkingembprofile', 'theme_iiidem2'));

        $mform->addElement('text', 'emb_organization', get_string('registerorganisation', 'theme_iiidem2'));
        $mform->setType('emb_organization', PARAM_TEXT);

        $mform->addElement('text', 'emb_designation', get_string('registerdesignation', 'theme_iiidem2'));
        $mform->setType('emb_designation', PARAM_TEXT);

        $mform->addElement('text', 'emb_country', get_string('registerembcountry', 'theme_iiidem2'));
        $mform->setType('emb_country', PARAM_TEXT);

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

        $workingfields = [
            'workingheader',
            'workingcategory',
            'organization',
            'jobprofile',
            'jobpostingcountry',
        ];
        foreach ($workingfields as $field) {
            $mform->hideIf($field, 'occupation', 'neq', 'working');
        }

        $workingembfields = [
            'workingembheader',
            'emb_organization',
            'emb_designation',
            'emb_country',
        ];
        foreach ($workingembfields as $field) {
            $mform->hideIf($field, 'occupation', 'neq', 'workingemb');
        }

        $studentfields = ['studentheader', 'university', 'position', 'specialization'];
        foreach ($studentfields as $field) {
            $mform->hideIf($field, 'occupation', 'neq', 'student');
        }

        $instructorfields = ['instructorheader', 'instructor_university', 'instructor_course', 'presentcountry'];
        foreach ($instructorfields as $field) {
            $mform->hideIf($field, 'occupation', 'neq', 'instructor');
        }

        // Collapsed until the matching occupation option is selected.
        foreach (['workingheader', 'workingembheader', 'studentheader', 'instructorheader'] as $header) {
            $mform->setExpanded($header, false);
        }

        // Own section so password fields are not nested under Instructor / Student / Working.
        $mform->addElement('header', 'passwordheader', get_string('registerpasswordheader', 'theme_iiidem2'));
        $mform->setExpanded('passwordheader', true);

        if (!empty($CFG->passwordpolicy)) {
            $mform->addElement(
                'static',
                'passwordpolicyinfo',
                get_string('registerpasswordshouldbe', 'theme_iiidem2'),
                print_password_policy()
            );
        }

        $mform->addElement('password', 'password', get_string('password'), [
            'maxlength' => MAX_PASSWORD_CHARACTERS,
            'autocomplete' => 'new-password',
        ]);
        $mform->setType('password', \core_user::get_property_type('password'));
        $mform->addRule('password', get_string('required'), 'required', null, 'client');

        $mform->addElement('password', 'password2', get_string('password') . ' (' . get_string('again') . ')', [
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
            'organization', 'jobprofile', 'jobpostingcountry',
            'emb_organization', 'emb_designation', 'emb_country',
            'university', 'position', 'specialization',
            'instructor_university', 'instructor_course', 'presentcountry',
        ] as $field) {
            $this->_form->applyFilter($field, 'trim');
        }
    }

    /**
     * Normalise contact number to E.164 when possible.
     *
     * @param string $phone
     * @param string $countryiso2
     * @return string
     */
    public static function normalize_phone(string $phone, string $countryiso2 = ''): string {
        $phone = preg_replace('/[\s\-()]/', '', $phone);
        $countryiso2 = strtoupper(trim($countryiso2));

        if ($phone === '') {
            return '';
        }

        if (!str_starts_with($phone, '+')) {
            $dialcodes = [
                'IN' => '91', 'US' => '1', 'GB' => '44', 'AE' => '971', 'SG' => '65',
                'AU' => '61', 'CA' => '1', 'DE' => '49', 'FR' => '33', 'NP' => '977',
                'BD' => '880', 'LK' => '94', 'PK' => '92', 'ZA' => '27',
            ];
            if ($countryiso2 === 'IN' && preg_match('/^0[6-9]\d{9}$/', $phone)) {
                $phone = substr($phone, 1);
            }
            if (isset($dialcodes[$countryiso2]) && preg_match('/^\d{6,14}$/', $phone)) {
                $phone = '+' . $dialcodes[$countryiso2] . $phone;
            }
        }

        return $phone;
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

        $phone = self::normalize_phone((string) ($data['phone1'] ?? ''), (string) ($data['country'] ?? ''));
        if ($phone === '') {
            $errors['phone1'] = get_string('required');
        } else if (!preg_match('/^\+[1-9]\d{6,14}$/', $phone)) {
            $errors['phone1'] = get_string('registerphoneinvalid', 'theme_iiidem2');
        }

        if ($data['password'] !== $data['password2']) {
            $errors['password2'] = get_string('passwordsdiffer');
        } else if (!check_password_policy($data['password'], $errmsg)) {
            $errors['password'] = $errmsg;
        }

        $formdata = (object) $data;
        $occupation = \theme_iiidem2\registration_profile::get_occupation_type($formdata);
        if ($occupation === '') {
            $errors['occupation'] = get_string('registeroccupationrequired', 'theme_iiidem2');
        } else if ($occupation === 'working') {
            if (!\theme_iiidem2\registration_profile::has_working_category((object) $data)) {
                $errors['workingcategory'] = get_string('registerworkingcategoryrequired', 'theme_iiidem2');
            }
            foreach (['organization', 'jobprofile', 'jobpostingcountry'] as $field) {
                if (trim(\theme_iiidem2\registration_profile::get_submitted_value($formdata, $field)) === '') {
                    $errors[$field] = get_string('required');
                }
            }
        } else if ($occupation === 'workingemb') {
            foreach (['emb_organization', 'emb_designation', 'emb_country'] as $field) {
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
