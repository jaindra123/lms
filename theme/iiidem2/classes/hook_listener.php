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
     * Sanitize public search query params (XSS / injection probes).
     */
    private static function sanitize_course_search_params(): void {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $hit = false;
        foreach (['/course/search.php', '/course/index.php', '/message/', '/user/index.php'] as $needle) {
            if (str_contains($script, $needle)) {
                $hit = true;
                break;
            }
        }
        if (!$hit) {
            return;
        }
        foreach (['search', 'q', 'query', 'keywords'] as $key) {
            if (!isset($_GET[$key])) {
                continue;
            }
            if (is_array($_GET[$key])) {
                $cleaned = [];
                foreach ($_GET[$key] as $item) {
                    if (!is_string($item)) {
                        continue;
                    }
                    $clean = self::sanitize_filter_keyword($item);
                    if ($clean !== '') {
                        $cleaned[] = $clean;
                    }
                }
                $_GET[$key] = $cleaned;
                $_REQUEST[$key] = $cleaned;
                continue;
            }
            if (!is_string($_GET[$key])) {
                continue;
            }
            $clean = self::sanitize_filter_keyword($_GET[$key]);
            $_GET[$key] = $clean;
            $_REQUEST[$key] = $clean;
        }
    }

    /**
     * Plain keyword/filter token — no HTML (participants unified filter / search).
     */
    private static function sanitize_filter_keyword(string $value): string {
        $clean = trim(strip_tags(clean_param($value, PARAM_TEXT)));
        if (\core_text::strlen($clean) > 200) {
            $clean = \core_text::substr($clean, 0, 200);
        }
        $clean = str_replace(['<', '>'], '', $clean);
        if (input_validation::contains_dangerous_markup($clean)) {
            return '';
        }
        return $clean;
    }

    /**
     * CDAC #39: purify course / question HTML fields on save (stored XSS).
     *
     * Scrubs fullname/shortname (plain) and editor/customfield HTML (purified)
     * before Moodle persists them — blocks &lt;script&gt; in Instructor Data, summary, etc.
     */
    private static function sanitize_richtext_content_post(): void {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $courseedit = (bool) preg_match('#/course/(edit|editadvanced)\.php$#', $script);
        $questionedit = str_contains($script, '/question/bank/editquestion/')
            || str_contains($script, '/question/question.php');
        if (!$courseedit && !$questionedit) {
            return;
        }

        if ($courseedit) {
            foreach (['fullname', 'shortname'] as $key) {
                if (!empty($_POST[$key]) && is_string($_POST[$key])) {
                    $clean = input_validation::purify_plain_title($_POST[$key]);
                    $_POST[$key] = $clean;
                    $_REQUEST[$key] = $clean;
                }
            }
        }

        if ($questionedit) {
            // ID number must never store HTML/script (CDAC #39 Instance 5).
            if (isset($_POST['idnumber']) && is_string($_POST['idnumber'])) {
                $idnumber = $_POST['idnumber'];
                if (input_validation::contains_dangerous_markup($idnumber) || str_contains($idnumber, '<')) {
                    $idnumber = '';
                } else {
                    $idnumber = clean_param(trim($idnumber), PARAM_NOTAGS);
                }
                $_POST['idnumber'] = $idnumber;
                $_REQUEST['idnumber'] = $idnumber;
            }
        }

        self::purify_editor_array_post('summary_editor');
        self::purify_editor_array_post('questiontext');
        self::purify_editor_array_post('questiontext_editor');
        self::purify_editor_array_post('generalfeedback');
        self::purify_editor_array_post('generalfeedback_editor');

        foreach (array_keys($_POST) as $key) {
            if (!is_string($key)) {
                continue;
            }
            if (preg_match('/^customfield_.+_editor$/', $key) && is_array($_POST[$key])) {
                self::purify_editor_array_post($key);
                continue;
            }
            if (str_starts_with($key, 'customfield_') && is_string($_POST[$key])) {
                $val = $_POST[$key];
                if (str_contains($val, '<')) {
                    $clean = input_validation::purify_html_fragment($val);
                } else if (input_validation::contains_dangerous_markup($val)) {
                    $clean = input_validation::purify_plain_title($val);
                } else {
                    continue;
                }
                $_POST[$key] = $clean;
                $_REQUEST[$key] = $clean;
            }
            // Question answer/feedback editors: answer[0][text], feedback[0]_editor, etc.
            if ($questionedit && is_array($_POST[$key])) {
                self::purify_nested_editor_post($key);
            }
        }
    }

    /**
     * Purify Moodle editor array shape: ['text' => html, 'format' => int, ...].
     */
    private static function purify_editor_array_post(string $key): void {
        if (empty($_POST[$key]) || !is_array($_POST[$key])) {
            return;
        }
        if (!isset($_POST[$key]['text']) || !is_string($_POST[$key]['text'])) {
            return;
        }
        $_POST[$key]['text'] = input_validation::purify_html_fragment($_POST[$key]['text']);
        if (isset($_REQUEST[$key]) && is_array($_REQUEST[$key])) {
            $_REQUEST[$key]['text'] = $_POST[$key]['text'];
        }
    }

    /**
     * Recursively purify ['text'=>…] leaves under a POST key (question answers).
     */
    private static function purify_nested_editor_post(string $key): void {
        if (empty($_POST[$key]) || !is_array($_POST[$key])) {
            return;
        }
        array_walk_recursive($_POST[$key], static function (&$value, $leafkey): void {
            if ($leafkey === 'text' && is_string($value) && str_contains($value, '<')) {
                $value = input_validation::purify_html_fragment($value);
            }
        });
        $_REQUEST[$key] = $_POST[$key];
    }

    /**
     * Scripts that legitimately consume PATH_INFO / slasharguments.
     *
     * @return string[]
     */
    private static function pathinfo_allowed_script_suffixes(): array {
        return [
            '/pluginfile.php',
            '/tokenpluginfile.php',
            '/draftfile.php',
            '/webservice/pluginfile.php',
            '/webservice/draftfile.php',
            '/theme/yui_combo.php',
            '/theme/jquery.php',
            '/theme/javascript.php',
            '/theme/styles.php',
            '/theme/styles_debug.php',
            '/theme/image.php',
            '/theme/font.php',
            '/lib/javascript.php',
            '/lib/requirejs.php',
            '/lib/jslib.php',
            '/lib/ajax/service-nologin.php',
        ];
    }

    /**
     * Stop PATH_INFO reflection into moodleform action="$FULLME" (CDAC form-action XSS).
     *
     * Example: /login/forgot_password.php/saw5xzmqrrg → clean .php URL.
     * Also covers /user/files.php and other non-slashargument scripts.
     */
    private static function neutralize_spurious_php_pathinfo(): void {
        global $FULLME, $ME, $SCRIPT, $FULLSCRIPT;

        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($script === '' || !str_ends_with($script, '.php')) {
            return;
        }
        foreach (self::pathinfo_allowed_script_suffixes() as $allowed) {
            if (str_ends_with($script, $allowed)) {
                return;
            }
        }

        $pathinfo = (string) ($_SERVER['PATH_INFO'] ?? '');
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $haspathinfo = ($pathinfo !== '' && $pathinfo !== '/') || str_contains($uri, '.php/');
        if (!$haspathinfo) {
            // Also scrub globals if FULLME already baked in path junk from setup.
            $haspathinfo = is_string($FULLME) && (bool) preg_match('#\.php/#', $FULLME);
        }
        if (!$haspathinfo) {
            return;
        }

        // moodleform defaults action to strip_querystring($FULLME) — clean globals first.
        if (is_string($FULLME) && preg_match('#^(https?://[^?#]+?\.php)(/[^?#]*)(\?.*)?$#', $FULLME, $m)) {
            $FULLME = $m[1] . ($m[3] ?? '');
        }
        if (is_string($ME) && preg_match('#^([^?#]+?\.php)(/[^?#]*)(\?.*)?$#', $ME, $m)) {
            $ME = $m[1] . ($m[3] ?? '');
        }
        if (is_string($SCRIPT) && preg_match('#^([^?#]+?\.php)(/.*)$#', $SCRIPT, $m)) {
            $SCRIPT = $m[1];
        }
        if (is_string($FULLSCRIPT) && preg_match('#^([^?#]+?\.php)(/.*)$#', $FULLSCRIPT, $m)) {
            $FULLSCRIPT = $m[1];
        }

        // 302 so HTML never renders form action with the probe segment.
        if ($uri !== '' && str_contains($uri, '.php/')
                && preg_match('#^([^?]*\.php)(/[^?]*)(\?.*)?$#', $uri, $m) && $m[2] !== '') {
            if (!headers_sent()) {
                header('Location: ' . $m[1] . ($m[3] ?? ''), true, 302);
                header('Cache-Control: no-store');
            }
            exit(0);
        }
    }

    /**
     * Accept CSRF sesskey from X-Moodle-Sesskey when the request has no sesskey.
     * Never overwrite an existing POST/GET value (filemanager / draftfiles_ajax).
     */
    private static function import_sesskey_from_header(): void {
        if (!empty($_POST['sesskey']) || !empty($_GET['sesskey'])) {
            return;
        }
        $header = '';
        foreach (['HTTP_X_MOODLE_SESSKEY', 'REDIRECT_HTTP_X_MOODLE_SESSKEY', 'HTTP_X_MOODLESESSKEY'] as $key) {
            if (!empty($_SERVER[$key]) && is_string($_SERVER[$key])) {
                $header = trim($_SERVER[$key]);
                break;
            }
        }
        if ($header === '' || \core_text::strlen($header) > 64) {
            return;
        }
        $_POST['sesskey'] = $header;
        $_GET['sesskey'] = $header;
        $_REQUEST['sesskey'] = $header;
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
        // Authenticated AJAX: force no-store at flush (CDAC Cache-Control PoC on service.php).
        security_headers::ensure_ajax_cache_control_buffer();
        // CDAC #24: target=_blank without rel=noopener in raw HTML (admin environment docs).
        security_headers::ensure_noopener_blank_targets_buffer();

        // CDAC: form-action PATH_INFO reflection (forgot_password / user/files / etc.).
        self::neutralize_spurious_php_pathinfo();

        // Never allow debug footers / SQL / stack in the browser unless local FORCE_DEBUG (CWE-209).
        // Moodle fatal_error() shows Debug info + Stack when $CFG->debugdeveloper is true
        // (set automatically when $CFG->debug === DEBUG_DEVELOPER / E_ALL|E_STRICT).
        $forcedebug = (string) (getenv('MOODLE_FORCE_DEBUG') ?: '');
        $forcedebug = in_array(strtolower($forcedebug), ['1', 'true', 'yes', 'on'], true);
        $allowbrowserdebug = defined('MOODLE_ENV') && MOODLE_ENV === 'dev' && $forcedebug;
        if (!$allowbrowserdebug) {
            $CFG->debugdisplay = 0;
            $CFG->debugdeveloper = false;
            $CFG->themedesignermode = false;
            $CFG->perfdebug = 0;
            $CFG->debugpageinfo = false;
            $CFG->debugstringids = false;
            @ini_set('display_errors', '0');
            if (defined('MOODLE_ENV') && MOODLE_ENV === 'dev') {
                // Keep logging, but not developer-level (avoids SQL/stack on exception pages).
                $CFG->debug = defined('DEBUG_ALL') ? DEBUG_ALL : (E_ALL & ~E_STRICT);
            } else if (!$forcedebug) {
                $CFG->debug = 0;
            }
        }

        // CDAC #40: keep idle session timeout short on staging/production (CWE-613).
        if (defined('MOODLE_ENV') && MOODLE_ENV !== 'dev') {
            $CFG->sessiontimeout = 30 * 60;
            $CFG->sessiontimeoutwarning = 5 * 60;
        }

        // Reject unknown “quick login” style tokens (not implemented here; CDAC PoC used ?qlogin=…&userid=).
        foreach (['qlogin', 'autologin', 'logintoken_userid', 'qrlogin'] as $badkey) {
            unset($_GET[$badkey], $_REQUEST[$badkey], $_POST[$badkey]);
        }

        // Unused QR login must stay off (CDAC #23 — profile “QR code for mobile app access” PoC).
        if (!isset($CFG->forced_plugin_settings) || !is_array($CFG->forced_plugin_settings)) {
            $CFG->forced_plugin_settings = [];
        }
        if (!isset($CFG->forced_plugin_settings['tool_mobile']) || !is_array($CFG->forced_plugin_settings['tool_mobile'])) {
            $CFG->forced_plugin_settings['tool_mobile'] = [];
        }
        $CFG->forced_plugin_settings['tool_mobile']['qrcodetype'] = 0;
        $CFG->forced_plugin_settings['tool_mobile']['setuplink'] = '';
        $CFG->forced_plugin_settings['tool_mobile']['enablesmartappbanners'] = 0;
        // Persist if DB still has a non-disabled value (belt-and-braces with config.php force).
        $qrcurrent = get_config('tool_mobile', 'qrcodetype');
        if ($qrcurrent !== false && (string) $qrcurrent !== '0') {
            set_config('qrcodetype', 0, 'tool_mobile');
        }
        if ((string) get_config('tool_mobile', 'setuplink') !== '') {
            set_config('setuplink', '', 'tool_mobile');
        }
        if ((string) get_config('tool_mobile', 'enablesmartappbanners') !== '0') {
            set_config('enablesmartappbanners', 0, 'tool_mobile');
        }

        // CSRF sesskey from AJAX header (theme JS strips sesskey from service.php query string).
        self::import_sesskey_from_header();

        // Course search XSS probes: strip tags / PARAM_TEXT before optional_param (CDAC search=<script>).
        self::sanitize_course_search_params();

        // CDAC #39: purify course/question rich text on save (Instructor Data, summary, etc.).
        self::sanitize_richtext_content_post();

        // Reaffirm cookie flags early (covers AJAX that never hit before_http_headers).
        session_security::enforce_httponly_on_set_cookie_headers();

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

        // Ensure MoodleSession / MoodleID Set-Cookie headers include HttpOnly.
        session_security::enforce_httponly_on_set_cookie_headers();

        // Re-assert no-store on authenticated pages (Moodle may have sent weaker Cache-Control).
        security_headers::send_sensitive_cache_control();

        self::throttle_login_posts();
        self::require_login_credentials_lock_js();

        require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

        // Run once per request. If a guard throws, Moodle re-enters header() while
        // rendering the error page — running again would print the same message twice.
        self::run_request_access_guards();

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
     * Load JS that disables paste/drop/autocomplete on username & password fields.
     */
    private static function require_login_credentials_lock_js(): void {
        global $PAGE;

        if (CLI_SCRIPT || AJAX_SCRIPT || WS_SERVER) {
            return;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $lockpages = [
            '/login/index.php',
            '/login/change_password.php',
            '/login/set_password.php',
            '/login/forgot_password.php',
        ];
        $match = false;
        foreach ($lockpages as $page) {
            if (str_ends_with($script, $page)) {
                $match = true;
                break;
            }
        }
        if (!$match && ($PAGE->pagelayout ?? '') === 'login') {
            $match = true;
        }
        if (!$match) {
            return;
        }

        $PAGE->requires->js(new \moodle_url('/theme/iiidem2/javascript/login_credentials_lock.js'));
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
     * CDAC access guards for participants / competency / loglive / prefs / attendance.
     * Skips when Moodle is already rendering an exception page (nested header),
     * otherwise the same deny text is printed twice via early_error_content.
     */
    private static function run_request_access_guards(): void {
        static $done = false;
        if ($done) {
            return;
        }

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25) as $frame) {
            $fn = $frame['function'] ?? '';
            if ($fn === 'default_exception_handler' || $fn === 'fatal_error') {
                return;
            }
        }

        $done = true;
        self::restrict_student_attendance_pages();
        self::restrict_preferences_userid_tampering();
        self::restrict_participants_list_access();
        self::restrict_competency_report_access();
        self::restrict_loglive_site_access();
    }

    /**
     * Harden /user/index.php (enrolled users / participants).
     *
     * Audit PoC: change id=4 → id=1 (site front page) and read another roster + emails.
     * - Site course (id=SITEID): site administrators only.
     * - Other courses: teachers/managers only — students must not browse peer PII lists.
     */
    private static function restrict_participants_list_access(): void {
        global $SITE;

        if (!isloggedin() || isguestuser() || is_siteadmin() || CLI_SCRIPT || AJAX_SCRIPT || WS_SERVER) {
            return;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (!str_ends_with($script, '/user/index.php')) {
            return;
        }

        $courseid = optional_param('id', 0, PARAM_INT);
        $contextid = optional_param('contextid', 0, PARAM_INT);
        if ($contextid > 0) {
            $ctx = \context::instance_by_id($contextid, IGNORE_MISSING);
            if ($ctx && (int) $ctx->contextlevel === CONTEXT_COURSE) {
                $courseid = (int) $ctx->instanceid;
            }
        }
        if ($courseid < 1) {
            return;
        }

        // Front page / site course participants (audit: id=<course> → id=1).
        // Core allows anyone with moodle/course:viewparticipants at system context
        // (often Managers / course creators). Match loglive: site roster = site admins only.
        // is_siteadmin() already returned above — any caller reaching here is denied.
        if ((int) $courseid === (int) SITEID || (int) $courseid === (int) $SITE->id) {
            throw new \moodle_exception(
                'nopermissions',
                'error',
                new \moodle_url('/'),
                get_string('participants')
            );
        }

        $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$coursecontext) {
            return;
        }

        // Staff who can manage the course or see identity fields may open the roster.
        if (has_any_capability([
            'moodle/course:update',
            'moodle/course:viewhiddenuserfields',
            'moodle/site:viewuseridentity',
            'moodle/course:enrolreview',
            'moodle/role:assign',
            'enrol/manual:enrol',
        ], $coursecontext)) {
            return;
        }

        // Pure students (viewparticipants alone): block peer roster / email dump.
        throw new \moodle_exception(
            'nopermissions',
            'error',
            new \moodle_url('/course/view.php', ['id' => $courseid]),
            get_string('participants')
        );
    }

    /**
     * Harden /report/competency/index.php.
     *
     * Audit PoC: change id=1 → id=4 and open peer competency; ?user=37 views another learner.
     * Non-staff may only view their own competency breakdown (never another user's).
     */
    private static function restrict_competency_report_access(): void {
        global $USER;

        if (!isloggedin() || isguestuser() || is_siteadmin() || CLI_SCRIPT || AJAX_SCRIPT || WS_SERVER) {
            return;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (!str_ends_with($script, '/report/competency/index.php')) {
            return;
        }

        $courseid = optional_param('id', 0, PARAM_INT);
        if ($courseid < 1) {
            return;
        }

        // Site home course: no competency roster to browse via ?id=1 (audit Instance 2).
        if ((int) $courseid === (int) SITEID) {
            throw new \moodle_exception(
                'nopermissions',
                'error',
                new \moodle_url('/'),
                get_string('pluginname', 'report_competency')
            );
        }

        $coursecontext = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$coursecontext) {
            return;
        }

        $canviewothers = has_any_capability([
            'moodle/competency:competencygrade',
            'moodle/competency:usercompetencyreview',
            'moodle/competency:coursecompetencymanage',
            'moodle/course:update',
            'moodle/course:viewhiddenuserfields',
        ], $coursecontext);

        if ($canviewothers) {
            return;
        }

        // Students / non-graders: only own report (core otherwise defaults to another participant).
        $requesteduser = optional_param('user', 0, PARAM_INT);
        $mod = optional_param('mod', 0, PARAM_INT);
        if ($requesteduser !== (int) $USER->id) {
            redirect(new \moodle_url('/report/competency/index.php', [
                'id' => $courseid,
                'user' => (int) $USER->id,
                'mod' => $mod,
            ]));
        }
    }

    /**
     * Harden live logs: site course id=SITEID must not be open to course teachers via ?id= switch.
     */
    private static function restrict_loglive_site_access(): void {
        if (!isloggedin() || isguestuser() || is_siteadmin() || CLI_SCRIPT || WS_SERVER) {
            return;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (!str_ends_with($script, '/report/loglive/index.php')
                && !str_ends_with($script, '/report/loglive/loglive_ajax.php')) {
            return;
        }

        $courseid = optional_param('id', 0, PARAM_INT);
        if ($courseid < 1 || (int) $courseid !== (int) SITEID) {
            return;
        }

        if (!is_siteadmin()) {
            throw new \moodle_exception(
                'nopermissions',
                'error',
                new \moodle_url('/'),
                get_string('livelogs', 'report_loglive')
            );
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

        // Belt-and-braces Referrer-Policy for documents (also sent as HTTP header).
        $hook->add_html('<meta name="referrer" content="strict-origin-when-cross-origin">');
        // Early AJAX sesskey helper for /lib/ajax/service.php only (cache-busted).
        $hook->add_html(
            '<script src="' .
            (new \moodle_url('/theme/iiidem2/javascript/ajax_sesskey_header.js', ['v' => '2024100998']))->out(false) .
            '"></script>'
        );
        $hook->add_html(
            '<script src="' .
            (new \moodle_url('/theme/iiidem2/javascript/message_xss_guard.js'))->out(false) .
            '"></script>'
        );
        $hook->add_html(
            '<script src="' .
            (new \moodle_url('/theme/iiidem2/javascript/logout_post.js'))->out(false) .
            '"></script>'
        );
        // Reduce bfcache of authenticated HTML in older agents.
        if (isloggedin() && !isguestuser()) {
            $hook->add_html(
                '<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, private">' .
                '<meta http-equiv="Pragma" content="no-cache">'
            );
            // CDAC #29: Back after logout must not show stale authenticated UI.
            $hook->add_html(
                '<script src="' .
                (new \moodle_url('/theme/iiidem2/javascript/auth_nocache_back.js'))->out(false) .
                '"></script>'
            );
        }

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
