/**
 * Improve IIIDEM registration fields on Moodle user edit forms.
 *
 * @package theme_iiidem2
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(function() {
    'use strict';

    function init() {
        var form = document.querySelector('form#mform1, form.mform');
        if (!form || form.dataset.iiidemRegistrationProfileReady === '1') {
            return;
        }

        function getProfileCheckbox(name) {
            return form.querySelector('input[type="checkbox"][name="' + name + '"]');
        }

        function hideField(name) {
            var input = getProfileCheckbox(name);
            var item = input ? input.closest('.fitem') : document.getElementById('fitem_id_' + name);
            if (item) {
                item.hidden = true;
                item.style.display = 'none';
            }
        }

        function createRadio(name, value, id, labelText, checked) {
            var wrapper = document.createElement('div');
            wrapper.className = 'form-check mb-2';

            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = name;
            radio.value = value;
            radio.id = id;
            radio.className = 'form-check-input';
            radio.checked = !!checked;

            var label = document.createElement('label');
            label.className = 'form-check-label';
            label.htmlFor = id;
            label.textContent = labelText;

            wrapper.appendChild(radio);
            wrapper.appendChild(label);
            return wrapper;
        }

        // Hide retired / internal checkboxes; rebuild cleaner UI below.
        [
            'profile_field_iiidem_electoral_practitioner',
            'profile_field_iiidem_emb',
            'profile_field_iiidem_policymaker',
            'profile_field_iiidem_journalist',
            'profile_field_iiidem_researcher'
        ].forEach(hideField);

        var categories = [
            {name: 'profile_field_iiidem_policymaker', label: 'Policymaker'},
            {name: 'profile_field_iiidem_journalist', label: 'Journalist'},
            {name: 'profile_field_iiidem_researcher', label: 'Researcher'}
        ].map(function(category) {
            var input = getProfileCheckbox(category.name);
            return input ? Object.assign({}, category, {
                input: input,
                item: input.closest('.fitem')
            }) : null;
        }).filter(Boolean);

        var embInput = getProfileCheckbox('profile_field_iiidem_emb');
        var insertBefore = null;
        if (categories.length && categories[0].item) {
            insertBefore = categories[0].item;
        } else if (embInput) {
            insertBefore = embInput.closest('.fitem');
        }
        if (!insertBefore || !insertBefore.parentNode) {
            return;
        }

        var parent = insertBefore.parentNode;
        var lastInserted = null;

        // Working professional category stays under IIIDEM registration.
        if (categories.length) {
            var categoryFieldset = document.createElement('fieldset');
            categoryFieldset.className = 'iiidem-admin-working-category mb-3';

            var legend = document.createElement('legend');
            legend.className = 'fs-6 fw-semibold';
            legend.textContent = 'Working professional category';
            categoryFieldset.appendChild(legend);

            var selectedCategory = categories.find(function(category) {
                return category.input.checked;
            });

            categoryFieldset.appendChild(
                createRadio(
                    'iiidem_workingcategory_admin',
                    '',
                    'id_iiidem_workingcategory_admin_none',
                    'None',
                    !selectedCategory
                )
            );

            categories.forEach(function(category) {
                categoryFieldset.appendChild(
                    createRadio(
                        'iiidem_workingcategory_admin',
                        category.name,
                        'id_iiidem_workingcategory_admin_' + category.name,
                        category.label,
                        !!(selectedCategory && selectedCategory.name === category.name)
                    )
                );
            });

            parent.insertBefore(categoryFieldset, insertBefore);
            lastInserted = categoryFieldset;

            function syncCategories() {
                var selectedRadio = categoryFieldset.querySelector(
                    'input[name="iiidem_workingcategory_admin"]:checked'
                );
                var selectedName = selectedRadio ? selectedRadio.value : '';
                categories.forEach(function(category) {
                    category.input.checked = category.name === selectedName;
                    category.input.value = category.input.checked ? '1' : '0';
                });
            }

            categoryFieldset.addEventListener('change', syncCategories);
            form.addEventListener('submit', syncCategories);
        }

        // Separate section (like "IIIDEM registration") for fee exemption.
        if (embInput) {
            var feeSection = document.createElement('div');
            feeSection.className = 'iiidem-admin-profile-section mb-3';
            feeSection.setAttribute('role', 'group');
            feeSection.setAttribute('aria-labelledby', 'id_iiidem_allow_without_payment_heading');

            var feeHeading = document.createElement('h3');
            feeHeading.id = 'id_iiidem_allow_without_payment_heading';
            feeHeading.className = 'iiidem-admin-profile-section__title';
            feeHeading.textContent = 'Allow without payment course';
            feeSection.appendChild(feeHeading);

            var feeFieldset = document.createElement('fieldset');
            feeFieldset.className = 'iiidem-admin-profile-section__body';
            feeSection.appendChild(feeFieldset);

            var allowWithoutPayment = !!embInput.checked;

            feeFieldset.appendChild(
                createRadio(
                    'iiidem_allow_without_payment_admin',
                    '0',
                    'id_iiidem_allow_without_payment_no',
                    'No — require course fee payment',
                    !allowWithoutPayment
                )
            );
            feeFieldset.appendChild(
                createRadio(
                    'iiidem_allow_without_payment_admin',
                    '1',
                    'id_iiidem_allow_without_payment_yes',
                    'Yes — allow without payment course',
                    allowWithoutPayment
                )
            );

            if (lastInserted && lastInserted.nextSibling) {
                parent.insertBefore(feeSection, lastInserted.nextSibling);
            } else if (lastInserted) {
                parent.insertBefore(feeSection, lastInserted.nextSibling);
            } else {
                parent.insertBefore(feeSection, insertBefore);
            }

            function syncFeeExemption() {
                var selectedRadio = feeFieldset.querySelector(
                    'input[name="iiidem_allow_without_payment_admin"]:checked'
                );
                var allow = selectedRadio && selectedRadio.value === '1';
                embInput.checked = allow;
                embInput.value = allow ? '1' : '0';
            }

            feeFieldset.addEventListener('change', syncFeeExemption);
            form.addEventListener('submit', syncFeeExemption);
        }

        // Keep electoral practitioner cleared on save.
        form.addEventListener('submit', function() {
            var electoral = getProfileCheckbox('profile_field_iiidem_electoral_practitioner');
            if (electoral) {
                electoral.checked = false;
                electoral.value = '0';
            }
        });

        form.dataset.iiidemRegistrationProfileReady = '1';
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
