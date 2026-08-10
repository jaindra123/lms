<?php
require_once(__DIR__ . '/../../../config.php');

use core_payment\helper as payment_helper;
use paygw_icici\icici_helper;

global $DB, $USER, $PAGE;

require_login();

$params = \theme_iiidem2\input_validation::clean_payment_params(array_merge($_GET, $_POST));

$txnref = icici_helper::extract_order_id($params);
$status = (string) ($params['STATUS'] ?? $params['RESPONSECODE'] ?? '');
$bankref = (string) ($params['BANKREF'] ?? $params['UNIQUEREFNUMBER'] ?? '');
if (\core_text::strlen($bankref) > 255) {
    $bankref = \core_text::substr($bankref, 0, 255);
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/payment/gateway/icici/return.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('pluginname', 'paygw_icici'));

$renderer = $PAGE->get_renderer('core');

$show_return_message = function(string $type, string $heading, string $message, \moodle_url $continueurl) use ($renderer): void {
    echo icici_helper::render_result_page($renderer, $type, $heading, $message, $continueurl);
    exit;
};

$defaultcontinue = new moodle_url('/my/courses.php');
$pendings = $DB->get_records('paygw_icici_txn', ['userid' => $USER->id], 'timecreated DESC', '*', 0, 1);
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
        get_string('mocksessionexpiredheading', 'paygw_icici'),
        get_string('returnnosession', 'paygw_icici'),
        $defaultcontinue
    );
}

$txn = $DB->get_record('paygw_icici_txn', ['txnref' => $txnref]);
if (!$txn) {
    $show_return_message(
        'error',
        get_string('paymentresultfailheading', 'paygw_icici'),
        get_string('txnnotfound', 'paygw_icici'),
        $defaultcontinue
    );
}

if ((int) $txn->userid !== (int) $USER->id && !is_siteadmin()) {
    $show_return_message(
        'error',
        get_string('paymentresultfailheading', 'paygw_icici'),
        get_string('paymentfailed', 'paygw_icici'),
        $defaultcontinue
    );
}

$config = (object) payment_helper::get_gateway_configuration(
    $txn->component,
    $txn->paymentarea,
    (int) $txn->itemid,
    'icici'
);

$redirecturl = payment_helper::get_success_url($txn->component, $txn->paymentarea, (int) $txn->itemid);
$redirecturl->param('icicipayment', 'success');

if ($txn->status === 'completed') {
    redirect($redirecturl, get_string('paymentsuccess', 'paygw_icici'), null, 'success');
}

if ($status === '') {
    $show_return_message(
        'info',
        get_string('mocksessionexpiredheading', 'paygw_icici'),
        get_string('returnnosession', 'paygw_icici'),
        $defaultcontinue
    );
}

$verified = icici_helper::verify_return($config, $params);
$returnedamount = $params['AMOUNT'] ?? $params['Amount'] ?? $params['amount'] ?? $params['TOTALAMOUNT'] ?? '';
$success = $verified && icici_helper::is_success_status($status);

// Fail closed: amount must be present and match the server-stored txn amount.
if (!$returnedamount || !icici_helper::amounts_match($returnedamount, (float) $txn->amount)) {
    $show_return_message(
        'error',
        get_string('paymentresultfailheading', 'paygw_icici'),
        get_string('amountmismatch', 'paygw_icici'),
        $defaultcontinue
    );
}

try {
    icici_helper::assert_txn_matches_payable($txn);
} catch (moodle_exception $e) {
    $show_return_message(
        'error',
        get_string('paymentresultfailheading', 'paygw_icici'),
        get_string('amountmismatch', 'paygw_icici'),
        $defaultcontinue
    );
}

if (!$verified) {
    $show_return_message(
        'error',
        get_string('paymentresultfailheading', 'paygw_icici'),
        get_string('invalidchecksum', 'paygw_icici'),
        $defaultcontinue
    );
}

if (!$success) {
    $show_return_message(
        'error',
        get_string('paymentresultfailheading', 'paygw_icici'),
        get_string('paymentfailed', 'paygw_icici'),
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
        'icici'
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
    $DB->update_record('paygw_icici_txn', $txn);

    redirect($redirecturl, get_string('paymentsuccess', 'paygw_icici'), null, 'success');
} catch (Exception $e) {
    error_log('ICICI payment completion error: ' . $e->getMessage());
    $show_return_message(
        'error',
        get_string('paymentresultfailheading', 'paygw_icici'),
        get_string('paymentfailed', 'paygw_icici'),
        $defaultcontinue
    );
}
