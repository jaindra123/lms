<?php
namespace local_iiidem_coursecalendar;

defined('MOODLE_INTERNAL') || die();

/**
 * Course Google Calendar storage, fetch, and notifications.
 */
class manager {

    public const TABLE = 'local_iiidem_coursecalendar';
    public const EVENT_TABLE = 'local_iiidem_coursecalendar_event';
    public const STATUS_SCHEDULED = 0;
    public const STATUS_CANCELLED = 1;

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

            $eventdata = new \core\message\message();
            $eventdata->component = 'local_iiidem_coursecalendar';
            $eventdata->name = 'schedule';
            $eventdata->userfrom = $sender;
            $eventdata->userto = $user;
            $eventdata->subject = $subject;
            $eventdata->fullmessage = $body;
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml = '';
            $eventdata->smallmessage = $subject;
            $eventdata->notification = 1;
            $eventdata->contexturl = $courseurl->out(false);
            $eventdata->contexturlname = $a->coursename;
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
        $liveclasses = self::get_upcoming_live_classes($courseid, 8);

        if (!$record && empty($liveclasses)) {
            return null;
        }

        $formatted = [];
        if ($record) {
            $events = self::get_upcoming_events($record, 8);
            foreach ($events as $event) {
                $formatted[] = [
                    'summary' => $event['summary'],
                    'date' => self::format_event_datetime($event),
                ];
            }
        }

        // API-scheduled live classes (Google Calendar invites).
        foreach ($liveclasses as $live) {
            $formatted[] = [
                'summary' => $live->summary,
                'date' => userdate($live->starttime, get_string('strftimedatetimeshort', 'langconfig')),
                'is_liveclass' => true,
            ];
        }
        usort($formatted, static function (array $a, array $b): int {
            return strcmp($a['date'], $b['date']);
        });
        $formatted = array_slice($formatted, 0, 8);

        return [
            'has_calendar' => true,
            'has_events' => !empty($formatted),
            'events' => $formatted,
            'embedurl' => $record->embedurl ?? '',
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

    /**
     * Enrolled student emails for Google Calendar attendees (excludes teachers/guests).
     *
     * @return string[]
     */
    public static function get_enrolled_student_emails(int $courseid, ?int $excludeuserid = null): array {
        $course = get_course($courseid);
        $context = \context_course::instance($courseid);
        $users = get_enrolled_users($context, '', 0, 'u.id, u.email, u.deleted, u.suspended', null, 0, 0, true);

        $emails = [];
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
            $email = trim(strtolower((string) $user->email));
            if ($email !== '' && validate_email($email)) {
                $emails[$email] = $email;
            }
        }

        return array_values($emails);
    }

    /**
     * Schedule a live class: Google Calendar event + DB record + Moodle notification.
     *
     * @param int $courseid
     * @param \stdClass $data Form data (summary, description, starttime, duration, location, sendgoogleinvites)
     * @param int $userid Teacher who scheduled
     * @return \stdClass Saved event record
     */
    public static function create_live_class(int $courseid, \stdClass $data, int $userid): \stdClass {
        global $DB;

        $course = get_course($courseid);
        $start = (int) $data->starttime;
        $duration = max(15, (int) ($data->duration ?? 60));
        $end = $start + ($duration * 60);
        $sendinvites = !empty($data->sendgoogleinvites);
        $attendees = self::get_enrolled_student_emails($courseid, $userid);

        $googleeventid = '';
        $googlelink = '';
        $invitessent = 0;

        if (google_calendar_client::is_configured()) {
            $client = new google_calendar_client();
            $google = $client->create_event([
                'summary' => trim($data->summary),
                'description' => trim((string) ($data->description ?? '')),
                'location' => trim((string) ($data->location ?? '')),
                'start' => $start,
                'end' => $end,
            ], $attendees, $sendinvites && !empty($attendees));

            $googleeventid = $google['id'];
            $googlelink = $google['htmlLink'] ?? '';
            $invitessent = ($sendinvites && !empty($attendees)) ? 1 : 0;
        } else if ($sendinvites) {
            throw new \moodle_exception('googleapinotconfigured', 'local_iiidem_coursecalendar');
        }

        $now = time();
        $record = (object) [
            'courseid' => $courseid,
            'googleeventid' => $googleeventid !== '' ? $googleeventid : null,
            'googlelink' => $googlelink !== '' ? $googlelink : null,
            'summary' => trim($data->summary),
            'description' => trim((string) ($data->description ?? '')),
            'location' => trim((string) ($data->location ?? '')),
            'starttime' => $start,
            'endtime' => $end,
            'attendeecount' => count($attendees),
            'invites_sent' => $invitessent,
            'userid' => $userid,
            'status' => self::STATUS_SCHEDULED,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record(self::EVENT_TABLE, $record);

        self::notify_live_class_scheduled($course, $record, $attendees, $userid);

        return $record;
    }

    /**
     * Whether Webex → Google Calendar auto-sync is enabled.
     */
    public static function is_webex_autosync_enabled(): bool {
        if (!google_calendar_client::is_configured()) {
            return false;
        }
        $setting = get_config('local_iiidem_coursecalendar', 'autowebexgoogle');
        // Default ON when setting not set yet.
        return $setting === false || $setting === null || (bool) $setting;
    }

    /**
     * Create/update Google Calendar event from a Webex or URL (Webex link) course module.
     *
     * @param int $cmid
     * @param int $userid
     * @param string $modname webexactivity|url
     * @return \stdClass|null
     */
    public static function sync_from_course_module(int $cmid, int $userid = 0, string $modname = ''): ?\stdClass {
        if ($modname === 'url') {
            return self::sync_from_url_cmid($cmid, $userid);
        }
        return self::sync_from_webex_cmid($cmid, $userid);
    }

    /**
     * URL activity with a Webex external URL + Timeline reminder date → Google invite.
     *
     * Uses course_modules.completionexpected as the session start (Set reminder in Timeline).
     */
    public static function sync_from_url_cmid(int $cmid, int $userid = 0): ?\stdClass {
        global $DB;

        if (!self::is_webex_autosync_enabled()) {
            return null;
        }

        $cm = $DB->get_record('course_modules', ['id' => $cmid], '*', IGNORE_MISSING);
        if (!$cm) {
            return null;
        }

        $module = $DB->get_record('modules', ['id' => $cm->module], 'name', IGNORE_MISSING);
        if (!$module || $module->name !== 'url') {
            return null;
        }

        $urlrec = $DB->get_record('url', ['id' => $cm->instance], '*', IGNORE_MISSING);
        if (!$urlrec) {
            return null;
        }

        $joinurl = trim((string) ($urlrec->externalurl ?? ''));
        if ($joinurl === '' || !self::is_webex_join_url($joinurl)) {
            // Not a Webex meeting URL — ignore normal URL resources.
            return null;
        }

        $start = (int) ($cm->completionexpected ?? 0);
        if ($start <= 0) {
            debugging(
                'local_iiidem_coursecalendar: URL Webex link saved but Timeline reminder date is empty — Google invite skipped',
                DEBUG_NORMAL
            );
            return null;
        }

        $duration = (int) get_config('local_iiidem_coursecalendar', 'urlliveduration');
        if ($duration < 15) {
            $duration = 60;
        }
        $end = $start + ($duration * 60);

        $description = trim(html_to_text($urlrec->intro ?? '', 0));
        $description .= ($description !== '' ? "\n\n" : '')
            . get_string('webexjoindescription', 'local_iiidem_coursecalendar', $joinurl);

        return self::upsert_google_event_for_cm([
            'courseid' => (int) $cm->course,
            'cmid' => $cmid,
            'webexid' => null,
            'summary' => format_string($urlrec->name),
            'description' => $description,
            'location' => $joinurl,
            'start' => $start,
            'end' => $end,
            'userid' => $userid,
            // Autosync on activity save: Google Calendar only. Skip Moodle email notify
            // (SMTP often unavailable locally/staging and floods the save page with notices).
            'notify' => false,
        ]);
    }

    /**
     * Whether a URL looks like a Webex join link.
     */
    public static function is_webex_join_url(string $url): bool {
        $url = strtolower(trim($url));
        return (bool) preg_match('#https?://[^/]*webex\.com/#i', $url);
    }

    /**
     * Create/update Google Calendar event from a Webex course module.
     *
     * @param int $cmid Course module id
     * @param int $userid Teacher / actor
     * @return \stdClass|null Linked calendar event record
     */
    public static function sync_from_webex_cmid(int $cmid, int $userid = 0): ?\stdClass {
        global $DB;

        if (!self::is_webex_autosync_enabled()) {
            return null;
        }

        $cm = get_coursemodule_from_id('webexactivity', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return null;
        }

        $webex = $DB->get_record('webexactivity', ['id' => $cm->instance], '*', IGNORE_MISSING);
        if (!$webex || empty($webex->starttime)) {
            return null;
        }

        $start = (int) $webex->starttime;
        $duration = max(15, (int) ($webex->duration ?: 60));
        $end = !empty($webex->endtime) ? (int) $webex->endtime : ($start + ($duration * 60));
        if ($end <= $start) {
            $end = $start + ($duration * 60);
        }

        $joinurl = trim((string) ($webex->meetinglink ?? ''));
        if ($joinurl === '') {
            $joinurl = (new \moodle_url('/mod/webexactivity/view.php', ['id' => $cmid]))->out(false);
        }

        $description = trim(html_to_text($webex->intro ?? '', 0));
        $description .= ($description !== '' ? "\n\n" : '')
            . get_string('webexjoindescription', 'local_iiidem_coursecalendar', $joinurl);

        return self::upsert_google_event_for_cm([
            'courseid' => (int) $webex->course,
            'cmid' => $cmid,
            'webexid' => (int) $webex->id,
            'summary' => format_string($webex->name),
            'description' => $description,
            'location' => $joinurl,
            'start' => $start,
            'end' => $end,
            'userid' => $userid,
            // Autosync on activity save: Google Calendar only (no Moodle email on every save).
            'notify' => false,
        ]);
    }

    /**
     * Shared Google create/update for a course module-linked live session.
     *
     * @param array{
     *   courseid:int,cmid:int,webexid?:?int,summary:string,description:string,
     *   location:string,start:int,end:int,userid:int,notify?:bool
     * } $data
     */
    protected static function upsert_google_event_for_cm(array $data): \stdClass {
        global $DB;

        $cmid = (int) $data['cmid'];
        $userid = (int) ($data['userid'] ?? 0);
        $attendees = self::get_enrolled_student_emails((int) $data['courseid'], $userid ?: null);
        $existing = $DB->get_record(self::EVENT_TABLE, [
            'cmid' => $cmid,
            'status' => self::STATUS_SCHEDULED,
        ]);

        $client = new google_calendar_client();
        $payload = [
            'summary' => $data['summary'],
            'description' => $data['description'],
            'location' => $data['location'],
            'start' => (int) $data['start'],
            'end' => (int) $data['end'],
        ];

        $now = time();
        if ($existing && !empty($existing->googleeventid)) {
            try {
                $google = $client->update_event((string) $existing->googleeventid, $payload, $attendees, true);
            } catch (\Throwable $e) {
                debugging('Google update failed, recreating: ' . $e->getMessage(), DEBUG_NORMAL);
                $google = $client->create_event($payload, $attendees, true);
            }
            $existing->googleeventid = $google['id'];
            $existing->googlelink = $google['htmlLink'] ?? $existing->googlelink;
            $existing->summary = $payload['summary'];
            $existing->description = $payload['description'];
            $existing->location = $payload['location'];
            $existing->starttime = $payload['start'];
            $existing->endtime = $payload['end'];
            $existing->attendeecount = count($attendees);
            $existing->invites_sent = (!empty($attendees) && empty($google['attendees_skipped'])) ? 1 : 0;
            $existing->webexid = $data['webexid'] ?? $existing->webexid;
            $existing->userid = $userid ?: (int) $existing->userid;
            $existing->timemodified = $now;
            $DB->update_record(self::EVENT_TABLE, $existing);
            if (!empty($data['notify'])) {
                try {
                    $course = get_course((int) $data['courseid']);
                    self::notify_live_class_scheduled($course, $existing, $attendees, $userid);
                } catch (\Throwable $e) {
                    error_log('local_iiidem_coursecalendar notify failed: ' . $e->getMessage());
                }
            }
            return $existing;
        }

        $google = $client->create_event($payload, $attendees, !empty($attendees));
        $record = (object) [
            'courseid' => (int) $data['courseid'],
            'cmid' => $cmid,
            'webexid' => $data['webexid'] ?? null,
            'googleeventid' => $google['id'],
            'googlelink' => $google['htmlLink'] ?? null,
            'summary' => $payload['summary'],
            'description' => $payload['description'],
            'location' => $payload['location'],
            'starttime' => $payload['start'],
            'endtime' => $payload['end'],
            'attendeecount' => count($attendees),
            'invites_sent' => (!empty($attendees) && empty($google['attendees_skipped'])) ? 1 : 0,
            'userid' => $userid,
            'status' => self::STATUS_SCHEDULED,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record(self::EVENT_TABLE, $record);

        if (!empty($data['notify'])) {
            try {
                $course = get_course((int) $data['courseid']);
                self::notify_live_class_scheduled($course, $record, $attendees, $userid);
            } catch (\Throwable $e) {
                error_log('local_iiidem_coursecalendar notify failed: ' . $e->getMessage());
            }
        }

        return $record;
    }

    /**
     * Cancel Google Calendar event linked to a deleted live-class CM (Webex or URL).
     */
    public static function cancel_by_webex_cmid(int $cmid): void {
        global $DB;

        $records = $DB->get_records(self::EVENT_TABLE, [
            'cmid' => $cmid,
            'status' => self::STATUS_SCHEDULED,
        ]);
        if (empty($records)) {
            return;
        }

        $client = google_calendar_client::is_configured() ? new google_calendar_client() : null;
        foreach ($records as $record) {
            if ($client && !empty($record->googleeventid)) {
                try {
                    // Short timeout: staging often cannot reach Google; never block CM delete.
                    $client->delete_event((string) $record->googleeventid, !empty($record->invites_sent), 8);
                } catch (\Throwable $e) {
                    // Log only — debugging() prints to the browser when debugdisplay is on
                    // and breaks Moodle's post-delete redirect (as seen on staging).
                    error_log('local_iiidem_coursecalendar: cancel Google event failed (cmid=' .
                        $cmid . '): ' . $e->getMessage());
                }
            }
            $record->status = self::STATUS_CANCELLED;
            $record->timemodified = time();
            $DB->update_record(self::EVENT_TABLE, $record);
        }
    }

    /**
     * @return \stdClass[]
     */
    public static function get_upcoming_live_classes(int $courseid, int $limit = 10): array {
        global $DB;

        return $DB->get_records_select(
            self::EVENT_TABLE,
            'courseid = ? AND status = ? AND starttime >= ?',
            [$courseid, self::STATUS_SCHEDULED, time() - DAYSECS],
            'starttime ASC',
            '*',
            0,
            $limit
        );
    }

    public static function cancel_live_class(int $eventid, int $userid): void {
        global $DB;

        $record = $DB->get_record(self::EVENT_TABLE, ['id' => $eventid], '*', MUST_EXIST);
        $context = \context_course::instance($record->courseid);
        require_capability('local/iiidem_coursecalendar:manage', $context);

        if ((int) $record->status === self::STATUS_CANCELLED) {
            return;
        }

        if (!empty($record->googleeventid) && google_calendar_client::is_configured()) {
            try {
                $client = new google_calendar_client();
                $client->delete_event((string) $record->googleeventid, !empty($record->invites_sent), 8);
            } catch (\Throwable $e) {
                error_log('local_iiidem_coursecalendar: cancel Google event failed (eventid=' .
                    $eventid . '): ' . $e->getMessage());
            }
        }

        $record->status = self::STATUS_CANCELLED;
        $record->timemodified = time();
        $DB->update_record(self::EVENT_TABLE, $record);
    }

    /**
     * Moodle notification when a live class is scheduled (backup if Google invites fail).
     */
    protected static function notify_live_class_scheduled(
        \stdClass $course,
        \stdClass $event,
        array $attendees,
        int $excludeuserid
    ): void {
        global $CFG;

        $context = \context_course::instance($course->id);
        $users = get_enrolled_users($context, '', 0, 'u.*', null, 0, 0, true);
        if (empty($users)) {
            return;
        }

        $courseurl = new \moodle_url('/course/view.php', ['id' => $course->id]);
        $when = userdate($event->starttime, get_string('strftimedatetimeshort', 'langconfig'));
        $a = (object) [
            'coursename' => format_string($course->fullname, true, ['context' => $context]),
            'title' => $event->summary,
            'when' => $when,
            'location' => $event->location ?: get_string('none'),
            'courseurl' => $courseurl->out(false),
            'googlelink' => $event->googlelink ?: '',
        ];

        $subject = get_string('liveclassnotificationsubject', 'local_iiidem_coursecalendar', $a);
        $body = get_string('liveclassnotificationbody', 'local_iiidem_coursecalendar', $a);
        $sender = \core_user::get_noreply_user();

        // Suppress Moodle debugging()/E_USER_NOTICE when email processor fails (no SMTP).
        global $CFG;
        $olddebug = $CFG->debug ?? 0;
        $olddebugdisplay = $CFG->debugdisplay ?? false;
        $CFG->debug = 0;
        $CFG->debugdisplay = false;

        try {
            foreach ($users as $user) {
                if ((int) $user->id === (int) $excludeuserid) {
                    continue;
                }
                if (isguestuser($user) || $user->deleted || $user->suspended) {
                    continue;
                }
                if (has_capability('local/iiidem_coursecalendar:manage', $context, $user)) {
                    continue;
                }

                try {
                    // Prefer direct email; message_send email-processor failures print Notices
                    // even when debugdisplay is off (trigger_error path in weblib.php).
                    if (!empty($user->email)) {
                        email_to_user($user, $sender, $subject, $body);
                    }
                } catch (\Throwable $e) {
                    error_log('local_iiidem_coursecalendar notify user ' . $user->id . ': ' . $e->getMessage());
                }
            }
        } finally {
            $CFG->debug = $olddebug;
            $CFG->debugdisplay = $olddebugdisplay;
        }
    }
}
