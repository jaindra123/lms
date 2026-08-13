/**
 * CDAC #29: after logout, Back must not restore authenticated HTML from bfcache.
 * Reload when the page is restored from the back-forward cache.
 */
(function () {
    'use strict';

    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            window.location.reload();
        }
    });

    // Discourage bfcache retention of this document.
    window.addEventListener('pagehide', function () {
        // no-op marker; pairing with Cache-Control: no-store on the response
    }, {capture: true});
})();
