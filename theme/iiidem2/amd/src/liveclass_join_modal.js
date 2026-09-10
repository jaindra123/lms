/**
 * Open Webex/live-class join link in a centered Moodle modal.
 *
 * Works on live-class pages and course curriculum preview panels.
 *
 * @module theme_iiidem2/liveclass_join_modal
 */
define([], function() {
    const SELECTOR = {
        open: '[data-action="open-liveclass-modal"]',
        close: '[data-action="close-liveclass-modal"]',
        openWindow: '[data-action="open-liveclass-window"]',
        modal: '#iiidem-liveclass-modal',
        frame: '.iiidem-liveclass-modal__frame',
        fallback: '.iiidem-liveclass-modal__fallback',
    };

    let activeJoinUrl = '';
    let fallbackTimer = null;
    let lastFocus = null;
    let bound = false;

    /**
     * @param {string} url
     */
    const openCenteredWindow = (url) => {
        const width = Math.min(1200, window.screen.availWidth - 40);
        const height = Math.min(800, window.screen.availHeight - 80);
        const left = Math.max(0, Math.round((window.screen.availWidth - width) / 2));
        const top = Math.max(0, Math.round((window.screen.availHeight - height) / 2));
        const features = [
            `width=${width}`,
            `height=${height}`,
            `left=${left}`,
            `top=${top}`,
            'resizable=yes',
            'scrollbars=yes',
            'noopener',
            'noreferrer',
        ].join(',');

        window.open(url, 'iiidemLiveClass', features);
    };

    /**
     * @param {HTMLElement} modal
     * @param {boolean} show
     */
    const toggleFallback = (modal, show) => {
        const fallback = modal.querySelector(SELECTOR.fallback);
        if (!fallback) {
            return;
        }
        fallback.hidden = !show;
    };

    /**
     * @param {string} url
     */
    const openModal = (url) => {
        const modal = document.querySelector(SELECTOR.modal);
        const frame = modal ? modal.querySelector(SELECTOR.frame) : null;
        if (!modal || !frame || !url) {
            if (url) {
                openCenteredWindow(url);
            }
            return;
        }

        lastFocus = document.activeElement;
        activeJoinUrl = url;
        toggleFallback(modal, false);
        frame.removeAttribute('src');
        frame.src = url;
        modal.hidden = false;
        document.body.classList.add('iiidem-liveclass-modal-open');

        const closeBtn = modal.querySelector(SELECTOR.close);
        if (closeBtn) {
            closeBtn.focus();
        }

        if (fallbackTimer) {
            window.clearTimeout(fallbackTimer);
        }
        fallbackTimer = window.setTimeout(() => {
            toggleFallback(modal, true);
        }, 1800);
    };

    const closeModal = () => {
        const modal = document.querySelector(SELECTOR.modal);
        const frame = modal ? modal.querySelector(SELECTOR.frame) : null;
        if (!modal) {
            return;
        }

        if (fallbackTimer) {
            window.clearTimeout(fallbackTimer);
            fallbackTimer = null;
        }

        modal.hidden = true;
        document.body.classList.remove('iiidem-liveclass-modal-open');
        if (frame) {
            frame.removeAttribute('src');
        }
        toggleFallback(modal, false);
        activeJoinUrl = '';

        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
    };

    /**
     * Tell Moodle this user clicked Join (for attendance when Webex shows guest emails).
     *
     * @param {string|null} cmid
     */
    const recordJoinClick = (cmid) => {
        if (!cmid || !window.M || !M.cfg) {
            return;
        }
        const sesskey = M.cfg.sesskey || '';
        const wwwroot = M.cfg.wwwroot || '';
        if (!sesskey || !wwwroot) {
            return;
        }
        // CDAC #17: sesskey in POST body, not query string.
        const url = wwwroot + '/local/iiidem_webexattendance/join_click.php';
        const body = new URLSearchParams();
        body.set('cmid', cmid);
        body.set('sesskey', sesskey);
        try {
            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body.toString(),
            });
        } catch (e) {
            // Non-fatal: Webex still opens.
        }
    };

    /**
     * Initialise join modal handlers (document-level, safe to call once).
     */
    const init = () => {
        if (bound) {
            return;
        }
        bound = true;

        document.addEventListener('click', (e) => {
            const openBtn = e.target.closest(SELECTOR.open);
            if (openBtn) {
                e.preventDefault();
                recordJoinClick(openBtn.getAttribute('data-cmid'));
                openModal(openBtn.getAttribute('data-join-url') || '');
                return;
            }

            const closeBtn = e.target.closest(SELECTOR.close);
            if (closeBtn) {
                e.preventDefault();
                closeModal();
                return;
            }

            const winBtn = e.target.closest(SELECTOR.openWindow);
            if (winBtn) {
                e.preventDefault();
                if (activeJoinUrl) {
                    openCenteredWindow(activeJoinUrl);
                }
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const modal = document.querySelector(`${SELECTOR.modal}:not([hidden])`);
                if (modal) {
                    closeModal();
                }
            }
        });
    };

    return {
        init: init,
    };
});
