<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_iiidem_support',
        get_string('pluginname', 'local_iiidem_support'),
        new moodle_url('/local/iiidem_support/manage.php'),
        'local/iiidem_support:manage'
    ));
}
