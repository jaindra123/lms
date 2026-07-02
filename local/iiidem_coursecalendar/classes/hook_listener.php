<?php
namespace local_iiidem_coursecalendar;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../lib.php');

class hook_listener {

    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $PAGE;

        if (!$PAGE->context || $PAGE->context->contextlevel !== CONTEXT_COURSE) {
            return;
        }

        $PAGE->requires->css('/local/iiidem_coursecalendar/styles.css');
    }
}
