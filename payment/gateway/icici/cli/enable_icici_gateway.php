<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/payment/classes/helper.php');

cli_heading('Enable ICICI payment gateway');

global $DB, $CFG;

$account = $DB->get_record('payment_accounts', ['idnumber' => 'iiidem-pnb'], '*', MUST_EXIST);
$accountid = (int) $account->id;
cli_writeln('Payment account id ' . $accountid);

$testurl = rtrim($CFG->wwwroot, '/') . '/payment/gateway/icici/mock.php';
$existing = $DB->get_record('payment_gateways', ['accountid' => $accountid, 'gateway' => 'icici']);

$config = json_encode([
    'brandname' => 'IIIDEM LMS',
    'merchantid' => 'MERCHANT_ID',
    'submerchantid' => '',
    'secretkey' => 'CHANGE_ME',
    'gatewayurl' => 'https://gateway.example.icici.in/pay',
    'testgatewayurl' => $testurl,
    'environment' => 'test',
    'surcharge' => 0,
]);

if (!$existing) {
    \core_payment\helper::save_payment_gateway((object) [
        'accountid' => $accountid,
        'gateway' => 'icici',
        'enabled' => 1,
        'config' => $config,
    ]);
    cli_writeln('Created and enabled ICICI gateway.');
} else {
    $existing->enabled = 1;
    $DB->update_record('payment_gateways', $existing);
    cli_writeln('ICICI gateway already exists — enabled.');
}

cli_writeln('Configure live credentials: Site admin → Payments → Accounts → IIIDEM PNB → ICICI');
