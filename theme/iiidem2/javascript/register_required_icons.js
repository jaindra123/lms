/**
 * Fallback (non-AMD) required-field icon sync for /register/.
 * Loaded via $PAGE->requires->js so it always runs even if AMD fails.
 */
(function() {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function fieldHasValue(field, form) {
        if (!field) {
            return false;
        }
        var type = (field.type || '').toLowerCase();
        if (type === 'checkbox') {
            return !!field.checked;
        }
        if (type === 'radio') {
            var group = form.querySelectorAll('input[type="radio"][name="' + field.name + '"]');
            for (var i = 0; i < group.length; i++) {
                if (group[i].checked) {
                    return true;
                }
            }
            return false;
        }
        return String(field.value || '').trim() !== '';
    }

    function sync(field, form) {
        if (!field) {
            return;
        }
        var item = field.closest('.fitem');
        if (!item) {
            return;
        }
        var addon = item.querySelector('.form-label-addon');
        if (!addon) {
            return;
        }
        var marker = addon.querySelector('.text-danger, .text-success, [title="Required"], [title="Completed"]')
            || addon.firstElementChild;
        var icon = addon.querySelector('.icon, i');
        var hasValue = fieldHasValue(field, form);

        item.classList.toggle('iiidem-field-valid', hasValue);

        if (marker) {
            marker.classList.toggle('text-danger', !hasValue);
            marker.classList.toggle('text-success', hasValue);
            marker.setAttribute('title', hasValue ? 'Completed' : 'Required');
            marker.style.color = hasValue ? '#198754' : '#dc3545';
        }
        if (icon) {
            icon.classList.toggle('text-danger', !hasValue);
            icon.classList.toggle('text-success', hasValue);
            icon.classList.toggle('fa-circle-exclamation', !hasValue);
            icon.classList.toggle('fa-exclamation-circle', !hasValue);
            icon.classList.toggle('fa-circle-check', hasValue);
            icon.classList.toggle('fa-check-circle', hasValue);
            icon.style.color = hasValue ? '#198754' : '#dc3545';
        }
    }

    ready(function() {
        var form = document.querySelector('.iiidem-register-form form.mform, form.mform');
        if (!form) {
            return;
        }

        var fields = form.querySelectorAll(
            'input[aria-required="true"], select[aria-required="true"], textarea[aria-required="true"]'
        );
        var seenRadios = {};
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            if ((field.type || '').toLowerCase() === 'radio') {
                if (seenRadios[field.name]) {
                    continue;
                }
                seenRadios[field.name] = true;
            }
            sync(field, form);
        }

        var onAny = function(e) {
            var t = e.target;
            if (!t || !t.matches || !t.matches('input, select, textarea')) {
                return;
            }
            sync(t, form);
        };
        form.addEventListener('input', onAny, true);
        form.addEventListener('change', onAny, true);
        form.addEventListener('keyup', onAny, true);
        form.addEventListener('blur', onAny, true);
    });
})();
