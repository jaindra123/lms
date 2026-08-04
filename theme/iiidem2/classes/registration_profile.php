<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Registration occupation data stored in Moodle custom profile fields.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class registration_profile {

    /** @var string */
    public const CATEGORY = 'IIIDEM registration';

    /** @var array<string, array<string, mixed>> */
    private const FIELDS = [
        'iiidem_occupation' => [
            'datatype' => 'menu',
            'name' => 'Occupation',
            'param1' => "working\nstudent\ninstructor",
        ],
        'iiidem_reg_status' => [
            'datatype' => 'menu',
            'name' => 'Registration approval',
            'param1' => "pending\napproved\nrejected",
        ],
        'iiidem_emb' => [
            'datatype' => 'checkbox',
            'name' => 'Allow without payment course',
        ],
        'iiidem_policymaker' => [
            'datatype' => 'checkbox',
            'name' => 'Policymaker',
        ],
        'iiidem_journalist' => [
            'datatype' => 'checkbox',
            'name' => 'Journalist',
        ],
        'iiidem_electoral_practitioner' => [
            'datatype' => 'checkbox',
            'name' => 'Electoral practitioner',
        ],
        'iiidem_researcher' => [
            'datatype' => 'checkbox',
            'name' => 'Researcher',
        ],
        'iiidem_organization' => [
            'datatype' => 'text',
            'name' => 'Organization',
            'param1' => 30,
            'param2' => 255,
        ],
        'iiidem_jobprofile' => [
            'datatype' => 'text',
            'name' => 'Job profile',
            'param1' => 30,
            'param2' => 255,
        ],
        'iiidem_jobpostingcountry' => [
            'datatype' => 'text',
            'name' => 'Job posting country',
            'param1' => 30,
            'param2' => 255,
        ],
        'iiidem_university' => [
            'datatype' => 'text',
            'name' => 'University (student)',
            'param1' => 30,
            'param2' => 255,
        ],
        'iiidem_position' => [
            'datatype' => 'text',
            'name' => 'Position (student)',
            'param1' => 30,
            'param2' => 255,
        ],
        'iiidem_specialization' => [
            'datatype' => 'text',
            'name' => 'Specialization (student)',
            'param1' => 30,
            'param2' => 255,
        ],
        'iiidem_instructor_university' => [
            'datatype' => 'text',
            'name' => 'University (instructor)',
            'param1' => 30,
            'param2' => 255,
        ],
        'iiidem_instructor_course' => [
            'datatype' => 'text',
            'name' => 'Course (instructor)',
            'param1' => 30,
            'param2' => 255,
        ],
        'iiidem_presentcountry' => [
            'datatype' => 'text',
            'name' => 'Present country (instructor)',
            'param1' => 30,
            'param2' => 255,
        ],
    ];

    /**
     * Read a custom profile field value for a user.
     *
     * @param int $userid
     * @param string $shortname
     * @return string
     */
    public static function get_profile_value(int $userid, string $shortname): string {
        global $DB;

        $field = $DB->get_record('user_info_field', ['shortname' => $shortname], 'id', IGNORE_MISSING);
        if (!$field) {
            return '';
        }

        $data = $DB->get_record('user_info_data', ['fieldid' => $field->id, 'userid' => $userid], 'data', IGNORE_MISSING);
        return $data ? (string) $data->data : '';
    }

    /**
     * Job profile label for display (custom field, then user department fallback).
     *
     * @param int $userid
     * @param \stdClass|null $user Optional user record with department.
     * @return string
     */
    public static function get_job_profile_display(int $userid, ?\stdClass $user = null): string {
        global $DB;

        $value = trim(self::get_profile_value($userid, 'iiidem_jobprofile'));
        if ($value !== '') {
            return $value;
        }

        if ($user !== null && !empty($user->department)) {
            return trim((string) $user->department);
        }

        $department = $DB->get_field('user', 'department', ['id' => $userid]);
        return $department ? trim((string) $department) : '';
    }

    /**
     * Working professional category options (form field => profile shortname).
     * Form uses a single radio group `workingcategory` with these values.
     *
     * @return array<string, string>
     */
    public static function working_category_fields(): array {
        return [
            'emb' => 'iiidem_emb',
            'policymaker' => 'iiidem_policymaker',
            'journalist' => 'iiidem_journalist',
            'researcher' => 'iiidem_researcher',
        ];
    }

    /**
     * Selected working category from radio (or legacy checkboxes).
     *
     * @param \stdClass $data
     * @return string emb|policymaker|journalist|researcher|''
     */
    public static function get_working_category(\stdClass $data): string {
        $allowed = array_keys(self::working_category_fields());

        $value = strtolower(trim(self::get_submitted_value($data, 'workingcategory')));
        if (in_array($value, $allowed, true)) {
            return $value;
        }

        // Legacy checkbox fallback (older form submissions).
        foreach ($allowed as $field) {
            if (self::is_checked_raw($data, $field)) {
                return $field;
            }
        }
        return '';
    }

    /**
     * Whether the submitted working profile selected a category.
     *
     * @param \stdClass $data
     * @return bool
     */
    public static function has_working_category(\stdClass $data): bool {
        return self::get_working_category($data) !== '';
    }

    /**
     * Whether the user is fee-exempt via "Allow without payment course"
     * (profile field iiidem_emb, set by admin or legacy EMB registration).
     *
     * @param int $userid
     * @return bool
     */
    public static function user_is_emb(int $userid): bool {
        return self::get_profile_value($userid, 'iiidem_emb') === '1';
    }

    /**
     * Alias: admin marked the user as allowed without course fee payment.
     *
     * @param int $userid
     * @return bool
     */
    public static function user_allowed_without_course_payment(int $userid): bool {
        return self::user_is_emb($userid);
    }

    /**
     * User registered with occupation "University student".
     *
     * @param int $userid
     * @return bool
     */
    public static function user_is_university_student(int $userid): bool {
        return self::get_profile_value($userid, 'iiidem_occupation') === 'student';
    }

    /**
     * Whether the user must pay the course fee.
     *
     * Students and working professionals (Policymaker / Journalist /
     * Researcher) pay, unless an administrator has checked
     * "Allow without payment course". Instructors remain exempt.
     *
     * @param int $userid
     * @return bool
     */
    public static function user_requires_course_fee_payment(int $userid): bool {
        $occupation = self::get_profile_value($userid, 'iiidem_occupation');
        $ispayingoccupation = $occupation === 'student' || $occupation === 'working';

        return $ispayingoccupation && !self::user_allowed_without_course_payment($userid);
    }

    /**
     * Ensure profile category and fields exist (idempotent).
     */
    public static function ensure_fields(): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/user/profile/lib.php');
        require_once($CFG->dirroot . '/user/profile/definelib.php');

        $category = $DB->get_record('user_info_category', ['name' => self::CATEGORY]);
        if (!$category) {
            $category = (object) [
                'name' => self::CATEGORY,
                'sortorder' => $DB->count_records('user_info_category') + 1,
            ];
            $category->id = $DB->insert_record('user_info_category', $category);
        }

        foreach (self::FIELDS as $shortname => $config) {
            $existing = $DB->get_record('user_info_field', ['shortname' => $shortname], 'id, name');
            if ($existing) {
                // Keep the visible label in sync (e.g. Allow without payment course).
                if (!empty($config['name']) && $existing->name !== $config['name']) {
                    $DB->set_field('user_info_field', 'name', $config['name'], ['id' => $existing->id]);
                }
                continue;
            }

            $defineclass = '\\profile_define_' . $config['datatype'];
            require_once($CFG->dirroot . '/user/profile/field/' . $config['datatype'] . '/define.class.php');

            $data = (object) array_merge([
                'shortname' => $shortname,
                'name' => $config['name'],
                'datatype' => $config['datatype'],
                'description' => '',
                'descriptionformat' => FORMAT_HTML,
                'categoryid' => $category->id,
                'sortorder' => ((int) $DB->get_field_sql(
                    'SELECT MAX(sortorder) FROM {user_info_field} WHERE categoryid = ?',
                    [$category->id]
                )) + 1,
                'required' => 0,
                'locked' => 0,
                'forceunique' => 0,
                'signup' => 0,
                'visible' => \PROFILE_VISIBLE_ALL,
                'defaultdata' => '',
                'defaultdataformat' => FORMAT_HTML,
            ], $config);

            (new $defineclass())->define_save($data);
        }

        // Keep working-category checkboxes grouped after Occupation / EMB.
        self::reorder_fields((int) $category->id);
    }

    /**
     * Keep IIIDEM registration fields in a stable display order.
     *
     * @param int $categoryid
     */
    public static function reorder_fields(int $categoryid = 0): void {
        global $DB;

        $desired = [
            'iiidem_occupation',
            'iiidem_reg_status',
            'iiidem_emb',
            'iiidem_policymaker',
            'iiidem_journalist',
            'iiidem_electoral_practitioner',
            'iiidem_researcher',
            'iiidem_organization',
            'iiidem_jobprofile',
            'iiidem_jobpostingcountry',
            'iiidem_university',
            'iiidem_position',
            'iiidem_specialization',
            'iiidem_instructor_university',
            'iiidem_instructor_course',
            'iiidem_presentcountry',
        ];

        if ($categoryid <= 0) {
            $categoryid = (int) $DB->get_field('user_info_category', 'id', ['name' => self::CATEGORY]);
        }
        if ($categoryid <= 0) {
            return;
        }

        $sort = 1;
        foreach ($desired as $shortname) {
            $field = $DB->get_record('user_info_field', [
                'shortname' => $shortname,
                'categoryid' => $categoryid,
            ], 'id');
            if (!$field) {
                continue;
            }
            $DB->set_field('user_info_field', 'sortorder', $sort, ['id' => $field->id]);
            $sort++;
        }
    }

    /**
     * Read a submitted registration value from form data or POST.
     *
     * @param \stdClass $data
     * @param string $field
     * @return string
     */
    public static function get_submitted_value(\stdClass $data, string $field): string {
        if (isset($_POST[$field]) && $_POST[$field] !== '') {
            $value = $_POST[$field];
            if (is_array($value)) {
                $value = end($value);
            }
            return (string) $value;
        }
        if (isset($data->{$field}) && $data->{$field} !== '' && $data->{$field} !== null) {
            return (string) $data->{$field};
        }
        return '';
    }

    /**
     * Whether a checkbox/category field was selected.
     *
     * Working categories use a radio group (`workingcategory`); legacy per-field
     * checkboxes are still accepted.
     *
     * @param \stdClass $data
     * @param string $field
     * @return bool
     */
    public static function is_checked(\stdClass $data, string $field): bool {
        $categories = array_keys(self::working_category_fields());
        if (in_array($field, $categories, true)) {
            $selected = self::get_working_category($data);
            if ($selected !== '') {
                return $selected === $field;
            }
        }
        return self::is_checked_raw($data, $field);
    }

    /**
     * Whether a checkbox field was ticked in POST/form data (no radio mapping).
     *
     * @param \stdClass $data
     * @param string $field
     * @return bool
     */
    private static function is_checked_raw(\stdClass $data, string $field): bool {
        if (isset($_POST[$field])) {
            $value = $_POST[$field];
            if (is_array($value)) {
                $value = end($value);
            }
            if ((string) $value === '1') {
                return true;
            }
        }
        if (isset($data->{$field})) {
            return (string) $data->{$field} === '1' || (int) $data->{$field} === 1;
        }
        return false;
    }

    /**
     * Resolve selected occupation from form data.
     *
     * @param \stdClass $data
     * @return string working|workingemb|student|instructor|''
     */
    public static function get_occupation_type(\stdClass $data): string {
        $allowed = ['working', 'workingemb', 'student', 'instructor'];

        $value = '';
        if (isset($_POST['occupation'])) {
            $raw = $_POST['occupation'];
            $value = is_array($raw) ? (string) end($raw) : (string) $raw;
        } else if (isset($data->occupation)) {
            $value = (string) $data->occupation;
        }

        $value = strtolower(trim($value));
        if (in_array($value, $allowed, true)) {
            return $value;
        }

        // Legacy checkbox fallback (older form submissions).
        if (self::is_checked($data, 'occupation_working')) {
            return 'working';
        }
        if (self::is_checked($data, 'occupation_student')) {
            return 'student';
        }
        if (self::is_checked($data, 'occupation_instructor')) {
            return 'instructor';
        }
        return '';
    }

    /**
     * Save occupation details for a new user.
     *
     * @param int $userid
     * @param \stdClass $data
     */
    public static function save_user_data(int $userid, \stdClass $data): void {
        global $CFG;

        require_once($CFG->dirroot . '/user/profile/lib.php');

        self::ensure_fields();

        $occupation = self::get_occupation_type($data);
        // Reuse the existing "working" menu value and identify EMB users through
        // the dedicated profile flag, avoiding a profile-field schema migration.
        $storedoccupation = $occupation === 'workingemb' ? 'working' : $occupation;
        $isemb = $occupation === 'workingemb' || self::is_checked($data, 'emb');

        $organization = self::get_submitted_value($data, 'organization');
        $jobprofile = self::get_submitted_value($data, 'jobprofile');
        $jobcountry = self::get_submitted_value($data, 'jobpostingcountry');
        if ($occupation === 'workingemb') {
            $organization = self::get_submitted_value($data, 'emb_organization');
            $jobprofile = self::get_submitted_value($data, 'emb_designation');
            $jobcountry = self::get_submitted_value($data, 'emb_country');
        }

        $profile = (object) [
            'id' => $userid,
            'profile_field_iiidem_occupation' => $storedoccupation,
            'profile_field_iiidem_reg_status' => 'pending',
            'profile_field_iiidem_emb' => $isemb ? '1' : '0',
            'profile_field_iiidem_policymaker' => self::is_checked($data, 'policymaker') ? '1' : '0',
            'profile_field_iiidem_journalist' => self::is_checked($data, 'journalist') ? '1' : '0',
            'profile_field_iiidem_electoral_practitioner' => self::is_checked($data, 'electoralpractitioner') ? '1' : '0',
            'profile_field_iiidem_researcher' => self::is_checked($data, 'researcher') ? '1' : '0',
            'profile_field_iiidem_organization' => $organization,
            'profile_field_iiidem_jobprofile' => $jobprofile,
            'profile_field_iiidem_jobpostingcountry' => $jobcountry,
            'profile_field_iiidem_university' => self::get_submitted_value($data, 'university'),
            'profile_field_iiidem_position' => self::get_submitted_value($data, 'position'),
            'profile_field_iiidem_specialization' => self::get_submitted_value($data, 'specialization'),
            'profile_field_iiidem_instructor_university' => self::get_submitted_value($data, 'instructor_university'),
            'profile_field_iiidem_instructor_course' => self::get_submitted_value($data, 'instructor_course'),
            'profile_field_iiidem_presentcountry' => self::get_submitted_value($data, 'presentcountry'),
        ];

        profile_save_data($profile);
    }

    /**
     * Set a custom profile field value for a user.
     *
     * @param int $userid
     * @param string $shortname
     * @param string $value
     */
    public static function set_profile_value(int $userid, string $shortname, string $value): void {
        global $CFG;

        require_once($CFG->dirroot . '/user/profile/lib.php');
        self::ensure_fields();
        $profile = (object) [
            'id' => $userid,
            'profile_field_' . $shortname => $value,
        ];
        profile_save_data($profile);
    }
}
