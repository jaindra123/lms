<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace theme_iiidem2\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Ad-hoc: welcome / admin emails after registration (must not block account create).
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_registration_emails extends \core\task\adhoc_task {

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('tasksendregistrationemails', 'theme_iiidem2');
    }

    /**
     * Email the new user and admins. SMTP delays must not hold the register POST.
     */
    public function execute(): void {
        global $CFG;
        require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

        $data = $this->get_custom_data();
        $userid = (int) ($data->userid ?? 0);
        if ($userid <= 0) {
            return;
        }
        $user = \core_user::get_user($userid);
        if (!$user || empty($user->id)) {
            return;
        }
        try {
            theme_iiidem2_send_registration_emails($user, null);
        } catch (\Throwable $e) {
            debugging('theme_iiidem2 send_registration_emails: ' . $e->getMessage(), DEBUG_NORMAL);
        }
    }
}
