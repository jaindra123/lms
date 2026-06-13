<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\after_config::class,
        'callback' => [\theme_iiidem2\hook_listener::class, 'after_config'],
        'priority' => 100,
    ],
    [
        'hook' => \core_user\hook\after_login_completed::class,
        'callback' => [\theme_iiidem2\hook_listener::class, 'after_login_completed'],
        'priority' => 100,
    ],
    [
        'hook' => \core\hook\navigation\primary_extend::class,
        'callback' => [\theme_iiidem2\hook_listener::class, 'primary_extend'],
        'priority' => 100,
    ],
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => [\theme_iiidem2\hook_listener::class, 'before_http_headers'],
        'priority' => 100,
    ],
];
