// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Fix Site administration secondary navigation tab clicks.
 *
 * Opens /admin/search.php#link* for settings tabs so the correct section
 * content and active tab state are shown. Payment stays on category.php.
 *
 * @module     theme_iiidem2/admin_nav_fix
 * @copyright  2026 IIIDEM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';

const isAdminIndexPage = () => /\/admin\/index\.php$/i.test(window.location.pathname);

const isAdminSearchPage = () => /\/admin\/search\.php$/i.test(window.location.pathname);

const isAdminCategoryPage = () => /\/admin\/category\.php$/i.test(window.location.pathname);

const isAdminPath = () => /\/admin(?:\/|$)/.test(window.location.pathname)
    || /\/local\/iiidem_support\/manage\.php$/i.test(window.location.pathname);

const isAdminSettingsHash = (hash) => typeof hash === 'string' && hash.startsWith('#link');

const PAYMENT_URL = () => new URL('/admin/category.php?category=payment', window.location.origin).href;

/**
 * Map Moodle admin category names / tab keys to search.php hash keys.
 * (Plugins category id is "modules", tab key is "plugins".)
 * Payment is intentionally omitted — it is a full category page.
 */
const CATEGORY_TO_LINK = {
    users: 'users',
    courses: 'courses',
    grades: 'grades',
    badges: 'badges',
    competencies: 'competencies',
    licenses: 'licenses',
    location: 'location',
    language: 'language',
    modules: 'plugins',
    plugins: 'plugins',
    security: 'security',
    appearance: 'appearance',
    frontpage: 'frontpage',
    server: 'server',
    mnet: 'mnet',
    reports: 'reports',
    development: 'development',
};

/**
 * @param {string} key
 * @returns {string|null}
 */
const linkHashForKey = (key) => {
    if (!key) {
        return null;
    }
    if (key === 'payment') {
        return null;
    }
    const mapped = CATEGORY_TO_LINK[key];
    return mapped ? '#link' + mapped : null;
};

/**
 * Resolve Payment destinations (never search.php#linkpayment).
 *
 * @param {string} href
 * @returns {string|null}
 */
const resolvePaymentUrl = (href) => {
    if (!href) {
        return null;
    }
    if (href.includes('#linkpayment') || href === '#linkpayment') {
        return PAYMENT_URL();
    }
    try {
        const url = new URL(href, window.location.origin);
        if (/\/admin\/category\.php$/i.test(url.pathname)
                && (url.searchParams.get('category') || '') === 'payment') {
            return url.href;
        }
    } catch (e) {
        // Ignore.
    }
    return null;
};

/**
 * Resolve an admin settings tab target on /admin/search.php.
 *
 * @param {string} href Tab href (hash-only or full URL).
 * @returns {string|null}
 */
export const resolveAdminSearchUrl = (href) => {
    if (!href) {
        return null;
    }

    // Payment is a real category page, not an in-page search tab.
    const payment = resolvePaymentUrl(href);
    if (payment) {
        return payment;
    }

    // Already a correct search tab URL (but not payment).
    if (href.includes('/admin/search.php') && href.includes('#link')) {
        return href.indexOf('http') === 0
            ? href
            : new URL(href.charAt(0) === '/' ? href : '/' + href, window.location.origin).href;
    }

    // Legacy / mistaken category.php links → search.php tabs (except payment).
    try {
        const url = new URL(href, window.location.origin);
        if (/\/admin\/category\.php$/i.test(url.pathname)) {
            const category = url.searchParams.get('category') || '';
            if (category === 'payment') {
                return url.href;
            }
            const hash = linkHashForKey(category);
            if (hash) {
                return new URL('/admin/search.php' + hash, window.location.origin).href;
            }
        }
    } catch (e) {
        // Ignore invalid URLs.
    }

    let hash = null;
    if (isAdminSettingsHash(href)) {
        hash = href;
    } else {
        const hashIndex = href.indexOf('#');
        if (hashIndex !== -1) {
            const candidate = href.substring(hashIndex);
            if (isAdminSettingsHash(candidate)) {
                hash = candidate;
            }
        }
    }

    if (!hash) {
        return null;
    }

    const key = hash.substring(5);
    if (key === 'payment') {
        return PAYMENT_URL();
    }

    const normalised = linkHashForKey(key) || hash;
    return new URL('/admin/search.php' + normalised, window.location.origin).href;
};

/**
 * Whether a secondary-nav href should switch in-page admin settings tabs.
 *
 * @param {string} href Tab href (hash-only or full URL).
 * @returns {boolean}
 */
const isInPageAdminSettingsTab = (href) => {
    if (!href) {
        return false;
    }
    // Payment is never an in-page search pane.
    if (resolvePaymentUrl(href)) {
        return false;
    }
    if (href.startsWith('#') && isAdminSettingsHash(href)) {
        return true;
    }
    const target = resolveAdminSearchUrl(href);
    if (!target) {
        return false;
    }
    return isAdminSettingsHash(new URL(target, window.location.origin).hash);
};

const redirectAdminIndexHash = () => {
    if (!isAdminIndexPage() && !isAdminSearchPage()) {
        return;
    }
    if ((window.location.hash || '') === '#linkpayment') {
        window.location.replace(PAYMENT_URL());
        return;
    }
    if (!isAdminIndexPage()) {
        return;
    }
    const target = resolveAdminSearchUrl(window.location.hash);
    if (target) {
        window.location.replace(target);
    }
};

/**
 * Escape leftover top-level category landings back to search tabs.
 * Keep Payment on /admin/category.php?category=payment.
 */
const redirectTopLevelCategoryPage = () => {
    if (!isAdminCategoryPage()) {
        return;
    }
    const params = new URLSearchParams(window.location.search);
    const category = params.get('category') || '';
    if (category === 'payment') {
        return;
    }
    const hash = linkHashForKey(category);
    if (!hash || !Object.prototype.hasOwnProperty.call(CATEGORY_TO_LINK, category)) {
        return;
    }
    window.location.replace(new URL('/admin/search.php' + hash, window.location.origin).href);
};

const handleSecondaryNavClick = (e) => {
    const link = e.target.closest('.secondary-navigation a.nav-link[href]');
    if (!link) {
        return;
    }

    const href = link.getAttribute('href') || '';
    if (!href || href === '#') {
        return;
    }

    // Payment always navigates to the payment category page.
    const payment = resolvePaymentUrl(href);
    if (payment) {
        e.preventDefault();
        e.stopImmediatePropagation();
        window.location.assign(payment);
        return;
    }

    // On admin search, #link* tabs switch in-page Bootstrap panes.
    if (isAdminSearchPage() && isInPageAdminSettingsTab(href)) {
        e.preventDefault();
        e.stopImmediatePropagation();
        $(link).tab('show');
        const target = resolveAdminSearchUrl(href);
        if (target) {
            const hash = new URL(target, window.location.origin).hash;
            if (hash && window.location.hash !== hash) {
                history.replaceState(null, '', hash);
            }
        }
        return;
    }

    const target = resolveAdminSearchUrl(href);
    if (target) {
        e.preventDefault();
        e.stopImmediatePropagation();
        window.location.assign(target);
        return;
    }

    // Full-page links rendered as Bootstrap tabs (e.g. Support).
    const istab = link.getAttribute('data-toggle') === 'tab'
        || link.getAttribute('data-bs-toggle') === 'tab';
    if (!href.startsWith('#') && istab) {
        e.preventDefault();
        e.stopImmediatePropagation();
        window.location.assign(href);
    }
};

/**
 * Initialise admin navigation fixes.
 */
export const init = () => {
    if (!isAdminPath()) {
        return;
    }

    redirectTopLevelCategoryPage();
    redirectAdminIndexHash();
    window.addEventListener('hashchange', redirectAdminIndexHash);
    document.addEventListener('click', handleSecondaryNavClick, true);
};
