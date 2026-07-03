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
 * Whether the current page is Site administration notifications (/admin/index.php).
 *
 * @param \moodle_page $page
 * @return bool
 */
function local_iiidem_support_is_admin_index_page(\moodle_page $page): bool {
    return (bool) preg_match('#/admin/index\.php$#', $page->url->get_path(false));
}

/**
 * Admin notifications page: use menubar links to /admin/search.php (not in-page hash tabs).
 *
 * @param \moodle_page $page
 * @return void
 */
function local_iiidem_support_prepare_admin_index_secondary_nav(\moodle_page $page): void {
    if (!local_iiidem_support_is_admin_index_page($page)) {
        return;
    }

    $page->set_secondary_navigation(true, false);

    if (!$page->has_secondary_navigation() || !$page->secondarynav->has_children()) {
        return;
    }

    foreach ($page->secondarynav->children as $child) {
        if (empty($child->key)) {
            continue;
        }
        $child->action = new moodle_url('/admin/search.php', [], 'link' . $child->key);
        $child->tab = null;
    }
}

/**
 * Head script for /admin/index.php only — redirects hash tabs to /admin/search.php.
 *
 * @return string
 */
function local_iiidem_support_admin_index_head_script(): string {
    return <<<'HTML'
<script>
(function(){if(!/\/admin\/index\.php$/i.test(location.pathname)){return;}function toSearch(href){if(!href){return null;}if(href.indexOf('/admin/search.php')!==-1&&href.indexOf('#link')!==-1){return href.indexOf('http')===0?href:(location.origin+(href.charAt(0)==='/'?'':'/')+href);}var hash=href.indexOf('#link')===0?href:null;if(!hash&&href.indexOf('#')!==-1){var c=href.substring(href.indexOf('#'));if(c.indexOf('#link')===0){hash=c;}}return hash?(location.origin+'/admin/search.php'+hash):null;}function redirectHash(){var h=location.hash||'';if(/^#link/.test(h)){location.replace(location.origin+'/admin/search.php'+h);}}redirectHash();window.addEventListener('hashchange',redirectHash);document.addEventListener('click',function(e){var link=e.target.closest('.secondary-navigation a[href]');if(!link){return;}var target=toSearch(link.getAttribute('href')||'');if(!target){return;}e.preventDefault();e.stopImmediatePropagation();location.assign(target);},true);document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.secondary-navigation a[href*="#link"]').forEach(function(link){var target=toSearch(link.getAttribute('href')||'');if(target){link.setAttribute('href',target);link.removeAttribute('data-toggle');link.removeAttribute('data-bs-toggle');}});});})();
</script>
HTML;
}

/**
 * Head script for /admin/search.php only — Support tab full URL navigation.
 *
 * Does not touch core #link tabs (General, Users, etc.).
 *
 * @return string
 */
function local_iiidem_support_support_tab_head_script(): string {
    return <<<'HTML'
<script>
(function(){if(!/\/admin\/search\.php$/i.test(location.pathname)){return;}document.addEventListener('click',function(e){var link=e.target.closest('.secondary-navigation a[data-toggle="tab"][href],.secondary-navigation a[data-bs-toggle="tab"][href]');if(!link){return;}var href=link.getAttribute('href')||'';if(!href||href.charAt(0)==='#'||href.indexOf('/admin/search.php')!==-1){return;}e.preventDefault();e.stopImmediatePropagation();location.assign(href);},true);})();
</script>
HTML;
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
