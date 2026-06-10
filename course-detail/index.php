<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Course detail page (marketing layout) — curriculum, quizzes from course, etc.
 *
 * URL: /course-detail/?id=COURSEID
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

$id = required_param('id', PARAM_INT);

$course = get_course($id);
$coursecontext = context_course::instance($course->id);

if (!$course->visible) {
    throw new moodle_exception('coursehidden');
}

require_course_login($SITE);

$PAGE->set_context($coursecontext);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/course-detail/', ['id' => $course->id]));
$PAGE->set_pagelayout('marketing');
$PAGE->set_cacheable(false);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));

theme_iiidem2_render_course_detail_page($course);
