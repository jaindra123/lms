/**

 * Moodle AJAX CSRF for /lib/ajax/service.php only.

 *

 * - Keep sesskey on the query string (compat if proxies drop custom headers).

 * - Also send X-Moodle-Sesskey for prefer-header clients.

 *

 * Do NOT touch other endpoints (e.g. repository/draftfiles_ajax.php) — those

 * already POST sesskey correctly; overwriting via header caused invalidsesskey.

 *

 * Server maps X-Moodle-Sesskey → request params only when sesskey is missing.

 */

(function() {

    'use strict';



    var HEADER = 'X-Moodle-Sesskey';



    function isServiceAjax(url) {

        return typeof url === 'string' && url.indexOf('/lib/ajax/service') !== -1;

    }



    function getCfgSesskey() {

        try {

            if (window.M && M.cfg && typeof M.cfg.sesskey === 'string' && M.cfg.sesskey !== '') {

                return M.cfg.sesskey;

            }

        } catch (e) {

            // ignore

        }

        return null;

    }



    function sesskeyFromUrl(url) {

        if (!isServiceAjax(url)) {

            return null;

        }

        try {

            var abs = new URL(url, window.location.origin);

            return abs.searchParams.get('sesskey');

        } catch (e) {

            return null;

        }

    }



    function ensureSesskeyOnUrl(url, sesskey) {

        if (!isServiceAjax(url) || !sesskey) {

            return url;

        }

        try {

            var abs = new URL(url, window.location.origin);

            if (!abs.searchParams.get('sesskey')) {

                abs.searchParams.set('sesskey', sesskey);

            }

            return abs.pathname + abs.search + abs.hash;

        } catch (e) {

            return url;

        }

    }



    if (typeof XMLHttpRequest !== 'undefined') {

        var origOpen = XMLHttpRequest.prototype.open;

        var origSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function(method, url) {

            var args = Array.prototype.slice.call(arguments);

            this._iiidemSesskey = null;

            if (isServiceAjax(url)) {

                var sk = sesskeyFromUrl(url) || getCfgSesskey();

                this._iiidemSesskey = sk;

                if (sk) {

                    args[1] = ensureSesskeyOnUrl(url, sk);

                }

            }

            return origOpen.apply(this, args);

        };

        XMLHttpRequest.prototype.send = function() {

            if (this._iiidemSesskey) {

                try {

                    this.setRequestHeader(HEADER, this._iiidemSesskey);

                } catch (e) {

                    // Ignore if request already sent / unsafe header edge cases.

                }

            }

            return origSend.apply(this, arguments);

        };

    }



    if (typeof window.fetch === 'function') {

        var origFetch = window.fetch;

        window.fetch = function(input, init) {

            init = init || {};

            var url = typeof input === 'string' ? input : (input && input.url);

            if (!isServiceAjax(url || '')) {

                return origFetch.call(this, input, init);

            }

            var sk = sesskeyFromUrl(url || '') || getCfgSesskey();

            if (!sk) {

                return origFetch.call(this, input, init);

            }

            var headers = new Headers(init.headers || (input && input.headers) || {});

            headers.set(HEADER, sk);

            var nextUrl = ensureSesskeyOnUrl(url, sk);

            init = Object.assign({}, init, {headers: headers});

            if (typeof input === 'string') {

                input = nextUrl;

            } else if (input && typeof Request !== 'undefined') {

                input = new Request(nextUrl, input);

            }

            return origFetch.call(this, input, init);

        };

    }

})();


