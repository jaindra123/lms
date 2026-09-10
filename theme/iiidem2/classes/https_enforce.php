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
 * HTTPS / TLS enforcement for sensitive auth flows (CDAC #4).
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class https_enforce {

    /**
     * Whether the current request arrived over HTTPS (incl. sslproxy).
     */
    public static function request_is_https(): bool {
        global $CFG;

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (!empty($CFG->sslproxy) && !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
                && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        if (!empty($CFG->wwwroot) && str_starts_with($CFG->wwwroot, 'https://')) {
            // Staging/production always use HTTPS wwwroot; trust site scheme.
            return is_https();
        }
        return is_https();
    }

    /**
     * On staging/production, refuse clear-text HTTP for auth-sensitive scripts.
     *
     * Does not replace TLS — it blocks accidental HTTP access if the edge
     * redirect fails. Burp over HTTPS will still show decrypted form fields;
     * that is expected (TLS terminates at the proxy).
     */
    public static function require_https_web(): void {
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }
        if (defined('MOODLE_ENV') && MOODLE_ENV === 'dev') {
            return;
        }
        if (self::request_is_https()) {
            return;
        }

        throw new \moodle_exception('httpsrequired', 'theme_iiidem2');
    }

    /**
     * JSON APIs: exit with error instead of HTML exception.
     */
    public static function require_https_json(): void {
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }
        if (defined('MOODLE_ENV') && MOODLE_ENV === 'dev') {
            return;
        }
        if (self::request_is_https()) {
            return;
        }

        input_validation::json_exit([
            'ok' => false,
            'message' => get_string('httpsrequired', 'theme_iiidem2'),
        ]);
    }
}
