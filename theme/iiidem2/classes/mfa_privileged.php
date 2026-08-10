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
 * Enable Multi-Factor Authentication for privileged accounts.
 *
 * Uses core tool_mfa: role factor marks privileged users NEUTRAL (must earn
 * 100 points from TOTP/email); other users PASS the role factor and skip MFA.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mfa_privileged {

    /** Default grace period for first-time factor setup (7 days). */
    public const DEFAULT_GRACE_SECONDS = 7 * DAYSECS;

    /**
     * Role shortnames treated as privileged (plus site administrators).
     *
     * @return string[]
     */
    public static function privileged_role_shortnames(): array {
        return ['manager', 'coursecreator', 'editingteacher', 'teacher'];
    }

    /**
     * Enable MFA and configure factors for privileged accounts.
     *
     * @param int $graceseconds Setup grace period (0 = no grace factor)
     * @param bool $forcesetup Redirect users to set up a factor when grace ends
     * @return string[] Human-readable status lines
     */
    public static function enable(int $graceseconds = self::DEFAULT_GRACE_SECONDS, bool $forcesetup = true): array {
        global $DB;

        $lines = [];

        // Resolve privileged role ids by shortname (portable across sites).
        $roleids = [];
        foreach (self::privileged_role_shortnames() as $shortname) {
            $id = $DB->get_field('role', 'id', ['shortname' => $shortname], IGNORE_MISSING);
            if ($id) {
                $roleids[] = (string) (int) $id;
            }
        }
        // Special token recognised by factor_role for site administrators.
        $rolesconfig = array_merge(['admin'], $roleids);
        set_config('roles', implode(',', $rolesconfig), 'factor_role');
        $lines[] = 'factor_role/roles = ' . implode(',', $rolesconfig);

        // Role factor: privileged → NEUTRAL (need another factor); others → PASS (100 pts).
        self::enable_factor('role', 100);
        $lines[] = 'factor_role enabled (weight 100)';

        // Site-admin singleton (belt-and-suspenders with role "admin").
        self::enable_factor('admin', 100);
        $lines[] = 'factor_admin enabled (weight 100)';

        // Authenticator app (TOTP) — primary second factor.
        self::enable_factor('totp', 100);
        set_config('window', 15, 'factor_totp');
        set_config('totplink', 1, 'factor_totp');
        $lines[] = 'factor_totp enabled (weight 100)';

        // Email OTP — available backup second factor for privileged users.
        self::enable_factor('email', 100);
        set_config('duration', 30 * MINSECS, 'factor_email');
        set_config('suspend', 0, 'factor_email');
        $lines[] = 'factor_email enabled (weight 100)';

        if ($graceseconds > 0) {
            self::enable_factor('grace', 100);
            set_config('graceperiod', $graceseconds, 'factor_grace');
            set_config('forcesetup', $forcesetup ? 1 : 0, 'factor_grace');
            $lines[] = 'factor_grace enabled (period ' . $graceseconds . 's, forcesetup='
                . ($forcesetup ? '1' : '0') . ')';
        }

        // Master switch + lockout after failed MFA attempts.
        set_config('enabled', 1, 'tool_mfa');
        set_config('lockout', 10, 'tool_mfa');
        set_config('debugmode', 0, 'tool_mfa');
        $lines[] = 'tool_mfa/enabled = 1';
        $lines[] = 'tool_mfa/lockout = 10';

        // Prefer role check first, then interactive factors, then grace.
        $order = ['role', 'admin', 'totp', 'email'];
        if ($graceseconds > 0) {
            $order[] = 'grace';
        }
        set_config('factor_order', implode(',', $order), 'tool_mfa');
        $lines[] = 'tool_mfa/factor_order = ' . implode(',', $order);

        purge_all_caches();

        return $lines;
    }

    /**
     * Enable a factor plugin and register it with tool_mfa factor_order.
     *
     * @param string $factor Factor name (role, totp, …)
     * @param int $weight Points awarded when the factor PASSes
     */
    private static function enable_factor(string $factor, int $weight): void {
        set_config('enabled', 1, 'factor_' . $factor);
        set_config('weight', $weight, 'factor_' . $factor);
        \tool_mfa\manager::do_factor_action($factor, 'enable');
    }
}
