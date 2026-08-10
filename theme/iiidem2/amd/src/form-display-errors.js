// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Custom form error event handler to manipulate the bootstrap markup and show
 * nicely styled errors in an mform.
 *
 * @module     theme_iiidem2/form-display-errors
 * @copyright  2016 Damyon Wiese <damyon@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core_form/events'], function($, FormEvent) {
    let focusedAlready = false;

    /**
     * Place validation text beside the label, e.g. "City/town * Required".
     * For Role radios, place it beside the Role heading (not between options).
     *
     * @param {jQuery} parent
     * @param {String} msg
     */
    const syncLabelError = (parent, msg) => {
        let labelWrap = parent.children('.col-form-label, .col-md-3').first();
        if (!labelWrap.length) {
            labelWrap = parent.find('> .col-form-label, > .col-md-3').first();
        }

        const isOccupation = parent.find('input[name="occupation"]').length > 0;
        const isWorkingCategory = parent.find('input[name="workingcategory"]').length > 0;
        if (isOccupation) {
            // Always attach Role error to the section heading — never between radios.
            labelWrap = $('#id_occupationheader .ftoggler').first();
            if (!labelWrap.length) {
                labelWrap = $('#id_occupationheader h3').first();
            }
        } else if (isWorkingCategory) {
            labelWrap = $('#id_workingheader .ftoggler').first();
            if (!labelWrap.length) {
                labelWrap = $('#id_workingheader h3').first();
            }
        } else if (!labelWrap.length || !$.trim(labelWrap.clone().children('.iiidem-label-error').remove().end().text())) {
            labelWrap = parent.find('.col-form-label').first();
        }

        if (!labelWrap.length) {
            return;
        }

        let near = labelWrap.find('> .iiidem-label-error');
        if (!near.length) {
            near = $('<span class="iiidem-label-error" role="status"></span>');
            labelWrap.append(near);
        }
        if (msg) {
            near.text(msg).attr('hidden', false).show();
            labelWrap.addClass('iiidem-label-has-error');
        } else {
            near.text('').hide();
            labelWrap.removeClass('iiidem-label-has-error');
        }
    };

    const clearLabelErrors = (parent) => {
        parent.find('.iiidem-label-error').remove();
        parent.find('.iiidem-label-has-error').removeClass('iiidem-label-has-error');
    };

    return {
        /**
         * Enhance the supplied element to handle form field errors.
         *
         * @method
         * @param {String} elementid
         * @listens event:formFieldValidationFailed
         */
        enhance: function(elementid) {
            var element = document.getElementById(elementid);
            if (!element) {
                return;
            }

            element.addEventListener(FormEvent.eventTypes.formFieldValidationFailed, e => {
                const msg = e.detail.message;
                e.preventDefault();

                var parent = $(element).closest('.fitem');
                var feedback = parent.find('.form-control-feedback');
                const feedbackId = feedback.attr('id');

                let describedBy = $(element).attr('aria-describedby');
                if (typeof describedBy === "undefined") {
                    describedBy = '';
                }
                let describedByIds = [];
                if (describedBy.length) {
                    describedByIds = describedBy.split(" ");
                }
                const feedbackIndex = describedByIds.indexOf(feedbackId);

                if (element.tagName === 'TEXTAREA') {
                    const contentEditable = parent.find('[contenteditable]');
                    if (contentEditable.length > 0) {
                        element = contentEditable[0];
                    } else {
                        element = document.getElementById(`${element.id}_ifr`) || element;
                    }
                }

                if (msg !== '') {
                    parent.addClass('has-danger');
                    parent.data('client-validation-error', true);
                    $(element).addClass('is-invalid');
                    if (feedbackIndex === -1) {
                        describedByIds.push(feedbackId);
                        $(element).attr('aria-describedby', describedByIds.join(" "));
                    }
                    $(element).attr('aria-invalid', true);
                    feedback.html(msg);
                    // Hide under-field copy; show beside label instead.
                    feedback.addClass('iiidem-feedback-sr');
                    syncLabelError(parent, msg);

                    if (!focusedAlready) {
                        element.scrollIntoView({behavior: "smooth", block: "center"});
                        focusedAlready = true;
                        setTimeout(()=> {
                            element.focus({preventScroll: true});
                            focusedAlready = false;
                        }, 0);
                    }

                } else {
                    if (parent.data('client-validation-error') === true) {
                        parent.removeClass('has-danger');
                        parent.data('client-validation-error', false);
                        $(element).removeClass('is-invalid');
                        if (feedbackIndex > -1) {
                            describedByIds.splice(feedbackIndex, 1);
                        }
                        if (describedByIds.length) {
                            describedBy = describedByIds.join(" ");
                            $(element).attr('aria-describedby', describedBy);
                        } else {
                            $(element).removeAttr('aria-describedby');
                        }
                        $(element).attr('aria-invalid', false);
                        feedback.hide().removeClass('iiidem-feedback-sr');
                        clearLabelErrors(parent);
                        if (parent.find('input[name="occupation"]').length) {
                            $('#id_occupationheader .iiidem-label-error').remove();
                            $('#id_occupationheader .iiidem-label-has-error').removeClass('iiidem-label-has-error');
                        }
                        if (parent.find('input[name="workingcategory"]').length) {
                            $('#id_workingheader .iiidem-label-error').remove();
                            $('#id_workingheader .iiidem-label-has-error').removeClass('iiidem-label-has-error');
                        }
                    }
                }
            });

            var form = element.closest('form');
            if (form && !('iiidem2FormErrorsEnhanced' in form.dataset)) {
                form.addEventListener('submit', function() {
                    var visibleError = $('.iiidem-label-error:visible');
                    if (visibleError.length) {
                        visibleError[0].scrollIntoView({behavior: "smooth", block: "center"});
                    }
                });
                // Server-rendered errors: mirror beside labels on load.
                $(form).find('.fitem').each(function() {
                    const item = $(this);
                    const text = $.trim(item.find('.form-control-feedback').first().text());
                    if (text) {
                        item.addClass('has-danger');
                        item.find('.form-control-feedback').addClass('iiidem-feedback-sr');
                        syncLabelError(item, text);
                    }
                });
                form.dataset.iiidem2FormErrorsEnhanced = 1;
            }
        }
    };
});
