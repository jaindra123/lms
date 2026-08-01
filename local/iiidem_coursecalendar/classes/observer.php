<?php
namespace local_iiidem_coursecalendar;

defined('MOODLE_INTERNAL') || die();

/**
 * Observers: auto-create Google Calendar invites when live-class activities are saved.
 */
class observer {

    /**
     * @param \core\event\course_module_created $event
     */
    public static function course_module_created(\core\event\course_module_created $event): void {
        self::sync_module($event);
    }

    /**
     * @param \core\event\course_module_updated $event
     */
    public static function course_module_updated(\core\event\course_module_updated $event): void {
        self::sync_module($event);
    }

    /**
     * @param \core\event\course_module_deleted $event
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        $modname = (string) ($event->other['modulename'] ?? '');
        if ($modname !== 'webexactivity' && $modname !== 'url') {
            return;
        }
        try {
            manager::cancel_by_webex_cmid((int) $event->objectid);
        } catch (\Throwable $e) {
            self::report_failure('delete', $e);
        }
    }

    /**
     * @param \core\event\course_module_created|\core\event\course_module_updated $event
     */
    private static function sync_module($event): void {
        $modname = (string) ($event->other['modulename'] ?? '');
        if ($modname !== 'webexactivity' && $modname !== 'url') {
            return;
        }
        try {
            $record = manager::sync_from_course_module((int) $event->objectid, (int) $event->userid, $modname);
            if ($record && !empty($record->googleeventid)) {
                if (!empty($record->invites_sent)) {
                    \core\notification::success(get_string('googleapisyncsuccess', 'local_iiidem_coursecalendar', (object) [
                        'count' => (int) $record->attendeecount,
                        'when' => userdate($record->starttime, get_string('strftimedatetimeshort', 'langconfig')),
                    ]));
                } else {
                    \core\notification::success(get_string('googleapisyncsuccessmoodle', 'local_iiidem_coursecalendar', (object) [
                        'count' => (int) $record->attendeecount,
                        'when' => userdate($record->starttime, get_string('strftimedatetimeshort', 'langconfig')),
                    ]));
                }
            }
        } catch (\Throwable $e) {
            self::report_failure($modname, $e);
        }
    }

    /**
     * Quiet failure: log + short admin/teacher notice. Never dump stack traces to the browser.
     */
    private static function report_failure(string $context, \Throwable $e): void {
        error_log('local_iiidem_coursecalendar (' . $context . '): ' . $e->getMessage());
        // Strip HTML/links noise for on-screen notice.
        $msg = trim(strip_tags($e->getMessage()));
        $msg = preg_replace('#https?://\S+#', '', $msg);
        $msg = trim(preg_replace('/\s+/', ' ', $msg));
        if ($msg === '') {
            $msg = get_string('error');
        }
        \core\notification::error(get_string('googleapisyncfailed', 'local_iiidem_coursecalendar', shorten_text($msg, 220)));
    }
}
