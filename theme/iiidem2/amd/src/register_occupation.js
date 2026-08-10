/**
 * Registration form helpers: required icons, phone country code.
 *
 * @module theme_iiidem2/register_occupation
 */
define([], function() {

    let phoneIti = null;

    /**
     * @param {HTMLElement} field
     * @returns {boolean}
     */
    function fieldHasValue(field) {
        if (!field) {
            return false;
        }

        const type = (field.type || '').toLowerCase();
        if (type === 'checkbox' || type === 'radio') {
            if (type === 'radio' && field.name) {
                const group = document.querySelectorAll('input[type="radio"][name="' + field.name + '"]');
                return Array.prototype.some.call(group, (el) => el.checked);
            }
            return field.checked;
        }

        return String(field.value || '').trim() !== '';
    }

    /**
     * Find the Moodle required marker inside a field row.
     *
     * @param {HTMLElement} fitem
     * @returns {{wrapper: HTMLElement|null, icon: HTMLElement|null}}
     */
    function getRequiredMarker(fitem) {
        if (!fitem) {
            return {wrapper: null, icon: null};
        }

        const addon = fitem.querySelector('.form-label-addon');
        if (!addon) {
            return {wrapper: null, icon: null};
        }

        const wrapper = addon.querySelector('.text-danger, .text-success, [title="Required"], [title="Completed"]')
            || addon.firstElementChild;
        const icon = addon.querySelector('.icon, i');

        return {wrapper: wrapper, icon: icon};
    }

    /**
     * Switch Moodle required icon between red (empty) and green (filled).
     *
     * @param {HTMLElement} fitem
     * @param {boolean} isValid
     */
    function setRequiredIconState(fitem, isValid) {
        if (!fitem) {
            return;
        }

        fitem.classList.toggle('iiidem-field-valid', isValid);

        const marker = getRequiredMarker(fitem);
        const wrapper = marker.wrapper;
        const icon = marker.icon;

        if (wrapper) {
            wrapper.classList.toggle('text-danger', !isValid);
            wrapper.classList.toggle('text-success', isValid);
            wrapper.setAttribute('title', isValid ? 'Completed' : 'Required');
        }

        if (icon) {
            icon.classList.toggle('text-danger', !isValid);
            icon.classList.toggle('text-success', isValid);
            // Prefer colour change; keep a recognizable glyph in both states.
            icon.classList.toggle('fa-circle-exclamation', !isValid);
            icon.classList.toggle('fa-exclamation-circle', !isValid);
            icon.classList.toggle('fa-circle-check', isValid);
            icon.classList.toggle('fa-check-circle', isValid);
        }
    }

    /**
     * Show / clear an inline error under a Moodle form field.
     *
     * @param {HTMLElement} field
     * @param {string} message
     */
    function setFieldError(field, message) {
        if (!field) {
            return;
        }
        const fitem = field.closest('.fitem');
        const feedback = document.getElementById('id_error_' + field.name)
            || (fitem ? fitem.querySelector('.invalid-feedback, .form-control-feedback') : null);

        if (message) {
            field.classList.add('is-invalid');
            if (fitem) {
                fitem.classList.add('has-danger');
                fitem.classList.remove('iiidem-field-valid');
            }
            if (feedback) {
                feedback.textContent = message;
                feedback.style.display = 'block';
            }
        } else {
            field.classList.remove('is-invalid');
            if (fitem) {
                fitem.classList.remove('has-danger');
            }
            if (feedback) {
                feedback.textContent = '';
                feedback.style.display = '';
            }
        }
    }

    /**
     * @param {HTMLElement} phoneField
     * @returns {boolean}
     */
    function isPhoneValid(phoneField) {
        if (!phoneField) {
            return false;
        }
        let digits = String(phoneField.value || '').replace(/\D/g, '');
        if (digits.length > 10 && digits.indexOf('91') === 0) {
            digits = digits.substring(2);
        } else if (digits.length >= 11 && digits.charAt(0) === '0') {
            digits = digits.substring(1);
        }
        if (digits.length > 15) {
            digits = digits.slice(0, 15);
        }
        return /^[0-9]{4,15}$/.test(digits);
    }

    /**
     * Update required icon for one field.
     *
     * @param {HTMLElement} field
     */
    function syncField(field) {
        if (!field) {
            return;
        }

        const fitem = field.closest('.fitem');
        if (!fitem || !fitem.querySelector('.form-label-addon')) {
            return;
        }

        if (field.id === 'id_phone1') {
            const valid = isPhoneValid(field);
            const existsMsg = field.getAttribute('data-phone-exists-message') || '';
            const feedback = document.getElementById('id_error_' + field.name)
                || (fitem ? fitem.querySelector('.invalid-feedback, .form-control-feedback') : null);
            const feedbackText = feedback ? String(feedback.textContent || '').trim() : '';
            const isDuplicateError = field.getAttribute('data-phone-status') === 'error'
                || (existsMsg && feedbackText === existsMsg)
                || /already registered/i.test(feedbackText);

            // Format-valid numbers can still be duplicates — do not wipe that error.
            setRequiredIconState(fitem, valid && !isDuplicateError);
            if (valid && !isDuplicateError) {
                setFieldError(field, '');
            }
            return;
        }

        setRequiredIconState(fitem, fieldHasValue(field));
    }

    /**
     * Collect required fields (aria-required or required attribute).
     *
     * @param {HTMLElement} form
     * @returns {HTMLElement[]}
     */
    function getRequiredFields(form) {
        const nodes = form.querySelectorAll(
            'input[aria-required="true"], select[aria-required="true"], textarea[aria-required="true"],' +
            'input[required], select[required], textarea[required]'
        );
        // De-dupe radios by name so one update covers the group.
        const seenRadioNames = {};
        const fields = [];
        nodes.forEach((field) => {
            if ((field.type || '').toLowerCase() === 'radio') {
                if (seenRadioNames[field.name]) {
                    return;
                }
                seenRadioNames[field.name] = true;
            }
            fields.push(field);
        });
        return fields;
    }

    /**
     * Turn required red icons green once fields have a value.
     */
    function initRequiredIndicators() {
        const form = document.querySelector('.iiidem-register-form form, form.mform');
        if (!form) {
            return;
        }

        const fields = getRequiredFields(form);
        fields.forEach((field) => {
            syncField(field);
        });

        // Event delegation so it keeps working if Moodle rewrites nodes.
        const refresh = function(event) {
            const target = event.target;
            if (!target || !target.closest) {
                return;
            }
            if (!target.matches('input, select, textarea')) {
                return;
            }
            if (target.getAttribute('aria-required') === 'true' || target.required) {
                syncField(target);
                return;
            }
            // Password / other fields that gained required via JS.
            const fitem = target.closest('.fitem');
            if (fitem && fitem.querySelector('.form-label-addon .text-danger, .form-label-addon .text-success')) {
                syncField(target);
            }
        };

        form.addEventListener('input', refresh, true);
        form.addEventListener('change', refresh, true);
        form.addEventListener('keyup', refresh, true);
        form.addEventListener('blur', refresh, true);
        form.addEventListener('focusout', refresh, true);
    }

    /**
     * Country-code phone input + validation (intl-tel-input).
     */
    function initPhoneCountryCode() {
        const input = document.getElementById('id_phone1');
        if (!input || typeof window.intlTelInput !== 'function') {
            return;
        }
        // Inline register script may already have initialised the widget.
        if (input.getAttribute('data-iti-ready') === '1' || input.closest('.iti')) {
            return;
        }

        const utilsUrl = M.cfg.wwwroot + '/theme/iiidem2/javascript/intl-tel-input/utils.js';
        const countrySelect = document.getElementById('id_country');
        const initialCountry = (countrySelect && countrySelect.value)
            ? String(countrySelect.value).toLowerCase()
            : 'in';

        input.setAttribute('type', 'tel');
        input.setAttribute('autocomplete', 'tel');
        input.setAttribute('inputmode', 'tel');
        input.classList.add('iiidem-phone-input');

        phoneIti = window.intlTelInput(input, {
            initialCountry: initialCountry || 'in',
            preferredCountries: ['in', 'us', 'gb', 'ae', 'sg'],
            showSelectedDialCode: true,
            // No combobox role → Moodle aria.js will not rewrite the button.
            countrySearch: false,
            nationalMode: true,
            autoPlaceholder: 'aggressive',
            formatOnDisplay: true,
            utilsScript: utilsUrl,
        });

        const syncCountryFromPhone = function() {
            if (!countrySelect || !phoneIti) {
                return;
            }
            const data = phoneIti.getSelectedCountryData();
            if (data && data.iso2) {
                const iso = String(data.iso2).toUpperCase();
                if ([].some.call(countrySelect.options, (opt) => opt.value === iso)) {
                    countrySelect.value = iso;
                    syncField(countrySelect);
                }
            }
        };

        const syncPhoneFromCountry = function() {
            if (!countrySelect || !phoneIti || !countrySelect.value) {
                return;
            }
            phoneIti.setCountry(String(countrySelect.value).toLowerCase());
        };

        input.addEventListener('countrychange', syncCountryFromPhone);
        if (countrySelect) {
            countrySelect.addEventListener('change', syncPhoneFromCountry);
        }

        // Re-sync phone required icon after utils load / typing.
        input.addEventListener('input', function() {
            syncField(input);
        });
        input.addEventListener('blur', function() {
            syncField(input);
        });

        const form = input.closest('form');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function(e) {
            const value = String(input.value || '').trim();
            if (value === '') {
                return;
            }

            const invalidMessage = input.getAttribute('data-invalid-phone')
                || 'Enter a valid contact number (digits only).';

            if (!isPhoneValid(input)) {
                e.preventDefault();
                e.stopPropagation();
                setFieldError(input, invalidMessage);
                setRequiredIconState(input.closest('.fitem'), false);
                input.focus();
                return false;
            }

            if (phoneIti && typeof phoneIti.getNumber === 'function') {
                input.setAttribute('maxlength', '20');
                const countryData = phoneIti.getSelectedCountryData();
                const dial = (countryData && countryData.dialCode) ? String(countryData.dialCode) : '91';
                let digits = String(input.value || '').replace(/\D/g, '').slice(0, 15);
                phoneIti.setNumber('+' + dial + digits);
                input.value = phoneIti.getNumber();
            }
            setFieldError(input, '');
            return true;
        }, true);
    }

    /**
     * Initialise registration form behaviours.
     */
    function init() {
        // Required icons first — must not depend on phone widget succeeding.
        try {
            initRequiredIndicators();
        } catch (e) {
            window.console && console.error('register_occupation required icons', e);
        }

        try {
            initPhoneCountryCode();
        } catch (e) {
            window.console && console.error('register_occupation phone', e);
        }

        // Country defaults to a value — ensure it shows green after phone init.
        const country = document.getElementById('id_country');
        if (country) {
            syncField(country);
        }
    }

    return {
        init: init,
    };
});
