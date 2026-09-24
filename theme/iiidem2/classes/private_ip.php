<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Hide RFC1918 / loopback / link-local / CGNAT IPs in LMS HTML
 * (CDAC: Private IP address disclosed on /report/log/index.php).
 *
 * Stored log values are unchanged for forensics. Public IPs still link to iplookup.
 *
 * @package   theme_iiidem2
 */
final class private_ip {

    /**
     * First address from a stored log value (may be X-Forwarded-For style).
     */
    public static function canonical(string $ip): string {
        $ip = trim($ip);
        if ($ip === '') {
            return '';
        }
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip, 2)[0]);
        }
        if (preg_match('/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $ip, $m)) {
            $ip = $m[1];
        }
        if (str_starts_with($ip, '[') && str_contains($ip, ']')) {
            $ip = substr($ip, 1, strpos($ip, ']') - 1);
        }
        return $ip;
    }

    /**
     * Whether this address must not be shown in the UI.
     */
    public static function is_private(string $ip): bool {
        $ip = self::canonical($ip);
        if ($ip === '' || $ip === '-' || strcasecmp($ip, 'unknown') === 0
                || $ip === '0.0.0.0' || $ip === '::' || $ip === '::1') {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long === false) {
                return true;
            }
            $unsigned = $long < 0 ? $long + 4294967296 : $long;
            $ranges = [
                [ip2long('0.0.0.0'), ip2long('0.255.255.255')],
                [ip2long('10.0.0.0'), ip2long('10.255.255.255')],
                [ip2long('100.64.0.0'), ip2long('100.127.255.255')],
                [ip2long('127.0.0.0'), ip2long('127.255.255.255')],
                [ip2long('169.254.0.0'), ip2long('169.254.255.255')],
                [ip2long('172.16.0.0'), ip2long('172.31.255.255')],
                [ip2long('192.168.0.0'), ip2long('192.168.255.255')],
            ];
            foreach ($ranges as [$start, $end]) {
                $s = $start < 0 ? $start + 4294967296 : $start;
                $e = $end < 0 ? $end + 4294967296 : $end;
                if ($unsigned >= $s && $unsigned <= $e) {
                    return true;
                }
            }
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $flags = FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
            return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false;
        }

        // Not a well-formed public IP — do not echo it.
        return true;
    }

    /**
     * Replacement label (no octets).
     */
    public static function label(): string {
        if (function_exists('get_string')) {
            $s = get_string('privateipmasked', 'theme_iiidem2');
            if (is_string($s) && $s !== '' && $s !== '[[privateipmasked]]') {
                return $s;
            }
        }
        return 'Private address';
    }

    /**
     * Safe text for tables / emails / downloads.
     */
    public static function display(string $ip): string {
        $ip = self::canonical($ip);
        return self::is_private($ip) ? self::label() : $ip;
    }

    /**
     * HTML for a log/profile IP cell. Private addresses are plain text, never iplookup links.
     */
    public static function html(string $ip, int $userid = 0, bool $popup = false): string {
        $ip = self::canonical($ip);
        if (self::is_private($ip)) {
            return \html_writer::span(s(self::label()), 'iiidem-private-ip');
        }
        $params = ['ip' => $ip];
        if ($userid > 0) {
            $params['user'] = $userid;
        }
        if ($popup) {
            $params['popup'] = 1;
        }
        $url = new \moodle_url('/iplookup/index.php', $params);
        return \html_writer::link($url, s($ip));
    }

    /**
     * Strip private IPs and iplookup links from HTML or JSON (final output).
     */
    public static function redact_output(string $buffer): string {
        if ($buffer === '') {
            return $buffer;
        }
        $label = htmlspecialchars(self::label(), ENT_QUOTES | ENT_HTML401 | ENT_SUBSTITUTE, 'UTF-8');

        $buffer = preg_replace_callback(
            '#<a\b[^>]*iplookup/index\.php[^>]*>.*?</a>#is',
            static function (array $m) use ($label): string {
                if (!preg_match('/[?&](?:amp;)?ip=([^&"\'#\s]+)/i', $m[0], $ipm)) {
                    return $m[0];
                }
                $ip = rawurldecode(html_entity_decode($ipm[1], ENT_QUOTES | ENT_HTML401));
                return self::is_private($ip)
                    ? '<span class="iiidem-private-ip">' . $label . '</span>'
                    : $m[0];
            },
            $buffer
        ) ?? $buffer;

        $buffer = preg_replace_callback(
            '/([?&]ip=)([^&"\'#\s]+)/i',
            static function (array $m): string {
                $ip = rawurldecode(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML401));
                return self::is_private($ip) ? $m[1] . 'redacted' : $m[0];
            },
            $buffer
        ) ?? $buffer;

        $v4 = '/\b(?:'
            . '10\.\d{1,3}\.\d{1,3}\.\d{1,3}'
            . '|127\.\d{1,3}\.\d{1,3}\.\d{1,3}'
            . '|169\.254\.\d{1,3}\.\d{1,3}'
            . '|192\.168\.\d{1,3}\.\d{1,3}'
            . '|172\.(?:1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3}'
            . '|100\.(?:6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.\d{1,3}\.\d{1,3}'
            . ')\b/';
        $buffer = preg_replace($v4, $label, $buffer) ?? $buffer;

        return $buffer;
    }
}
