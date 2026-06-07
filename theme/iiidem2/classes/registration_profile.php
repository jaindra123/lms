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
        'iiidem_emb' => [
            'datatype' => 'checkbox',
            'name' => 'EMB',
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
            if ($DB->record_exists('user_info_field', ['shortname' => $shortname])) {
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
     * Whether a checkbox field was ticked.
     *
     * @param \stdClass $data
     * @param string $field
     * @return bool
     */
    public static function is_checked(\stdClass $data, string $field): bool {
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
     * @return string working|student|instructor|''
     */
    public static function get_occupation_type(\stdClass $data): string {
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
        $profile = (object) [
            'id' => $userid,
            'profile_field_iiidem_occupation' => $occupation,
            'profile_field_iiidem_emb' => self::is_checked($data, 'emb') ? '1' : '0',
            'profile_field_iiidem_organization' => self::get_submitted_value($data, 'organization'),
            'profile_field_iiidem_jobprofile' => self::get_submitted_value($data, 'jobprofile'),
            'profile_field_iiidem_jobpostingcountry' => self::get_submitted_value($data, 'jobpostingcountry'),
            'profile_field_iiidem_university' => self::get_submitted_value($data, 'university'),
            'profile_field_iiidem_position' => self::get_submitted_value($data, 'position'),
            'profile_field_iiidem_specialization' => self::get_submitted_value($data, 'specialization'),
            'profile_field_iiidem_instructor_university' => self::get_submitted_value($data, 'instructor_university'),
            'profile_field_iiidem_instructor_course' => self::get_submitted_value($data, 'instructor_course'),
            'profile_field_iiidem_presentcountry' => self::get_submitted_value($data, 'presentcountry'),
        ];

        profile_save_data($profile);
    }
}
