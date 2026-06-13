<?php
// This file is part of Moodle - http://moodle.org/
//
// Local PNB payment simulator for development / UAT when real bank URLs are not configured.

require_once(__DIR__ . '/../../../config.php');

use core_payment\helper as payment_helper;
use paygw_pnb\pnb_helper;

global $DB, $USER, $PAGE, $OUTPUT;

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/payment/gateway/pnb/mock.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('mocktitle', 'paygw_pnb'));
$renderer = $PAGE->get_renderer('core');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $continueurl = new moodle_url('/my/courses.php');
    $pendings = $DB->get_records('paygw_pnb_txn', ['userid' => $USER->id, 'status' => 'pending'], 'timecreated DESC', '*', 0, 1);
    if ($pending = reset($pendings)) {
        if ($pending->component === 'enrol_fee' && $pending->paymentarea === 'fee') {
            $courseid = $DB->get_field('enrol', 'courseid', ['id' => $pending->itemid]);
            if ($courseid) {
                $continueurl = new moodle_url('/course/view.php', ['id' => $courseid]);
            }
        }
    }

    echo pnb_helper::render_result_page(
        $renderer,
        'info',
        get_string('mocksessionexpiredheading', 'paygw_pnb'),
        get_string('mocksessionexpired', 'paygw_pnb'),
        $continueurl
    );
    exit;
}

$postparams = [];
foreach ($_POST as $key => $value) {
    if (is_string($value)) {
        $postparams[strtoupper($key)] = $value;
    }
}

$txnref = $postparams['TXNREFNO'] ?? '';
$returnurl = $postparams['RETURNURL'] ?? '';

if ($txnref === '' || $returnurl === '') {
    throw new moodle_exception('txnnotfound', 'paygw_pnb');
}

$txn = $DB->get_record('paygw_pnb_txn', ['txnref' => $txnref], '*', MUST_EXIST);

$amount = number_format((float) $txn->amount, 2, '.', '');
$currency = $txn->currency ?: ($postparams['CURRENCYCODE'] ?? 'INR');
$merchantid = $postparams['MERCHANTID'] ?? '';
$description = $postparams['DESCRIPTION'] ?? '';

if ((int) $txn->userid !== (int) $USER->id && !is_siteadmin()) {
    throw new require_login_exception('Invalid transaction user.');
}

$config = (object) payment_helper::get_gateway_configuration(
    $txn->component,
    $txn->paymentarea,
    (int) $txn->itemid,
    'pnb'
);

$action = optional_param('action', '', PARAM_ALPHA);

if ($action === 'pay' || $action === 'cancel') {
    require_sesskey();

    $status = $action === 'pay' ? 'SUCCESS' : 'FAILED';
    $bankref = $action === 'pay' ? ('MOCK' . time()) : '';
    $payload = implode('|', [$merchantid, $txnref, $amount, $currency, $status, $bankref]);
    $checksum = hash_hmac('sha256', $payload, $config->secretkey ?? '');

    $returnfields = [
        'MERCHANTID' => $merchantid,
        'TXNREFNO' => $txnref,
        'AMOUNT' => $amount,
        'CURRENCYCODE' => $currency,
        'STATUS' => $status,
        'BANKREF' => $bankref,
        'CHECKSUM' => $checksum,
    ];

    echo $renderer->header();
    echo $renderer->heading(get_string('mockredirecting', 'paygw_pnb'), 3);
    echo html_writer::start_tag('form', [
        'id' => 'pnb-mock-return',
        'method' => 'post',
        'action' => $returnurl,
    ]);
    foreach ($returnfields as $name => $value) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
    }
    echo html_writer::end_tag('form');
    echo html_writer::script('document.getElementById("pnb-mock-return").submit();');
    echo $renderer->footer();
    exit;
}

echo $renderer->header();
echo $renderer->notification(get_string('mocknotice', 'paygw_pnb'), 'info');
echo $renderer->heading(get_string('mocktitle', 'paygw_pnb'), 2);
echo html_writer::tag('p', get_string('mockinstructions', 'paygw_pnb'), ['class' => 'text-muted']);
echo html_writer::tag('p', format_string($description));
echo html_writer::tag('p', html_writer::tag('strong', $currency . ' ' . $amount), ['class' => 'lead']);
echo html_writer::tag('p', get_string('mocktxnref', 'paygw_pnb', s($txnref)), ['class' => 'text-muted small']);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'mt-4']);
foreach ($postparams as $name => $value) {
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $name, 'value' => $value]);
}
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::start_tag('div', ['class' => 'd-grid gap-2']);
echo html_writer::tag('button', get_string('mockpaybutton', 'paygw_pnb'), [
    'type' => 'submit',
    'name' => 'action',
    'value' => 'pay',
    'class' => 'btn btn-success btn-lg',
]);
echo html_writer::tag('button', get_string('mockcancelbutton', 'paygw_pnb'), [
    'type' => 'submit',
    'name' => 'action',
    'value' => 'cancel',
    'class' => 'btn btn-outline-secondary',
]);
echo html_writer::end_tag('div');
echo html_writer::end_tag('form');
echo $renderer->footer();
