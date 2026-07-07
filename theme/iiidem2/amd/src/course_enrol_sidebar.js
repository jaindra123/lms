/**
 * Sticky course enrolment sidebar after the hero banner scrolls away.
 *
 * @module     theme_iiidem2/course_enrol_sidebar
 * @copyright  2026 IIIDEM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var SELECTOR_ASIDE = '.iiidem-course-content-layout__aside';
    var SELECTOR_HERO = '.iiidem-course-hero';
    var SELECTOR_SIDEBAR = '#iiidem-course-fee';
    var STUCK_CLASS = 'iiidem-course-enrol-sidebar--stuck';
    var MIN_WIDTH = 992;

    /**
     * @returns {number}
     */
    function getNavbarHeight() {
        var navbar = document.querySelector('.iiidem-site-header.fixed-top, .navbar.fixed-top');
        return navbar ? navbar.offsetHeight : 60;
    }

    /**
     * Initialise sticky enrolment sidebar behaviour.
     */
    function init() {
        var aside = document.querySelector(SELECTOR_ASIDE);
        var sidebar = document.querySelector(SELECTOR_SIDEBAR);
        var hero = document.querySelector(SELECTOR_HERO);

        if (!aside || !sidebar) {
            return;
        }

        var mode = 'static';

        function clearSticky() {
            mode = 'static';
            aside.classList.remove(STUCK_CLASS);
            aside.style.minHeight = '';
            sidebar.style.position = '';
            sidebar.style.top = '';
            sidebar.style.left = '';
            sidebar.style.width = '';
            sidebar.style.zIndex = '';
        }

        function update() {
            if (window.innerWidth < MIN_WIDTH) {
                clearSticky();
                return;
            }

            var topOffset = getNavbarHeight() + 12;
            var heroBottom = hero ? hero.getBoundingClientRect().bottom : 0;

            if (heroBottom > topOffset) {
                if (mode !== 'static') {
                    clearSticky();
                }
                return;
            }

            var asideRect = aside.getBoundingClientRect();
            var sidebarHeight = sidebar.offsetHeight;
            var maxTop = asideRect.bottom - sidebarHeight;
            var nextTop = Math.min(topOffset, maxTop);

            aside.classList.add(STUCK_CLASS);
            aside.style.minHeight = sidebarHeight + 'px';
            sidebar.style.left = asideRect.left + 'px';
            sidebar.style.width = asideRect.width + 'px';
            sidebar.style.zIndex = '1020';

            if (maxTop <= topOffset) {
                if (mode !== 'fixed') {
                    sidebar.style.position = 'fixed';
                    mode = 'fixed';
                }
                sidebar.style.top = nextTop + 'px';
                return;
            }

            if (mode !== 'fixed') {
                sidebar.style.position = 'fixed';
                mode = 'fixed';
            }
            sidebar.style.top = topOffset + 'px';
        }

        window.addEventListener('scroll', update, {passive: true});
        window.addEventListener('resize', update);
        update();
    }

    return {
        init: init
    };
});
