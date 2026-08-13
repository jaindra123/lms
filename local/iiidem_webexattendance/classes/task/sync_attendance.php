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
        if (!\local_iiidem_webexattendance\attendance_sync::is_enabled()
                && !\local_iiidem_webexattendance\attendance_sync::recordings_enabled()) {
            return;
        }
        if (!\local_iiidem_webexattendance\oauth::is_connected()) {
            mtrace('Webex attendance: not connected (authorize OAuth first).');
            return;
        }
        if (\local_iiidem_webexattendance\attendance_sync::is_enabled()) {
            $count = \local_iiidem_webexattendance\attendance_sync::sync_due_meetings(25);
            mtrace('Webex attendance: synced ' . $count . ' meeting(s).');
        }
        if (\local_iiidem_webexattendance\attendance_sync::recordings_enabled()) {
            $rec = \local_iiidem_webexattendance\attendance_sync::import_due_recordings(25);
            mtrace('Webex recordings: imported ' . $rec . ' class video(s).');
        }
    }
}
