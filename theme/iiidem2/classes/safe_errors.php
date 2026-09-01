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
            // Never reflect raw request fragments that may have been interpolated into $a.
            $msg = clean_param($msg, PARAM_TEXT);
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

    /**
     * Strip debuginfo / backtrace / sesskey material from Moodle AJAX JSON (CDAC CWE-209).
     *
     * Core only omits these when debugging('', DEBUG_DEVELOPER) is false; this is a
     * fail-closed filter for staging/production even if debug was misconfigured.
     * Local MOODLE_ENV=dev keeps full payloads for developers.
     */
    public static function sanitize_ajax_json(string $buffer): string {
        if (defined('MOODLE_ENV') && MOODLE_ENV === 'dev') {
            return $buffer;
        }

        $trim = ltrim($buffer);
        if ($trim === '' || ($trim[0] !== '{' && $trim[0] !== '[')) {
            return $buffer;
        }

        $data = json_decode($buffer, true);
        if (!is_array($data)) {
            return $buffer;
        }

        $changed = self::strip_exception_debug_fields($data);
        if (!$changed) {
            return $buffer;
        }

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $out = json_encode($data, $flags);
        return is_string($out) ? $out : $buffer;
    }

    /**
     * Recursively remove sensitive exception fields from AJAX payloads.
     *
     * @param array $node
     * @return bool Whether anything was changed
     */
    private static function strip_exception_debug_fields(array &$node): bool {
        $changed = false;

        // Moodle multi-call: [ {error, exception}, ... ]
        if (array_is_list($node)) {
            foreach ($node as &$item) {
                if (is_array($item) && self::strip_exception_debug_fields($item)) {
                    $changed = true;
                }
            }
            unset($item);
            return $changed;
        }

        if (isset($node['exception']) && is_array($node['exception'])) {
            foreach (['debuginfo', 'backtrace', 'stacktrace', 'reproductionlink', 'a'] as $key) {
                if (array_key_exists($key, $node['exception'])) {
                    unset($node['exception'][$key]);
                    $changed = true;
                }
            }
            if (!empty($node['exception']['message']) && is_string($node['exception']['message'])) {
                $scrubbed = self::scrub_public_error_text($node['exception']['message']);
                if ($scrubbed !== $node['exception']['message']) {
                    $node['exception']['message'] = $scrubbed;
                    $changed = true;
                }
            }
        }

        // core_renderer_ajax::fatal_error() shape: { error, stacktrace, debuginfo, ... }
        foreach (['debuginfo', 'backtrace', 'stacktrace', 'reproductionlink'] as $key) {
            if (array_key_exists($key, $node)) {
                unset($node[$key]);
                $changed = true;
            }
        }

        // Top-level "error" is sometimes a verbose string (not the boolean used in multi-call).
        if (isset($node['error']) && is_string($node['error'])) {
            $scrubbed = self::scrub_public_error_text($node['error']);
            if ($scrubbed !== $node['error']) {
                $node['error'] = $scrubbed;
                $changed = true;
            }
        }

        foreach ($node as &$child) {
            if (is_array($child) && self::strip_exception_debug_fields($child)) {
                $changed = true;
            }
        }
        unset($child);

        return $changed;
    }

    /**
     * Replace programmer / token / sesskey leak text with a safe public string.
     */
    private static function scrub_public_error_text(string $msg): string {
        if (preg_match('/sesskey/i', $msg) && preg_match('/\([A-Za-z0-9]{8,}\)/', $msg)) {
            return get_string('invalidsesskey', 'error');
        }
        // coding_exception / webservice "fixed by a programmer" / internal WS dumps (CDAC Instance 5).
        if (preg_match('/must be fixed by a programmer/i', $msg)
            || preg_match('/internal error in the Web services/i', $msg)
            || preg_match('/Invalid error detected/i', $msg)
            || preg_match('#(?:/var/www|/home/|/usr/share/moodle)#', $msg)
            || preg_match('/\bSELECT\b.+\bFROM\b/i', $msg)
            // Moodle MUST_EXIST wording historically named DB tables (CDAC #37).
            || preg_match('/database table\b/i', $msg)
            || preg_match('/Can\'t find data record/i', $msg)
        ) {
            return get_string('genericerror', 'theme_iiidem2');
        }
        return $msg;
    }
}
