<?php
// This file is part of Moodle - http://moodle.org/
//
// Local Razorpay payment simulator for development / UAT when real API keys
// are not configured. State-changing actions require POST + sesskey.
// Disabled on the live production host.

require_once(__DIR__ . '/../../../config.php');

use core_payment\helper as payment_helper;
use paygw_razorpay\razorpay_helper;

global $DB, $USER, $PAGE, $OUTPUT, $CFG;

require_login();

$host = (string) (parse_url($CFG->wwwroot ?? '', PHP_URL_HOST) ?: '');
// Live production must never complete enrolments via the mock simulator.
if (\paygw_razorpay\razorpay_helper::is_live_host() || $host === 'iiidemlms.eci.gov.in') {
    throw new moodle_exception('nopermissions', 'error', '', 'mock payment');
}

$orderid = trim(clean_param(required_param('orderid', PARAM_ALPHANUMEXT), PARAM_ALPHANUMEXT));
$action = optional_param('action', '', PARAM_ALPHA);
if ($orderid === '' || core_text::strlen($orderid) > 64) {
    throw new moodle_exception('invalidparameter', 'error');
}

$txn = $DB->get_record('paygw_razorpay_txn', ['orderid' => $orderid]);
if (!$txn) {
    throw new moodle_exception('txnnotfound', 'paygw_razorpay');
}

// Owner (or site admin) only.
if ((int) $txn->userid !== (int) $USER->id && !is_siteadmin()) {
    throw new require_login_exception('Invalid transaction user.');
}

// Only mock-created orders may be completed here.
if (strpos($orderid, 'order_mock_') !== 0) {
    throw new moodle_exception('nopermissions', 'error', '', 'mock payment');
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/payment/gateway/razorpay/mock.php', ['orderid' => $orderid]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mocktitle', 'paygw_razorpay'));

$continueurl = new moodle_url('/my/courses.php');
if ($txn->component === 'enrol_fee' && $txn->paymentarea === 'fee') {
    $courseid = $DB->get_field('enrol', 'courseid', ['id' => $txn->itemid]);
    if ($courseid) {
        $continueurl = new moodle_url('/course/view.php', ['id' => $courseid]);
    }
}

$successurl = payment_helper::get_success_url($txn->component, $txn->paymentarea, (int) $txn->itemid);
$successurl->param('razorpaypayment', 'success');

if ($action === 'pay' || $action === 'cancel') {
    require_sesskey();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new moodle_exception('invalidrequest', 'core');
    }

    if ($action === 'pay') {
        $paymentid = 'pay_mock_' . $txn->txnref;
        razorpay_helper::complete_transaction($txn, $paymentid);
        redirect($successurl, get_string('paymentsuccess', 'paygw_razorpay'), null, 'success');
    }

    razorpay_helper::mark_transaction_failed($txn, get_string('mockcancelbutton', 'paygw_razorpay'));
    redirect($continueurl, get_string('paymentfailed', 'paygw_razorpay'), null, 'error');
}

echo $OUTPUT->header();
echo html_writer::start_div('container py-5');
echo html_writer::start_div('row justify-content-center');
echo html_writer::start_div('col-md-8 col-lg-6');
echo html_writer::start_div('card shadow border-0');
echo html_writer::start_div('card-body p-4 p-md-5 text-center');

echo $OUTPUT->heading(get_string('mocktitle', 'paygw_razorpay'), 3);
echo html_writer::div(get_string('mocknotice', 'paygw_razorpay'), 'alert alert-info text-start');
echo html_writer::tag('p', get_string('coursefeepaymentlabel', 'theme_iiidem2') . ': ' .
    payment_helper::get_cost_as_string((float) $txn->amount, $txn->currency), ['class' => 'lead']);

$payurl = new moodle_url('/payment/gateway/razorpay/mock.php', ['orderid' => $orderid]);
$cancelurl = new moodle_url('/payment/gateway/razorpay/mock.php', ['orderid' => $orderid]);

echo html_writer::start_div('d-grid gap-2 mt-4');
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $payurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'pay']);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary btn-lg w-100',
    'value' => get_string('mockpaybutton', 'paygw_razorpay'),
]);
echo html_writer::end_tag('form');

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $cancelurl->out(false), 'class' => 'mt-2']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'cancel']);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-outline-secondary w-100',
    'value' => get_string('mockcancelbutton', 'paygw_razorpay'),
]);
echo html_writer::end_tag('form');
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
