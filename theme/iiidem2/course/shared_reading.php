<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Upload / delete / toggle shared reading materials for a course.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

$courseid = required_param('id', PARAM_INT);
$deleteid = optional_param('delete', 0, PARAM_INT);
$enable = optional_param('enable', -1, PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($course->id);
\theme_iiidem2\shared_readings::require_login_for_access($course);
require_sesskey();

$PAGE->set_url(new moodle_url('/theme/iiidem2/course/shared_reading.php', ['id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('sharedreadingsheading', 'theme_iiidem2'));
$PAGE->set_heading(format_string($course->fullname));

$returnurl = new moodle_url('/course/view.php', ['id' => $course->id]);
$returnurl->set_anchor('shared-readings');

if ($enable === 0 || $enable === 1) {
    require_capability('moodle/course:update', $context);
    \theme_iiidem2\shared_readings::set_enabled($courseid, $enable === 1);
    redirect(
        $returnurl,
        $enable === 1
            ? get_string('sharedreadingsenabledok', 'theme_iiidem2')
            : get_string('sharedreadingsdisabledok', 'theme_iiidem2'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($deleteid > 0) {
    \theme_iiidem2\shared_readings::delete($deleteid, (int) $USER->id);
    redirect(
        $returnurl,
        get_string('sharedreadingsdeleted', 'theme_iiidem2'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$form = new \theme_iiidem2\form\shared_reading_form(null, [
    'courseid' => $courseid,
    'maxbytes' => \theme_iiidem2\shared_readings::max_bytes($context, $course),
]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    if (!\theme_iiidem2\shared_readings::is_enabled($courseid)
            || !\theme_iiidem2\shared_readings::can_access($course)) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('sharedreadingsupload', 'theme_iiidem2'));
    }
    $draftid = (int) ($data->sharedfile ?? 0);
    \theme_iiidem2\shared_readings::create($course, (string) $data->title, $draftid);
    redirect(
        $returnurl,
        get_string('sharedreadingsuploaded', 'theme_iiidem2'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

if ($form->is_submitted() && !$form->is_cancelled()) {
    if (!\theme_iiidem2\shared_readings::can_access($course)) {
        throw new moodle_exception('nopermissions', 'error', '', get_string('sharedreadingsupload', 'theme_iiidem2'));
    }
    echo $OUTPUT->header();
    echo $form->render();
    echo $OUTPUT->footer();
    exit;
}

redirect($returnurl);
