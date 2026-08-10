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

        $mform->addElement('text', 'subject', get_string('ticketsubject', 'local_iiidem_support'), [
            'size' => 64,
            'maxlength' => 255,
        ]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('required'), 'required', null, 'client');
        $mform->addRule('subject', get_string('required'), 'required', null, 'server');
        $mform->addRule('subject', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addRule('subject', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');

        $mform->addElement('textarea', 'message', get_string('ticketmessage', 'local_iiidem_support'),
            'rows="6" cols="60" maxlength="5000"');
        $mform->setType('message', PARAM_TEXT);
        $mform->addRule('message', get_string('required'), 'required', null, 'client');
        $mform->addRule('message', get_string('required'), 'required', null, 'server');

        $this->add_action_buttons(true, get_string('raiseticket', 'local_iiidem_support'));
    }

    /**
     * Server-side authorization: courseid must be 0 or one of the user's enrolments.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $categories = array_keys(\local_iiidem_support\manager::get_categories());
        $category = (string) ($data['category'] ?? '');
        if ($category === '' || !in_array($category, $categories, true)) {
            $errors['category'] = get_string('invalidparameter', 'error');
        }

        $subject = trim((string) ($data['subject'] ?? ''));
        if ($subject === '') {
            $errors['subject'] = get_string('required');
        } else if (\core_text::strlen($subject) > 255) {
            $errors['subject'] = get_string('maximumchars', '', 255);
        }

        $message = trim((string) ($data['message'] ?? ''));
        if ($message === '') {
            $errors['message'] = get_string('required');
        } else if (\core_text::strlen($message) > 5000) {
            $errors['message'] = get_string('maximumchars', '', 5000);
        }

        $courseid = (int) ($data['courseid'] ?? 0);
        if ($courseid > 0) {
            $allowed = [];
            foreach ($this->_customdata['courses'] ?? [] as $course) {
                $allowed[(int) $course->id] = true;
            }
            if (!isset($allowed[$courseid])) {
                $errors['courseid'] = get_string('invalidcourseid', 'error');
            }
        }

        return $errors;
    }
}
