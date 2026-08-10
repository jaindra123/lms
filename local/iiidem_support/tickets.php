<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_iiidem_support\manager;

require_login();

if (class_exists('\theme_iiidem2\rate_limit')) {
    // List page + Intruder-style POST floods (PoC posted ticket_id/message here).
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        \theme_iiidem2\rate_limit::require_allowed('support_tickets_post', 10, 60);
        \theme_iiidem2\rate_limit::require_allowed('support_tickets_post_hour', 40, 3600);
    } else {
        \theme_iiidem2\rate_limit::require_allowed('support_tickets_page', 60, 60);
    }
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/iiidem_support/tickets.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('ticketlist', 'local_iiidem_support'));
$PAGE->set_heading(get_string('ticketlist', 'local_iiidem_support'));
local_iiidem_support_page_requirements();

$tickets = manager::get_user_tickets($USER->id);

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('ticketlistlead', 'local_iiidem_support'), ['class' => 'iiidem-support-lead']);

if (empty($tickets)) {
    echo $OUTPUT->notification(get_string('notickets', 'local_iiidem_support'), 'notifymessage');
    echo html_writer::link(
        new moodle_url('/local/iiidem_support/ticket_new.php'),
        get_string('raiseticket', 'local_iiidem_support'),
        ['class' => 'btn btn-primary mt-3']
    );
} else {
    echo html_writer::start_tag('table', ['class' => 'generaltable iiidem-support-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::tag('tr',
        html_writer::tag('th', get_string('ticketsubject', 'local_iiidem_support')) .
        html_writer::tag('th', get_string('ticketcategory', 'local_iiidem_support')) .
        html_writer::tag('th', get_string('ticketstatus', 'local_iiidem_support')) .
        html_writer::tag('th', get_string('ticketdate', 'local_iiidem_support'))
    );
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    foreach ($tickets as $ticket) {
        $statuscell = html_writer::span($ticket['statuslabel'], 'iiidem-support-status iiidem-support-status--' . $ticket['statusclass']);
        if (!empty($ticket['hasreply'])) {
            $statuscell .= ' ' . html_writer::span(get_string('ticketreplied', 'local_iiidem_support'), 'badge bg-success ms-1');
        }
        echo html_writer::tag('tr',
            html_writer::tag('td', html_writer::link($ticket['url'], $ticket['subject'])) .
            html_writer::tag('td', $ticket['categorylabel']) .
            html_writer::tag('td', $statuscell) .
            html_writer::tag('td', $ticket['date'])
        );
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}

echo html_writer::div(
    html_writer::link(new moodle_url('/local/iiidem_support/ticket_new.php'), get_string('raiseticket', 'local_iiidem_support'), ['class' => 'btn btn-primary']),
    'mt-3'
);

echo $OUTPUT->footer();
