/**
 * Form / search input guard — strip markup probes and mark fields invalid (CDAC #15).
 *
 * Server-side validation remains authoritative; this stops XSS payloads staying
 * in First name / other text boxes (element.matches('form.mform input') never
 * matches an INPUT, so older versions never ran on register fields).
 */
(function() {
    'use strict';

    var DIRECT_SELECTORS = [
        '[data-region="view-overview-search-input"]',
        '[data-region="view-contacts-search-input"]',
        '[data-region="search-input"]',
        '[data-region="input"]',
        '#searchform_search',
        'input[name="search"]',
        'input[name="q"]',
        'input[name="query"]',
        'input[data-region="input"]',
        'input.form-control[data-field-name="keywords"]',
        '[data-iiidem-no-markup="1"]'
    ].join(',');

    var PLAIN_FIELD_NAMES = {
        name: 1,
        subject: 1,
        message: 1,
        email: 1,
        firstname: 1,
        lastname: 1,
        middlename: 1,
        city: 1,
        organization: 1,
        jobprofile: 1,
        jobpostingcountry: 1,
        emb_organization: 1,
        emb_designation: 1,
        emb_country: 1,
        university: 1,
        position: 1,
        specialization: 1,
        instructor_university: 1,
        instructor_course: 1,
        presentcountry: 1
    };

    // Chrome compiles HTML pattern with the unicodeSets `v` flag; `\"` is an invalid escape.
    var SAFE_NOXSS_PATTERN = '[^<>\\x22\\x27]+';
    var SAFE_NOXSS_PATTERN_OPTIONAL = '[^<>\\x22\\x27]*';

    function patternCompiles(pattern) {
        try {
            new RegExp('^(?:' + pattern + ')$', 'v');
            return true;
        } catch (e1) {
            try {
                new RegExp('^(?:' + pattern + ')$', 'u');
                return true;
            } catch (e2) {
                return false;
            }
        }
    }

    function fixBrokenHtmlPatterns(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var nodes = scope.querySelectorAll('input[pattern], textarea[pattern]');
        for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i];
            var p = el.getAttribute('pattern') || '';
            if (p && patternCompiles(p)) {
                continue;
            }
            el.setAttribute('pattern', (p && p.slice(-1) === '*')
                ? SAFE_NOXSS_PATTERN_OPTIONAL
                : SAFE_NOXSS_PATTERN);
        }
    }

    function hasMarkup(value) {
        if (typeof value !== 'string' || value === '') {
            return false;
        }
        if (/[<>]/.test(value)) {
            return true;
        }
        if (/javascript\s*:|data\s*:|vbscript\s*:/i.test(value)) {
            return true;
        }
        if (/(?:^|[^a-z0-9_])(?:alert|prompt|confirm)\s*\(/i.test(value)) {
            return true;
        }
        return false;
    }

    function hasStructured(value) {
        if (typeof value !== 'string' || value === '') {
            return false;
        }
        if (/\{base\}|\{select\}|\(base\}|\(select\}|%7bbase%7d|%28base%7d/i.test(value)) {
            return true;
        }
        if (/[{}\[\]]/.test(value)) {
            return true;
        }
        if (/xmlns\s*:/i.test(value) || /<!\[CDATA\[|<!--|<\?xml|<!DOCTYPE/i.test(value)) {
            return true;
        }
        if (/\s+[a-zA-Z_:][\w:.-]*\s*=\s*["']/.test(value)) {
            return true;
        }
        return false;
    }

    function hasSearchProbe(value) {
        if (typeof value !== 'string' || value === '') {
            return false;
        }
        if (hasStructured(value)) {
            return true;
        }
        if (/\{base\}|\{select\}/i.test(value)) {
            return true;
        }
        if (/sleep\s*\(|pg_sleep\s*\(|benchmark\s*\(|waitfor\s+delay/i.test(value)) {
            return true;
        }
        if (/\(\s*select\b|\bselect\s*\*?\s*from\b|\bunion\s+select\b/i.test(value)) {
            return true;
        }
        if (/information_schema|sys\.tables|into\s+outfile|load_file\s*\(/i.test(value)) {
            return true;
        }
        return false;
    }

    function hasAlnum(value) {
        try {
            return /[\p{L}\p{N}]/u.test(value);
        } catch (e) {
            return /[A-Za-z0-9]/.test(value);
        }
    }

    function scrubSearch(value) {
        if (typeof value !== 'string') {
            return value;
        }
        if (hasMarkup(value) || hasSearchProbe(value)) {
            return '';
        }
        var cleaned = value.replace(/[<>]/g, '').replace(/javascript\s*:/gi, '');
        if (cleaned !== '' && !hasAlnum(cleaned)) {
            return '';
        }
        return cleaned;
    }

    function isTextish(el) {
        var type = (el.type || '').toLowerCase();
        if (type === 'password' || type === 'hidden' || type === 'checkbox'
                || type === 'radio' || type === 'submit' || type === 'button' || type === 'file') {
            return false;
        }
        return el.matches('input[type="text"], input[type="email"], input[type="search"], input:not([type]), textarea');
    }

    function isSearchLike(el) {
        if (!el || !el.matches) {
            return false;
        }
        if (el.matches(
            'input[name="search"], input[name="q"], input[name="query"], [data-region*="search"],' +
            '[data-region="view-overview-search-input"], [data-region="search-input"],' +
            '[data-action="search"], input[data-region="input"],' +
            'input.form-control[data-field-name="keywords"]'
        )) {
            return true;
        }
        if (!el.closest) {
            return false;
        }
        return isTextish(el) && !!(
            el.closest('.simplesearchform')
            || el.closest('.searchbar')
            || el.closest('.dashboard-search')
            || el.closest('.message-app')
            || el.closest('[data-region="myoverview"]')
            || el.closest('[data-region="filter"]')
            || el.closest('.unified-filters')
            || el.closest('#page-user-index')
            || el.closest('#page-my-index')
            || el.closest('.page-mycourses')
            || el.closest('#page-course-management')
            || el.closest('#page-course-index-category')
        );
    }

    function isGuardedField(el) {
        var name = el.name || '';
        return el.getAttribute('data-iiidem-no-markup') === '1'
            || el.matches('textarea')
            || PLAIN_FIELD_NAMES[name]
            || el.type === 'email';
    }

    function isWatched(el) {
        if (!el || el.nodeType !== 1 || !el.matches || !isTextish(el)) {
            return false;
        }
        if (el.matches(DIRECT_SELECTORS) || isGuardedField(el) || isSearchLike(el)) {
            return true;
        }
        if (!el.closest) {
            return false;
        }
        return !!(
            el.closest('form.mform')
            || el.closest('.iiidem-register-form')
            || el.closest('#page-register')
            || el.closest('#page-contact-us')
            || el.closest('#page-contact-us-index')
        );
    }

    function needsAlnum(name) {
        return name === 'subject' || name === 'message' || name === 'name'
            || name === 'city' || name === 'university' || name === 'position'
            || name === 'specialization' || name === 'firstname' || name === 'lastname'
            || name === 'middlename';
    }

    function markValidity(el) {
        if (!el || !isWatched(el)) {
            return;
        }
        var name = el.name || '';
        var bad = hasMarkup(el.value) || hasStructured(el.value) || (isSearchLike(el) && hasSearchProbe(el.value));
        if (!bad && needsAlnum(name) && el.value !== '' && !hasAlnum(el.value)) {
            bad = true;
        }
        if (isSearchLike(el) && el.value !== '' && !hasAlnum(el.value)) {
            bad = true;
        }
        if (bad) {
            el.setCustomValidity(el.getAttribute('title') || 'Invalid characters');
            el.classList.add('is-invalid');
            el.classList.remove('is-valid');
        } else {
            el.setCustomValidity('');
            el.classList.remove('is-invalid');
        }
    }

    function scrubGuarded(el) {
        if (!el || !isWatched(el)) {
            return false;
        }
        if (isSearchLike(el)) {
            var next = scrubSearch(el.value);
            if (next !== el.value) {
                el.value = next;
                return true;
            }
            return false;
        }
        if (!isGuardedField(el) && !(el.closest && el.closest('form.mform'))) {
            return false;
        }
        var name = el.name || '';
        if (hasMarkup(el.value) || hasStructured(el.value) || (needsAlnum(name) && el.value !== '' && !hasAlnum(el.value))) {
            el.value = '';
            return true;
        }
        return false;
    }

    function onInput(e) {
        var el = e.target;
        if (!isWatched(el)) {
            return;
        }
        scrubGuarded(el);
        markValidity(el);
    }

    document.addEventListener('beforeinput', function(e) {
        var el = e.target;
        if (!isWatched(el) || !isGuardedField(el)) {
            return;
        }
        var data = e.data || '';
        if (data && (/[<>]/.test(data) || /javascript\s*:/i.test(data))) {
            e.preventDefault();
        }
    }, true);

    document.addEventListener('keydown', function(e) {
        var el = e.target;
        if (!isWatched(el) || !isGuardedField(el)) {
            return;
        }
        if (e.key === '<' || e.key === '>') {
            e.preventDefault();
        }
    }, true);

    document.addEventListener('input', onInput, true);
    document.addEventListener('change', onInput, true);
    document.addEventListener('paste', function(e) {
        var el = e.target;
        if (!isWatched(el)) {
            return;
        }
        var clip = '';
        if (e.clipboardData) {
            clip = e.clipboardData.getData('text') || '';
        }
        if (clip && (hasMarkup(clip) || hasStructured(clip) || (isSearchLike(el) && hasSearchProbe(clip)))) {
            e.preventDefault();
            el.value = '';
            markValidity(el);
            return;
        }
        window.setTimeout(function() {
            onInput({target: el});
        }, 0);
    }, true);

    document.addEventListener('submit', function(e) {
        var form = e.target;
        if (!form || !form.querySelectorAll) {
            return;
        }
        if (form.id === 'login' || (form.classList && (form.classList.contains('loginform')
                || form.classList.contains('login-form') || form.classList.contains('mfa-verify-form')))) {
            return;
        }
        if (form.querySelector && form.querySelector('input[name="verificationcode"], input[name="password"]')) {
            var action = (form.getAttribute('action') || '');
            if (/login|mfa|forgot.password|change.password/i.test(action) || form.id === 'login') {
                return;
            }
        }
        fixBrokenHtmlPatterns(form);
        var blocked = false;
        form.querySelectorAll('input[type="text"], input[type="email"], textarea').forEach(function(el) {
            if (scrubGuarded(el)) {
                blocked = true;
            }
            markValidity(el);
            if (el.validity && el.validity.customError) {
                blocked = true;
            }
        });
        if (blocked) {
            e.preventDefault();
            e.stopPropagation();
        }
    }, true);

    fixBrokenHtmlPatterns(document);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            fixBrokenHtmlPatterns(document);
        });
    }
})();
