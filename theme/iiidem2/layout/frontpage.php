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

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);

$templatecontext = [
    'sitename' => format_string($SITE->fullname),
    'output' => $OUTPUT,
    'primarymoremenu' => $primarymenu['moremenu'],
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'isrealuser' => ($USER->id > 1),
    'slides' => theme_iiidem2_get_frontpage_slides(),
    'loginurl' => (new moodle_url('/login/index.php'))->out(false),
    'coursesurl' => (new moodle_url('/course/index.php'))->out(false),
];

$frontpagecourses = theme_iiidem2_get_frontpage_courses();
$templatecontext['courses'] = $frontpagecourses;
$templatecontext['hascourses'] = !empty($frontpagecourses);

$templatecontext = theme_iiidem2_merge_footer_context($templatecontext);

$PAGE->requires->js_call_amd('theme_iiidem2/frontpage_slider', 'init');

echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <?php echo $OUTPUT->standard_head_html(); ?>
</head>
<body <?php echo $OUTPUT->body_attributes(['pagelayout-frontpage']); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<?php
echo $OUTPUT->render_from_template('theme_iiidem2/frontpage', $templatecontext);
?>
</body>
</html>
