<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Custom registration page (clean URL: /register/).
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

global $SESSION;
if (isloggedin() && !isguestuser()) {
    redirect(new moodle_url('/'));
}

$PAGE->set_context(context_system::instance());
$PAGE->set_course($SITE);
$PAGE->set_url(new moodle_url('/register/'));
$PAGE->set_pagelayout('register');
$PAGE->set_cacheable(false);
$PAGE->set_title(get_string('registerpagetitle', 'theme_iiidem2'));
$PAGE->set_heading(get_string('registerpagetitle', 'theme_iiidem2'));
$PAGE->requires->css('/theme/iiidem2/style/intl-tel-input/intlTelInput.min.css');
// Load in head so country-code widget is available before form scripts run.
$PAGE->requires->js(new moodle_url('/theme/iiidem2/javascript/intl-tel-input/intlTelInput.min.js'), false);
$PAGE->requires->js_call_amd('theme_iiidem2/register_occupation', 'init');

$form = new \theme_iiidem2\form\register_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/login/index.php'));
}

if ($data = $form->get_data()) {
    try {
        $submission = (object) array_merge((array) $_POST, (array) $data);
        $userid = theme_iiidem2_create_registered_user($submission);
        $user = core_user::get_user($userid);
        theme_iiidem2_send_registration_emails($user, $submission);
        complete_user_login($user);
        $redirecturl = new moodle_url('/course/view.php', ['id' => 4, 'registered' => 1]);
        $SESSION->wantsurl = $redirecturl->out(false);
        redirect($redirecturl);
    } catch (moodle_exception $e) {
        \core\notification::error($e->getMessage());
    }
}

echo $OUTPUT->header();
$form->display();
?>
<script>
(function () {
    var form = document.querySelector('.iiidem-register-form form.mform, form.mform');
    if (!form) {
        return;
    }

    var registerWrap = document.querySelector('.iiidem-register-form') || form.parentElement;
    var phoneIti = null;
    var emailInput = document.getElementById('id_email');
    var phoneInput = document.getElementById('id_phone1');
    var countrySelect = document.getElementById('id_country');
    var emailCheckUrl = emailInput ? emailInput.getAttribute('data-email-check-url') : '';
    var emailExistsMsg = (emailInput && emailInput.getAttribute('data-email-exists-message'))
        || 'This email address is already registered.';
    var approvedEmail = '';
    var emailCheckSequence = 0;
    var bypassEmailCheck = false;
    var invalidPhoneMsg = (phoneInput && phoneInput.getAttribute('data-invalid-phone'))
        || 'Please enter a valid contact number with country code.';

    // Conditional role fields are required only for the selected role. Moodle's
    // client required rules validate hidden role fields too, so render the
    // required indicators without attaching unconditional client rules.
    [
        'organization', 'jobprofile', 'jobpostingcountry',
        'emb_organization', 'emb_designation', 'emb_country',
        'university', 'position', 'specialization',
        'instructor_university', 'instructor_course', 'presentcountry'
    ].forEach(function(fieldName) {
        var field = form.querySelector('[name="' + fieldName + '"]');
        if (!field) {
            return;
        }
        field.setAttribute('aria-required', 'true');

        var item = field.closest('.fitem');
        var labelColumn = item ? item.querySelector('.col-form-label') : null;
        if (!labelColumn) {
            return;
        }

        var addon = labelColumn.querySelector('.form-label-addon');
        if (!addon) {
            addon = document.createElement('div');
            addon.className = 'form-label-addon d-flex align-items-center align-self-start';
            labelColumn.appendChild(addon);
        }
        addon.innerHTML =
            '<span class="iiidem-required-asterisk" title="Required" aria-hidden="true">*</span>';
    });

    // Keep the required marker attached to the label text. This prevents the
    // marker from being pushed to the edge when a label wraps onto two lines.
    form.querySelectorAll('.col-form-label').forEach(function(labelColumn) {
        var label = labelColumn.querySelector('label');
        var addon = labelColumn.querySelector('.form-label-addon');
        var field = label && label.htmlFor ? document.getElementById(label.htmlFor) : null;
        if (label && addon && field && field.getAttribute('aria-required') === 'true') {
            label.classList.add('iiidem-required-label');
        }
    });

    function addPasswordToggle(inputId) {
        var input = document.getElementById(inputId);
        if (!input || input.getAttribute('data-password-toggle-ready') === '1') {
            return;
        }

        var wrap = document.createElement('div');
        wrap.className = 'iiidem-register-password-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'iiidem-register-password-toggle';
        button.setAttribute('aria-label', 'Show or hide password');
        button.setAttribute('aria-pressed', 'false');
        button.setAttribute('title', 'Show or hide password');
        button.innerHTML =
            '<span class="fa fa-eye-slash" aria-hidden="true" data-icon="hidden"></span>' +
            '<span class="fa fa-eye d-none" aria-hidden="true" data-icon="visible"></span>';
        wrap.appendChild(button);

        button.addEventListener('click', function () {
            var show = input.getAttribute('type') === 'password';
            input.setAttribute('type', show ? 'text' : 'password');
            button.setAttribute('aria-pressed', show ? 'true' : 'false');

            var hiddenIcon = button.querySelector('[data-icon="hidden"]');
            var visibleIcon = button.querySelector('[data-icon="visible"]');
            hiddenIcon.classList.toggle('d-none', show);
            visibleIcon.classList.toggle('d-none', !show);
        });

        input.setAttribute('data-password-toggle-ready', '1');
    }

    addPasswordToggle('id_password');
    addPasswordToggle('id_password2');

    function syncOccupationSections() {
        var selected = form.querySelector('input[name="occupation"]:checked');
        var value = selected ? selected.value : '';
        if (!registerWrap) {
            return;
        }
        registerWrap.classList.remove(
            'iiidem-occupation-working',
            'iiidem-occupation-workingemb',
            'iiidem-occupation-student',
            'iiidem-occupation-instructor'
        );
        if (value === 'working' || value === 'workingemb' || value === 'student' || value === 'instructor') {
            registerWrap.classList.add('iiidem-occupation-' + value);
            // Expand the matching Moodle collapsible section.
            var map = {
                working: 'id_workingheader',
                workingemb: 'id_workingembheader',
                student: 'id_studentheader',
                instructor: 'id_instructorheader'
            };
            var fieldset = document.getElementById(map[value]);
            if (fieldset) {
                var container = fieldset.querySelector('.fcontainer.collapseable, .fcontainer.collapse');
                var toggle = fieldset.querySelector('a.fheader, .ftoggler a');
                if (container && !container.classList.contains('show')) {
                    container.classList.add('show');
                    if (toggle) {
                        toggle.setAttribute('aria-expanded', 'true');
                        toggle.classList.remove('collapsed');
                    }
                }
            }
        }
    }

    form.addEventListener('change', function (e) {
        if (e.target && e.target.name === 'occupation') {
            syncOccupationSections();
        }
    }, true);

    // Initial state: all profile sections hidden until an occupation is chosen.
    syncOccupationSections();

    function clearFeedback(field) {
        var item = field && field.closest ? field.closest('.fitem') : null;
        if (!item) {
            return;
        }
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        item.classList.remove('has-danger');
        var feedback = document.getElementById('id_error_' + field.name)
            || item.querySelector('.form-control-feedback, .invalid-feedback');
        if (feedback) {
            feedback.textContent = '';
            feedback.style.display = 'none';
        }
    }

    function setFeedback(field, message) {
        var item = field && field.closest ? field.closest('.fitem') : null;
        if (!item) {
            return;
        }
        field.classList.add('is-invalid');
        field.setAttribute('aria-invalid', 'true');
        item.classList.add('has-danger');
        item.classList.remove('iiidem-field-valid');
        var feedback = document.getElementById('id_error_' + field.name)
            || item.querySelector('.form-control-feedback, .invalid-feedback');
        if (feedback) {
            feedback.textContent = message;
            feedback.style.display = 'block';
        }
    }

    function checkEmailAvailability() {
        if (!emailInput || !emailCheckUrl) {
            return Promise.resolve(true);
        }

        var email = String(emailInput.value || '').trim().toLowerCase();
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            return Promise.resolve(true);
        }

        var sequence = ++emailCheckSequence;
        var body = new URLSearchParams();
        body.set('email', email);
        body.set('sesskey', (window.M && M.cfg) ? M.cfg.sesskey : '');

        return fetch(emailCheckUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString()
        }).then(function(response) {
            if (!response.ok) {
                throw new Error('Email availability check failed');
            }
            return response.json();
        }).then(function(result) {
            if (sequence !== emailCheckSequence
                    || email !== String(emailInput.value || '').trim().toLowerCase()) {
                return false;
            }
            if (result.exists) {
                approvedEmail = '';
                setFeedback(emailInput, emailExistsMsg);
                paint(emailInput);
                return false;
            }
            approvedEmail = email;
            clearFeedback(emailInput);
            paint(emailInput, true);
            return true;
        }).catch(function() {
            // Do not block registration if AJAX is unavailable. PHP validation
            // below remains the authoritative duplicate-email check.
            approvedEmail = email;
            return true;
        });
    }

    function isPhoneValid() {
        if (!phoneInput) {
            return false;
        }
        var raw = String(phoneInput.value || '').trim();
        if (!raw) {
            return false;
        }
        if (phoneIti && typeof phoneIti.isValidNumber === 'function') {
            try {
                if (phoneIti.isValidNumber()) {
                    return true;
                }
            } catch (e) {
                // Fall through.
            }
        }
        // Fallback before utils load: national digits or E.164.
        if (/^\+[1-9]\d{7,14}$/.test(raw.replace(/[\s\-()]/g, ''))) {
            return true;
        }
        return /^(?:0)?[6-9]\d{9}$/.test(raw.replace(/\D/g, ''));
    }

    function hasValue(field) {
        if (!field) {
            return false;
        }
        if (field.id === 'id_phone1') {
            return isPhoneValid();
        }
        var type = (field.type || '').toLowerCase();
        if (type === 'checkbox') {
            return !!field.checked;
        }
        if (type === 'radio') {
            var nodes = form.querySelectorAll('input[type="radio"][name="' + field.name + '"]');
            for (var i = 0; i < nodes.length; i++) {
                if (nodes[i].checked) {
                    return true;
                }
            }
            return false;
        }
        return String(field.value || '').trim() !== '';
    }

    function paint(field, allowFeedbackClear) {
        if (!field || !field.closest) {
            return;
        }
        var item = field.closest('.fitem');
        if (!item) {
            return;
        }
        var ok = hasValue(field);
        // Keep server-side errors (for example, "Email already exists") visible
        // after a rejected submission. They may be cleared once the user edits
        // the field, but never by the initial green-check rendering.
        var existingFeedback = document.getElementById('id_error_' + field.name)
            || item.querySelector('.form-control-feedback, .invalid-feedback');
        var hasServerError = field.classList.contains('is-invalid')
            || field.getAttribute('aria-invalid') === 'true'
            || item.classList.contains('has-danger')
            || (existingFeedback && String(existingFeedback.textContent || '').trim() !== '');
        if (hasServerError && !allowFeedbackClear) {
            ok = false;
        }
        item.classList.toggle('iiidem-field-valid', ok);
        item.classList.toggle('iiidem-field-empty', !ok);

        if (field.id === 'id_phone1') {
            if (hasServerError && !allowFeedbackClear) {
                return;
            }
            var raw = String(field.value || '').trim();
            if (!raw) {
                // Keep Moodle required messaging for empty.
                return;
            }
            if (ok && allowFeedbackClear) {
                clearFeedback(field);
            } else {
                setFeedback(field, invalidPhoneMsg);
            }
        } else if (ok && allowFeedbackClear) {
            clearFeedback(field);
        }
    }

    function paintAll() {
        var fields = form.querySelectorAll(
            'input[aria-required="true"], select[aria-required="true"], textarea[aria-required="true"]'
        );
        var seen = {};
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            if ((field.type || '').toLowerCase() === 'radio') {
                if (seen[field.name]) {
                    continue;
                }
                seen[field.name] = 1;
            }
            paint(field);
        }
    }

    function initPhoneWidget() {
        if (!phoneInput || typeof window.intlTelInput !== 'function') {
            return false;
        }
        if (phoneInput.getAttribute('data-iti-ready') === '1') {
            return true;
        }

        var initialCountry = (countrySelect && countrySelect.value)
            ? String(countrySelect.value).toLowerCase()
            : 'in';

        phoneInput.setAttribute('type', 'tel');
        phoneInput.setAttribute('autocomplete', 'tel');
        phoneInput.setAttribute('inputmode', 'tel');
        phoneInput.classList.add('iiidem-phone-input');

        phoneIti = window.intlTelInput(phoneInput, {
            initialCountry: initialCountry || 'in',
            preferredCountries: ['in', 'us', 'gb', 'ae', 'sg'],
            separateDialCode: true,
            nationalMode: true,
            autoPlaceholder: 'aggressive',
            formatOnDisplay: true,
            utilsScript: (window.M && M.cfg && M.cfg.wwwroot
                ? M.cfg.wwwroot
                : '') + '/theme/iiidem2/javascript/intl-tel-input/utils.js'
        });

        function syncPhoneInputPadding() {
            var wrapper = phoneInput.closest('.iti');
            var selector = wrapper ? wrapper.querySelector('.iti__flag-container') : null;
            if (!selector) {
                return;
            }
            // Country dial-code widths vary (+1, +971, etc.). Keep the typed
            // number clear of the selector instead of relying on fixed padding.
            var selectorWidth = Math.ceil(selector.getBoundingClientRect().width);
            phoneInput.style.setProperty('padding-left', (selectorWidth + 14) + 'px', 'important');
        }

        phoneInput.setAttribute('data-iti-ready', '1');
        window.requestAnimationFrame(syncPhoneInputPadding);

        phoneInput.addEventListener('countrychange', function () {
            if (!countrySelect || !phoneIti) {
                return;
            }
            var data = phoneIti.getSelectedCountryData();
            if (data && data.iso2) {
                var iso = String(data.iso2).toUpperCase();
                for (var i = 0; i < countrySelect.options.length; i++) {
                    if (countrySelect.options[i].value === iso) {
                        countrySelect.value = iso;
                        paint(countrySelect);
                        break;
                    }
                }
            }
            window.requestAnimationFrame(syncPhoneInputPadding);
            paint(phoneInput);
        });

        if (countrySelect) {
            countrySelect.addEventListener('change', function () {
                if (phoneIti && countrySelect.value) {
                    phoneIti.setCountry(String(countrySelect.value).toLowerCase());
                }
                window.requestAnimationFrame(syncPhoneInputPadding);
                paint(phoneInput);
            });
        }

        window.addEventListener('resize', syncPhoneInputPadding);

        return true;
    }

    function ensurePhoneWidget(attempt) {
        attempt = attempt || 0;
        if (initPhoneWidget()) {
            paint(phoneInput);
            return;
        }
        if (attempt < 40) {
            setTimeout(function () {
                ensurePhoneWidget(attempt + 1);
            }, 100);
        }
    }

    form.addEventListener('input', function (e) {
        if (e.target && e.target.tagName) {
            var tag = e.target.tagName.toLowerCase();
            if (tag === 'input' || tag === 'select' || tag === 'textarea') {
                if (e.target === emailInput) {
                    approvedEmail = '';
                    emailCheckSequence++;
                }
                paint(e.target, true);
            }
        }
    }, true);

    form.addEventListener('change', function (e) {
        if (e.target && e.target.tagName) {
            var tag = e.target.tagName.toLowerCase();
            if (tag === 'input' || tag === 'select' || tag === 'textarea') {
                paint(e.target);
            }
        }
    }, true);

    form.addEventListener('keyup', function (e) {
        if (e.target && e.target.tagName) {
            paint(e.target, true);
        }
    }, true);

    if (emailInput) {
        emailInput.addEventListener('blur', function () {
            checkEmailAvailability();
        });
    }

    form.addEventListener('submit', function (e) {
        if (!emailInput || bypassEmailCheck) {
            return;
        }

        var email = String(emailInput.value || '').trim().toLowerCase();
        if (email && approvedEmail === email) {
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();
        var submitter = e.submitter || null;

        checkEmailAvailability().then(function(available) {
            if (!available) {
                emailInput.focus();
                return;
            }

            bypassEmailCheck = true;
            try {
                if (typeof form.requestSubmit === 'function') {
                    if (submitter) {
                        form.requestSubmit(submitter);
                    } else {
                        form.requestSubmit();
                    }
                } else {
                    form.submit();
                }
            } finally {
                bypassEmailCheck = false;
            }
        });
    }, true);

    form.addEventListener('submit', function (e) {
        if (!phoneInput) {
            return;
        }
        var raw = String(phoneInput.value || '').trim();
        if (!raw) {
            return;
        }
        if (!isPhoneValid()) {
            e.preventDefault();
            e.stopPropagation();
            setFeedback(phoneInput, invalidPhoneMsg);
            paint(phoneInput, true);
            phoneInput.focus();
            return false;
        }
        if (phoneIti && typeof phoneIti.getNumber === 'function') {
            var countryData = phoneIti.getSelectedCountryData();
            var nationalDigits = String(phoneInput.value || '').replace(/\D/g, '');
            // Users commonly type India's trunk prefix 0 even though +91 is
            // already displayed separately. Remove it before creating E.164.
            if (countryData && countryData.iso2 === 'in' && /^0[6-9]\d{9}$/.test(nationalDigits)) {
                phoneIti.setNumber('+91' + nationalDigits.substring(1));
            }
            phoneInput.value = phoneIti.getNumber(); // E.164 e.g. +9198xxxxxxxx
        }
        clearFeedback(phoneInput);
        paint(phoneInput, true);
        return true;
    }, true);

    ensurePhoneWidget();
    paintAll();
    setTimeout(paintAll, 300);
    setTimeout(paintAll, 1000);
})();
</script>
<?php
echo $OUTPUT->footer();
