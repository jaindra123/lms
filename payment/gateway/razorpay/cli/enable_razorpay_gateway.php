<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/payment/classes/helper.php');

cli_heading('Enable Razorpay payment gateway');

global $DB, $CFG;

$account = $DB->get_record('payment_accounts', ['idnumber' => 'iiidem-pnb'], '*', IGNORE_MISSING);
if (!$account) {
    $account = $DB->get_record('payment_accounts', [], '*', IGNORE_MULTIPLE);
}
if (!$account) {
    cli_error('No payment account found. Create one in Site administration → Payments → Payment accounts.');
}

$accountid = (int) $account->id;
cli_writeln('Payment account id ' . $accountid . ' (' . $account->name . ')');

$mockkey = 'rzp_test_mock';
$config = json_encode([
    'brandname' => 'IIIDEM LMS',
    'keyid' => $mockkey,
    'keysecret' => 'CHANGE_ME',
    'environment' => 'test',
    'surcharge' => 0,
]);

$existing = $DB->get_record('payment_gateways', ['accountid' => $accountid, 'gateway' => 'razorpay']);

if (!$existing) {
    \core_payment\helper::save_payment_gateway((object) [
        'accountid' => $accountid,
        'gateway' => 'razorpay',
        'enabled' => 1,
        'config' => $config,
    ]);
    cli_writeln('Created and enabled Razorpay gateway (mock test keys).');
} else {
    $existing->enabled = 1;
    $DB->update_record('payment_gateways', $existing);
    cli_writeln('Razorpay gateway already exists — enabled.');
}

cli_writeln('Configure live/test API keys: Site administration → Payments → Accounts → ' . $account->name . ' → Razorpay');
