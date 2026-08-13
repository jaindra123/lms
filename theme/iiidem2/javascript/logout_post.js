/**
 * Logout without sesskey in the URL (CDAC session-token-in-URL / logout.php?sesskey=).
 *
 * Intercepts clicks on /login/logout.php?…sesskey=… and submits a POST form instead.
 */
(function() {
    'use strict';

    function postLogout(urlString) {
        var abs;
        try {
            abs = new URL(urlString, window.location.origin);
        } catch (e) {
            return false;
        }
        if (abs.pathname.indexOf('/login/logout.php') === -1) {
            return false;
        }
        var sk = abs.searchParams.get('sesskey');
        if (!sk && window.M && M.cfg && M.cfg.sesskey) {
            sk = M.cfg.sesskey;
        }
        if (!sk) {
            return false;
        }

        var form = document.createElement('form');
        form.method = 'POST';
        form.action = abs.pathname;
        form.style.display = 'none';

        abs.searchParams.forEach(function(value, key) {
            if (key === 'sesskey') {
                return;
            }
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        });

        var sess = document.createElement('input');
        sess.type = 'hidden';
        sess.name = 'sesskey';
        sess.value = sk;
        form.appendChild(sess);

        document.body.appendChild(form);
        form.submit();
        return true;
    }

    document.addEventListener('click', function(e) {
        var a = e.target && e.target.closest ? e.target.closest('a[href*="logout.php"]') : null;
        if (!a) {
            return;
        }
        var href = a.getAttribute('href');
        if (!href || href.indexOf('logout.php') === -1) {
            return;
        }
        if (postLogout(href)) {
            e.preventDefault();
            e.stopPropagation();
        }
    }, true);
})();
