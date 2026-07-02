<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => [\local_iiidem_coursecalendar\hook_listener::class, 'before_http_headers'],
        'priority' => 90,
    ],
];
