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
 * Shared server-side input validation helpers.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class input_validation {

    public const LEN_NAME = 100;
    public const LEN_SHORT = 255;
    public const LEN_MESSAGE = 5000;
    public const LEN_CHATBOT_QUERY = 2000;
    public const LEN_CHATBOT_REPLY = 5000;
    public const LEN_TXNREF = 64;

    /**
     * Trim, clean as text, and optionally enforce max length (truncate or reject).
     *
     * @param string $value
     * @param int $maxlen
     * @param bool $truncate If false and over length, returns null
     * @return string|null
     */
    public static function clean_text(string $value, int $maxlen, bool $truncate = true): ?string {
        $value = trim(clean_param($value, PARAM_TEXT));
        if ($value === '') {
            return '';
        }
        if (\core_text::strlen($value) > $maxlen) {
            if (!$truncate) {
                return null;
            }
            return \core_text::substr($value, 0, $maxlen);
        }
        return $value;
    }

    /**
     * Whether string length is within [min, max] after trim.
     */
    public static function length_ok(string $value, int $min, int $max): bool {
        $len = \core_text::strlen(trim($value));
        return $len >= $min && $len <= $max;
    }

    /**
     * Clean payment callback scalar (alphanumeric ref).
     */
    public static function clean_txn_ref(string $value): string {
        $value = trim(clean_param($value, PARAM_ALPHANUMEXT));
        if (\core_text::strlen($value) > self::LEN_TXNREF) {
            $value = \core_text::substr($value, 0, self::LEN_TXNREF);
        }
        return $value;
    }

    /**
     * Sanitize a flat map of payment gateway return/mock fields.
     *
     * @param array $params
     * @return array
     */
    public static function clean_payment_params(array $params): array {
        $out = [];
        foreach ($params as $key => $value) {
            if (!is_string($key) || is_array($value) || is_object($value)) {
                continue;
            }
            $ukey = strtoupper(clean_param((string) $key, PARAM_ALPHANUMEXT));
            if ($ukey === '' || \core_text::strlen($ukey) > 64) {
                continue;
            }
            $sval = trim((string) $value);
            if (in_array($ukey, ['AMOUNT', 'TOTALAMOUNT'], true)) {
                $out[$ukey] = clean_param($sval, PARAM_FLOAT);
                $out[strtolower($ukey)] = $out[$ukey];
                continue;
            }
            if (in_array($ukey, ['TXNREFNO', 'ORDERID', 'MERCHANTID', 'STATUS', 'RESPONSECODE',
                'CURRENCYCODE', 'CHECKSUM'], true)) {
                $cleaned = self::clean_txn_ref($sval);
                if (in_array($ukey, ['STATUS', 'RESPONSECODE'], true)) {
                    $cleaned = clean_param($sval, PARAM_ALPHANUMEXT);
                }
                if ($ukey === 'CHECKSUM') {
                    $cleaned = clean_param($sval, PARAM_ALPHANUMEXT);
                    if (\core_text::strlen($cleaned) > 128) {
                        $cleaned = \core_text::substr($cleaned, 0, 128);
                    }
                }
                $out[$ukey] = $cleaned;
                continue;
            }
            // Bank refs / misc text fields.
            $text = self::clean_text($sval, self::LEN_SHORT, true);
            $out[$ukey] = $text ?? '';
        }
        return $out;
    }

    /**
     * Escape for HTML body/attributes (never echo raw request data).
     */
    public static function escape_html(string $value): string {
        return s($value);
    }

    /**
     * Detect HTML / script / event-handler payloads (XSS probes).
     */
    public static function contains_dangerous_markup(string $value): bool {
        if ($value === '') {
            return false;
        }
        if ($value !== strip_tags($value)) {
            return true;
        }
        if (preg_match('/[<>]|javascript\s*:|data\s*:|vbscript\s*:|on[a-z]+\s*=/i', $value)) {
            return true;
        }
        // Bare probe tokens often used in CDAC PoCs without angle brackets.
        if (preg_match('/(?:^|[^a-z0-9_])(?:alert|prompt|confirm)\s*\(/i', $value)) {
            return true;
        }
        if (preg_match('/<\/?\s*(?:script|iframe|object|embed|svg|math|link|meta|base)\b/i', $value)) {
            return true;
        }
        return false;
    }

    /**
     * Person name allow-list: letters (incl. Unicode), spaces, hyphen, apostrophe, period.
     * Anchored regex covering the entire string (CWE-20 recommendation).
     */
    public static function is_safe_person_name(string $value): bool {
        $value = trim($value);
        if ($value === '' || \core_text::strlen($value) > self::LEN_NAME) {
            return false;
        }
        if (self::contains_dangerous_markup($value)) {
            return false;
        }
        // Entire string: letters + combining marks, with limited separators.
        return (bool) preg_match('/^[\p{L}\p{M}]+(?:[\s\'\-\.]+[\p{L}\p{M}]+)*\.?$/u', $value);
    }

    /**
     * Plain single-line field (org, city, subject): no markup / control chars.
     */
    public static function is_safe_plain_line(string $value, int $maxlen = self::LEN_SHORT): bool {
        $value = trim($value);
        if ($value === '' || \core_text::strlen($value) > $maxlen) {
            return false;
        }
        if (self::contains_dangerous_markup($value)) {
            return false;
        }
        if (str_contains($value, '<') || str_contains($value, '>')) {
            return false;
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            return false;
        }
        return true;
    }

    /**
     * Clean public form free-text: reject markup first, then PARAM_TEXT + length.
     *
     * Important: do NOT strip tags and accept the remainder — that turns
     * `<script>hello</script>` into a successful “hello” submission (CDAC PoC).
     *
     * @return string|null Null when invalid / empty when allowempty and blank
     */
    public static function clean_public_text(string $value, int $maxlen, bool $allowempty = false): ?string {
        $raw = trim($value);
        if ($raw === '') {
            return $allowempty ? '' : null;
        }
        // Fail closed on any XSS / markup probe in the raw input.
        if (self::contains_dangerous_markup($raw)) {
            return null;
        }
        $cleaned = trim(clean_param($raw, PARAM_TEXT));
        $cleaned = trim(strip_tags($cleaned));
        if ($cleaned === '') {
            return $allowempty ? '' : null;
        }
        // If cleaning changed the semantic content (tags removed), reject.
        if ($cleaned !== $raw && self::contains_dangerous_markup($raw)) {
            return null;
        }
        // Reject when strip/clean removed angle-bracket content.
        if (str_contains($raw, '<') || str_contains($raw, '>')) {
            return null;
        }
        if (self::contains_dangerous_markup($cleaned)) {
            return null;
        }
        if (\core_text::strlen($cleaned) > $maxlen) {
            return null;
        }
        return $cleaned;
    }

    /**
     * Subject/body must contain at least one letter or digit (not only punctuation).
     */
    public static function has_alnum_content(string $value): bool {
        return (bool) preg_match('/[\p{L}\p{N}]/u', $value);
    }

    /**
     * Read email from the request without reflecting invalid/XSS payloads.
     *
     * Uses PARAM_RAW then validates — avoids Moodle exception pages that can
     * echo raw parameter debuginfo when cleaning fails under debug.
     *
     * @return string|null Lowercased valid email, or null when missing/invalid/dangerous
     */
    public static function request_email(string $paramname = 'email'): ?string {
        $raw = optional_param($paramname, '', PARAM_RAW);
        if (!is_string($raw)) {
            return null;
        }
        $raw = trim($raw);
        if ($raw === '' || self::contains_dangerous_markup($raw)) {
            return null;
        }
        $email = \core_text::strtolower(clean_param($raw, PARAM_EMAIL));
        if ($email === '' || !validate_email($email)) {
            return null;
        }
        // Reject if cleaning changed the address (quote / tag injection attempts).
        if (\core_text::strtolower($raw) !== $email) {
            return null;
        }
        return $email;
    }

    /**
     * Encode JSON for browser responses without reflecting raw markup as HTML.
     *
     * JSON_HEX_* prevents </script> / quote breakouts if a consumer embeds JSON in HTML.
     *
     * @param mixed $data
     * @return string
     */
    public static function json_encode_safe($data): string {
        $flags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $json = json_encode($data, $flags);
        return is_string($json) ? $json : '{"success":false,"error":"encode"}';
    }

    /**
     * Send a JSON response and exit (AJAX endpoints).
     *
     * @param mixed $data
     * @param int $status
     */
    public static function json_exit($data, int $status = 200): void {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            if ($status !== 200) {
                http_response_code($status);
            }
        }
        echo self::json_encode_safe($data);
        exit;
    }

    /**
     * Purify HTML fragments (course summary, custom fields, question text).
     * Removes script/iframe and other XSS vectors; keeps safe formatting tags.
     */
    public static function purify_html_fragment(string $html): string {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        // Decode entities so stored &lt;script&gt; probes are still removed.
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = self::strip_script_probes($html);
        $clean = clean_text($html, FORMAT_HTML);
        $clean = self::strip_script_probes($clean);
        $clean = preg_replace('/\son[a-z]+\s*=\s*(["\']).*?\1/iu', '', $clean) ?? $clean;
        $clean = preg_replace('/\son[a-z]+\s*=\s*[^\s>]+/iu', '', $clean) ?? $clean;
        // If the fragment is only XSS leftovers (no real prose), blank it.
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($clean)) ?? '');
        if ($plain !== '' && self::contains_dangerous_markup($plain)) {
            return '';
        }
        if (preg_match('/^\s*(?:alert|prompt|confirm)\s*\(/i', $plain)) {
            return '';
        }
        return $clean;
    }

    /**
     * Strip script tags and common XSS probe patterns from stored/plain text.
     * Use before format_text so FORMAT_MOODLE/PLAIN does not re-escape probes as visible text.
     */
    public static function strip_script_probes(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $text) ?? $text;
        $text = preg_replace('#</?script\b[^>]*>#is', '', $text) ?? $text;
        $text = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $text) ?? $text;
        $text = preg_replace('/javascript\s*:/i', '', $text) ?? $text;
        // Repeated auditor payloads left as literal text.
        $text = preg_replace('/<\s*script\b[^>]*>/i', '', $text) ?? $text;
        $text = preg_replace('/<\s*\/\s*script\s*>/i', '', $text) ?? $text;
        return $text;
    }

    /**
     * Plain title/name: no HTML at all.
     */
    public static function purify_plain_title(string $value): string {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = self::strip_script_probes($value);
        $value = trim(strip_tags($value));
        $value = clean_param($value, PARAM_TEXT);
        if (self::contains_dangerous_markup($value) || preg_match('/^\s*(?:alert|prompt|confirm)\s*\(/i', $value)) {
            return '';
        }
        return $value;
    }

    /**
     * Whether a raw request value is a strict positive decimal integer (allow-list).
     *
     * Unlike PARAM_INT (which coerces "{base}'…" → 0), this rejects any non-digit
     * junk used in JSON/XML injection scanner PoCs.
     *
     * @param mixed $raw
     * @return bool
     */
    public static function is_strict_positive_int($raw): bool {
        if (is_int($raw)) {
            return $raw > 0;
        }
        if (!is_string($raw) && !is_float($raw)) {
            return false;
        }
        $s = trim((string) $raw);
        return $s !== '' && (bool) preg_match('/^[1-9][0-9]{0,18}$/', $s);
    }

    /**
     * Keyword / filter token for participants and search (allow-list style).
     * Rejects markup and XSS probes entirely (does not strip-then-keep "alert(1)").
     */
    public static function sanitize_keyword_token(string $value): string {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }
        if (str_contains($raw, '<') || str_contains($raw, '>')
                || self::contains_dangerous_markup($raw)) {
            return '';
        }
        $clean = trim(strip_tags(clean_param($raw, PARAM_TEXT)));
        $clean = str_replace(['<', '>'], '', $clean);
        if ($clean === '' || !self::has_alnum_content($clean)) {
            return '';
        }
        if (self::contains_dangerous_markup($clean)
                || preg_match('/(?:^|[^a-z0-9_])(?:alert|prompt|confirm)\s*\(/i', $clean)) {
            return '';
        }
        if (\core_text::strlen($clean) > 200) {
            $clean = \core_text::substr($clean, 0, 200);
        }
        return $clean;
    }
}
