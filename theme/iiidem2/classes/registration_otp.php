<?php
// This file is part of Moodle - http://moodle.org/.

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Email OTP for registration (before the user account is created).
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registration_otp {

    /** @var int OTP lifetime in seconds. */
    public const TTL = 600;

    /** @var int Max failed verify attempts before a new OTP is required. */
    public const MAX_ATTEMPTS = 5;

    /** @var int Minimum seconds between resend requests. */
    public const RESEND_COOLDOWN = 30;

    /**
     * @return array|null
     */
    protected static function get_store(): ?array {
        global $SESSION;
        if (empty($SESSION->theme_iiidem2_regotp) || !is_array($SESSION->theme_iiidem2_regotp)) {
            return null;
        }
        return $SESSION->theme_iiidem2_regotp;
    }

    /**
     * @param array $data
     * @return void
     */
    protected static function set_store(array $data): void {
        global $SESSION;
        $SESSION->theme_iiidem2_regotp = $data;
    }

    /**
     * @return void
     */
    public static function clear(): void {
        global $SESSION;
        unset($SESSION->theme_iiidem2_regotp);
    }

    /**
     * Generate, store, and email a new OTP for this address.
     *
     * @param string $email
     * @param string $firstname
     * @return array{ok:bool,message:string,cooldown?:int}
     */
    public static function send(string $email, string $firstname = ''): array {
        global $SITE;

        $email = \core_text::strtolower(trim($email));
        if ($email === '' || !validate_email($email)) {
            return [
                'ok' => false,
                'message' => get_string('invalidemail'),
            ];
        }

        $existing = self::get_store();
        if ($existing && (int) ($existing['lastsend'] ?? 0) > (time() - self::RESEND_COOLDOWN)
                && ($existing['email'] ?? '') === $email) {
            $wait = self::RESEND_COOLDOWN - (time() - (int) $existing['lastsend']);
            return [
                'ok' => false,
                'message' => get_string('registerotpwait', 'theme_iiidem2', max(1, $wait)),
                'cooldown' => max(1, $wait),
            ];
        }

        $code = (string) random_int(100000, 999999);
        self::set_store([
            'email' => $email,
            'hash' => password_hash($code, PASSWORD_DEFAULT),
            'expires' => time() + self::TTL,
            'attempts' => 0,
            'lastsend' => time(),
            'verified' => false,
        ]);

        $sitename = format_string($SITE->fullname);
        $minutes = (int) max(1, floor(self::TTL / MINSECS));
        $supportuser = \core_user::get_support_user();
        $touser = clone \core_user::get_noreply_user();
        $touser->id = -99;
        $touser->email = $email;
        $touser->firstname = $firstname !== '' ? $firstname : 'User';
        $touser->lastname = '';
        $touser->maildisplay = true;
        $touser->mailformat = 1;
        $touser->emailstop = 0;

        $subject = get_string('registerotpsubject', 'theme_iiidem2', $sitename);
        $text = get_string('registerotpbody', 'theme_iiidem2', (object) [
            'code' => $code,
            'minutes' => $minutes,
            'sitename' => $sitename,
            'firstname' => $touser->firstname,
        ]);
        $html = notify_email::render([
            'sitename' => $sitename,
            'firstname' => $touser->firstname,
            'title' => get_string('registerotptitle', 'theme_iiidem2'),
            'intro' => get_string('registerotpintro', 'theme_iiidem2', (object) [
                'minutes' => $minutes,
                'sitename' => $sitename,
            ]),
            'rows' => [
                [
                    'label' => get_string('registerotplabel', 'theme_iiidem2'),
                    'value' => $code,
                ],
            ],
            'note' => get_string('registerotpnote', 'theme_iiidem2'),
            'signoff' => $sitename,
            'ctaurl' => (new \moodle_url('/register/'))->out(false),
            'ctalabel' => get_string('registerotpcta', 'theme_iiidem2'),
        ]);

        $sent = email_to_user($touser, $supportuser, $subject, $text, $html);
        if (!$sent) {
            return [
                'ok' => false,
                'message' => get_string('registerotpsendfailed', 'theme_iiidem2'),
            ];
        }

        return [
            'ok' => true,
            // Do not echo the address (CDAC reflected XSS / input-in-response).
            'message' => get_string('registerotpsent', 'theme_iiidem2'),
            'cooldown' => self::RESEND_COOLDOWN,
            'expiresin' => self::TTL,
        ];
    }

    /**
     * Verify an OTP for the given email. On success marks the session verified.
     *
     * @param string $email
     * @param string $code
     * @return array{ok:bool,message:string}
     */
    public static function verify(string $email, string $code): array {
        $email = \core_text::strtolower(trim($email));
        $code = trim($code);

        $store = self::get_store();
        if (!$store || ($store['email'] ?? '') !== $email) {
            return [
                'ok' => false,
                'message' => get_string('registerotpmissing', 'theme_iiidem2'),
            ];
        }

        if ((int) ($store['expires'] ?? 0) < time()) {
            self::clear();
            return [
                'ok' => false,
                'message' => get_string('registerotpexpired', 'theme_iiidem2'),
            ];
        }

        if ((int) ($store['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            return [
                'ok' => false,
                'message' => get_string('registerotplocked', 'theme_iiidem2'),
            ];
        }

        if ($code === '' || !preg_match('/^[0-9]{6}$/', $code)
                || empty($store['hash']) || !password_verify($code, $store['hash'])) {
            $store['attempts'] = (int) ($store['attempts'] ?? 0) + 1;
            self::set_store($store);
            if ($store['attempts'] >= self::MAX_ATTEMPTS) {
                return [
                    'ok' => false,
                    'message' => get_string('registerotplocked', 'theme_iiidem2'),
                ];
            }
            return [
                'ok' => false,
                'message' => get_string('registerotpinvalid', 'theme_iiidem2'),
            ];
        }

        $store['verified'] = true;
        $store['verifiedat'] = time();
        self::set_store($store);

        return [
            'ok' => true,
            'message' => get_string('registerotpverified', 'theme_iiidem2'),
        ];
    }

    /**
     * Whether this email completed OTP verification in the current session.
     *
     * @param string $email
     * @return bool
     */
    public static function is_verified(string $email): bool {
        $email = \core_text::strtolower(trim($email));
        $store = self::get_store();
        if (!$store || ($store['email'] ?? '') !== $email) {
            return false;
        }
        if (empty($store['verified'])) {
            return false;
        }
        if ((int) ($store['expires'] ?? 0) < time()) {
            self::clear();
            return false;
        }
        return true;
    }
}
