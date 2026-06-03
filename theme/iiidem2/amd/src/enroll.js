/**
 * Enrollment modal handler.
 *
 * @module theme_iiidem/enroll
 */

define(['theme_boost/bootstrap/modal'], function(Modal) {

    /**
     * Initialize enroll modal events.
     */
    function init() {

        var modalInstance = null;

        document.addEventListener('click', function(e) {

            var btn = e.target.closest('.enroll-btn');

            if (!btn) {
                return;
            }

            var modalEl = document.getElementById('enrollModal');

            if (!modalEl) {
                return;
            }

            modalInstance = new Modal(modalEl);

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