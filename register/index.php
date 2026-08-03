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
        $email = \core_text::strtolower(trim((string) ($submission->email ?? '')));
        if (!\theme_iiidem2\registration_otp::is_verified($email)) {
            throw new \moodle_exception('registerotprequired', 'theme_iiidem2');
        }
        $userid = theme_iiidem2_create_registered_user($submission);
        \theme_iiidem2\registration_otp::clear();
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

$otpsendurl = (new moodle_url('/register/send_otp.php'))->out(false);
$otpverifyurl = (new moodle_url('/register/verify_otp.php'))->out(false);
$otpstring = static function(string $key, string $fallback) {
    return get_string_manager()->string_exists($key, 'theme_iiidem2')
        ? get_string($key, 'theme_iiidem2')
        : $fallback;
};
$otpstrings = [
    'title' => $otpstring('registerotpmodaltitle', 'Verify your email'),
    'intro' => $otpstring('registerotpmodalintro', 'Enter the 6-digit verification code we sent to your email address.'),
    'label' => $otpstring('registerotplabel', 'Verification code'),
    'placeholder' => $otpstring('registerotpplaceholder', '6-digit code'),
    'verify' => $otpstring('registerotpverify', 'Verify & create account'),
    'resend' => $otpstring('registerotpresend', 'Resend code'),
    'close' => get_string('closebuttontitle'),
    'sending' => $otpstring('registerotpsending', 'Sending verification code…'),
    'verifying' => $otpstring('registerotpverifying', 'Verifying code…'),
    'required' => $otpstring('registerotprequiredcode', 'Enter the 6-digit verification code.'),
    'loadingtitle' => $otpstring('registerotploadingtitle', 'Verifying your email'),
    'loadingtext' => $otpstring('registerotploadingtext', 'Please wait while we verify your code and create your account…'),
];
?>
<div id="iiidem-register-otp-modal" class="iiidem-register-otp-modal" hidden aria-hidden="true">
    <div class="iiidem-register-otp-modal__backdrop" data-otp-close="1"></div>
    <div class="iiidem-register-otp-modal__dialog" role="dialog" aria-modal="true"
         aria-labelledby="iiidem-register-otp-title">
        <div class="iiidem-register-otp-modal__header">
            <h2 id="iiidem-register-otp-title" class="iiidem-register-otp-modal__title">
                <?php echo s($otpstrings['title']); ?>
            </h2>
            <button type="button" class="iiidem-register-otp-modal__close" data-otp-close="1"
                    aria-label="<?php echo s($otpstrings['close']); ?>">&times;</button>
        </div>
        <div class="iiidem-register-otp-modal__body">
            <p class="iiidem-register-otp-modal__intro" data-otp-intro>
                <?php echo s($otpstrings['intro']); ?>
            </p>
            <p class="iiidem-register-otp-modal__status" data-otp-status hidden></p>
            <label class="iiidem-register-otp-modal__label" for="iiidem-register-otp-input">
                <?php echo s($otpstrings['label']); ?>
            </label>
            <input id="iiidem-register-otp-input" class="form-control iiidem-register-otp-modal__input"
                   type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                   placeholder="<?php echo s($otpstrings['placeholder']); ?>">
            <p class="iiidem-register-otp-modal__error" data-otp-error hidden></p>
        </div>
        <div class="iiidem-register-otp-modal__footer">
            <button type="button" class="btn btn-link" data-otp-resend>
                <?php echo s($otpstrings['resend']); ?>
            </button>
            <button type="button" class="btn btn-primary" data-otp-verify>
                <?php echo s($otpstrings['verify']); ?>
            </button>
        </div>
    </div>
</div>
<script>
window.IIIDEM_REGISTER_OTP = {
    sendUrl: <?php echo json_encode($otpsendurl); ?>,
    verifyUrl: <?php echo json_encode($otpverifyurl); ?>,
    strings: <?php echo json_encode($otpstrings); ?>
};
</script>
<?php
// Continue inline registration scripts below.
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
    var firstnameInput = document.getElementById('id_firstname');
    var emailCheckUrl = emailInput ? emailInput.getAttribute('data-email-check-url') : '';
    var emailExistsMsg = (emailInput && emailInput.getAttribute('data-email-exists-message'))
        || 'This email address is already registered.';
    var emailDisposableMsg = (emailInput && emailInput.getAttribute('data-email-disposable-message'))
        || 'Please check the email. Temporary or disposable email addresses are not allowed.';
    var emailUndeliverableMsg = (emailInput && emailInput.getAttribute('data-email-undeliverable-message'))
        || 'Please check the email. This domain does not appear to accept mail.';
    var emailToastMsg = 'Please check the email';
    var emailInvalidMsg = 'Please enter a valid email address (for example name@gmail.com).';
    var approvedEmail = '';
    var emailCheckSequence = 0;
    var bypassEmailCheck = false;
    var phoneCheckUrl = phoneInput ? phoneInput.getAttribute('data-phone-check-url') : '';
    var phoneExistsMsg = (phoneInput && phoneInput.getAttribute('data-phone-exists-message'))
        || 'This contact number is already registered.';
    var invalidPhoneMsg = (phoneInput && phoneInput.getAttribute('data-invalid-phone'))
        || 'Enter a valid 10-digit contact number (digits only).';
    var approvedPhone = '';
    var phoneCheckSequence = 0;
    var bypassPhoneCheck = false;
    var otpVerifiedEmail = '';
    var bypassOtpGate = false;
    var otpCfg = window.IIIDEM_REGISTER_OTP || {};
    var otpModal = document.getElementById('iiidem-register-otp-modal');
    var otpInput = document.getElementById('iiidem-register-otp-input');
    var otpStatus = otpModal ? otpModal.querySelector('[data-otp-status]') : null;
    var otpError = otpModal ? otpModal.querySelector('[data-otp-error]') : null;
    var otpIntro = otpModal ? otpModal.querySelector('[data-otp-intro]') : null;
    var otpVerifyBtn = otpModal ? otpModal.querySelector('[data-otp-verify]') : null;
    var otpResendBtn = otpModal ? otpModal.querySelector('[data-otp-resend]') : null;
    var pendingSubmitter = null;
    var otpBusy = false;

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
        field.removeAttribute('data-phone-status');
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
        if (field.id === 'id_phone1') {
            field.setAttribute('data-phone-status', 'error');
        }
        item.classList.add('has-danger');
        item.classList.remove('iiidem-field-valid');
        var feedback = document.getElementById('id_error_' + field.name)
            || item.querySelector('.form-control-feedback, .invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'form-control-feedback invalid-feedback';
            feedback.id = 'id_error_' + field.name;
            var felement = item.querySelector('.felement') || item;
            felement.appendChild(feedback);
        }
        feedback.textContent = message;
        feedback.style.display = 'block';
    }

    function showRegisterToast(title, body) {
        var host = document.getElementById('iiidem-register-toast-host');
        if (!host) {
            host = document.createElement('div');
            host.id = 'iiidem-register-toast-host';
            host.className = 'iiidem-register-toast-host';
            host.setAttribute('aria-live', 'polite');
            document.body.appendChild(host);
        }
        var toast = document.createElement('div');
        toast.className = 'iiidem-register-toast';
        toast.innerHTML = ''
            + '<button type="button" class="iiidem-register-toast__close" aria-label="Close">&times;</button>'
            + '<p class="iiidem-register-toast__title"></p>'
            + (body ? '<p class="iiidem-register-toast__body"></p>' : '');
        toast.querySelector('.iiidem-register-toast__title').textContent = title || emailToastMsg;
        var bodyEl = toast.querySelector('.iiidem-register-toast__body');
        if (bodyEl && body) {
            bodyEl.textContent = body;
        }
        host.appendChild(toast);
        window.requestAnimationFrame(function () {
            toast.classList.add('is-visible');
        });
        var close = function () {
            toast.classList.add('is-hiding');
            setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 220);
        };
        toast.querySelector('.iiidem-register-toast__close').addEventListener('click', close);
        setTimeout(close, 5000);
    }

    function isValidEmailFormat(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || '').trim());
    }

    function rejectEmail(message, toastTitle, toastBody) {
        approvedEmail = '';
        if (emailInput) {
            setFeedback(emailInput, message || emailInvalidMsg);
            paint(emailInput);
        }
        showRegisterToast(toastTitle || emailToastMsg, toastBody || message || emailInvalidMsg);
        return false;
    }

    function checkEmailAvailability() {
        if (!emailInput || !emailCheckUrl) {
            return Promise.resolve(true);
        }

        var email = String(emailInput.value || '').trim().toLowerCase();
        if (!email) {
            return Promise.resolve(rejectEmail(emailInvalidMsg));
        }
        if (!isValidEmailFormat(email)) {
            return Promise.resolve(rejectEmail(emailInvalidMsg));
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
            if (result.exists || result.reason === 'exists') {
                return rejectEmail(result.message || emailExistsMsg, result.toast || emailExistsMsg, '');
            }
            if (result.ok === false || result.reason === 'disposable' || result.reason === 'undeliverable'
                    || result.reason === 'invalid') {
                var msg = result.message || '';
                if (!msg) {
                    if (result.reason === 'disposable') {
                        msg = emailDisposableMsg;
                    } else if (result.reason === 'undeliverable') {
                        msg = emailUndeliverableMsg;
                    } else {
                        msg = emailInvalidMsg;
                    }
                }
                return rejectEmail(msg, result.toast || emailToastMsg, msg);
            }
            approvedEmail = email;
            clearFeedback(emailInput);
            paint(emailInput, true);
            return true;
        }).catch(function() {
            // Do not mark as approved when the quality check cannot run.
            approvedEmail = '';
            return rejectEmail(
                'Could not verify the email address. Please try again.',
                emailToastMsg,
                'Could not verify the email address. Please try again.'
            );
        });
    }

    function getSelectedDialCode() {
        if (phoneIti && typeof phoneIti.getSelectedCountryData === 'function') {
            var data = phoneIti.getSelectedCountryData();
            if (data && data.dialCode) {
                return String(data.dialCode);
            }
        }
        if (countrySelect && String(countrySelect.value || '').toUpperCase() === 'IN') {
            return '91';
        }
        return '91';
    }

    /**
     * National number only (dial code from the +91 selector must not count).
     */
    function getNationalPhoneDigits() {
        if (!phoneInput) {
            return '';
        }
        var digits = String(phoneInput.value || '').replace(/\D/g, '');
        var dial = getSelectedDialCode();

        // Pasted / widget E.164: 91XXXXXXXXXX (longer than 10).
        if (dial && digits.length > 10 && digits.indexOf(dial) === 0) {
            digits = digits.substring(dial.length);
        }
        // Trunk prefix 0XXXXXXXXXX.
        if (digits.length === 11 && digits.charAt(0) === '0') {
            digits = digits.substring(1);
        }
        if (digits.length > 10) {
            digits = digits.slice(-10);
        }
        return digits;
    }

    function sanitizePhoneInputValue() {
        if (!phoneInput) {
            return;
        }
        // Allow paste of +91…; enforce national 10 after stripping dial/E.164.
        phoneInput.setAttribute('maxlength', '16');
        var digits = String(phoneInput.value || '').replace(/\D/g, '');
        var dial = getSelectedDialCode();
        if (dial && digits.length > 10 && digits.indexOf(dial) === 0) {
            digits = digits.substring(dial.length);
        }
        if (digits.length === 11 && digits.charAt(0) === '0') {
            digits = digits.substring(1);
        }
        var national = digits.slice(0, 10);
        if (phoneInput.value !== national) {
            phoneInput.value = national;
        }
    }

    function isPhoneValid() {
        return /^[0-9]{10}$/.test(getNationalPhoneDigits());
    }

    function checkPhoneAvailability() {
        if (!phoneInput || !phoneCheckUrl) {
            return Promise.resolve(true);
        }

        sanitizePhoneInputValue();
        var national = getNationalPhoneDigits();
        if (!/^[0-9]{10}$/.test(national)) {
            approvedPhone = '';
            if (String(phoneInput.value || '').trim() !== '') {
                setFeedback(phoneInput, invalidPhoneMsg);
                paint(phoneInput, true);
            }
            return Promise.resolve(false);
        }

        var sequence = ++phoneCheckSequence;
        var body = new URLSearchParams();
        body.set('phone', national);
        body.set('country', countrySelect ? String(countrySelect.value || 'IN') : 'IN');
        body.set('sesskey', (window.M && M.cfg) ? M.cfg.sesskey : '');

        return fetch(phoneCheckUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString()
        }).then(function(response) {
            if (!response.ok) {
                throw new Error('Phone availability check failed');
            }
            return response.json();
        }).then(function(result) {
            if (sequence !== phoneCheckSequence || national !== getNationalPhoneDigits()) {
                return false;
            }
            if (!result.valid) {
                approvedPhone = '';
                setFeedback(phoneInput, result.message || invalidPhoneMsg);
                paint(phoneInput);
                return false;
            }
            if (result.exists) {
                approvedPhone = '';
                setFeedback(phoneInput, result.message || phoneExistsMsg);
                // Do not paint(..., true) — that clears duplicate errors because
                // the number is format-valid (10 digits).
                paint(phoneInput);
                return false;
            }
            approvedPhone = national;
            clearFeedback(phoneInput);
            paint(phoneInput, true);
            return true;
        }).catch(function() {
            // Network / parse failure — do not mark as approved; PHP still
            // validates on submit. Keep the field unchecked for duplicates.
            approvedPhone = '';
            return true;
        });
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
            // Keep AJAX / server duplicate messages visible.
            if (field.getAttribute('data-phone-status') === 'error' && !allowFeedbackClear) {
                item.classList.remove('iiidem-field-valid');
                item.classList.add('iiidem-field-empty');
                return;
            }
            if (hasServerError && !allowFeedbackClear) {
                return;
            }
            var raw = String(field.value || '').trim();
            if (!raw) {
                // Keep Moodle required messaging for empty.
                return;
            }
            // Format-valid (10 digits) must not wipe a duplicate-phone error.
            if (ok) {
                if (allowFeedbackClear && field.getAttribute('data-phone-status') !== 'error') {
                    clearFeedback(field);
                }
                if (field.getAttribute('data-phone-status') === 'error') {
                    item.classList.remove('iiidem-field-valid');
                    item.classList.add('iiidem-field-empty');
                }
                return;
            }
            if (allowFeedbackClear) {
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
        phoneInput.setAttribute('inputmode', 'numeric');
        // Do not use maxlength=10 here: country code digits must not consume the
        // national 10-digit budget. sanitizePhoneInputValue() enforces 10.
        phoneInput.setAttribute('maxlength', '16');
        phoneInput.removeAttribute('pattern');
        phoneInput.classList.add('iiidem-phone-input');

        phoneIti = window.intlTelInput(phoneInput, {
            initialCountry: initialCountry || 'in',
            preferredCountries: ['in', 'us', 'gb', 'ae', 'sg'],
            separateDialCode: true,
            nationalMode: true,
            autoPlaceholder: 'aggressive',
            formatOnDisplay: false,
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

        phoneInput.addEventListener('input', function () {
            sanitizePhoneInputValue();
            approvedPhone = '';
            phoneCheckSequence++;
            phoneInput.removeAttribute('data-phone-status');
        });

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
                    otpVerifiedEmail = '';
                }
                if (e.target === phoneInput) {
                    approvedPhone = '';
                    phoneCheckSequence++;
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

    if (phoneInput) {
        phoneInput.addEventListener('blur', function () {
            checkPhoneAvailability();
        });
    }

    form.addEventListener('submit', function (e) {
        if (bypassEmailCheck && bypassPhoneCheck) {
            return;
        }

        var email = emailInput ? String(emailInput.value || '').trim().toLowerCase() : '';
        var national = getNationalPhoneDigits();
        var emailOk = !emailInput || !email || approvedEmail === email;
        var phoneOk = !phoneInput || !national || approvedPhone === national;

        if (emailOk && phoneOk) {
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();
        var submitter = e.submitter || null;

        var checks = [];
        if (!emailOk) {
            checks.push(checkEmailAvailability());
        } else {
            checks.push(Promise.resolve(true));
        }
        if (!phoneOk) {
            checks.push(checkPhoneAvailability());
        } else {
            checks.push(Promise.resolve(true));
        }

        Promise.all(checks).then(function(results) {
            var emailAvailable = results[0];
            var phoneAvailable = results[1];
            if (!emailAvailable) {
                emailInput.focus();
                return;
            }
            if (!phoneAvailable) {
                phoneInput.focus();
                return;
            }

            bypassEmailCheck = true;
            bypassPhoneCheck = true;
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
                bypassPhoneCheck = false;
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
        var nationalDigits = getNationalPhoneDigits();
        if (phoneIti && typeof phoneIti.getNumber === 'function') {
            // Allow E.164 value longer than the visible 10-digit maxlength.
            phoneInput.setAttribute('maxlength', '20');
            var countryData = phoneIti.getSelectedCountryData();
            var dial = (countryData && countryData.dialCode) ? String(countryData.dialCode) : '91';
            phoneIti.setNumber('+' + dial + nationalDigits);
            phoneInput.value = phoneIti.getNumber(); // E.164 e.g. +9198xxxxxxxx
        } else {
            phoneInput.setAttribute('maxlength', '20');
            var iso = countrySelect ? String(countrySelect.value || 'IN').toUpperCase() : 'IN';
            var dialFallback = (iso === 'IN') ? '91' : '';
            phoneInput.value = dialFallback ? ('+' + dialFallback + nationalDigits) : nationalDigits;
        }
        clearFeedback(phoneInput);
        paint(phoneInput, true);
        return true;
    }, true);

    function setOtpError(message) {
        if (!otpError) {
            return;
        }
        if (message) {
            otpError.hidden = false;
            otpError.textContent = message;
        } else {
            otpError.hidden = true;
            otpError.textContent = '';
        }
    }

    function setOtpStatus(message) {
        if (!otpStatus) {
            return;
        }
        if (message) {
            otpStatus.hidden = false;
            otpStatus.textContent = message;
        } else {
            otpStatus.hidden = true;
            otpStatus.textContent = '';
        }
    }

    function openOtpModal(email) {
        if (!otpModal) {
            return;
        }
        setOtpError('');
        setOtpStatus('');
        if (otpInput) {
            otpInput.value = '';
        }
        otpModal.hidden = false;
        otpModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('iiidem-register-otp-open');
        if (otpInput) {
            otpInput.focus();
        }
    }

    function closeOtpModal() {
        if (!otpModal) {
            return;
        }
        otpModal.hidden = true;
        otpModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('iiidem-register-otp-open');
        pendingSubmitter = null;
    }

    function postOtp(url, payload) {
        var body = new URLSearchParams();
        Object.keys(payload).forEach(function(key) {
            body.set(key, payload[key]);
        });
        body.set('sesskey', (window.M && M.cfg) ? M.cfg.sesskey : '');
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString()
        }).then(function(response) {
            if (!response.ok) {
                throw new Error('OTP request failed');
            }
            return response.json();
        });
    }

    function sendRegistrationOtp() {
        if (!emailInput || !otpCfg.sendUrl || otpBusy) {
            return Promise.resolve(false);
        }
        var email = String(emailInput.value || '').trim().toLowerCase();
        var firstname = firstnameInput ? String(firstnameInput.value || '').trim() : '';
        otpBusy = true;
        if (otpResendBtn) {
            otpResendBtn.disabled = true;
        }
        setOtpStatus((otpCfg.strings && otpCfg.strings.sending) || 'Sending code…');
        setOtpError('');
        return postOtp(otpCfg.sendUrl, {email: email, firstname: firstname}).then(function(result) {
            otpBusy = false;
            if (otpResendBtn) {
                otpResendBtn.disabled = false;
            }
            if (!result.ok) {
                setOtpStatus('');
                setOtpError(result.message || 'Could not send verification code.');
                if (result.reason === 'disposable' || result.reason === 'undeliverable'
                        || result.reason === 'invalid') {
                    closeOtpModal();
                    setFeedback(emailInput, result.message || emailDisposableMsg);
                    paint(emailInput);
                    showRegisterToast(result.toast || emailToastMsg, result.message || '');
                    approvedEmail = '';
                }
                return false;
            }
            setOtpStatus(result.message || '');
            return true;
        }).catch(function() {
            otpBusy = false;
            if (otpResendBtn) {
                otpResendBtn.disabled = false;
            }
            setOtpStatus('');
            setOtpError('Could not send verification code. Please try again.');
            return false;
        });
    }

    function showOtpLoading() {
        hideOtpLoading();
        var title = (otpCfg.strings && otpCfg.strings.loadingtitle) || 'Verifying your email';
        var text = (otpCfg.strings && otpCfg.strings.loadingtext)
            || 'Please wait while we verify your code and create your account…';
        var overlay = document.createElement('div');
        overlay.id = 'iiidem-register-otp-loading';
        overlay.className = 'iiidem-register-otp-loading';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        overlay.innerHTML = ''
            + '<div class="iiidem-register-otp-loading__card">'
            +   '<div class="iiidem-register-otp-loading__spinner" aria-hidden="true"></div>'
            +   '<p class="iiidem-register-otp-loading__title">' + title + '</p>'
            +   '<p class="iiidem-register-otp-loading__text">' + text + '</p>'
            + '</div>';
        document.body.appendChild(overlay);
        document.body.classList.add('iiidem-register-otp-loading-open');
        if (otpVerifyBtn) {
            otpVerifyBtn.setAttribute('aria-busy', 'true');
            if (!otpVerifyBtn.dataset.originalHtml) {
                otpVerifyBtn.dataset.originalHtml = otpVerifyBtn.innerHTML;
            }
            otpVerifyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>'
                + ((otpCfg.strings && otpCfg.strings.verifying) || 'Verifying…');
        }
    }

    function hideOtpLoading() {
        var overlay = document.getElementById('iiidem-register-otp-loading');
        if (overlay) {
            overlay.remove();
        }
        document.body.classList.remove('iiidem-register-otp-loading-open');
        if (otpVerifyBtn) {
            otpVerifyBtn.removeAttribute('aria-busy');
            if (otpVerifyBtn.dataset.originalHtml) {
                otpVerifyBtn.innerHTML = otpVerifyBtn.dataset.originalHtml;
            }
        }
    }

    function verifyRegistrationOtp() {
        if (!emailInput || !otpCfg.verifyUrl || otpBusy) {
            return;
        }
        var email = String(emailInput.value || '').trim().toLowerCase();
        var code = otpInput ? String(otpInput.value || '').replace(/\D/g, '') : '';
        if (!/^[0-9]{6}$/.test(code)) {
            setOtpError((otpCfg.strings && otpCfg.strings.required) || 'Enter the 6-digit code.');
            return;
        }
        otpBusy = true;
        if (otpVerifyBtn) {
            otpVerifyBtn.disabled = true;
        }
        if (otpResendBtn) {
            otpResendBtn.disabled = true;
        }
        setOtpError('');
        setOtpStatus((otpCfg.strings && otpCfg.strings.verifying) || 'Verifying…');
        showOtpLoading();
        postOtp(otpCfg.verifyUrl, {email: email, code: code}).then(function(result) {
            if (!result.ok) {
                otpBusy = false;
                hideOtpLoading();
                if (otpVerifyBtn) {
                    otpVerifyBtn.disabled = false;
                }
                if (otpResendBtn) {
                    otpResendBtn.disabled = false;
                }
                setOtpStatus('');
                setOtpError(result.message || 'Invalid verification code.');
                return;
            }
            otpVerifiedEmail = email;
            // Keep loader visible through form submit / account creation.
            closeOtpModal();
            bypassEmailCheck = true;
            bypassPhoneCheck = true;
            bypassOtpGate = true;
            try {
                if (typeof form.requestSubmit === 'function') {
                    if (pendingSubmitter) {
                        form.requestSubmit(pendingSubmitter);
                    } else {
                        form.requestSubmit();
                    }
                } else {
                    form.submit();
                }
            } finally {
                bypassEmailCheck = false;
                bypassPhoneCheck = false;
                bypassOtpGate = false;
            }
        }).catch(function() {
            otpBusy = false;
            hideOtpLoading();
            if (otpVerifyBtn) {
                otpVerifyBtn.disabled = false;
            }
            if (otpResendBtn) {
                otpResendBtn.disabled = false;
            }
            setOtpStatus('');
            setOtpError('Could not verify the code. Please try again.');
        });
    }

    function startOtpFlow(submitter) {
        var email = emailInput ? String(emailInput.value || '').trim().toLowerCase() : '';
        pendingSubmitter = submitter || null;
        // Re-validate email quality before opening OTP (blocks disposable / test domains).
        checkEmailAvailability().then(function(ok) {
            if (!ok) {
                if (emailInput) {
                    emailInput.focus();
                }
                return;
            }
            openOtpModal(email);
            sendRegistrationOtp();
        });
    }

    form.addEventListener('submit', function (e) {
        if (bypassOtpGate || !otpModal || !otpCfg.sendUrl) {
            return;
        }

        var email = emailInput ? String(emailInput.value || '').trim().toLowerCase() : '';
        var national = getNationalPhoneDigits();
        var emailOk = !emailInput || !email || approvedEmail === email;
        var phoneOk = !phoneInput || !national || approvedPhone === national;

        // Invalid / unapproved email must never fall through silently.
        if (!emailOk || !isValidEmailFormat(email)) {
            e.preventDefault();
            e.stopImmediatePropagation();
            checkEmailAvailability().then(function(ok) {
                if (!ok && emailInput) {
                    emailInput.focus();
                }
            });
            return;
        }
        if (!phoneOk) {
            return;
        }
        if (email && otpVerifiedEmail === email) {
            return;
        }

        e.preventDefault();
        e.stopImmediatePropagation();
        startOtpFlow(e.submitter || null);
    }, true);

    if (otpModal) {
        otpModal.querySelectorAll('[data-otp-close]').forEach(function(el) {
            el.addEventListener('click', function () {
                closeOtpModal();
            });
        });
    }
    if (otpVerifyBtn) {
        otpVerifyBtn.addEventListener('click', verifyRegistrationOtp);
    }
    if (otpResendBtn) {
        otpResendBtn.addEventListener('click', function () {
            sendRegistrationOtp();
        });
    }
    if (otpInput) {
        otpInput.addEventListener('input', function () {
            otpInput.value = String(otpInput.value || '').replace(/\D/g, '').slice(0, 6);
            setOtpError('');
        });
        otpInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                verifyRegistrationOtp();
            }
        });
    }

    ensurePhoneWidget();
    paintAll();
    setTimeout(paintAll, 300);
    setTimeout(paintAll, 1000);
})();
</script>
<?php
echo $OUTPUT->footer();
