// This file is part of Moodle - http://moodle.org/
//
// @package   theme_iiidem2
// @copyright 2026 IIIDEM
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

/**
 * MCQ attempt UI helpers (Submit label, clickable options, hide nav).
 *
 * @module     theme_iiidem2/quiz_mcq
 */
define(['core/config'], function(cfg) {
    'use strict';

    const CSS_ID = 'iiidem-quiz-mcq-css';
    const CSS_PATH = '/theme/iiidem2/style/quiz-mcq.css';

    /**
     * Inject quiz-mcq.css when PHP/layout did not (fallback for pagelayout-incourse).
     */
    function ensureQuizMcqCss() {
        if (document.getElementById(CSS_ID)) {
            return;
        }
        const isAttempt = document.body && (
            document.body.id === 'page-mod-quiz-attempt'
            || document.getElementById('responseform')
        );
        if (!isAttempt) {
            return;
        }

        const link = document.createElement('link');
        link.id = CSS_ID;
        link.rel = 'stylesheet';
        link.type = 'text/css';
        link.href = cfg.wwwroot + CSS_PATH;
        document.head.appendChild(link);
    }

    ensureQuizMcqCss();

    /**
     * @param {HTMLFormElement} form
     */
    function styleSubmitButtons(form) {
        form.querySelectorAll('input[type="submit"]').forEach((btn) => {
            const value = (btn.value || '').trim().toLowerCase();
            if (value === 'next page' || value === 'next' || value.indexOf('finish attempt') === 0) {
                btn.value = 'Submit';
            }
        });
    }

    /**
     * @param {HTMLFormElement} form
     */
    function bindAnswerRows(form) {
        form.querySelectorAll('.answer input[type="radio"]').forEach((radio) => {
            const row = radio.closest('.r0, .r1, [class*="r"]') || radio.closest('.d-flex')?.parentElement;
            if (!row || row.dataset.iiidemBound) {
                return;
            }
            row.dataset.iiidemBound = '1';
            row.addEventListener('click', (e) => {
                if (e.target === radio) {
                    return;
                }
                radio.checked = true;
                radio.dispatchEvent(new Event('change', {bubbles: true}));
            });
        });
    }

    /**
     * Hide Moodle quiz navigation drawer block on custom MCQ pages.
     */
    function hideQuizNavBlock() {
        document.querySelectorAll(
            '.block_mod_quiz_navblock, #mod_quiz_navblock'
        ).forEach((el) => {
            const block = el.closest('.block');
            if (block) {
                block.remove();
            } else {
                el.remove();
            }
        });

        document.querySelectorAll('.tertiary-navigation').forEach((el) => {
            el.remove();
        });
    }

    /**
     * Wrap #responseform in MCQ card markup when still on default drawers layout.
     */
    function ensureMcqCardWrapper() {
        const form = document.getElementById('responseform');
        if (!form || form.closest('.iiidem-quiz-mcq__card')) {
            return;
        }

        const card = document.createElement('div');
        card.className = 'iiidem-quiz-mcq__card';
        const parent = form.parentNode;
        parent.insertBefore(card, form);
        card.appendChild(form);
    }

    /**
     * Initialise quiz MCQ styling helpers.
     */
    function init() {
        ensureQuizMcqCss();

        const form = document.getElementById('responseform');
        if (!form) {
            return;
        }

        document.body.classList.add('iiidem-quiz-attempt-active');
        hideQuizNavBlock();
        ensureMcqCardWrapper();

        form.classList.add('iiidem-quiz-responseform');
        styleSubmitButtons(form);
        bindAnswerRows(form);
    }

    return {init: init};
});
