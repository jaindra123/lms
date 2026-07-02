/**
 * Razorpay payment gateway — opens Razorpay Checkout.
 *
 * @module     paygw_razorpay/gateways_modal
 * @copyright  2026 IIIDEM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Repository from './repository';

const SCRIPT_URL = 'https://checkout.razorpay.com/v1/checkout.js';
let scriptPromise = null;

const loadRazorpayScript = () => {
    if (window.Razorpay) {
        return Promise.resolve();
    }
    if (!scriptPromise) {
        scriptPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = SCRIPT_URL;
            script.async = true;
            script.onload = () => resolve();
            script.onerror = () => reject('Could not load Razorpay checkout.');
            document.head.appendChild(script);
        });
    }
    return scriptPromise;
};

export const process = (component, paymentArea, itemId, description) => {
    return Repository.getCheckoutData(component, paymentArea, itemId, description)
        .then((data) => {
            if (data.mock && data.mockurl) {
                window.location.assign(data.mockurl);
                return new Promise(() => {});
            }

            return loadRazorpayScript().then(() => new Promise((resolve, reject) => {
                const options = {
                    key: data.keyid,
                    amount: data.amount,
                    currency: data.currency,
                    name: data.brandname,
                    description: data.description,
                    order_id: data.orderid,
                    prefill: {
                        name: data.username,
                        email: data.useremail,
                    },
                    theme: {
                        color: '#0b3d91',
                    },
                    handler: (response) => {
                        Repository.verifyPayment(
                            response.razorpay_order_id,
                            response.razorpay_payment_id,
                            response.razorpay_signature
                        ).then((result) => {
                            window.location.assign(result.redirecturl);
                            resolve();
                        }).catch(reject);
                    },
                    modal: {
                        ondismiss: () => reject('Payment cancelled.'),
                    },
                };

                const checkout = new window.Razorpay(options);
                checkout.on('payment.failed', (response) => {
                    const message = response.error && response.error.description
                        ? response.error.description
                        : 'Payment failed.';
                    reject(message);
                });
                checkout.open();
            }));
        });
};
