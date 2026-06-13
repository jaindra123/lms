/**
 * Enrollment and course payment modal handlers.
 *
 * @module theme_iiidem2/enroll
 */

define(['jquery', 'theme_boost/bootstrap/modal'], function($, BoostModal) {

    /**
     * Hide a Bootstrap modal (BS5 global or Boost BS4/jQuery).
     *
     * @param {HTMLElement|null} element
     */
    function hideModal(element) {
        if (!element) {
            return;
        }

        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var bs5 = bootstrap.Modal.getInstance(element);
            if (bs5) {
                bs5.hide();
                return;
            }
        }

        var data = $(element).data('bs.modal');
        if (data && typeof data.hide === 'function') {
            data.hide();
            return;
        }

        $(element).modal('hide');
    }

    /**
     * Scroll to the course fee card and briefly highlight it.
     *
     * @param {string} selector
     */
    function scrollToFeeCard(selector) {
        var target = selector ? document.querySelector(selector) : null;
        if (!target) {
            return;
        }

        target.scrollIntoView({behavior: 'smooth', block: 'start'});
        target.classList.add('iiidem-course-fee--highlight');
        window.setTimeout(function() {
            target.classList.remove('iiidem-course-fee--highlight');
        }, 2400);
    }

    /**
     * Close payment alert, scroll to fee card, and open PNB payment when available.
     *
     * @param {HTMLElement} button
     */
    function goToCoursePayment(button) {
        var paymentModalEl = document.getElementById('iiidemPaymentRequiredModal');
        var scrollTarget = button.getAttribute('data-scroll-target') || '#iiidem-course-fee';
        var paymentTrigger = button.getAttribute('data-payment-trigger');

        hideModal(paymentModalEl);
        scrollToFeeCard(scrollTarget);

        if (paymentTrigger) {
            window.setTimeout(function() {
                var triggerBtn = document.querySelector(paymentTrigger);
                if (triggerBtn) {
                    triggerBtn.click();
                }
            }, 500);
        }
    }

    /**
     * Initialize enroll modal events.
     */
    function init() {

        var modalInstance = null;
        var paymentRequiredModal = document.getElementById('iiidemPaymentRequiredModal');

        if (paymentRequiredModal) {
            paymentRequiredModal.addEventListener('shown.bs.modal', function() {
                scrollToFeeCard('#iiidem-course-fee');
            });
        }

        document.addEventListener('click', function(e) {

            var okbtn = e.target.closest('.iiidem-payment-required-ok');

            if (okbtn) {
                e.preventDefault();
                goToCoursePayment(okbtn);
                return;
            }

            var btn = e.target.closest('.enroll-btn');

            if (!btn) {
                return;
            }

            var modalEl = document.getElementById('enrollModal');

            if (!modalEl) {
                return;
            }

            modalInstance = new BoostModal(modalEl);
            modalInstance.show();
        });

        document.addEventListener('click', function(e) {

            if (e.target.id === 'cancelEnroll' && modalInstance) {
                modalInstance.hide();
            }
        });
    }

    return {
        init: init
    };
});
