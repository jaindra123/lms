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
 * AJAX batch envelope allowlist (CDAC API Mass Assignment).
 *
 * Moodle external APIs already schema-validate args via external_function_parameters
 * (unexpected keys → invalid_parameter_exception). This guard additionally:
 * - Allows only index / methodname / args on each batch item
 * - Rejects privilege-like keys on the envelope or inside args
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ajax_request_guard {

    /** @var string[] Allowed keys on each /lib/ajax/service.php batch item. */
    private const ENVELOPE_KEYS = ['index', 'methodname', 'args'];

    /**
     * Keys that must never be client-assigned (privilege / authz mass-assignment PoCs).
     * Legitimate APIs that need a user id use "userid" — that remains allowed.
     *
     * @var string[]
     */
    private const FORBIDDEN_KEYS = [
        'isadmin',
        'is_siteadmin',
        'siteadmin',
        'siteadmins',
        'issso',
        'is_sso',
        'isguest',
        'role',
        'roles',
        'admin',
        'administrator',
        'capability',
        'capabilities',
        'permissions',
        'accessallgroups',
        'loggedinas',
        'realuser',
        'sesskey',
        'wstoken',
        'defaultuserroleid',
        'auth',
    ];

    /**
     * Explicit arg allowlist for the auditor PoC method (and other tight APIs).
     * Methods not listed still use Moodle external_function_parameters.
     *
     * @var array<string, string[]>
     */
    private const METHOD_ARG_ALLOWLIST = [
        'media_videojs_get_language' => ['lang'],
    ];

    /**
     * Must survive PARAM_TEXT (control chars are stripped) and must not match
     * real course/message rows. Empty search on My Courses returns ALL courses.
     */
    private const REJECTED_SEARCH_SENTINEL = 'iiidemRejectedSearchToken';

    /** @var string[] */
    private const SEARCH_ARG_KEYS = [
        'search', 'q', 'query', 'keywords', 'searchtext', 'searchvalue', 'searchstring',
    ];

    /**
     * Validate one batch item. Returns null when OK, or an error response array.
     *
     * @param mixed $request
     * @return array|null
     */
    public static function validate_item($request): ?array {
        if (!is_array($request)) {
            return self::reject_response();
        }

        $extra = array_diff(array_keys($request), self::ENVELOPE_KEYS);
        if ($extra !== []) {
            return self::reject_response();
        }

        if (!array_key_exists('methodname', $request)
                || !array_key_exists('index', $request)
                || !array_key_exists('args', $request)) {
            return self::reject_response();
        }

        if (!is_string($request['methodname']) && !is_numeric($request['methodname'])) {
            return self::reject_response();
        }

        if (!is_array($request['args'])) {
            return self::reject_response();
        }

        if (self::args_contain_forbidden_keys($request['args'])) {
            return self::reject_response();
        }

        $method = (string) $request['methodname'];
        $denied = self::validate_method_arg_allowlist($method, $request['args']);
        if ($denied !== null) {
            return $denied;
        }
        if ($method === 'media_videojs_get_language') {
            $denied = self::validate_videojs_language($request['args']);
            if ($denied !== null) {
                return $denied;
            }
        }
        if ($method === 'core_calendar_get_calendar_event_by_id') {
            $denied = self::validate_calendar_event_by_id($request['args']);
            if ($denied !== null) {
                return $denied;
            }
        }

        return null;
    }

    /**
     * Empty SQLi/XSS search tokens on message/course AJAX (drawer search is not a GET param).
     *
     * @param array $args
     */
    public static function sanitize_search_args(array &$args): void {
        foreach ($args as $key => &$value) {
            if (is_array($value)) {
                self::sanitize_search_args($value);
                continue;
            }
            if (!is_string($key) || !is_string($value)) {
                continue;
            }
            if (!in_array(strtolower($key), self::SEARCH_ARG_KEYS, true)) {
                continue;
            }
            if (input_validation::is_plain_json_document($value)) {
                continue;
            }
            $clean = input_validation::sanitize_keyword_token($value);
            // Empty string must not reach message LIKE searches (wildcard).
            $args[$key] = ($value !== '' && $clean === '') ? self::REJECTED_SEARCH_SENTINEL : $clean;
        }
        unset($value);
    }

    /**
     * Reject args keys that are not on the per-method allowlist.
     *
     * @param string $method
     * @param array $args
     * @return array|null
     */
    private static function validate_method_arg_allowlist(string $method, array $args): ?array {
        if (!isset(self::METHOD_ARG_ALLOWLIST[$method])) {
            return null;
        }
        $allowed = self::METHOD_ARG_ALLOWLIST[$method];
        foreach (array_keys($args) as $key) {
            if (!is_string($key) || !in_array($key, $allowed, true)) {
                return self::reject_response();
            }
        }
        foreach ($allowed as $need) {
            if (!array_key_exists($need, $args)) {
                return self::reject_response();
            }
        }
        return null;
    }

    /**
     * media_videojs_get_language: only a known Video.js lang pack code.
     *
     * @param array $args
     * @return array|null
     */
    private static function validate_videojs_language(array $args): ?array {
        global $CFG;

        if (!isset($args['lang']) || is_array($args['lang'])) {
            return self::reject_response();
        }
        $lang = (string) $args['lang'];
        if (!preg_match('/^[a-z]{2}(?:[-_][A-Za-z0-9]{2,8})?$/', $lang)) {
            return self::reject_response();
        }

        $dir = realpath($CFG->dirroot . '/media/player/videojs/videojs/lang');
        if ($dir === false) {
            return self::reject_response();
        }
        $file = realpath($dir . DIRECTORY_SEPARATOR . $lang . '.json');
        $prefix = $dir . DIRECTORY_SEPARATOR;
        if ($file === false || !str_starts_with($file, $prefix) || !is_file($file)) {
            return self::reject_response();
        }

        return null;
    }

    /**
     * CDAC Web Parameter Tampering Instance 4: eventid must be a real id the
     * caller is allowed to view. PARAM_INT coerces "0" / junk to 0 and must not
     * fall through to another calendar row.
     *
     * @param array $args
     * @return array|null
     */
    private static function validate_calendar_event_by_id(array $args): ?array {
        global $CFG, $DB;

        if (!array_key_exists('eventid', $args)
                || is_array($args['eventid'])
                || !input_validation::is_strict_positive_int($args['eventid'])) {
            return self::deny_calendar_event();
        }

        $eventid = (int) $args['eventid'];
        require_once($CFG->dirroot . '/calendar/lib.php');
        $record = $DB->get_record('event', ['id' => $eventid]);
        if (!$record || (int) $record->id !== $eventid) {
            return self::deny_calendar_event();
        }

        try {
            $event = new \calendar_event($record);
            if (!\calendar_view_event_allowed($event)) {
                return self::deny_calendar_event();
            }
        } catch (\Throwable $e) {
            return self::deny_calendar_event();
        }

        return null;
    }

    /**
     * Recursively detect forbidden mass-assignment keys in args.
     *
     * @param array $args
     * @return bool
     */
    private static function args_contain_forbidden_keys(array $args): bool {
        foreach ($args as $key => $value) {
            if (is_string($key) && self::is_forbidden_key($key)) {
                return true;
            }
            if (is_array($value) && self::args_contain_forbidden_keys($value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $key
     * @return bool
     */
    private static function is_forbidden_key(string $key): bool {
        $norm = strtolower(str_replace(['-', ' '], '_', $key));
        return in_array($norm, self::FORBIDDEN_KEYS, true);
    }

    /**
     * Generic client-safe rejection (no key names / stack).
     *
     * @return array{error:bool,exception:array}
     */
    public static function reject_response(): array {
        return [
            'error' => true,
            'exception' => [
                'errorcode' => 'invalidparameter',
                'message' => get_string('invalidparameter', 'debug'),
            ],
        ];
    }

    /**
     * Same deny for missing, invalid, and unauthorized event ids (no existence oracle).
     *
     * @return array{error:bool,exception:array}
     */
    public static function deny_calendar_event(): array {
        return [
            'error' => true,
            'exception' => [
                'errorcode' => 'nopermissiontoviewcalendar',
                'message' => get_string('nopermissiontoviewcalendar', 'error'),
            ],
        ];
    }

    /**
     * CDAC Razorpay Key ID Exposure: never send Key ID / order id / PII to the browser.
     *
     * Strips leftover Checkout.js fields even if an older paygw_razorpay still
     * returns them. Hosted Payment Link only needs redirecturl.
     *
     * @param string $methodname
     * @param array $response call_external_function payload
     * @return array
     */
    public static function redact_payment_ajax(string $methodname, array $response): array {
        if ($methodname !== 'paygw_razorpay_get_checkout_data') {
            return $response;
        }
        if (!empty($response['error']) || !isset($response['data']) || !is_array($response['data'])) {
            return $response;
        }

        $allowed = ['redirecturl', 'mock', 'mockurl'];
        $clean = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $response['data'])) {
                $clean[$key] = $response['data'][$key];
            }
        }
        $response['data'] = $clean;
        return $response;
    }
}
