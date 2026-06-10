/**
 * Front page announcements drawer — arrow tab opens slide-in panel.
 *
 * @module theme_iiidem2/frontpage_announcements
 */
define([], function() {

    var STORAGE_KEY = 'theme_iiidem2_fp_announcements_open';

    /**
     * @param {string} value
     */
    function saveOpenState(value) {
        try {
            localStorage.setItem(STORAGE_KEY, value);
        } catch (err) {
            // Ignore storage errors.
        }
    }

    /**
     * @param {HTMLElement} drawer
     * @param {boolean} open
     */
    function setOpen(drawer, open) {
        drawer.classList.toggle('is-open', open);
        drawer.classList.toggle('is-closed', !open);

        var trigger = drawer.querySelector('[data-action="open-announcements"]');
        var panel = drawer.querySelector('.iiidem-announcements-drawer__panel');

        if (trigger) {
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        if (panel) {
            panel.setAttribute('aria-hidden', open ? 'false' : 'true');
        }
    }

    /**
     * Initialise the announcements drawer toggle.
     */
    function init() {
        var drawer = document.getElementById('iiidemAnnouncementsDrawer');
        if (!drawer) {
            return;
        }

        var saved = null;
        try {
            saved = localStorage.getItem(STORAGE_KEY);
        } catch (e) {
            saved = null;
        }

        var isOpen = saved === '1';
        setOpen(drawer, isOpen);

        drawer.addEventListener('click', function(e) {
            var closeBtn = e.target.closest('[data-action="close-announcements"]');
            var openBtn = e.target.closest('[data-action="open-announcements"]');

            if (closeBtn) {
                e.preventDefault();
                setOpen(drawer, false);
                saveOpenState('0');
                return;
            }

            if (openBtn) {
                e.preventDefault();
                setOpen(drawer, true);
                saveOpenState('1');
            }
        });
    }

    return {
        init: init
    };
});
