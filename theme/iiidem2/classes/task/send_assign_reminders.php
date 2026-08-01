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
 * Scheduled task: email students ~24 hours before an assignment due date.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_assign_reminders extends \core\task\scheduled_task {

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('tasksendassignreminders', 'theme_iiidem2');
    }

    /**
     * Find assignments due within 24 hours and send reminder emails once.
     */
    public function execute(): void {
        $sent = \theme_iiidem2\assign_notifier::send_due_reminders();
        if ($sent > 0) {
            mtrace('theme_iiidem2: sent assignment due reminders for ' . $sent . ' assignment(s).');
        }
    }
}
