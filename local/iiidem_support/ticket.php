<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_iiidem_support\manager;

require_login();

$id = required_param('id', PARAM_INT);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/iiidem_support/ticket.php', ['id' => $id]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('ticketview', 'local_iiidem_support'));
$PAGE->set_heading(get_string('ticketid', 'local_iiidem_support', $id));
local_iiidem_support_page_requirements();

$isadmin = manager::user_can_manage();
$ticket = $isadmin
    ? manager::get_ticket_for_admin($id)
    : manager::get_user_ticket($id, $USER->id);

if (!$ticket) {
    throw new moodle_exception('invalidrecord', 'error');
}

if (!$isadmin && (int) $ticket->userid !== (int) $USER->id) {
    throw new required_capability_exception($context, 'local/iiidem_support:submit', 'nopermissions', '');
}

echo $OUTPUT->header();

echo html_writer::start_div('iiidem-support-ticket');
echo html_writer::tag('div',
    html_writer::span($ticket->statuslabel, 'iiidem-support-status iiidem-support-status--' . $ticket->statusclass) .
    html_writer::span($ticket->categorylabel, 'badge bg-light text-dark ms-2'),
    ['class' => 'mb-3']
);
echo html_writer::tag('h3', format_string($ticket->subject), ['class' => 'h5']);
echo html_writer::tag('div', get_string('ticketdate', 'local_iiidem_support') . ': ' . $ticket->datecreated, ['class' => 'text-muted small mb-3']);

if (!empty($ticket->coursename)) {
    echo html_writer::tag('div', get_string('ticketcourse', 'local_iiidem_support') . ': ' . $ticket->coursename, ['class' => 'mb-3']);
}

echo html_writer::tag('h4', get_string('ticketmessage', 'local_iiidem_support'), ['class' => 'h6']);
echo html_writer::tag('div', nl2br(s($ticket->message)), ['class' => 'iiidem-support-ticket__message mb-4']);

echo html_writer::tag('h4', get_string('ticketreply', 'local_iiidem_support'), ['class' => 'h6']);
if ($ticket->hasreply) {
    echo html_writer::tag('div', nl2br(s($ticket->adminreply)), ['class' => 'iiidem-support-ticket__reply']);
    if ($ticket->datereplied) {
        echo html_writer::tag('div', $ticket->datereplied, ['class' => 'text-muted small mt-2']);
    }
} else {
    echo $OUTPUT->notification(get_string('ticketnoreply', 'local_iiidem_support'), 'notifymessage');
}

echo html_writer::end_div();

$backurl = $isadmin
    ? new moodle_url('/local/iiidem_support/manage.php')
    : new moodle_url('/local/iiidem_support/tickets.php');
echo html_writer::div(html_writer::link($backurl, get_string('back')), 'mt-4');

echo $OUTPUT->footer();
