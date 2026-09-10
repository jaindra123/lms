/**
 * Razorpay payment gateway — opens Razorpay Checkout only when healthy.
 *
 * @module     paygw_razorpay/gateways_modal
 * @copyright  2026 IIIDEM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Repository from './repository';

const SCRIPT_URL = 'https://checkout.razorpay.com/v1/checkout.js';
let scriptPromise = null;

/**
 * Load the Razorpay checkout script once.
 *
 * @returns {Promise}
 */
const loadRazorpayScript = () => {
    if (window.Razorpay) {
        return Promise.resolve();
    }
    if (scriptPromise) {
        return scriptPromise;
    }

    scriptPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = SCRIPT_URL;
        script.async = true;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(
            'Razorpay payment gateway is currently experiencing a temporary service disruption. '
            + 'This issue is on Razorpay side and is not related to our side or payment configuration. '
            + 'Please try again in a few minutes.'
        ));
        document.head.appendChild(script);
    });

    return scriptPromise;
};

/**
 * Best-effort failure report (never blocks UI).
 *
 * @param {string|number|null} orderId
 * @param {string} reason
 * @returns {Promise}
 */
const reportFailure = (orderId, reason) => {
    if (!orderId) {
        return Promise.resolve();
    }
    return Repository.reportPaymentFailure(orderId, reason || 'Payment failed or cancelled.')
        .catch(() => {
            // Do not block UI if failure notification AJAX fails.
        });
};

/**
 * Open a never-resolving promise (used after redirect).
 *
 * @returns {Promise}
 */
const pendingForever = () => new Promise(() => {
    // Intentionally left pending — page navigates away.
});

/**
 * Close Razorpay UI and remove leftover overlays.
 *
 * @param {Object|null} checkout
 */
const dismissRazorpayUi = (checkout) => {
    try {
        if (checkout && typeof checkout.close === 'function') {
            checkout.close();
        }
    } catch (e) {
        // Ignore.
    }

    document.querySelectorAll(
        '.razorpay-container, .razorpay-backdrop, .razorpay-checkout-frame, iframe[src*="razorpay"]'
    ).forEach((el) => {
        try {
            el.remove();
        } catch (e) {
            // Ignore.
        }
    });

    document.body.classList.remove('razorpay-modal-open');
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
};

/**
 * @param {string} message
 * @returns {string}
 */
const friendlyFailureMessage = (message) => {
    const text = (message || '').toString();
    const lower = text.toLowerCase();

    if (!text
            || lower.includes('unexpected error')
            || lower.includes('payment failed')
            || lower.includes('something went wrong')
            || lower.includes('server_error')
            || lower.includes('server error')
            || lower.includes('temporarily unavailable')
            || lower.includes('checkout')
            || lower.includes('grpc')
            || lower.includes('internal error')
            || lower.includes('request_failed')) {
        return 'Razorpay payment gateway is currently experiencing a temporary service disruption. '
            + 'This issue is on Razorpay side and is not related to our side or payment configuration. '
            + 'Please try again in a few minutes.';
    }

    if (lower.includes('cancelled') || lower.includes('canceled')) {
        return 'Payment cancelled.';
    }

    return text;
};

/**
 * Process Razorpay payment for Moodle core_payment.
 *
 * Checkout is opened only after getCheckoutData succeeds. That webservice now probes
 * Razorpay preferences first, so a down Checkout never reaches open().
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
            if (data.mock && data.mockurl) {
                window.location.assign(data.mockurl);
                return pendingForever();
            }

            return loadRazorpayScript().then(() => {
                return new Promise((resolve, reject) => {
                    let settled = false;
                    let checkout = null;

                    const finish = (message, isError) => {
                        if (settled) {
                            return;
                        }
                        settled = true;
                        dismissRazorpayUi(checkout);

                        const finalMessage = isError
                            ? friendlyFailureMessage(message)
                            : (message || '');
                        const afterReport = () => {
                            if (isError) {
                                reject(new Error(finalMessage));
                            } else {
                                resolve();
                            }
                        };

                        if (isError) {
                            reportFailure(data.orderid, finalMessage).finally(afterReport);
                        } else {
                            afterReport();
                        }
                    };

                    const options = {
                        key: data.keyid,
                        amount: data.amount,
                        currency: data.currency,
                        name: data.brandname,
                        description: data.description,
                        order_id: data.orderid,
                        // No name/email prefill — avoids PII in get_checkout_data JSON (CDAC).
                        theme: {
                            color: '#0b3d91',
                        },
                        handler: (response) => {
                            Repository.verifyPayment(
                                response.razorpay_order_id,
                                response.razorpay_payment_id,
                                response.razorpay_signature
                            ).then((result) => {
                                settled = true;
                                dismissRazorpayUi(checkout);
                                window.location.assign(result.redirecturl);
                                resolve();
                            }).catch((error) => {
                                finish((error && error.message) || 'Payment verification failed.', true);
                            });
                        },
                        modal: {
                            ondismiss: () => {
                                finish('Payment cancelled.', true);
                            },
                        },
                    };

                    try {
                        checkout = new window.Razorpay(options);
                        checkout.on('payment.failed', (response) => {
                            finish(
                                (response.error && response.error.description) || 'Payment failed.',
                                true
                            );
                        });
                        checkout.open();
                    } catch (error) {
                        finish((error && error.message) || 'Could not open Razorpay checkout.', true);
                    }
                });
            });
        });
};
