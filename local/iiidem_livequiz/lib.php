<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/classes/manager.php');

use local_iiidem_livequiz\manager;

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
