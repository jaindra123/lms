<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('paygw_razorpay_settings', '', get_string('pluginname_desc', 'paygw_razorpay')));
    \core_payment\helper::add_common_gateway_settings($settings, 'paygw_razorpay');
}
