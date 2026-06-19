<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Plugin library functions.
 *
 * @package local_iiidem_support
 */

/**
 * @param int $userid
 * @return array
 */
function local_iiidem_support_get_dashboard_context(int $userid): array {
    return \local_iiidem_support\manager::get_dashboard_context($userid);
}

/**
 * Load plugin styles on support pages.
 *
 * @return void
 */
function local_iiidem_support_page_requirements(): void {
    global $PAGE;
    $PAGE->requires->css(new moodle_url('/local/iiidem_support/styles.css'));
}

/**
 * Add a Support tickets tab to site administration secondary navigation.
 *
 * @param \moodle_page $page
 * @return void
 */
function local_iiidem_support_extend_admin_secondary_nav(\moodle_page $page): void {
    if ($page->context->contextlevel !== CONTEXT_SYSTEM) {
        return;
    }

    $context = context_system::instance();
    if (!has_capability('local/iiidem_support:manage', $context)) {
        return;
    }

    if ($page->pagelayout !== 'admin' && !str_starts_with($page->pagetype ?? '', 'admin-')) {
        return;
    }

    $secondary = $page->secondarynav;
    if ($secondary->get('local_iiidem_support_manage', \navigation_node::TYPE_SETTING)) {
        return;
    }

    $url = new moodle_url('/local/iiidem_support/manage.php');
    $node = $secondary->add(
        get_string('admintab', 'local_iiidem_support'),
        $url,
        \navigation_node::TYPE_SETTING,
        null,
        'local_iiidem_support_manage'
    );
    // Required when admin secondary nav renders as a tab list (e.g. /admin/search.php).
    $node->tab = $url->out(false);

    if ($page->url->compare($url, URL_MATCH_BASE)) {
        $node->make_active();
        $page->set_secondary_active_tab('local_iiidem_support_manage');
    }
}

/**
 * Common page setup for the admin ticket management UI.
 *
 * @param \moodle_page $page
 * @return void
 */
function local_iiidem_support_admin_page_setup(\moodle_page $page): void {
    $page->set_pagetype('admin-local_iiidem_support');
    $page->set_primary_active_tab('siteadminnode');
    local_iiidem_support_extend_admin_secondary_nav($page);
}
