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
 * Teacher course file materials (mod_resource) for student viewing.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_materials {

    /**
     * Courses the user can add File activities to.
     *
     * @param int|null $userid
     * @return array
     */
    public static function get_teaching_courses(?int $userid = null): array {
        global $CFG, $USER;

        if ($userid === null) {
            $userid = (int) $USER->id;
        }

        require_once($CFG->libdir . '/enrollib.php');

        $courses = enrol_get_users_courses($userid, true, '*', 'visible DESC, fullname ASC');
        $teaching = [];
        foreach ($courses as $course) {
            if ((int) $course->id === SITEID) {
                continue;
            }
            $context = \context_course::instance($course->id);
            if (has_capability('moodle/course:manageactivities', $context, $userid)
                    || has_capability('mod/resource:addinstance', $context, $userid)) {
                $teaching[] = $course;
            }
        }
        return $teaching;
    }

    /**
     * Course options for a select element.
     *
     * @param array $courses
     * @return array
     */
    public static function course_options(array $courses): array {
        $options = [];
        foreach ($courses as $course) {
            $options[(int) $course->id] = format_string(
                $course->fullname,
                true,
                ['context' => \context_course::instance($course->id)]
            );
        }
        return $options;
    }

    /**
     * Section options for a course (0 = general / top).
     *
     * @param int $courseid
     * @return array
     */
    public static function section_options(int $courseid): array {
        $modinfo = get_fast_modinfo($courseid);
        $options = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            $num = (int) $section->section;
            $name = get_section_name($courseid, $section);
            if ($name === '' || $name === null) {
                $name = get_string('section') . ' ' . $num;
            }
            $options[$num] = $name;
        }
        if (empty($options)) {
            $options[0] = get_string('general');
        }
        return $options;
    }

    /**
     * Create a visible File (mod_resource) activity from an uploaded draft file.
     *
     * @param \stdClass $course
     * @param string $name
     * @param string $intro
     * @param int $draftitemid
     * @param int $sectionnum
     * @return \stdClass Module info returned by add_moduleinfo
     */
    public static function create_resource(
        \stdClass $course,
        string $name,
        string $intro,
        int $draftitemid,
        int $sectionnum = 0
    ): \stdClass {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/resource/lib.php');
        require_once($CFG->libdir . '/resourcelib.php');

        $context = \context_course::instance($course->id);
        require_capability('moodle/course:manageactivities', $context);

        $reject = upload_security::validate_user_draft(
            (int) $USER->id,
            $draftitemid,
            upload_security::MATERIAL_EXTENSIONS
        );
        if ($reject !== '') {
            throw new \moodle_exception('teachermaterialsinvalidtype', 'theme_iiidem2', '', $reject);
        }

        $moduleid = (int) $DB->get_field('modules', 'id', ['name' => 'resource'], MUST_EXIST);

        $moduleinfo = new \stdClass();
        $moduleinfo->modulename = 'resource';
        $moduleinfo->module = $moduleid;
        $moduleinfo->name = $name;
        $moduleinfo->intro = $intro;
        $moduleinfo->introformat = FORMAT_HTML;
        $moduleinfo->files = $draftitemid;
        $moduleinfo->section = $sectionnum;
        $moduleinfo->visible = 1;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->course = $course->id;
        $moduleinfo->coursemodule = 0;
        $moduleinfo->instance = 0;
        $moduleinfo->add = 'resource';
        $moduleinfo->cmidnumber = '';
        $moduleinfo->idnumber = '';
        // Prefer download/forcefile for safer delivery of uploaded content.
        $moduleinfo->display = RESOURCELIB_DISPLAY_DOWNLOAD;
        $moduleinfo->printintro = 1;
        $moduleinfo->showsize = 1;
        $moduleinfo->showtype = 1;
        $moduleinfo->showdate = 0;
        $moduleinfo->filterfiles = 0;
        $moduleinfo->completion = COMPLETION_TRACKING_NONE;
        $moduleinfo->groupmode = 0;
        $moduleinfo->groupingid = 0;
        $moduleinfo->availabilityconditionsjson = '';
        $moduleinfo->showdescription = 0;

        return add_moduleinfo($moduleinfo, $course);
    }

    /**
     * Recent File resources in the teacher's courses (for listing).
     *
     * @param array $courses
     * @param int $userid
     * @param int $limit
     * @return array
     */
    public static function list_recent_materials(array $courses, int $userid, int $limit = 20): array {
        $items = [];
        if (empty($courses)) {
            return $items;
        }

        foreach ($courses as $course) {
            try {
                $modinfo = get_fast_modinfo($course, $userid);
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($modinfo->get_instances_of('resource') as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                $items[] = [
                    'id' => (int) $cm->id,
                    'name' => $cm->get_formatted_name(),
                    'coursename' => format_string($course->fullname, true, [
                        'context' => \context_course::instance($course->id),
                    ]),
                    'courseid' => (int) $course->id,
                    'url' => $cm->url ? $cm->url->out(false) : (new \moodle_url('/mod/resource/view.php', [
                        'id' => $cm->id,
                    ]))->out(false),
                    'courseurl' => (new \moodle_url('/course/view.php', [
                        'id' => $course->id,
                    ]))->out(false) . '#curriculum',
                ];
            }
        }

        usort($items, static function(array $a, array $b): int {
            return ($b['id'] <=> $a['id']);
        });

        return array_slice($items, 0, $limit);
    }
}
