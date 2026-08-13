<?php
/**
 * Teacher / admin: list videos, upload, edit, delete, approve requests.
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
$videoid = (int) optional_param('videoid', 0, PARAM_INT);
$editid = (int) optional_param('editid', 0, PARAM_INT);
$reqid = (int) optional_param('reqid', 0, PARAM_INT);
$confirm = (int) optional_param('confirm', 0, PARAM_INT);

$editingvideo = null;
if ($editid) {
    $editingvideo = manager::get_video($editid);
    if (!$editingvideo || !manager::user_can_manage_video($editingvideo, $userid)) {
        throw new moodle_exception('nopermissions', 'error');
    }
    $defaultcourseid = (int) $editingvideo->courseid;
}

$pageparams = ['courseid' => $defaultcourseid];
if ($editid) {
    $pageparams['editid'] = $editid;
}
$PAGE->set_url(new moodle_url('/local/iiidem_classvideos/manage.php', $pageparams));
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
    redirect(
        new moodle_url('/local/iiidem_classvideos/manage.php', ['courseid' => $defaultcourseid]),
        get_string('visibilityupdated', 'local_iiidem_classvideos')
    );
}

// Delete with confirmation.
if ($action === 'delete' && $videoid && confirm_sesskey()) {
    $todelete = manager::get_video($videoid);
    if (!$todelete || !manager::user_can_manage_video($todelete, $userid)) {
        throw new moodle_exception('nopermissions', 'error');
    }
    $returnurl = new moodle_url('/local/iiidem_classvideos/manage.php', [
        'courseid' => (int) $todelete->courseid,
    ]);
    if ($confirm) {
        manager::delete_video($videoid, $userid);
        redirect($returnurl, get_string('videodeleted', 'local_iiidem_classvideos'));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdeletevideo', 'local_iiidem_classvideos', format_string($todelete->title)),
        new moodle_url('/local/iiidem_classvideos/manage.php', [
            'action' => 'delete',
            'videoid' => $videoid,
            'confirm' => 1,
            'sesskey' => sesskey(),
            'courseid' => (int) $todelete->courseid,
        ]),
        $returnurl
    );
    echo $OUTPUT->footer();
    exit;
}

$maxbytes = get_max_upload_file_size($CFG->maxbytes);
if ($maxbytes <= 0 || $maxbytes > manager::MAX_UPLOAD_BYTES) {
    $maxbytes = manager::MAX_UPLOAD_BYTES;
}

$formcustom = [
    'courseoptions' => $courseoptions,
    'maxbytes' => $maxbytes,
];
if ($editingvideo) {
    $formcustom['editing'] = true;
    $formcustom['videoid'] = (int) $editingvideo->id;
    $formcustom['courseid'] = (int) $editingvideo->courseid;
}

$formurlparams = ['courseid' => $defaultcourseid];
if ($editingvideo) {
    $formurlparams['editid'] = (int) $editingvideo->id;
}
$form = new video_form(
    new moodle_url('/local/iiidem_classvideos/manage.php', $formurlparams),
    $formcustom
);

if ($editingvideo) {
    $draftitemid = file_get_submitted_draft_itemid('videofile');
    $ctx = context_course::instance((int) $editingvideo->courseid);
    file_prepare_draft_area(
        $draftitemid,
        $ctx->id,
        'local_iiidem_classvideos',
        'video',
        (int) $editingvideo->id,
        ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => $maxbytes]
    );
    $form->set_data([
        'id' => (int) $editingvideo->id,
        'editing' => 1,
        'courseid' => (int) $editingvideo->courseid,
        'title' => $editingvideo->title,
        'description' => $editingvideo->description,
        'sessiondate' => (int) $editingvideo->sessiondate ?: 0,
        'accesstype' => $editingvideo->accesstype,
        'externalurl' => $editingvideo->externalurl ?? '',
        'videofile' => $draftitemid,
    ]);
} else {
    $form->set_data([
        'courseid' => $defaultcourseid,
        'accesstype' => manager::ACCESS_REQUEST,
    ]);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/iiidem_classvideos/manage.php', ['courseid' => $defaultcourseid]));
}

if ($data = $form->get_data()) {
    $sessiondate = 0;
    if (!empty($data->sessiondate)) {
        $sessiondate = is_numeric($data->sessiondate)
            ? (int) $data->sessiondate
            : (int) $data->sessiondate;
    }

    if (!empty($data->editing) && !empty($data->id)) {
        manager::update_video(
            (int) $data->id,
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
            get_string('videoupdated', 'local_iiidem_classvideos')
        );
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
        $edit = new moodle_url('/local/iiidem_classvideos/manage.php', [
            'courseid' => $defaultcourseid,
            'editid' => $v->id,
        ]);
        $visaction = (int) $v->visible ? 'hide' : 'show';
        $visurl = new moodle_url('/local/iiidem_classvideos/manage.php', [
            'action' => $visaction,
            'videoid' => $v->id,
            'sesskey' => sesskey(),
            'courseid' => $defaultcourseid,
        ]);
        $delurl = new moodle_url('/local/iiidem_classvideos/manage.php', [
            'action' => 'delete',
            'videoid' => $v->id,
            'sesskey' => sesskey(),
            'courseid' => $defaultcourseid,
        ]);
        $actions = html_writer::link($watch, get_string('watch', 'local_iiidem_classvideos'), ['class' => 'btn btn-sm btn-primary'])
            . ' '
            . html_writer::link($edit, get_string('edit'), ['class' => 'btn btn-sm btn-secondary'])
            . ' '
            . html_writer::link($visurl, get_string($visaction, 'local_iiidem_classvideos'), ['class' => 'btn btn-sm btn-outline-secondary'])
            . ' '
            . html_writer::link($delurl, get_string('delete'), ['class' => 'btn btn-sm btn-outline-danger']);
        $table->data[] = [
            format_string($v->title) . (!(int) $v->visible
                ? ' ' . html_writer::span('(' . get_string('hide', 'local_iiidem_classvideos') . ')', 'text-muted')
                : ''),
            manager::access_label($v->accesstype),
            $v->sessiondate ? userdate($v->sessiondate, get_string('strftimedate', 'langconfig')) : '—',
            $actions,
        ];
    }
    echo html_writer::table($table);
}
$listhtml = ob_get_clean();

$addheading = $editingvideo
    ? get_string('editvideo', 'local_iiidem_classvideos')
    : get_string('addvideo', 'local_iiidem_classvideos');

$ctx = [
    'pagetitle' => get_string('managevideos', 'local_iiidem_classvideos'),
    'pagesubtitle' => get_string('managevideos_desc', 'local_iiidem_classvideos'),
    'dashboardurl' => (new moodle_url('/my/'))->out(false),
    'addheading' => $addheading,
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
