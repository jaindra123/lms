<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/payment/gateway/pnb/classes/pnb_helper.php');

$gw = $DB->get_record('payment_gateways', ['gateway' => 'pnb'], '*', IGNORE_MISSING);
if (!$gw) {
    cli_error('PNB gateway not found.');
}

$config = json_decode($gw->config, true) ?: [];
$mockurl = (new moodle_url('/payment/gateway/pnb/mock.php'))->out(false);

cli_heading('PNB gateway config check (id=' . $gw->id . ')');
cli_writeln('Enabled: ' . ($gw->enabled ? 'yes' : 'NO'));
cli_writeln('Environment: ' . ($config['environment'] ?? '(missing)'));
cli_writeln('Merchant ID: ' . ($config['merchantid'] ?? '(missing)'));
cli_writeln('Secret key set: ' . (!empty($config['secretkey']) ? 'yes (' . strlen($config['secretkey']) . ' chars)' : 'NO'));
cli_writeln('Test gateway URL: ' . ($config['testgatewayurl'] ?? '(missing)'));
cli_writeln('Expected mock URL: ' . $mockurl);

$testurl = $config['testgatewayurl'] ?? '';
if ($testurl !== $mockurl) {
    if (str_ends_with($testurl, 'mock.ph')) {
        cli_writeln('WARNING: Test URL ends with mock.ph — fix to mock.php');
    } else {
        cli_writeln('NOTE: Test URL differs from local mock (OK if using real PNB UAT URL).');
    }
}

$resolved = paygw_pnb\pnb_helper::resolve_gateway_url($testurl);
cli_writeln('Resolved redirect URL: ' . $resolved);

cli_writeln('');
cli_writeln('For local mock testing, use:');
cli_writeln('  Merchant ID: MERCHANT_ID (any value)');
cli_writeln('  Secret key: CHANGE_ME');
cli_writeln('  Test gateway URL: ' . $mockurl);
cli_writeln('  Environment: test');

$txns = $DB->get_records('paygw_pnb_txn', null, 'timecreated DESC', '*', 0, 5);
cli_writeln('');
cli_heading('Recent transactions');
foreach ($txns as $t) {
    cli_writeln("{$t->txnref} user={$t->userid} amount={$t->amount} status={$t->status}");
}
