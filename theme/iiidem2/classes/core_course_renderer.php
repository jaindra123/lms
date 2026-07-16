<?php
// This file is part of Moodle - http://moodle.org/
//
// @package   theme_iiidem2
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/renderer.php');

/**
 * Course category listing renderer for theme_iiidem2.
 *
 * Course summaries on this site contain full marketing HTML (programme overview).
 * Category browse pages should show a short plain-text excerpt instead.
 *
 * @package   theme_iiidem2
 */
class theme_iiidem2_core_course_renderer extends core_course_renderer {

    /**
     * Plain-text truncated course summary for category / course listings.
     *
     * @param coursecat_helper $chelper
     * @param core_course_list_element $course
     * @return string
     */
    protected function course_summary(coursecat_helper $chelper, core_course_list_element $course): string {
        if (!$course->has_summary()) {
            return '';
        }

        $plain = trim(html_to_text($course->summary, 0, false));
        if ($plain === '') {
            return '';
        }

        $excerpt = shorten_text($plain, 220);
        return html_writer::tag('div', s($excerpt), ['class' => 'summary']);
    }
}
