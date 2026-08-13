<?php
namespace local_iiidem_webexattendance;

defined('MOODLE_INTERNAL') || die();

/**
 * Minimal Webex REST client for meetings + participants.
 */
class api {

    public const BASE = 'https://webexapis.com/v1';

    /**
     * @param string $method
     * @param string $path
     * @param array $query
     * @param array|null $body
     * @return array
     */
    public static function request(string $method, string $path, array $query = [], ?array $body = null): array {
        $token = oauth::get_access_token();
        $url = self::BASE . $path;
        if ($query) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $curl = new \curl();
        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        $curl->setHeader($headers);

        $method = strtoupper($method);
        if ($method === 'GET') {
            $raw = $curl->get($url);
        } else if ($method === 'POST') {
            $raw = $curl->post($url, $body ? json_encode($body) : '');
        } else {
            $raw = $curl->request($method, $url, $body ? json_encode($body) : '');
        }

        $info = $curl->get_info();
        $code = (int) ($info['http_code'] ?? 0);
        $data = json_decode((string) $raw, true);
        if ($code === 401) {
            // Force refresh once.
            set_config('token_expires', 0, 'local_iiidem_webexattendance');
            $token = oauth::get_access_token();
            $curl = new \curl();
            $curl->setHeader([
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ]);
            $raw = $curl->get($url);
            $info = $curl->get_info();
            $code = (int) ($info['http_code'] ?? 0);
            $data = json_decode((string) $raw, true);
        }

        if ($code < 200 || $code >= 300) {
            $msg = is_array($data) ? ($data['message'] ?? json_encode($data)) : (string) $raw;
            throw new \moodle_exception('oauth_error', 'local_iiidem_webexattendance', '', 'HTTP ' . $code . ': ' . $msg);
        }
        return is_array($data) ? $data : [];
    }

    /**
     * Resolve meeting UUID/id from meeting number or join URL if needed.
     */
    public static function resolve_meeting_id(?string $meetingid, ?string $meetingnumber, ?string $joinurl = ''): string {
        $meetingid = trim((string) $meetingid);
        if ($meetingid !== '') {
            return $meetingid;
        }

        $queries = [];
        $meetingnumber = preg_replace('/\D+/', '', (string) $meetingnumber);
        if ($meetingnumber !== '') {
            $queries[] = ['meetingNumber' => $meetingnumber];
        }
        $joinurl = trim((string) $joinurl);
        if ($joinurl !== '') {
            $queries[] = ['webLink' => $joinurl];
        }

        foreach ($queries as $query) {
            $data = self::request('GET', '/meetings', $query);
            $items = $data['items'] ?? [];
            foreach ($items as $item) {
                if (!empty($item['id'])) {
                    return (string) $item['id'];
                }
            }
        }
        return '';
    }

    /**
     * List participants with duration (seconds when available).
     *
     * @return array<int,array{email:string,displayName:string,duration:int}>
     */
    public static function list_participants(string $meetingid): array {
        $meetingid = trim($meetingid);
        if ($meetingid === '') {
            return [];
        }

        $out = [];
        $urlpath = '/meetingParticipants';
        $query = ['meetingId' => $meetingid, 'max' => 100];

        do {
            $data = self::request('GET', $urlpath, $query);
            foreach ($data['items'] ?? [] as $item) {
                $email = strtolower(trim((string) ($item['email'] ?? '')));
                $duration = 0;
                if (isset($item['duration'])) {
                    $duration = (int) $item['duration'];
                } else if (!empty($item['joinedTime']) && !empty($item['leftTime'])) {
                    $duration = max(0, strtotime($item['leftTime']) - strtotime($item['joinedTime']));
                }
                if ($email === '' && empty($item['displayName'])) {
                    continue;
                }
                // Aggregate multiple join segments for same email.
                if ($email !== '' && isset($out[$email])) {
                    $out[$email]['duration'] += $duration;
                    continue;
                }
                $key = $email !== '' ? $email : ('name:' . strtolower(trim((string) $item['displayName'])));
                $out[$key] = [
                    'email' => $email,
                    'displayName' => (string) ($item['displayName'] ?? ''),
                    'duration' => $duration,
                ];
            }

            $query = [];
            $urlpath = '';
            if (!empty($data['link']['next'])) {
                // Absolute next link — strip base.
                $next = $data['link']['next'];
                if (strpos($next, self::BASE) === 0) {
                    $urlpath = substr($next, strlen(self::BASE));
                }
            }
        } while ($urlpath !== '');

        return array_values($out);
    }

    /**
     * List recordings for a meeting (playback/download links).
     *
     * @return array<int,array{id:string,topic:string,playbackUrl:string,password:string,createTime:string}>
     */
    public static function list_recordings(string $meetingid, int $from = 0, int $to = 0): array {
        $meetingid = trim($meetingid);
        $query = ['max' => 50];
        if ($meetingid !== '') {
            $query['meetingId'] = $meetingid;
        }
        if ($from > 0) {
            $query['from'] = gmdate('Y-m-d\TH:i:s\Z', $from);
        }
        if ($to > 0) {
            $query['to'] = gmdate('Y-m-d\TH:i:s\Z', $to);
        }

        $out = [];
        $urlpath = '/recordings';
        $pagequery = $query;

        do {
            $data = self::request('GET', $urlpath, $pagequery);
            foreach ($data['items'] ?? [] as $item) {
                $playback = trim((string) ($item['playbackUrl'] ?? ''));
                if ($playback === '') {
                    $playback = trim((string) ($item['shareAndDownloadUrl'] ?? ''));
                }
                if ($playback === '') {
                    $playback = trim((string) ($item['downloadUrl'] ?? ''));
                }
                if ($playback === '') {
                    continue;
                }
                $out[] = [
                    'id' => (string) ($item['id'] ?? ''),
                    'topic' => (string) ($item['topic'] ?? ''),
                    'playbackUrl' => $playback,
                    'password' => (string) ($item['password'] ?? ''),
                    'createTime' => (string) ($item['createTime'] ?? $item['timeRecorded'] ?? ''),
                ];
            }

            $urlpath = '';
            $pagequery = [];
            if (!empty($data['link']['next'])) {
                $next = $data['link']['next'];
                if (strpos($next, self::BASE) === 0) {
                    $urlpath = substr($next, strlen(self::BASE));
                }
            }
        } while ($urlpath !== '');

        return $out;
    }

    /**
     * Best playback URL from a recordings list (newest first).
     *
     * @param array $recordings
     * @return array{url:string,topic:string,password:string}|null
     */
    public static function pick_recording(array $recordings): ?array {
        if (!$recordings) {
            return null;
        }
        // Prefer MP4-style playback URLs; list is usually newest-first from API.
        $first = $recordings[0];
        return [
            'url' => $first['playbackUrl'],
            'topic' => $first['topic'],
            'password' => $first['password'],
        ];
    }
}
