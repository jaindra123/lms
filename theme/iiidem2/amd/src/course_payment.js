/**
 * Direct course fee payment via PNB or ICICI gateway (no gateway picker modal).
 *
 * @module     theme_iiidem2/course_payment
 * @copyright  2026 IIIDEM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/notification'], function(Notification) {

    var SELECTOR = '[data-action="theme_iiidem2/triggerCoursePayment"]';
    var initialised = false;

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
                reject(err);
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
                    var message = err;
                    if (err && typeof err === 'object') {
                        message = err.message || err.error || err.exception || '';
                    }
                    if (!message || typeof message !== 'string') {
                        message = 'Payment could not be started. Please try again.';
                    }
                    Notification.alert('', message);
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
