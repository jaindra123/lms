<?php
namespace local_iiidem_classvideos;

defined('MOODLE_INTERNAL') || die();

/**
 * Class video library: upload, access modes, requests, watch.
 */
class manager {

    public const ACCESS_PUBLIC = 'public';
    public const ACCESS_REQUEST = 'request';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const TABLE_VIDEO = 'local_iiidem_classvideos';
    public const TABLE_REQ = 'local_iiidem_classvideo_req';

    /** @var string[] */
    public const VIDEO_EXTS = ['.mp4', '.webm', '.mov', '.m4v'];

    /** Max upload size hint (200 MB) — still capped by Moodle/PHP limits. */
    public const MAX_UPLOAD_BYTES = 209715200;

    /**
     * Courses where the user can manage class videos.
     *
     * Uses capability lookup (not enrolment only) so teachers with a course
     * role assignment but no enrol record still get access.
     *
     * @return \stdClass[]
     */
    public static function get_manageable_courses(?int $userid = null): array {
        global $USER, $CFG;
        require_once($CFG->libdir . '/accesslib.php');

        $userid = $userid ?? (int) $USER->id;

        $courses = get_user_capability_course(
            'local/iiidem_classvideos:manage',
            $userid,
            true,
            'id, fullname, shortname, visible, category',
            'fullname ASC'
        );
        if (empty($courses)) {
            return [];
        }

        $out = [];
        foreach ($courses as $course) {
            if ((int) $course->id === SITEID) {
                continue;
            }
            $out[] = $course;
        }
        return $out;
    }

    /**
     * Courses where the user can see class video listings.
     *
     * @return \stdClass[]
     */
    public static function get_student_courses(?int $userid = null): array {
        global $USER, $CFG;
        require_once($CFG->libdir . '/accesslib.php');

        $userid = $userid ?? (int) $USER->id;

        $courses = get_user_capability_course(
            'local/iiidem_classvideos:view',
            $userid,
            true,
            'id, fullname, shortname, visible, category',
            'fullname ASC'
        );
        if (empty($courses)) {
            return [];
        }

        $out = [];
        foreach ($courses as $course) {
            if ((int) $course->id === SITEID) {
                continue;
            }
            $out[] = $course;
        }
        return $out;
    }

    public static function get_video(int $id): ?\stdClass {
        global $DB;
        $rec = $DB->get_record(self::TABLE_VIDEO, ['id' => $id]);
        return $rec ?: null;
    }

    /**
     * @return \stdClass[]
     */
    public static function list_for_course(int $courseid, bool $onlyvisible = true): array {
        global $DB;
        $params = ['courseid' => $courseid];
        $sql = 'SELECT * FROM {' . self::TABLE_VIDEO . '} WHERE courseid = :courseid';
        if ($onlyvisible) {
            $sql .= ' AND visible = 1';
        }
        $sql .= ' ORDER BY sessiondate DESC, timecreated DESC';
        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * Pending requests across courses the user can manage.
     *
     * @return \stdClass[]
     */
    public static function list_pending_requests_for_manager(int $userid): array {
        global $DB;

        $courses = self::get_manageable_courses($userid);
        if (empty($courses)) {
            return [];
        }
        $ids = array_map(static fn($c) => (int) $c->id, $courses);
        list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'c');
        $params['st'] = self::STATUS_PENDING;

        $sql = "SELECT r.*, v.title, v.courseid, v.accesstype,
                       u.firstname, u.lastname, u.email
                  FROM {" . self::TABLE_REQ . "} r
                  JOIN {" . self::TABLE_VIDEO . "} v ON v.id = r.videoid
                  JOIN {user} u ON u.id = r.userid
                 WHERE r.status = :st AND v.courseid {$insql}
              ORDER BY r.timerequested ASC";

        return array_values($DB->get_records_sql($sql, $params));
    }

    public static function user_can_manage_video(\stdClass $video, ?int $userid = null): bool {
        global $USER;
        $userid = $userid ?? (int) $USER->id;
        if (is_siteadmin($userid)) {
            return true;
        }
        $ctx = \context_course::instance((int) $video->courseid);
        return has_capability('local/iiidem_classvideos:manage', $ctx, $userid);
    }

    public static function user_can_watch(\stdClass $video, ?int $userid = null): bool {
        global $USER;
        $userid = $userid ?? (int) $USER->id;

        if (!(int) $video->visible) {
            return self::user_can_manage_video($video, $userid);
        }

        $ctx = \context_course::instance((int) $video->courseid);
        if (!is_enrolled($ctx, $userid, '', true) && !is_siteadmin($userid)
                && !has_capability('local/iiidem_classvideos:manage', $ctx, $userid)) {
            return false;
        }

        if (self::user_can_manage_video($video, $userid)) {
            return true;
        }

        if ($video->accesstype === self::ACCESS_PUBLIC) {
            return has_capability('local/iiidem_classvideos:view', $ctx, $userid);
        }

        // request mode
        $req = self::get_request((int) $video->id, $userid);
        return $req && $req->status === self::STATUS_APPROVED;
    }

    public static function get_request(int $videoid, int $userid): ?\stdClass {
        global $DB;
        $rec = $DB->get_record(self::TABLE_REQ, ['videoid' => $videoid, 'userid' => $userid]);
        return $rec ?: null;
    }

    /**
     * Create video record and save optional draft file.
     */
    public static function create_video(
        int $courseid,
        string $title,
        string $description,
        string $accesstype,
        string $externalurl,
        int $sessiondate,
        int $draftitemid,
        int $userid
    ): int {
        global $DB;

        $ctx = \context_course::instance($courseid);
        require_capability('local/iiidem_classvideos:manage', $ctx);

        $accesstype = $accesstype === self::ACCESS_PUBLIC ? self::ACCESS_PUBLIC : self::ACCESS_REQUEST;
        $externalurl = trim($externalurl);
        if ($externalurl !== '') {
            $externalurl = clean_param($externalurl, PARAM_URL);
            if ($externalurl === '' || !preg_match('#^https?://#i', $externalurl)) {
                throw new \moodle_exception('invalurl', 'local_iiidem_classvideos');
            }
        }

        $title = trim(clean_param($title, PARAM_TEXT));
        if ($title === '' || \core_text::strlen($title) > 255) {
            throw new \moodle_exception('invalidtitle', 'local_iiidem_classvideos');
        }

        $description = trim(clean_param($description, PARAM_TEXT));
        if (\core_text::strlen($description) > 5000) {
            $description = \core_text::substr($description, 0, 5000);
        }

        $now = time();
        $rec = (object) [
            'courseid' => $courseid,
            'userid' => $userid,
            'title' => $title,
            'description' => $description,
            'accesstype' => $accesstype,
            'externalurl' => $externalurl !== '' ? $externalurl : null,
            'sessiondate' => max(0, $sessiondate),
            'visible' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $videoid = (int) $DB->insert_record(self::TABLE_VIDEO, $rec);

        $hasfile = false;
        if ($draftitemid > 0) {
            if (class_exists('\theme_iiidem2\upload_security')) {
                $err = \theme_iiidem2\upload_security::validate_user_draft($userid, $draftitemid, self::VIDEO_EXTS);
                if ($err !== '' && $err !== 'empty') {
                    $DB->delete_records(self::TABLE_VIDEO, ['id' => $videoid]);
                    throw new \moodle_exception('invalidfile', 'local_iiidem_classvideos', '', $err);
                }
                $hasfile = ($err === '');
            } else {
                $hasfile = true;
            }
            if ($hasfile) {
                file_save_draft_area_files(
                    $draftitemid,
                    $ctx->id,
                    'local_iiidem_classvideos',
                    'video',
                    $videoid,
                    ['subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => self::MAX_UPLOAD_BYTES]
                );
            }
        }

        $files = get_file_storage()->get_area_files($ctx->id, 'local_iiidem_classvideos', 'video', $videoid, 'id', false);
        if (empty($files) && empty($rec->externalurl)) {
            $DB->delete_records(self::TABLE_VIDEO, ['id' => $videoid]);
            throw new \moodle_exception('needfileorurl', 'local_iiidem_classvideos');
        }

        return $videoid;
    }

    public static function set_visible(int $videoid, bool $visible, int $userid): void {
        global $DB;
        $video = self::get_video($videoid);
        if (!$video || !self::user_can_manage_video($video, $userid)) {
            throw new \moodle_exception('nopermissions', 'error');
        }
        $DB->set_field(self::TABLE_VIDEO, 'visible', $visible ? 1 : 0, ['id' => $videoid]);
        $DB->set_field(self::TABLE_VIDEO, 'timemodified', time(), ['id' => $videoid]);
    }

    public static function request_access(int $videoid, int $userid, string $note = ''): void {
        global $DB;

        $video = self::get_video($videoid);
        if (!$video || !(int) $video->visible) {
            throw new \moodle_exception('invalidrecord', 'error');
        }
        if ($video->accesstype !== self::ACCESS_REQUEST) {
            throw new \moodle_exception('requestnotneeded', 'local_iiidem_classvideos');
        }

        $ctx = \context_course::instance((int) $video->courseid);
        require_capability('local/iiidem_classvideos:request', $ctx);
        if (!is_enrolled($ctx, $userid, '', true)) {
            throw new \moodle_exception('notenrolled', 'local_iiidem_classvideos');
        }

        $note = trim(clean_param($note, PARAM_TEXT));
        if (\core_text::strlen($note) > 500) {
            $note = \core_text::substr($note, 0, 500);
        }

        $existing = self::get_request($videoid, $userid);
        $now = time();
        if ($existing) {
            if ($existing->status === self::STATUS_APPROVED || $existing->status === self::STATUS_PENDING) {
                return;
            }
            // Re-request after reject.
            $existing->status = self::STATUS_PENDING;
            $existing->studentnote = $note;
            $existing->timerequested = $now;
            $existing->timeresolved = 0;
            $existing->resolvedby = 0;
            $DB->update_record(self::TABLE_REQ, $existing);
            return;
        }

        $DB->insert_record(self::TABLE_REQ, (object) [
            'videoid' => $videoid,
            'userid' => $userid,
            'status' => self::STATUS_PENDING,
            'studentnote' => $note,
            'timerequested' => $now,
            'timeresolved' => 0,
            'resolvedby' => 0,
        ]);
    }

    public static function resolve_request(int $requestid, string $status, int $managerid): void {
        global $DB;

        if ($status !== self::STATUS_APPROVED && $status !== self::STATUS_REJECTED) {
            throw new \coding_exception('Invalid status');
        }

        $req = $DB->get_record(self::TABLE_REQ, ['id' => $requestid], '*', MUST_EXIST);
        $video = self::get_video((int) $req->videoid);
        if (!$video || !self::user_can_manage_video($video, $managerid)) {
            throw new \moodle_exception('nopermissions', 'error');
        }

        $req->status = $status;
        $req->timeresolved = time();
        $req->resolvedby = $managerid;
        $DB->update_record(self::TABLE_REQ, $req);
    }

    /**
     * First playable file URL for a video, or null.
     */
    public static function get_file_url(\stdClass $video): ?\moodle_url {
        $ctx = \context_course::instance((int) $video->courseid);
        $files = get_file_storage()->get_area_files(
            $ctx->id,
            'local_iiidem_classvideos',
            'video',
            (int) $video->id,
            'id',
            false
        );
        if (empty($files)) {
            return null;
        }
        $file = reset($files);
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );
    }

    public static function access_label(string $accesstype): string {
        if ($accesstype === self::ACCESS_PUBLIC) {
            return get_string('accesstype_public', 'local_iiidem_classvideos');
        }
        return get_string('accesstype_request', 'local_iiidem_classvideos');
    }

    public static function status_label(string $status): string {
        return get_string('status_' . $status, 'local_iiidem_classvideos');
    }
}
