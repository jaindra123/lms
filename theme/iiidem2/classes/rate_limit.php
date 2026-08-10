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
 * Server-side request rate limiting (per user, IP, or custom key).
 *
 * Uses the application cache so limits survive across sessions (unlike
 * SESSION-only cooldowns that attackers can bypass).
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class rate_limit {

    /**
     * Whether another hit is allowed; increments the counter when allowed.
     *
     * @param string $bucket Logical endpoint name (e.g. register_send_otp)
     * @param int $max Maximum allowed hits inside the window
     * @param int $window Window length in seconds
     * @param string|null $identity Override identity (default: userid or client IP)
     * @return bool
     */
    public static function allow(string $bucket, int $max, int $window, ?string $identity = null): bool {
        $bucket = trim($bucket);
        if ($bucket === '' || $max <= 0 || $window <= 0) {
            return true;
        }

        $identity = $identity ?? self::default_identity();
        $identity = trim($identity);
        if ($identity === '') {
            $identity = 'anon';
        }

        $cache = self::cache();
        $key = self::cache_key($bucket, $identity);
        $now = time();

        $entry = $cache->get($key);
        if (!is_array($entry)
                || !isset($entry['start'], $entry['count'])
                || ($now - (int) $entry['start']) >= $window) {
            $entry = [
                'start' => $now,
                'count' => 0,
            ];
        }

        if ((int) $entry['count'] >= $max) {
            return false;
        }

        $entry['count'] = (int) $entry['count'] + 1;
        $cache->set($key, $entry);

        return true;
    }

    /**
     * Require allow() or throw a Moodle exception (web services / pages).
     *
     * @param string $bucket
     * @param int $max
     * @param int $window
     * @param string|null $identity
     * @return void
     * @throws \moodle_exception
     */
    public static function require_allowed(string $bucket, int $max, int $window, ?string $identity = null): void {
        if (!self::allow($bucket, $max, $window, $identity)) {
            throw new \moodle_exception('ratelimited', 'theme_iiidem2');
        }
    }

    /**
     * Require allow() or emit JSON 429 and exit (AJAX endpoints).
     *
     * @param string $bucket
     * @param int $max
     * @param int $window
     * @param string|null $identity
     * @param array $extra Extra JSON fields
     * @return void
     */
    public static function require_json(string $bucket, int $max, int $window, ?string $identity = null,
            array $extra = []): void {
        if (self::allow($bucket, $max, $window, $identity)) {
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Retry-After: ' . max(1, $window));
            http_response_code(429);
        }

        $payload = array_merge([
            'ok' => false,
            'success' => false,
            'error' => 'ratelimit',
            'message' => get_string('ratelimited', 'theme_iiidem2'),
        ], $extra);

        echo json_encode($payload);
        exit;
    }

    /**
     * Default identity: logged-in user id, else client IP.
     */
    public static function default_identity(): string {
        global $USER;

        if (!empty($USER->id) && (int) $USER->id > 0 && !isguestuser()) {
            return 'u:' . (int) $USER->id;
        }

        return 'ip:' . self::client_ip();
    }

    /**
     * Best-effort client IP (respects Moodle reverse-proxy config).
     */
    public static function client_ip(): string {
        $ip = getremoteaddr();
        $ip = is_string($ip) ? trim($ip) : '';
        return $ip !== '' ? $ip : '0.0.0.0';
    }

    /**
     * Identity for an email-scoped bucket.
     */
    public static function email_identity(string $email): string {
        $email = \core_text::strtolower(trim($email));
        return 'email:' . ($email !== '' ? $email : 'empty');
    }

    /**
     * @return \cache
     */
    private static function cache(): \cache {
        return \cache::make_from_params(
            \cache_store::MODE_APPLICATION,
            'theme_iiidem2',
            'ratelimit',
            ['simplekeys' => true, 'simpledata' => true]
        );
    }

    private static function cache_key(string $bucket, string $identity): string {
        // Cache keys must be short alphanumeric-ish; hash the composite.
        return 'rl_' . sha1($bucket . '|' . $identity);
    }
}
