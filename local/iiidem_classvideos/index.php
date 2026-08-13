<?php
/**
 * Student: list class videos, request access, open watch.
 *
 * @package local_iiidem_classvideos
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/manager.php');

use local_iiidem_classvideos\manager;

require_login();

$userid = (int) $USER->id;
$videoid = optional_param('videoid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$note = optional_param('note', '', PARAM_TEXT);

$PAGE->set_url(new moodle_url('/local/iiidem_classvideos/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_title(get_string('studentvideos', 'local_iiidem_classvideos'));
$PAGE->set_heading(get_string('studentvideos', 'local_iiidem_classvideos'));
$PAGE->set_secondary_navigation(false);

if ($action === 'request' && $videoid && confirm_sesskey()) {
    manager::request_access($videoid, $userid, $note);
    redirect($PAGE->url, get_string('requestsent', 'local_iiidem_classvideos'));
}

$courses = manager::get_student_courses($userid);
$rows = [];
foreach ($courses as $course) {
    $ctx = context_course::instance($course->id);
    if (!has_capability('local/iiidem_classvideos:view', $ctx)) {
        continue;
    }
    $videos = manager::list_for_course((int) $course->id, true);
    foreach ($videos as $v) {
        $canwatch = manager::user_can_watch($v, $userid);
        $req = manager::get_request((int) $v->id, $userid);
        $status = '';
        $actionhtml = '';
        if ($canwatch) {
            $watch = new moodle_url('/local/iiidem_classvideos/watch.php', ['id' => $v->id]);
            $actionhtml = html_writer::link($watch, get_string('watch', 'local_iiidem_classvideos'), [
                'class' => 'btn btn-sm btn-primary',
            ]);
            $status = $v->accesstype === manager::ACCESS_PUBLIC
                ? manager::access_label(manager::ACCESS_PUBLIC)
                : manager::status_label(manager::STATUS_APPROVED);
        } else if ($v->accesstype === manager::ACCESS_REQUEST) {
            if ($req && $req->status === manager::STATUS_PENDING) {
                $status = manager::status_label(manager::STATUS_PENDING);
                $actionhtml = '—';
            } else if ($req && $req->status === manager::STATUS_REJECTED) {
                $status = manager::status_label(manager::STATUS_REJECTED);
                $requrl = new moodle_url('/local/iiidem_classvideos/index.php', [
                    'action' => 'request',
                    'videoid' => $v->id,
                    'sesskey' => sesskey(),
                ]);
                $actionhtml = html_writer::link($requrl, get_string('requestaccess', 'local_iiidem_classvideos'), [
                    'class' => 'btn btn-sm btn-outline-primary',
                ]);
            } else if (has_capability('local/iiidem_classvideos:request', $ctx)) {
                $status = manager::access_label(manager::ACCESS_REQUEST);
                $requrl = new moodle_url('/local/iiidem_classvideos/index.php', [
                    'action' => 'request',
                    'videoid' => $v->id,
                    'sesskey' => sesskey(),
                ]);
                $actionhtml = html_writer::link($requrl, get_string('requestaccess', 'local_iiidem_classvideos'), [
                    'class' => 'btn btn-sm btn-outline-primary',
                ]);
            }
        }

        $rows[] = [
            'course' => format_string($course->fullname, true, ['context' => $ctx]),
            'title' => format_string($v->title),
            'date' => $v->sessiondate ? userdate($v->sessiondate, get_string('strftimedate', 'langconfig')) : '—',
            'status' => $status,
            'action' => $actionhtml,
        ];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('studentvideos', 'local_iiidem_classvideos'));
echo html_writer::tag('p', get_string('studentvideos_desc', 'local_iiidem_classvideos'));

if (empty($rows)) {
    echo html_writer::div(get_string('novideosstudent', 'local_iiidem_classvideos'), 'alert alert-info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [
        get_string('course', 'local_iiidem_classvideos'),
        get_string('videotitle', 'local_iiidem_classvideos'),
        get_string('sessiondate', 'local_iiidem_classvideos'),
        get_string('accesstype', 'local_iiidem_classvideos'),
        get_string('actions', 'local_iiidem_classvideos'),
    ];
    foreach ($rows as $r) {
        $table->data[] = [$r['course'], $r['title'], $r['date'], $r['status'], $r['action']];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
