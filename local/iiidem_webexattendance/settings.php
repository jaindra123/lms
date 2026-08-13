<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_iiidem_webexattendance',
        get_string('pluginname', 'local_iiidem_webexattendance')
    );

    $settings->add(new admin_setting_heading(
        'local_iiidem_webexattendance/oauthheading',
        get_string('settingsheading', 'local_iiidem_webexattendance'),
        get_string('settingsheading_desc', 'local_iiidem_webexattendance')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_iiidem_webexattendance/enabled',
        get_string('enabled', 'local_iiidem_webexattendance'),
        get_string('enabled_desc', 'local_iiidem_webexattendance'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_iiidem_webexattendance/autosync',
        get_string('autosync', 'local_iiidem_webexattendance'),
        get_string('autosync_desc', 'local_iiidem_webexattendance'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_iiidem_webexattendance/clientid',
        get_string('clientid', 'local_iiidem_webexattendance'),
        get_string('clientid_desc', 'local_iiidem_webexattendance'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_iiidem_webexattendance/clientsecret',
        get_string('clientsecret', 'local_iiidem_webexattendance'),
        get_string('clientsecret_desc', 'local_iiidem_webexattendance'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_iiidem_webexattendance/orgid',
        get_string('orgid', 'local_iiidem_webexattendance'),
        get_string('orgid_desc', 'local_iiidem_webexattendance'),
        '',
        PARAM_TEXT
    ));

    $redirect = (new moodle_url('/local/iiidem_webexattendance/oauth.php'))->out(false);
    $settings->add(new admin_setting_description(
        'local_iiidem_webexattendance/redirecturi',
        get_string('redirecturi', 'local_iiidem_webexattendance'),
        get_string('redirecturi_desc', 'local_iiidem_webexattendance') . '<br><code>' . s($redirect) . '</code>'
    ));

    $connected = \local_iiidem_webexattendance\oauth::is_connected();
    $status = $connected
        ? get_string('connected', 'local_iiidem_webexattendance')
        : get_string('notconnected', 'local_iiidem_webexattendance');
    $statushtml = html_writer::div(s($status), 'mb-2');
    if (\local_iiidem_webexattendance\oauth::is_configured()) {
        $connecturl = new moodle_url('/local/iiidem_webexattendance/oauth.php', [
            'action' => 'connect',
            'sesskey' => sesskey(),
        ]);
        $statushtml .= html_writer::link(
            $connecturl,
            get_string('connectwebex', 'local_iiidem_webexattendance'),
            ['class' => 'btn btn-primary']
        );
    }
    $settings->add(new admin_setting_description(
        'local_iiidem_webexattendance/connectionstatus',
        get_string('connectionstatus', 'local_iiidem_webexattendance'),
        html_writer::div($statushtml, 'local-iiidem-webexattendance-connect')
    ));

    $settings->add(new admin_setting_heading(
        'local_iiidem_webexattendance/thresholdsheading',
        get_string('thresholdsheading', 'local_iiidem_webexattendance'),
        get_string('thresholdsheading_desc', 'local_iiidem_webexattendance')
    ));

    $settings->add(new admin_setting_configtext(
        'local_iiidem_webexattendance/presentminutes',
        get_string('presentminutes', 'local_iiidem_webexattendance'),
        get_string('presentminutes_desc', 'local_iiidem_webexattendance'),
        '30',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_iiidem_webexattendance/lateminutes',
        get_string('lateminutes', 'local_iiidem_webexattendance'),
        get_string('lateminutes_desc', 'local_iiidem_webexattendance'),
        '10',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_iiidem_webexattendance/graceafterend',
        get_string('graceafterend', 'local_iiidem_webexattendance'),
        get_string('graceafterend_desc', 'local_iiidem_webexattendance'),
        '20',
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_iiidem_webexattendance/recordingsheading',
        get_string('recordingsheading', 'local_iiidem_webexattendance'),
        get_string('recordingsheading_desc', 'local_iiidem_webexattendance')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_iiidem_webexattendance/importrecordings',
        get_string('importrecordings', 'local_iiidem_webexattendance'),
        get_string('importrecordings_desc', 'local_iiidem_webexattendance'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_iiidem_webexattendance/recordinggraceafterend',
        get_string('recordinggraceafterend', 'local_iiidem_webexattendance'),
        get_string('recordinggraceafterend_desc', 'local_iiidem_webexattendance'),
        '60',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_iiidem_webexattendance/recordingmaxdays',
        get_string('recordingmaxdays', 'local_iiidem_webexattendance'),
        get_string('recordingmaxdays_desc', 'local_iiidem_webexattendance'),
        '7',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configselect(
        'local_iiidem_webexattendance/recordingaccesstype',
        get_string('recordingaccesstype', 'local_iiidem_webexattendance'),
        get_string('recordingaccesstype_desc', 'local_iiidem_webexattendance'),
        'request',
        [
            'request' => get_string('recordingaccess_request', 'local_iiidem_webexattendance'),
            'public' => get_string('recordingaccess_public', 'local_iiidem_webexattendance'),
        ]
    ));

    $ADMIN->add('localplugins', $settings);
}
