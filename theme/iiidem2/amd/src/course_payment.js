/**
 * Direct course fee payment via PNB, ICICI, or Razorpay gateway (no gateway picker modal).
 *
 * @module     theme_iiidem2/course_payment
 * @copyright  2026 IIIDEM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var SELECTOR = '[data-action="theme_iiidem2/triggerCoursePayment"]';
    var LOADING_ID = 'iiidem-payment-loading';
    var ERROR_MODAL_ID = 'iiidem-payment-error-modal';
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
     * Show full-page loading overlay while payment is starting.
     *
     * @param {HTMLElement} trigger
     */
    function showLoading(trigger) {
        hideLoading();

        if (trigger) {
            trigger.classList.add('iiidem-paybtn--loading');
            trigger.setAttribute('aria-busy', 'true');
            if (!trigger.dataset.originalHtml) {
                trigger.dataset.originalHtml = trigger.innerHTML;
            }
            trigger.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>'
                + 'Please wait…';
        }

        var overlay = document.createElement('div');
        overlay.id = LOADING_ID;
        overlay.className = 'iiidem-payment-loading';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        overlay.innerHTML = ''
            + '<div class="iiidem-payment-loading__card">'
            +   '<div class="iiidem-payment-loading__spinner" aria-hidden="true"></div>'
            +   '<p class="iiidem-payment-loading__title">Connecting to payment gateway</p>'
            +   '<p class="iiidem-payment-loading__text">Please wait while we prepare your secure payment…</p>'
            + '</div>';
        document.body.appendChild(overlay);
        document.body.classList.add('iiidem-payment-loading-open');
    }

    /**
     * Remove loading overlay and restore Pay Now button.
     *
     * @param {HTMLElement|null} trigger
     */
    function hideLoading(trigger) {
        var overlay = document.getElementById(LOADING_ID);
        if (overlay) {
            overlay.remove();
        }
        document.body.classList.remove('iiidem-payment-loading-open');

        if (trigger) {
            trigger.classList.remove('iiidem-paybtn--loading');
            trigger.removeAttribute('aria-busy');
            trigger.disabled = false;
            if (trigger.dataset.originalHtml) {
                trigger.innerHTML = trigger.dataset.originalHtml;
            }
        }
    }

    /**
     * Compact payment error dialog (avoids Moodle alert modal whitespace).
     *
     * @param {string} message
     */
    function showPaymentError(message) {
        var text = message || 'Payment could not be started. Please try again.';
        var existing = document.getElementById(ERROR_MODAL_ID);
        if (existing) {
            existing.remove();
        }

        var wrap = document.createElement('div');
        wrap.id = ERROR_MODAL_ID;
        wrap.className = 'iiidem-payment-error-modal';
        wrap.setAttribute('role', 'dialog');
        wrap.setAttribute('aria-modal', 'true');
        wrap.setAttribute('aria-labelledby', 'iiidem-payment-error-title');
        wrap.innerHTML = ''
            + '<div class="iiidem-payment-error-modal__backdrop" data-dismiss="iiidem-payment-error"></div>'
            + '<div class="iiidem-payment-error-modal__dialog">'
            +   '<div class="iiidem-payment-error-modal__header">'
            +     '<h2 id="iiidem-payment-error-title" class="iiidem-payment-error-modal__title">Payment not started</h2>'
            +     '<button type="button" class="iiidem-payment-error-modal__close" data-dismiss="iiidem-payment-error" aria-label="Close">&times;</button>'
            +   '</div>'
            +   '<div class="iiidem-payment-error-modal__body">'
            +     '<p class="iiidem-payment-error-modal__message"></p>'
            +   '</div>'
            +   '<div class="iiidem-payment-error-modal__footer">'
            +     '<button type="button" class="btn btn-primary" data-dismiss="iiidem-payment-error">OK</button>'
            +   '</div>'
            + '</div>';

        wrap.querySelector('.iiidem-payment-error-modal__message').textContent = text;
        document.body.appendChild(wrap);
        document.body.classList.add('iiidem-payment-error-open');

        var close = function() {
            wrap.remove();
            document.body.classList.remove('iiidem-payment-error-open');
        };

        wrap.querySelectorAll('[data-dismiss="iiidem-payment-error"]').forEach(function(el) {
            el.addEventListener('click', close);
        });

        var okBtn = wrap.querySelector('.iiidem-payment-error-modal__footer .btn');
        if (okBtn) {
            okBtn.focus();
        }
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

            if (trigger.disabled || trigger.classList.contains('iiidem-paybtn--loading')) {
                return;
            }

            trigger.disabled = true;
            showLoading(trigger);

            var razorpayWatch = window.setInterval(function() {
                if (document.querySelector(
                    '.razorpay-container, .razorpay-backdrop, .razorpay-checkout-frame, iframe[src*="razorpay"]'
                )) {
                    window.clearInterval(razorpayWatch);
                    // Checkout is visible — drop overlay; keep button locked until result.
                    var overlay = document.getElementById(LOADING_ID);
                    if (overlay) {
                        overlay.remove();
                    }
                    document.body.classList.remove('iiidem-payment-loading-open');
                }
            }, 200);

            processPayment(gateway, component, paymentArea, itemId, description)
                .then(function() {
                    window.clearInterval(razorpayWatch);
                    hideLoading(trigger);
                })
                .catch(function(err) {
                    window.clearInterval(razorpayWatch);
                    hideLoading(trigger);
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
