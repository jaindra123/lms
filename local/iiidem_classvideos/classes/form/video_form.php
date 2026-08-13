<?php
namespace local_iiidem_classvideos\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Teacher / admin: add a class video (file and/or URL).
 */
class video_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;
        $courseoptions = $this->_customdata['courseoptions'] ?? [];
        $maxbytes = (int) ($this->_customdata['maxbytes'] ?? \local_iiidem_classvideos\manager::MAX_UPLOAD_BYTES);

        $mform->addElement('select', 'courseid', get_string('course'), $courseoptions);
        $mform->addRule('courseid', null, 'required', null, 'client');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('text', 'title', get_string('videotitle', 'local_iiidem_classvideos'), [
            'size' => 64,
            'maxlength' => 255,
        ]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('textarea', 'description', get_string('videodesc', 'local_iiidem_classvideos'), [
            'rows' => 3,
            'cols' => 60,
        ]);
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('date_selector', 'sessiondate', get_string('sessiondate', 'local_iiidem_classvideos'), [
            'optional' => true,
        ]);

        $mform->addElement('select', 'accesstype', get_string('accesstype', 'local_iiidem_classvideos'), [
            \local_iiidem_classvideos\manager::ACCESS_REQUEST => get_string('accesstype_request', 'local_iiidem_classvideos'),
            \local_iiidem_classvideos\manager::ACCESS_PUBLIC => get_string('accesstype_public', 'local_iiidem_classvideos'),
        ]);
        $mform->setType('accesstype', PARAM_ALPHA);
        $mform->setDefault('accesstype', \local_iiidem_classvideos\manager::ACCESS_REQUEST);
        $mform->addHelpButton('accesstype', 'accesstype', 'local_iiidem_classvideos');

        $mform->addElement('static', 'uploadtip', '',
            \html_writer::div(get_string('uploadtip', 'local_iiidem_classvideos'), 'alert alert-info mb-0'));

        $mform->addElement('url', 'externalurl', get_string('externalurl', 'local_iiidem_classvideos'), [
            'size' => 64,
        ], ['usefilepicker' => false]);
        $mform->setType('externalurl', PARAM_URL);
        $mform->addHelpButton('externalurl', 'externalurl', 'local_iiidem_classvideos');

        $mform->addElement('filemanager', 'videofile', get_string('videofile', 'local_iiidem_classvideos'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'maxbytes' => $maxbytes,
            'accepted_types' => \local_iiidem_classvideos\manager::VIDEO_EXTS,
        ]);
        $mform->addHelpButton('videofile', 'videofile', 'local_iiidem_classvideos');

        $this->add_action_buttons(true, get_string('savevideo', 'local_iiidem_classvideos'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $url = trim((string) ($data['externalurl'] ?? ''));
        $draft = (int) ($data['videofile'] ?? 0);

        $hasfile = false;
        if ($draft > 0) {
            $usercontext = \context_user::instance($GLOBALS['USER']->id);
            $fs = get_file_storage();
            $stored = $fs->get_area_files($usercontext->id, 'user', 'draft', $draft, 'id', false);
            $hasfile = !empty($stored);
        }

        if ($url === '' && !$hasfile) {
            $errors['externalurl'] = get_string('needfileorurl', 'local_iiidem_classvideos');
            $errors['videofile'] = get_string('needfileorurl', 'local_iiidem_classvideos');
        }
        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
            $errors['externalurl'] = get_string('invalurl', 'local_iiidem_classvideos');
        }
        return $errors;
    }
}
