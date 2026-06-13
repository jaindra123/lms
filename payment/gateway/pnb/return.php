<?php
require_once(__DIR__ . '/../../../config.php');

use core_payment\helper as payment_helper;
use paygw_pnb\pnb_helper;

global $DB, $USER, $PAGE;

require_login();

$params = array_merge($_GET, $_POST);
foreach ($params as $key => $value) {
    if (is_string($value)) {
        $params[strtoupper($key)] = $value;
    }
}

$txnref = $params['TXNREFNO'] ?? '';
$status = $params['STATUS'] ?? '';
$bankref = $params['BANKREF'] ?? '';

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/payment/gateway/pnb/return.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'paygw_pnb'));

$renderer = $PAGE->get_renderer('core');

/**
 * Show a friendly return-page message instead of throwing (Moodle sends HTTP 404 for pre-output exceptions).
 */
$show_return_message = function(string $type, string $heading, string $message, \moodle_url $continueurl) use ($renderer): void {
    echo pnb_helper::render_result_page($renderer, $type, $heading, $message, $continueurl);
    exit;
};

$defaultcontinue = new moodle_url('/my/courses.php');
$pendings = $DB->get_records('paygw_pnb_txn', ['userid' => $USER->id], 'timecreated DESC', '*', 0, 1);
if ($pending = reset($pendings)) {
    if ($pending->component === 'enrol_fee' && $pending->paymentarea === 'fee') {
        $courseid = $DB->get_field('enrol', 'courseid', ['id' => $pending->itemid]);
        if ($courseid) {
            $defaultcontinue = new moodle_url('/course/view.php', ['id' => $courseid]);
        }
    }
}

if ($txnref === '') {
    $show_return_message(
        'info',
        get_string('mocksessionexpiredheading', 'paygw_pnb'),
        get_string('returnnosession', 'paygw_pnb'),
        $defaultcontinue
    );
}

$txn = $DB->get_record('paygw_pnb_txn', ['txnref' => $txnref]);
if (!$txn) {
    $show_return_message(
        'error',
        get_string('paymentresultfailheading', 'paygw_pnb'),
        get_string('txnnotfound', 'paygw_pnb'),
        $defaultcontinue
    );
}

if ((int) $txn->userid !== (int) $USER->id && !is_siteadmin()) {
    $show_return_message(
        'error',
        get_string('paymentresultfailheading', 'paygw_pnb'),
        get_string('paymentfailed', 'paygw_pnb'),
        $defaultcontinue
    );
}

$config = (object) payment_helper::get_gateway_configuration(
    $txn->component,
    $txn->paymentarea,
    (int) $txn->itemid,
    'pnb'
);

$redirecturl = payment_helper::get_success_url($txn->component, $txn->paymentarea, (int) $txn->itemid);
$redirecturl->param('pnbpayment', 'success');

if ($txn->status === 'completed') {
    redirect($redirecturl, get_string('paymentsuccess', 'paygw_pnb'), null, 'success');
}

// Bank callback must include status and checksum (POST from mock or real PNB).
if ($status === '') {
    $show_return_message(
        'info',
        get_string('mocksessionexpiredheading', 'paygw_pnb'),
        get_string('returnnosession', 'paygw_pnb'),
        $defaultcontinue
    );
}

$verified = pnb_helper::verify_return($config, $params);
$returnedamount = $params['AMOUNT'] ?? $params['amount'] ?? '';
$success = $verified && pnb_helper::is_success_status($status);

if ($verified && $returnedamount !== '' && !pnb_helper::amounts_match($returnedamount, (float) $txn->amount)) {
    $show_return_message(
        'error',
        get_string('paymentresultfailheading', 'paygw_pnb'),
        get_string('amountmismatch', 'paygw_pnb'),
        $defaultcontinue
    );
}

if (!$verified) {
    $show_return_message(
        'error',
        get_string('paymentresultfailheading', 'paygw_pnb'),
        get_string('invalidchecksum', 'paygw_pnb'),
        $defaultcontinue
    );
}

if (!$success) {
    $show_return_message(
        'error',
        get_string('paymentresultfailheading', 'paygw_pnb'),
        get_string('paymentfailed', 'paygw_pnb'),
        $defaultcontinue
    );
}

try {
    $paymentid = payment_helper::save_payment(
        (int) $txn->accountid,
        $txn->component,
        $txn->paymentarea,
        (int) $txn->itemid,
        (int) $txn->userid,
        (float) $txn->amount,
        $txn->currency,
        'pnb'
    );

    payment_helper::deliver_order(
        $txn->component,
        $txn->paymentarea,
        (int) $txn->itemid,
        $paymentid,
        (int) $txn->userid
    );

    $txn->status = 'completed';
    $txn->bankref = $bankref;
    $txn->timemodified = time();
    $DB->update_record('paygw_pnb_txn', $txn);

    redirect($redirecturl, get_string('paymentsuccess', 'paygw_pnb'), null, 'success');
} catch (Exception $e) {
    debugging('PNB payment completion error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    $show_return_message(
        'error',
        get_string('paymentresultfailheading', 'paygw_pnb'),
        get_string('paymentfailed', 'paygw_pnb'),
        $defaultcontinue
    );
}
