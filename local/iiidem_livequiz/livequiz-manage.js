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

    setInterval(function() {
        var url = apiurl + '?action=teacherstats&sessionid=' + encodeURIComponent(sessionid) +
            '&sesskey=' + encodeURIComponent(sesskey);
        fetch(url, {credentials: 'same-origin'}).catch(function() {});
    }, 10000);
})();
