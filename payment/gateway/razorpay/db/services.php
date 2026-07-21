<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'paygw_razorpay_get_checkout_data' => [
        'classname'   => 'paygw_razorpay\external\get_checkout_data',
        'classpath'   => '',
        'description' => 'Creates a Razorpay order and returns checkout data.',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'paygw_razorpay_verify_payment' => [
        'classname'   => 'paygw_razorpay\external\verify_payment',
        'classpath'   => '',
        'description' => 'Verifies Razorpay payment signature and completes enrolment.',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
    'paygw_razorpay_report_payment_failure' => [
        'classname'   => 'paygw_razorpay\external\report_payment_failure',
        'classpath'   => '',
        'description' => 'Reports a cancelled or failed Razorpay checkout and emails user/admins.',
        'type'        => 'write',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
