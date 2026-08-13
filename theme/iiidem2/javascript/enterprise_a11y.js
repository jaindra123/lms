/**
 * Minimal a11y + link hardening helpers for Enterprise 2026 / GIGW.
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

    /**
     * Auditor: unsafe third-party link (target="_blank") — require noopener noreferrer.
     * Covers Moodle core / plugin markup that omitted rel=.
     */
    function hardenBlankTargets(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var links = scope.querySelectorAll('a[target="_blank"], a[target="_BLANK"]');
        for (var i = 0; i < links.length; i++) {
            var a = links[i];
            var rel = (a.getAttribute('rel') || '').toLowerCase();
            var parts = rel.split(/\s+/).filter(Boolean);
            if (parts.indexOf('noopener') === -1) {
                parts.push('noopener');
            }
            if (parts.indexOf('noreferrer') === -1) {
                parts.push('noreferrer');
            }
            a.setAttribute('rel', parts.join(' '));
        }
    }

    function observeBlankTargets() {
        if (typeof MutationObserver === 'undefined') {
            return;
        }
        var observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var nodes = mutations[i].addedNodes;
                for (var j = 0; j < nodes.length; j++) {
                    var node = nodes[j];
                    if (node.nodeType !== 1) {
                        continue;
                    }
                    if (node.matches && node.matches('a[target="_blank"], a[target="_BLANK"]')) {
                        hardenBlankTargets(node.parentNode || document);
                    } else {
                        hardenBlankTargets(node);
                    }
                }
            }
        });
        observer.observe(document.documentElement, {childList: true, subtree: true});
    }

    function boot() {
        resolveMain();
        initSkip();
        hardenBlankTargets(document);
        observeBlankTargets();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
