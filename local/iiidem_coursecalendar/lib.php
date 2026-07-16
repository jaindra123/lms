<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Add course calendar link under course administration.
 */
function local_iiidem_coursecalendar_extend_settings_navigation(
    \settings_navigation $settingsnav,
    \context $context
): void {
    if ($context->contextlevel !== CONTEXT_COURSE) {
        return;
    }

    if (!has_capability('local/iiidem_coursecalendar:manage', $context)) {
        return;
    }

    $coursenode = $settingsnav->find('courseadmin', \navigation_node::TYPE_COURSE);
    if (!$coursenode) {
        return;
    }

    $url = new \moodle_url('/local/iiidem_coursecalendar/manage.php', [
        'courseid' => $context->instanceid,
    ]);

    $coursenode->add(
        get_string('manage', 'local_iiidem_coursecalendar'),
        $url,
        \navigation_node::TYPE_SETTING,
        null,
        'iiidem_coursecalendar',
        new \pix_icon('i/calendar', '')
    );

    $scheduleurl = new \moodle_url('/local/iiidem_coursecalendar/schedule_event.php', [
        'courseid' => $context->instanceid,
    ]);
    $coursenode->add(
        get_string('scheduleliveclass', 'local_iiidem_coursecalendar'),
        $scheduleurl,
        \navigation_node::TYPE_SETTING,
        null,
        'iiidem_coursecalendar_schedule',
        new \pix_icon('i/calendar', '')
    );
}

/**
 * Message provider callback.
 */
function local_iiidem_coursecalendar_get_course_display_context(int $courseid): ?array {
    return \local_iiidem_coursecalendar\manager::get_course_display_context($courseid);
}
