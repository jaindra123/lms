// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Fix Site administration secondary navigation tab clicks.
 *
 * @module     theme_iiidem2/admin_nav_fix
 * @copyright  2026 IIIDEM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const isAdminIndexPage = () => /\/admin\/index\.php$/i.test(window.location.pathname);

const isAdminSearchPage = () => /\/admin\/search\.php$/i.test(window.location.pathname);

const isAdminPath = () => /\/admin(?:\/|$)/.test(window.location.pathname)
    || /\/local\/iiidem_support\/manage\.php$/i.test(window.location.pathname);

const isAdminSettingsHash = (hash) => typeof hash === 'string' && hash.startsWith('#link');

/**
 * Resolve an admin settings tab target on /admin/search.php.
 *
 * Always returns an absolute /admin/search.php URL so it works from plugin pages too.
 *
 * @param {string} href Tab href (hash-only or full URL).
 * @returns {string|null}
 */
export const resolveAdminSearchUrl = (href) => {
    if (!href) {
        return null;
    }

    if (href.includes('/admin/search.php') && href.includes('#link')) {
        return href;
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

    return new URL('/admin/search.php' + hash, window.location.origin).href;
};

const redirectAdminIndexHash = () => {
    if (!isAdminIndexPage()) {
        return;
    }
    const target = resolveAdminSearchUrl(window.location.hash);
    if (target) {
        window.location.replace(target);
    }
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

    // On admin search, #link* tabs switch in-page Bootstrap panes — do not redirect.
    if (isAdminSearchPage() && (href.startsWith('#') || isAdminSettingsHash(href))) {
        return;
    }

    const target = resolveAdminSearchUrl(href);
    if (target) {
        e.preventDefault();
        e.stopImmediatePropagation();
        window.location.assign(target);
        return;
    }

    // Full-page links rendered as Bootstrap tabs (e.g. Payment, Support on /admin/search.php).
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

    redirectAdminIndexHash();
    window.addEventListener('hashchange', redirectAdminIndexHash);
    document.addEventListener('click', handleSecondaryNavClick, true);
};
