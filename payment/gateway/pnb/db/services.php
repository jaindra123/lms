<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'paygw_pnb_get_redirect_form' => [
        'classname'   => 'paygw_pnb\external\get_redirect_form',
        'classpath'   => '',
        'description' => 'Returns PNB IPG redirect form data for a payment.',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
