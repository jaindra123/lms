<?php
require_once(__DIR__ . '/../../../config.php');

use core_payment\helper as payment_helper;
use paygw_razorpay\razorpay_helper;

global $DB, $USER, $PAGE, $OUTPUT;

require_login();

$orderid = required_param('orderid', PARAM_TEXT);
$action = optional_param('action', '', PARAM_ALPHA);

$txn = $DB->get_record('paygw_razorpay_txn', ['orderid' => $orderid]);
if (!$txn) {
    throw new moodle_exception('txnnotfound', 'paygw_razorpay');
}

if ((int) $txn->userid !== (int) $USER->id && !is_siteadmin()) {
    throw new require_login_exception('Invalid transaction user.');
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

if ($action === 'pay') {
    $paymentid = 'pay_mock_' . $txn->txnref;
    $signature = hash_hmac('sha256', $orderid . '|' . $paymentid, 'razorpay_mock_secret');
    razorpay_helper::complete_transaction($txn, $paymentid);
    redirect($successurl, get_string('paymentsuccess', 'paygw_razorpay'), null, 'success');
}

if ($action === 'cancel') {
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

$payurl = new moodle_url('/payment/gateway/razorpay/mock.php', ['orderid' => $orderid, 'action' => 'pay']);
$cancelurl = new moodle_url('/payment/gateway/razorpay/mock.php', ['orderid' => $orderid, 'action' => 'cancel']);

echo html_writer::start_div('d-grid gap-2 mt-4');
echo html_writer::link($payurl, get_string('mockpaybutton', 'paygw_razorpay'), ['class' => 'btn btn-primary btn-lg']);
echo html_writer::link($cancelurl, get_string('mockcancelbutton', 'paygw_razorpay'), ['class' => 'btn btn-outline-secondary']);
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
