<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Role-based user dashboard (student / teacher / admin).
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_url(new moodle_url('/theme/iiidem2/dashboard/index.php'));
$PAGE->set_title(get_string('dashboard', 'theme_iiidem2'));
$PAGE->set_heading('');
$PAGE->activityheader->disable();

$templatecontext = theme_iiidem2_get_dashboard_context();
if (!empty($templatecontext['isstudent'])) {
    $PAGE->add_body_class('iiidem-student-dashboard-page');
}
if (!empty($templatecontext['isteacher'])) {
    $PAGE->add_body_class('iiidem-teacher-dashboard-page');
}
if (!empty($templatecontext['isstudent']) || !empty($templatecontext['isteacher']) || !empty($templatecontext['hascoursecards']) || !empty($templatecontext['isadmin'])) {
    $PAGE->requires->css(new moodle_url('/theme/iiidem2/style/student-dashboard.css'));
}
if (!empty($templatecontext['isteacher'])) {
    $PAGE->requires->css(new moodle_url('/theme/iiidem2/style/teacher-dashboard.css'));
}
if (!empty($templatecontext['hascalendarhtml'])) {
    $PAGE->add_body_class('iiidem-dashboard-has-calendar');
}
$templatecontext['output'] = $OUTPUT;

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_iiidem2/dashboard/main', $templatecontext);
echo $OUTPUT->footer();
