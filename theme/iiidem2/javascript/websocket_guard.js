/**
 * Block LMS-origin risk/checkout/telemetry that must not run on this site.
 *
 * - WebSocket to *.sardine.ai (deviceToken)
 * - XHR/fetch to Razorpay standard_checkout prefill/encrypt
 * - Sentry Browser SDK (Razorpay ships 7.64.0 on api.razorpay.com — not on this origin)
 *
 * Hosted Payment Links run off-origin; this guard covers the LMS origin only.
 */
(function() {
    'use strict';

    function hostnameOf(url) {
        try {
            return new URL(String(url), window.location.href).hostname || '';
        } catch (e) {
            return '';
        }
    }

    function pathnameOf(url) {
        try {
            return new URL(String(url), window.location.href).pathname || '';
        } catch (e2) {
            return String(url || '');
        }
    }

    function isSardine(url) {
        return /(^|\.)sardine\.ai$/i.test(hostnameOf(url));
    }

    function isSentry(url) {
        var host = hostnameOf(url);
        var path = pathnameOf(url);
        var raw = String(url || '');
        if (/(^|\.)sentry\.io$/i.test(host) || /(^|\.)sentry-cdn\.com$/i.test(host)) {
            return true;
        }
        if (/sentry_key=|sentry_client=/i.test(raw) || /sentry_key=|sentry_client=/i.test(path)) {
            return true;
        }
        return /\/api\/\d+\/envelope\/?/i.test(path);
    }

    function isPrefillEncrypt(url) {
        var host = hostnameOf(url);
        var path = pathnameOf(url);
        return /(^|\.)razorpay\.com$/i.test(host) && /prefill\/encrypt/i.test(path);
    }

    function isRazorpayHost(url) {
        var host = hostnameOf(url);
        return /(^|\.)razorpay\.com$/i.test(host) || /(^|\.)rzp\.io$/i.test(host);
    }

    function isCheckoutPublic(url) {
        var path = pathnameOf(url);
        return isRazorpayHost(url) && /\/v1\/checkout\/public|checkout\.js|standard_checkout/i.test(path + String(url || ''));
    }

    function isBlocked(url) {
        return isSardine(url) || isPrefillEncrypt(url) || isSentry(url) || isCheckoutPublic(url)
            || isRazorpayHost(url);
    }

    function urlFromFetchInput(input) {
        if (typeof input === 'string') {
            return input;
        }
        if (input && typeof input.url === 'string') {
            return input.url;
        }
        return '';
    }

    var nativeFetch = window.fetch;
    if (typeof nativeFetch === 'function') {
        window.fetch = function(input, init) {
            var url = urlFromFetchInput(input);
            if (isBlocked(url)) {
                return Promise.reject(new TypeError('Request blocked.'));
            }
            return nativeFetch.apply(this, arguments);
        };
    }

    if (window.XMLHttpRequest && XMLHttpRequest.prototype) {
        var nativeOpen = XMLHttpRequest.prototype.open;
        var nativeSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function(method, url) {
            this._iiidemBlocked = isBlocked(url);
            return nativeOpen.apply(this, arguments);
        };
        XMLHttpRequest.prototype.send = function() {
            if (this._iiidemBlocked) {
                throw new DOMException('Request blocked.', 'SecurityError');
            }
            return nativeSend.apply(this, arguments);
        };
    }

    var NativeWS = window.WebSocket;
    var openSockets = [];

    function forget(ws) {
        var i = openSockets.indexOf(ws);
        if (i !== -1) {
            openSockets.splice(i, 1);
        }
    }

    function track(ws) {
        openSockets.push(ws);
        var drop = function() {
            forget(ws);
        };
        try {
            ws.addEventListener('close', drop);
            ws.addEventListener('error', drop);
        } catch (e) {
            // ignore
        }
    }

    if (NativeWS) {
        function GuardedWebSocket(url, protocols) {
            if (isSardine(url) || isSentry(url) || isRazorpayHost(url)) {
                throw new DOMException('WebSocket to this host is not allowed.', 'SecurityError');
            }
            var ws = protocols !== undefined ? new NativeWS(url, protocols) : new NativeWS(url);
            track(ws);
            return ws;
        }

        GuardedWebSocket.prototype = NativeWS.prototype;
        GuardedWebSocket.CONNECTING = NativeWS.CONNECTING;
        GuardedWebSocket.OPEN = NativeWS.OPEN;
        GuardedWebSocket.CLOSING = NativeWS.CLOSING;
        GuardedWebSocket.CLOSED = NativeWS.CLOSED;
        if (NativeWS.prototype) {
            GuardedWebSocket.prototype.constructor = GuardedWebSocket;
        }
        try {
            window.WebSocket = GuardedWebSocket;
        } catch (e3) {
            // ignore
        }
    }

    if (navigator.sendBeacon) {
        var nativeBeacon = navigator.sendBeacon.bind(navigator);
        navigator.sendBeacon = function(url) {
            if (isBlocked(url)) {
                return false;
            }
            return nativeBeacon.apply(navigator, arguments);
        };
    }

    function hideSessionToken() {
        try {
            try {
                delete window.session_token;
            } catch (eTok) {
                // ignore
            }
            Object.defineProperty(window, 'session_token', {
                configurable: true,
                enumerable: false,
                get: function() {
                    return undefined;
                },
                set: function() {
                    // Do not keep Razorpay Checkout tokens on the LMS origin.
                }
            });
        } catch (e9) {
            // ignore
        }
    }

    hideSessionToken();

    function blockFrameSrc(proto) {
        if (!proto) {
            return;
        }
        try {
            var nativeSet = proto.setAttribute;
            if (typeof nativeSet === 'function') {
                proto.setAttribute = function(name, value) {
                    if (String(name).toLowerCase() === 'src' && isBlocked(value)) {
                        return;
                    }
                    return nativeSet.apply(this, arguments);
                };
            }
            var desc = Object.getOwnPropertyDescriptor(proto, 'src');
            if (desc && desc.set) {
                Object.defineProperty(proto, 'src', {
                    configurable: true,
                    enumerable: desc.enumerable,
                    get: function() {
                        return desc.get.call(this);
                    },
                    set: function(v) {
                        if (isBlocked(v)) {
                            return;
                        }
                        return desc.set.call(this, v);
                    }
                });
            }
        } catch (e10) {
            // ignore
        }
    }

    blockFrameSrc(window.HTMLIFrameElement && HTMLIFrameElement.prototype);
    blockFrameSrc(window.HTMLEmbedElement && HTMLEmbedElement.prototype);
    blockFrameSrc(window.HTMLObjectElement && HTMLObjectElement.prototype);

    function noop() {
        return undefined;
    }

    function sentryHub() {
        return {
            captureException: noop,
            captureMessage: noop,
            captureEvent: noop,
            addBreadcrumb: noop,
            configureScope: noop,
            withScope: function(cb) {
                if (typeof cb === 'function') {
                    try {
                        cb({ setUser: noop, setTag: noop, setExtra: noop, setLevel: noop });
                    } catch (e6) {
                        // ignore
                    }
                }
            },
            getClient: function() {
                return { getDsn: function() { return undefined; } };
            }
        };
    }

    function stubSentry() {
        var client = {
            init: noop,
            captureException: noop,
            captureMessage: noop,
            captureEvent: noop,
            addBreadcrumb: noop,
            configureScope: noop,
            withScope: sentryHub().withScope,
            setUser: noop,
            setTag: noop,
            setExtra: noop,
            setContext: noop,
            close: function() { return Promise.resolve(true); },
            flush: function() { return Promise.resolve(true); },
            getCurrentHub: sentryHub,
            SDK_VERSION: 'disabled'
        };
        try {
            window.Sentry = client;
        } catch (e7) {
            // ignore
        }
    }

    stubSentry();

    function isRiskSrc(src) {
        return isBlocked(src) || /sardine\.ai|razorpay\.com|rzp\.io|sentry\.io|sentry-cdn\.com/i.test(src || '');
    }

    function removeRiskFrames() {
        var nodes = document.querySelectorAll('iframe, script, object, embed, link');
        for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i];
            var src = el.getAttribute('src') || el.getAttribute('href') || el.getAttribute('data') || '';
            if (isRiskSrc(src)) {
                el.parentNode && el.parentNode.removeChild(el);
            }
        }
        var overlay = document.querySelectorAll(
            '.razorpay-container, .razorpay-backdrop, .razorpay-checkout-frame'
        );
        for (var j = 0; j < overlay.length; j++) {
            overlay[j].parentNode && overlay[j].parentNode.removeChild(overlay[j]);
        }
        stubSentry();
    }

    if (window.MutationObserver) {
        try {
            var mo = new MutationObserver(function(mutations) {
                for (var m = 0; m < mutations.length; m++) {
                    var mut = mutations[m];
                    if (mut.type === 'attributes' && mut.target && mut.target.nodeType === 1) {
                        var tsrc = mut.target.getAttribute
                            ? (mut.target.getAttribute('src') || mut.target.getAttribute('href')
                                || mut.target.getAttribute('data') || '')
                            : '';
                        if (isRiskSrc(tsrc)) {
                            mut.target.parentNode && mut.target.parentNode.removeChild(mut.target);
                        }
                        continue;
                    }
                    var added = mut.addedNodes;
                    for (var n = 0; n < added.length; n++) {
                        var node = added[n];
                        if (!node || node.nodeType !== 1) {
                            continue;
                        }
                        var tag = (node.tagName || '').toLowerCase();
                        var src = node.getAttribute && (node.getAttribute('src') || node.getAttribute('href') || '');
                        if ((tag === 'script' || tag === 'iframe' || tag === 'embed' || tag === 'object' || tag === 'link')
                                && isRiskSrc(src)) {
                            node.parentNode && node.parentNode.removeChild(node);
                        }
                    }
                }
            });
            mo.observe(document.documentElement, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['src', 'href', 'data']
            });
        } catch (e8) {
            // ignore
        }
    }

    function closeRiskSockets() {
        var copy = openSockets.slice();
        openSockets.length = 0;
        for (var i = 0; i < copy.length; i++) {
            try {
                copy[i].close();
            } catch (e4) {
                // ignore
            }
        }
        try {
            var keys = [];
            for (var s = 0; s < sessionStorage.length; s++) {
                keys.push(sessionStorage.key(s));
            }
            keys.forEach(function(k) {
                if (k && /sardine|deviceToken|deviceId|prefill_data|sentry|session_token/i.test(k)) {
                    sessionStorage.removeItem(k);
                }
            });
        } catch (e5) {
            // ignore
        }
        removeRiskFrames();
        stubSentry();
        hideSessionToken();
    }

    window.iiidemCloseRiskSockets = closeRiskSockets;

    document.addEventListener('click', function(e) {
        var a = e.target && e.target.closest ? e.target.closest('a[href*="logout.php"]') : null;
        if (a) {
            closeRiskSockets();
        }
    }, true);

    window.addEventListener('pagehide', closeRiskSockets);
})();
