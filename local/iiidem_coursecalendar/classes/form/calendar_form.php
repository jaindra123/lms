<?php
namespace local_iiidem_coursecalendar\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class calendar_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;

        $mform->addElement('hidden', 'courseid', $this->_customdata['courseid']);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('text', 'calendarurl', get_string('calendarurl', 'local_iiidem_coursecalendar'), ['size' => 80]);
        $mform->setType('calendarurl', PARAM_URL);
        $mform->addRule('calendarurl', get_string('calendarurlrequired', 'local_iiidem_coursecalendar'), 'required');
        $mform->addHelpButton('calendarurl', 'calendarurl', 'local_iiidem_coursecalendar');

        if (!empty($this->_customdata['currenturl'])) {
            $mform->setDefault('calendarurl', $this->_customdata['currenturl']);
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($errors['calendarurl'])) {
            $normalized = \local_iiidem_coursecalendar\manager::normalize_google_url($data['calendarurl']);
            if ($normalized === null) {
                $errors['calendarurl'] = get_string('invalidcalendarurl', 'local_iiidem_coursecalendar');
            }
        }

        return $errors;
    }
}
