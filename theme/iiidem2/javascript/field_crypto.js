/**
 * Encrypt password and MFA OTP in the POST body (AES-256-GCM + RSA-OAEP).
 * CDAC: Sensitive Data Exposure / cryptography failures.
 */
(function () {
    'use strict';

    var NAMES = {
        password: true,
        password1: true,
        password2: true,
        newpassword1: true,
        newpassword2: true,
        oldpassword: true,
        verificationcode: true
    };

    function cfg() {
        var el = document.getElementById('iiidem-field-crypto');
        if (!el) {
            return null;
        }
        try {
            return JSON.parse(el.textContent || '{}');
        } catch (e) {
            return null;
        }
    }

    function b64encode(buf) {
        var bytes = new Uint8Array(buf);
        var binary = '';
        for (var i = 0; i < bytes.length; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

    function pemToSpki(pem) {
        var b64 = String(pem)
            .replace(/-----BEGIN PUBLIC KEY-----/g, '')
            .replace(/-----END PUBLIC KEY-----/g, '')
            .replace(/\s+/g, '');
        var raw = atob(b64);
        var buf = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) {
            buf[i] = raw.charCodeAt(i);
        }
        return buf.buffer;
    }

    function encryptValue(plain, rsaKey) {
        var encoder = new TextEncoder();
        return crypto.subtle.generateKey({name: 'AES-GCM', length: 256}, true, ['encrypt']).then(function (aesKey) {
            var iv = crypto.getRandomValues(new Uint8Array(12));
            return crypto.subtle.encrypt({name: 'AES-GCM', iv: iv}, aesKey, encoder.encode(plain)).then(function (ct) {
                return crypto.subtle.exportKey('raw', aesKey).then(function (rawKey) {
                    return crypto.subtle.encrypt({name: 'RSA-OAEP'}, rsaKey, rawKey).then(function (wrapped) {
                        var payload = JSON.stringify({
                            v: 1,
                            k: b64encode(wrapped),
                            iv: b64encode(iv),
                            c: b64encode(ct)
                        });
                        return payload;
                    });
                });
            });
        });
    }

    function importRsa(pem) {
        return crypto.subtle.importKey(
            'spki',
            pemToSpki(pem),
            {name: 'RSA-OAEP', hash: 'SHA-1'},
            false,
            ['encrypt']
        );
    }

    function nativeSubmit(form) {
        fixLoginAction(form);
        HTMLFormElement.prototype.submit.call(form);
    }

    /**
     * Apache DirectorySlash 301s POST /login → GET /login/ (POST body dropped).
     * Always POST credentials to the real script.
     */
    function fixLoginAction(form) {
        if (!form) {
            return;
        }
        var raw = form.getAttribute('action') || form.action || '';
        var search = '';
        try {
            var resolved = new URL(raw || '/login/index.php', window.location.href);
            var path = resolved.pathname.replace(/\/+$/, '') || '';
            if (path === '/login' || path === '/login/index.php' || path === '') {
                search = resolved.search || '';
                form.setAttribute('action', '/login/index.php' + search);
                return;
            }
        } catch (err) {
            // Fall through.
        }
        if (!raw || raw === '/login' || raw === '/login/' || /\/login\/?$/.test(raw)) {
            form.setAttribute('action', '/login/index.php');
        }
    }

    function bindForm(form, rsaKey, prefix) {
        if (!form || form.getAttribute('data-iiidem-enc') === '1') {
            return;
        }
        form.setAttribute('data-iiidem-enc', '1');
        fixLoginAction(form);
        form.addEventListener('submit', function (e) {
            if (form.getAttribute('data-iiidem-enc-busy') === '1') {
                e.preventDefault();
                e.stopImmediatePropagation();
                return;
            }
            if (form.getAttribute('data-iiidem-enc-done') === '1') {
                return;
            }
            var inputs = form.querySelectorAll('input[name]');
            var targets = [];
            for (var i = 0; i < inputs.length; i++) {
                var name = inputs[i].getAttribute('name') || '';
                if (!NAMES[name]) {
                    continue;
                }
                var val = inputs[i].value || '';
                if (val === '' || val.indexOf(prefix) === 0) {
                    continue;
                }
                targets.push(inputs[i]);
            }
            if (!targets.length) {
                return;
            }
            e.preventDefault();
            e.stopImmediatePropagation();
            form.setAttribute('data-iiidem-enc-busy', '1');
            var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            var chain = Promise.resolve();
            targets.forEach(function (input) {
                chain = chain.then(function () {
                    var plain = input.value;
                    return encryptValue(plain, rsaKey).then(function (payload) {
                        var wrapped = prefix + btoa(payload);
                        var fname = input.getAttribute('name');
                        input.setAttribute('data-iiidem-enc-name', fname);
                        input.removeAttribute('name');
                        input.value = plain;
                        if ((input.getAttribute('type') || '') === 'text' && fname.indexOf('password') !== -1) {
                            input.setAttribute('type', 'password');
                        }
                        var hidden = form.querySelector('input[type="hidden"][name="' + fname + '"]');
                        if (!hidden) {
                            hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = fname;
                            form.appendChild(hidden);
                        }
                        hidden.value = wrapped;
                        // Do not leave plaintext in the visible field for Chrome's save dialog.
                        if (fname === 'password' || fname.indexOf('password') !== -1 || fname === 'verificationcode') {
                            input.value = '';
                        }
                        hidden.setAttribute('autocomplete', 'off');
                    });
                });
            });
            chain.then(function () {
                form.removeAttribute('data-iiidem-enc-busy');
                form.setAttribute('data-iiidem-enc-done', '1');
                nativeSubmit(form);
            }).catch(function () {
                form.removeAttribute('data-iiidem-enc-busy');
                form.removeAttribute('data-iiidem-enc-done');
                var restored = form.querySelectorAll('input[data-iiidem-enc-name]');
                for (var r = 0; r < restored.length; r++) {
                    restored[r].setAttribute('name', restored[r].getAttribute('data-iiidem-enc-name'));
                    restored[r].removeAttribute('data-iiidem-enc-name');
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                // Encrypt failed — still submit so the first Login click is not a no-op.
                nativeSubmit(form);
            });
        }, true);
    }

    function boot() {
        var scanFix = function (root) {
            var forms = (root.querySelectorAll ? root : document).querySelectorAll('form');
            for (var i = 0; i < forms.length; i++) {
                fixLoginAction(forms[i]);
            }
        };
        scanFix(document);

        if (!window.crypto || !crypto.subtle) {
            return;
        }
        var meta = cfg();
        if (!meta || !meta.pem) {
            return;
        }
        var prefix = meta.prefix || 'iiidemenc.';
        importRsa(meta.pem).then(function (rsaKey) {
            var scan = function (root) {
                var forms = (root.querySelectorAll ? root : document).querySelectorAll('form');
                for (var i = 0; i < forms.length; i++) {
                    bindForm(forms[i], rsaKey, prefix);
                }
            };
            scan(document);
            if (typeof MutationObserver !== 'undefined') {
                var obs = new MutationObserver(function (mutations) {
                    for (var i = 0; i < mutations.length; i++) {
                        var nodes = mutations[i].addedNodes;
                        for (var j = 0; j < nodes.length; j++) {
                            if (nodes[j].nodeType === 1) {
                                scan(nodes[j]);
                            }
                        }
                    }
                });
                obs.observe(document.documentElement, {childList: true, subtree: true});
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
