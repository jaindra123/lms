<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Front page layout.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $USER;

$primarymenu = theme_iiidem2_export_primary_menu($PAGE);

$templatecontext = [
    'sitename' => format_string($SITE->fullname),
    'output' => $OUTPUT,
    'bodyattributes' => $OUTPUT->body_attributes(['pagelayout-frontpage']),
    'primarymoremenu' => $primarymenu['moremenu'],
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'isrealuser' => ($USER->id > 1),
    'slides' => theme_iiidem2_get_frontpage_slides(),
    'loginurl' => (new moodle_url('/login/index.php'))->out(false),
    'registerurl' => theme_iiidem2_get_register_url(),
    'coursesurl' => (new moodle_url('/course/index.php'))->out(false),
];

$frontpagecourses = theme_iiidem2_get_frontpage_courses();
$templatecontext['courses'] = $frontpagecourses;
$templatecontext['hascourses'] = !empty($frontpagecourses);

$templatecontext = theme_iiidem2_merge_footer_context($templatecontext);
$governancecontext = theme_iiidem2_get_program_governance_context();
$ideacontext = theme_iiidem2_get_about_idea_context();

$templatecontext = array_merge(
    $templatecontext,
    $governancecontext ?? [],
    $ideacontext ?? []
);

$templatecontext['aboutideahtml'] = $OUTPUT->render_from_template('theme_iiidem2/about_idea', $ideacontext);

$PAGE->requires->js_call_amd('theme_iiidem2/frontpage_slider', 'init');

echo $OUTPUT->render_from_template('theme_iiidem2/frontpage', $templatecontext);
?>
<div class="d-none" aria-hidden="true"><?php echo $OUTPUT->main_content(); ?></div>
<?php
echo $OUTPUT->render_from_template('theme_iiidem2/page_end', $templatecontext);
?>
</body>
</html>
