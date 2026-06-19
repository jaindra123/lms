<?php
namespace local_iiidem_support;

defined('MOODLE_INTERNAL') || die();

/**
 * Plugin hook callbacks.
 *
 * @package   local_iiidem_support
 */
class hook_listener {

    /**
     * Add Support tab to site administration secondary navigation.
     *
     * @param \core\hook\output\before_http_headers $hook
     */
    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $PAGE;
        require_once(__DIR__ . '/../lib.php');
        \local_iiidem_support_extend_admin_secondary_nav($PAGE);
    }
}
