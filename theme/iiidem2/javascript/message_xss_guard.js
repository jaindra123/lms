/**
 * Messaging / search XSS guard — strip markup from search fields (CDAC message/index.php PoC).
 *
 * Core preview uses textContent after parsing HTML; typing <script> in search does not
 * execute JS. This still blocks angle-bracket probes in the search UI.
 */
(function() {
    'use strict';

    var SELECTORS = [
        '[data-region="view-overview-search-input"]',
        '[data-region="view-contacts-search-input"]',
        '[data-region="search-input"]',
        '#searchform_search',
        'input[name="search"]',
        'input[name="q"]'
    ].join(',');

    function scrub(value) {
        if (typeof value !== 'string') {
            return value;
        }
        return value.replace(/[<>]/g, '').replace(/javascript\s*:/gi, '');
    }

    function onEvent(e) {
        var el = e.target;
        if (!el || !el.matches || !el.matches(SELECTORS)) {
            return;
        }
        var next = scrub(el.value);
        if (next !== el.value) {
            el.value = next;
        }
    }

    document.addEventListener('input', onEvent, true);
    document.addEventListener('change', onEvent, true);
    document.addEventListener('paste', function(e) {
        var el = e.target;
        if (!el || !el.matches || !el.matches(SELECTORS)) {
            return;
        }
        window.setTimeout(function() {
            el.value = scrub(el.value);
        }, 0);
    }, true);
})();
