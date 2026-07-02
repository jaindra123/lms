<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');

global $DB;

$amount = isset($argv[1]) ? (float) $argv[1] : 5.0;
if ($amount <= 0) {
    echo "Usage: php set_course_fee_amount.php [amount]\n";
    exit(1);
}
$g = $DB->get_record('payment_gateways', ['gateway' => 'razorpay']);
if (!$g) {
    echo "Razorpay gateway not found.\n";
    exit(1);
}

$config = json_decode($g->config, true) ?: [];
$config['coursefeeamount'] = $amount;
$g->config = json_encode($config);
$DB->update_record('payment_gateways', $g);

\paygw_razorpay\course_fee_amount::sync_enrol_fee_instances((int) $g->accountid, $amount);

echo "Course fee amount set to {$amount} INR.\n";
