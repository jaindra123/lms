/**
 * Registration form helpers: occupation exclusivity, required icons, phone country code.
 *
 * @module theme_iiidem2/register_occupation
 */
define([], function() {

    const CHECKBOXES = [
        'occupation_working',
        'occupation_student',
        'occupation_instructor',
    ];

    let phoneIti = null;

    /**
     * Keep only one occupation checkbox selected at a time.
     */
    function initOccupationCheckboxes() {
        const elements = CHECKBOXES
            .map((name) => document.getElementById('id_' + name))
            .filter(Boolean);

        if (!elements.length) {
            return;
        }

        elements.forEach((checkbox) => {
            checkbox.addEventListener('change', function() {
                if (!checkbox.checked) {
                    return;
                }
                elements.forEach((other) => {
                    if (other !== checkbox) {
                        other.checked = false;
                        other.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                });
            });
        });
    }

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
            return field.checked;
        }

        if (field.tagName === 'SELECT') {
            return String(field.value || '').trim() !== '';
        }

        return String(field.value || '').trim() !== '';
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

        const wrapper = fitem.querySelector('.form-label-addon .text-danger, .form-label-addon .text-success');
        const icon = fitem.querySelector('.form-label-addon .icon, .form-label-addon i');

        if (wrapper) {
            wrapper.classList.toggle('text-danger', !isValid);
            wrapper.classList.toggle('text-success', isValid);
            wrapper.setAttribute('title', isValid ? 'Completed' : 'Required');
        }

        if (icon) {
            icon.classList.toggle('text-danger', !isValid);
            icon.classList.toggle('text-success', isValid);
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
        const value = String(phoneField.value || '').trim();
        if (value === '') {
            return false;
        }
        if (phoneIti && typeof phoneIti.isValidNumber === 'function') {
            return !!phoneIti.isValidNumber();
        }
        // Fallback if utils have not loaded yet.
        return /^\+?[0-9\s\-()]{7,20}$/.test(value);
    }

    /**
     * Turn required red icons green once fields have a value.
     */
    function initRequiredIndicators() {
        const form = document.querySelector('.iiidem-register-form form, form.mform');
        if (!form) {
            return;
        }

        const fields = form.querySelectorAll(
            'input[aria-required="true"], select[aria-required="true"], textarea[aria-required="true"]'
        );

        fields.forEach((field) => {
            const fitem = field.closest('.fitem');
            if (!fitem || !fitem.querySelector('.form-label-addon')) {
                return;
            }

            const update = function() {
                if (field.id === 'id_phone1') {
                    const valid = isPhoneValid(field);
                    setRequiredIconState(fitem, valid);
                    if (valid) {
                        setFieldError(field, '');
                    }
                    return;
                }
                setRequiredIconState(fitem, fieldHasValue(field));
            };

            field.addEventListener('input', update);
            field.addEventListener('change', update);
            field.addEventListener('blur', update);
            update();
        });
    }

    /**
     * Country-code phone input + validation (intl-tel-input).
     */
    function initPhoneCountryCode() {
        const input = document.getElementById('id_phone1');
        if (!input || typeof window.intlTelInput !== 'function') {
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
            separateDialCode: true,
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
                || 'Please enter a valid contact number with country code.';

            if (phoneIti && typeof phoneIti.isValidNumber === 'function' && !phoneIti.isValidNumber()) {
                e.preventDefault();
                e.stopPropagation();
                setFieldError(input, invalidMessage);
                setRequiredIconState(input.closest('.fitem'), false);
                input.focus();
                return false;
            }

            if (phoneIti && typeof phoneIti.getNumber === 'function') {
                input.value = phoneIti.getNumber(); // E.164 e.g. +9198xxxxxxxx
            }
            setFieldError(input, '');
            return true;
        }, true);
    }

    /**
     * Initialise registration form behaviours.
     */
    function init() {
        initOccupationCheckboxes();
        initPhoneCountryCode();
        initRequiredIndicators();
    }

    return {
        init: init,
    };
});
