<?php
$string['pluginname'] = 'Razorpay Payment Gateway';
$string['pluginname_desc'] = 'Razorpay payment gateway for course fee collection via UPI, cards, net banking, and wallets.';
$string['gatewayname'] = 'Razorpay';
$string['gatewaydescription'] = 'Pay your course fee securely through Razorpay (UPI, card, net banking, wallet).';
$string['brandname'] = 'Brand name';
$string['brandname_help'] = 'Name shown to the payer on the Razorpay checkout screen.';
$string['keyid'] = 'Key ID';
$string['keyid_help'] = 'Razorpay Key ID from Dashboard → Settings → API Keys (use rzp_test_* for test mode).';
$string['keysecret'] = 'Key secret';
$string['keysecret_help'] = 'Razorpay Key Secret from the same API Keys page. Keep this confidential.';
$string['environment'] = 'Environment';
$string['environment_help'] = 'Must match your API keys: Test with rzp_test_* keys, Live with rzp_live_* keys. After switching modes, start a new payment (do not reuse an old checkout).';
$string['environmentkeymismatch'] = 'Environment does not match the Key ID. Use Live with rzp_live_* keys, or Test with rzp_test_* keys.';
$string['coursefeeamount'] = 'Course fee amount';
$string['coursefeeamount_help'] = 'Course fee in INR. Use the full production fee (for example 15000) on live, or a small amount such as 5 for testing with Razorpay test keys and Environment set to Test.';
$string['invalidcoursefeeamount'] = 'Enter a valid course fee amount greater than zero (for example 5 for testing or 15000 for production).';
$string['live'] = 'Live (production)';
$string['test'] = 'Test (sandbox / mock)';
$string['paywithrazorpay'] = 'Pay with Razorpay';
$string['paymentfailed'] = 'Payment could not be completed. Please try again or contact support.';
$string['paymentsuccess'] = 'Payment successful. You are now enrolled in the course.';
$string['invalidsignature'] = 'Payment verification failed (invalid signature).';
$string['amountmismatch'] = 'The payment amount does not match the course fee. Please start the payment again from the course page.';
$string['txnnotfound'] = 'Transaction reference not found.';
$string['ordercreatefailed'] = 'Could not create Razorpay order. Check your API keys and try again.';
$string['apiconnectionfailed'] = 'Could not reach Razorpay. Check your internet connection and try again.';
$string['apiinvalidresponse'] = 'Razorpay returned an unexpected response. Please try again in a few minutes.';
$string['apiunavailable'] = 'Razorpay payment gateway is currently experiencing a temporary service disruption. This issue is on Razorpay side and is not related to our side or payment configuration. Please try again in a few minutes.';
$string['apiservererror'] = 'Razorpay payment gateway is currently experiencing a temporary service disruption. This issue is on Razorpay side and is not related to our side or payment configuration. Please try again in a few minutes.';

$string['apierror'] = '{$a}';
$string['apierrorprefix'] = 'Razorpay could not start this payment: {$a}';
$string['paymentnotallowed'] = 'Course fee payment is only available for registered students and NON-EMB professionals.';
$string['privacy:metadata'] = 'The Razorpay payment gateway stores transaction references linked to Moodle payments.';
$string['mocktitle'] = 'Razorpay payment (test simulator)';
$string['mocknotice'] = 'This is a local test page. Configure your Razorpay test or live API keys in Site administration → Payments for real checkout.';
$string['mockpaybutton'] = 'Complete payment (simulate success)';
$string['mockcancelbutton'] = 'Cancel payment (simulate failure)';
$string['continuetocourse'] = 'Continue to course';
$string['paymentresultsuccessheading'] = 'Payment successful';
$string['paymentresultfailheading'] = 'Payment not completed';
$string['paymentsuccessemailusergreeting'] = 'Student';
$string['paymentsuccessemailusersubject'] = '{$a->sitename}: Payment successful — {$a->coursename}';
$string['paymentsuccessemailusertitle'] = 'Payment received successfully';
$string['paymentsuccessemailuserintro'] = 'This is to confirm that your course fee payment has been received successfully.';
$string['paymentsuccessemailusernote'] = 'Thank you for your payment. We wish you a rewarding learning experience with {$a->sitename}.';
$string['paymentsuccessemailusercta'] = 'Open course';
$string['paymentsuccessemailusersignoff'] = '{$a->sitename} Administrator';
$string['paymentsuccessemailuserlabel_student'] = 'Student Name';
$string['paymentsuccessemailuserlabel_email'] = 'Email';
$string['paymentsuccessemailuserlabel_course'] = 'Course';
$string['paymentsuccessemailuserlabel_amount'] = 'Amount Paid';
$string['paymentsuccessemailuserlabel_invoice'] = 'Invoice Number';
$string['paymentsuccessemailuserlabel_reference'] = 'Payment Reference';
$string['paymentsuccessemailuserbody'] = 'Dear Student,

This is to confirm that your course fee payment has been received successfully.

Student Name: {$a->fullname}
Email: {$a->email}

Course: {$a->coursename}

Payment Details:

Amount Paid: {$a->amount}
Invoice Number: {$a->invoicenumber}
Payment Reference: {$a->txnref}

You can access your course using the link below:

Course URL: {$a->courseurl}

Thank you for your payment. We wish you a rewarding learning experience with {$a->sitename}.

Regards,
{$a->sitename} Administrator
';
$string['paymentsuccessemailadminsubject'] = '{$a->sitename}: Course fee paid — {$a->coursename}';
$string['paymentsuccessemailadminbody'] = 'A course fee payment was completed.

Student: {$a->fullname} ({$a->email})
Course: {$a->coursename}
Amount: {$a->amount}
Invoice: {$a->invoicenumber}
Reference: {$a->txnref}
Razorpay order: {$a->orderid}
Razorpay payment: {$a->paymentid}

Course URL: {$a->courseurl}

{$a->admin}';
$string['paymentfailedemailusersubject'] = '{$a->sitename}: Payment not completed — {$a->coursename}';
$string['paymentfailedemailuserbody'] = 'Hi {$a->firstname},

Your payment for "{$a->coursename}" was not completed.

Amount: {$a->amount}
Reference: {$a->txnref}
Reason: {$a->reason}

You can try again from the course page:
{$a->courseurl}

{$a->admin}';
$string['paymentfailedemailadminsubject'] = '{$a->sitename}: Course fee payment failed — {$a->coursename}';
$string['paymentfailedemailadminbody'] = 'A course fee payment failed or was cancelled.

Student: {$a->fullname} ({$a->email})
Course: {$a->coursename}
Amount: {$a->amount}
Reference: {$a->txnref}
Razorpay order: {$a->orderid}
Reason: {$a->reason}

Course URL: {$a->courseurl}

{$a->admin}';
$string['invoicetitle'] = 'TAX INVOICE / PAYMENT RECEIPT';
$string['invoicebillto'] = 'Bill to';
$string['invoicedetails'] = 'Invoice details';
$string['invoicenumberlabel'] = 'Invoice number';
$string['invoicedatelabel'] = 'Date';
$string['invoicedescription'] = 'Description';
$string['invoiceamount'] = 'Amount';
$string['invoicetotal'] = 'Total paid';
$string['invoicelineitem'] = 'Course fee — {$a}';
$string['invoicepaidnote'] = 'This invoice confirms that the course fee payment was received successfully via Razorpay.';
$string['invoicefooterdefault'] = 'This is a computer-generated invoice. No physical signature is required.';
$string['invoicegstinlabel'] = 'GSTIN: {$a}';
$string['invoicesupportlabel'] = 'Support: {$a}';
$string['invoiceorgname'] = 'Invoice organisation name';
$string['invoiceorgname_desc'] = 'Shown at the top of PDF invoices. Leave blank to use the site name.';
$string['invoiceaddress'] = 'Invoice organisation address';
$string['invoiceaddress_desc'] = 'Optional postal / registered address printed on invoices.';
$string['invoicegstin'] = 'GSTIN (optional)';
$string['invoicegstin_desc'] = 'If set, printed on the PDF invoice.';
$string['invoicesupport'] = 'Invoice support contact';
$string['invoicesupport_desc'] = 'Email or phone shown on invoices. Leave blank to use the site support email.';
$string['invoicefooter'] = 'Invoice footer text';
$string['invoicefooter_desc'] = 'Optional note at the bottom of the PDF. Leave blank for the default message.';
$string['invoiceattachname'] = 'Invoice {$a}.pdf';
$string['invoicecolumn'] = 'Invoice';
$string['downloadinvoice'] = 'Download invoice';
$string['transactionhistory'] = 'Razorpay transaction history';
$string['transactionhistorydesc'] = 'Course fee payments processed through the Razorpay payment gateway (test and live).';
$string['paymentstatuscompleted'] = 'Completed';
$string['paymentstatuspending'] = 'Pending';
$string['unknowncourse'] = 'Unknown course';
$string['notransactions'] = 'No Razorpay transactions recorded yet.';
$string['transactioncount'] = '{$a} transaction(s) listed.';
$string['paymenttxnreflabel'] = 'Transaction reference';
$string['orderreference'] = 'Razorpay order ID';
$string['paymentreference'] = 'Razorpay payment ID';
$string['viewpnbtransactions'] = 'View PNB transaction history';
$string['viewicicihistory'] = 'View ICICI transaction history';
