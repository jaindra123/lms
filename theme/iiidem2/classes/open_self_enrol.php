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
 * Open self-enrolment for IMW / shared-reading courses (not the paid EMB course).
 *
 * Logged-in students can join without an administrator using Enrol users.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class open_self_enrol {

    /**
     * Workshop-style courses only: shared readings / IMW, never paid registration courses.
     *
     * @param int $courseid
     * @return bool
     */
    public static function is_open_course(int $courseid): bool {
        if ($courseid <= SITEID) {
            return false;
        }
        if (in_array($courseid, registration_enrolment::get_target_course_ids(), true)) {
            return false;
        }
        if (theme_iiidem2_get_course_fee_enrol_instance($courseid)) {
            return false;
        }
        return shared_readings::is_enabled($courseid);
    }

    /**
     * Enable (or create) a no-key self-enrolment instance on an open course.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function ensure_instance(int $courseid): ?\stdClass {
        global $CFG, $DB;

        if (!self::is_open_course($courseid) || !enrol_is_enabled('self')) {
            return null;
        }

        require_once($CFG->libdir . '/enrollib.php');

        $plugin = enrol_get_plugin('self');
        if (!$plugin) {
            return null;
        }

        $instance = $DB->get_record('enrol', [
            'courseid' => $courseid,
            'enrol' => 'self',
        ], '*', IGNORE_MULTIPLE);

        $studentroles = get_archetype_roles('student');
        $student = reset($studentroles);
        $roleid = $student ? (int) $student->id : 5;

        if ($instance) {
            $dirty = false;
            if ((int) $instance->status !== ENROL_INSTANCE_ENABLED) {
                $instance->status = ENROL_INSTANCE_ENABLED;
                $dirty = true;
            }
            if ((int) $instance->customint6 !== 1) {
                $instance->customint6 = 1;
                $dirty = true;
            }
            if ((string) ($instance->password ?? '') !== '') {
                $instance->password = '';
                $dirty = true;
            }
            if ((int) $instance->roleid <= 0) {
                $instance->roleid = $roleid;
                $dirty = true;
            }
            if ($dirty) {
                $instance->timemodified = time();
                $DB->update_record('enrol', $instance);
            }
            return $instance;
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);
        if (!$course) {
            return null;
        }

        $fields = $plugin->get_instance_defaults();
        $fields['status'] = ENROL_INSTANCE_ENABLED;
        $fields['customint6'] = 1;
        $fields['password'] = '';
        $fields['roleid'] = $roleid;
        $fields['name'] = 'Participant self enrolment';
        $plugin->add_instance($course, $fields);

        return $DB->get_record('enrol', [
            'courseid' => $courseid,
            'enrol' => 'self',
            'status' => ENROL_INSTANCE_ENABLED,
        ], '*', IGNORE_MULTIPLE) ?: null;
    }

    /**
     * Enrol the current logged-in user as a student when this is an open course.
     *
     * @param int $courseid
     * @return bool True when a new enrolment was created
     */
    public static function enrol_current_user(int $courseid): bool {
        global $CFG, $USER;

        if (CLI_SCRIPT || AJAX_SCRIPT || WS_SERVER) {
            return false;
        }
        if (!isloggedin() || isguestuser() || empty($USER->id)) {
            return false;
        }
        if (is_siteadmin()) {
            return false;
        }
        if (\core\session\manager::is_loggedinas()) {
            return false;
        }
        if (!self::is_open_course($courseid)) {
            return false;
        }
        // Course 7 / IMW: logged-in students upload readings without being enrolled.
        if (shared_readings::allows_loggedin_without_enrol($courseid)) {
            return false;
        }

        require_once($CFG->libdir . '/enrollib.php');

        $context = \context_course::instance($courseid);
        if (is_enrolled($context, $USER, '', true)) {
            return false;
        }
        if (has_capability('moodle/course:update', $context)) {
            return false;
        }

        $instance = self::ensure_instance($courseid);
        if (!$instance) {
            return false;
        }

        $plugin = enrol_get_plugin('self');
        if (!$plugin || $plugin->can_self_enrol($instance) !== true) {
            return false;
        }

        $plugin->enrol_self($instance);
        return is_enrolled($context, $USER, '', true);
    }
}
