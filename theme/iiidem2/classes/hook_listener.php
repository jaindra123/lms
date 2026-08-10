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

        // Security headers for all web responses (including AJAX that skip $OUTPUT).
        security_headers::send();

        // Reject unknown “quick login” style tokens (not implemented here; CDAC PoC used ?qlogin=…&userid=).
        foreach (['qlogin', 'autologin', 'logintoken_userid'] as $badkey) {
            unset($_GET[$badkey], $_REQUEST[$badkey], $_POST[$badkey]);
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
            global $CFG;
            static $map = [
                'core_course\\external\\course_summary_exporter' =>
                    '/theme/iiidem2/classes/external/course_summary_exporter.php',
                // Block .php / script uploads in Private files (CWE-434).
                'core_user\\form\\private_files' =>
                    '/theme/iiidem2/classes/form/private_files.php',
            ];
            if (!isset($map[$classname])) {
                return;
            }
            if (class_exists($classname, false)) {
                return;
            }
            require_once($CFG->dirroot . $map[$classname]);
        }, true, true);
    }

    /**
     * Block executable/script uploads before they enter the file pool (CWE-434).
     *
     * Covers Private files and repository AJAX (`accepted_types=*`) which bypass
     * form-level accepted_types until save. Also throttles mass user uploads.
     *
     * @param \core_files\hook\before_file_created $hook
     */
    public static function before_file_created(\core_files\hook\before_file_created $hook): void {
        if (!self::is_theme_active()) {
            return;
        }

        $filerecord = $hook->get_filerecord();
        upload_security::assert_safe_file_create(
            $filerecord,
            $hook->has_filepath() ? $hook->get_filepath() : null,
            $hook->has_filecontent() ? $hook->get_filecontent() : null
        );

        // Instance 2: rate-limit Private files / draft repository uploads (DoS / mass shell dump).
        if (upload_security::is_user_upload_area($filerecord)) {
            $filename = (string) ($filerecord->filename ?? '');
            if ($filename !== '' && $filename !== '.' && $filename !== '..') {
                // 40 creates / 10 minutes, 120 / hour per user (or IP if guest).
                rate_limit::require_allowed('user_file_upload', 40, 600);
                rate_limit::require_allowed('user_file_upload_hour', 120, 3600);
            }
        }
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
        global $CFG, $SESSION, $USER;

        if (!self::is_theme_active()) {
            return;
        }

        // Session fixation: regenerate session ID immediately after authentication.
        // Core login_user() already regenerates once; this explicit rotation keeps
        // the sessions table + CSRF sesskey aligned and is auditable site policy.
        session_security::regenerate_id_now();

        if (isguestuser()) {
            return;
        }

        // Single concurrent session: drop every other browser session for this user.
        // Runs after regenerate so the kept sid is the post-login cookie.
        session_security::invalidate_other_sessions_on_login((int) $USER->id, session_id());

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
        global $CFG, $PAGE, $COURSE, $USER;

        if (!self::is_theme_active()) {
            return;
        }

        // Security headers for all web responses (including AJAX that skip $OUTPUT).
        security_headers::send();

        self::throttle_login_posts();

        require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

        self::restrict_student_attendance_pages();
        self::restrict_preferences_userid_tampering();

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
     * IP throttle for login POSTs (complements account lockout by username).
     * CDAC finding #8 cited /login/index.php without request throttling.
     */
    private static function throttle_login_posts(): void {
        if (CLI_SCRIPT || AJAX_SCRIPT || WS_SERVER) {
            return;
        }
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (!str_ends_with($script, '/login/index.php')) {
            return;
        }

        $ip = 'ip:' . rate_limit::client_ip();
        // 20 login POSTs / 5 min, 60 / hour per IP (account lockout still applies per user).
        rate_limit::require_allowed('login_post_ip', 20, 300, $ip);
        rate_limit::require_allowed('login_post_ip_hour', 60, 3600, $ip);
    }

    /**
     * Students may only open their own attendance; teachers keep full reports.
     */
    private static function restrict_student_attendance_pages(): void {
        global $USER;

        if (!isloggedin() || isguestuser() || CLI_SCRIPT || AJAX_SCRIPT || WS_SERVER) {
            return;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $attendancepages = [
            '/mod/attendance/report.php',
            '/mod/attendance/manage.php',
            '/mod/attendance/take.php',
            '/mod/attendance/view.php',
        ];
        $matched = false;
        foreach ($attendancepages as $page) {
            if (str_ends_with($script, $page)) {
                $matched = true;
                break;
            }
        }
        if (!$matched) {
            return;
        }

        $cmid = optional_param('id', 0, PARAM_INT);
        if ($cmid < 1) {
            return;
        }

        $cm = get_coursemodule_from_id('attendance', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $context = \context_module::instance($cm->id);
        $canteach = has_any_capability([
            'mod/attendance:takeattendances',
            'mod/attendance:manageattendances',
            'mod/attendance:changeattendances',
            'mod/attendance:viewreports',
        ], $context, $USER);

        if ($canteach) {
            return;
        }

        // Pure students: block class-wide pages and force own studentid on view.
        if (str_ends_with($script, '/mod/attendance/report.php')
                || str_ends_with($script, '/mod/attendance/manage.php')
                || str_ends_with($script, '/mod/attendance/take.php')) {
            redirect(new \moodle_url('/mod/attendance/view.php', [
                'id' => $cmid,
                'studentid' => (int) $USER->id,
            ]));
        }

        $requestedstudent = optional_param('studentid', 0, PARAM_INT);
        if ($requestedstudent && $requestedstudent !== (int) $USER->id) {
            redirect(new \moodle_url('/mod/attendance/view.php', [
                'id' => $cmid,
                'studentid' => (int) $USER->id,
            ]));
        }
    }

    /**
     * Block preference / messaging IDOR: non-privileged users may only edit their own settings.
     * Covers CDAC URL list: forum.php, calendar.php, contentbank.php, message/edit.php, preferences.php.
     * Core already requires moodle/user:editprofile (etc.); this forces students away from ?id=N / ?userid=N.
     */
    private static function restrict_preferences_userid_tampering(): void {
        global $USER;

        if (!isloggedin() || isguestuser() || CLI_SCRIPT || AJAX_SCRIPT || WS_SERVER) {
            return;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $targets = [
            '/user/preferences.php' => 'userid',
            '/user/forum.php' => 'id',
            '/user/calendar.php' => 'id',
            '/user/contentbank.php' => 'id',
            '/user/editor.php' => 'id',
            '/user/language.php' => 'id',
            '/message/edit.php' => 'id',
        ];

        $param = null;
        $ownurl = null;
        foreach ($targets as $suffix => $pname) {
            if (str_ends_with($script, $suffix)) {
                $param = $pname;
                $ownurl = $suffix;
                break;
            }
        }
        if ($param === null) {
            return;
        }

        $userid = optional_param($param, (int) $USER->id, PARAM_INT);
        if ($userid === (int) $USER->id) {
            return;
        }

        $sysctx = \context_system::instance();
        if (is_siteadmin() || has_capability('moodle/user:update', $sysctx)) {
            return;
        }

        $userctx = \context_user::instance($userid, IGNORE_MISSING);
        if ($userctx) {
            if (has_capability('moodle/user:editprofile', $userctx)) {
                return;
            }
            if (str_ends_with($script, '/message/edit.php')
                    && has_capability('moodle/user:editmessageprofile', $userctx)) {
                return;
            }
        }

        // Not privileged for this target — ignore tampered user key.
        redirect(new \moodle_url($ownurl, [$param => (int) $USER->id]));
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
