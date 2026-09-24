/**
 * Messaging / search XSS + SQLi-probe guard (CDAC message drawer / message/index.php).
 *
 * Core preview uses textContent; typing <script> does not execute JS.
 * This still clears angle-bracket and SQL-scanner fragments from search UI.
 */
(function() {
    'use strict';

    var SELECTORS = [
        '[data-region="view-overview-search-input"]',
        '[data-region="view-contacts-search-input"]',
        '[data-region="search-input"]',
        '#searchform_search',
        'input[name="search"]',
        'input[name="q"]',
        '[data-action="search"]',
        'input[data-region="input"]',
        '.simplesearchform input[type="text"]',
        '.page-mycourses input[type="text"]',
        '[data-region="myoverview"] input',
        '#page-my-index input[type="text"]',
        '.message-app input[type="text"]'
    ].join(',');

    function isProbe(value) {
        if (typeof value !== 'string' || value === '') {
            return false;
        }
        if (/[<>]|javascript\s*:/i.test(value)) {
            return true;
        }
        if (/\{base\}|\{select\}|\(base\}|\(select\}|%7bbase%7d/i.test(value)) {
            return true;
        }
        if (/xmlns\s*:/i.test(value) || /[{}\[\]]/.test(value)) {
            return true;
        }
        if (/sleep\s*\(|pg_sleep\s*\(|benchmark\s*\(|waitfor\s+delay/i.test(value)) {
            return true;
        }
        if (/\(\s*select\b|\bselect\s*\*?\s*from\b|\bunion\s+select\b/i.test(value)) {
            return true;
        }
        try {
            if (!/[\p{L}\p{N}]/u.test(value)) {
                return true;
            }
        } catch (e) {
            if (!/[A-Za-z0-9]/.test(value)) {
                return true;
            }
        }
        return false;
    }

    function scrub(value) {
        if (typeof value !== 'string') {
            return value;
        }
        if (isProbe(value)) {
            return '';
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

    function scrubSearchArgs(payload) {
        if (!payload || typeof payload !== 'object') {
            return payload;
        }
        if (Array.isArray(payload)) {
            payload.forEach(scrubSearchArgs);
            return payload;
        }
        Object.keys(payload).forEach(function(key) {
            var val = payload[key];
            if (val && typeof val === 'object') {
                scrubSearchArgs(val);
                return;
            }
            if (typeof val !== 'string') {
                return;
            }
            if (key === 'search' || key === 'q' || key === 'query' || key === 'searchtext'
                    || key === 'keywords' || key === 'searchvalue' || key === 'searchstring') {
                payload[key] = scrub(val);
            }
        });
        return payload;
    }

    function scrubOutgoingBody(body) {
        if (typeof body !== 'string' || body.charAt(0) !== '[') {
            return body;
        }
        try {
            var parsed = JSON.parse(body);
            scrubSearchArgs(parsed);
            return JSON.stringify(parsed);
        } catch (e) {
            return body;
        }
    }

    if (window.XMLHttpRequest && XMLHttpRequest.prototype) {
        var origSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function(body) {
            return origSend.call(this, scrubOutgoingBody(body));
        };
    }

    if (window.fetch) {
        var origFetch = window.fetch;
        window.fetch = function(input, init) {
            init = init || {};
            if (init.body && typeof init.body === 'string') {
                init = Object.assign({}, init, {body: scrubOutgoingBody(init.body)});
            }
            return origFetch.call(this, input, init);
        };
    }
})();
