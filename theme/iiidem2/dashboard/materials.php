<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Teacher materials upload — File resources students can view in the course.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

require_login();

$userid = (int) $USER->id;
$courses = \theme_iiidem2\teacher_materials::get_teaching_courses($userid);
if (empty($courses)) {
    throw new \moodle_exception('teachermaterialsnocourses', 'theme_iiidem2');
}

$courseoptions = \theme_iiidem2\teacher_materials::course_options($courses);
$defaultcourseid = (int) optional_param('courseid', (int) reset($courses)->id, PARAM_INT);
if (!isset($courseoptions[$defaultcourseid])) {
    $defaultcourseid = (int) array_key_first($courseoptions);
}

$course = get_course($defaultcourseid);
$coursecontext = context_course::instance($course->id);
require_capability('moodle/course:manageactivities', $coursecontext);

$PAGE->set_url(new moodle_url('/theme/iiidem2/dashboard/materials.php', ['courseid' => $course->id]));
// System context keeps the Teaching Console chrome (no course "Home / Content bank" bar).
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_title(get_string('teachermaterialspagetitle', 'theme_iiidem2'));
$PAGE->set_heading('');
$PAGE->set_secondary_navigation(false);
$PAGE->activityheader->disable();
$PAGE->add_body_class('iiidem-teacher-dashboard-page');
$PAGE->requires->css(new moodle_url('/theme/iiidem2/style/student-dashboard.css'));
$PAGE->requires->css(new moodle_url('/theme/iiidem2/style/teacher-dashboard.css'));

$maxbytes = get_user_max_upload_file_size($coursecontext, $CFG->maxbytes, $course->maxbytes);
// Teacher materials: keep uploads small (~5 MB) for reliable production uploads.
$teachermaterialslimit = 5 * 1024 * 1024;
if ($maxbytes <= 0 || $maxbytes > $teachermaterialslimit) {
    $maxbytes = $teachermaterialslimit;
}
$sectionoptions = \theme_iiidem2\teacher_materials::section_options($course->id);

$form = new \theme_iiidem2\form\teacher_material_form(null, [
    'courseoptions' => $courseoptions,
    'sectionoptions' => $sectionoptions,
    'maxbytes' => $maxbytes,
]);

$form->set_data([
    'courseid' => $course->id,
    'section' => 0,
    'intro' => ['text' => '', 'format' => FORMAT_HTML],
]);

if ($form->is_cancelled()) {
    redirect(theme_iiidem2_get_dashboard_url());
}

if ($data = $form->get_data()) {
    $selectedcourse = get_course((int) $data->courseid);
    $selectedcontext = context_course::instance($selectedcourse->id);
    require_capability('moodle/course:manageactivities', $selectedcontext);

    $name = trim((string) $data->name);
    $intro = '';
    if (is_array($data->intro)) {
        $intro = trim((string) ($data->intro['text'] ?? ''));
    }
    $draftid = (int) $data->files;
    $section = (int) ($data->section ?? 0);

    try {
        $moduleinfo = \theme_iiidem2\teacher_materials::create_resource(
            $selectedcourse,
            $name,
            $intro,
            $draftid,
            $section
        );
        $viewurl = new moodle_url('/course/view.php', ['id' => $selectedcourse->id]);
        $viewurl->set_anchor('curriculum');
        redirect(
            $viewurl,
            get_string('teachermaterialssuccess', 'theme_iiidem2'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (\Throwable $e) {
        \theme_iiidem2\safe_errors::notify($e, 'teacher_materials_create', true);
    }
}

// Reload sections when course changes via GET.
if ($form->is_submitted() && !$form->is_validated()) {
    // Keep form errors; section list already for default course.
}

$materials = \theme_iiidem2\teacher_materials::list_recent_materials($courses, $userid, 25);

$templatecontext = [
    'dashboardurl' => theme_iiidem2_get_dashboard_url()->out(false),
    'formhtml' => $form->render(),
    'hasmaterials' => !empty($materials),
    'materials' => $materials,
    'courseviewurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false) . '#curriculum',
    'createassignmenturl' => (new moodle_url('/theme/iiidem2/dashboard/create_assignment.php'))->out(false),
    'sidenav' => \theme_iiidem2\teacher_dashboard::get_page_sidenav($userid, 'materials'),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('theme_iiidem2/dashboard/teacher_materials', $templatecontext);
echo html_writer::script("
(function() {
    var courseSelect = document.getElementById('id_courseid');
    if (!courseSelect) {
        return;
    }
    courseSelect.addEventListener('change', function() {
        var courseid = courseSelect.value;
        if (!courseid) {
            return;
        }
        var url = new URL(window.location.href);
        url.searchParams.set('courseid', courseid);
        window.location.href = url.toString();
    });
})();
");
echo $OUTPUT->footer();
