<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Admin reply form for support tickets.
 */
class local_iiidem_support_reply_form extends moodleform {

    /**
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $ticketid = (int) ($this->_customdata['ticketid'] ?? 0);

        $mform->addElement('hidden', 'ticketid', $ticketid);
        $mform->setType('ticketid', PARAM_INT);

        $statuses = [
            \local_iiidem_support\manager::STATUS_IN_PROGRESS => get_string('status_in_progress', 'local_iiidem_support'),
            \local_iiidem_support\manager::STATUS_RESOLVED => get_string('status_resolved', 'local_iiidem_support'),
            \local_iiidem_support\manager::STATUS_CLOSED => get_string('status_closed', 'local_iiidem_support'),
        ];

        $mform->addElement('textarea', 'adminreply', get_string('reply', 'local_iiidem_support'), 'rows="5" cols="60"');
        $mform->setType('adminreply', PARAM_TEXT);
        $mform->addRule('adminreply', get_string('required'), 'required', null, 'client');
        $mform->addRule('adminreply', get_string('required'), 'required', null, 'server');

        $mform->addElement('select', 'status', get_string('ticketstatus', 'local_iiidem_support'), $statuses);
        $mform->setType('status', PARAM_ALPHANUMEXT);

        $this->add_action_buttons(true, get_string($this->_customdata['editing'] ?? false ? 'updatereply' : 'reply', 'local_iiidem_support'));
    }
}
