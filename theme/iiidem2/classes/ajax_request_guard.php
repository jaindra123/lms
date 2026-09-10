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
        'issso',
        'is_sso',
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
}
