/**
 * Razorpay payment gateway — redirect to hosted Payment Link.
 *
 * CDAC Key ID exposure: do not load Checkout.js on the LMS origin (that sent
 * key id, order id, name, and email to lumberjack). The server creates a
 * Payment Link; this module only follows the short URL.
 *
 * @module     paygw_razorpay/gateways_modal
 * @copyright  2026 IIIDEM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Repository from './repository';

/**
 * Open a never-resolving promise (used after redirect).
 *
 * @returns {Promise}
 */
const pendingForever = () => new Promise(() => {
    // Intentionally left pending — page navigates away.
});

/**
 * Process Razorpay payment for Moodle core_payment.
 *
 * @param {string} component
 * @param {string} paymentArea
 * @param {number} itemId
 * @param {string} description
 * @returns {Promise}
 */
export const process = (component, paymentArea, itemId, description) => {
    return Repository.getCheckoutData(component, paymentArea, itemId, description)
        .then((data) => {
            const url = (data && data.mock && data.mockurl)
                ? data.mockurl
                : (data && data.redirecturl);
            if (!url) {
                return Promise.reject(new Error('Payment could not be started. Please try again.'));
            }
            window.location.replace(url);
            return pendingForever();
        })
        .catch(() => Promise.reject(new Error('Payment could not be started. Please try again.')));
};
