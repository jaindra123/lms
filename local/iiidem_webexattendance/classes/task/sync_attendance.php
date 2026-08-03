<?php
namespace local_iiidem_webexattendance\task;

defined('MOODLE_INTERNAL') || die();

/**
 * After live classes end, fetch Webex participants and mark attendance.
 */
class sync_attendance extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('tasksync', 'local_iiidem_webexattendance');
    }

    public function execute(): void {
        if (!\local_iiidem_webexattendance\attendance_sync::is_enabled()) {
            return;
        }
        if (!\local_iiidem_webexattendance\oauth::is_connected()) {
            mtrace('Webex attendance: not connected (authorize OAuth first).');
            return;
        }
        $count = \local_iiidem_webexattendance\attendance_sync::sync_due_meetings(25);
        mtrace('Webex attendance: synced ' . $count . ' meeting(s).');
    }
}
