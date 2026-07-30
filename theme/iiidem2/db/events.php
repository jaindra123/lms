<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for theme_iiidem2.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$observers = [
    [
        'eventname' => '\core\event\course_module_created',
        'callback' => '\theme_iiidem2\liveclass_notifier::course_module_changed',
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\theme_iiidem2\liveclass_notifier::course_module_changed',
    ],
    [
        'eventname' => '\core\event\course_module_created',
        'callback' => '\theme_iiidem2\assign_notifier::course_module_changed',
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\theme_iiidem2\assign_notifier::course_module_changed',
    ],
    [
        'eventname' => '\core\event\user_updated',
        'callback' => '\theme_iiidem2\registration_enrolment::user_updated',
    ],
];
