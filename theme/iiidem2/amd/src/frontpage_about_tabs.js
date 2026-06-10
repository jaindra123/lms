/**
 * About IIIDEM section tabs on the site home page.
 *
 * @module theme_iiidem2/frontpage_about_tabs
 */
define(['jquery', 'theme_iiidem2/bootstrap/tab'], function($) {

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
    }

    return {
        init: init
    };
});
