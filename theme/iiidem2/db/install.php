<?php
// This file is part of Moodle - http://moodle.org/.

/**
 * Theme installation steps.
 *
 * @package theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Enable login using either username or email on new installations.
 */
function xmldb_theme_iiidem2_install(): void {
    set_config('authloginviaemail', 1);
    set_config('registrationcourseids', '4', 'theme_iiidem2');
}
