/**
 * Direct course fee payment via PNB, ICICI, or Razorpay gateway (no gateway picker modal).
 *
 * @module     theme_iiidem2/course_payment
 * @copyright  2026 IIIDEM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/notification'], function(Notification) {

    var SELECTOR = '[data-action="theme_iiidem2/triggerCoursePayment"]';
    var initialised = false;

    /**
     * Pull a readable message from Moodle Ajax / Error / string rejects.
     *
     * @param {*} err
     * @returns {string}
     */
    function extractErrorMessage(err) {
        if (!err) {
            return '';
        }
        if (typeof err === 'string') {
            return err;
        }
        if (err.message && typeof err.message === 'string') {
            return err.message;
        }
        if (typeof err.error === 'string') {
            return err.error;
        }
        if (err.error && typeof err.error === 'object' && err.error.message) {
            return err.error.message;
        }
        if (err.exception && typeof err.exception === 'object' && err.exception.message) {
            return err.exception.message;
        }
        if (err.exception && typeof err.exception === 'string') {
            return err.exception;
        }
        return '';
    }

    /**
     * Show exactly one Moodle dialog — never Razorpay UI.
     *
     * @param {string} message
     */
    function showPaymentError(message) {
        var text = message || 'Payment could not be started. Please try again.';
        Notification.alert('Payment not started', text, 'OK');
    }

    /**
     * Redirect to the selected payment gateway.
     *
     * @param {string} gateway
     * @param {string} component
     * @param {string} paymentArea
     * @param {string} itemId
     * @param {string} description
     * @returns {Promise}
     */
    function processPayment(gateway, component, paymentArea, itemId, description) {
        return new Promise(function(resolve, reject) {
            require(['paygw_' + gateway + '/gateways_modal'], function(paymentMethod) {
                paymentMethod.process(component, paymentArea, itemId, description)
                    .then(resolve)
                    .catch(reject);
            }, function(err) {
                reject(err || new Error('Payment module could not be loaded.'));
            });
        });
    }

    /**
     * Register click handlers for payment buttons.
     */
    function registerEventListeners() {
        document.addEventListener('click', function(e) {
            var trigger = e.target.closest(SELECTOR);
            if (!trigger) {
                return;
            }

            e.preventDefault();

            var gateway = trigger.dataset.gateway;
            if (!gateway) {
                return;
            }

            var component = trigger.dataset.component;
            var paymentArea = trigger.dataset.paymentarea;
            var itemId = trigger.dataset.itemid;
            var description = trigger.dataset.description || '';

            if (!component || !paymentArea || !itemId) {
                return;
            }

            trigger.disabled = true;
            processPayment(gateway, component, paymentArea, itemId, description)
                .catch(function(err) {
                    trigger.disabled = false;
                    showPaymentError(extractErrorMessage(err));
                });
        });
    }

    return {
        init: function() {
            if (initialised) {
                return;
            }
            initialised = true;
            registerEventListeners();
        }
    };
});
