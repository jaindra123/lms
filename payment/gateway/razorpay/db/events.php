<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core_payment\event\account_updated',
        'callback' => '\paygw_razorpay\observer::account_updated',
    ],
];
