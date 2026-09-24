<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Strong password policy for registration and ongoing logins (CDAC weak password).
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class password_policy {

    public const PREF_SET = 'theme_iiidem2_pwset';
    public const MAX_AGE_DAYS = 90;

    /**
     * Extra checks Moodle core does not cover (user id / email / leftover ciphertext).
     *
     * @return string[]
     */
    public static function extra_errors(string $password, ?string $username = null, ?string $email = null): array {
        $errors = [];
        if (str_starts_with($password, field_crypto::PREFIX)) {
            $errors[] = get_string('registerpasswordpolicyfailed', 'theme_iiidem2');
            return $errors;
        }

        $lower = \core_text::strtolower($password);
        if ($username !== null && $username !== '' && $lower === \core_text::strtolower($username)) {
            $errors[] = get_string('registerpasswordnotuserid', 'theme_iiidem2');
        }
        if ($email !== null && $email !== '') {
            $em = \core_text::strtolower($email);
            $local = (string) explode('@', $em, 2)[0];
            if ($lower === $em || ($local !== '' && $lower === $local)) {
                $errors[] = get_string('registerpasswordnotuserid', 'theme_iiidem2');
            }
        }

        return $errors;
    }

    /**
     * Combined core + extra policy errors as HTML, or empty string if valid.
     */
    public static function error_html(string $password, ?\stdClass $user = null): string {
        $errmsg = '';
        check_password_policy($password, $errmsg, $user);
        $username = isset($user->username) ? (string) $user->username : null;
        $email = isset($user->email) ? (string) $user->email : null;
        foreach (self::extra_errors($password, $username, $email) as $error) {
            $errmsg .= '<div>' . $error . '</div>';
        }
        return $errmsg;
    }

    /**
     * Record that this user just set a password (create or change).
     */
    public static function stamp(int $userid): void {
        if ($userid > 0) {
            set_user_preference(self::PREF_SET, time(), $userid);
        }
    }

    /**
     * After login: start the 90-day clock, or force a change when it expires.
     */
    public static function enforce_age_on_login(): void {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return;
        }
        $userid = (int) ($USER->id ?? 0);
        if ($userid < 1 || is_siteadmin($userid)) {
            return;
        }

        $set = (int) get_user_preferences(self::PREF_SET, 0, $USER);
        if ($set <= 0) {
            self::stamp($userid);
            return;
        }
        if ((time() - $set) > (self::MAX_AGE_DAYS * DAYSECS)) {
            set_user_preference('auth_forcepasswordchange', 1, $USER);
        }
    }

    /**
     * Observer: password was changed.
     *
     * @param \core\event\user_password_updated $event
     */
    public static function user_password_updated(\core\event\user_password_updated $event): void {
        $userid = (int) $event->relateduserid;
        if ($userid < 1) {
            $userid = (int) $event->userid;
        }
        if ($userid > 0) {
            self::stamp($userid);
        }
    }
}
