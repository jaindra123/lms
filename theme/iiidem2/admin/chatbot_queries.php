<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin list / reply for homepage chatbot queries.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$id = optional_param('id', 0, PARAM_INT);
$replytext = optional_param('reply', '', PARAM_TEXT);

$PAGE->set_url(new moodle_url('/theme/iiidem2/admin/chatbot_queries.php', $id ? ['id' => $id] : []));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title(theme_iiidem2_str('homepagechatbotqueries', null, 'Chatbot queries'));
$PAGE->set_heading(theme_iiidem2_str('homepagechatbotqueries', null, 'Chatbot queries'));

if ($id && $replytext !== '' && confirm_sesskey()) {
    $result = theme_iiidem2_chatbot_reply($id, $replytext, (int) $USER->id);
    if ($result['success']) {
        \core\notification::success($result['message']);
    } else {
        \core\notification::error($result['message']);
    }
    redirect(new moodle_url('/theme/iiidem2/admin/chatbot_queries.php'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(theme_iiidem2_str('homepagechatbotqueries', null, 'Chatbot queries'));
echo html_writer::tag(
    'p',
    theme_iiidem2_str(
        'homepagechatbotqueriesintro',
        null,
        'Visitor questions from the homepage chatbot. Reply here or use the floating chat button while logged in as admin.'
    ),
    ['class' => 'text-muted']
);

if (!theme_iiidem2_chatbot_table_ready()) {
    echo $OUTPUT->notification(
        theme_iiidem2_str(
            'homepagechatbotnotable',
            null,
            'Chatbot storage is not ready. Please run Site administration → Notifications.'
        ),
        'error'
    );
    echo $OUTPUT->footer();
    exit;
}

$open = $DB->get_records('theme_iiidem2_chatbot', ['status' => 'open'], 'timecreated DESC', '*', 0, 50);
$answered = $DB->get_records('theme_iiidem2_chatbot', ['status' => 'answered'], 'timereplied DESC', '*', 0, 30);

echo $OUTPUT->heading(theme_iiidem2_str('homepagechatbotopen', null, 'Open questions'), 3);
if (!$open) {
    echo $OUTPUT->notification(theme_iiidem2_str('homepagechatbotnoopen', null, 'No open questions right now.'), 'info');
} else {
    foreach ($open as $row) {
        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-body');
        echo html_writer::tag('h5', s($row->name) . ' (' . s($row->email) . ')', ['class' => 'card-title']);
        echo html_writer::tag('p', s($row->question), ['class' => 'card-text']);
        echo html_writer::tag('small', userdate($row->timecreated), ['class' => 'text-muted d-block mb-2']);
        echo html_writer::start_tag('form', [
            'method' => 'post',
            'action' => (new moodle_url('/theme/iiidem2/admin/chatbot_queries.php'))->out(false),
        ]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $row->id]);
        echo html_writer::tag('textarea', '', [
            'name' => 'reply',
            'rows' => 3,
            'class' => 'form-control mb-2',
            'required' => 'required',
            'placeholder' => theme_iiidem2_str('homepagechatbotreplyplaceholder', null, 'Type your reply to the user…'),
        ]);
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-primary',
            'value' => theme_iiidem2_str('homepagechatbotreplysend', null, 'Reply'),
        ]);
        echo html_writer::end_tag('form');
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
}

echo $OUTPUT->heading(theme_iiidem2_str('homepagechatbotanswered', null, 'Recently answered'), 3);
if (!$answered) {
    echo html_writer::tag('p', theme_iiidem2_str('homepagechatbotqueriesempty', null, 'No chatbot questions yet.'), ['class' => 'text-muted']);
} else {
    $table = new html_table();
    $table->head = ['Time', 'Visitor', 'Question', 'Reply', 'Emailed'];
    $table->data = [];
    foreach ($answered as $row) {
        $table->data[] = [
            userdate($row->timereplied ?: $row->timemodified),
            s($row->name) . '<br><small>' . s($row->email) . '</small>',
            s($row->question),
            s($row->reply),
            !empty($row->emailsent) ? 'Yes' : 'No',
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
