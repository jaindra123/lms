/**
 * Interactive student dashboard — tab panels, sidebar navigation, deep links.
 *
 * @module     theme_iiidem2/student_dashboard
 * @copyright  2026 IIIDEM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    const ROOT_SELECTOR = '[data-region="student-dashboard"]';

    /**
     * @param {HTMLElement} root
     * @param {string} panelId
     */
    const activatePanel = (root, panelId) => {
        const tabs = root.querySelectorAll('[data-dashboard-tab]');
        const panels = root.querySelectorAll('[data-dashboard-panel]');
        const sidenav = root.querySelectorAll('[data-dashboard-sidenav]');

        tabs.forEach((tab) => {
            const active = tab.dataset.dashboardTab === panelId;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.tabIndex = active ? 0 : -1;
        });

        panels.forEach((panel) => {
            const show = panel.dataset.dashboardPanel === panelId;
            panel.classList.toggle('d-none', !show);
            panel.hidden = !show;
        });

        sidenav.forEach((link) => {
            const active = link.dataset.dashboardPanel === panelId && link.dataset.dashboardSidenav === 'true';
            link.closest('.iiidem-student-sidebar__item')?.classList.toggle('is-active', active);
        });

        if (history.replaceState) {
            history.replaceState(null, '', '#' + panelId);
        }
    };

    /**
     * @param {HTMLElement} root
     * @param {string} panelId
     * @param {string|null} sectionId
     */
    const goToPanel = (root, panelId, sectionId) => {
        activatePanel(root, panelId);
        if (sectionId) {
            const section = document.getElementById(sectionId);
            if (section) {
                window.setTimeout(() => {
                    section.scrollIntoView({behavior: 'smooth', block: 'start'});
                }, 50);
            }
        }
    };

    /**
     * @param {HTMLElement} root
     */
    const initTabs = (root) => {
        root.querySelectorAll('[data-dashboard-tab]').forEach((tab) => {
            tab.addEventListener('click', (event) => {
                event.preventDefault();
                activatePanel(root, tab.dataset.dashboardTab);
            });
            tab.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                event.preventDefault();
                activatePanel(root, tab.dataset.dashboardTab);
            });
        });
    };

    /**
     * @param {HTMLElement} root
     */
    const initSidebar = (root) => {
        root.querySelectorAll('[data-dashboard-sidenav]').forEach((link) => {
            link.addEventListener('click', (event) => {
                const panel = link.dataset.dashboardPanel;
                if (!panel) {
                    return;
                }
                event.preventDefault();
                goToPanel(root, panel, link.dataset.dashboardSection || null);
            });
        });
    };

    /**
     * @param {HTMLElement} root
     */
    const initHash = (root) => {
        const hash = window.location.hash.replace('#', '');
        if (!hash) {
            return;
        }
        if (root.querySelector('[data-dashboard-panel="' + hash + '"]')) {
            activatePanel(root, hash);
            return;
        }
        const section = document.getElementById(hash);
        if (section) {
            const panel = section.closest('[data-dashboard-panel]');
            if (panel) {
                goToPanel(root, panel.dataset.dashboardPanel, hash);
            }
        }
    };

    const init = () => {
        const root = document.querySelector(ROOT_SELECTOR);
        if (!root) {
            return;
        }
        initTabs(root);
        initSidebar(root);
        initHash(root);
        window.addEventListener('hashchange', () => initHash(root));
    };

    return {init};
});
