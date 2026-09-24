/**
 * Client-side password policy on /register/ (server still authoritative).
 * Weak values such as "test" cannot be submitted.
 */
(function () {
    'use strict';

    function extraErrors(password, email) {
        var errors = [];
        if (password.length < 8) {
            errors.push('at least 8 characters');
        }
        if (!/[a-z]/.test(password)) {
            errors.push('a lowercase letter');
        }
        if (!/[A-Z]/.test(password)) {
            errors.push('an uppercase letter');
        }
        if (!/[0-9]/.test(password)) {
            errors.push('a digit');
        }
        if (!/[^A-Za-z0-9]/.test(password)) {
            errors.push('a special character');
        }
        if (email) {
            var em = String(email).toLowerCase();
            var local = em.split('@')[0] || '';
            var lower = password.toLowerCase();
            if (lower === em || (local && lower === local)) {
                errors.push('must not match your email or username');
            }
        }
        return errors;
    }

    function showError(input, message) {
        var item = input.closest('.fitem') || input.parentElement;
        if (!item) {
            return;
        }
        var box = item.querySelector('.iiidem-pwpolicy-error');
        if (!box) {
            box = document.createElement('div');
            box.className = 'iiidem-pwpolicy-error invalid-feedback d-block';
            item.appendChild(box);
        }
        box.textContent = message;
        input.classList.toggle('is-invalid', !!message);
        input.setAttribute('aria-invalid', message ? 'true' : 'false');
    }

    function bind() {
        var form = document.querySelector('.iiidem-register-form form.mform, form.mform');
        if (!form || form.getAttribute('data-iiidem-pwpolicy') === '1') {
            return;
        }
        form.setAttribute('data-iiidem-pwpolicy', '1');

        var password = form.querySelector('input[name="password"]');
        var email = form.querySelector('input[name="email"]');
        if (!password) {
            return;
        }

        password.setAttribute('minlength', '8');
        password.setAttribute('autocomplete', 'new-password');

        var check = function () {
            var pw = String(password.value || '');
            var em = email ? String(email.value || '').trim() : '';
            var errors = extraErrors(pw, em);
            if (pw && errors.length) {
                showError(password, 'Password must have ' + errors.join(', ') + '.');
                return false;
            }
            showError(password, '');
            return true;
        };

        password.addEventListener('input', check);
        password.addEventListener('blur', check);
        if (email) {
            email.addEventListener('change', check);
        }

        form.addEventListener('submit', function (event) {
            if (!check()) {
                event.preventDefault();
                event.stopPropagation();
                password.focus();
            }
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
