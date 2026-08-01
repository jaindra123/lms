<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_iiidem_livequiz\manager;

global $USER, $OUTPUT;

require_login();

$courseid = required_param('courseid', PARAM_INT);
$sessionid = optional_param('sessionid', 0, PARAM_INT);

$course = get_course($courseid);
require_course_login($course);

if (!manager::user_can_manage($courseid)) {
    throw new moodle_exception('nopermission', 'local_iiidem_livequiz');
}

$context = context_course::instance($courseid);
$PAGE->set_url(new moodle_url('/local/iiidem_livequiz/manage.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('manage', 'local_iiidem_livequiz'));
$PAGE->set_heading(format_string($course->fullname));

$livepages = manager::get_live_class_pages($courseid);
if (empty($livepages)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('nolivectitle', 'local_iiidem_livequiz'), 2);
    echo $OUTPUT->notification(
        get_string('nolivemessage', 'local_iiidem_livequiz'),
        \core\output\notification::NOTIFY_WARNING
    );
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/course/view.php', ['id' => $courseid]),
            get_string('returntocourse', 'local_iiidem_livequiz'),
            ['class' => 'btn btn-primary']
        ),
        'mt-3'
    );
    echo $OUTPUT->footer();
    exit;
}

// Handle POST actions.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $action = required_param('action', PARAM_ALPHANUMEXT);

    if ($action === 'createsession') {
        $name = required_param('sessionname', PARAM_TEXT);
        $cmid = required_param('cmid', PARAM_INT);
        $newid = manager::create_session($courseid, $cmid, $name, (int) $USER->id);
        redirect(new moodle_url('/local/iiidem_livequiz/manage.php', [
            'courseid' => $courseid,
            'sessionid' => $newid,
        ]), get_string('sessioncreated', 'local_iiidem_livequiz'));
    }

    $sessionid = required_param('sessionid', PARAM_INT);
    $session = manager::get_session($sessionid);
    if (!$session || (int) $session->courseid !== $courseid) {
        throw new moodle_exception('invalidsession', 'local_iiidem_livequiz');
    }

    switch ($action) {
        case 'addquestion':
            $text = required_param('questiontext', PARAM_TEXT);
            $options = [
                required_param('opt0', PARAM_TEXT),
                required_param('opt1', PARAM_TEXT),
                optional_param('opt2', '', PARAM_TEXT),
                optional_param('opt3', '', PARAM_TEXT),
            ];
            $correctindex = required_param('correctindex', PARAM_INT);
            manager::add_question($sessionid, $text, $options, $correctindex);
            redirect(new moodle_url('/local/iiidem_livequiz/manage.php', [
                'courseid' => $courseid,
                'sessionid' => $sessionid,
            ]), get_string('questionadded', 'local_iiidem_livequiz'));
            break;

        case 'deletequestion':
            $questionid = required_param('questionid', PARAM_INT);
            manager::delete_question($questionid, $sessionid);
            redirect(new moodle_url('/local/iiidem_livequiz/manage.php', [
                'courseid' => $courseid,
                'sessionid' => $sessionid,
            ]), get_string('questiondeleted', 'local_iiidem_livequiz'));
            break;

        case 'release':
            manager::activate_session($sessionid);
            redirect(new moodle_url('/local/iiidem_livequiz/manage.php', [
                'courseid' => $courseid,
                'sessionid' => $sessionid,
            ]), get_string('sessionreleased', 'local_iiidem_livequiz'));
            break;

        case 'close':
            manager::close_session($sessionid);
            redirect(new moodle_url('/local/iiidem_livequiz/manage.php', [
                'courseid' => $courseid,
                'sessionid' => $sessionid,
            ]), get_string('sessionclosed', 'local_iiidem_livequiz'));
            break;
    }
}

echo $OUTPUT->header();

echo html_writer::tag('h2', get_string('managesessions', 'local_iiidem_livequiz'));

if (!$sessionid) {
    // List sessions + create form.
    $sessions = manager::get_course_sessions($courseid);
    if ($sessions) {
        $table = new html_table();
        $table->head = [get_string('sessionname', 'local_iiidem_livequiz'), get_string('status', 'local_iiidem_livequiz'), ''];
        foreach ($sessions as $session) {
            $url = new moodle_url('/local/iiidem_livequiz/manage.php', [
                'courseid' => $courseid,
                'sessionid' => $session->id,
            ]);
            $table->data[] = [
                format_string($session->name),
                manager::status_label((int) $session->status),
                html_writer::link($url, get_string('edit', 'moodle')),
            ];
        }
        echo html_writer::table($table);
    }

    echo html_writer::tag('h3', get_string('createsession', 'local_iiidem_livequiz'));
    echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'mb-4']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'createsession']);

    echo html_writer::tag('label', get_string('sessionname', 'local_iiidem_livequiz'), ['for' => 'sessionname']);
    echo html_writer::empty_tag('input', [
        'type' => 'text',
        'class' => 'form-control mb-2',
        'id' => 'sessionname',
        'name' => 'sessionname',
        'required' => 'required',
        'placeholder' => 'Week 1 live check-in',
    ]);

    echo html_writer::tag('label', get_string('selectlivepage', 'local_iiidem_livequiz'), ['for' => 'cmid']);
    $selectoptions = [];
    foreach ($livepages as $page) {
        $selectoptions[$page['cmid']] = $page['name'];
    }
    echo html_writer::select($selectoptions, 'cmid', reset($livepages)['cmid'], false, ['class' => 'form-control mb-3', 'id' => 'cmid']);

    echo html_writer::tag('button', get_string('createsession', 'local_iiidem_livequiz'), [
        'type' => 'submit',
        'class' => 'btn btn-primary',
    ]);
    echo html_writer::end_tag('form');
    echo $OUTPUT->footer();
    exit;
}

$session = manager::get_session($sessionid);
if (!$session || (int) $session->courseid !== $courseid) {
    throw new moodle_exception('invalidsession', 'local_iiidem_livequiz');
}

$status = (int) $session->status;
echo html_writer::div(
    format_string($session->name) . ' — ' . manager::status_label($status),
    'mb-3 fw-bold'
);

$backurl = new moodle_url('/local/iiidem_livequiz/manage.php', ['courseid' => $courseid]);
echo html_writer::link($backurl, '← ' . get_string('back'));

if ($status === manager::STATUS_DRAFT) {
    echo html_writer::tag('h3', get_string('addquestion', 'local_iiidem_livequiz'), ['class' => 'mt-4']);
    echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'mb-4']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'addquestion']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sessionid', 'value' => $sessionid]);

    echo html_writer::tag('label', get_string('questiontext', 'local_iiidem_livequiz'));
    echo html_writer::tag('textarea', '', [
        'name' => 'questiontext',
        'class' => 'form-control mb-2',
        'rows' => 2,
        'required' => 'required',
    ]);

    for ($i = 0; $i < 4; $i++) {
        $required = $i < 2 ? 'required' : '';
        echo html_writer::tag('label', get_string('option', 'local_iiidem_livequiz', $i + 1));
        echo html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => 'opt' . $i,
            'class' => 'form-control mb-2',
            $required => $required,
        ]);
    }

    echo html_writer::tag('label', get_string('correctoption', 'local_iiidem_livequiz'));
    $correctoptions = [
        '' => get_string('selectcorrectoption', 'local_iiidem_livequiz'),
        0 => get_string('option', 'local_iiidem_livequiz', 1),
        1 => get_string('option', 'local_iiidem_livequiz', 2),
        2 => get_string('option', 'local_iiidem_livequiz', 3),
        3 => get_string('option', 'local_iiidem_livequiz', 4),
    ];
    echo html_writer::select($correctoptions, 'correctindex', '', false, [
        'class' => 'form-control mb-3',
        'required' => 'required',
    ]);

    echo html_writer::tag('button', get_string('addquestion', 'local_iiidem_livequiz'), [
        'type' => 'submit',
        'class' => 'btn btn-secondary',
    ]);
    echo html_writer::end_tag('form');
}

$questions = manager::get_questions($sessionid);
if ($questions) {
    echo html_writer::tag('h3', get_string('livequestions', 'local_iiidem_livequiz'), ['class' => 'mt-4']);
    $qtable = new html_table();
    $qtable->head = ['#', get_string('questiontext', 'local_iiidem_livequiz'), ''];
    foreach ($questions as $index => $question) {
        $opts = [];
        foreach (manager::question_options($question) as $opt) {
            $opts[] = $opt['label'];
        }
        $deleteform = '';
        if ($status === manager::STATUS_DRAFT) {
            $deleteform = html_writer::start_tag('form', ['method' => 'post', 'style' => 'display:inline']);
            $deleteform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            $deleteform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'deletequestion']);
            $deleteform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sessionid', 'value' => $sessionid]);
            $deleteform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'questionid', 'value' => $question->id]);
            $deleteform .= html_writer::tag('button', get_string('deletequestion', 'local_iiidem_livequiz'), [
                'type' => 'submit',
                'class' => 'btn btn-link text-danger btn-sm',
            ]);
            $deleteform .= html_writer::end_tag('form');
        }

        $qtable->data[] = [
            $index + 1,
            format_text($question->questiontext, FORMAT_PLAIN) . html_writer::tag(
                'div',
                implode(' · ', $opts),
                ['class' => 'text-muted small']
            ),
            $deleteform,
        ];
    }
    echo html_writer::table($qtable);
}

echo html_writer::start_div('mt-3 d-flex gap-2 flex-wrap');
if ($status === manager::STATUS_DRAFT && $questions) {
    echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'd-inline']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'release']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sessionid', 'value' => $sessionid]);
    echo html_writer::tag('button', get_string('release', 'local_iiidem_livequiz'), [
        'type' => 'submit',
        'class' => 'btn btn-success',
    ]);
    echo html_writer::end_tag('form');
}

if ($status === manager::STATUS_ACTIVE) {
    echo html_writer::start_tag('form', ['method' => 'post', 'class' => 'd-inline']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'close']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sessionid', 'value' => $sessionid]);
    echo html_writer::tag('button', get_string('close', 'local_iiidem_livequiz'), [
        'type' => 'submit',
        'class' => 'btn btn-warning',
    ]);
    echo html_writer::end_tag('form');
}
echo html_writer::end_div();

if ($status === manager::STATUS_ACTIVE || $status === manager::STATUS_CLOSED) {
    $results = manager::get_teacher_results($sessionid);
    echo html_writer::tag('h3', get_string('results', 'local_iiidem_livequiz'), ['class' => 'mt-4']);
    echo html_writer::div(
        get_string('submittedcount', 'local_iiidem_livequiz', $results['submittedcount']),
        'mb-2',
        ['id' => 'iiidem-livequiz-submitted-count']
    );

    $rtable = new html_table();
    $rtable->attributes['id'] = 'iiidem-livequiz-results-table';
    $rtable->head = [
        get_string('student', 'local_iiidem_livequiz'),
        get_string('status', 'local_iiidem_livequiz'),
        get_string('score', 'local_iiidem_livequiz'),
    ];
    foreach ($results['rows'] as $row) {
        $rtable->data[] = [
            $row['studentname'] . html_writer::tag('div', $row['email'], ['class' => 'small text-muted']),
            $row['complete'] ? get_string('active', 'local_iiidem_livequiz') : get_string('notstarted', 'local_iiidem_livequiz'),
            $row['scorelabel'],
        ];
    }
    if (empty($rtable->data)) {
        $emptycell = new html_table_cell(get_string('nostudents', 'local_iiidem_livequiz'));
        $emptycell->colspan = 3;
        $emptycell->attributes['class'] = 'text-muted';
        $rtable->data[] = [$emptycell];
    }
    echo html_writer::table($rtable);

    if ($status === manager::STATUS_ACTIVE) {
        $PAGE->requires->js(new moodle_url('/local/iiidem_livequiz/livequiz-manage.js'));
        echo html_writer::div('', '', [
            'id' => 'iiidem-livequiz-manage-data',
            'data-sessionid' => $sessionid,
            'data-apiurl' => (new moodle_url('/local/iiidem_livequiz/api.php'))->out(false),
            'data-sesskey' => sesskey(),
            'data-submittedlabel' => get_string('submittedcount', 'local_iiidem_livequiz', '__COUNT__'),
        ]);
    }
}

echo $OUTPUT->footer();
