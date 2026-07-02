/**
 * ICICI payment gateway AJAX repository.
 *
 * @module     paygw_icici/repository
 * @copyright  2026 IIIDEM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

export const getRedirectForm = (component, paymentArea, itemId, description) => {
    const request = {
        methodname: 'paygw_icici_get_redirect_form',
        args: {
            component: component,
            paymentarea: paymentArea,
            itemid: itemId,
            description: description,
        },
    };
    return Ajax.call([request])[0];
};
