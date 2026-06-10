<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('paygw_pnb_settings', '', get_string('pluginname_desc', 'paygw_pnb')));
    \core_payment\helper::add_common_gateway_settings($settings, 'paygw_pnb');
}
