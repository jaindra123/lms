<?php
require_once(__DIR__ . '/../../config.php');

use local_iiidem_coursecalendar\form\calendar_form;
use local_iiidem_coursecalendar\manager;

$courseid = required_param('courseid', PARAM_INT);
$delete = optional_param('delete', 0, PARAM_BOOL);

$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/iiidem_coursecalendar:manage', $context);

$PAGE->set_url(new moodle_url('/local/iiidem_coursecalendar/manage.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('manage', 'local_iiidem_coursecalendar'));
$PAGE->set_heading(get_string('manageheading', 'local_iiidem_coursecalendar', format_string($course->fullname)));

$returnurl = new moodle_url('/course/view.php', ['id' => $courseid]);
$existing = manager::get_by_course($courseid);

if ($delete && confirm_sesskey()) {
    manager::delete_calendar($courseid);
    redirect($returnurl, get_string('deleted', 'local_iiidem_coursecalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$form = new calendar_form(null, [
    'courseid' => $courseid,
    'currenturl' => $existing ? $existing->calendarurl : '',
]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    manager::save_calendar($courseid, $data->calendarurl, $USER->id);
    redirect($returnurl, get_string('saved', 'local_iiidem_coursecalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();

$scheduleurl = new moodle_url('/local/iiidem_coursecalendar/schedule_event.php', ['courseid' => $courseid]);
echo html_writer::div(
    html_writer::link($scheduleurl, get_string('scheduleliveclass', 'local_iiidem_coursecalendar'), ['class' => 'btn btn-primary mb-3'])
    . ' '
    . html_writer::tag('span', get_string('scheduleliveclasshelp', 'local_iiidem_coursecalendar'), ['class' => 'text-muted']),
    'local-iiidem-coursecalendar-manage-actions'
);

if ($existing) {
    $deleteurl = new moodle_url('/local/iiidem_coursecalendar/manage.php', [
        'courseid' => $courseid,
        'delete' => 1,
        'sesskey' => sesskey(),
    ]);
    echo html_writer::div(
        html_writer::link(
            $deleteurl,
            get_string('deletecalendar', 'local_iiidem_coursecalendar'),
            ['class' => 'btn btn-outline-danger mb-3']
        ),
        'local-iiidem-coursecalendar-manage-actions'
    );

    $events = manager::get_upcoming_events($existing, 5);
    if (!empty($events)) {
        echo html_writer::tag('h4', get_string('scheduleheading', 'local_iiidem_coursecalendar'));
        echo html_writer::start_tag('ul', ['class' => 'local-iiidem-coursecalendar-preview']);
        foreach ($events as $event) {
            $line = manager::format_event_datetime($event);
            if ($event['summary'] !== '') {
                $line .= ' — ' . s($event['summary']);
            }
            echo html_writer::tag('li', $line);
        }
        echo html_writer::end_tag('ul');
    }
}

$form->display();

echo $OUTPUT->footer();
