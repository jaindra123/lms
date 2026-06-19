<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/form/reply_form.php');

use local_iiidem_support\manager;

require_login();
require_capability('local/iiidem_support:manage', context_system::instance());
require_once($CFG->libdir . '/adminlib.php');

$context = context_system::instance();
$ticketid = optional_param('ticketid', 0, PARAM_INT);
$edit = optional_param('edit', 0, PARAM_BOOL);

$pageurl = new moodle_url('/local/iiidem_support/manage.php', $ticketid ? ['ticketid' => $ticketid] : []);
admin_externalpage_setup('local_iiidem_support', '', null, $pageurl);

$PAGE->set_title(get_string('manage', 'local_iiidem_support'));
$PAGE->set_heading(get_string('manage', 'local_iiidem_support'));
$PAGE->set_secondary_active_tab('local_iiidem_support_manage');
local_iiidem_support_extend_admin_secondary_nav($PAGE);
local_iiidem_support_page_requirements();

$form = null;
$ticket = null;
$showreplyform = false;

if ($ticketid) {
    $ticket = manager::get_ticket_for_admin($ticketid);
    if (!$ticket) {
        throw new moodle_exception('invalidrecord', 'error');
    }

    $formurl = new moodle_url('/local/iiidem_support/manage.php', ['ticketid' => $ticketid]);
    if ($edit) {
        $formurl->param('edit', 1);
    }

    $showreplyform = !$ticket->hasreply || $edit;
    $form = new local_iiidem_support_reply_form($formurl, [
        'ticketid' => $ticketid,
        'editing' => $ticket->hasreply && $edit,
    ]);
    $form->set_data([
        'ticketid' => $ticketid,
        'adminreply' => $showreplyform ? ($ticket->adminreply ?? '') : '',
        'status' => $ticket->status === manager::STATUS_OPEN ? manager::STATUS_IN_PROGRESS : $ticket->status,
    ]);

    if ($showreplyform && ($data = $form->get_data())) {
        $reply = trim($data->adminreply ?? '');
        if ($reply !== '') {
            manager::admin_reply((int) $data->ticketid, $reply, $data->status);
            redirect(
                new moodle_url('/local/iiidem_support/manage.php', ['ticketid' => $ticketid]),
                get_string('replysent', 'local_iiidem_support'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
        \core\notification::error(get_string('required'));
    }
}

echo $OUTPUT->header();

if ($ticketid && $ticket) {
    echo html_writer::start_div('iiidem-support-ticket iiidem-support-ticket--admin');

    echo html_writer::tag('p', $ticket->username . ' &lt;' . s($ticket->useremail) . '&gt;', ['class' => 'text-muted']);
    echo html_writer::tag('h3', format_string($ticket->subject), ['class' => 'h5 mt-2']);

    echo html_writer::tag('div',
        html_writer::span($ticket->statuslabel, 'iiidem-support-status iiidem-support-status--' . $ticket->statusclass) .
        html_writer::span($ticket->categorylabel, 'badge bg-light text-dark ms-2'),
        ['class' => 'mb-3']
    );

    echo html_writer::tag('h4', get_string('ticketmessage', 'local_iiidem_support'), ['class' => 'h6']);
    echo html_writer::tag('div', nl2br(s($ticket->message)), ['class' => 'iiidem-support-ticket__message mb-4']);

    if ($ticket->hasreply) {
        echo html_writer::tag('h4', get_string('ticketreply', 'local_iiidem_support'), ['class' => 'h6']);
        echo html_writer::tag('div', nl2br(s($ticket->adminreply)), ['class' => 'iiidem-support-ticket__reply mb-2']);
        if ($ticket->datereplied) {
            echo html_writer::tag('div', get_string('ticketdate', 'local_iiidem_support') . ': ' . $ticket->datereplied, [
                'class' => 'text-muted small mb-3',
            ]);
        }
        if (!$edit) {
            echo html_writer::tag('p', get_string('replysavedview', 'local_iiidem_support'), ['class' => 'text-success small mb-3']);
            echo html_writer::link(
                new moodle_url('/local/iiidem_support/manage.php', ['ticketid' => $ticketid, 'edit' => 1]),
                get_string('editreply', 'local_iiidem_support'),
                ['class' => 'btn btn-outline-secondary btn-sm mb-3']
            );
        }
    }

    if ($showreplyform && $form) {
        if ($edit && $ticket->hasreply) {
            echo html_writer::link(
                new moodle_url('/local/iiidem_support/manage.php', ['ticketid' => $ticketid]),
                get_string('cancelreplyedit', 'local_iiidem_support'),
                ['class' => 'btn btn-link btn-sm mb-2 p-0']
            );
        }
        $form->display();
    }

    echo html_writer::end_div();

    echo html_writer::div(
        html_writer::link(new moodle_url('/local/iiidem_support/manage.php'), get_string('back')),
        'mt-3'
    );
} else {
    echo html_writer::tag('p', get_string('managelead', 'local_iiidem_support'), ['class' => 'iiidem-support-lead']);
    $tickets = manager::get_all_tickets();

    if (empty($tickets)) {
        echo $OUTPUT->notification(get_string('notickets', 'local_iiidem_support'), 'notifymessage');
    } else {
        echo html_writer::start_tag('table', ['class' => 'generaltable iiidem-support-table']);
        echo html_writer::start_tag('thead');
        echo html_writer::tag('tr',
            html_writer::tag('th', get_string('ticketsubject', 'local_iiidem_support')) .
            html_writer::tag('th', 'Student') .
            html_writer::tag('th', get_string('ticketstatus', 'local_iiidem_support')) .
            html_writer::tag('th', get_string('ticketdate', 'local_iiidem_support'))
        );
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');
        foreach ($tickets as $row) {
            $viewurl = new moodle_url('/local/iiidem_support/manage.php', ['ticketid' => $row['id']]);
            echo html_writer::tag('tr',
                html_writer::tag('td', html_writer::link($viewurl, $row['subject'])) .
                html_writer::tag('td', $row['username'] . '<br><small>' . s($row['useremail']) . '</small>') .
                html_writer::tag('td', html_writer::span($row['statuslabel'], 'iiidem-support-status iiidem-support-status--' . $row['statusclass'])) .
                html_writer::tag('td', $row['date'])
            );
        }
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
    }
}

echo $OUTPUT->footer();
