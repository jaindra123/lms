/**
 * Policy: disable paste, drop, and autocomplete on username/password fields.
 * Login + change/set password pages (theme_iiidem2).
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
        'form#login input[type="text"]',
        'form#login input[type="password"]',
        'form.mform input[name="username"]',
        '.loginform input[type="password"]',
        '.loginform input[name="username"]'
    ].join(',');

    function hardenField(el) {
        if (!el || el.nodeType !== 1) {
            return;
        }
        var type = (el.getAttribute('type') || '').toLowerCase();
        var name = (el.getAttribute('name') || '').toLowerCase();
        var id = (el.getAttribute('id') || '').toLowerCase();
        var isUser = name === 'username' || id === 'username';
        var isPass = type === 'password' || name.indexOf('password') !== -1;

        if (!isUser && !isPass) {
            return;
        }

        el.setAttribute('autocomplete', 'off');
        el.setAttribute('autocapitalize', 'off');
        el.setAttribute('autocorrect', 'off');
        el.setAttribute('spellcheck', 'false');
        // Chrome sometimes ignores autocomplete=off; randomize for password fields.
        if (isPass) {
            el.setAttribute('autocomplete', 'new-password');
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
        if (!form || form.getAttribute('data-iiidem-cred-form') === '1') {
            return;
        }
        form.setAttribute('autocomplete', 'off');
        form.setAttribute('data-iiidem-cred-form', '1');
    }

    function scan(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var forms = scope.querySelectorAll
            ? scope.querySelectorAll('form#login, form.loginform, #page-login-index form, #page-login-change_password form, #page-login-set_password form')
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
