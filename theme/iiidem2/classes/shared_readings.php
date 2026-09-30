<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Course reading materials uploaded by logged-in students.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shared_readings {

    public const FILEAREA = 'sharedreading';

    public const TABLE = 'theme_iiidem2_shared_reading';

    public const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * Course ids where shared readings replace Before/During/After workshop sections.
     *
     * @return int[]
     */
    public static function enabled_courseids(): array {
        return self::parse_id_list((string) get_config('theme_iiidem2', 'sharedreadingcourses'));
    }

    /**
     * Course ids that an editor explicitly turned off (beats auto-enable).
     *
     * @return int[]
     */
    public static function disabled_courseids(): array {
        return self::parse_id_list((string) get_config('theme_iiidem2', 'sharedreadingdisabled'));
    }

    /**
     * @param int $courseid
     * @return bool
     */
    public static function is_enabled(int $courseid): bool {
        if ($courseid <= SITEID) {
            return false;
        }
        if (in_array($courseid, self::disabled_courseids(), true)) {
            return false;
        }
        if (in_array($courseid, self::enabled_courseids(), true)) {
            return true;
        }
        // Local used course id 8; production created the same kind of course as id 7.
        // Auto-enable empty/placeholder courses so the reading library is not bound to one site's ids.
        return self::should_auto_enable($courseid);
    }

    /**
     * @param int $courseid
     * @param bool $enabled
     */
    public static function set_enabled(int $courseid, bool $enabled): void {
        global $DB;

        $ids = self::enabled_courseids();
        $disabled = self::disabled_courseids();
        if ($enabled) {
            $ids[] = $courseid;
            $disabled = array_values(array_filter($disabled, static fn(int $id): bool => $id !== $courseid));
        } else {
            $ids = array_values(array_filter($ids, static fn(int $id): bool => $id !== $courseid));
            $disabled[] = $courseid;
        }
        set_config('sharedreadingcourses', implode(',', array_unique($ids)), 'theme_iiidem2');
        set_config('sharedreadingdisabled', implode(',', array_unique($disabled)), 'theme_iiidem2');

        if ($DB->record_exists('course', ['id' => $courseid])) {
            self::sync_workshop_section_visibility($courseid, $enabled);
        }
    }

    /**
     * Persist auto-detected courses (ids differ per site) and hide workshop-phase sections.
     */
    public static function enable_eligible_courses(): void {
        global $DB;

        $courses = $DB->get_records_select('course', 'id > ?', [SITEID], '', 'id');
        foreach ($courses as $course) {
            $courseid = (int) $course->id;
            if (!self::is_enabled($courseid)) {
                continue;
            }
            if (!in_array($courseid, self::enabled_courseids(), true)) {
                self::set_enabled($courseid, true);
                continue;
            }
            self::sync_workshop_section_visibility($courseid, true);
        }
        self::sync_professors_from_source_course();
    }

    /**
     * Previously copied EMB teachers onto IMW so Meet your Professors appeared there.
     * That section is only for the certificate course now.
     */
    public static function sync_professors_from_source_course(): void {
    }

    /**
     * Hide or restore EMB workshop-phase sections so they leave the student curriculum.
     *
     * @param int $courseid
     * @param bool $hide
     */
    public static function sync_workshop_section_visibility(int $courseid, bool $hide): void {
        global $DB, $CFG;

        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
        $visible = $hide ? 0 : 1;
        $changed = false;
        foreach ($sections as $section) {
            if ((int) $section->section === 0) {
                continue;
            }
            $name = trim((string) ($section->name ?? ''));
            if ($name === '' || !self::is_workshop_phase_section($name)) {
                continue;
            }
            if ((int) $section->visible === $visible) {
                continue;
            }
            $DB->set_field('course_sections', 'visible', $visible, ['id' => $section->id]);
            $changed = true;
        }
        if ($changed && empty($CFG->upgraderunning)) {
            require_once($CFG->dirroot . '/course/lib.php');
            rebuild_course_cache($courseid, true);
        }
    }

    /**
     * Workshop-phase section titles used by the EMB programme template.
     *
     * @param string $name
     * @return bool
     */
    public static function is_workshop_phase_section(string $name): bool {
        return (bool) preg_match('/\b(before|during|after)\s+the\s+workshop\b/i', $name);
    }

    /**
     * Moodle default topic titles ("New section", "Topic 1", …).
     *
     * @param string $name
     * @return bool
     */
    public static function is_default_section_name(string $name): bool {
        $name = trim($name);
        if ($name === '') {
            return true;
        }
        if (get_string_manager()->string_exists('newsection', 'moodle')) {
            $default = trim(get_string('newsection', 'moodle'));
            if ($default !== '' && strcasecmp($name, $default) === 0) {
                return true;
            }
        }
        return (bool) preg_match('/^(new section|topic\s+\d+|section\s+\d+)$/i', $name);
    }

    /**
     * Empty default topics that should not appear on the public curriculum accordion.
     *
     * @param string $displayname
     * @param string $rawname
     * @param bool $hasactivities
     * @param bool $hassummary
     * @return bool
     */
    public static function is_placeholder_curriculum_section(
        string $displayname,
        string $rawname,
        bool $hasactivities,
        bool $hassummary
    ): bool {
        if ($hasactivities || $hassummary) {
            return false;
        }
        $rawname = trim($rawname);
        return $rawname === '' || self::is_default_section_name($displayname) || self::is_workshop_phase_section($displayname);
    }

    /**
     * New empty courses (production id 7, local id 8) — not the paid EMB registration course.
     *
     * @param int $courseid
     * @return bool
     */
    public static function should_auto_enable(int $courseid): bool {
        global $DB;

        static $cache = [];
        if (isset($cache[$courseid])) {
            return $cache[$courseid];
        }

        if ($courseid <= SITEID) {
            $cache[$courseid] = false;
            return false;
        }

        if (in_array($courseid, registration_enrolment::get_target_course_ids(), true)) {
            $cache[$courseid] = false;
            return false;
        }

        $shortname = strtoupper(trim((string) $DB->get_field('course', 'shortname', ['id' => $courseid])));
        // Same programme as local course 8 (IMW). Production often uses a different numeric id.
        if ($shortname === 'IMW') {
            $cache[$courseid] = true;
            return true;
        }

        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
        foreach ($sections as $section) {
            if ((int) $section->section === 0) {
                continue;
            }
            $cmcount = $DB->count_records_select(
                'course_modules',
                'course = ? AND section = ? AND deletioninprogress = 0',
                [$courseid, (int) $section->id]
            );
            $summary = trim(html_to_text((string) ($section->summary ?? ''), 0));
            $rawname = trim((string) ($section->name ?? ''));
            $displayname = $rawname !== '' ? $rawname : 'New section';
            if (!self::is_placeholder_curriculum_section($displayname, $rawname, $cmcount > 0, $summary !== '')) {
                $cache[$courseid] = false;
                return false;
            }
        }

        $cache[$courseid] = true;
        return true;
    }

    /**
     * @param string $raw
     * @return int[]
     */
    private static function parse_id_list(string $raw): array {
        $ids = [];
        foreach (preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            $id = (int) $part;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    /**
     * @return bool
     */
    public static function table_ready(): bool {
        global $DB;
        static $ready = null;
        if ($ready === null) {
            $ready = $DB->get_manager()->table_exists(self::TABLE);
        }
        return $ready;
    }

    /**
     * Production IMW is course 7. Logged-in students may upload/download there
     * without an enrolment. Other courses still require enrolment.
     *
     * Local IMW is usually a different id (often 8) with shortname IMW.
     *
     * @param int $courseid
     * @return bool
     */
    public static function allows_loggedin_without_enrol(int $courseid): bool {
        global $DB;

        if ($courseid <= SITEID) {
            return false;
        }
        if (!self::is_enabled($courseid)) {
            return false;
        }
        if (in_array($courseid, registration_enrolment::get_target_course_ids(), true)) {
            return false;
        }
        if (function_exists('theme_iiidem2_get_course_fee_enrol_instance')
                && theme_iiidem2_get_course_fee_enrol_instance($courseid)) {
            return false;
        }
        if ($courseid === 7) {
            return true;
        }
        $shortname = strtoupper(trim((string) $DB->get_field('course', 'shortname', ['id' => $courseid])));
        return $shortname === 'IMW';
    }

    /**
     * Site login only (no course enrolment) for the IMW reading library.
     *
     * @param \stdClass $course
     */
    public static function require_login_for_access(\stdClass $course): void {
        if (self::allows_loggedin_without_enrol((int) $course->id)) {
            require_login(null, false);
            if (!isloggedin() || isguestuser()) {
                redirect(get_login_url());
            }
            return;
        }
        require_login($course, false);
    }

    /**
     * Enrolled (or editing) users may upload and view shared files.
     * Course 7 / IMW also allows any logged-in non-guest without enrolment.
     *
     * @param \stdClass $course
     * @param int|null $userid
     * @return bool
     */
    public static function can_access(\stdClass $course, ?int $userid = null): bool {
        if (self::allows_loggedin_without_enrol((int) $course->id)) {
            if ($userid === null) {
                return isloggedin() && !isguestuser();
            }
            return $userid > 0 && !isguestuser($userid);
        }

        return \theme_iiidem2_user_can_preview_curriculum($course, $userid);
    }

    /**
     * @param \stdClass $course
     * @param int|null $userid
     * @return bool
     */
    public static function can_manage_course(\stdClass $course, ?int $userid = null): bool {
        global $USER;
        if ($userid === null) {
            $userid = (int) $USER->id;
        }
        $context = \context_course::instance($course->id);
        return has_capability('moodle/course:update', $context, $userid);
    }

    /**
     * @param \stdClass $record
     * @param int $userid
     * @param \stdClass $course
     * @return bool
     */
    public static function can_delete(\stdClass $record, int $userid, \stdClass $course): bool {
        if ((int) $record->userid === $userid) {
            return true;
        }
        return self::can_manage_course($course, $userid);
    }

    /**
     * @param int $courseid
     * @return \stdClass[]
     */
    public static function get_records(int $courseid): array {
        global $DB;
        if (!self::table_ready()) {
            return [];
        }
        return $DB->get_records(self::TABLE, ['courseid' => $courseid], 'timecreated DESC, id DESC');
    }

    /**
     * @param \stdClass $course
     * @param string $title
     * @param int $draftitemid
     * @return int New record id
     */
    public static function create(\stdClass $course, string $title, int $draftitemid): int {
        global $DB, $USER, $CFG;

        require_once($CFG->libdir . '/filelib.php');

        if (!self::table_ready()) {
            throw new \moodle_exception('sharedreadingsnotready', 'theme_iiidem2');
        }

        $context = \context_course::instance($course->id);
        if (!self::can_access($course)) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('sharedreadingsupload', 'theme_iiidem2'));
        }

        \theme_iiidem2\rate_limit::require_allowed(
            'shared_reading_upload',
            20,
            HOURSECS,
            'u' . (int) $USER->id . ':c' . (int) $course->id
        );

        $title = trim($title);
        if ($title === '' || \core_text::strlen($title) > 255) {
            throw new \moodle_exception('invalidrecord', 'error');
        }

        $reject = upload_security::validate_user_draft(
            (int) $USER->id,
            $draftitemid,
            upload_security::MATERIAL_EXTENSIONS
        );
        if ($reject !== '') {
            throw new \moodle_exception('teachermaterialsinvalidtype', 'theme_iiidem2', '', $reject);
        }

        $record = (object) [
            'courseid' => (int) $course->id,
            'userid' => (int) $USER->id,
            'title' => $title,
            'timecreated' => time(),
        ];
        $id = (int) $DB->insert_record(self::TABLE, $record);

        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            'theme_iiidem2',
            self::FILEAREA,
            $id,
            [
                'subdirs' => 0,
                'maxfiles' => 1,
                'maxbytes' => self::max_bytes($context, $course),
            ]
        );

        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'theme_iiidem2', self::FILEAREA, $id, 'id', false);
        if (empty($files)) {
            $DB->delete_records(self::TABLE, ['id' => $id]);
            throw new \moodle_exception('teachermaterialsinvalidtype', 'theme_iiidem2', '', 'empty');
        }

        return $id;
    }

    /**
     * @param int $recordid
     * @param int $userid
     */
    public static function delete(int $recordid, int $userid): void {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['id' => $recordid], '*', MUST_EXIST);
        $course = get_course((int) $record->courseid);
        if (!self::can_delete($record, $userid, $course)) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('delete'));
        }

        $context = \context_course::instance($course->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'theme_iiidem2', self::FILEAREA, $recordid);
        $DB->delete_records(self::TABLE, ['id' => $recordid]);
    }

    /**
     * @param \context $context
     * @param \stdClass $course
     * @return int
     */
    public static function max_bytes(\context $context, \stdClass $course): int {
        global $CFG;
        $maxbytes = get_user_max_upload_file_size($context, $CFG->maxbytes, $course->maxbytes);
        if ($maxbytes <= 0 || $maxbytes > self::MAX_BYTES) {
            return self::MAX_BYTES;
        }
        return (int) $maxbytes;
    }

    /**
     * Mustache context for the course curriculum page.
     *
     * @param \stdClass $course
     * @return array
     */
    public static function curriculum_context(\stdClass $course): array {
        global $USER;

        $enabled = self::is_enabled((int) $course->id) && self::table_ready();
        $canmanage = self::can_manage_course($course);
        $canaccess = self::can_access($course);
        $context = \context_course::instance($course->id);
        $posturl = (new \moodle_url('/theme/iiidem2/course/shared_reading.php', [
            'id' => $course->id,
        ]))->out(false);

        $files = [];
        if ($enabled && $canaccess) {
            $fs = get_file_storage();
            foreach (self::get_records((int) $course->id) as $record) {
                $stored = $fs->get_area_files(
                    $context->id,
                    'theme_iiidem2',
                    self::FILEAREA,
                    (int) $record->id,
                    'id',
                    false
                );
                $file = reset($stored);
                if (!$file) {
                    continue;
                }
                $uploader = \core_user::get_user((int) $record->userid);
                $files[] = [
                    'id' => (int) $record->id,
                    'title' => format_string($record->title),
                    'filename' => $file->get_filename(),
                    'filesize' => display_size($file->get_filesize()),
                    'uploader' => $uploader ? fullname($uploader) : get_string('user'),
                    'uploadedon' => userdate((int) $record->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
                    'url' => \moodle_url::make_pluginfile_url(
                        $context->id,
                        'theme_iiidem2',
                        self::FILEAREA,
                        (int) $record->id,
                        $file->get_filepath(),
                        $file->get_filename(),
                        true
                    )->out(false),
                    'candelete' => self::can_delete($record, (int) $USER->id, $course),
                    'deleteurl' => (new \moodle_url('/theme/iiidem2/course/shared_reading.php', [
                        'id' => $course->id,
                        'delete' => (int) $record->id,
                        'sesskey' => sesskey(),
                    ]))->out(false),
                ];
            }
        }

        $formhtml = '';
        if ($enabled && $canaccess) {
            $form = new \theme_iiidem2\form\shared_reading_form($posturl, [
                'courseid' => (int) $course->id,
                'maxbytes' => self::max_bytes($context, $course),
            ]);
            $formhtml = $form->render();
        }

        $returnurl = theme_iiidem2_get_course_detail_url($course);

        return [
            'sharedreadingsenabled' => $enabled,
            'sharedreadingscanmanage' => $canmanage,
            'sharedreadingscanupload' => $enabled && $canaccess,
            'sharedreadingsloggedin' => isloggedin() && !isguestuser(),
            'hassharedreadings' => !empty($files),
            'sharedreadings' => $files,
            'sharedreadingsformhtml' => $formhtml,
            'sharedreadingsenableurl' => (new \moodle_url('/theme/iiidem2/course/shared_reading.php', [
                'id' => $course->id,
                'enable' => 1,
                'sesskey' => sesskey(),
            ]))->out(false),
            'sharedreadingsdisableurl' => (new \moodle_url('/theme/iiidem2/course/shared_reading.php', [
                'id' => $course->id,
                'enable' => 0,
                'sesskey' => sesskey(),
            ]))->out(false),
            'sharedreadingsloginurl' => (new \moodle_url('/login/index.php', [
                'wantsurl' => $returnurl,
            ]))->out(false),
            'sharedreadingsmaxsize' => display_size(self::max_bytes($context, $course)),
        ];
    }

    /**
     * Serve a shared reading file.
     *
     * @param \stdClass|null $course
     * @param \context $context
     * @param array $args
     * @param bool $forcedownload
     * @param array $options
     * @return void
     */
    public static function pluginfile($course, \context $context, array $args, bool $forcedownload, array $options): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_COURSE || !self::table_ready()) {
            send_file_not_found();
        }

        $course = $course ?: get_course($context->instanceid);
        self::require_login_for_access($course);
        if (isguestuser()) {
            send_file_not_found();
        }

        $itemid = (int) array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

        $record = $DB->get_record(self::TABLE, ['id' => $itemid]);
        if (!$record || (int) $record->courseid !== (int) $context->instanceid) {
            send_file_not_found();
        }

        if ((int) $course->id !== (int) $record->courseid || !self::can_access($course)) {
            send_file_not_found();
        }

        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'theme_iiidem2', self::FILEAREA, $itemid, $filepath, $filename);
        if (!$file || $file->is_directory()) {
            send_file_not_found();
        }

        send_stored_file($file, 0, 0, true, $options);
    }
}
