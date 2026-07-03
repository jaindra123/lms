<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => [\local_iiidem_support\hook_listener::class, 'before_http_headers'],
        'priority' => 100,
    ],
    [
        'hook' => \core\hook\output\before_standard_head_html_generation::class,
        'callback' => [\local_iiidem_support\hook_listener::class, 'before_standard_head_html_generation'],
        'priority' => 100,
    ],
];
