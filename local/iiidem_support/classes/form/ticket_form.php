<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Raise support ticket form.
 */
class local_iiidem_support_ticket_form extends moodleform {

    /**
     * @return void
     */
    protected function definition() {
        global $USER;

        $mform = $this->_form;
        $courses = $this->_customdata['courses'] ?? [];
        $categories = \local_iiidem_support\manager::get_categories();

        $mform->addElement('select', 'category', get_string('ticketcategory', 'local_iiidem_support'), $categories);
        $mform->setType('category', PARAM_ALPHANUMEXT);

        $courseoptions = [0 => get_string('none')];
        foreach ($courses as $course) {
            $courseoptions[$course->id] = format_string($course->fullname);
        }
        $mform->addElement('select', 'courseid', get_string('ticketcourse', 'local_iiidem_support'), $courseoptions);
        $mform->addHelpButton('courseid', 'ticketcourse', 'local_iiidem_support');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('text', 'subject', get_string('ticketsubject', 'local_iiidem_support'), ['size' => 64]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('required'), 'required');

        $mform->addElement('textarea', 'message', get_string('ticketmessage', 'local_iiidem_support'), 'rows="6" cols="60"');
        $mform->setType('message', PARAM_TEXT);
        $mform->addRule('message', get_string('required'), 'required');

        $this->add_action_buttons(true, get_string('raiseticket', 'local_iiidem_support'));
    }
}
