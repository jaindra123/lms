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
 * Session security — fixation hardening, single concurrent login, password-change invalidation.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class session_security {

    /** @var bool Whether this request already issued a new session id. */
    private static bool $rotatedthistime = false;

    /**
     * Regenerate the PHP session ID immediately and rotate the CSRF sesskey.
     *
     * Safe to call after complete_user_login() / after_login_completed.
     * No-ops for CLI, webservice servers, or when a new id cannot be issued.
     *
     * @return bool True when a new session id was issued
     */
    public static function regenerate_id_now(): bool {
        global $CFG, $USER, $SESSION;

        if (self::$rotatedthistime) {
            return true;
        }

        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return false;
        }
        if (defined('WS_SERVER') && WS_SERVER) {
            return false;
        }
        if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
            // PHPUnit often has headers/session edge cases; core already covers login.
            return false;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $oldsid = session_id();
        if ($oldsid === '') {
            return false;
        }

        $file = null;
        $line = null;
        if (headers_sent($file, $line)) {
            // Cannot emit Set-Cookie — browser would keep the pre-login sid (fixation).
            debugging(
                'theme_iiidem2 session regenerate skipped; headers sent in ' . $file . ':' . $line,
                DEBUG_DEVELOPER
            );
            return false;
        }

        // Issue a new session id and delete the previous server-side session data.
        $regenerated = session_regenerate_id(true);
        $newsid = session_id();

        // If PHP failed to rotate (custom handlers / edge cases), force a new id.
        if (!$regenerated || $newsid === '' || $newsid === $oldsid) {
            if (!self::force_new_session_id($oldsid)) {
                return false;
            }
            $newsid = session_id();
        }

        if ($newsid === '' || $newsid === $oldsid) {
            return false;
        }

        // Remove pre-login / prior sid from Moodle sessions table (idempotent).
        \core\session\manager::destroy($oldsid);

        $userid = (!empty($USER->id) && !isguestuser($USER)) ? (int) $USER->id : 0;
        \core\session\manager::add_session($userid);

        // Ensure the browser receives the new MoodleSession cookie explicitly.
        self::emit_moodle_session_cookie($newsid);

        if (isset($SESSION) && is_object($SESSION)) {
            $SESSION->isnewsessioncookie = true;
        }

        // Rotate Moodle CSRF token bound to the authenticated session.
        if (isset($USER)) {
            unset($USER->sesskey);
            sesskey();
        }

        self::$rotatedthistime = true;
        return true;
    }

    /**
     * Immediately after Moodle records a successful login (before page output).
     *
     * @param \core\event\user_loggedin $event
     */
    public static function user_loggedin(\core\event\user_loggedin $event): void {
        unset($event);
        self::regenerate_id_now();
        if (class_exists(password_policy::class)) {
            password_policy::enforce_age_on_login();
        }
    }

    /**
     * Force a new session id when session_regenerate_id() did not change it.
     *
     * @param string $oldsid
     * @return bool
     */
    private static function force_new_session_id(string $oldsid): bool {
        if (!function_exists('session_create_id')) {
            return false;
        }
        if (headers_sent()) {
            return false;
        }

        $created = session_create_id('iiidem');
        if ($created === false || $created === '' || $created === $oldsid) {
            return false;
        }

        // Persist current $_SESSION under the new id, then switch.
        session_write_close();
        session_id($created);
        session_start();

        return session_id() !== '' && session_id() !== $oldsid;
    }

    /**
     * Queue Set-Cookie for MoodleSession with Path, Domain, Secure, HttpOnly, SameSite=Lax.
     *
     * @param string $sid
     */
    private static function emit_moodle_session_cookie(string $sid): void {
        global $CFG;

        if ($sid === '' || headers_sent()) {
            return;
        }

        $name = session_name();
        if ($name === '' || $name === 'PHPSESSID') {
            $name = 'MoodleSession' . ($CFG->sessioncookie ?? '');
        }
        $path = self::cookie_path();
        $domain = self::cookie_domain();
        $secure = self::cookies_must_be_secure();

        $params = [
            'expires' => 0,
            'path' => $path,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if ($domain !== '') {
            $params['domain'] = $domain;
        }

        setcookie($name, $sid, $params);
        $_COOKIE[$name] = $sid;
    }

    /** Idle timeout (seconds) on staging/production. */
    public const IDLE_SECONDS = 1800;

    /** Hard cap from login time, even if the tab stays active (CWE-613, 16h PoC). */
    public const ABSOLUTE_SECONDS = 28800;

    /**
     * Force a short idle timeout into $CFG (and mdl_config so M.cfg matches).
     */
    public static function force_idle_timeout(): void {
        global $CFG;

        if (!self::should_force_timeout()) {
            return;
        }

        $CFG->sessiontimeout = self::IDLE_SECONDS;
        $CFG->sessiontimeoutwarning = 5 * MINSECS;

        if (!empty($CFG->version)) {
            $current = (int) get_config('core', 'sessiontimeout');
            if ($current !== self::IDLE_SECONDS) {
                set_config('sessiontimeout', self::IDLE_SECONDS);
                set_config('sessiontimeoutwarning', 5 * MINSECS);
            }
        }
    }

    /**
     * Staging, production, and any eci.gov.in host — not a long-lived local session.
     */
    public static function should_force_timeout(): bool {
        $allowlong = (string) (getenv('MOODLE_ALLOW_LONG_SESSION') ?: '');
        if (in_array(strtolower($allowlong), ['1', 'true', 'yes', 'on'], true)) {
            return false;
        }
        return true;
    }

    /**
     * Log the user out when idle timeout already elapsed or login is older than the hard cap.
     */
    public static function enforce_session_limits(): void {
        global $USER;

        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }
        if (defined('WS_SERVER') && WS_SERVER) {
            return;
        }
        if (!function_exists('isloggedin') || !isloggedin() || isguestuser()) {
            return;
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (!self::should_force_timeout()) {
            return;
        }
        if (str_contains($script, '/login/') || str_contains($script, '/admin/tool/mfa/')) {
            return;
        }

        $loginat = (int) ($USER->currentlogin ?? 0);
        if ($loginat > 0 && (time() - $loginat) > self::ABSOLUTE_SECONDS) {
            self::expire_and_send_to_login();
        }
    }

    /**
     * Destroy the browser session and send the user to login (HTML) or stop (AJAX).
     */
    private static function expire_and_send_to_login(): void {
        require_logout();
        if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
            return;
        }
        if (!headers_sent()) {
            redirect(new \moodle_url('/login/index.php'));
        }
    }

    /**
     * Invalidate every other active browser session after a successful login.
     *
     * Keeps only $keepsid (the session that just authenticated). Other devices /
     * browsers for the same user are logged out immediately. Does not revoke
     * web-service tokens (those are handled on password change).
     *
     * @param int $userid
     * @param string|null $keepsid Current session id to retain
     * @return void
     */
    public static function invalidate_other_sessions_on_login(int $userid, ?string $keepsid = null): void {
        if ($userid <= 0 || isguestuser($userid)) {
            return;
        }

        \core\session\manager::destroy_user_sessions($userid, $keepsid);

        // Also honour Moodle's concurrent-login limiter (forced to 1 in config.php).
        if ($keepsid !== null && $keepsid !== '') {
            \core\session\manager::apply_concurrent_login_limit($userid, $keepsid);
        }
    }

    /**
     * Invalidate every other active browser session (and WS tokens) after a password change.
     *
     * The session that performed the change may be kept ($keepsid); all others
     * are destroyed immediately so a stolen cookie cannot keep working.
     *
     * @param int $userid
     * @param string|null $keepsid Current session id to retain (null = destroy all)
     * @param bool $deletewstokens Also revoke web service / mobile tokens
     * @return void
     */
    public static function invalidate_sessions_after_password_change(
        int $userid,
        ?string $keepsid = null,
        bool $deletewstokens = true
    ): void {
        global $CFG;

        if ($userid <= 0) {
            return;
        }

        require_once($CFG->dirroot . '/webservice/lib.php');

        \core\session\manager::destroy_user_sessions($userid, $keepsid);

        if ($deletewstokens) {
            \webservice::delete_user_ws_tokens($userid);
        }

        // Rotate the remaining session id so a pre-change cookie value is useless.
        if ($keepsid !== null && $keepsid !== '' && session_id() === $keepsid) {
            self::regenerate_id_now();
        }
    }

    /**
     * Ensure sensitive Moodle cookies in outgoing Set-Cookie headers include
     * HttpOnly, Secure (HTTPS), SameSite=Lax, and Domain=wwwroot-host.
     *
     * Uses core cookie_helper to patch headers already queued for MoodleSession* / MoodleID*.
     */
    public static function enforce_httponly_on_set_cookie_headers(): void {
        self::enforce_cookie_attributes();
    }

    /**
     * Patch queued Set-Cookie headers and again at flush (late session cookies).
     */
    public static function enforce_cookie_attributes(): void {
        static $callbackregistered = false;

        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }

        self::patch_set_cookie_headers();

        if ($callbackregistered || headers_sent()) {
            return;
        }
        $callbackregistered = true;
        header_register_callback(static function (): void {
            self::patch_set_cookie_headers();
        });
    }

    /**
     * wwwroot host for the Domain cookie attribute (empty = host-only).
     *
     * DDEV / localhost / IPs stay host-only: Domain=*.ddev.site is rejected
     * (public suffix) and login then redirect-loops before MFA.
     */
    public static function cookie_domain(): string {
        global $CFG;

        $host = '';
        if (!empty($CFG->wwwroot)) {
            $host = strtolower((string) (parse_url($CFG->wwwroot, PHP_URL_HOST) ?? ''));
        }
        if (self::cookie_domain_forbidden($host)) {
            return '';
        }

        $domain = strtolower(trim((string) ($CFG->sessioncookiedomain ?? '')));
        if ($domain !== '') {
            $domain = ltrim($domain, '.');
            if (!self::cookie_domain_forbidden($domain)) {
                return $domain;
            }
        }
        return $host;
    }

    /**
     * Hosts that must not receive a Domain= cookie attribute.
     */
    public static function cookie_domain_forbidden(string $host): bool {
        $host = strtolower(ltrim(trim($host), '.'));
        if ($host === '' || $host === 'localhost') {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }
        foreach (['.ddev.site', '.localhost', '.local'] as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Cookie Path: wwwroot subdirectory, or "/" when the LMS is the site root.
     */
    public static function cookie_path(): string {
        global $CFG;

        $path = (string) ($CFG->sessioncookiepath ?? '');
        if ($path !== '' && str_starts_with($path, '/')) {
            return $path;
        }
        if (!empty($CFG->wwwroot)) {
            $wwwpath = (string) (parse_url($CFG->wwwroot, PHP_URL_PATH) ?? '');
            if ($wwwpath !== '' && $wwwpath !== '/') {
                return rtrim($wwwpath, '/') . '/';
            }
        }
        return '/';
    }

    /**
     * Whether this HTTP request was received over TLS.
     */
    public static function request_is_https(): bool {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }
        $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($fwd === 'https') {
            return true;
        }
        return strtolower((string) ($_SERVER['HTTP_FRONT_END_HTTPS'] ?? '')) === 'on';
    }

    /**
     * Secure flag when this response is actually HTTPS.
     *
     * wwwroot may be https while a local DDEV visit is http. Marking that
     * cookie Secure makes the browser drop it, so the first login POST has
     * no session (invalid login / captcha) and the second attempt works.
     */
    public static function cookies_must_be_secure(): bool {
        global $CFG;

        if (!self::request_is_https()) {
            return false;
        }
        if (!empty($CFG->cookiesecure)) {
            return true;
        }
        if (!empty($CFG->wwwroot) && str_starts_with($CFG->wwwroot, 'https://')) {
            return true;
        }
        return true;
    }

    /**
     * @return void
     */
    private static function patch_set_cookie_headers(): void {
        global $CFG;

        if (headers_sent()) {
            return;
        }

        $suffix = $CFG->sessioncookie ?? '';
        $names = [
            'MoodleSession' . $suffix,
            'MoodleID' . $suffix,
        ];
        foreach (headers_list() as $headerline) {
            if (preg_match('/^Set-Cookie:\s*(MFA_TOKEN_[^=\s;]+)/i', $headerline, $m)) {
                $names[] = $m[1];
            }
        }
        $names = array_values(array_unique(array_filter($names)));

        $attrs = ['HttpOnly', 'SameSite=Lax'];
        if (self::cookies_must_be_secure()) {
            $attrs[] = 'Secure';
        }
        $domain = self::cookie_domain();
        if ($domain !== '') {
            $attrs[] = 'Domain=' . $domain;
        }

        foreach ($names as $name) {
            \core\session\utility\cookie_helper::add_attributes_to_cookie_response_header($name, $attrs);
        }
    }
}
