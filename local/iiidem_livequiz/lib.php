<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/classes/manager.php');

use local_iiidem_livequiz\manager;

/**
 * Add the Live Quiz Manager to course navigation for teachers and admins.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_iiidem_livequiz_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context_course $context
): void {
    if (!manager::user_can_manage((int) $course->id)) {
        return;
    }

    $url = new moodle_url('/local/iiidem_livequiz/manage.php', [
        'courseid' => $course->id,
    ]);
    $navigation->add(
        get_string('manage', 'local_iiidem_livequiz'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'local_iiidem_livequiz_manage'
    );
}

/**
 * Whether the live quiz plugin is installed.
 *
 * @return bool
 */
function local_iiidem_livequiz_is_available(): bool {
    global $DB;
    $manager = \core_plugin_manager::instance();
    $plugin = $manager->get_plugin_info('local_iiidem_livequiz');
    if (!$plugin || $plugin->versiondb === null) {
        return false;
    }
    return $DB->get_manager()->table_exists('local_iiidem_livequiz_session');
}

/**
 * @param int $cmid
 * @param int $courseid
 * @return array
 */
function local_iiidem_livequiz_get_live_page_context(int $cmid, int $courseid): array {
    if (!local_iiidem_livequiz_is_available()) {
        return ['haslivequiz' => false];
    }
    return manager::get_live_page_shell_context($cmid, $courseid);
}

/**
 * Closed live-session results for the student dashboard.
 *
 * @param int $userid
 * @return array
 */
function local_iiidem_livequiz_get_dashboard_context(int $userid): array {
    if (!local_iiidem_livequiz_is_available()) {
        return [
            'haslivequizresults' => false,
            'livequizresults' => [],
        ];
    }

    $results = manager::get_student_closed_results($userid);
    return [
        'haslivequizresults' => !empty($results),
        'livequizresults' => $results,
    ];
}
