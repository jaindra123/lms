<?php
namespace local_iiidem_coursecalendar\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Schedule a live class and push to Google Calendar with student invites.
 */
class liveclass_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;
        $courseid = (int) $this->_customdata['courseid'];

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);
        $mform->setConstant('courseid', $courseid);

        $mform->addElement('header', 'liveclasshdr', get_string('scheduleliveclass', 'local_iiidem_coursecalendar'));

        $mform->addElement('text', 'summary', get_string('liveclasstitle', 'local_iiidem_coursecalendar'),
            ['size' => 70, 'maxlength' => 255]);
        $mform->setType('summary', PARAM_TEXT);
        $mform->addRule('summary', get_string('required'), 'required', null, 'client');
        $mform->addRule('summary', get_string('required'), 'required', null, 'server');
        $mform->addRule('summary', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addRule('summary', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');

        $mform->addElement('textarea', 'description', get_string('liveclassdescription', 'local_iiidem_coursecalendar'),
            'rows="4" cols="70" maxlength="5000"');
        $mform->setType('description', PARAM_TEXT);
        $mform->addRule('description', get_string('maximumchars', '', 5000), 'maxlength', 5000, 'client');
        $mform->addRule('description', get_string('maximumchars', '', 5000), 'maxlength', 5000, 'server');

        $mform->addElement('date_time_selector', 'starttime', get_string('liveclassstart', 'local_iiidem_coursecalendar'));
        $mform->addRule('starttime', get_string('required'), 'required', null, 'client');

        $durationoptions = [
            30 => '30 ' . get_string('minutes'),
            45 => '45 ' . get_string('minutes'),
            60 => '1 ' . get_string('hour'),
            90 => '1.5 ' . get_string('hours'),
            120 => '2 ' . get_string('hours'),
            180 => '3 ' . get_string('hours'),
        ];
        $mform->addElement('select', 'duration', get_string('liveclassduration', 'local_iiidem_coursecalendar'), $durationoptions);
        $mform->setDefault('duration', 60);

        $mform->addElement('text', 'location', get_string('liveclasslocation', 'local_iiidem_coursecalendar'), ['size' => 70]);
        $mform->setType('location', PARAM_URL);
        $mform->addHelpButton('location', 'liveclasslocation', 'local_iiidem_coursecalendar');

        if (\local_iiidem_coursecalendar\google_calendar_client::is_configured()) {
            $mform->addElement('advcheckbox', 'sendgoogleinvites', get_string('sendgoogleinvites', 'local_iiidem_coursecalendar'));
            $mform->addHelpButton('sendgoogleinvites', 'sendgoogleinvites', 'local_iiidem_coursecalendar');
            $mform->setDefault('sendgoogleinvites', 1);
        } else {
            $mform->addElement('static', 'googlewarning', '', get_string('googleapinotconfiguredteacher', 'local_iiidem_coursecalendar'));
        }

        $this->add_action_buttons(true, get_string('scheduleliveclass', 'local_iiidem_coursecalendar'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $summary = trim((string) ($data['summary'] ?? ''));
        if ($summary === '') {
            $errors['summary'] = get_string('required');
        } else if (\core_text::strlen($summary) > 255) {
            $errors['summary'] = get_string('maximumchars', '', 255);
        }

        $description = trim((string) ($data['description'] ?? ''));
        if (\core_text::strlen($description) > 5000) {
            $errors['description'] = get_string('maximumchars', '', 5000);
        }

        $duration = (int) ($data['duration'] ?? 0);
        if (!in_array($duration, [30, 45, 60, 90, 120, 180], true)) {
            $errors['duration'] = get_string('invalidparameter', 'error');
        }

        if (!empty($data['starttime'])) {
            $start = (int) $data['starttime'];
            if ($start < time() - 300) {
                $errors['starttime'] = get_string('liveclassstartpast', 'local_iiidem_coursecalendar');
            }
        }

        if (!empty($data['location']) && empty($errors['location'])) {
            $loc = trim((string) $data['location']);
            if ($loc !== '' && (\core_text::strlen($loc) > 255 || !filter_var($loc, FILTER_VALIDATE_URL))) {
                $errors['location'] = get_string('invalidurllocation', 'local_iiidem_coursecalendar');
            }
        }

        return $errors;
    }
}
