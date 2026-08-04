<?php
namespace local_iiidem_webexattendance;

defined('MOODLE_INTERNAL') || die();

/**
 * Create attendance sessions when Webex live classes are scheduled.
 */
class observer {

    public static function course_module_created(\core\event\course_module_created $event): void {
        self::handle($event);
    }

    public static function course_module_updated(\core\event\course_module_updated $event): void {
        self::handle($event);
    }

    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        $modname = $event->other['modulename'] ?? '';
        if ($modname !== 'webexactivity' && $modname !== 'url') {
            return;
        }
        attendance_sync::delete_for_cm((int) $event->objectid);
    }

    protected static function handle(\core\event\base $event): void {
        $modname = $event->other['modulename'] ?? '';
        if ($modname !== 'webexactivity' && $modname !== 'url') {
            return;
        }
        try {
            attendance_sync::sync_from_cm((int) $event->objectid);
        } catch (\Throwable $e) {
            debugging('local_iiidem_webexattendance observer: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
