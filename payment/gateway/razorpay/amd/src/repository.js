/**
 * Razorpay payment gateway AJAX repository.
 *
 * @module     paygw_razorpay/repository
 * @copyright  2026 IIIDEM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

export const getCheckoutData = (component, paymentArea, itemId, description) => {
    const request = {
        methodname: 'paygw_razorpay_get_checkout_data',
        args: {
            component: component,
            paymentarea: paymentArea,
            itemid: itemId,
            description: description,
        },
    };
    return Ajax.call([request])[0];
};

export const verifyPayment = (orderId, paymentId, signature) => {
    const request = {
        methodname: 'paygw_razorpay_verify_payment',
        args: {
            orderid: orderId,
            paymentid: paymentId,
            signature: signature,
        },
    };
    return Ajax.call([request])[0];
};
