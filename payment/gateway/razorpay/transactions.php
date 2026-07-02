<?php
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('paygw_razorpay_transactions');

$PAGE->set_url(new moodle_url('/payment/gateway/razorpay/transactions.php'));
$PAGE->set_title(get_string('transactionhistory', 'paygw_razorpay'));
$PAGE->set_heading(get_string('transactionhistory', 'paygw_razorpay'));
$PAGE->set_secondary_active_tab('siteadminnode');
$PAGE->set_primary_active_tab('siteadminnode');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('transactionhistory', 'paygw_razorpay'), 2);
echo html_writer::div(get_string('transactionhistorydesc', 'paygw_razorpay'), 'mb-3 text-muted');

$sql = "SELECT t.*,
               u.username,
               u.firstname,
               u.lastname,
               u.firstnamephonetic,
               u.lastnamephonetic,
               u.middlename,
               u.alternatename,
               u.email,
               e.courseid,
               c.fullname AS coursename,
               c.shortname AS courseshortname
          FROM {paygw_razorpay_txn} t
          JOIN {user} u ON u.id = t.userid
     LEFT JOIN {enrol} e ON e.id = t.itemid AND t.component = 'enrol_fee' AND t.paymentarea = 'fee'
     LEFT JOIN {course} c ON c.id = e.courseid
      ORDER BY t.timecreated DESC";

$records = $DB->get_records_sql($sql);

$table = new html_table();
$table->head = [
    get_string('date'),
    get_string('fullnameuser'),
    get_string('username'),
    get_string('course'),
    get_string('cost', 'core'),
    get_string('status'),
    get_string('paymenttxnreflabel', 'paygw_razorpay'),
    get_string('orderreference', 'paygw_razorpay'),
    get_string('paymentreference', 'paygw_razorpay'),
];
$table->attributes['class'] = 'generaltable table-striped w-auto';
$table->data = [];

foreach ($records as $row) {
    $userlink = html_writer::link(
        new moodle_url('/user/profile.php', ['id' => $row->userid]),
        fullname($row)
    );
    $courselabel = $row->coursename
        ? format_string($row->coursename)
        : get_string('unknowncourse', 'paygw_razorpay');
    if (!empty($row->courseid)) {
        $courselabel = html_writer::link(
            new moodle_url('/course/view.php', ['id' => $row->courseid]),
            $courselabel
        );
    }

    $statuslabel = $row->status === 'completed'
        ? html_writer::span(get_string('paymentstatuscompleted', 'paygw_razorpay'), 'badge bg-success text-white')
        : html_writer::span(get_string('paymentstatuspending', 'paygw_razorpay'), 'badge bg-warning text-dark');

    $table->data[] = [
        userdate($row->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        $userlink,
        s($row->username),
        $courselabel,
        s($row->currency . ' ' . number_format((float) $row->amount, 2)),
        $statuslabel,
        s($row->txnref),
        s($row->orderid ?? '-'),
        s($row->paymentid ?? '-'),
    ];
}

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('notransactions', 'paygw_razorpay'), 'info');
} else {
    echo html_writer::div(html_writer::table($table), 'table-responsive');
    echo html_writer::div(get_string('transactioncount', 'paygw_razorpay', count($table->data)), 'mt-2 text-muted small');
}

echo html_writer::start_div('mt-4');
echo html_writer::link(new moodle_url('/payment/accounts.php'), get_string('paymentaccounts', 'payment'));
echo ' | ';
echo html_writer::link(
    new moodle_url('/payment/gateway/pnb/transactions.php'),
    get_string('viewpnbtransactions', 'paygw_razorpay')
);
echo ' | ';
echo html_writer::link(
    new moodle_url('/payment/gateway/icici/transactions.php'),
    get_string('viewicicihistory', 'paygw_razorpay')
);
echo html_writer::end_div();

echo $OUTPUT->footer();
