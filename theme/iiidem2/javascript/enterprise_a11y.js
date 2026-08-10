/**
 * Minimal a11y helpers for Enterprise 2026 / GIGW skip-to-main.
 * Does not alter LMS business logic.
 */
(function () {
    'use strict';

    function resolveMain() {
        var main = document.getElementById('main-content')
            || document.querySelector('main[tabindex], main')
            || document.getElementById('region-main')
            || document.querySelector('[role="main"]');
        if (main && !main.hasAttribute('tabindex')) {
            main.setAttribute('tabindex', '-1');
        }
        return main;
    }

    function initSkip() {
        var skip = document.querySelector('[data-eci-skip]');
        if (!skip) {
            return;
        }
        skip.addEventListener('click', function (e) {
            e.preventDefault();
            var main = resolveMain();
            if (!main) {
                return;
            }
            try {
                main.focus({preventScroll: false});
            } catch (err) {
                main.focus();
            }
            if (typeof main.scrollIntoView === 'function') {
                main.scrollIntoView({behavior: 'smooth', block: 'start'});
            }
        });
    }

    function boot() {
        resolveMain();
        initSkip();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
