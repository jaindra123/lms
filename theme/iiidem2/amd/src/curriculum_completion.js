/**
 * Record activity completion when a student opens a curriculum preview.
 * Also lazy-loads PDF iframes after the collapse panel is visible so the
 * browser PDF viewer can size/zoom against the real panel dimensions.
 *
 * @module theme_iiidem2/curriculum_completion
 */

define([], function() {

    var pending = {};

    /**
     * Load PDF iframes that were deferred with data-pdf-src.
     *
     * @param {HTMLElement} panel
     */
    function activatePdfPreviews(panel) {
        if (!panel || !panel.querySelectorAll) {
            return;
        }

        panel.querySelectorAll('iframe.iiidem-curriculum-pdf[data-pdf-src]').forEach(function(iframe) {
            var url = iframe.getAttribute('data-pdf-src');
            if (!url) {
                return;
            }
            if (iframe.getAttribute('src') === url) {
                return;
            }
            iframe.setAttribute('src', url);
        });
    }

    /**
     * @param {object} config
     * @param {string} [config.ajaxurl]
     * @param {string} [config.sesskey]
     */
    function init(config) {
        var curriculum = document.querySelector('.iiidem-curriculum');
        if (!curriculum) {
            return;
        }

        config = config || {};

        /**
         * @param {string} cmid
         * @param {HTMLElement|null} source
         */
        function markViewed(cmid, source) {
            if (!config.ajaxurl || !config.sesskey || !cmid || pending[cmid]) {
                return;
            }

            pending[cmid] = true;

            var body = new URLSearchParams({
                sesskey: config.sesskey,
                cmid: cmid
            });

            fetch(config.ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: body.toString()
            }).then(function(response) {
                if (!response.ok) {
                    throw new Error('request failed');
                }
                return response.json();
            }).then(function(data) {
                if (!data || !data.success) {
                    throw new Error('completion failed');
                }
                if (source) {
                    source.classList.add('iiidem-curriculum-preview-btn--tracked');
                }
            }).catch(function() {
                delete pending[cmid];
            });
        }

        curriculum.addEventListener('click', function(event) {
            var button = event.target.closest('.iiidem-curriculum-preview-btn');
            if (!button || !curriculum.contains(button)) {
                return;
            }
            if (button.getAttribute('data-trackcompletion') !== '1') {
                return;
            }
            markViewed(button.getAttribute('data-cmid'), button);
        });

        curriculum.addEventListener('shown.bs.collapse', function(event) {
            var panel = event.target;
            if (!panel || !panel.id || panel.id.indexOf('preview') !== 0) {
                return;
            }

            activatePdfPreviews(panel);

            if (panel.getAttribute('data-trackcompletion') === '1') {
                markViewed(panel.getAttribute('data-cmid'), null);
            }
        });
    }

    return {
        init: init
    };
});
