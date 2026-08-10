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
 * Keep exception details out of the browser; log them server-side.
 *
 * Intentional Moodle validation/permission errors may still be shown when
 * $allowmoodle is true. Raw Throwable messages (SQL, paths, API bodies) never are.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class safe_errors {

    /**
     * Log full exception details for operators (never send this to clients).
     */
    public static function log(\Throwable $e, string $context = ''): void {
        global $CFG;

        $prefix = $context !== '' ? $context . ': ' : '';
        error_log($prefix . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        $debuglevel = (int) ($CFG->debug ?? 0);
        if ($debuglevel >= DEBUG_DEVELOPER) {
            error_log($prefix . $e->getTraceAsString());
        }
    }

    /**
     * User-safe message. Never returns SQL, filesystem paths, or API payloads.
     *
     * @param bool $allowmoodle When true, localized moodle_exception text is OK (validation UX).
     */
    public static function message_for_user(\Throwable $e, bool $allowmoodle = true): string {
        if ($allowmoodle && $e instanceof \moodle_exception) {
            // getMessage() is the localized string; debuginfo is separate and must not be appended.
            $msg = trim(strip_tags($e->getMessage()));
            if ($msg !== '') {
                return $msg;
            }
        }
        return get_string('genericerror', 'theme_iiidem2');
    }

    /**
     * Flash a notification for full-page forms.
     */
    public static function notify(\Throwable $e, string $context = '', bool $allowmoodle = true): void {
        self::log($e, $context);
        \core\notification::error(self::message_for_user($e, $allowmoodle));
    }

    /**
     * Payload for JSON AJAX endpoints.
     *
     * @return array{success:bool,error:string,message:string}
     */
    public static function json(\Throwable $e, string $context = '', bool $allowmoodle = false): array {
        self::log($e, $context);
        return [
            'success' => false,
            'error' => 'exception',
            'message' => self::message_for_user($e, $allowmoodle),
        ];
    }
}
