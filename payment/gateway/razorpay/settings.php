<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('paygw_razorpay_settings', '', get_string('pluginname_desc', 'paygw_razorpay')));
    \core_payment\helper::add_common_gateway_settings($settings, 'paygw_razorpay');

    $settings->add(new admin_setting_heading(
        'paygw_razorpay_invoiceheading',
        get_string('invoicetitle', 'paygw_razorpay'),
        ''
    ));
    $settings->add(new admin_setting_configtext(
        'paygw_razorpay/invoiceorgname',
        get_string('invoiceorgname', 'paygw_razorpay'),
        get_string('invoiceorgname_desc', 'paygw_razorpay'),
        '',
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtextarea(
        'paygw_razorpay/invoiceaddress',
        get_string('invoiceaddress', 'paygw_razorpay'),
        get_string('invoiceaddress_desc', 'paygw_razorpay'),
        ''
    ));
    $settings->add(new admin_setting_configtext(
        'paygw_razorpay/invoicegstin',
        get_string('invoicegstin', 'paygw_razorpay'),
        get_string('invoicegstin_desc', 'paygw_razorpay'),
        '',
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtext(
        'paygw_razorpay/invoicesupport',
        get_string('invoicesupport', 'paygw_razorpay'),
        get_string('invoicesupport_desc', 'paygw_razorpay'),
        '',
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtextarea(
        'paygw_razorpay/invoicefooter',
        get_string('invoicefooter', 'paygw_razorpay'),
        get_string('invoicefooter_desc', 'paygw_razorpay'),
        ''
    ));
}
