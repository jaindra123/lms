<?php
// This file is part of Moodle - http://moodle.org/
//
// @package   theme_iiidem2
// @copyright 2026 IIIDEM
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

$PAGE->requires->css(new moodle_url('/theme/iiidem2/style/login-page.css'));
$PAGE->requires->js(new moodle_url('/theme/iiidem2/javascript/login_credentials_lock.js'));

$bodyattributes = $OUTPUT->body_attributes(['iiidem-login-page', 'pagelayout-login']);

$templatecontext = array_merge(
    theme_iiidem2_get_login_page_context(),
    [
        'output' => $OUTPUT,
        'bodyattributes' => $bodyattributes,
    ]
);

echo $OUTPUT->render_from_template('theme_iiidem2/login', $templatecontext);
