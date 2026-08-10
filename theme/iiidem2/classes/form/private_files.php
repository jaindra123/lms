<?php
// This file is part of Moodle - http://moodle.org/
//
// Override of core private-files form: block executable/script uploads (CWE-434).

namespace core_user\form;

defined('MOODLE_INTERNAL') || die();

/**
 * Private files manager with extension whitelist + content inspection.
 *
 * Loaded ahead of core via theme_iiidem2 after_config autoloader.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class private_files extends \core_form\dynamic_form {

    /**
     * Allowed types for user private files (non-executable).
     *
     * @return string[]
     */
    protected function allowed_extensions(): array {
        return \theme_iiidem2\upload_security::PRIVATE_FILES_EXTENSIONS;
    }

    public function definition() {
        global $OUTPUT;
        $mform = $this->_form;
        $options = $this->get_options();

        $maxareabytes = $options['areamaxbytes'];
        if ($maxareabytes != FILE_AREA_MAX_BYTES_UNLIMITED) {
            $fileareainfo = file_get_file_area_info($this->get_context_for_dynamic_submission()->id, 'user', 'private');
            if ($fileareainfo['filecount']) {
                $a = (object) [
                    'used' => display_size($fileareainfo['filesize_without_references']),
                    'total' => display_size($maxareabytes, 0),
                ];
                $quotamsg = get_string('quotausage', 'moodle', $a);
                $notification = new \core\output\notification($quotamsg, \core\output\notification::NOTIFY_INFO);
                $mform->addElement('static', 'areabytes', '', $OUTPUT->render($notification));
            }
        }

        $mform->addElement('filemanager', 'files_filemanager', get_string('files'), null, $options);
        if ($link = $this->get_emaillink()) {
            $emaillink = \html_writer::link(new \moodle_url('mailto:' . $link), $link);
            $mform->addElement('static', 'emailaddress', '',
                get_string('emailtoprivatefiles', 'moodle', $emaillink));
        }
        $mform->setType('returnurl', PARAM_LOCALURL);

        if (!$this->optional_param('nosubmit', false, PARAM_BOOL)) {
            $this->add_action_buttons();
        }
    }

    public function validation($data, $files) {
        global $USER;

        $errors = [];
        $draftitemid = (int) ($data['files_filemanager'] ?? 0);
        $options = $this->get_options();
        if (file_is_draft_area_limit_reached($draftitemid, $options['areamaxbytes'])) {
            $errors['files_filemanager'] = get_string('userquotalimit', 'error');
        }

        $rejected = \theme_iiidem2\upload_security::validate_user_draft(
            (int) $USER->id,
            $draftitemid,
            $this->allowed_extensions()
        );
        if ($rejected !== '' && $rejected !== 'empty') {
            $errors['files_filemanager'] = get_string('uploaderror', 'moodle')
                . ' (' . s($rejected) . ')';
        }

        return $errors;
    }

    protected function get_emaillink() {
        global $USER;

        $generator = new \core\message\inbound\address_manager();
        $generator->set_handler('\core\message\inbound\private_files_handler');
        $generator->set_data(-1);
        return $generator->generate($USER->id);
    }

    public function check_access_for_dynamic_submission(): void {
        require_capability('moodle/user:manageownfiles', $this->get_context_for_dynamic_submission());
    }

    protected function get_context_for_dynamic_submission(): \context {
        global $USER;
        return \context_user::instance($USER->id);
    }

    public function get_options(): array {
        global $CFG;

        $maxbytes = $CFG->userquota;
        $maxareabytes = $CFG->userquota;
        if (has_capability('moodle/user:ignoreuserquota', $this->get_context_for_dynamic_submission())) {
            $maxbytes = USER_CAN_IGNORE_FILE_SIZE_LIMITS;
            $maxareabytes = FILE_AREA_MAX_BYTES_UNLIMITED;
        }

        return [
            'subdirs' => 1,
            'maxbytes' => $maxbytes,
            'maxfiles' => -1,
            // Core default is '*'; restrict to non-executable types.
            'accepted_types' => $this->allowed_extensions(),
            'areamaxbytes' => $maxareabytes,
        ];
    }

    public function process_dynamic_submission() {
        global $USER;

        $data = $this->get_data();
        $draftitemid = (int) ($data->files_filemanager ?? 0);
        $rejected = \theme_iiidem2\upload_security::validate_user_draft(
            (int) $USER->id,
            $draftitemid,
            $this->allowed_extensions()
        );
        if ($rejected !== '' && $rejected !== 'empty') {
            throw new \moodle_exception('uploaderror', 'moodle');
        }

        file_postupdate_standard_filemanager($data, 'files',
            $this->get_options(), $this->get_context_for_dynamic_submission(), 'user', 'private', 0);
        return null;
    }

    public function set_data_for_dynamic_submission(): void {
        $data = new \stdClass();
        file_prepare_standard_filemanager($data, 'files', $this->get_options(),
            $this->get_context_for_dynamic_submission(), 'user', 'private', 0);
        $this->set_data($data);
    }

    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/user/files.php');
    }
}
