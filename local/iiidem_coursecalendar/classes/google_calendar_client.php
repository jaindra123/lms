<?php
namespace local_iiidem_coursecalendar;

defined('MOODLE_INTERNAL') || die();

/**
 * Google Calendar API client (service account + optional domain-wide delegation).
 *
 * Creates events with enrolled students as attendees and sendUpdates=all so Google
 * sends invitation emails (Accept / Decline / Maybe).
 */
class google_calendar_client {

    private const TOKEN_URI = 'https://oauth2.googleapis.com/token';
    private const API_BASE = 'https://www.googleapis.com/calendar/v3';

    /** @var array|null Parsed service account JSON */
    private ?array $credentials = null;

    /**
     * Whether Google Calendar API credentials are configured.
     */
    public static function is_configured(): bool {
        $json = trim((string) get_config('local_iiidem_coursecalendar', 'google_service_account_json'));
        $calendarid = trim((string) get_config('local_iiidem_coursecalendar', 'google_calendar_id'));
        return $json !== '' && $calendarid !== '';
    }

    /**
     * Target Google Calendar ID (from plugin settings).
     */
    public static function get_calendar_id(): string {
        return trim((string) get_config('local_iiidem_coursecalendar', 'google_calendar_id'));
    }

    /**
     * Create a calendar event and optionally email invites to attendees.
     *
     * @param array{summary:string,description?:string,location?:string,start:int,end:int} $event
     * @param string[] $attendeeemails Valid email addresses
     * @param bool $sendinvites Pass sendUpdates=all to Google
     * @return array{id:string,htmlLink?:string} Google event payload subset
     */
    public function create_event(array $event, array $attendeeemails, bool $sendinvites = true): array {
        $calendarid = self::get_calendar_id();
        if ($calendarid === '') {
            throw new \moodle_exception('googleapinotconfigured', 'local_iiidem_coursecalendar');
        }

        $timezone = \core_date::get_server_timezone();
        $body = [
            'summary' => $event['summary'],
            'description' => $event['description'] ?? '',
            'location' => $event['location'] ?? '',
            'start' => [
                'dateTime' => self::format_rfc3339($event['start'], $timezone),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => self::format_rfc3339($event['end'], $timezone),
                'timeZone' => $timezone,
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 60],
                    ['method' => 'popup', 'minutes' => 15],
                ],
            ],
        ];

        $attendees = [];
        foreach ($attendeeemails as $email) {
            $email = trim(strtolower($email));
            if ($email !== '' && validate_email($email)) {
                $attendees[] = ['email' => $email];
            }
        }
        if (!empty($attendees)) {
            $body['attendees'] = $attendees;
        }

        $query = $sendinvites && !empty($attendees) ? '?sendUpdates=all' : '';
        $url = self::API_BASE . '/calendars/' . rawurlencode($calendarid) . '/events' . $query;

        try {
            $response = $this->api_request('POST', $url, $body);
        } catch (\moodle_exception $e) {
            // Personal Gmail calendars: service accounts cannot add attendees without Workspace DWD.
            if (!empty($attendees) && self::is_attendee_delegation_error($e->getMessage())) {
                unset($body['attendees']);
                $response = $this->api_request('POST', self::API_BASE . '/calendars/' . rawurlencode($calendarid) . '/events', $body);
                $response['_attendees_skipped'] = true;
            } else {
                throw $e;
            }
        }

        if (empty($response['id'])) {
            throw new \moodle_exception('googleapicreatefailed', 'local_iiidem_coursecalendar');
        }

        return [
            'id' => (string) $response['id'],
            'htmlLink' => $response['htmlLink'] ?? '',
            'attendees_skipped' => !empty($response['_attendees_skipped']),
        ];
    }

    /**
     * Update an existing calendar event (time/title/attendees) and optionally notify.
     *
     * @param string $googleeventid
     * @param array{summary:string,description?:string,location?:string,start:int,end:int} $event
     * @param string[] $attendeeemails
     * @param bool $sendinvites
     * @return array{id:string,htmlLink?:string}
     */
    public function update_event(string $googleeventid, array $event, array $attendeeemails, bool $sendinvites = true): array {
        $calendarid = self::get_calendar_id();
        if ($calendarid === '' || $googleeventid === '') {
            throw new \moodle_exception('googleapinotconfigured', 'local_iiidem_coursecalendar');
        }

        $timezone = \core_date::get_server_timezone();
        $body = [
            'summary' => $event['summary'],
            'description' => $event['description'] ?? '',
            'location' => $event['location'] ?? '',
            'start' => [
                'dateTime' => self::format_rfc3339($event['start'], $timezone),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => self::format_rfc3339($event['end'], $timezone),
                'timeZone' => $timezone,
            ],
        ];

        $attendees = [];
        foreach ($attendeeemails as $email) {
            $email = trim(strtolower($email));
            if ($email !== '' && validate_email($email)) {
                $attendees[] = ['email' => $email];
            }
        }
        if (!empty($attendees)) {
            $body['attendees'] = $attendees;
        }

        $query = $sendinvites && !empty($attendees) ? '?sendUpdates=all' : '';
        $url = self::API_BASE . '/calendars/' . rawurlencode($calendarid)
            . '/events/' . rawurlencode($googleeventid) . $query;

        try {
            $response = $this->api_request('PATCH', $url, $body);
        } catch (\moodle_exception $e) {
            if (!empty($attendees) && self::is_attendee_delegation_error($e->getMessage())) {
                unset($body['attendees']);
                $response = $this->api_request(
                    'PATCH',
                    self::API_BASE . '/calendars/' . rawurlencode($calendarid) . '/events/' . rawurlencode($googleeventid),
                    $body
                );
                $response['_attendees_skipped'] = true;
            } else {
                throw $e;
            }
        }

        return [
            'id' => (string) ($response['id'] ?? $googleeventid),
            'htmlLink' => $response['htmlLink'] ?? '',
            'attendees_skipped' => !empty($response['_attendees_skipped']),
        ];
    }

    /**
     * Google rejects SA attendee invites on consumer Gmail without Workspace domain-wide delegation.
     */
    private static function is_attendee_delegation_error(string $message): bool {
        $message = strtolower($message);
        return strpos($message, 'domain-wide delegation') !== false
            || strpos($message, 'cannot invite attendees') !== false;
    }

    /**
     * Cancel/delete a Google Calendar event.
     */
    public function delete_event(string $googleeventid, bool $notifyattendees = true): void {
        if ($googleeventid === '') {
            return;
        }
        $calendarid = self::get_calendar_id();
        $query = $notifyattendees ? '?sendUpdates=all' : '';
        $url = self::API_BASE . '/calendars/' . rawurlencode($calendarid) . '/events/' . rawurlencode($googleeventid) . $query;
        $this->api_request('DELETE', $url);
    }

    /**
     * @param string $method GET|POST|PATCH|DELETE
     * @param string $url Full API URL
     * @param array|null $body JSON body for POST/PATCH
     * @return array Decoded JSON response (empty for DELETE)
     */
    private function api_request(string $method, string $url, ?array $body = null): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $token = $this->get_access_token();
        $payload = $body !== null ? json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;

        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        // Native cURL is more reliable for Google APIs than Moodle's curl helper here.
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CUSTOMREQUEST => $method,
            ];
            if ($method === 'POST' || $method === 'PATCH' || $method === 'PUT') {
                $opts[CURLOPT_POSTFIELDS] = $payload ?? '';
            }
            curl_setopt_array($ch, $opts);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            if ($errno) {
                throw new \moodle_exception('googleapierror', 'local_iiidem_coursecalendar', '', $error);
            }
        } else {
            $curl = new \curl();
            $curl->setHeader($headers);
            $options = ['CURLOPT_TIMEOUT' => 30];
            if ($method === 'DELETE') {
                $options['CURLOPT_CUSTOMREQUEST'] = 'DELETE';
                $raw = $curl->get($url, null, $options);
            } else if ($method === 'GET') {
                $raw = $curl->get($url, null, $options);
            } else {
                $curl->setopt(array_merge($options, ['CURLOPT_CUSTOMREQUEST' => $method]));
                $raw = $curl->post($url, $payload ?? '');
            }
            $info = $curl->get_info();
            $code = (int) ($info['http_code'] ?? 0);
        }

        if ($method === 'DELETE' && ($code === 204 || $code === 410 || $code === 200)) {
            return [];
        }

        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if ($code < 200 || $code >= 300) {
            $message = is_array($decoded) ? ($decoded['error']['message'] ?? $raw) : (string) $raw;
            error_log('Google Calendar API ' . $method . ' ' . $code . ': ' . substr((string) $message, 0, 500));
            throw new \moodle_exception('googleapierror', 'local_iiidem_coursecalendar', '', s(shorten_text((string) $message, 300)));
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * OAuth access token via service account JWT.
     */
    private function get_access_token(): string {
        $creds = $this->load_credentials();
        $now = time();

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        if (!empty($creds['private_key_id'])) {
            $header['kid'] = $creds['private_key_id'];
        }

        $claims = [
            'iss' => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/calendar.events',
            'aud' => $creds['token_uri'] ?: self::TOKEN_URI,
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $impersonate = trim((string) get_config('local_iiidem_coursecalendar', 'google_impersonate_email'));
        if ($impersonate !== '' && validate_email($impersonate)) {
            $claims['sub'] = $impersonate;
        }

        $jwt = $this->build_jwt($header, $claims, $creds['private_key']);
        $tokenuri = $creds['token_uri'] ?: self::TOKEN_URI;
        $body = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ], '', '&', PHP_QUERY_RFC3986);

        // Prefer native cURL — Moodle's curl helper has broken this OAuth request with "Bad Request".
        if (function_exists('curl_init')) {
            $ch = curl_init($tokenuri);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_TIMEOUT => 30,
            ]);
            $response = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            if ($errno) {
                throw new \moodle_exception('googleapitokenfailed', 'local_iiidem_coursecalendar', '', $error);
            }
        } else {
            global $CFG;
            require_once($CFG->libdir . '/filelib.php');
            $curl = new \curl();
            $curl->setHeader(['Content-Type: application/x-www-form-urlencoded']);
            $response = $curl->post($tokenuri, $body);
        }

        $data = json_decode((string) $response, true);
        if (empty($data['access_token'])) {
            $err = is_array($data) ? ($data['error_description'] ?? $data['error'] ?? $response) : $response;
            throw new \moodle_exception('googleapitokenfailed', 'local_iiidem_coursecalendar', '', $err);
        }

        return (string) $data['access_token'];
    }

    /**
     * @return array{client_email:string,private_key:string,private_key_id:string,token_uri:string}
     */
    private function load_credentials(): array {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $json = trim((string) get_config('local_iiidem_coursecalendar', 'google_service_account_json'));
        if ($json === '') {
            throw new \moodle_exception('googleapinotconfigured', 'local_iiidem_coursecalendar');
        }

        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
            throw new \moodle_exception('googleapicredentialsinvalid', 'local_iiidem_coursecalendar');
        }

        $privatekey = (string) $data['private_key'];
        // Moodle textarea sometimes stores literal \n sequences.
        if (strpos($privatekey, "\n") === false && strpos($privatekey, '\\n') !== false) {
            $privatekey = str_replace('\\n', "\n", $privatekey);
        }

        $this->credentials = [
            'client_email' => (string) $data['client_email'],
            'private_key' => $privatekey,
            'private_key_id' => (string) ($data['private_key_id'] ?? ''),
            'token_uri' => (string) ($data['token_uri'] ?? self::TOKEN_URI),
        ];

        return $this->credentials;
    }

    /**
     * @param array<string, mixed> $header
     * @param array<string, mixed> $claims
     */
    private function build_jwt(array $header, array $claims, string $privatekey): string {
        $input = self::base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES))
            . '.' . self::base64url_encode(json_encode($claims, JSON_UNESCAPED_SLASHES));

        $key = openssl_pkey_get_private($privatekey);
        if ($key === false) {
            throw new \moodle_exception('googleapicredentialsinvalid', 'local_iiidem_coursecalendar');
        }

        $signature = '';
        if (!openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \moodle_exception('googleapicredentialsinvalid', 'local_iiidem_coursecalendar');
        }

        return $input . '.' . self::base64url_encode($signature);
    }

    private static function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function format_rfc3339(int $timestamp, string $timezone): string {
        $dt = new \DateTime('@' . $timestamp);
        $dt->setTimezone(new \DateTimeZone($timezone));
        return $dt->format('c');
    }
}
