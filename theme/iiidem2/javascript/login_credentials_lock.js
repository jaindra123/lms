/**
 * Do not let the browser store or autofill login passwords (CDAC: Password stored in browser).
 * Login + MFA + change/set password (theme_iiidem2).
 */
(function () {
    'use strict';

    var SELECTOR = [
        'input#username',
        'input[name="username"]',
        'input#password',
        'input[name="password"]',
        'input[name="password1"]',
        'input[name="password2"]',
        'input[name="newpassword1"]',
        'input[name="newpassword2"]',
        'input[name="password"][type="password"]',
        'input[type="password"]',
        'input[name="verificationcode"]',
        '#id_verificationcode',
        'form#login input[type="text"]',
        'form#login input[type="password"]',
        'form.mform input[name="username"]',
        '.loginform input[type="password"]',
        '.loginform input[name="username"]'
    ].join(',');

    function ignoreManagers(el) {
        el.setAttribute('data-lpignore', 'true');
        el.setAttribute('data-1p-ignore', 'true');
        el.setAttribute('data-bwignore', 'true');
        el.setAttribute('data-form-type', 'other');
    }

    function hardenField(el) {
        if (!el || el.nodeType !== 1) {
            return;
        }
        var type = (el.getAttribute('type') || '').toLowerCase();
        var name = (el.getAttribute('name') || '').toLowerCase();
        var id = (el.getAttribute('id') || '').toLowerCase();
        var isUser = name === 'username' || id === 'username';
        var isOtp = name === 'verificationcode' || id === 'id_verificationcode';
        var isPass = type === 'password' || name.indexOf('password') !== -1;

        if (!isUser && !isPass && !isOtp) {
            return;
        }

        el.setAttribute('autocapitalize', 'off');
        el.setAttribute('autocorrect', 'off');
        el.setAttribute('spellcheck', 'false');
        ignoreManagers(el);

        if (isOtp) {
            el.setAttribute('autocomplete', 'one-time-code');
            el.setAttribute('data-iiidem-cred-lock', '1');
            return;
        }

        // Never current-password / username — Chrome then offers "Save your password"
        // on the next page (MFA). Change-password still uses new-password.
        var isCreate = name === 'password1' || name === 'password2'
            || name === 'newpassword1' || name === 'newpassword2'
            || name === 'newpassword' || name === 'changepassword';
        if (isPass && isCreate) {
            el.setAttribute('autocomplete', 'new-password');
        } else {
            el.setAttribute('autocomplete', 'off');
        }

        el.setAttribute('data-iiidem-cred-lock', '1');

        ['paste', 'drop', 'dragover', 'dragenter', 'copy', 'cut'].forEach(function (evt) {
            el.addEventListener(evt, function (e) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }, true);
        });
    }

    function hardenForm(form) {
        if (!form) {
            return;
        }
        form.setAttribute('autocomplete', 'off');
        form.setAttribute('data-lpignore', 'true');
        form.setAttribute('data-1p-ignore', 'true');
        form.setAttribute('data-iiidem-cred-form', '1');
    }

    function scan(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var forms = scope.querySelectorAll
            ? scope.querySelectorAll(
                'form#login, form.loginform, form.login-form, #page-login-index form, '
                + '#page-login-change_password form, #page-login-set_password form, '
                + '#page-admin-tool-mfa-auth form, form.mfa-verify-form, .mfa-verify form'
            )
            : [];
        for (var f = 0; f < forms.length; f++) {
            hardenForm(forms[f]);
        }
        var fields = scope.querySelectorAll ? scope.querySelectorAll(SELECTOR) : [];
        for (var i = 0; i < fields.length; i++) {
            hardenField(fields[i]);
        }
    }

    function boot() {
        scan(document);
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function (mutations) {
                for (var i = 0; i < mutations.length; i++) {
                    var nodes = mutations[i].addedNodes;
                    for (var j = 0; j < nodes.length; j++) {
                        if (nodes[j].nodeType === 1) {
                            scan(nodes[j]);
                        }
                    }
                }
            });
            observer.observe(document.documentElement, {childList: true, subtree: true});
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
