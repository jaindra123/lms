<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Theme override of core course_summary_exporter for weekend-based progress on My courses.
 *
 * Loaded via autoload hook in theme_iiidem2\hook_listener::after_config().
 * Based on core_course\external\course_summary_exporter — keep in sync on Moodle upgrades.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_course\external;

defined('MOODLE_INTERNAL') || die();

use renderer_base;
use moodle_url;

class course_summary_exporter extends \core\external\exporter {

    public function __construct($data, $related = array()) {
        if (!array_key_exists('isfavourite', $related)) {
            $related['isfavourite'] = false;
        }
        parent::__construct($data, $related);
    }

    protected static function define_related() {
        return array('context' => '\\context', 'isfavourite' => 'bool?');
    }

    protected function get_other_values(renderer_base $output) {
        global $CFG;
        $courseimage = self::get_course_image($this->data);
        if (!$courseimage) {
            $courseimage = $output->get_generated_image_for_id($this->data->id);
        }
        $progress = self::get_course_progress($this->data);
        $hasprogress = false;
        if ($progress === 0 || $progress > 0) {
            $hasprogress = true;
        }
        $progress = floor($progress ?? 0);
        $coursecategory = \core_course_category::get($this->data->category, MUST_EXIST, true);
        return array(
            'fullnamedisplay' => get_course_display_name_for_list($this->data),
            'viewurl' => (new moodle_url('/course/view.php', array('id' => $this->data->id)))->out(false),
            'courseimage' => $courseimage,
            'progress' => $progress,
            'hasprogress' => $hasprogress,
            'isfavourite' => $this->related['isfavourite'],
            'hidden' => boolval(get_user_preferences('block_myoverview_hidden_course_' . $this->data->id, 0)),
            'showshortname' => $CFG->courselistshortnames ? true : false,
            'coursecategory' => $coursecategory->name
        );
    }

    public static function define_properties() {
        return array(
            'id' => array(
                'type' => PARAM_INT,
            ),
            'fullname' => array(
                'type' => PARAM_TEXT,
            ),
            'shortname' => array(
                'type' => PARAM_TEXT,
            ),
            'idnumber' => array(
                'type' => PARAM_RAW,
            ),
            'summary' => array(
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null,
            ),
            'summaryformat' => array(
                'type' => PARAM_INT,
                'default' => FORMAT_MOODLE,
            ),
            'startdate' => array(
                'type' => PARAM_INT,
            ),
            'enddate' => array(
                'type' => PARAM_INT,
            ),
            'visible' => array(
                'type' => PARAM_BOOL,
            ),
            'showactivitydates' => [
                'type' => PARAM_BOOL,
                'null' => NULL_ALLOWED
            ],
            'showcompletionconditions' => [
                'type' => PARAM_BOOL,
                'null' => NULL_ALLOWED
            ],
            'pdfexportfont' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null,
            ],
        );
    }

    protected function get_format_parameters_for_summary() {
        return [
            'component' => 'course',
            'filearea' => 'summary',
        ];
    }

    public static function define_other_properties() {
        return array(
            'fullnamedisplay' => array(
                'type' => PARAM_TEXT,
            ),
            'viewurl' => array(
                'type' => PARAM_URL,
            ),
            'courseimage' => array(
                'type' => PARAM_RAW,
            ),
            'progress' => array(
                'type' => PARAM_INT,
                'optional' => true
            ),
            'hasprogress' => array(
                'type' => PARAM_BOOL
            ),
            'isfavourite' => array(
                'type' => PARAM_BOOL
            ),
            'hidden' => array(
                'type' => PARAM_BOOL
            ),
            'timeaccess' => array(
                'type' => PARAM_INT,
                'optional' => true
            ),
            'showshortname' => array(
                'type' => PARAM_BOOL
            ),
            'coursecategory' => array(
                'type' => PARAM_TEXT
            )
        );
    }

    public static function get_course_image($course) {
        $image = \cache::make('core', 'course_image')->get($course->id);

        if (is_null($image)) {
            $image = false;
        }

        return $image;
    }

    public static function get_course_pattern($course) {
        global $OUTPUT;
        debugging('course_summary_exporter::get_course_pattern() is deprecated. ' .
            'Please use $OUTPUT->get_generated_image_for_id() instead.', DEBUG_DEVELOPER);
        return $OUTPUT->get_generated_image_for_id($course->id);
    }

    /**
     * Weekend-slot progress when available; otherwise Moodle activity completion.
     *
     * @param object $course
     * @return float|null
     */
    public static function get_course_progress($course) {
        global $CFG;

        require_once($CFG->dirroot . '/theme/iiidem2/lib.php');
        $override = theme_iiidem2_get_course_list_progress_percentage($course);
        if ($override !== null) {
            return $override;
        }

        return \core_completion\progress::get_course_progress_percentage($course);
    }

    public static function coursecolor($courseid) {
        global $OUTPUT;
        debugging('course_summary_exporter::coursecolor() is deprecated. ' .
            'Please use $OUTPUT->get_generated_color_for_id() instead.', DEBUG_DEVELOPER);
        return $OUTPUT->get_generated_color_for_id($courseid);
    }
}
