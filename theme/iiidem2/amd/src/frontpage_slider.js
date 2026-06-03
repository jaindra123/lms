/**
 * Homepage hero carousel (Bootstrap 4).
 *
 * @module theme_iiidem2/frontpage_slider
 */
define(['jquery', 'theme_iiidem2/bootstrap/carousel'], function($) {

    /**
     * Start the front page slider cycling through slides.
     */
    function init() {
        var $slider = $('#homepageSlider');
        if (!$slider.length) {
            return;
        }

        $slider.carousel({
            interval: 5000,
            pause: false,
            wrap: true,
            keyboard: true
        });

        if ($slider.find('.carousel-item').length > 1) {
            $slider.carousel('cycle');
        }
    }

    return {
        init: init
    };
});
