<?php
/**
 * Teacher / admin: list videos, upload, approve requests.
 *
 * @package local_iiidem_classvideos
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/manager.php');
require_once(__DIR__ . '/classes/form/video_form.php');

use local_iiidem_classvideos\manager;
use local_iiidem_classvideos\form\video_form;

require_login();

$userid = (int) $USER->id;
$courses = manager::get_manageable_courses($userid);
if (empty($courses) && !is_siteadmin()) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('managevideos', 'local_iiidem_classvideos'));
}

$courseoptions = [];
foreach ($courses as $c) {
    $courseoptions[(int) $c->id] = format_string($c->fullname, true, [
        'context' => context_course::instance($c->id),
    ]);
}
if (empty($courseoptions) && is_siteadmin()) {
    $all = get_courses('id, fullname, visible');
    foreach ($all as $c) {
        if ((int) $c->id === SITEID) {
            continue;
        }
        $courseoptions[(int) $c->id] = format_string($c->fullname);
    }
}

$defaultcourseid = (int) optional_param('courseid', 0, PARAM_INT);
if (!$defaultcourseid || !isset($courseoptions[$defaultcourseid])) {
    $defaultcourseid = (int) array_key_first($courseoptions);
}

$action = optional_param('action', '', PARAM_ALPHA);
$videoid = optional_param('videoid', 0, PARAM_INT);
$reqid = optional_param('reqid', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/iiidem_classvideos/manage.php', ['courseid' => $defaultcourseid]));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_title(get_string('managevideos', 'local_iiidem_classvideos'));
$PAGE->set_heading('');
$PAGE->set_secondary_navigation(false);
$PAGE->activityheader->disable();

// Professors chrome only for real teachers — site admins keep the normal admin page.
$useteachershell = false;
if (file_exists($CFG->dirroot . '/theme/iiidem2/lib.php')) {
    require_once($CFG->dirroot . '/theme/iiidem2/lib.php');
    $useteachershell = theme_iiidem2_get_user_dashboard_role($userid) === 'teacher';
}
if ($useteachershell && file_exists($CFG->dirroot . '/theme/iiidem2/style/teacher-dashboard.css')) {
    $PAGE->requires->css(new moodle_url('/theme/iiidem2/style/teacher-dashboard.css'));
    $PAGE->requires->css(new moodle_url('/theme/iiidem2/style/student-dashboard.css'));
    $PAGE->add_body_class('iiidem-teacher-dashboard-page');
}

if ($action && $reqid && confirm_sesskey()) {
    if ($action === 'approve') {
        manager::resolve_request($reqid, manager::STATUS_APPROVED, $userid);
    } else if ($action === 'reject') {
        manager::resolve_request($reqid, manager::STATUS_REJECTED, $userid);
    }
    redirect($PAGE->url, get_string('requestresolved', 'local_iiidem_classvideos'));
}

if (($action === 'hide' || $action === 'show') && $videoid && confirm_sesskey()) {
    manager::set_visible($videoid, $action === 'show', $userid);
    redirect($PAGE->url, get_string('visibilityupdated', 'local_iiidem_classvideos'));
}

$maxbytes = get_max_upload_file_size($CFG->maxbytes);
if ($maxbytes <= 0 || $maxbytes > manager::MAX_UPLOAD_BYTES) {
    $maxbytes = manager::MAX_UPLOAD_BYTES;
}

$form = new video_form(null, [
    'courseoptions' => $courseoptions,
    'maxbytes' => $maxbytes,
]);
$form->set_data([
    'courseid' => $defaultcourseid,
    'accesstype' => manager::ACCESS_REQUEST,
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/my/'));
}

if ($data = $form->get_data()) {
    $sessiondate = 0;
    if (!empty($data->sessiondate)) {
        $sessiondate = is_numeric($data->sessiondate)
            ? (int) $data->sessiondate
            : (int) $data->sessiondate;
    }
    manager::create_video(
        (int) $data->courseid,
        (string) $data->title,
        (string) ($data->description ?? ''),
        (string) $data->accesstype,
        (string) ($data->externalurl ?? ''),
        $sessiondate,
        (int) ($data->videofile ?? 0),
        $userid
    );
    redirect(
        new moodle_url('/local/iiidem_classvideos/manage.php', ['courseid' => (int) $data->courseid]),
        get_string('videosaved', 'local_iiidem_classvideos')
    );
}

$pending = manager::list_pending_requests_for_manager($userid);
$videos = $defaultcourseid ? manager::list_for_course($defaultcourseid, false) : [];

$coursenames = $courseoptions;

ob_start();
if (empty($pending)) {
    echo html_writer::div(get_string('nopending', 'local_iiidem_classvideos'), 'alert alert-info');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [
        get_string('videotitle', 'local_iiidem_classvideos'),
        get_string('course', 'local_iiidem_classvideos'),
        get_string('student', 'local_iiidem_classvideos'),
        get_string('requestedon', 'local_iiidem_classvideos'),
        get_string('actions', 'local_iiidem_classvideos'),
    ];
    foreach ($pending as $row) {
        $approve = new moodle_url('/local/iiidem_classvideos/manage.php', [
            'action' => 'approve',
            'reqid' => $row->id,
            'sesskey' => sesskey(),
            'courseid' => $defaultcourseid,
        ]);
        $reject = new moodle_url('/local/iiidem_classvideos/manage.php', [
            'action' => 'reject',
            'reqid' => $row->id,
            'sesskey' => sesskey(),
            'courseid' => $defaultcourseid,
        ]);
        $cname = $coursenames[(int) $row->courseid] ?? (string) $row->courseid;
        $table->data[] = [
            format_string($row->title),
            $cname,
            fullname($row) . html_writer::empty_tag('br') . s($row->email),
            userdate($row->timerequested),
            html_writer::link($approve, get_string('approve', 'local_iiidem_classvideos'), ['class' => 'btn btn-sm btn-success'])
            . ' '
            . html_writer::link($reject, get_string('reject', 'local_iiidem_classvideos'), ['class' => 'btn btn-sm btn-secondary']),
        ];
    }
    echo html_writer::table($table);
}
$pendinghtml = ob_get_clean();

ob_start();
// Always show course filter so teachers open the same course the admin used.
if (!empty($courseoptions)) {
    $select = new single_select(
        new moodle_url('/local/iiidem_classvideos/manage.php'),
        'courseid',
        $courseoptions,
        $defaultcourseid,
        null
    );
    $select->set_label(get_string('selectcourse', 'local_iiidem_classvideos'));
    echo $OUTPUT->render($select);
    echo html_writer::div(get_string('sharedcoursevideos', 'local_iiidem_classvideos'), 'text-muted mb-3');
}
if (empty($videos)) {
    echo html_writer::div(get_string('novideos', 'local_iiidem_classvideos'), 'alert alert-secondary');
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [
        get_string('videotitle', 'local_iiidem_classvideos'),
        get_string('accesstype', 'local_iiidem_classvideos'),
        get_string('sessiondate', 'local_iiidem_classvideos'),
        get_string('actions', 'local_iiidem_classvideos'),
    ];
    foreach ($videos as $v) {
        $watch = new moodle_url('/local/iiidem_classvideos/watch.php', ['id' => $v->id]);
        $visaction = (int) $v->visible ? 'hide' : 'show';
        $visurl = new moodle_url('/local/iiidem_classvideos/manage.php', [
            'action' => $visaction,
            'videoid' => $v->id,
            'sesskey' => sesskey(),
            'courseid' => $defaultcourseid,
        ]);
        $table->data[] = [
            format_string($v->title) . (!(int) $v->visible
                ? ' ' . html_writer::span('(' . get_string('hide', 'local_iiidem_classvideos') . ')', 'text-muted')
                : ''),
            manager::access_label($v->accesstype),
            $v->sessiondate ? userdate($v->sessiondate, get_string('strftimedate', 'langconfig')) : '—',
            html_writer::link($watch, get_string('watch', 'local_iiidem_classvideos'), ['class' => 'btn btn-sm btn-primary'])
            . ' '
            . html_writer::link($visurl, get_string($visaction, 'local_iiidem_classvideos'), ['class' => 'btn btn-sm btn-outline-secondary']),
        ];
    }
    echo html_writer::table($table);
}
$listhtml = ob_get_clean();

$ctx = [
    'pagetitle' => get_string('managevideos', 'local_iiidem_classvideos'),
    'pagesubtitle' => get_string('managevideos_desc', 'local_iiidem_classvideos'),
    'dashboardurl' => (new moodle_url('/my/'))->out(false),
    'addheading' => get_string('addvideo', 'local_iiidem_classvideos'),
    'pendingheading' => get_string('pendingrequests', 'local_iiidem_classvideos'),
    'listheading' => get_string('yourvideos', 'local_iiidem_classvideos'),
    'formhtml' => $form->render(),
    'pendinghtml' => $pendinghtml,
    'listhtml' => $listhtml,
];

if ($useteachershell && class_exists('\theme_iiidem2\teacher_dashboard')) {
    $ctx['sidenav'] = \theme_iiidem2\teacher_dashboard::get_page_sidenav($userid, 'classvideos');
    $ctx['dashboardurl'] = \theme_iiidem2_get_dashboard_url()->out(false);
} else {
    $PAGE->set_heading($ctx['pagetitle']);
}

echo $OUTPUT->header();
if (!empty($ctx['sidenav'])) {
    echo $OUTPUT->render_from_template('theme_iiidem2/dashboard/teacher_classvideos', $ctx);
} else {
    // Admin / manager: standard page (no Professors sidebar). List first.
    echo html_writer::tag('p', $ctx['pagesubtitle'], ['class' => 'text-muted']);
    echo $OUTPUT->heading($ctx['listheading'], 3);
    echo $ctx['listhtml'];
    echo $OUTPUT->heading($ctx['pendingheading'], 3);
    echo $ctx['pendinghtml'];
    echo $OUTPUT->heading($ctx['addheading'], 3);
    echo $ctx['formhtml'];
}
echo $OUTPUT->footer();
