<?php
defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");

class faq_form extends moodleform {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('text', 'courseid', 'Course ID');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('text', 'question', 'Question');
        $mform->setType('question', PARAM_TEXT);
        $mform->addRule('question', 'Required', 'required');

        $mform->addElement('textarea', 'answer', 'Answer');
        $mform->setType('answer', PARAM_RAW);
        $mform->addRule('answer', 'Required', 'required');

        $this->add_action_buttons();
    }
}