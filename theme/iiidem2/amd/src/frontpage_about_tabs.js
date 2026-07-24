/**
 * About IIIDEM section tabs on the site home page.
 *
 * @module theme_iiidem2/frontpage_about_tabs
 */
define(['jquery', 'theme_iiidem2/bootstrap/tab'], function($) {

    /**
     * Keep the active tab pill visible inside the horizontal scroller.
     *
     * @param {HTMLElement} tabLink Active tab link
     */
    function scrollTabIntoView(tabLink) {
        var tabbar = tabLink && tabLink.closest('.iiidem-programme-overview__tabbar');
        if (!tabbar || !tabLink) {
            return;
        }

        var tabbarRect = tabbar.getBoundingClientRect();
        var linkRect = tabLink.getBoundingClientRect();
        var nextLeft = tabbar.scrollLeft + (linkRect.left - tabbarRect.left)
            - (tabbarRect.width / 2) + (linkRect.width / 2);

        if (typeof tabbar.scrollTo === 'function') {
            tabbar.scrollTo({left: Math.max(0, nextLeft), behavior: 'smooth'});
        } else {
            tabbar.scrollLeft = Math.max(0, nextLeft);
        }
    }

    /**
     * Initialise Bootstrap 4 tabs in the About section.
     */
    function init() {
        var $tablist = $('#iiidemTab');
        if (!$tablist.length) {
            return;
        }

        $tablist.on('click', '[data-toggle="tab"]', function(e) {
            e.preventDefault();
            $(this).tab('show');
        });

        $tablist.on('shown.bs.tab', 'a[data-toggle="tab"]', function(e) {
            scrollTabIntoView(e.target);
        });

        var active = $tablist.find('.nav-link.active').get(0);
        if (active) {
            scrollTabIntoView(active);
        }
    }

    return {
        init: init
    };
});
