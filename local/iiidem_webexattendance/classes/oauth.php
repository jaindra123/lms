<?php
namespace local_iiidem_webexattendance;

defined('MOODLE_INTERNAL') || die();

/**
 * Webex OAuth2 helpers (Integration client credentials + refresh token).
 */
class oauth {

    public const TOKEN_URL = 'https://webexapis.com/v1/access_token';
    public const AUTH_URL = 'https://webexapis.com/v1/authorize';
    public const SCOPES = 'meeting:participants_read meeting:admin_participants_read meeting:schedules_read spark:kms';

    public static function redirect_uri(): string {
        return (new \moodle_url('/local/iiidem_webexattendance/oauth.php'))->out(false);
    }

    public static function is_configured(): bool {
        $clientid = trim((string) get_config('local_iiidem_webexattendance', 'clientid'));
        $secret = trim((string) get_config('local_iiidem_webexattendance', 'clientsecret'));
        return $clientid !== '' && $secret !== '';
    }

    public static function is_connected(): bool {
        $refresh = trim((string) get_config('local_iiidem_webexattendance', 'refresh_token'));
        return $refresh !== '';
    }

    public static function authorize_url(): string {
        global $SESSION;

        // Opaque OAuth state (not Moodle sesskey / session id) — never put session tokens in URLs.
        $state = bin2hex(random_bytes(16));
        $SESSION->local_iiidem_webexattendance_oauth_state = $state;

        $params = [
            'client_id' => trim((string) get_config('local_iiidem_webexattendance', 'clientid')),
            'response_type' => 'code',
            'redirect_uri' => self::redirect_uri(),
            'scope' => self::SCOPES,
            'state' => $state,
        ];
        return self::AUTH_URL . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange authorization code for tokens.
     */
    public static function exchange_code(string $code): void {
        $payload = [
            'grant_type' => 'authorization_code',
            'client_id' => trim((string) get_config('local_iiidem_webexattendance', 'clientid')),
            'client_secret' => trim((string) get_config('local_iiidem_webexattendance', 'clientsecret')),
            'code' => $code,
            'redirect_uri' => self::redirect_uri(),
        ];
        $data = self::token_request($payload);
        self::store_tokens($data);
    }

    /**
     * Valid access token (refreshing if needed).
     */
    public static function get_access_token(): string {
        $access = trim((string) get_config('local_iiidem_webexattendance', 'access_token'));
        $expires = (int) get_config('local_iiidem_webexattendance', 'token_expires');
        if ($access !== '' && $expires > (time() + 60)) {
            return $access;
        }

        $refresh = trim((string) get_config('local_iiidem_webexattendance', 'refresh_token'));
        if ($refresh === '') {
            throw new \moodle_exception('notconnected_exception', 'local_iiidem_webexattendance');
        }

        $payload = [
            'grant_type' => 'refresh_token',
            'client_id' => trim((string) get_config('local_iiidem_webexattendance', 'clientid')),
            'client_secret' => trim((string) get_config('local_iiidem_webexattendance', 'clientsecret')),
            'refresh_token' => $refresh,
        ];
        $data = self::token_request($payload);
        self::store_tokens($data);
        return trim((string) get_config('local_iiidem_webexattendance', 'access_token'));
    }

    protected static function store_tokens(array $data): void {
        if (empty($data['access_token'])) {
            throw new \moodle_exception('oauth_error', 'local_iiidem_webexattendance', '', 'missing access_token');
        }
        set_config('access_token', $data['access_token'], 'local_iiidem_webexattendance');
        if (!empty($data['refresh_token'])) {
            set_config('refresh_token', $data['refresh_token'], 'local_iiidem_webexattendance');
        }
        $expiresin = (int) ($data['expires_in'] ?? 3600);
        set_config('token_expires', time() + max(60, $expiresin), 'local_iiidem_webexattendance');
    }

    protected static function token_request(array $payload): array {
        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/x-www-form-urlencoded']);
        $raw = $curl->post(self::TOKEN_URL, $payload);
        $info = $curl->get_info();
        $code = (int) ($info['http_code'] ?? 0);
        $data = json_decode((string) $raw, true);
        if ($code < 200 || $code >= 300 || !is_array($data)) {
            $msg = is_array($data) ? ($data['message'] ?? $data['error'] ?? $raw) : $raw;
            throw new \moodle_exception('oauth_error', 'local_iiidem_webexattendance', '', $msg);
        }
        return $data;
    }
}
