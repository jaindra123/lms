<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Public About Us page (clean URL: /about-us/).
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_course($SITE);
$PAGE->set_url(new moodle_url('/about-us/'));
$PAGE->set_pagelayout('frontpage');
$PAGE->set_cacheable(false);
$PAGE->set_title(get_string('aboutus', 'theme_iiidem2'));
$PAGE->set_heading(get_string('aboutus', 'theme_iiidem2'));

theme_iiidem2_render_public_page('theme_iiidem2/pages/about-us', [
    'pagetitle' => get_string('aboutus', 'theme_iiidem2'),
    'pagesubtitle' => get_string('aboutus_lead', 'theme_iiidem2'),
]);
