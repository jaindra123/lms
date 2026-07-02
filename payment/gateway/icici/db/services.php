<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'paygw_icici_get_redirect_form' => [
        'classname'   => 'paygw_icici\external\get_redirect_form',
        'classpath'   => '',
        'description' => 'Returns ICICI redirect form data for a payment.',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
