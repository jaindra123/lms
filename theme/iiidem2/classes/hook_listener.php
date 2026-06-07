<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Theme hook listeners.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_listener {

    /**
     * Send users to their role dashboard after login (unless they requested another page).
     *
     * @param \core_user\hook\after_login_completed $hook
     */
    public static function after_login_completed(\core_user\hook\after_login_completed $hook): void {
        global $CFG, $SESSION;

        if (isguestuser()) {
            return;
        }

        // Theme lib.php is not loaded yet during login; helpers live there.
        require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

        if (!empty($SESSION->wantsurl) && !\theme_iiidem2_is_generic_login_landing($SESSION->wantsurl)) {
            return;
        }

        $SESSION->wantsurl = \theme_iiidem2_get_dashboard_url()->out(false);
    }

    /**
     * Add About us link to the top primary navigation menu.
     *
     * @param \core\hook\navigation\primary_extend $hook
     */
    /**
     * Build course layout data before header/layout (avoids add_body_class errors in layout).
     *
     * @param \core\hook\output\before_http_headers $hook
     */
    public static function before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $CFG, $PAGE, $COURSE;

        require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

        if (\theme_iiidem2_is_quiz_attempt_page($PAGE) && \theme_iiidem2_use_custom_quiz_ui($PAGE)) {
            if ($PAGE->state < \moodle_page::STATE_IN_BODY) {
                $PAGE->set_pagelayout('quizattempt');
            }
            \theme_iiidem2_apply_custom_quiz_page_assets($PAGE);
        } else if (\theme_iiidem2_is_custom_quiz_page($PAGE)) {
            \theme_iiidem2_apply_custom_quiz_page_assets($PAGE);
        } else if (\theme_iiidem2_is_live_class_page($PAGE)) {
            \theme_iiidem2_apply_live_class_page_assets($PAGE);
        }

        if ($PAGE->pagelayout !== 'course' || empty($COURSE->id) || (int) $COURSE->id === SITEID) {
            return;
        }

        if (!$PAGE->url->compare(new \moodle_url('/course/view.php'), URL_MATCH_BASE)) {
            return;
        }

        \theme_iiidem2_preload_course_layout_context($COURSE);
    }

    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        $view = $hook->get_primaryview();
        $view->add(
            get_string('aboutus', 'theme_iiidem2'),
            new \moodle_url('/about-us/'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'aboutus'
        );
        $view->add(
            get_string('contactus', 'theme_iiidem2'),
            new \moodle_url('/contact-us/'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'contactus'
        );

        if (!empty($view->children)) {
            foreach ($view->children as $child) {
                if ($child->key === 'register' && (int) $child->type === \navigation_node::TYPE_CUSTOM) {
                    $child->remove();
                    break;
                }
            }
        }
    }
}
