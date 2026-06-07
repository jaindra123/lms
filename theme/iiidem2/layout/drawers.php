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
 * A drawer based layout for the iiidem2 theme.
 *
 * @package   theme_iiidem2
 * @copyright 2021 Bas Brands
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Quiz attempt: full-screen MCQ layout (pagelayout may still be incourse when this runs).
if (theme_iiidem2_is_quiz_attempt_page($PAGE) && theme_iiidem2_use_custom_quiz_ui($PAGE)) {
    theme_iiidem2_apply_custom_quiz_page_assets($PAGE);
    require(__DIR__ . '/quizattempt.php');
    return;
}

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

// Add block button in editing mode.
$addblockbutton = $OUTPUT->addblockbutton();

if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $courseindexopen = false;
    $blockdraweropen = false;
}

if (defined('BEHAT_SITE_RUNNING') && get_user_preferences('behat_keep_drawer_closed') != 1) {
    $blockdraweropen = true;
}

$extraclasses = ['uses-drawers'];
$isenrolpage = theme_iiidem2_is_enrol_index_page($PAGE)
    && !empty($COURSE->id)
    && (int) $COURSE->id !== SITEID;
if ($isenrolpage) {
    $extraclasses[] = 'iiidem-enrol-index';
}
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}
if (theme_iiidem2_is_quiz_attempt_url($PAGE)) {
    $extraclasses[] = 'iiidem-quiz-attempt-active';
    $quizcm = theme_iiidem2_resolve_quiz_cm_from_page($PAGE);
    if ($quizcm) {
        $extraclasses[] = 'iiidem-custom-quiz-cmid-' . (int) $quizcm->id;
    }
    $PAGE->requires->js_call_amd('theme_iiidem2/quiz_mcq', 'init');
} else if (theme_iiidem2_is_custom_quiz_page()) {
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

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}
$courseindex = core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
//$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();
$forceblockdraweropen = false;

$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$primarymenu = theme_iiidem2_export_primary_menu($PAGE);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
// If the settings menu will be included in the header then don't add it here.
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($OUTPUT);

$enrolcontext = [];
if ($isenrolpage) {
    $enrolcontext = theme_iiidem2_get_course_display_context($COURSE);
    $enrolcontext['isenrolpage'] = true;
}

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton
];

$templatecontext = theme_iiidem2_merge_footer_context($templatecontext);
$templatecontext = array_merge(
    $templatecontext,
    theme_iiidem2_get_custom_quiz_template_context(),
    theme_iiidem2_get_live_class_template_context(),
    $enrolcontext
);

echo $OUTPUT->render_from_template('theme_iiidem2/drawers', $templatecontext);
