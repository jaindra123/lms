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

    /** @var bool Whether the course_summary_exporter autoloader is registered. */
    private static $courseexporterautoloadregistered = false;

    /**
     * Whether theme_iiidem2 is the active site theme.
     *
     * @return bool
     */
    private static function is_theme_active(): bool {
        global $CFG;
        return ($CFG->theme ?? '') === 'iiidem2';
    }

    /**
     * Register theme override for course list progress (My courses block webservice).
     *
     * @param \core\hook\after_config $hook
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $CFG;

        if (!self::is_theme_active()) {
            return;
        }

        // Belt-and-braces: admin must not use theme designer mode (see config.php too).
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (str_starts_with($script, '/admin/')) {
            $CFG->themedesignermode = false;
        }

        if (self::$courseexporterautoloadregistered) {
            return;
        }
        self::$courseexporterautoloadregistered = true;

        spl_autoload_register(static function(string $classname): void {
            if ($classname !== 'core_course\\external\\course_summary_exporter') {
                return;
            }
            if (class_exists($classname, false)) {
                return;
            }
            global $CFG;
            require_once($CFG->dirroot . '/theme/iiidem2/classes/external/course_summary_exporter.php');
        }, true, true);
    }

    /**
     * Send generic logins to the certificate course.
     *
     * A specific requested page is preserved, for example when login was
     * triggered while opening a protected activity.
     *
     * @param \core_user\hook\after_login_completed $hook
     */
    public static function after_login_completed(\core_user\hook\after_login_completed $hook): void {
        global $CFG, $SESSION;

        if (!self::is_theme_active()) {
            return;
        }

        if (isguestuser()) {
            return;
        }

        // Theme lib.php is not loaded yet during login; helpers live there.
        require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

        if (!empty($SESSION->wantsurl) && !\theme_iiidem2_is_generic_login_landing($SESSION->wantsurl)) {
            return;
        }

        $SESSION->wantsurl = (new \moodle_url('/course/view.php', ['id' => 4]))->out(false);
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

        if (!self::is_theme_active()) {
            return;
        }

        require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

        \theme_iiidem2_extend_admin_secondary_nav($PAGE);

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

        \theme_iiidem2_apply_course_view_page_assets($PAGE);
        \theme_iiidem2_preload_course_layout_context($COURSE);
    }

    /**
     * Inject admin navigation scripts into <head> as early as possible.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook
    ): void {
        global $PAGE, $CFG;

        if (!self::is_theme_active()) {
            return;
        }

        require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

        $pagepath = $PAGE->url->get_path(false);
        if (in_array($pagepath, ['/user/editadvanced.php', '/user/edit.php'], true)) {
            $scriptpath = $CFG->dirroot . '/theme/iiidem2/javascript/admin_registration_profile.js';
            $profilescript = is_readable($scriptpath) ? file_get_contents($scriptpath) : '';
            $hook->add_html(
                '<style>' .
                '.fitem:has([name="profile_field_iiidem_emb"]),' .
                '.fitem:has([name="profile_field_iiidem_electoral_practitioner"])' .
                '{display:none!important}' .
                '</style>' .
                ($profilescript !== '' ? '<script>' . $profilescript . '</script>' : '')
            );
        }

        if (\theme_iiidem2_is_admin_index_page($PAGE)) {
            $hook->add_html(\theme_iiidem2_admin_index_head_script());
            return;
        }

        if ($PAGE->pagelayout === 'admin'
                && preg_match('#/admin/search\.php$#', $PAGE->url->get_path(false))) {
            $hook->add_html(\theme_iiidem2_admin_search_head_script());
        }
    }

    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        if (!self::is_theme_active()) {
            return;
        }

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
