/**
 * Auto-refresh teacher results on manage.php while session is live.
 */
(function() {
    'use strict';

    var dataEl = document.getElementById('iiidem-livequiz-manage-data');
    if (!dataEl) {
        return;
    }

    var sessionid = dataEl.getAttribute('data-sessionid');
    var apiurl = dataEl.getAttribute('data-apiurl');
    var sesskey = dataEl.getAttribute('data-sesskey');
    var submittedLabel = dataEl.getAttribute('data-submittedlabel') || '__COUNT__ students submitted';
    var countEl = document.getElementById('iiidem-livequiz-submitted-count');
    var table = document.getElementById('iiidem-livequiz-results-table');

    function esc(value) {
        var div = document.createElement('div');
        div.textContent = String(value || '');
        return div.innerHTML;
    }

    function render(data) {
        if (!data || data.status !== 'ok') {
            return;
        }
        if (countEl) {
            countEl.textContent = submittedLabel.replace('__COUNT__', data.submittedcount || 0);
        }
        if (!table || !table.tBodies.length) {
            return;
        }

        var rows = Array.isArray(data.results) ? data.results : [];
        var html = '';
        rows.forEach(function(row) {
            html += '<tr><td>' + esc(row.studentname) +
                '<div class="small text-muted">' + esc(row.email) + '</div></td>' +
                '<td>' + esc(row.submittedlabel) + '</td>' +
                '<td>' + esc(row.scorelabel) + '</td></tr>';
        });
        if (!html) {
            html = '<tr><td colspan="3" class="text-muted">No submissions yet</td></tr>';
        }
        table.tBodies[0].innerHTML = html;
    }

    function refresh() {
        var body = new URLSearchParams();
        body.set('action', 'teacherstats');
        body.set('sessionid', sessionid);
        body.set('sesskey', sesskey);
        fetch(apiurl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString()
        })
            .then(function(response) {
                return response.json();
            })
            .then(render)
            .catch(function() {});
    }

    refresh();
    setInterval(refresh, 5000);
})();
