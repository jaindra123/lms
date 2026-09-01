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
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

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
     *
     * @param int $from Unix start (optional, helps find ended instances)
     * @param int $to Unix end (optional)
     */
    public static function resolve_meeting_id(
        ?string $meetingid,
        ?string $meetingnumber,
        ?string $joinurl = '',
        int $from = 0,
        int $to = 0
    ): string {
        $meetingid = trim((string) $meetingid);
        if ($meetingid !== '') {
            return $meetingid;
        }

        $meetingnumber = preg_replace('/\D+/', '', (string) $meetingnumber);
        // Webex meeting numbers are typically 9–11 digits. Reject longer junk from MTID hashes.
        if ($meetingnumber !== '' && (strlen($meetingnumber) < 9 || strlen($meetingnumber) > 11)) {
            $meetingnumber = '';
        }

        $joinurl = trim((string) $joinurl);
        $queries = [];

        // Prefer webLink for personal-room / j.php?MTID= joins (digit scraping from MTID is wrong).
        if ($joinurl !== '' && preg_match('#/j\.php\?MTID=#i', $joinurl)) {
            $queries[] = ['webLink' => $joinurl];
        }
        if ($meetingnumber !== '') {
            $queries[] = ['meetingNumber' => $meetingnumber];
        }
        if ($joinurl !== '') {
            $queries[] = ['webLink' => $joinurl];
        }

        // Ended instances: list actual meetings in the class time window.
        if ($from > 0 || $to > 0) {
            $range = [
                'meetingType' => 'meeting',
                'max' => 50,
            ];
            if ($from > 0) {
                $range['from'] = gmdate('Y-m-d\TH:i:s\Z', max(0, $from - DAYSECS));
            }
            if ($to > 0) {
                $range['to'] = gmdate('Y-m-d\TH:i:s\Z', $to + DAYSECS);
            }
            if ($meetingnumber !== '') {
                $queries[] = $range + ['meetingNumber' => $meetingnumber];
            }
            $queries[] = $range;
        }

        $seen = [];
        foreach ($queries as $query) {
            $key = md5(json_encode($query));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            try {
                $data = self::request('GET', '/meetings', $query);
            } catch (\Throwable $e) {
                // Try next strategy (wrong meetingNumber often returns 404).
                continue;
            }

            $items = $data['items'] ?? [];
            // Prefer an instance id (contains _I_) when present.
            $fallback = '';
            foreach ($items as $item) {
                if (empty($item['id'])) {
                    continue;
                }
                $id = (string) $item['id'];
                if ($joinurl !== '' && !empty($item['webLink'])) {
                    // Exact join URL match wins.
                    if (strcasecmp(rtrim($item['webLink'], '/'), rtrim($joinurl, '/')) === 0) {
                        return $id;
                    }
                }
                if (strpos($id, '_I_') !== false) {
                    return $id;
                }
                if ($fallback === '') {
                    $fallback = $id;
                }
            }
            if ($fallback !== '') {
                return $fallback;
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
                $joined = 0;
                $left = 0;
                // Prefer join/leave timestamps (seconds). API "duration" is also seconds when set.
                if (!empty($item['joinedTime'])) {
                    $joined = (int) strtotime((string) $item['joinedTime']);
                }
                if (!empty($item['leftTime'])) {
                    $left = (int) strtotime((string) $item['leftTime']);
                }
                if ($joined && $left && $left >= $joined) {
                    $duration = $left - $joined;
                }
                if ($duration <= 0 && isset($item['duration'])) {
                    $duration = (int) $item['duration'];
                }
                // Some payloads omit email on host but include it under devices.
                if ($email === '' && !empty($item['devices']) && is_array($item['devices'])) {
                    foreach ($item['devices'] as $device) {
                        $demail = strtolower(trim((string) ($device['email'] ?? '')));
                        if ($demail !== '') {
                            $email = $demail;
                            break;
                        }
                    }
                }
                if ($email === '' && empty($item['displayName'])) {
                    continue;
                }
                $key = $email !== '' ? $email : ('name:' . strtolower(trim((string) $item['displayName'])));
                // Aggregate multiple join segments for same person.
                if (isset($out[$key])) {
                    $out[$key]['duration'] += $duration;
                    if ($joined > 0 && ($out[$key]['firstjoined'] === 0 || $joined < $out[$key]['firstjoined'])) {
                        $out[$key]['firstjoined'] = $joined;
                    }
                    if ($left > $out[$key]['lastleft']) {
                        $out[$key]['lastleft'] = $left;
                    }
                    continue;
                }
                $out[$key] = [
                    'email' => $email,
                    'displayName' => (string) ($item['displayName'] ?? ''),
                    'duration' => $duration,
                    'firstjoined' => $joined,
                    'lastleft' => $left,
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
