<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/enrol/fee/lib.php');
require_once($CFG->dirroot . '/payment/classes/helper.php');

$courseid = (int) ($argv[1] ?? 4);
$cost = (float) ($argv[2] ?? 15000);

$course = get_course($courseid);
cli_heading('PNB course fee setup for course ' . $courseid);

global $CFG;

if (!enrol_is_enabled('fee')) {
    $enabled = array_filter(explode(',', $CFG->enrol_plugins_enabled ?? ''));
    if (!in_array('fee', $enabled, true)) {
        $enabled[] = 'fee';
        set_config('enrol_plugins_enabled', implode(',', $enabled));
        cli_writeln('Enabled enrol_fee plugin.');
    }
}

$account = $DB->get_record('payment_accounts', ['idnumber' => 'iiidem-pnb'], '*', IGNORE_MISSING);
if (!$account) {
    $account = \core_payment\helper::save_payment_account((object) [
        'name' => 'IIIDEM PNB',
        'idnumber' => 'iiidem-pnb',
        'enabled' => 1,
    ]);
    cli_writeln('Created payment account id ' . $account->get('id'));
} else {
    $account = new \core_payment\account($account);
    cli_writeln('Using payment account id ' . $account->get('id'));
}

$accountid = $account->get('id');
$existing = $DB->get_record('payment_gateways', ['accountid' => $accountid, 'gateway' => 'pnb']);

if (!$existing) {
    \core_payment\helper::save_payment_gateway((object) [
        'accountid' => $accountid,
        'gateway' => 'pnb',
        'enabled' => 1,
        'config' => json_encode([
            'brandname' => 'IIIDEM LMS',
            'merchantid' => 'MERCHANT_ID',
            'secretkey' => 'CHANGE_ME',
            'gatewayurl' => 'https://gateway.example.pnb.in/pay',
            'testgatewayurl' => (new moodle_url('/payment/gateway/pnb/mock.php'))->out(false),
            'environment' => 'test',
            'surcharge' => 0,
        ]),
    ]);
    cli_writeln('Created and enabled PNB gateway.');
} else {
    $existing->enabled = 1;
    $DB->update_record('payment_gateways', $existing);
    cli_writeln('PNB gateway already exists — enabled.');
}

$instance = $DB->get_record('enrol', ['courseid' => $courseid, 'enrol' => 'fee']);
$plugin = enrol_get_plugin('fee');
$studentrole = $DB->get_field('role', 'id', ['shortname' => 'student']);

if (!$instance) {
    $instanceid = $plugin->add_instance($course, [
        'status' => ENROL_INSTANCE_ENABLED,
        'roleid' => $studentrole,
        'cost' => $cost,
        'currency' => 'INR',
        'customint1' => $accountid,
        'enrolperiod' => 0,
    ]);
    cli_writeln('Added fee enrolment instance id ' . $instanceid . ' — INR ' . $cost);
} else {
    $instance->cost = $cost;
    $instance->currency = 'INR';
    $instance->customint1 = $accountid;
    $instance->status = ENROL_INSTANCE_ENABLED;
    $DB->update_record('enrol', $instance);
    cli_writeln('Updated fee enrolment instance id ' . $instance->id . ' — INR ' . $cost);
}

cli_writeln('Configure merchant credentials: Site admin → Payments → Accounts → IIIDEM PNB');
