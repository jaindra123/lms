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
        // Cross-domain referrer leakage: full URL must not leak to other origins.
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: SAMEORIGIN');

        // Keep Moodle admin setting aligned (weblib.php also emits this header).
        if (empty($CFG->referrerpolicy) || $CFG->referrerpolicy === 'default') {
            $CFG->referrerpolicy = 'strict-origin-when-cross-origin';
        }

        // Same-origin only — never reflect arbitrary Origin or use *.
        $origin = self::site_origin();
        if ($origin !== '') {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Headers: X-Moodle-Sesskey, Content-Type, Accept');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

        // Authenticated / login pages must not linger in the browser cache after logout.
        self::send_sensitive_cache_control();
    }

    /**
     * Prevent browser/proxy reuse of authenticated HTML/JSON after logout (bfcache / back button).
     *
     * Covers normal pages, /login/*, and AJAX (/lib/ajax/service.php) which auditors
     * often capture with Moodle’s weaker "private, max-age=0" (no no-store).
     *
     * Public anonymous pages keep Moodle defaults except login/password flows.
     * Intentional long-cache AJAX (GET + cachekey) is left alone.
     */
    public static function send_sensitive_cache_control(): void {
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }
        if (headers_sent()) {
            return;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $isloginflow = str_contains($script, '/login/');
        $isajax = (defined('AJAX_SCRIPT') && AJAX_SCRIPT)
            || str_contains($script, '/lib/ajax/')
            || str_contains($script, '/webservice/');

        // Moodle core may mark some GET AJAX as publicly cacheable via cachekey.
        if ($isajax
                && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
                && !empty($_GET['cachekey'])
                && (int) $_GET['cachekey'] > 0) {
            return;
        }

        $isauthed = false;
        try {
            $isauthed = isloggedin() && !isguestuser();
        } catch (\Throwable $e) {
            $isauthed = false;
        }

        if (!$isauthed && !$isloginflow) {
            return;
        }

        header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    /**
     * Re-assert no-store for AJAX just before the response body is flushed.
     *
     * Moodle / PHP may emit weaker Cache-Control after after_config; buffering
     * lets us win on authenticated /lib/ajax/service.php responses (CDAC PoC).
     */
    public static function ensure_ajax_cache_control_buffer(): void {
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }
        if (!defined('AJAX_SCRIPT') || !AJAX_SCRIPT) {
            return;
        }
        if (!empty($GLOBALS['theme_iiidem2_ajax_cache_ob'])) {
            return;
        }
        $GLOBALS['theme_iiidem2_ajax_cache_ob'] = true;

        ob_start(static function (string $buffer): string {
            self::send_sensitive_cache_control();
            return safe_errors::sanitize_ajax_json($buffer);
        });
    }

    /**
     * Ensure every target=_blank anchor in HTML responses has rel=noopener noreferrer.
     * Auditors inspect raw HTML (admin environment docs links) before JS runs (CDAC #24).
     */
    public static function ensure_noopener_blank_targets_buffer(): void {
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }
        if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
            return;
        }
        if (defined('WS_SERVER') && WS_SERVER) {
            return;
        }
        if (!empty($GLOBALS['theme_iiidem2_noopener_ob'])) {
            return;
        }
        $GLOBALS['theme_iiidem2_noopener_ob'] = true;

        ob_start([self::class, 'harden_blank_target_html']);
    }

    /**
     * @param string $html
     * @return string
     */
    public static function harden_blank_target_html(string $html): string {
        if ($html === '' || stripos($html, '_blank') === false) {
            return $html;
        }

        $out = preg_replace_callback(
            '/<a\b([^>]*?)>/i',
            static function (array $m): string {
                $attrs = $m[1];
                if (!preg_match('/\btarget\s*=\s*(["\']?)_blank\1/i', $attrs)) {
                    return $m[0];
                }
                if (preg_match('/\brel\s*=\s*(["\'])([^"\']*)\1/i', $attrs, $rm)) {
                    $rel = preg_split('/\s+/', strtolower($rm[2]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    foreach (['noopener', 'noreferrer'] as $token) {
                        if (!in_array($token, $rel, true)) {
                            $rel[] = $token;
                        }
                    }
                    $attrs = preg_replace(
                        '/\brel\s*=\s*(["\'])([^"\']*)\1/i',
                        'rel="' . implode(' ', $rel) . '"',
                        $attrs,
                        1
                    );
                } else {
                    $attrs .= ' rel="noopener noreferrer"';
                }
                return '<a' . $attrs . '>';
            },
            $html
        );

        return is_string($out) ? $out : $html;
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
     * Practical CSP for Moodle + Razorpay checkout + MathJax CDN (Moodle default).
     */
    public static function csp_policy(): string {
        $origin = self::site_origin();
        // Moodle filter_mathjaxloader uses jsDelivr MathJax 3.2.2 (not 2.7.9).
        $mathjax = 'https://cdn.jsdelivr.net';
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            // Moodle AMD / YUI / Mustache need inline + eval in many releases.
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://checkout.razorpay.com https://cdn.razorpay.com {$mathjax}",
            "style-src 'self' 'unsafe-inline' {$mathjax}",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: {$mathjax}",
            "connect-src 'self' https://api.razorpay.com https://lumberjack.razorpay.com https://checkout.razorpay.com https://checkout-static-next.razorpay.com {$mathjax}",
            "frame-src 'self' https://api.razorpay.com https://checkout.razorpay.com",
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
