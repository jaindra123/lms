<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A secure layout for the iiidem2 theme.
 *
 * @package   theme_iiidem2
 * @copyright 2016 Damyon Wiese
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = strpos($blockshtml, 'data-block=') !== false;

$extraclasses = [];
if (theme_iiidem2_is_custom_quiz_page()) {
    $quizcm = theme_iiidem2_resolve_quiz_cm_from_page($PAGE);
    $extraclasses[] = 'iiidem-custom-quiz';
    if ($quizcm) {
        $extraclasses[] = 'iiidem-custom-quiz-cmid-' . (int) $quizcm->id;
    }
    theme_iiidem2_apply_custom_quiz_page_assets($PAGE);
} else if (theme_iiidem2_is_live_class_page()) {
    $pagecm = theme_iiidem2_resolve_page_cm_from_page($PAGE);
    $extraclasses[] = 'iiidem-live-class';
    if ($pagecm) {
        $extraclasses[] = 'iiidem-live-class-cmid-' . (int) $pagecm->id;
    }
    theme_iiidem2_apply_live_class_page_assets($PAGE);
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks
];

if (empty($PAGE->layout_options['noactivityheader'])) {
    $header = $PAGE->activityheader;
    $renderer = $PAGE->get_renderer('core');
    $templatecontext['headercontent'] = $header->export_for_template($renderer);
}

$templatecontext = theme_iiidem2_merge_footer_context($templatecontext);
$templatecontext = array_merge(
    $templatecontext,
    theme_iiidem2_get_custom_quiz_template_context(),
    theme_iiidem2_get_live_class_template_context()
);

echo $OUTPUT->render_from_template('theme_iiidem2/secure', $templatecontext);

