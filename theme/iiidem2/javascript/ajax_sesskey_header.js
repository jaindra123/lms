/**
 * Moodle AJAX CSRF for /lib/ajax/service.php and service-nologin.php.
 *
 * - Remove sesskey from request URLs (not in history / Referer / proxy logs).
 * - For /lib/ajax/service*.php send X-Moodle-Sesskey; server maps it when query/body sesskey is absent.
 *
 * Do NOT set the header on other endpoints (e.g. repository/draftfiles_ajax.php).
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

    function urlToString(url) {
        if (typeof url === 'string') {
            return url;
        }
        if (url && typeof url === 'object') {
            if (typeof url.href === 'string' && url.href !== '') {
                return url.href;
            }
            if (typeof url.toString === 'function') {
                return url.toString();
            }
        }
        return url == null ? '' : String(url);
    }

    function isServiceAjax(url) {
        return urlToString(url).indexOf('/lib/ajax/service') !== -1;
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
        if (!url) {
            return null;
        }
        try {
            var abs = new URL(urlToString(url), window.location.origin);
            return abs.searchParams.get('sesskey');
        } catch (e) {
            var m = String(url).match(/[?&]sesskey=([^&]*)/);
            return m ? decodeURIComponent(m[1]) : null;
        }
    }

    /** Strip sesskey from any request URL (Referer / history / proxy logs). */
    function stripSesskeyFromUrl(url) {
        var raw = urlToString(url);
        if (raw.indexOf('sesskey=') === -1) {
            return url;
        }
        try {
            var abs = new URL(raw, window.location.origin);
            abs.searchParams.delete('sesskey');
            if (/^https?:\/\//i.test(raw)) {
                return abs.toString();
            }
            return abs.pathname + abs.search + abs.hash;
        } catch (e) {
            return raw.replace(/([?&])sesskey=[^&]*&?/g, function(m, sep) {
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
            if (typeof url === 'string' || (url && typeof url === 'object' && url.href)) {
                this._iiidemIsServiceAjax = isServiceAjax(url);
                this._iiidemSesskey = sesskeyFromUrl(url) || (this._iiidemIsServiceAjax ? getCfgSesskey() : null);
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
            if (!options || !options.url) {
                return;
            }
            var url = options.url;
            var sk = sesskeyFromUrl(url);
            options.url = stripSesskeyFromUrl(url);
            if (!isServiceAjax(url)) {
                return;
            }
            sk = sk || getCfgSesskey();
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
            var nextUrl = stripSesskeyFromUrl(url || '');
            if (!isServiceAjax(url || '')) {
                if (nextUrl !== url && typeof input === 'string') {
                    input = nextUrl;
                }
                return origFetch.call(this, input, init);
            }
            var sk = sesskeyFromUrl(url || '') || getCfgSesskey();
            var headers = new Headers(init.headers || (input && input.headers) || {});
            if (sk && !headers.has(HEADER)) {
                headers.set(HEADER, sk);
            }
            init = Object.assign({}, init, {headers: headers});
            if (typeof input === 'string') {
                input = nextUrl;
            } else if (input && typeof Request !== 'undefined') {
                input = new Request(nextUrl, input);
            }
            return origFetch.call(this, input, init);
        };
    }

    /**
     * Tiny autosave / H5P xAPI use sendBeacon — no custom headers, so sesskey
     * used to sit on the query string. Move it into POST FormData instead.
     */
    if (navigator.sendBeacon) {
        var origBeacon = navigator.sendBeacon.bind(navigator);
        navigator.sendBeacon = function(url, data) {
            var urlStr = urlToString(url);
            var clean = stripSesskeyFromUrl(urlStr);
            if (!isServiceAjax(urlStr)) {
                return origBeacon(clean, data);
            }
            var sk = sesskeyFromUrl(urlStr) || getCfgSesskey();
            var payload = data;
            try {
                if (typeof FormData !== 'undefined') {
                    if (data && typeof FormData !== 'undefined' && data instanceof FormData) {
                        if (sk && typeof data.has === 'function' && !data.has('sesskey')) {
                            data.append('sesskey', sk);
                        } else if (sk && typeof data.has !== 'function') {
                            data.append('sesskey', sk);
                        }
                        payload = data;
                    } else {
                        var fd = new FormData();
                        if (sk) {
                            fd.append('sesskey', sk);
                        }
                        if (typeof data === 'string') {
                            fd.append('args', data);
                        } else if (typeof Blob !== 'undefined' && data instanceof Blob) {
                            fd.append('args', data);
                        } else if (data != null) {
                            fd.append('args', String(data));
                        }
                        payload = fd;
                    }
                }
            } catch (e) {
                payload = data;
            }
            return origBeacon(clean, payload);
        };
    }
})();
