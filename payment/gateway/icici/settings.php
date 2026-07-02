<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('paygw_icici_settings', '', get_string('pluginname_desc', 'paygw_icici')));
    \core_payment\helper::add_common_gateway_settings($settings, 'paygw_icici');
}
