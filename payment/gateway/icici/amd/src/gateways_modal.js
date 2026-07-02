/**
 * ICICI payment gateway — redirect to bank.
 *
 * @module     paygw_icici/gateways_modal
 * @copyright  2026 IIIDEM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Repository from './repository';

const submitRedirectForm = (formdata) => {
    const form = document.createElement('form');
    form.method = formdata.method || 'post';
    form.action = formdata.gatewayurl;
    form.style.display = 'none';

    formdata.fields.forEach((field) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = field.name;
        input.value = field.value;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
};

export const process = (component, paymentArea, itemId, description) => {
    return Repository.getRedirectForm(component, paymentArea, itemId, description)
        .then((formdata) => {
            submitRedirectForm(formdata);
            return new Promise(() => {});
        });
};
