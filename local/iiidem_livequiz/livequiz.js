/**
 * Live MCQ panel for students on the live class page.
 */
(function() {
    'use strict';

    var root = document.getElementById('iiidem-livequiz-root');
    if (!root) {
        return;
    }

    var apiurl = root.getAttribute('data-apiurl');
    var courseid = root.getAttribute('data-courseid');
    var cmid = root.getAttribute('data-cmid');
    var sesskey = root.getAttribute('data-sesskey');
    var pollMs = 5000;

    function esc(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderWaiting() {
        root.innerHTML = '<div class="iiidem-livequiz__state iiidem-livequiz__state--waiting">' +
            '<span class="iiidem-livequiz__pulse" aria-hidden="true"></span>' +
            '<p>' + esc(root.getAttribute('data-waiting')) + '</p></div>';
    }

    function renderSubmitted(name) {
        root.innerHTML = '<div class="iiidem-livequiz__state iiidem-livequiz__state--done">' +
            '<span class="fa fa-check-circle" aria-hidden="true"></span>' +
            '<p><strong>' + esc(root.getAttribute('data-thanks')) + '</strong></p>' +
            (name ? '<p class="iiidem-livequiz__meta">' + esc(name) + '</p>' : '') +
            '</div>';
    }

    function renderForm(data) {
        var html = '<form class="iiidem-livequiz__form" id="iiidem-livequiz-form">' +
            '<p class="iiidem-livequiz__session">' + esc(data.sessionname) + '</p>';

        data.questions.forEach(function(q, qi) {
            html += '<fieldset class="iiidem-livequiz__question">' +
                '<legend><span class="iiidem-livequiz__qno">' + (qi + 1) + '</span> ' + esc(q.text) + '</legend>';
            q.options.forEach(function(opt) {
                var id = 'lq-' + q.id + '-' + opt.index;
                html += '<label class="iiidem-livequiz__option" for="' + id + '">' +
                    '<input type="radio" name="q_' + q.id + '" id="' + id + '" value="' + opt.index + '" required>' +
                    '<span>' + esc(opt.label) + '</span></label>';
            });
            html += '</fieldset>';
        });

        html += '<button type="submit" class="iiidem-livequiz__submit">' +
            esc(root.getAttribute('data-submit')) + '</button></form>';
        root.innerHTML = html;

        document.getElementById('iiidem-livequiz-form').addEventListener('submit', function(e) {
            e.preventDefault();
            var answers = {};
            data.questions.forEach(function(q) {
                var selected = root.querySelector('input[name="q_' + q.id + '"]:checked');
                if (selected) {
                    answers[q.id] = parseInt(selected.value, 10);
                }
            });

            var body = new URLSearchParams();
            body.set('action', 'submit');
            body.set('sesskey', sesskey);
            body.set('sessionid', data.sessionid);
            body.set('answers', JSON.stringify(answers));

            fetch(apiurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                credentials: 'same-origin',
                body: body.toString()
            })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.status === 'ok') {
                        renderSubmitted(data.sessionname);
                    } else {
                        alert(res.message || 'Error');
                    }
                })
                .catch(function() {
                    alert('Could not submit answers. Please try again.');
                });
        });
    }

    function poll() {
        var body = new URLSearchParams();
        body.set('action', 'getactive');
        body.set('courseid', courseid);
        body.set('cmid', cmid);
        body.set('sesskey', sesskey);

        fetch(apiurl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString()
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status === 'waiting') {
                    renderWaiting();
                } else if (data.status === 'submitted') {
                    renderSubmitted(data.sessionname || '');
                } else if (data.status === 'active' && !data.canteach) {
                    renderForm(data);
                } else if (data.status === 'active' && data.canteach) {
                    root.innerHTML = '<div class="iiidem-livequiz__state iiidem-livequiz__state--teacher">' +
                        '<p><strong>' + esc(data.sessionname) + '</strong> is live.</p>' +
                        '<p class="iiidem-livequiz__meta">' +
                        esc(root.getAttribute('data-teachermeta')) + '</p></div>';
                }
            })
            .catch(function() {
                // Keep last state on transient errors.
            });
    }

    renderWaiting();
    poll();
    setInterval(poll, pollMs);
})();
