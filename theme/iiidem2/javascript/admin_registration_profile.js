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

        var obsoleteNames = ['profile_field_iiidem_electoral_practitioner'];
        var categories = [
            {name: 'profile_field_iiidem_policymaker', label: 'Policymaker'},
            {name: 'profile_field_iiidem_journalist', label: 'Journalist'},
            {name: 'profile_field_iiidem_researcher', label: 'Researcher'},
            {
                name: 'profile_field_iiidem_emb',
                label: 'Allow without payment course'
            }
        ];

        function getProfileCheckbox(name) {
            return form.querySelector(
                'input[type="checkbox"][name="' + name + '"]'
            );
        }

        obsoleteNames.forEach(function(name) {
            var input = getProfileCheckbox(name);
            var item = input ? input.closest('.fitem') : document.getElementById('fitem_id_' + name);
            if (item) {
                item.hidden = true;
                item.style.display = 'none';
            }
        });

        var available = categories.map(function(category) {
            var input = getProfileCheckbox(category.name);
            return input ? Object.assign({}, category, {
                input: input,
                item: input.closest('.fitem')
            }) : null;
        }).filter(Boolean);

        if (!available.length || !available[0].item) {
            return;
        }

        var fieldset = document.createElement('fieldset');
        fieldset.className = 'iiidem-admin-working-category mb-3';
        fieldset.innerHTML =
            '<legend class="col-form-label fw-bold">Working professional category</legend>';

        var options = [{name: '', label: 'None'}].concat(available);
        var selected = available.find(function(category) {
            return category.name === 'profile_field_iiidem_emb' && category.input.checked;
        }) || available.find(function(category) {
            return category.input.checked;
        });

        options.forEach(function(option) {
            var wrapper = document.createElement('div');
            wrapper.className = 'form-check mb-2';

            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'iiidem_workingcategory_admin';
            radio.value = option.name;
            radio.id = 'id_iiidem_workingcategory_admin_' + (option.name || 'none');
            radio.className = 'form-check-input';
            radio.checked = selected ? selected.name === option.name : option.name === '';

            var label = document.createElement('label');
            label.className = 'form-check-label';
            label.htmlFor = radio.id;
            label.textContent = option.label;

            wrapper.appendChild(radio);
            wrapper.appendChild(label);
            fieldset.appendChild(wrapper);
        });

        available[0].item.parentNode.insertBefore(fieldset, available[0].item);
        available.forEach(function(category) {
            if (category.item) {
                category.item.hidden = true;
                category.item.style.display = 'none';
            }
        });

        function syncProfileFields() {
            var selectedRadio = fieldset.querySelector(
                'input[name="iiidem_workingcategory_admin"]:checked'
            );
            var selectedName = selectedRadio ? selectedRadio.value : '';

            available.forEach(function(category) {
                category.input.checked = category.name === selectedName;
                category.input.value = category.input.checked ? '1' : '0';
            });

            obsoleteNames.forEach(function(name) {
                var input = getProfileCheckbox(name);
                if (input) {
                    input.checked = false;
                    input.value = '0';
                }
            });
        }

        fieldset.addEventListener('change', syncProfileFields);
        form.addEventListener('submit', syncProfileFields);
        form.dataset.iiidemRegistrationProfileReady = '1';
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
