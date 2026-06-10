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

if ($confirm && confirm_sesskey()) {
    $DB->delete_records('local_coursefaq', ['id' => $id]);
    redirect(
        new moodle_url('/local/coursefaq/index.php'),
        get_string('faqdeleted', 'local_coursefaq'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

$confirmurl = new moodle_url('/local/coursefaq/delete.php', [
    'id' => $id,
    'confirm' => 1,
    'sesskey' => sesskey(),
]);
$cancelurl = new moodle_url('/local/coursefaq/index.php');

echo $OUTPUT->confirm(
    get_string('confirmdeletefaq', 'local_coursefaq', format_string($faq->question)),
    $confirmurl,
    $cancelurl
);

echo $OUTPUT->footer();
