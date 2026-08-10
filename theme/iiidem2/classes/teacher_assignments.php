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
 * Teacher assignment create helper (mod_assign) without course Edit mode.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class teacher_assignments {

    /**
     * Courses where the user can create Assignment activities.
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
                    || has_capability('mod/assign:addinstance', $context, $userid)) {
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
     * Create a visible Assignment with file submissions enabled.
     *
     * @param \stdClass $course
     * @param string $name
     * @param string $intro
     * @param int $sectionnum
     * @param int $allowfrom Unix timestamp or 0
     * @param int $duedate Unix timestamp or 0
     * @param int $grade Max grade points
     * @param bool $allowonlinetext
     * @param int $maxfiles
     * @return \stdClass Module info from add_moduleinfo
     */
    public static function create_assignment(
        \stdClass $course,
        string $name,
        string $intro,
        int $sectionnum = 0,
        int $allowfrom = 0,
        int $duedate = 0,
        int $grade = 100,
        bool $allowonlinetext = false,
        int $maxfiles = 5
    ): \stdClass {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/assign/lib.php');
        require_once($CFG->libdir . '/gradelib.php');

        $context = \context_course::instance($course->id);
        require_capability('moodle/course:manageactivities', $context);

        $moduleid = (int) $DB->get_field('modules', 'id', ['name' => 'assign'], MUST_EXIST);
        $maxbytes = get_max_upload_file_size($CFG->maxbytes, $course->maxbytes);

        $moduleinfo = new \stdClass();
        $moduleinfo->modulename = 'assign';
        $moduleinfo->module = $moduleid;
        $moduleinfo->name = $name;
        $moduleinfo->intro = $intro;
        $moduleinfo->introformat = FORMAT_HTML;
        $moduleinfo->alwaysshowdescription = 1;
        $moduleinfo->submissiondrafts = 0;
        $moduleinfo->requiresubmissionstatement = 0;
        $moduleinfo->sendnotifications = 0;
        $moduleinfo->sendlatenotifications = 0;
        $moduleinfo->sendstudentnotifications = 1;
        $moduleinfo->allowsubmissionsfromdate = $allowfrom;
        $moduleinfo->duedate = $duedate;
        $moduleinfo->cutoffdate = 0;
        $moduleinfo->gradingduedate = 0;
        $moduleinfo->grade = max(0, $grade);
        $moduleinfo->teamsubmission = 0;
        $moduleinfo->requireallteammemberssubmit = 0;
        $moduleinfo->teamsubmissiongroupingid = 0;
        $moduleinfo->blindmarking = 0;
        $moduleinfo->hidegrader = 0;
        $moduleinfo->attemptreopenmethod = 'untilpass';
        $moduleinfo->maxattempts = 1;
        $moduleinfo->markingworkflow = 0;
        $moduleinfo->markingallocation = 0;
        $moduleinfo->markinganonymous = 0;
        $moduleinfo->preventsubmissionnotingroup = 0;
        $moduleinfo->submissionattachments = 0;
        $moduleinfo->timelimit = 0;
        $moduleinfo->activity = '';
        $moduleinfo->activityformat = FORMAT_HTML;

        // File submission plugin (students upload files).
        $moduleinfo->assignsubmission_file_enabled = 1;
        $moduleinfo->assignsubmission_file_maxfiles = max(1, min(20, $maxfiles));
        $moduleinfo->assignsubmission_file_maxsizebytes = $maxbytes;
        $moduleinfo->assignsubmission_file_filetypes = upload_security::assignment_filetypes_string();

        $moduleinfo->assignsubmission_onlinetext_enabled = $allowonlinetext ? 1 : 0;
        $moduleinfo->assignsubmission_onlinetext_wordlimit_enabled = 0;
        $moduleinfo->assignsubmission_onlinetext_wordlimit = 0;

        // Common feedback plugins — leave site defaults via empty/disabled unless required.
        $moduleinfo->assignfeedback_comments_enabled = 1;
        $moduleinfo->assignfeedback_comments_commentinline = 0;
        $moduleinfo->assignfeedback_file_enabled = 0;
        $moduleinfo->assignfeedback_offline_enabled = 0;
        $moduleinfo->assignfeedback_editpdf_enabled = 0;

        $moduleinfo->section = $sectionnum;
        $moduleinfo->visible = 1;
        $moduleinfo->visibleoncoursepage = 1;
        $moduleinfo->course = $course->id;
        $moduleinfo->coursemodule = 0;
        $moduleinfo->instance = 0;
        $moduleinfo->add = 'assign';
        $moduleinfo->cmidnumber = '';
        $moduleinfo->idnumber = '';
        // Activity completion so certificates can issue after submit.
        $moduleinfo->completion = COMPLETION_TRACKING_AUTOMATIC;
        $moduleinfo->completionview = 0;
        $moduleinfo->completionusegrade = 0;
        $moduleinfo->completionpassgrade = 0;
        $moduleinfo->completionsubmit = 1;
        $moduleinfo->completionexpected = 0;
        $moduleinfo->groupmode = 0;
        $moduleinfo->groupingid = 0;
        $moduleinfo->availabilityconditionsjson = '';
        $moduleinfo->download = 0;
        $moduleinfo->showdescription = 0;

        return add_moduleinfo($moduleinfo, $course);
    }

    /**
     * Recent assignments in the teacher's courses.
     *
     * @param array $courses
     * @param int $userid
     * @param int $limit
     * @return array
     */
    public static function list_recent_assignments(array $courses, int $userid, int $limit = 20): array {
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
            foreach ($modinfo->get_instances_of('assign') as $cm) {
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
                    'url' => (new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]))->out(false),
                    'gradingurl' => (new \moodle_url('/mod/assign/view.php', [
                        'id' => $cm->id,
                        'action' => 'grading',
                    ]))->out(false),
                ];
            }
        }

        usort($items, static function(array $a, array $b): int {
            return ($b['id'] <=> $a['id']);
        });

        return array_slice($items, 0, $limit);
    }
}
