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
 * HTTP security response headers (CSP, nosniff, XSS, Referrer, CORS, Clear-Site-Data).
 * HSTS is set once at the web server (.htaccess), not from this class.
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

    /** @var bool */
    private static $fullclearsent = false;

    /** @var string|null Per-request CSP nonce (base64). */
    private static $cspnonce = null;

    /** Clear-Site-Data header value required by CDAC #13 (logout). */
    public const CLEAR_SITE_DATA =
        '"cache", "cookies", "storage", "executionContexts"';

    /** Cache-only Clear-Site-Data for anonymous login GET (must not clear cookies). */
    public const CLEAR_SITE_DATA_LOGIN = '"cache"';

    /** HSTS value required by CDAC (preload was missing on staging Apache). */
    public const HSTS = 'max-age=31536000; includeSubDomains; preload';

    /**
     * requirejs.php / javascript.php abort after config but still run after_config.
     * HTML rewriters must not touch those JS/CSS bodies (breaks core/first).
     */
    private static function skip_html_mutation_buffers(): bool {
        if (defined('ABORT_AFTER_CONFIG') && ABORT_AFTER_CONFIG) {
            return true;
        }
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $uri = str_replace('\\', '/', (string) ($_SERVER['REQUEST_URI'] ?? ''));
        $haystack = strtolower($script . ' ' . $uri);
        foreach ([
            'requirejs.php',
            'javascript.php',
            'jslib.php',
            'jssourcemap.php',
            'yui_combo.php',
            'jquery.php',
            'styles.php',
            'styles_debug.php',
            'image.php',
            'font.php',
        ] as $asset) {
            if (str_contains($haystack, $asset)) {
                return true;
            }
            $bare = '/' . substr($asset, 0, -4);
            if ($script !== '' && (str_ends_with($script, $bare) || str_ends_with($script, $bare . '/'))) {
                return true;
            }
        }
        return false;
    }

    /**
     * True when the buffer is not an HTML document (JS combo, JSON, CSS).
     */
    private static function buffer_is_non_html(string $buffer): bool {
        $start = ltrim(substr($buffer, 0, 80));
        return $start !== '' && $start[0] !== '<' && $start[0] !== "\xEF";
    }

    /**
     * Send baseline security headers once per request (web only).
     */
    public static function send(): void {
        global $CFG, $SESSION;

        if (self::$sent) {
            // Still allow a late Clear-Site-Data after logout in the same request.
            self::emit_clear_site_data_if_pending();
            self::emit_login_clear_site_data();
            self::suppress_x_ua_compatible();
            self::emit_clickjacking_and_xss_headers();
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
        self::suppress_x_ua_compatible();
        if (!defined('MOODLE_ENV') || MOODLE_ENV !== 'dev') {
            header_remove('X-Moodle-Exception');
        }

        header('X-Content-Type-Options: nosniff');
        self::emit_clickjacking_and_xss_headers();
        // Cross-domain referrer leakage: full URL must not leak to other origins.
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(self), usb=()');
        // Isolate this origin: do not keep window.opener for cross-origin popups
        // (CDAC: Cross-Origin Opener Policy Allows Cross-Origin Popups).
        // Payments use top-level Payment Link redirects, not Checkout.js popups.
        header('Cross-Origin-Opener-Policy: same-origin');

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

        header_remove('Content-Security-Policy');
        header('Content-Security-Policy: ' . self::csp_policy(), true);

        self::emit_clear_site_data_if_pending();
        self::emit_login_clear_site_data();

        // HSTS is emitted once by Apache (.htaccess / vhost), not PHP.
        // PHP header() lands in Apache's onsuccess table; Header always set
        // lands in the always table — both survive and CDAC flags duplicates.
        // DDEV / loopback must still never pin HSTS (nginx snippet omits it).

        // Authenticated / login pages must not linger in the browser cache after logout.
        self::send_sensitive_cache_control();
    }

    /**
     * Emit Clear-Site-Data when queued (logout) or session flash is set.
     */
    private static function emit_clear_site_data_if_pending(): void {
        global $SESSION;

        if (headers_sent()) {
            return;
        }

        $pending = self::$clearsitedataqueued;
        if (!$pending && isset($SESSION) && !empty($SESSION->theme_iiidem2_clear_site_data)) {
            $pending = true;
        }
        if (!$pending) {
            return;
        }

        header('Clear-Site-Data: ' . self::CLEAR_SITE_DATA);
        self::$clearsitedataqueued = false;
        self::$fullclearsent = true;
        if (isset($SESSION)) {
            unset($SESSION->theme_iiidem2_clear_site_data);
        }
    }

    /**
     * Auditors capture GET /login/index.php. Full Clear-Site-Data (cookies) here
     * would wipe MoodleSession and break logintoken. Send cache-only so the header
     * is present without destroying the sign-in session.
     */
    private static function emit_login_clear_site_data(): void {
        if (headers_sent()) {
            return;
        }
        if (self::$fullclearsent || self::$clearsitedataqueued) {
            return;
        }
        if (!self::is_anonymous_login_document()) {
            return;
        }
        header('Clear-Site-Data: ' . self::CLEAR_SITE_DATA_LOGIN);
    }

    /**
     * Guest GET of login / MFA HTML (not AJAX, not credential POST).
     */
    private static function is_anonymous_login_document(): bool {
        if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
            return false;
        }
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return false;
        }
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'GET' && $method !== 'HEAD') {
            return false;
        }
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $islogin = (str_contains($script, '/login/') || str_contains($script, '/admin/tool/mfa/'))
            && !str_contains($script, '/login/logout');
        if (!$islogin) {
            return false;
        }
        try {
            if (isloggedin() && !isguestuser()) {
                return false;
            }
        } catch (\Throwable $e) {
            // Headers still apply on login before session is ready.
        }
        return true;
    }

    /**
     * HSTS only on real HTTPS at staging/production — not DDEV or loopback.
     */
    private static function should_send_hsts(): bool {
        if (defined('MOODLE_ENV') && MOODLE_ENV === 'dev') {
            return false;
        }
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if (str_contains($host, ':')) {
            [$host] = explode(':', $host, 2);
        }
        if ($host === '' || $host === 'localhost' || $host === '127.0.0.1'
                || str_ends_with($host, '.ddev.site') || str_ends_with($host, '.localhost')) {
            return false;
        }
        $https = (string) ($_SERVER['HTTPS'] ?? '');
        if ($https !== '' && strtolower($https) !== 'off') {
            return true;
        }
        $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $fwd === 'https';
    }

    /**
     * HTTPS via wwwroot, the request, or a TLS-terminating proxy.
     */
    public static function is_https(): bool {
        global $CFG;

        if (!empty($CFG->wwwroot) && str_starts_with($CFG->wwwroot, 'https://')) {
            return true;
        }
        $https = (string) ($_SERVER['HTTPS'] ?? '');
        if ($https !== '' && strtolower($https) !== 'off') {
            return true;
        }
        $fwd = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $fwd === 'https';
    }

    /**
     * Per-request CSP nonce. Stable for the header and every script/style tag.
     */
    public static function csp_nonce(): string {
        if (self::$cspnonce === null) {
            self::$cspnonce = base64_encode(random_bytes(16));
        }
        return self::$cspnonce;
    }

    /**
     * HTML attribute: nonce="…".
     */
    public static function nonce_attribute(): string {
        return ' nonce="' . htmlspecialchars(self::csp_nonce(), ENT_QUOTES, 'UTF-8') . '"';
    }

    /**
     * Stamp nonce on every script/style tag so script-src can drop 'unsafe-inline'.
     */
    public static function ensure_csp_nonce_html_buffer(): void {
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }
        if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
            return;
        }
        if (defined('WS_SERVER') && WS_SERVER) {
            return;
        }
        if (self::skip_html_mutation_buffers()) {
            return;
        }
        if (!empty($GLOBALS['theme_iiidem2_csp_nonce_ob'])) {
            return;
        }
        $GLOBALS['theme_iiidem2_csp_nonce_ob'] = true;

        ob_start([self::class, 'apply_csp_nonces']);
    }

    /**
     * @param string $html
     * @return string
     */
    public static function apply_csp_nonces(string $html): string {
        if ($html === '' || self::buffer_is_non_html($html)) {
            return $html;
        }
        if (stripos($html, '<script') === false && stripos($html, '<style') === false) {
            return $html;
        }
        $nonce = htmlspecialchars(self::csp_nonce(), ENT_QUOTES, 'UTF-8');
        $out = preg_replace_callback(
            '/<(script|style)\b([^>]*)>/i',
            static function (array $m) use ($nonce): string {
                $tag = $m[1];
                $attrs = $m[2];
                if (preg_match('/\bnonce\s*=/i', $attrs)) {
                    return $m[0];
                }
                return '<' . $tag . ' nonce="' . $nonce . '"' . $attrs . '>';
            },
            $html
        );
        return is_string($out) ? $out : $html;
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
        $isloginflow = str_contains($script, '/login/')
            || str_contains($script, '/admin/tool/mfa/');
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
     * Ensure every target=_blank anchor in HTML responses has rel=noopener noreferrer,
     * and external http(s) links get referrerpolicy=no-referrer (CDAC #28).
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
        if (self::skip_html_mutation_buffers()) {
            return;
        }
        if (!empty($GLOBALS['theme_iiidem2_noopener_ob'])) {
            return;
        }
        $GLOBALS['theme_iiidem2_noopener_ob'] = true;

        ob_start([self::class, 'harden_blank_target_html']);
    }

    /**
     * Strip sesskey from /login/*.php hrefs/actions in HTML (CDAC #17 session token in URL).
     * Logout CSRF is supplied via POST (logout_post.js).
     */
    public static function ensure_sesskey_url_strip_buffer(): void {
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }
        if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
            return;
        }
        if (defined('WS_SERVER') && WS_SERVER) {
            return;
        }
        if (self::skip_html_mutation_buffers()) {
            return;
        }
        if (!empty($GLOBALS['theme_iiidem2_sesskey_url_ob'])) {
            return;
        }
        $GLOBALS['theme_iiidem2_sesskey_url_ob'] = true;

        ob_start(static function (string $buffer): string {
            try {
                if (self::buffer_is_non_html($buffer)) {
                    return $buffer;
                }
                if (!method_exists(\theme_iiidem2\output\core_renderer::class, 'strip_login_sesskey_from_html')) {
                    return $buffer;
                }
                return \theme_iiidem2\output\core_renderer::strip_login_sesskey_from_html($buffer);
            } catch (\Throwable $e) {
                error_log('theme_iiidem2 sesskey buffer: ' . $e->getMessage());
                return $buffer;
            }
        });
    }

    /**
     * CDAC: Private IP address disclosed — strip RFC1918 from HTML/JSON
     * (report/log iplookup links, last IP, sessions).
     */
    public static function ensure_private_ip_html_buffer(): void {
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }
        if (defined('WS_SERVER') && WS_SERVER) {
            return;
        }
        if (self::skip_html_mutation_buffers()) {
            return;
        }
        if (!empty($GLOBALS['theme_iiidem2_private_ip_ob'])) {
            return;
        }
        $GLOBALS['theme_iiidem2_private_ip_ob'] = true;

        ob_start(static function (string $buffer): string {
            try {
                if (!class_exists(\theme_iiidem2\private_ip::class)) {
                    return $buffer;
                }
                return \theme_iiidem2\private_ip::redact_output($buffer);
            } catch (\Throwable $e) {
                error_log('theme_iiidem2 private_ip buffer: ' . $e->getMessage());
                return $buffer;
            }
        });
    }

    /**
     * Whether an absolute URL is cross-origin relative to this site.
     *
     * @param string $url
     * @return bool
     */
    private static function is_cross_origin_url(string $url): bool {
        global $CFG;
        $parts = @parse_url($url);
        if (empty($parts['host'])) {
            return false;
        }
        $sitehost = '';
        if (!empty($CFG->wwwroot)) {
            $site = @parse_url($CFG->wwwroot);
            $sitehost = strtolower((string) ($site['host'] ?? ''));
        }
        $linkhost = strtolower((string) $parts['host']);
        if ($sitehost === '' || $linkhost === '') {
            return true;
        }
        return $linkhost !== $sitehost;
    }

    /**
     * @param string $html
     * @return string
     */
    public static function harden_blank_target_html(string $html): string {
        if ($html === '' || self::buffer_is_non_html($html)) {
            return $html;
        }
        if (stripos($html, '<a') === false && stripos($html, '<A') === false) {
            return $html;
        }

        $out = preg_replace_callback(
            '/<a\b([^>]*?)>/is',
            static function (array $m): string {
                $attrs = $m[1];
                // Match target=_blank with optional quotes / whitespace (admin docs links).
                $isblank = (bool) preg_match('/\btarget\s*=\s*(["\']?)\s*_blank\s*\1/i', $attrs);
                $external = false;
                if (preg_match('/\bhref\s*=\s*(["\'])(https?:\/\/[^"\']+)\1/i', $attrs, $hm)
                        || preg_match('/\bhref\s*=\s*(https?:\/\/[^\s>]+)/i', $attrs, $hm2)) {
                    $url = $hm[2] ?? ($hm2[1] ?? '');
                    $external = $url !== '' && self::is_cross_origin_url($url);
                }

                if (!$isblank && !$external) {
                    return $m[0];
                }

                if ($isblank || $external) {
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
                }

                // CDAC #28: element-level policy so path/query never leave the site on click.
                if ($external && !preg_match('/\breferrerpolicy\s*=/i', $attrs)) {
                    $attrs .= ' referrerpolicy="no-referrer"';
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
            'Server',
            'X-UA-Compatible',
        ] as $headername) {
            header_remove($headername);
        }

        // Do not send a replacement Server value — Apache would still fingerprint
        // as "Apache" if we leave the token. Edge .htaccess unsets it as well.
    }

    /**
     * Drop deprecated X-UA-Compatible (IE document mode).
     *
     * Moodle send_headers() used to emit IE=edge after after_config; a flush
     * callback strips it even if a later core path re-adds it.
     */
    public static function suppress_x_ua_compatible(): void {
        static $callbackregistered = false;

        if (headers_sent()) {
            return;
        }

        header_remove('X-UA-Compatible');

        if ($callbackregistered) {
            return;
        }
        $callbackregistered = true;
        header_register_callback(static function (): void {
            header_remove('X-UA-Compatible');
            self::emit_clickjacking_and_xss_headers();
        });
    }

    /**
     * Queue Clear-Site-Data for the logout response (user_loggedout observer).
     */
    public static function queue_clear_site_data(): void {
        global $SESSION;

        self::$clearsitedataqueued = true;
        if (isset($SESSION)) {
            // Survives if send() already ran earlier in this request.
            $SESSION->theme_iiidem2_clear_site_data = 1;
        }

        if (!headers_sent()) {
            if (self::$sent) {
                self::emit_clear_site_data_if_pending();
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
     * X-XSS-Protection and X-Frame-Options (CDAC improper security headers).
     *
     * Moodle weblib send_headers() may overwrite X-Frame-Options with sameorigin;
     * a flush callback (see suppress_x_ua_compatible) re-asserts these.
     */
    private static function emit_clickjacking_and_xss_headers(): void {
        if (headers_sent()) {
            return;
        }
        header_remove('X-XSS-Protection');
        header('X-XSS-Protection: 1; mode=block', true);
        header_remove('X-Frame-Options');
        header('X-Frame-Options: ' . self::x_frame_options(), true);
    }

    /**
     * DENY on public/auth pages (incident URL is site root). SAMEORIGIN on
     * course/H5P/admin so same-origin iframes still work. Cross-origin
     * clickjacking is blocked either way.
     */
    public static function x_frame_options(): string {
        return self::must_deny_framing() ? 'DENY' : 'SAMEORIGIN';
    }

    /**
     * CSP frame-ancestors matching X-Frame-Options.
     */
    public static function csp_frame_ancestors(): string {
        return self::must_deny_framing() ? "'none'" : "'self'";
    }

    /**
     * Login, register, MFA, and the site front page must not be framed.
     */
    private static function must_deny_framing(): bool {
        global $CFG;

        if (!empty($CFG->allowframembedding)) {
            return false;
        }
        if (class_exists(\core_useragent::class, false) && \core_useragent::is_moodle_app()) {
            return false;
        }

        $uri = str_replace('\\', '/', (string) ($_SERVER['REQUEST_URI'] ?? '/'));
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $path = strtolower((string) (parse_url($uri, PHP_URL_PATH) ?: $script));
        if ($path === '') {
            $path = '/';
        }

        foreach (['/login', '/register', '/admin/tool/mfa/'] as $needle) {
            if (str_contains($path, $needle) || str_contains(strtolower($script), $needle)) {
                return true;
            }
        }

        $trimmed = rtrim($path, '/') ?: '/';
        return $trimmed === '/' || $trimmed === '/index.php' || $trimmed === '/index';
    }

    /**
     * Practical CSP for Moodle + hosted Payment Links + MathJax CDN.
     *
     * Scripts: 'self' + per-request nonce + strict-dynamic (no 'unsafe-inline',
     * no third-party script hosts). RequireJS still uses eval() so 'unsafe-eval'
     * remains. Inline event handlers: script-src-attr.
     * Styles: Moodle uses style= attributes — style-src keeps 'unsafe-inline'.
     *
     * Razorpay Checkout.js / Sardine / Sentry Browser SDK are NOT allow-listed
     * (script-src / connect-src). Payments use a top-level redirect to a hosted
     * Payment Link (form-action https:). Sentry 7.64.0 in CDAC captures is
     * Razorpay’s bundle on api.razorpay.com, not this origin.
     */
    public static function csp_policy(): string {
        $origin = self::site_origin();
        $nonce = self::csp_nonce();
        // Moodle filter_mathjaxloader uses jsDelivr MathJax 3.2.2 (not 2.7.9).
        $mathjax = 'https://cdn.jsdelivr.net';
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors " . self::csp_frame_ancestors(),
            "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-eval'",
            "script-src-attr 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline' {$mathjax}",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data: {$mathjax}",
            "connect-src 'self' {$mathjax}",
            "frame-src 'self' https://www.youtube.com https://youtube.com https://www.youtube-nocookie.com https://*.webex.com https://webex.com",
            // Bank / payment POSTs leave the site (https: hosts only).
            "form-action 'self' https:",
            "upgrade-insecure-requests",
            "worker-src 'self' blob:",
            "manifest-src 'self'",
        ];
        if ($origin !== '') {
            // Keep media local + https (direct .mp4 / Webex media).
            $directives[] = "media-src 'self' https: blob:";
        }
        return implode('; ', $directives);
    }
}
