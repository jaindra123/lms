<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Registration page layout.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

$pagesubtitle = get_string('registerpagesubtitle', 'theme_iiidem2');
$templatecontext = theme_iiidem2_merge_footer_context(array_merge(
    theme_iiidem2_get_marketing_page_context($pagesubtitle),
    [
        'pagetitle' => $PAGE->title,
        'pagesubtitle' => $pagesubtitle,
        'loginurl' => (new moodle_url('/login/index.php'))->out(false),
        'config' => ['wwwroot' => $CFG->wwwroot],
        'output' => $OUTPUT,
        'bodyattributes' => $OUTPUT->body_attributes(['pagelayout-marketing', 'iiidem-register-page']),
    ]
));

echo $OUTPUT->render_from_template('theme_iiidem2/pages/register_page', $templatecontext);
