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

    /**
     * Regenerate the PHP session ID immediately and rotate the CSRF sesskey.
     *
     * Safe to call after complete_user_login() / after_login_completed.
     * No-ops for CLI, webservice servers, or when headers were already sent.
     *
     * @return bool True when a new session id was issued
     */
    public static function regenerate_id_now(): bool {
        global $USER;

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
            debugging(
                'theme_iiidem2 session regenerate skipped; headers sent in ' . $file . ':' . $line,
                DEBUG_DEVELOPER
            );
            return false;
        }

        // Issue a new session id and delete the previous server-side session record.
        session_regenerate_id(true);
        \core\session\manager::destroy($oldsid);

        $userid = (!empty($USER->id) && !isguestuser($USER)) ? (int) $USER->id : 0;
        \core\session\manager::add_session($userid);

        // Rotate Moodle CSRF token bound to the authenticated session.
        if (isset($USER)) {
            unset($USER->sesskey);
            sesskey();
        }

        return true;
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
     * Ensure sensitive Moodle cookies in outgoing Set-Cookie headers include HttpOnly.
     *
     * Uses core cookie_helper to patch headers already queued for MoodleSession* / MoodleID*.
     */
    public static function enforce_httponly_on_set_cookie_headers(): void {
        global $CFG;

        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }
        if (headers_sent()) {
            return;
        }

        $suffix = $CFG->sessioncookie ?? '';
        $names = [
            'MoodleSession' . $suffix,
            'MoodleID' . $suffix,
        ];

        foreach ($names as $name) {
            if ($name === '') {
                continue;
            }
            \core\session\utility\cookie_helper::add_attributes_to_cookie_response_header($name, ['HttpOnly']);
        }
    }
}
