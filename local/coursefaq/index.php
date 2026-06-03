<?php
require('../../config.php');

global $DB, $OUTPUT, $PAGE;

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url(new moodle_url('/local/coursefaq/index.php'));
$PAGE->set_context($context);
$PAGE->set_title('Course FAQs');
$PAGE->set_heading('Course FAQs');

echo $OUTPUT->header();

$faqs = $DB->get_records('local_coursefaq');

echo html_writer::link(
    new moodle_url('/local/coursefaq/add.php'),
    '➕ Add FAQ'
);

echo "<br><br>";

foreach ($faqs as $faq) {

    echo "<div style='border:1px solid #ccc; padding:10px; margin-bottom:10px;'>";

    echo "<b>Q:</b> " . format_string($faq->question) . "<br>";
    echo "<b>A:</b> " . format_text($faq->answer) . "<br><br>";

    echo html_writer::link(
        new moodle_url('/local/coursefaq/edit.php', ['id' => $faq->id]),
        'Edit'
    );

    echo " | ";

    echo html_writer::link(
        new moodle_url('/local/coursefaq/delete.php', ['id' => $faq->id]),
        'Delete'
    );

    echo "</div>";
}

echo $OUTPUT->footer();