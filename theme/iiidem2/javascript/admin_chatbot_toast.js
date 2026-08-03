/**
 * Desktop-style toast for site admins when a homepage chatbot query arrives.
 *
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    var started = false;

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    function initAdminChatbotToast(config) {
        if (started || !config || !config.apiUrl || !config.sesskey) {
            return;
        }
        started = true;

        var sinceId = parseInt(config.sinceId || '0', 10) || 0;
        var pollMs = parseInt(config.pollMs || '8000', 10) || 8000;
        var host = document.getElementById('iiidem-admin-toast-host');
        if (!host) {
            host = document.createElement('div');
            host.id = 'iiidem-admin-toast-host';
            host.className = 'iiidem-admin-toast-host';
            host.setAttribute('aria-live', 'polite');
            document.body.appendChild(host);
        }

        function showToast(item) {
            var toast = document.createElement('div');
            toast.className = 'iiidem-admin-toast';
            toast.innerHTML =
                '<button type="button" class="iiidem-admin-toast__close" aria-label="Close">&times;</button>' +
                '<div class="iiidem-admin-toast__eyebrow">IIIDEM chatbot</div>' +
                '<div class="iiidem-admin-toast__title">' + escapeHtml(item.title || 'New question') + '</div>' +
                '<div class="iiidem-admin-toast__body">' + escapeHtml(item.body || '') + '</div>';

            var closeBtn = toast.querySelector('.iiidem-admin-toast__close');
            closeBtn.addEventListener('click', function() {
                toast.classList.add('is-hiding');
                setTimeout(function() {
                    toast.remove();
                }, 220);
            });

            host.appendChild(toast);
            requestAnimationFrame(function() {
                toast.classList.add('is-visible');
            });

            setTimeout(function() {
                if (!toast.parentNode) {
                    return;
                }
                toast.classList.add('is-hiding');
                setTimeout(function() {
                    toast.remove();
                }, 220);
            }, 12000);
        }

        function poll() {
            var url = config.apiUrl +
                '?sesskey=' + encodeURIComponent(config.sesskey) +
                '&sinceid=' + encodeURIComponent(String(sinceId));

            fetch(url, {credentials: 'same-origin'})
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function(data) {
                    if (!data || !data.items || !data.items.length) {
                        return;
                    }
                    data.items.forEach(function(item) {
                        if (item.id > sinceId) {
                            sinceId = item.id;
                        }
                        showToast(item);
                    });
                })
                .catch(function() {
                    // Ignore transient poll errors.
                });
        }

        poll();
        setInterval(poll, pollMs);
    }

    // Expose for Moodle js_init_code (runs after this file in footer).
    window.iiidemAdminChatbotToastInit = initAdminChatbotToast;

    function boot() {
        if (window.iiidemAdminChatbotToast) {
            initAdminChatbotToast(window.iiidemAdminChatbotToast);
        }
    }

    // Moodle footer scripts often run after DOMContentLoaded already fired.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
