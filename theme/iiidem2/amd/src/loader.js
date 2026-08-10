// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Template renderer for Moodle. Load and render Moodle templates with Mustache.
 *
 * @module     theme_iiidem2/loader
 * @copyright  2015 Damyon Wiese <damyon@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      2.9
 */

import $ from 'jquery';
import * as Aria from './aria';
import {init as initAdminNavFix, resolveAdminSearchUrl} from './admin_nav_fix';
// Side-effect imports: register Bootstrap 4 data-api handlers (data-toggle, not data-bs-*).
// Use bs4popover (not bootstrap/popover) — live hosts block/empty that filename.
import './bootstrap/collapse';
import './bootstrap/tab';
import './bootstrap/carousel';
import './bootstrap/modal';
import './bootstrap/dropdown';
import './bootstrap/tooltip';
import './bootstrap/bs4popover';
import Pending from 'core/pending';
import {DefaultWhitelist} from './bootstrap/tools/sanitizer';
import setupBootstrapPendingChecks from './pending';

/**
 * Rember the last visited tabs.
 */
const isAdminIndexPage = () => /\/admin\/index\.php$/i.test(window.location.pathname);

const rememberTabs = () => {
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        var hash = $(e.target).attr('href');
        if (!hash || !hash.startsWith('#')) {
            return;
        }
        // Admin notifications page: do not swap hash in-place — go to settings search UI.
        if (hash.startsWith('#link') && isAdminIndexPage()) {
            window.location.assign(resolveAdminSearchUrl(hash));
            return;
        }
        if (history.replaceState) {
            history.replaceState(null, null, hash);
        } else {
            location.hash = hash;
        }
    });
    const activateTabFromHash = () => {
        const hash = window.location.hash;
        if (!hash || !hash.startsWith('#')) {
            return;
        }
        if (hash.startsWith('#link') && isAdminIndexPage()) {
            window.location.replace(resolveAdminSearchUrl(hash));
            return;
        }
        // Do not simulate in-page tab clicks on the notifications page.
        if (isAdminIndexPage()) {
            return;
        }
        const tab = document.querySelector('[role="tablist"] [href="' + hash + '"]')
            || document.querySelector('[role="tablist"] [href$="' + hash + '"]');
        if (tab) {
            $(tab).tab('show');
        }
    };
    activateTabFromHash();
    window.addEventListener('hashchange', activateTabFromHash);
};

/**
 * Enable all popovers
 *
 */
const enablePopovers = () => {
    $('body').popover({
        container: 'body',
        selector: '[data-toggle="popover"]',
        trigger: 'focus',
        whitelist: Object.assign(DefaultWhitelist, {
            table: [],
            thead: [],
            tbody: [],
            tr: [],
            th: [],
            td: [],
        }),
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && e.target.closest('[data-toggle="popover"]')) {
            $(e.target).popover('hide');
        }
        if (e.key === 'Enter' && e.target.closest('[data-toggle="popover"]')) {
            $(e.target).popover('show');
        }
    });
    document.addEventListener('click', e => {
        $(e.target).closest('[data-toggle="popover"]').popover('show');
    });
};

/**
 * Enable tooltips
 *
 */
const enableTooltips = () => {
    $('body').tooltip({
        container: 'body',
        selector: '[data-toggle="tooltip"]',
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            $('.tooltip').tooltip('hide');
        }
    });
};

const pendingPromise = new Pending('theme_iiidem2/loader:init');

const disableAdminIndexBootstrapTabs = () => {
    if (!isAdminIndexPage()) {
        return;
    }
    $(document).off('click.bs.tab.data-api', '[data-toggle="tab"]');
    $(document).off('keydown.bs.tab.data-api', '[data-toggle="tab"]');
};

setupBootstrapPendingChecks();
disableAdminIndexBootstrapTabs();
initAdminNavFix();
Aria.init();
rememberTabs();
enablePopovers();
enableTooltips();

$.fn.dropdown.Constructor.Default.popperConfig = {
    modifiers: {
        flip: {
            enabled: false,
        },
        storeTopPosition: {
            enabled: true,
            fn(data) {
                data.storedTop = data.offsets.popper.top;
                return data;
            },
            order: 299
        },
        restoreTopPosition: {
            enabled: true,
            fn(data) {
                data.offsets.popper.top = data.storedTop;
                return data;
            },
            order: 301
        }
    },
};

pendingPromise.resolve();
