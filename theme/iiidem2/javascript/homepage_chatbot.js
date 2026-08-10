/**
 * Homepage chatbot — visitors ask; site admins reply.
 *
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    function initChatbot(root) {
        if (!root || root.dataset.initialized === '1') {
            return;
        }
        root.dataset.initialized = '1';

        var isAdmin = root.dataset.adminMode === '1';
        if (isAdmin) {
            initAdminMode(root);
        } else {
            initVisitorMode(root);
        }
    }

    function setOpen(root, panel, toggle, queryInput, open) {
        root.classList.toggle('is-open', open);
        if (panel) {
            if (open) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', 'hidden');
            }
        }
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        if (open && queryInput) {
            queryInput.focus();
        }
    }

    function appendBubble(container, text, type) {
        var bubble = document.createElement('div');
        bubble.className = 'iiidem-home-chatbot__bubble iiidem-home-chatbot__bubble--' + type;
        bubble.innerHTML = escapeHtml(text);
        container.appendChild(bubble);
        container.scrollTop = container.scrollHeight;
    }

    function initVisitorMode(root) {
        var apiUrl = root.dataset.apiUrl;
        var sesskey = root.dataset.sesskey;
        var panel = root.querySelector('.iiidem-home-chatbot__panel');
        var toggle = root.querySelector('[data-action="toggle"]');
        var closeBtn = root.querySelector('[data-action="close"]');
        var form = root.querySelector('[data-role="form"]');
        var messages = root.querySelector('[data-role="messages"]');
        var queryInput = form ? form.querySelector('[name="query"]') : null;
        var sendBtn = form ? form.querySelector('[data-action="send"]') : null;
        var nameField = form ? form.querySelector('[name="name"]') : null;
        var emailField = form ? form.querySelector('[name="email"]') : null;
        var minQueryLen = 2;
        var welcomeHtml = messages ? messages.innerHTML : '';
        var pollTimer = null;
        var lastHistoryKey = '';

        function getEmail() {
            return emailField ? (emailField.value || '').trim() : '';
        }

        function removeSendingBubble() {
            var bots = messages.querySelectorAll('.iiidem-home-chatbot__bubble--bot');
            if (!bots.length) {
                return;
            }
            var last = bots[bots.length - 1];
            if (last && last.textContent === (root.dataset.sending || 'Sending…')) {
                last.remove();
            }
        }

        function setSending(busy) {
            if (sendBtn) {
                sendBtn.disabled = busy;
            }
            if (queryInput) {
                queryInput.disabled = busy;
            }
        }

        function historyKey(items) {
            return (items || []).map(function(it) {
                return it.id + ':' + (it.answered ? '1' : '0') + ':' + (it.reply || '').length;
            }).join('|');
        }

        function renderHistory(items, statusMessage) {
            if (!messages) {
                return;
            }
            messages.innerHTML = welcomeHtml;
            if (!items || !items.length) {
                if (statusMessage) {
                    appendBubble(messages, statusMessage, 'bot');
                }
                return;
            }
            items.forEach(function(item) {
                appendBubble(messages, item.question, 'user');
                if (item.answered && item.reply) {
                    appendBubble(messages, 'Admin: ' + item.reply, 'bot');
                } else {
                    appendBubble(messages, 'Waiting for administrator reply…', 'bot');
                }
            });
            if (statusMessage) {
                appendBubble(messages, statusMessage, 'bot');
            }
            lastHistoryKey = historyKey(items);
        }

        function loadHistory(statusMessage) {
            // History is authorized server-side from the session email set after ask.
            // Do not send a client-chosen email (IDOR prevention).
            // sesskey must not appear in the URL (Referer / proxy logs).
            var body = new URLSearchParams();
            body.set('action', 'history');
            body.set('sesskey', sesskey);
            fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: body.toString()
            })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data || !data.success) {
                        return;
                    }
                    var key = historyKey(data.items || []);
                    if (key !== lastHistoryKey || statusMessage) {
                        renderHistory(data.items || [], statusMessage || '');
                    }
                })
                .catch(function() { /* ignore */ });
        }

        function startPolling() {
            stopPolling();
            pollTimer = setInterval(function() {
                if (!root.classList.contains('is-open')) {
                    return;
                }
                loadHistory('');
            }, 8000);
        }

        function stopPolling() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        if (toggle) {
            toggle.addEventListener('click', function() {
                var opening = !root.classList.contains('is-open');
                setOpen(root, panel, toggle, queryInput, opening);
                if (opening) {
                    loadHistory('');
                    startPolling();
                } else {
                    stopPolling();
                }
            });
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                setOpen(root, panel, toggle, queryInput, false);
                stopPolling();
            });
        }

        if (emailField) {
            emailField.addEventListener('change', function() {
                loadHistory('');
            });
            emailField.addEventListener('blur', function() {
                loadHistory('');
            });
        }

        if (!form) {
            return;
        }

        form.addEventListener('submit', function(event) {
            event.preventDefault();

            var name = nameField ? (nameField.value || '').trim() : '';
            var email = getEmail();
            var query = queryInput ? (queryInput.value || '').trim() : '';

            if (name.length < 2) {
                appendBubble(messages, 'Please enter your name.', 'bot');
                return;
            }
            if (!email || email.indexOf('@') < 1) {
                appendBubble(messages, 'Please enter a valid email address.', 'bot');
                return;
            }
            if (query.length < minQueryLen) {
                appendBubble(messages, 'Please type a short question (at least 2 characters).', 'bot');
                return;
            }

            queryInput.value = '';
            setSending(true);
            appendBubble(messages, root.dataset.sending || 'Sending…', 'bot');

            var body = new URLSearchParams();
            body.set('sesskey', sesskey);
            body.set('action', 'ask');
            body.set('name', name);
            body.set('email', email);
            body.set('query', query);

            fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: body.toString()
            })
                .then(function(response) {
                    return response.text().then(function(text) {
                        var data = null;
                        try {
                            data = text ? JSON.parse(text) : null;
                        } catch (e) {
                            data = null;
                        }
                        return {ok: response.ok, status: response.status, data: data};
                    });
                })
                .then(function(result) {
                    removeSendingBubble();
                    var msg = (result.data && result.data.message)
                        ? result.data.message
                        : (root.dataset.error || 'Sorry, we could not send your question. Please try again.');
                    if (result.data && result.data.history) {
                        renderHistory(result.data.history, msg);
                    } else {
                        appendBubble(messages, msg, 'bot');
                        loadHistory('');
                    }
                    startPolling();
                })
                .catch(function() {
                    removeSendingBubble();
                    appendBubble(messages, root.dataset.error || 'Could not send your query. Please try again.', 'bot');
                })
                .finally(function() {
                    setSending(false);
                    if (queryInput) {
                        queryInput.focus();
                    }
                });
        });

        // Prefill history if email already present (logged-in users).
        if (getEmail()) {
            loadHistory('');
        }
    }

    function initAdminMode(root) {
        var adminApi = root.dataset.adminApiUrl;
        var sesskey = root.dataset.sesskey;
        var panel = root.querySelector('.iiidem-home-chatbot__panel');
        var toggle = root.querySelector('[data-action="toggle"]');
        var closeBtn = root.querySelector('[data-action="close"]');
        var listEl = root.querySelector('[data-role="admin-list"]');
        var threadEl = root.querySelector('[data-role="admin-thread"]');
        var messages = root.querySelector('[data-role="admin-messages"]');
        var replyForm = root.querySelector('[data-role="admin-reply-form"]');
        var replyInput = replyForm ? replyForm.querySelector('[name="reply"]') : null;
        var backBtn = root.querySelector('[data-action="admin-back"]');
        var badge = root.querySelector('[data-role="admin-badge"]');
        var selectedId = 0;
        var selectedItem = null;

        function showList() {
            selectedId = 0;
            selectedItem = null;
            if (threadEl) {
                threadEl.setAttribute('hidden', 'hidden');
            }
            if (listEl) {
                listEl.removeAttribute('hidden');
            }
        }

        function showThread(item) {
            selectedId = item.id;
            selectedItem = item;
            if (listEl) {
                listEl.setAttribute('hidden', 'hidden');
            }
            if (threadEl) {
                threadEl.removeAttribute('hidden');
            }
            messages.innerHTML = '';
            appendBubble(messages, item.name + ' (' + item.email + ')', 'bot');
            appendBubble(messages, item.question, 'user');
            appendBubble(messages, 'Type your reply below. The user will receive it by email.', 'bot');
            if (replyInput) {
                replyInput.value = '';
                replyInput.focus();
            }
        }

        function renderList(items) {
            if (!listEl) {
                return;
            }
            if (!items || !items.length) {
                listEl.innerHTML = '<p class="iiidem-home-chatbot__hint">' +
                    escapeHtml(root.dataset.noOpen || 'No open questions right now.') + '</p>';
                if (badge) {
                    badge.hidden = true;
                }
                return;
            }

            if (badge) {
                badge.hidden = false;
                badge.textContent = String(items.length);
            }

            var html = '<p class="iiidem-home-chatbot__hint">Open questions — click one to reply:</p>';
            items.forEach(function(item) {
                html += '<button type="button" class="iiidem-home-chatbot__qitem" data-id="' + item.id + '">' +
                    '<strong>' + escapeHtml(item.name) + '</strong>' +
                    '<span>' + escapeHtml(item.question) + '</span>' +
                    '<small>' + escapeHtml(item.timestr || '') + '</small>' +
                    '</button>';
            });
            listEl.innerHTML = html;

            listEl.querySelectorAll('.iiidem-home-chatbot__qitem').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var id = parseInt(btn.getAttribute('data-id'), 10);
                    var found = null;
                    items.forEach(function(it) {
                        if (it.id === id) {
                            found = it;
                        }
                    });
                    if (found) {
                        showThread(found);
                    }
                });
            });
        }

        function loadList() {
            var body = new URLSearchParams();
            body.set('action', 'list');
            body.set('sesskey', sesskey);
            fetch(adminApi, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: body.toString()
            })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    renderList((data && data.items) ? data.items : []);
                })
                .catch(function() {
                    if (listEl) {
                        listEl.innerHTML = '<p class="iiidem-home-chatbot__hint">Could not load questions.</p>';
                    }
                });
        }

        if (toggle) {
            toggle.addEventListener('click', function() {
                var opening = !root.classList.contains('is-open');
                setOpen(root, panel, toggle, replyInput, opening);
                if (opening) {
                    showList();
                    loadList();
                }
            });
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                setOpen(root, panel, toggle, replyInput, false);
            });
        }
        if (backBtn) {
            backBtn.addEventListener('click', function() {
                showList();
                loadList();
            });
        }

        if (replyForm) {
            replyForm.addEventListener('submit', function(event) {
                event.preventDefault();
                if (!selectedId) {
                    return;
                }
                var reply = replyInput ? (replyInput.value || '').trim() : '';
                if (reply.length < 2) {
                    appendBubble(messages, 'Please type a reply.', 'bot');
                    return;
                }

                appendBubble(messages, reply, 'bot');
                replyInput.value = '';
                var body = new URLSearchParams();
                body.set('sesskey', sesskey);
                body.set('action', 'reply');
                body.set('id', String(selectedId));
                body.set('reply', reply);

                fetch(adminApi, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                    body: body.toString()
                })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        appendBubble(messages, (data && data.message) ? data.message : 'Reply saved.', 'bot');
                        setTimeout(function() {
                            showList();
                            loadList();
                        }, 1200);
                    })
                    .catch(function() {
                        appendBubble(messages, 'Could not send reply. Please try again.', 'bot');
                    });
            });
        }

        // Prefetch count for badge.
        loadList();
    }

    function boot() {
        document.querySelectorAll('.iiidem-home-chatbot').forEach(initChatbot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
