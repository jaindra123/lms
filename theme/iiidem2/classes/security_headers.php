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
 * HTTP security response headers (CSP, nosniff, XSS, Referrer, CORS).
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class security_headers {

    /** @var bool */
    private static $sent = false;

    /** @var bool */
    private static $clearsitedataqueued = false;

    /**
     * Send baseline security headers once per request (web only).
     */
    public static function send(): void {
        global $CFG;

        if (self::$sent) {
            return;
        }
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }
        if (headers_sent()) {
            return;
        }

        self::$sent = true;

        // Disable technology/version disclosure in response headers.
        self::suppress_version_headers();

        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: SAMEORIGIN');

        // Same-origin only — never reflect arbitrary Origin or use *.
        $origin = self::site_origin();
        if ($origin !== '') {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }

        header('Content-Security-Policy: ' . self::csp_policy());

        if (self::$clearsitedataqueued) {
            // Auditor requirement: clear browser site data after logout.
            header('Clear-Site-Data: "cache", "cookies", "storage", "executionContexts"');
            self::$clearsitedataqueued = false;
        }

        // HSTS only when the site is served over HTTPS.
        if (!empty($CFG->wwwroot) && str_starts_with($CFG->wwwroot, 'https://')) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Remove headers that disclose server / language / framework versions.
     */
    public static function suppress_version_headers(): void {
        @ini_set('expose_php', '0');

        if (headers_sent()) {
            return;
        }

        foreach ([
            'X-Powered-By',
            'X-AspNet-Version',
            'X-AspNetMvc-Version',
            'X-Generator',
            'X-Drupal-Cache',
            'X-Drupal-Dynamic-Cache',
        ] as $headername) {
            header_remove($headername);
        }

        // Where the SAPI allows it, strip or blank the Server token (full removal
        // of nginx/Apache Server often requires server_tokens off — see docs).
        header_remove('Server');
    }

    /**
     * Queue Clear-Site-Data for the logout response (user_loggedout observer).
     */
    public static function queue_clear_site_data(): void {
        self::$clearsitedataqueued = true;
        // If headers not sent yet, emit immediately (logout redirect path).
        if (!headers_sent()) {
            // Allow send() to include Clear-Site-Data even if other headers already went out
            // via an earlier send() in this request.
            if (self::$sent) {
                header('Clear-Site-Data: "cache", "cookies", "storage", "executionContexts"');
                self::$clearsitedataqueued = false;
            } else {
                self::send();
            }
        }
    }

    /**
     * Event observer: user logged out.
     *
     * @param \core\event\user_loggedout $event
     */
    public static function user_loggedout(\core\event\user_loggedout $event): void {
        unset($event);
        self::queue_clear_site_data();
    }

    /**
     * Site origin from wwwroot (scheme://host[:port]).
     */
    public static function site_origin(): string {
        global $CFG;

        if (empty($CFG->wwwroot)) {
            return '';
        }
        $parts = parse_url($CFG->wwwroot);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }
        return $origin;
    }

    /**
     * Practical CSP for Moodle + Razorpay checkout (allows required inline/AMD).
     */
    public static function csp_policy(): string {
        $origin = self::site_origin();
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            // Moodle AMD / YUI / Mustache need inline + eval in many releases.
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://checkout.razorpay.com https://cdn.razorpay.com",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            "connect-src 'self' https://api.razorpay.com https://lumberjack.razorpay.com https://checkout.razorpay.com",
            "frame-src 'self' https://api.razorpay.com https://checkout.razorpay.com https://api.razorpay.com",
            // Bank / payment POSTs leave the site.
            "form-action 'self' https:",
            "upgrade-insecure-requests",
        ];
        if ($origin !== '') {
            // Keep media local + https.
            $directives[] = "media-src 'self' https: blob:";
        }
        return implode('; ', $directives);
    }
}
