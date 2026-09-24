<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Server-side login CAPTCHA (no Google). Complements account lockout.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class login_captcha {

    public const POST_FIELD = 'iiidem_login_captcha';
    public const SESSION_KEY = 'theme_iiidem2_login_captcha';

    /**
     * Moodle Google reCAPTCHA is already validating the login form.
     */
    public static function google_enabled(): bool {
        return function_exists('login_captcha_enabled') && login_captcha_enabled();
    }

    /**
     * HTML for the login form (injected into the recaptcha slot).
     */
    public static function html(): string {
        global $SESSION;

        $a = random_int(2, 9);
        $b = random_int(1, 9);
        $token = bin2hex(random_bytes(8));
        $SESSION->{self::SESSION_KEY} = [
            'hash' => hash_hmac('sha256', (string) ($a + $b), self::secret()),
            'token' => $token,
            'time' => time(),
        ];

        $label = get_string('logincaptchalabel', 'theme_iiidem2', (object) [
            'a' => $a,
            'b' => $b,
        ]);

        $out = \html_writer::start_div('iiidem-login-captcha');
        $out .= \html_writer::label($label, 'iiidem_login_captcha', false, [
            'class' => 'iiidem-loginform__label',
        ]);
        $out .= \html_writer::empty_tag('input', [
            'type' => 'text',
            'name' => self::POST_FIELD,
            'id' => 'iiidem_login_captcha',
            'class' => 'form-control iiidem-loginform__input',
            'required' => 'required',
            'inputmode' => 'numeric',
            'pattern' => '[0-9]{1,2}',
            'maxlength' => '2',
            'autocomplete' => 'off',
            'autocapitalize' => 'off',
            'spellcheck' => 'false',
            'aria-required' => 'true',
        ]);
        $out .= \html_writer::empty_tag('input', [
            'type' => 'hidden',
            'name' => self::POST_FIELD . '_token',
            'value' => $token,
        ]);
        $out .= \html_writer::end_div();
        return $out;
    }

    /**
     * Reject login POST before authenticate_user_login() when the challenge fails.
     */
    public static function enforce_on_login_post(): void {
        global $SESSION;

        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }
        if (defined('AJAX_SCRIPT') && AJAX_SCRIPT) {
            return;
        }
        if (defined('WS_SERVER') && WS_SERVER) {
            return;
        }
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
            return;
        }

        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (!str_ends_with($script, '/login/index.php')) {
            return;
        }
        if (self::google_enabled()) {
            return;
        }

        $answer = trim((string) ($_POST[self::POST_FIELD] ?? ''));
        $token = (string) ($_POST[self::POST_FIELD . '_token'] ?? '');
        $stored = $SESSION->{self::SESSION_KEY} ?? null;
        unset($SESSION->{self::SESSION_KEY});

        $ok = is_array($stored)
            && hash_equals((string) ($stored['token'] ?? ''), $token)
            && ctype_digit($answer)
            && (time() - (int) ($stored['time'] ?? 0)) < 600
            && hash_equals(
                (string) ($stored['hash'] ?? ''),
                hash_hmac('sha256', (string) ((int) $answer), self::secret())
            );

        if ($ok) {
            return;
        }

        $SESSION->loginerrormsg = get_string('logincaptchafailed', 'theme_iiidem2');
        redirect(new \moodle_url('/login/index.php'));
    }

    /**
     * @return string
     */
    private static function secret(): string {
        global $CFG;
        return 'iiidem-login-captcha|' . ($CFG->passwordsaltmain ?? '') . '|' . ($CFG->wwwroot ?? '');
    }
}
