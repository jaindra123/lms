<?php
require_once(__DIR__ . '/../../config.php');

use local_iiidem_coursecalendar\form\liveclass_form;
use local_iiidem_coursecalendar\google_calendar_client;
use local_iiidem_coursecalendar\manager;

$courseid = required_param('courseid', PARAM_INT);
$cancelid = optional_param('cancel', 0, PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/iiidem_coursecalendar:manage', $context);

$returnurl = new moodle_url('/local/iiidem_coursecalendar/manage.php', ['courseid' => $courseid]);
$PAGE->set_url(new moodle_url('/local/iiidem_coursecalendar/schedule_event.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('scheduleliveclass', 'local_iiidem_coursecalendar'));
$PAGE->set_heading(get_string('scheduleliveclassheading', 'local_iiidem_coursecalendar', format_string($course->fullname)));

if ($cancelid && confirm_sesskey() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $event = $DB->get_record('local_iiidem_coursecalendar_event', ['id' => $cancelid], 'id, courseid', MUST_EXIST);
    if ((int) $event->courseid !== $courseid) {
        throw new moodle_exception('invalidrecord', 'error');
    }
    manager::cancel_live_class($cancelid, $USER->id);
    redirect($returnurl, get_string('liveclasscancelled', 'local_iiidem_coursecalendar'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$form = new liveclass_form(null, ['courseid' => $courseid]);

if ($form->is_cancelled()) {
    redirect($returnurl);
}

if ($data = $form->get_data()) {
    try {
        // Ignore any client-tampered hidden courseid; URL param is capability-checked.
        $data->courseid = $courseid;
        $record = manager::create_live_class($courseid, $data, $USER->id);
        $a = (object) [
            'count' => (int) $record->attendeecount,
            'invites' => !empty($record->invites_sent)
                ? get_string('liveclassinvitessent', 'local_iiidem_coursecalendar')
                : get_string('liveclassinvitesnotsent', 'local_iiidem_coursecalendar'),
        ];
        redirect($returnurl, get_string('liveclassscheduled', 'local_iiidem_coursecalendar', $a), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (\Throwable $e) {
        \theme_iiidem2\safe_errors::notify($e, 'schedule_live_class', true);
    }
}

echo $OUTPUT->header();

if (!google_calendar_client::is_configured()) {
    echo $OUTPUT->notification(get_string('googleapinotconfiguredteacher', 'local_iiidem_coursecalendar'), 'notifywarning');
}

$attendees = manager::get_enrolled_student_emails($courseid, $USER->id);
echo html_writer::div(
    get_string('liveclassattendeepreview', 'local_iiidem_coursecalendar', count($attendees)),
    'alert alert-info mb-3'
);

$upcoming = manager::get_upcoming_live_classes($courseid, 10);
if (!empty($upcoming)) {
    echo html_writer::tag('h4', get_string('upcomingliveclasses', 'local_iiidem_coursecalendar'));
    echo html_writer::start_tag('ul', ['class' => 'local-iiidem-coursecalendar-liveclasses mb-4']);
    foreach ($upcoming as $event) {
        $line = userdate($event->starttime, get_string('strftimedatetimeshort', 'langconfig'))
            . ' — ' . s($event->summary)
            . ' (' . get_string('liveclassattendees', 'local_iiidem_coursecalendar', $event->attendeecount) . ')';
        $cancelform = html_writer::start_tag('form', [
            'method' => 'post',
            'action' => (new moodle_url('/local/iiidem_coursecalendar/schedule_event.php'))->out(false),
            'class' => 'd-inline',
        ]);
        $cancelform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
        $cancelform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cancel', 'value' => $event->id]);
        $cancelform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $cancelform .= html_writer::tag('button', get_string('cancel'), [
            'type' => 'submit',
            'class' => 'btn btn-link text-danger p-0 ms-2 align-baseline',
        ]);
        $cancelform .= html_writer::end_tag('form');
        $line .= ' ' . $cancelform;
        echo html_writer::tag('li', $line);
    }
    echo html_writer::end_tag('ul');
}

$form->display();

echo $OUTPUT->footer();
