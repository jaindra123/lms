/**
 * Moodle AJAX CSRF for /lib/ajax/service.php and service-nologin.php.
 *
 * - Remove sesskey from the query string (not in URLs / Referer / proxy logs).
 * - Send X-Moodle-Sesskey instead; server maps it when query/body sesskey is absent.
 *
 * Do NOT touch other endpoints (e.g. repository/draftfiles_ajax.php).
 * Do NOT set the header twice (core/ajax already sets it) — duplicates become
 * "key, key" in PHP and cause invalidsesskey on Edit mode / AJAX writes.
 */
(function() {
    'use strict';

    if (window.__iiidemAjaxSesskeyPatched) {
        return;
    }
    window.__iiidemAjaxSesskeyPatched = true;

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
        if (!url || !isServiceAjax(url)) {
            return null;
        }
        try {
            var abs = new URL(url, window.location.origin);
            return abs.searchParams.get('sesskey');
        } catch (e) {
            var m = String(url).match(/[?&]sesskey=([^&]*)/);
            return m ? decodeURIComponent(m[1]) : null;
        }
    }

    /** Strip sesskey from service.php / service-nologin.php URLs. */
    function stripSesskeyFromUrl(url) {
        if (!isServiceAjax(url)) {
            return url;
        }
        try {
            var abs = new URL(url, window.location.origin);
            abs.searchParams.delete('sesskey');
            if (/^https?:\/\//i.test(url)) {
                return abs.toString();
            }
            return abs.pathname + abs.search + abs.hash;
        } catch (e) {
            return String(url).replace(/([?&])sesskey=[^&]*&?/g, function(m, sep) {
                if (sep === '?' && m.indexOf('&') === -1) {
                    return '';
                }
                return sep === '?' ? '?' : '';
            }).replace(/\?&/, '?').replace(/[?&]$/, '');
        }
    }

    function headerAlreadySet(headers) {
        if (!headers) {
            return false;
        }
        if (typeof Headers !== 'undefined' && headers instanceof Headers) {
            return headers.has(HEADER);
        }
        if (typeof headers === 'object') {
            var keys = Object.keys(headers);
            for (var i = 0; i < keys.length; i++) {
                if (keys[i].toLowerCase() === HEADER.toLowerCase()) {
                    return true;
                }
            }
        }
        return false;
    }

    function attachHeaderOnce(xhr, sk) {
        if (!xhr || !sk || xhr._iiidemSesskeyHeaderSet) {
            return;
        }
        try {
            xhr.setRequestHeader(HEADER, sk);
            xhr._iiidemSesskeyHeaderSet = true;
        } catch (e) {
            // Ignore if request already sent / unsafe header edge cases.
        }
    }

    if (typeof XMLHttpRequest !== 'undefined') {
        var origOpen = XMLHttpRequest.prototype.open;
        var origSend = XMLHttpRequest.prototype.send;
        var origSetRequestHeader = XMLHttpRequest.prototype.setRequestHeader;

        XMLHttpRequest.prototype.setRequestHeader = function(name, value) {
            if (name && String(name).toLowerCase() === HEADER.toLowerCase()) {
                this._iiidemSesskeyHeaderSet = true;
            }
            return origSetRequestHeader.apply(this, arguments);
        };

        XMLHttpRequest.prototype.open = function(method, url) {
            var args = Array.prototype.slice.call(arguments);
            this._iiidemSesskey = null;
            this._iiidemSesskeyHeaderSet = false;
            this._iiidemIsServiceAjax = false;
            if (typeof url === 'string' && isServiceAjax(url)) {
                this._iiidemIsServiceAjax = true;
                this._iiidemSesskey = sesskeyFromUrl(url) || getCfgSesskey();
                args[1] = stripSesskeyFromUrl(url);
            }
            return origOpen.apply(this, args);
        };
        XMLHttpRequest.prototype.send = function() {
            // Only for service.php — and only if core/ajax (or anyone) has not set it yet.
            if (this._iiidemIsServiceAjax && !this._iiidemSesskeyHeaderSet) {
                attachHeaderOnce(this, this._iiidemSesskey || getCfgSesskey());
            }
            return origSend.apply(this, arguments);
        };
    }

    // jQuery.ajax — strip URL sesskey; add header only if options.headers lacks it.
    function patchJquery($) {
        if (!$ || !$.ajaxPrefilter || $.__iiidemSesskeyPrefilter) {
            return;
        }
        $.__iiidemSesskeyPrefilter = true;
        $.ajaxPrefilter(function(options) {
            if (!options || !isServiceAjax(options.url || '')) {
                return;
            }
            var sk = sesskeyFromUrl(options.url) || getCfgSesskey();
            options.url = stripSesskeyFromUrl(options.url);
            if (!sk || headerAlreadySet(options.headers)) {
                return;
            }
            options.headers = options.headers || {};
            options.headers[HEADER] = sk;
        });
    }

    if (window.jQuery) {
        patchJquery(window.jQuery);
    } else {
        var tries = 0;
        var timer = window.setInterval(function() {
            if (window.jQuery) {
                patchJquery(window.jQuery);
                window.clearInterval(timer);
            } else if (++tries > 120) {
                window.clearInterval(timer);
            }
        }, 50);
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
            var headers = new Headers(init.headers || (input && input.headers) || {});
            if (sk && !headers.has(HEADER)) {
                headers.set(HEADER, sk);
            }
            var nextUrl = stripSesskeyFromUrl(url);
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
