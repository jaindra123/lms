(function() {
    'use strict';

    function initChatbot(root) {
        if (!root || root.dataset.initialized === '1') {
            return;
        }
        root.dataset.initialized = '1';

        var apiUrl = root.dataset.apiUrl;
        var ticketUrl = root.dataset.ticketUrl;
        var toggle = root.querySelector('[data-action="toggle"]');
        var panel = root.querySelector('.iiidem-support-chatbot__panel');
        var input = root.querySelector('.iiidem-support-chatbot__input');
        var send = root.querySelector('[data-action="send"]');
        var body = root.querySelector('.iiidem-support-chatbot__body');
        var sesskey = root.dataset.sesskey;

        function setBody(html) {
            body.innerHTML = html;
        }

        function search() {
            var q = (input.value || '').trim();
            if (!q) {
                return;
            }

            setBody('<p class="text-muted mb-0">Searching…</p>');

            var bodyParams = new URLSearchParams();
            bodyParams.set('action', 'searchfaq');
            bodyParams.set('q', q);
            bodyParams.set('sesskey', sesskey);
            fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: bodyParams.toString()
            })
                .then(function(response) {
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    return response.json();
                })
                .then(function(data) {
                    if (!data.results || !data.results.length) {
                        setBody(
                            '<p class="mb-2">' + (root.dataset.noResults || 'No matching FAQ found.') + '</p>' +
                            '<a class="btn btn-sm btn-primary" href="' + (data.ticketurl || ticketUrl) + '">' +
                            (root.dataset.raiseTicket || 'Raise a ticket') + '</a>'
                        );
                        return;
                    }

                    var html = '';
                    data.results.forEach(function(item) {
                        html += '<div class="iiidem-support-chatbot__result">' +
                            '<strong>' + escapeHtml(item.question) + '</strong>' +
                            '<p>' + escapeHtml(stripTags(item.answer)) + '</p>' +
                            '<small class="text-muted">' + escapeHtml(item.coursename || '') + '</small>' +
                            '</div>';
                    });
                    setBody(html);
                })
                .catch(function() {
                    setBody('<p class="text-danger mb-0">Could not search FAQs. Please try again.</p>');
                });
        }

        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text || '';
            return div.innerHTML;
        }

        function stripTags(html) {
            var div = document.createElement('div');
            div.innerHTML = html || '';
            return div.textContent || div.innerText || '';
        }

        toggle.addEventListener('click', function() {
            root.classList.toggle('is-open');
            if (root.classList.contains('is-open')) {
                input.focus();
            }
        });

        send.addEventListener('click', search);
        input.addEventListener('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                search();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.iiidem-support-chatbot').forEach(initChatbot);
    });
})();
