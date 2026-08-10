<?php
require('../../config.php');

global $DB, $OUTPUT, $PAGE;

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$faq = $DB->get_record('local_coursefaq', ['id' => $id], '*', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursefaq/delete.php', ['id' => $id]));
$PAGE->set_title(get_string('deletefaq', 'local_coursefaq'));
$PAGE->set_heading(get_string('deletefaq', 'local_coursefaq'));

if ($confirm && confirm_sesskey() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $DB->delete_records('local_coursefaq', ['id' => $id]);
    redirect(
        new moodle_url('/local/coursefaq/index.php'),
        get_string('faqdeleted', 'local_coursefaq'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

$cancelurl = new moodle_url('/local/coursefaq/index.php');

echo html_writer::tag('p', get_string('confirmdeletefaq', 'local_coursefaq', format_string($faq->question)));
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => (new moodle_url('/local/coursefaq/delete.php'))->out(false),
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'confirm', 'value' => 1]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('button', get_string('delete'), ['type' => 'submit', 'class' => 'btn btn-danger']);
echo ' ';
echo html_writer::link($cancelurl, get_string('cancel'), ['class' => 'btn btn-secondary']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
