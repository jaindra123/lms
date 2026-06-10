<?php
/**
 * A drawer based layout for the boost theme.
 *
 * @package   theme_boost
 * @copyright 2021 Bas Brands
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$PAGE->add_body_class('iiidem-course-hero-layout');

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
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
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

$preloaded = theme_iiidem2_get_preloaded_course_layout_context($COURSE);
if ($preloaded) {
    $coursedisplay = $preloaded['display'];
    $curriculum = $preloaded['curriculum'];
    $quizzes = $preloaded['quizzes'];
} else {
    $coursedisplay = theme_iiidem2_get_course_display_context($COURSE);
    $curriculum = theme_iiidem2_get_course_curriculum_context($COURSE);
    $quizzes = theme_iiidem2_get_course_quizzes_context($COURSE);
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
    'addblockbutton' => $addblockbutton,
    'hasenrollmodal' => true,
    // Moodle header() requires the main_content token in layout output (hidden; curriculum uses theme partial).
    'maincontentplaceholder' => $OUTPUT->main_content(),

];

$templatecontext = array_merge($templatecontext, $coursedisplay, $curriculum, $quizzes);
$templatecontext = array_merge($templatecontext, theme_iiidem2_get_course_fee_payment_context($COURSE));
$templatecontext = array_merge($templatecontext, theme_iiidem2_get_program_governance_context());
$templatecontext['pnbpaymentsuccess'] = optional_param('pnbpayment', '', PARAM_ALPHA) === 'success';
$templatecontext = theme_iiidem2_merge_footer_context($templatecontext);

$PAGE->requires->js_call_amd('theme_iiidem2/enroll', 'init');

/*$templatecontext = [
    'output' => $OUTPUT,
    'bodyattributes' => $bodyattributes,
];
$templatecontext['iscoursepage'] = true;*/

echo $OUTPUT->render_from_template(
    'theme_iiidem2/course_drawers',
    $templatecontext
);
