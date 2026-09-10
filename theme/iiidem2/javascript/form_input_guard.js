/**
 * Form / search input guard — strip markup probes and mark fields invalid (CDAC #15).
 *
 * Server-side validation remains authoritative; this stops green "valid" UI on XSS payloads.
 */
(function() {
    'use strict';

    var SEARCH_SELECTORS = [
        '[data-region="view-overview-search-input"]',
        '[data-region="view-contacts-search-input"]',
        '[data-region="search-input"]',
        '#searchform_search',
        'input[name="search"]',
        'input[name="q"]',
        'input[name="query"]',
        '.dashboard-search input',
        'form.mform input[type="text"]',
        'form.mform textarea',
        '#page-contact-us input[type="text"]',
        '#page-contact-us textarea',
        '#page-register input[type="text"]',
        '#page-register textarea',
        // Participants / unified filters (CDAC keyword XSS chip).
        '#page-user-index input[type="text"]',
        '#page-user-index textarea',
        '[data-region="filter"] input[type="text"]',
        '.unified-filters input[type="text"]',
        'input.form-control[data-field-name="keywords"]',
        // Course category / management search (Instance 2).
        '#page-course-management input[name="search"]',
        '#page-course-index-category input[name="search"]',
        '.searchbar input[type="text"]',
        'form[action*="management.php"] input[name="search"]',
        'form[action*="search.php"] input[name="search"]'
    ].join(',');

    var PLAIN_FIELD_NAMES = {
        name: 1,
        subject: 1,
        message: 1,
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
        // Reject XSS / punctuation-only probes entirely.
        if (hasMarkup(value)) {
            return '';
        }
        var cleaned = value.replace(/[<>]/g, '').replace(/javascript\s*:/gi, '');
        if (cleaned !== '' && !hasAlnum(cleaned)) {
            return '';
        }
        return cleaned;
    }

    function isSearchLike(el) {
        return el.matches(
            'input[name="search"], input[name="q"], input[name="query"], [data-region*="search"],' +
            '#page-user-index input[type="text"], [data-region="filter"] input[type="text"],' +
            '.unified-filters input[type="text"], input.form-control[data-field-name="keywords"],' +
            '.searchbar input[type="text"]'
        );
    }

    function markValidity(el) {
        if (!el || el.type === 'password' || el.type === 'email' || el.type === 'hidden') {
            return;
        }
        var name = el.name || '';
        var guarded = el.getAttribute('data-iiidem-no-markup') === '1'
            || el.matches('textarea')
            || PLAIN_FIELD_NAMES[name];
        if (!guarded) {
            return;
        }
        var bad = hasMarkup(el.value);
        // Contact subject / message: punctuation-only (@#$$$) is invalid.
        if (!bad && (name === 'subject' || name === 'message' || name === 'name')
                && el.value !== '' && !hasAlnum(el.value)) {
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

    function onInput(e) {
        var el = e.target;
        if (!el || !el.matches || !el.matches(SEARCH_SELECTORS)) {
            return;
        }
        if (isSearchLike(el)) {
            var next = scrubSearch(el.value);
            if (next !== el.value) {
                el.value = next;
            }
        }
        markValidity(el);
    }

    document.addEventListener('input', onInput, true);
    document.addEventListener('change', onInput, true);
    document.addEventListener('paste', function(e) {
        var el = e.target;
        if (!el || !el.matches || !el.matches(SEARCH_SELECTORS)) {
            return;
        }
        window.setTimeout(function() {
            onInput({target: el});
        }, 0);
    }, true);
})();
