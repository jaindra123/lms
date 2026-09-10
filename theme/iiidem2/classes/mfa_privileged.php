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
 * Enable Multi-Factor Authentication for all accounts (students + staff + admins).
 *
 * CDAC #21: student login must not skip MFA. Disables factor_role / factor_admin
 * (those grant PASS to non-privileged users). Everyone earns ≥100 points via
 * TOTP or email OTP (grace allows first-time setup).
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mfa_privileged {

    /** Default grace period for first-time factor setup (7 days). */
    public const DEFAULT_GRACE_SECONDS = 7 * DAYSECS;

    /**
     * Role shortnames previously used for privileged-only MFA (kept for docs/CLI help).
     *
     * @return string[]
     */
    public static function privileged_role_shortnames(): array {
        return ['manager', 'coursecreator', 'editingteacher', 'teacher'];
    }

    /**
     * Enable MFA for every authenticated user (including students).
     *
     * @param int $graceseconds Setup grace period (0 = no grace factor)
     * @param bool $forcesetup Redirect users to set up a factor when grace ends
     * @return string[] Human-readable status lines
     */
    public static function enable(int $graceseconds = self::DEFAULT_GRACE_SECONDS, bool $forcesetup = true): array {
        $lines = [];

        // CDAC #21: role/admin factors PASS non-privileged users (students) with 100 pts
        // and skip the MFA challenge. Disable them so everyone must use TOTP/email.
        self::disable_factor('role');
        $lines[] = 'factor_role disabled (was skipping MFA for students)';

        self::disable_factor('admin');
        $lines[] = 'factor_admin disabled (was PASSing non-admins)';

        // Authenticator app (TOTP) — primary second factor.
        self::enable_factor('totp', 100);
        set_config('window', 15, 'factor_totp');
        set_config('totplink', 1, 'factor_totp');
        $lines[] = 'factor_totp enabled (weight 100)';

        // Email OTP — backup second factor (needs working outbound mail).
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
        } else {
            self::disable_factor('grace');
            $lines[] = 'factor_grace disabled';
        }

        // Master switch + lockout after failed MFA attempts.
        set_config('enabled', 1, 'tool_mfa');
        set_config('lockout', 10, 'tool_mfa');
        set_config('debugmode', 0, 'tool_mfa');
        $lines[] = 'tool_mfa/enabled = 1';
        $lines[] = 'tool_mfa/lockout = 10';

        $order = ['totp', 'email'];
        if ($graceseconds > 0) {
            $order[] = 'grace';
        }
        set_config('factor_order', implode(',', $order), 'tool_mfa');
        $lines[] = 'tool_mfa/factor_order = ' . implode(',', $order);
        $lines[] = 'Scope: ALL users (students, teachers, admins)';

        // Never leave nosetup enabled — it bypasses MFA.
        self::disable_factor('nosetup');

        purge_all_caches();

        return $lines;
    }

    /**
     * Enable a factor plugin and register it with tool_mfa.
     *
     * @param string $factor Factor name (totp, email, …)
     * @param int $weight Points awarded when the factor PASSes
     */
    private static function enable_factor(string $factor, int $weight): void {
        set_config('enabled', 1, 'factor_' . $factor);
        set_config('weight', $weight, 'factor_' . $factor);
        \tool_mfa\manager::do_factor_action($factor, 'enable');
    }

    /**
     * Disable a factor so it cannot grant PASS / bypass.
     *
     * @param string $factor Factor name
     */
    private static function disable_factor(string $factor): void {
        set_config('enabled', 0, 'factor_' . $factor);
        try {
            \tool_mfa\manager::do_factor_action($factor, 'disable');
        } catch (\Throwable $e) {
            // Factor may not be installed; ignore.
        }
    }
}
