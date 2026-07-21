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
    var phoneInput = document.getElementById('id_phone1');
    var countrySelect = document.getElementById('id_country');
    var invalidPhoneMsg = (phoneInput && phoneInput.getAttribute('data-invalid-phone'))
        || 'Please enter a valid contact number with country code.';

    function syncOccupationSections() {
        var selected = form.querySelector('input[name="occupation"]:checked');
        var value = selected ? selected.value : '';
        if (!registerWrap) {
            return;
        }
        registerWrap.classList.remove(
            'iiidem-occupation-working',
            'iiidem-occupation-student',
            'iiidem-occupation-instructor'
        );
        if (value === 'working' || value === 'student' || value === 'instructor') {
            registerWrap.classList.add('iiidem-occupation-' + value);
            // Expand the matching Moodle collapsible section.
            var map = {
                working: 'id_workingheader',
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

    function markerOf(item) {
        var addon = item.querySelector('.form-label-addon');
        if (!addon) {
            return null;
        }
        return addon.querySelector('.text-danger, .text-success, [title="Required"], [title="Completed"]')
            || addon.firstElementChild;
    }

    function iconOf(item) {
        var addon = item.querySelector('.form-label-addon');
        return addon ? addon.querySelector('.icon, i') : null;
    }

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
                return !!phoneIti.isValidNumber();
            } catch (e) {
                // Fall through.
            }
        }
        // Fallback before utils load: national digits or E.164.
        if (/^\+[1-9]\d{7,14}$/.test(raw.replace(/[\s\-()]/g, ''))) {
            return true;
        }
        return /^[6-9]\d{9}$/.test(raw.replace(/\D/g, ''));
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

    function paint(field) {
        if (!field || !field.closest) {
            return;
        }
        var item = field.closest('.fitem');
        if (!item) {
            return;
        }
        var ok = hasValue(field);
        var marker = markerOf(item);
        var icon = iconOf(item);

        item.classList.toggle('iiidem-field-valid', ok);
        item.classList.toggle('iiidem-field-empty', !ok);

        if (marker) {
            marker.classList.remove(ok ? 'text-danger' : 'text-success');
            marker.classList.add(ok ? 'text-success' : 'text-danger');
            marker.setAttribute('title', ok ? 'Completed' : 'Required');
            marker.style.setProperty('color', ok ? '#198754' : '#dc3545', 'important');
        }
        if (icon) {
            icon.classList.remove(ok ? 'text-danger' : 'text-success');
            icon.classList.add(ok ? 'text-success' : 'text-danger');
            if (ok) {
                icon.classList.remove('fa-circle-exclamation', 'fa-exclamation-circle');
                icon.classList.add('fa-circle-check', 'fa-check-circle');
            } else {
                icon.classList.remove('fa-circle-check', 'fa-check-circle');
                icon.classList.add('fa-circle-exclamation', 'fa-exclamation-circle');
            }
            icon.style.setProperty('color', ok ? '#198754' : '#dc3545', 'important');
        }

        if (field.id === 'id_phone1') {
            var raw = String(field.value || '').trim();
            if (!raw) {
                // Keep Moodle required messaging for empty.
                return;
            }
            if (ok) {
                clearFeedback(field);
            } else {
                setFeedback(field, invalidPhoneMsg);
            }
        } else if (ok) {
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

        phoneInput.setAttribute('data-iti-ready', '1');

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
            paint(phoneInput);
        });

        if (countrySelect) {
            countrySelect.addEventListener('change', function () {
                if (phoneIti && countrySelect.value) {
                    phoneIti.setCountry(String(countrySelect.value).toLowerCase());
                }
                paint(phoneInput);
            });
        }

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
                paint(e.target);
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
            paint(e.target);
        }
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
            paint(phoneInput);
            phoneInput.focus();
            return false;
        }
        if (phoneIti && typeof phoneIti.getNumber === 'function') {
            phoneInput.value = phoneIti.getNumber(); // E.164 e.g. +9198xxxxxxxx
        }
        clearFeedback(phoneInput);
        paint(phoneInput);
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
