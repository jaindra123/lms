<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_iiidem_coursecalendar',
        get_string('pluginname', 'local_iiidem_coursecalendar')
    );

    $settings->add(new admin_setting_heading(
        'local_iiidem_coursecalendar/googleheading',
        get_string('googlesettingsheading', 'local_iiidem_coursecalendar'),
        get_string('googlesettingsheading_desc', 'local_iiidem_coursecalendar')
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_iiidem_coursecalendar/google_service_account_json',
        get_string('google_service_account_json', 'local_iiidem_coursecalendar'),
        get_string('google_service_account_json_desc', 'local_iiidem_coursecalendar'),
        '',
        PARAM_RAW,
        8,
        80
    ));

    $settings->add(new admin_setting_configtext(
        'local_iiidem_coursecalendar/google_calendar_id',
        get_string('google_calendar_id', 'local_iiidem_coursecalendar'),
        get_string('google_calendar_id_desc', 'local_iiidem_coursecalendar'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_iiidem_coursecalendar/google_impersonate_email',
        get_string('google_impersonate_email', 'local_iiidem_coursecalendar'),
        get_string('google_impersonate_email_desc', 'local_iiidem_coursecalendar'),
        '',
        PARAM_EMAIL
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_iiidem_coursecalendar/autowebexgoogle',
        get_string('autowebexgoogle', 'local_iiidem_coursecalendar'),
        get_string('autowebexgoogle_desc', 'local_iiidem_coursecalendar'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_iiidem_coursecalendar/urlliveduration',
        get_string('urlliveduration', 'local_iiidem_coursecalendar'),
        get_string('urlliveduration_desc', 'local_iiidem_coursecalendar'),
        '60',
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
