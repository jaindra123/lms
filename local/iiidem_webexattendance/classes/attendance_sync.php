<?php
namespace local_iiidem_webexattendance;

defined('MOODLE_INTERNAL') || die();

/**
 * Create attendance sessions and sync Webex participants into Present/Late/Absent.
 */
class attendance_sync {

    public const TABLE = 'local_iiidem_webexatt';
    public const STATUS_PENDING = 'pending';
    public const STATUS_SYNCED = 'synced';
    public const STATUS_ERROR = 'error';
    public const STATUS_SKIPPED = 'skipped';

    public static function is_enabled(): bool {
        return (bool) get_config('local_iiidem_webexattendance', 'enabled');
    }

    public static function autosync_enabled(): bool {
        return (bool) get_config('local_iiidem_webexattendance', 'autosync');
    }

    /**
     * Handle webexactivity or webex URL course module create/update.
     */
    public static function sync_from_cm(int $cmid): void {
        global $DB;

        if (!self::autosync_enabled()) {
            return;
        }

        $cm = get_coursemodule_from_id(null, $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $info = self::extract_webex_info($cm);
        if ($info === null) {
            return;
        }

        $now = time();
        $existing = $DB->get_record(self::TABLE, ['cmid' => $cmid]);

        $attendancecm = self::ensure_attendance_activity((int) $cm->course);
        if (!$attendancecm) {
            return;
        }

        $att = self::load_attendance_structure($attendancecm);
        if (!$att) {
            return;
        }

        $sessionname = 'Webex: ' . format_string($info['name']);
        $sessionid = $existing ? (int) $existing->sessionid : 0;

        if ($sessionid > 0 && $DB->record_exists('attendance_sessions', ['id' => $sessionid])) {
            $sess = $DB->get_record('attendance_sessions', ['id' => $sessionid], '*', MUST_EXIST);
            $sess->sessdate = $info['start'];
            $sess->duration = max(60, $info['end'] - $info['start']);
            $sess->description = $sessionname;
            $sess->timemodified = $now;
            $DB->update_record('attendance_sessions', $sess);
        } else {
            $sess = new \stdClass();
            $sess->sessdate = $info['start'];
            $sess->duration = max(60, $info['end'] - $info['start']);
            $sess->descriptionitemid = 0;
            $sess->description = $sessionname;
            $sess->descriptionformat = FORMAT_PLAIN;
            $sess->calendarevent = 0;
            $sess->timemodified = $now;
            $sess->studentscanmark = 0;
            $sess->allowupdatestatus = 0;
            $sess->autoassignstatus = 0;
            $sess->subnet = $att->subnet ?? '';
            $sess->studentpassword = '';
            $sess->automark = 0;
            $sess->automarkcompleted = 0;
            $sess->absenteereport = (int) get_config('attendance', 'absenteereport_default');
            $sess->includeqrcode = 0;
            $sess->statusset = 0;
            $sess->groupid = 0;
            $sess->automarkcmid = 0;
            $sess->studentsearlyopentime = (int) get_config('attendance', 'studentsearlyopentime');
            $sessionid = (int) $att->add_session($sess);
        }

        $record = (object) [
            'courseid' => (int) $cm->course,
            'cmid' => $cmid,
            'modulename' => $cm->modname,
            'attendancecmid' => (int) $attendancecm->id,
            'sessionid' => $sessionid,
            'meetingid' => $info['meetingid'],
            'meetingnumber' => $info['meetingnumber'],
            'joinurl' => $info['joinurl'],
            'sessionname' => $sessionname,
            'starttime' => $info['start'],
            'endtime' => $info['end'],
            'status' => self::STATUS_PENDING,
            'lastsync' => 0,
            'syncmessage' => null,
            'recordingstatus' => self::STATUS_PENDING,
            'recordingurl' => null,
            'classvideoid' => 0,
            'recordinglastsync' => 0,
            'recordingmessage' => null,
            'timemodified' => $now,
        ];

        if ($existing) {
            $record->id = $existing->id;
            // Do not reset a completed recording import when the CM is edited.
            if (!empty($existing->recordingstatus) && $existing->recordingstatus === self::STATUS_SYNCED) {
                $record->recordingstatus = $existing->recordingstatus;
                $record->recordingurl = $existing->recordingurl;
                $record->classvideoid = $existing->classvideoid;
                $record->recordinglastsync = $existing->recordinglastsync;
                $record->recordingmessage = $existing->recordingmessage;
            }
            $DB->update_record(self::TABLE, $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record(self::TABLE, $record);
        }
    }

    public static function delete_for_cm(int $cmid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
    }

    /**
     * Sync ended pending meetings from Webex into attendance logs.
     */
    public static function sync_due_meetings(int $limit = 20): int {
        global $DB;

        if (!self::is_enabled() || !oauth::is_connected()) {
            return 0;
        }

        $grace = max(5, (int) get_config('local_iiidem_webexattendance', 'graceafterend')) * MINSECS;
        $cutoff = time() - $grace;

        $records = $DB->get_records_select(
            self::TABLE,
            "status = ? AND endtime > 0 AND endtime <= ?",
            [self::STATUS_PENDING, $cutoff],
            'endtime ASC',
            '*',
            0,
            $limit
        );

        $count = 0;
        foreach ($records as $record) {
            try {
                self::sync_one($record);
                $count++;
            } catch (\Throwable $e) {
                $record->status = self::STATUS_ERROR;
                $record->lastsync = time();
                $record->syncmessage = $e->getMessage();
                $record->timemodified = time();
                $DB->update_record(self::TABLE, $record);
            }
        }
        return $count;
    }

    public static function sync_one(\stdClass $record): void {
        global $DB, $USER;

        $meetingid = api::resolve_meeting_id($record->meetingid, $record->meetingnumber, $record->joinurl ?? '');
        if ($meetingid === '') {
            throw new \moodle_exception('oauth_error', 'local_iiidem_webexattendance', '', 'Could not resolve Webex meeting id');
        }

        $participants = api::list_participants($meetingid);
        $presentmin = max(1, (int) get_config('local_iiidem_webexattendance', 'presentminutes'));
        $latemin = max(0, (int) get_config('local_iiidem_webexattendance', 'lateminutes'));
        if ($latemin > $presentmin) {
            $latemin = $presentmin;
        }

        $cm = get_coursemodule_from_id('attendance', (int) $record->attendancecmid, 0, false, MUST_EXIST);
        $att = self::load_attendance_structure($cm);
        if (!$att) {
            throw new \moodle_exception('oauth_error', 'local_iiidem_webexattendance', '', 'Attendance activity missing');
        }

        $statuses = attendance_get_statuses($att->id, true, 0);
        $by = [];
        foreach ($statuses as $st) {
            $by[strtoupper($st->acronym)] = (int) $st->id;
        }
        if (empty($by['P']) || empty($by['A'])) {
            throw new \moodle_exception('oauth_error', 'local_iiidem_webexattendance', '', 'Attendance statuses P/A not found');
        }

        $emailmap = [];
        foreach ($participants as $p) {
            if ($p['email'] !== '') {
                $emailmap[$p['email']] = (int) $p['duration'];
            }
        }

        $context = \context_course::instance((int) $record->courseid);
        $students = get_enrolled_users($context, 'mod/attendance:canbelisted', 0, 'u.*', null, 0, 0, true);

        $now = time();
        $takenby = (isloggedin() && !isguestuser()) ? (int) $USER->id : get_admin()->id;
        $statusset = implode(',', array_map('intval', array_keys($statuses)));
        $sesslog = [];
        $marked = 0;

        foreach ($students as $student) {
            $email = strtolower(trim($student->email));
            $seconds = $emailmap[$email] ?? 0;
            $minutes = (int) floor($seconds / 60);

            if ($minutes >= $presentmin) {
                $statusid = $by['P'];
            } else if ($minutes >= $latemin && !empty($by['L'])) {
                $statusid = $by['L'];
            } else {
                $statusid = $by['A'];
            }

            $log = new \stdClass();
            $log->studentid = (int) $student->id;
            $log->statusid = $statusid;
            $log->statusset = $statusset;
            $log->remarks = 'Webex sync: ' . $minutes . ' min';
            $log->sessionid = (int) $record->sessionid;
            $log->timetaken = $now;
            $log->takenby = $takenby;
            $sesslog[$student->id] = $log;
            $marked++;
        }

        $att->pageparams = (object) [
            'sessionid' => (int) $record->sessionid,
            'grouptype' => 0,
        ];
        if ($sesslog) {
            $att->save_log($sesslog);
        }

        $record->meetingid = $meetingid;
        $record->status = self::STATUS_SYNCED;
        $record->lastsync = $now;
        $record->syncmessage = 'Marked ' . $marked . ' students from ' . count($participants) . ' Webex participants';
        $record->timemodified = $now;
        $DB->update_record(self::TABLE, $record);
    }

    /**
     * @return array{name:string,start:int,end:int,meetingid:string,meetingnumber:string,joinurl:string}|null
     */
    protected static function extract_webex_info(\stdClass $cm): ?array {
        global $DB, $CFG;

        if ($cm->modname === 'webexactivity') {
            $webex = $DB->get_record('webexactivity', ['id' => $cm->instance]);
            if (!$webex || empty($webex->starttime)) {
                return null;
            }
            $start = (int) $webex->starttime;
            $duration = !empty($webex->duration) ? (int) $webex->duration : 3600;
            if ($duration < 300) {
                // Some stores minutes.
                $duration = $duration * MINSECS;
            }
            $end = !empty($webex->endtime) ? (int) $webex->endtime : ($start + $duration);
            return [
                'name' => $webex->name,
                'start' => $start,
                'end' => $end,
                'meetingid' => '',
                'meetingnumber' => (string) ($webex->meetingkey ?? ''),
                'joinurl' => (string) ($webex->meetinglink ?? ''),
            ];
        }

        if ($cm->modname === 'url') {
            require_once($CFG->dirroot . '/local/iiidem_coursecalendar/classes/manager.php');
            $url = $DB->get_record('url', ['id' => $cm->instance]);
            if (!$url || empty($url->externalurl)) {
                return null;
            }
            if (!\local_iiidem_coursecalendar\manager::is_webex_join_url($url->externalurl)) {
                return null;
            }

            $start = 0;
            $end = 0;
            // Prefer Restrict access date window when present.
            if (!empty($cm->availability)) {
                $dates = self::parse_availability_dates((string) $cm->availability);
                $start = $dates['from'];
                $end = $dates['until'];
            }
            if ($start <= 0 && !empty($cm->completionexpected)) {
                $start = (int) $cm->completionexpected;
            }
            if ($start <= 0) {
                $start = time();
            }
            $mins = (int) get_config('local_iiidem_coursecalendar', 'urlliveduration');
            if ($mins < 1) {
                $mins = 60;
            }
            if ($end <= 0) {
                $end = $start + ($mins * MINSECS);
            }
            if ($end <= $start) {
                $end = $start + ($mins * MINSECS);
            }

            $meetingnumber = '';
            if (preg_match('/(\d{9,12})/', $url->externalurl, $m)) {
                $meetingnumber = $m[1];
            }
            // Also try description for "Meeting Number: 2513 988 6477".
            if ($meetingnumber === '' && !empty($url->intro)) {
                if (preg_match('/(\d{3,4}\s*\d{3,4}\s*\d{3,4})/', strip_tags($url->intro), $m2)) {
                    $meetingnumber = preg_replace('/\D+/', '', $m2[1]);
                }
            }
            return [
                'name' => $url->name,
                'start' => $start,
                'end' => $end,
                'meetingid' => '',
                'meetingnumber' => $meetingnumber,
                'joinurl' => $url->externalurl,
            ];
        }

        return null;
    }

    /**
     * Read from/until timestamps from Moodle availability JSON.
     *
     * @return array{from:int,until:int}
     */
    protected static function parse_availability_dates(string $availabilityjson): array {
        $from = 0;
        $until = 0;
        $data = json_decode($availabilityjson, true);
        if (!is_array($data)) {
            return ['from' => 0, 'until' => 0];
        }
        $stack = [$data];
        while ($stack) {
            $node = array_pop($stack);
            if (!is_array($node)) {
                continue;
            }
            if (($node['type'] ?? '') === 'date' && isset($node['t'], $node['d'])) {
                $t = (int) $node['t'];
                if ($node['d'] === '>=' || $node['d'] === '>') {
                    $from = $from ? max($from, $t) : $t;
                } else if ($node['d'] === '<' || $node['d'] === '<=') {
                    $until = $until ? min($until, $t) : $t;
                }
            }
            if (!empty($node['c']) && is_array($node['c'])) {
                foreach ($node['c'] as $child) {
                    $stack[] = $child;
                }
            }
        }
        return ['from' => $from, 'until' => $until];
    }

    /**
     * Import Webex recording playback links into Class videos after meetings end.
     */
    public static function import_due_recordings(int $limit = 20): int {
        global $DB, $CFG;

        if (!self::recordings_enabled() || !oauth::is_connected()) {
            return 0;
        }
        if (!file_exists($CFG->dirroot . '/local/iiidem_classvideos/classes/manager.php')) {
            mtrace('Webex recordings: local_iiidem_classvideos not installed.');
            return 0;
        }

        $grace = max(15, (int) get_config('local_iiidem_webexattendance', 'recordinggraceafterend')) * MINSECS;
        $maxage = max(1, (int) get_config('local_iiidem_webexattendance', 'recordingmaxdays')) * DAYSECS;
        $cutoff = time() - $grace;
        $oldest = time() - $maxage;

        $records = $DB->get_records_select(
            self::TABLE,
            "recordingstatus = ? AND endtime > 0 AND endtime <= ? AND endtime >= ?",
            [self::STATUS_PENDING, $cutoff, $oldest],
            'endtime ASC',
            '*',
            0,
            $limit
        );

        $count = 0;
        foreach ($records as $record) {
            try {
                if (self::import_one_recording($record)) {
                    $count++;
                }
            } catch (\Throwable $e) {
                $record->recordingstatus = self::STATUS_ERROR;
                $record->recordinglastsync = time();
                $record->recordingmessage = $e->getMessage();
                $record->timemodified = time();
                $DB->update_record(self::TABLE, $record);
            }
        }
        return $count;
    }

    public static function recordings_enabled(): bool {
        return (bool) get_config('local_iiidem_webexattendance', 'importrecordings');
    }

    /**
     * @return bool True when a class video was created.
     */
    public static function import_one_recording(\stdClass $record): bool {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/local/iiidem_classvideos/classes/manager.php');

        $meetingid = api::resolve_meeting_id($record->meetingid, $record->meetingnumber, $record->joinurl ?? '');
        if ($meetingid === '') {
            throw new \moodle_exception('oauth_error', 'local_iiidem_webexattendance', '', 'Could not resolve Webex meeting id for recording');
        }

        $from = max(0, (int) $record->starttime - DAYSECS);
        $to = max(time(), (int) $record->endtime + (2 * DAYSECS));
        $items = api::list_recordings($meetingid, $from, $to);
        $picked = api::pick_recording($items);

        if ($picked === null) {
            // Still processing — keep pending unless too old.
            $maxage = max(1, (int) get_config('local_iiidem_webexattendance', 'recordingmaxdays')) * DAYSECS;
            if ((int) $record->endtime < (time() - $maxage)) {
                $record->recordingstatus = self::STATUS_SKIPPED;
                $record->recordingmessage = 'No recording found within retry window';
            } else {
                $record->recordingmessage = 'No recording available yet (Webex still processing)';
            }
            $record->meetingid = $meetingid;
            $record->recordinglastsync = time();
            $record->timemodified = time();
            $DB->update_record(self::TABLE, $record);
            return false;
        }

        // Avoid duplicate class videos for the same CM.
        if (!empty($record->classvideoid) && $DB->record_exists('local_iiidem_classvideos', ['id' => $record->classvideoid])) {
            $record->recordingstatus = self::STATUS_SYNCED;
            $record->recordingurl = $picked['url'];
            $record->recordingmessage = 'Class video already linked';
            $record->recordinglastsync = time();
            $record->timemodified = time();
            $DB->update_record(self::TABLE, $record);
            return false;
        }

        $accesstype = get_config('local_iiidem_webexattendance', 'recordingaccesstype');
        if ($accesstype !== \local_iiidem_classvideos\manager::ACCESS_PUBLIC) {
            $accesstype = \local_iiidem_classvideos\manager::ACCESS_REQUEST;
        }

        $title = trim((string) $record->sessionname);
        if ($title === '') {
            $title = 'Webex recording';
        }
        if (stripos($title, 'recording') === false) {
            $title .= ' (Recording)';
        }
        if (\core_text::strlen($title) > 255) {
            $title = \core_text::substr($title, 0, 255);
        }

        $description = 'Imported automatically from Webex after the live class.';
        if ($picked['password'] !== '') {
            $description .= ' Recording password: ' . $picked['password'];
        }
        if ($picked['topic'] !== '') {
            $description .= ' Topic: ' . $picked['topic'];
        }

        $admin = get_admin();
        if (!$admin) {
            throw new \moodle_exception('oauth_error', 'local_iiidem_webexattendance', '', 'No admin user to own class video');
        }
        \core\session\manager::set_user($admin);
        $userid = (int) $admin->id;

        $videoid = \local_iiidem_classvideos\manager::create_video(
            (int) $record->courseid,
            $title,
            $description,
            $accesstype,
            $picked['url'],
            (int) $record->starttime,
            0,
            $userid
        );

        $record->meetingid = $meetingid;
        $record->recordingstatus = self::STATUS_SYNCED;
        $record->recordingurl = $picked['url'];
        $record->classvideoid = $videoid;
        $record->recordinglastsync = time();
        $record->recordingmessage = 'Created class video #' . $videoid;
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);
        return true;
    }

    protected static function ensure_attendance_activity(int $courseid): ?\stdClass {
        global $DB, $CFG;

        $modinfo = get_fast_modinfo($courseid);
        $instances = $modinfo->get_instances_of('attendance');
        if ($instances) {
            $cminfo = reset($instances);
            return get_coursemodule_from_id('attendance', (int) $cminfo->id, $courseid, false, MUST_EXIST);
        }

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');

        $module = $DB->get_record('modules', ['name' => 'attendance'], '*', MUST_EXIST);
        $course = get_course($courseid);

        $moduleinfo = new \stdClass();
        $moduleinfo->modulename = 'attendance';
        $moduleinfo->module = $module->id;
        $moduleinfo->name = 'Attendance';
        $moduleinfo->intro = 'Auto-created for Webex live class attendance';
        $moduleinfo->introformat = FORMAT_HTML;
        $moduleinfo->section = 0;
        $moduleinfo->visible = 1;
        $moduleinfo->course = $courseid;
        $moduleinfo->grade = 100;
        $moduleinfo->groupmode = 0;

        try {
            $cm = add_moduleinfo($moduleinfo, $course);
            return get_coursemodule_from_id('attendance', $cm->coursemodule, $courseid, false, MUST_EXIST);
        } catch (\Throwable $e) {
            debugging('Webex attendance: could not create attendance activity: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }

    protected static function load_attendance_structure(\stdClass $cm): ?\mod_attendance_structure {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/attendance/locallib.php');
        require_once($CFG->dirroot . '/mod/attendance/classes/structure.php');

        $attrecord = $DB->get_record('attendance', ['id' => $cm->instance], '*', IGNORE_MISSING);
        if (!$attrecord) {
            return null;
        }
        $course = get_course($cm->course);
        $context = \context_module::instance($cm->id);
        return new \mod_attendance_structure($attrecord, $cm, $course, $context, null);
    }
}
