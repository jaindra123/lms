/**
 * PNB payment gateway AJAX repository.
 *
 * @module     paygw_pnb/repository
 * @copyright  2026 IIIDEM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Fetch PNB redirect form fields for a payable item.
 *
 * @param {string} component
 * @param {string} paymentArea
 * @param {number} itemId
 * @param {string} description
 * @returns {Promise<{gatewayurl: string, method: string, fields: Array}>}
 */
export const getRedirectForm = (component, paymentArea, itemId, description) => {
    const request = {
        methodname: 'paygw_pnb_get_redirect_form',
        args: {
            component: component,
            paymentarea: paymentArea,
            itemid: itemId,
            description: description,
        },
    };
    return Ajax.call([request])[0];
};
