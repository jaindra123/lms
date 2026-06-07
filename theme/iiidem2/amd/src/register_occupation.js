/**
 * Registration form: mutually exclusive occupation checkboxes.
 *
 * @module theme_iiidem2/register_occupation
 */
define([], function() {

    const CHECKBOXES = [
        'occupation_working',
        'occupation_student',
        'occupation_instructor',
    ];

    /**
     * Keep only one occupation checkbox selected at a time.
     */
    function init() {
        const elements = CHECKBOXES
            .map((name) => document.getElementById('id_' + name))
            .filter(Boolean);

        if (!elements.length) {
            return;
        }

        elements.forEach((checkbox) => {
            checkbox.addEventListener('change', function() {
                if (!checkbox.checked) {
                    return;
                }
                elements.forEach((other) => {
                    if (other !== checkbox) {
                        other.checked = false;
                        other.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                });
            });
        });
    }

    return {
        init: init,
    };
});
