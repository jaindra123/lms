<?php
namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Application-layer encryption for auth secrets in POST bodies (CDAC cryptography).
 *
 * TLS still protects the wire. This wraps password / OTP so intercepting proxies
 * and access logs do not see the raw values (Burp still shows ciphertext).
 *
 * @package   theme_iiidem2
 */
final class field_crypto {

    public const PREFIX = 'iiidemenc.';

    /**
     * Fields that must never travel as plaintext in a form POST.
     *
     * @return string[]
     */
    public static function sensitive_fields(): array {
        return [
            'password',
            'password1',
            'password2',
            'newpassword1',
            'newpassword2',
            'oldpassword',
            'verificationcode',
        ];
    }

    /**
     * Decrypt inbound POST fields that the login/MFA JS wrapped.
     */
    public static function unwrap_post_fields(): void {
        if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
            return;
        }
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        foreach (self::sensitive_fields() as $field) {
            if (!isset($_POST[$field]) || !is_string($_POST[$field])) {
                continue;
            }
            $value = $_POST[$field];
            if (!str_starts_with($value, self::PREFIX)) {
                continue;
            }
            $plain = self::decrypt_payload(substr($value, strlen(self::PREFIX)));
            if ($plain === '') {
                continue;
            }
            $_POST[$field] = $plain;
            $_REQUEST[$field] = $plain;
        }
    }

    /**
     * PEM public key for the browser (SPKI).
     */
    public static function public_pem(): string {
        self::ensure_keys();
        return (string) get_config('theme_iiidem2', 'field_crypto_pub');
    }

    /**
     * JSON blob for a &lt;script type="application/json"&gt; tag.
     */
    public static function public_json(): string {
        return json_encode(['pem' => self::public_pem(), 'prefix' => self::PREFIX], JSON_UNESCAPED_SLASHES);
    }

    public static function ensure_keys(): void {
        $pub = (string) get_config('theme_iiidem2', 'field_crypto_pub');
        $priv = (string) get_config('theme_iiidem2', 'field_crypto_priv');
        if ($pub !== '' && $priv !== '') {
            return;
        }

        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($res === false) {
            debugging('theme_iiidem2 field_crypto: openssl_pkey_new failed', DEBUG_DEVELOPER);
            return;
        }
        $privpem = '';
        if (!openssl_pkey_export($res, $privpem)) {
            return;
        }
        $details = openssl_pkey_get_details($res);
        $pubpem = (string) ($details['key'] ?? '');
        if ($pubpem === '' || $privpem === '') {
            return;
        }
        set_config('field_crypto_pub', $pubpem, 'theme_iiidem2');
        set_config('field_crypto_priv', $privpem, 'theme_iiidem2');
    }

    private static function decrypt_payload(string $b64): string {
        $raw = base64_decode($b64, true);
        if ($raw === false || $raw === '') {
            return '';
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['k']) || empty($data['iv']) || empty($data['c'])) {
            return '';
        }

        $wrapped = base64_decode((string) $data['k'], true);
        $iv = base64_decode((string) $data['iv'], true);
        $ctag = base64_decode((string) $data['c'], true);
        if ($wrapped === false || $iv === false || $ctag === false || strlen((string) $ctag) < 17) {
            return '';
        }

        $priv = (string) get_config('theme_iiidem2', 'field_crypto_priv');
        if ($priv === '') {
            self::ensure_keys();
            $priv = (string) get_config('theme_iiidem2', 'field_crypto_priv');
        }
        $pkey = openssl_pkey_get_private($priv);
        if ($pkey === false) {
            return '';
        }

        $aeskey = '';
        if (!openssl_private_decrypt($wrapped, $aeskey, $pkey, OPENSSL_PKCS1_OAEP_PADDING)) {
            return '';
        }
        if (strlen($aeskey) !== 32) {
            return '';
        }

        $tag = substr($ctag, -16);
        $ct = substr($ctag, 0, -16);
        $plain = openssl_decrypt($ct, 'aes-256-gcm', $aeskey, OPENSSL_RAW_DATA, $iv, $tag);
        return is_string($plain) ? $plain : '';
    }
}
