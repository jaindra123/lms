<?php
namespace local_iiidem_coursecalendar;

defined('MOODLE_INTERNAL') || die();

/**
 * Course Google Calendar storage, fetch, and notifications.
 */
class manager {

    public const TABLE = 'local_iiidem_coursecalendar';

    public static function get_by_course(int $courseid): ?\stdClass {
        global $DB;
        return $DB->get_record(self::TABLE, ['courseid' => $courseid]) ?: null;
    }

    /**
     * Normalize a Google Calendar URL into iCal and embed URLs.
     *
     * @return array{icalurl:string,embedurl:string,calendarid:string}|null
     */
    public static function normalize_google_url(string $url): ?array {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        if (!preg_match('#calendar\.google\.com#i', $url)) {
            return null;
        }

        $calendarid = null;

        if (preg_match('#/ical/([^/]+)/#i', $url, $m)) {
            $calendarid = rawurldecode($m[1]);
        } else if (preg_match('/[?&]src=([^&]+)/i', $url, $m)) {
            $calendarid = urldecode($m[1]);
        } else if (preg_match('/[?&]cid=([^&]+)/i', $url, $m)) {
            $decoded = base64_decode(urldecode($m[1]), true);
            if ($decoded !== false && strpos($decoded, '@') !== false) {
                $calendarid = $decoded;
            }
        }

        if ($calendarid === null || $calendarid === '') {
            return null;
        }

        $encoded = rawurlencode($calendarid);
        return [
            'calendarid' => $calendarid,
            'icalurl' => 'https://calendar.google.com/calendar/ical/' . $encoded . '/public/basic.ics',
            'embedurl' => 'https://calendar.google.com/calendar/embed?src=' . rawurlencode($calendarid),
        ];
    }

    /**
     * Fetch iCal content from a public feed.
     */
    public static function fetch_ical(string $icalurl): ?string {
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 15,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_FOLLOWLOCATION' => 1,
            'CURLOPT_MAXREDIRS' => 5,
        ]);

        $content = $curl->get($icalurl);
        $info = $curl->get_info();
        $code = (int) ($info['http_code'] ?? 0);

        if ($content === false || $code < 200 || $code >= 400) {
            return null;
        }

        if (stripos($content, 'BEGIN:VCALENDAR') === false) {
            return null;
        }

        return $content;
    }

    /**
     * @return array<int, array{uid:string,summary:string,start:int,end:int,allday:bool}>
     */
    public static function get_upcoming_events(?\stdClass $record, int $limit = 10): array {
        if (!$record || empty($record->icalurl)) {
            return [];
        }

        $ical = self::fetch_ical($record->icalurl);
        if ($ical === null) {
            return [];
        }

        return ical_parser::parse_events($ical, $limit);
    }

    public static function save_calendar(int $courseid, string $inputurl, int $userid): \stdClass {
        global $DB;

        $normalized = self::normalize_google_url($inputurl);
        if ($normalized === null) {
            throw new \moodle_exception('invalidcalendarurl', 'local_iiidem_coursecalendar');
        }

        $ical = self::fetch_ical($normalized['icalurl']);
        if ($ical === null) {
            throw new \moodle_exception('invalidcalendarurl', 'local_iiidem_coursecalendar');
        }

        $now = time();
        $existing = self::get_by_course($courseid);
        $isupdate = (bool) $existing;

        $record = (object) [
            'courseid' => $courseid,
            'calendarurl' => $inputurl,
            'icalurl' => $normalized['icalurl'],
            'embedurl' => $normalized['embedurl'],
            'eventhash' => ical_parser::events_hash($ical),
            'userid' => $userid,
            'timemodified' => $now,
            'timenotified' => $now,
        ];

        if ($existing) {
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            $DB->update_record(self::TABLE, $record);
        } else {
            $record->timecreated = $now;
            $record->id = $DB->insert_record(self::TABLE, $record);
        }

        $course = get_course($courseid);
        $events = ical_parser::parse_events($ical, 8);
        $bodykey = $isupdate ? 'notificationbodyupdate' : 'notificationbody';
        self::notify_enrolled_students($course, $record, $events, $isupdate, $userid, $bodykey);

        return $record;
    }

    public static function delete_calendar(int $courseid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['courseid' => $courseid]);
    }

    /**
     * Notify enrolled students (excluding the editor).
     *
     * @param array<int, array{uid:string,summary:string,start:int,end:int,allday:bool}> $events
     */
    public static function notify_enrolled_students(
        \stdClass $course,
        \stdClass $record,
        array $events,
        bool $isupdate = false,
        ?int $excludeuserid = null,
        string $bodykey = 'notificationbody'
    ): void {
        global $CFG;

        $context = \context_course::instance($course->id);
        $users = get_enrolled_users($context, '', 0, 'u.*', null, 0, 0, true);

        if (empty($users)) {
            return;
        }

        $eventlines = self::format_events_for_message($events);
        $courseurl = new \moodle_url('/course/view.php', ['id' => $course->id]);
        $calendarurl = $record->embedurl ?: $record->calendarurl;

        $a = (object) [
            'coursename' => format_string($course->fullname, true, ['context' => $context]),
            'events' => $eventlines !== '' ? $eventlines : get_string('noevents', 'local_iiidem_coursecalendar'),
            'courseurl' => $courseurl->out(false),
            'calendarurl' => $calendarurl,
        ];

        $subject = get_string('notificationsubject', 'local_iiidem_coursecalendar', $a->coursename);
        $body = get_string($bodykey, 'local_iiidem_coursecalendar', $a);

        $sender = \core_user::get_noreply_user();

        foreach ($users as $user) {
            if ($excludeuserid && (int) $user->id === (int) $excludeuserid) {
                continue;
            }
            if (isguestuser($user) || $user->deleted || $user->suspended) {
                continue;
            }
            if (has_capability('local/iiidem_coursecalendar:manage', $context, $user)) {
                continue;
            }

            $eventdata = (object) [
                'component' => 'local_iiidem_coursecalendar',
                'name' => 'schedule',
                'userfrom' => $sender,
                'userto' => $user,
                'subject' => $subject,
                'fullmessage' => $body,
                'fullmessageformat' => FORMAT_PLAIN,
                'fullmessagehtml' => '',
                'smallmessage' => $subject,
                'notification' => 1,
                'contexturl' => $courseurl->out(false),
                'contexturlname' => $a->coursename,
            ];
            message_send($eventdata);

            if (!empty($user->email)) {
                email_to_user($user, $sender, $subject, $body);
            }
        }
    }

    /**
     * @param array<int, array{uid:string,summary:string,start:int,end:int,allday:bool}> $events
     */
    public static function format_events_for_message(array $events): string {
        $lines = [];
        foreach ($events as $event) {
            $datestr = self::format_event_datetime($event);
            $summary = $event['summary'] !== '' ? $event['summary'] : '-';
            $lines[] = '• ' . $datestr . ' — ' . $summary;
        }
        return implode("\n", $lines);
    }

    /**
     * @param array{summary:string,start:int,end:int,allday:bool} $event
     */
    public static function format_event_datetime(array $event): string {
        if (!empty($event['allday'])) {
            return userdate($event['start'], get_string('strftimedatefullshort', 'langconfig'));
        }
        return userdate($event['start'], get_string('strftimedatetimeshort', 'langconfig'));
    }

    /**
     * Context for course page Mustache partial.
     *
     * @return array<string, mixed>|null
     */
    public static function get_course_display_context(int $courseid): ?array {
        $record = self::get_by_course($courseid);
        if (!$record) {
            return null;
        }

        $events = self::get_upcoming_events($record, 8);
        $formatted = [];
        foreach ($events as $event) {
            $formatted[] = [
                'summary' => $event['summary'],
                'date' => self::format_event_datetime($event),
            ];
        }

        return [
            'has_calendar' => true,
            'has_events' => !empty($formatted),
            'events' => $formatted,
            'embedurl' => $record->embedurl,
            'heading' => get_string('scheduleheading', 'local_iiidem_coursecalendar'),
            'intro' => get_string('scheduleintro', 'local_iiidem_coursecalendar'),
            'viewfullcalendar' => get_string('viewfullcalendar', 'local_iiidem_coursecalendar'),
            'noevents' => get_string('noevents', 'local_iiidem_coursecalendar'),
        ];
    }

    /**
     * Cron: detect new/changed events and notify students.
     */
    public static function sync_all_calendars(): void {
        global $DB;

        $records = $DB->get_records(self::TABLE);
        foreach ($records as $record) {
            $ical = self::fetch_ical($record->icalurl);
            if ($ical === null) {
                continue;
            }

            $newhash = ical_parser::events_hash($ical);
            if (!empty($record->eventhash) && hash_equals($record->eventhash, $newhash)) {
                continue;
            }

            $course = $DB->get_record('course', ['id' => $record->courseid], '*', IGNORE_MISSING);
            if (!$course) {
                continue;
            }

            $events = ical_parser::parse_events($ical, 8);
            self::notify_enrolled_students($course, $record, $events, true, null, 'notificationbodynewevents');

            $record->eventhash = $newhash;
            $record->timemodified = time();
            $record->timenotified = time();
            $DB->update_record(self::TABLE, $record);
        }
    }
}
