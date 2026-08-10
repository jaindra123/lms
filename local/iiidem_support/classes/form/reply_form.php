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

        $mform->addElement('textarea', 'adminreply', get_string('reply', 'local_iiidem_support'),
            'rows="5" cols="60" maxlength="5000"');
        $mform->setType('adminreply', PARAM_TEXT);
        $mform->addRule('adminreply', get_string('required'), 'required', null, 'client');
        $mform->addRule('adminreply', get_string('required'), 'required', null, 'server');
        $mform->addRule('adminreply', get_string('maximumchars', '', 5000), 'maxlength', 5000, 'client');
        $mform->addRule('adminreply', get_string('maximumchars', '', 5000), 'maxlength', 5000, 'server');

        $mform->addElement('select', 'status', get_string('ticketstatus', 'local_iiidem_support'), $statuses);
        $mform->setType('status', PARAM_ALPHANUMEXT);

        $this->add_action_buttons(true, get_string($this->_customdata['editing'] ?? false ? 'updatereply' : 'reply', 'local_iiidem_support'));
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $reply = trim((string) ($data['adminreply'] ?? ''));
        if ($reply === '') {
            $errors['adminreply'] = get_string('required');
        } else if (\core_text::strlen($reply) > 5000) {
            $errors['adminreply'] = get_string('maximumchars', '', 5000);
        }

        $allowed = [
            \local_iiidem_support\manager::STATUS_IN_PROGRESS,
            \local_iiidem_support\manager::STATUS_RESOLVED,
            \local_iiidem_support\manager::STATUS_CLOSED,
        ];
        $status = (string) ($data['status'] ?? '');
        if ($status !== '' && !in_array($status, $allowed, true)) {
            $errors['status'] = get_string('invalidparameter', 'error');
        }

        return $errors;
    }
}
