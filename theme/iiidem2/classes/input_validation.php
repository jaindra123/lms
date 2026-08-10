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
}
