<?php
require('../../config.php');

global $DB, $OUTPUT, $PAGE;

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url(new moodle_url('/local/coursefaq/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('managefaqs', 'local_coursefaq'));
$PAGE->set_heading(get_string('managefaqs', 'local_coursefaq'));

echo $OUTPUT->header();

echo html_writer::div(
    html_writer::link(
        new moodle_url('/local/coursefaq/add.php'),
        get_string('addfaq', 'local_coursefaq'),
        ['class' => 'btn btn-primary']
    ),
    'mb-4'
);

$faqs = $DB->get_records('local_coursefaq', null, 'courseid ASC, id ASC');

if (empty($faqs)) {
    echo $OUTPUT->notification(get_string('nofaqs', 'local_coursefaq'), 'info');
    echo $OUTPUT->footer();
    exit;
}

foreach ($faqs as $recordid => $faq) {
    $id = (int) ($faq->id ?? $recordid);
    $course = $DB->get_record('course', ['id' => $faq->courseid], 'id, fullname', IGNORE_MISSING);
    $coursename = $course ? format_string($course->fullname) : get_string('unknowncourse', 'local_coursefaq');

    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('div', get_string('course') . ': ' . $coursename, ['class' => 'text-muted small mb-2']);
    echo html_writer::tag('p', html_writer::tag('strong', get_string('question', 'local_coursefaq') . ': ') .
        format_string($faq->question), ['class' => 'mb-2']);
    echo html_writer::tag('div', html_writer::tag('strong', get_string('answer', 'local_coursefaq') . ': ') .
        format_text($faq->answer), ['class' => 'mb-3']);

    echo html_writer::start_div('d-flex gap-2');
    echo html_writer::link(
        new moodle_url('/local/coursefaq/edit.php', ['id' => $id]),
        get_string('edit'),
        ['class' => 'btn btn-outline-secondary btn-sm']
    );
    echo html_writer::link(
        new moodle_url('/local/coursefaq/delete.php', ['id' => $id]),
        get_string('delete'),
        ['class' => 'btn btn-outline-danger btn-sm']
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
