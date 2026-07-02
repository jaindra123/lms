<?php
namespace local_iiidem_coursecalendar\task;

defined('MOODLE_INTERNAL') || die();

class check_new_events extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('taskchecknewevents', 'local_iiidem_coursecalendar');
    }

    public function execute(): void {
        \local_iiidem_coursecalendar\manager::sync_all_calendars();
    }
}
