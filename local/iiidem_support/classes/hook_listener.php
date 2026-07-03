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
     * Prepare admin secondary nav and add Support tab.
     *
     * @param \core\hook\output\before_http_headers $hook
     */
    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $PAGE;
        require_once(__DIR__ . '/../lib.php');
        local_iiidem_support_prepare_admin_index_secondary_nav($PAGE);
        local_iiidem_support_extend_admin_secondary_nav($PAGE);
    }

    /**
     * Inject small, page-specific admin navigation scripts (all themes).
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook
    ): void {
        global $PAGE;
        require_once(__DIR__ . '/../lib.php');

        if (local_iiidem_support_is_admin_index_page($PAGE)) {
            $hook->add_html(local_iiidem_support_admin_index_head_script());
            return;
        }

        if ($PAGE->pagelayout === 'admin' && preg_match('#/admin/search\.php$#', $PAGE->url->get_path(false))) {
            $hook->add_html(local_iiidem_support_support_tab_head_script());
        }
    }
}
