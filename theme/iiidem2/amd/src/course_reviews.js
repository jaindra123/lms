/**
 * Interactive star rating for the course review form.
 *
 * @module theme_iiidem2/course_reviews
 */
define([], function() {

    /**
     * Update visible stars and hidden rating input.
     *
     * @param {HTMLElement} form
     * @param {number} rating
     */
    function setRating(form, rating) {
        var input = form.querySelector('.iiidem-review-form__rating-input');
        if (input) {
            input.value = String(rating);
        }

        form.querySelectorAll('.iiidem-review-stars__btn').forEach(function(button) {
            var value = parseInt(button.getAttribute('data-rating'), 10);
            button.classList.toggle('is-active', value <= rating);
        });
    }

    /**
     * Initialise review star pickers on the course page.
     */
    function init() {
        document.querySelectorAll('.iiidem-review-form').forEach(function(form) {
            var input = form.querySelector('.iiidem-review-form__rating-input');
            var current = input ? parseInt(input.value, 10) : 5;
            if (!current || current < 1) {
                current = 5;
            }
            setRating(form, current);

            form.addEventListener('click', function(event) {
                var button = event.target.closest('.iiidem-review-stars__btn');
                if (!button || !form.contains(button)) {
                    return;
                }
                event.preventDefault();
                setRating(form, parseInt(button.getAttribute('data-rating'), 10));
            });
        });
    }

    return {
        init: init
    };
});
