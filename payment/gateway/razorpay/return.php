<?php
// This file is part of Moodle - http://moodle.org/
//
// Razorpay Payment Link callback. Completes enrolment only after signature
// and captured amount match the server-side course fee.

require_once(__DIR__ . '/../../../config.php');

use core_payment\helper as payment_helper;
use paygw_razorpay\razorpay_helper;

require_login();

global $DB, $USER, $PAGE;

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/payment/gateway/razorpay/return.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'paygw_razorpay'));

$clean = static function (string $name, int $maxlen): string {
    $value = trim(clean_param(optional_param($name, '', PARAM_RAW_TRIMMED), PARAM_ALPHANUMEXT));
    if ($value === '' || \core_text::strlen($value) > $maxlen) {
        return '';
    }
    return $value;
};

$paymentid = $clean('razorpay_payment_id', 64);
$linkid = $clean('razorpay_payment_link_id', 64);
$reference = $clean('razorpay_payment_link_reference_id', 64);
$status = strtolower(trim(clean_param(optional_param('razorpay_payment_link_status', '', PARAM_ALPHA), PARAM_ALPHA)));
$signature = $clean('razorpay_signature', 128);

$continueurl = new moodle_url('/my/courses.php');

$fail = static function (string $message, \moodle_url $url): void {
    redirect($url, $message, null, \core\output\notification::NOTIFY_ERROR);
};

$txn = null;
if ($reference !== '') {
    $txn = $DB->get_record('paygw_razorpay_txn', ['txnref' => $reference]);
}
if (!$txn && $linkid !== '') {
    $txn = $DB->get_record('paygw_razorpay_txn', ['orderid' => $linkid]);
}

if ($txn && $txn->component === 'enrol_fee' && $txn->paymentarea === 'fee') {
    $courseid = (int) $DB->get_field('enrol', 'courseid', ['id' => (int) $txn->itemid]);
    if ($courseid) {
        $continueurl = new moodle_url('/course/view.php', ['id' => $courseid]);
    }
}

if (!$txn) {
    $fail(get_string('txnnotfound', 'paygw_razorpay'), $continueurl);
}

if ((int) $txn->userid !== (int) $USER->id && !is_siteadmin()) {
    $fail(get_string('paymentfailed', 'paygw_razorpay'), $continueurl);
}

$successurl = payment_helper::get_success_url($txn->component, $txn->paymentarea, (int) $txn->itemid);
$successurl->param('razorpaypayment', 'success');

if (($txn->status ?? '') === 'completed') {
    redirect($successurl);
}

if ($paymentid === '' || $linkid === '' || $reference === '' || $signature === '' || $status !== 'paid') {
    razorpay_helper::mark_transaction_failed($txn, get_string('paymentfailed', 'paygw_razorpay'));
    $fail(get_string('paymentfailed', 'paygw_razorpay'), $continueurl);
}

$config = (object) payment_helper::get_gateway_configuration(
    $txn->component,
    $txn->paymentarea,
    (int) $txn->itemid,
    'razorpay'
);

$verified = razorpay_helper::verify_payment_link_signature(
    $linkid,
    $reference,
    $status,
    $paymentid,
    $signature,
    (string) ($config->keysecret ?? '')
);

if (!$verified) {
    razorpay_helper::mark_transaction_failed($txn, get_string('invalidsignature', 'paygw_razorpay'));
    $fail(get_string('invalidsignature', 'paygw_razorpay'), $continueurl);
}

try {
    razorpay_helper::assert_txn_matches_payable($txn);
    razorpay_helper::assert_remote_payment_link_matches_txn($config, $txn, $linkid);
    razorpay_helper::assert_remote_payment_matches_txn($config, $txn, $paymentid);
} catch (\moodle_exception $e) {
    razorpay_helper::mark_transaction_failed($txn, $e->getMessage());
    $fail(get_string('amountmismatch', 'paygw_razorpay'), $continueurl);
}

razorpay_helper::complete_transaction($txn, $paymentid);
redirect($successurl, get_string('paymentsuccess', 'paygw_razorpay'), null, \core\output\notification::NOTIFY_SUCCESS);
