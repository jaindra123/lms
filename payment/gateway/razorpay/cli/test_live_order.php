<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');

require_once($CFG->libdir . '/filelib.php');

global $DB;
$g = $DB->get_record('payment_gateways', ['gateway' => 'razorpay']);
$config = json_decode($g->config);
$keyid = trim($config->keyid ?? '');
$secret = trim($config->keysecret ?? '');
$env = $config->environment ?? 'test';

echo "environment={$env}\n";
echo "keyid={$keyid}\n";
echo "keyid_len=" . strlen($keyid) . "\n";
echo "secret_len=" . strlen($secret) . "\n";
$islive = str_starts_with($keyid, 'rzp_live_');
$istest = str_starts_with($keyid, 'rzp_test_');
echo "key_mode=" . ($islive ? 'live' : ($istest ? 'test' : 'unknown')) . "\n";
echo "env_matches=" . (($env === 'live') === $islive ? 'yes' : 'NO') . "\n\n";

$payload = json_encode([
    'amount' => 500,
    'currency' => 'INR',
    'receipt' => 'cli_test_' . time(),
]);

$curl = new curl();
$options = [
    'CURLOPT_USERPWD' => $keyid . ':' . $secret,
    'CURLOPT_HTTPHEADER' => ['Content-Type: application/json'],
];
$url = 'https://api.razorpay.com/v1/orders';
$raw = $curl->post($url, $payload, $options);
echo "HTTP create order response:\n{$raw}\n\n";

$decoded = json_decode($raw, true);
if (!empty($decoded['id'])) {
    $orderid = $decoded['id'];
    $fetch = $curl->get("https://api.razorpay.com/v1/orders/{$orderid}", [], $options);
    echo "HTTP fetch order response:\n{$fetch}\n";
}
