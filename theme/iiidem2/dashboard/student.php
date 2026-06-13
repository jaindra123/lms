<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Teacher view — enrolled student profile and progress.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$studentid = required_param('studentid', PARAM_INT);

$course = get_course($courseid);
require_login($course, false);

$context = context_course::instance($courseid);
require_capability('moodle/course:viewparticipants', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/theme/iiidem2/dashboard/student.php', [
    'courseid' => $courseid,
    'studentid' => $studentid,
]));
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_secondary_navigation(false);
$PAGE->set_course($course);

\theme_iiidem2\teacher_students::require_teacher_student_access($course, $studentid, $USER->id);

$detail = \theme_iiidem2\teacher_students::get_student_detail_context($courseid, $studentid, $USER->id);

$PAGE->set_title(get_string('dashboardteacherstudentdetailtitle', 'theme_iiidem2', $detail['studentname']));
$PAGE->set_heading('');
$PAGE->activityheader->disable();
$PAGE->add_body_class('iiidem-teacher-dashboard-page');
$PAGE->requires->css(new moodle_url('/theme/iiidem2/style/teacher-dashboard.css'));

$detail['output'] = $OUTPUT;

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_iiidem2/dashboard/teacher_student_detail', $detail);
echo $OUTPUT->footer();
