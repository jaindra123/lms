<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace paygw_razorpay;

defined('MOODLE_INTERNAL') || die();

/**
 * PDF invoice generation for completed Razorpay course-fee payments.
 *
 * @package   paygw_razorpay
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class invoice {

    /** @var string File area for stored invoice PDFs. */
    public const FILEAREA = 'invoice';

    /**
     * Build a unique invoice number for a completed transaction.
     *
     * @param \stdClass $txn
     * @return string
     */
    public static function generate_number(\stdClass $txn): string {
        $year = userdate(time(), '%Y');
        $id = str_pad((string) ((int) $txn->id), 6, '0', STR_PAD_LEFT);
        return 'INV-' . $year . '-' . $id;
    }

    /**
     * Create PDF content, store it, persist invoice number, return temp path for email.
     *
     * @param \stdClass $txn Completed transaction (with paymentid set).
     * @return array{path:string,filename:string,invoicenumber:string}|null
     */
    public static function create_for_transaction(\stdClass $txn): ?array {
        global $CFG, $DB, $SITE;

        require_once($CFG->libdir . '/pdflib.php');
        require_once($CFG->libdir . '/filelib.php');

        $user = \core_user::get_user((int) $txn->userid);
        if (!$user) {
            return null;
        }

        $invoicenumber = trim((string) ($txn->invoicenumber ?? ''));
        if ($invoicenumber === '') {
            $invoicenumber = self::generate_number($txn);
            $txn->invoicenumber = $invoicenumber;
            $DB->set_field('paygw_razorpay_txn', 'invoicenumber', $invoicenumber, ['id' => $txn->id]);
        }

        $coursename = get_string('unknowncourse', 'paygw_razorpay');
        if (($txn->component ?? '') === 'enrol_fee' && ($txn->paymentarea ?? '') === 'fee') {
            $courseid = (int) $DB->get_field('enrol', 'courseid', ['id' => (int) $txn->itemid]);
            if ($courseid) {
                $course = get_course($courseid);
                $coursename = format_string($course->fullname, true, [
                    'context' => \context_course::instance($courseid),
                ]);
            }
        }

        $filename = clean_filename($invoicenumber . '.pdf');
        $pdfcontent = self::render_pdf($txn, $user, $coursename, $invoicenumber);
        if ($pdfcontent === '') {
            return null;
        }

        self::store_pdf_file($txn, $filename, $pdfcontent);

        $tmpdir = make_temp_directory('paygw_razorpay/invoices');
        $filepath = $tmpdir . '/' . $filename;
        if (file_put_contents($filepath, $pdfcontent) === false) {
            return null;
        }

        return [
            'path' => $filepath,
            'filename' => $filename,
            'invoicenumber' => $invoicenumber,
        ];
    }

    /**
     * Render invoice PDF binary.
     *
     * @param \stdClass $txn
     * @param \stdClass $user
     * @param string $coursename
     * @param string $invoicenumber
     * @return string
     */
    protected static function render_pdf(
        \stdClass $txn,
        \stdClass $user,
        string $coursename,
        string $invoicenumber
    ): string {
        global $SITE, $CFG;

        $orgname = trim((string) get_config('paygw_razorpay', 'invoiceorgname'));
        if ($orgname === '') {
            $orgname = format_string($SITE->fullname);
        }
        $orgaddress = trim((string) get_config('paygw_razorpay', 'invoiceaddress'));
        $orggstin = trim((string) get_config('paygw_razorpay', 'invoicegstin'));
        $orgsupport = trim((string) get_config('paygw_razorpay', 'invoicesupport'));
        if ($orgsupport === '') {
            $orgsupport = !empty($CFG->supportemail) ? (string) $CFG->supportemail : '';
        }

        $amount = \core_payment\helper::get_cost_as_string((float) $txn->amount, (string) $txn->currency);
        $paidon = userdate((int) ($txn->timemodified ?: time()), get_string('strftimedatefullshort', 'core_langconfig'));

        $doc = new \pdf();
        $doc->setPrintHeader(false);
        $doc->setPrintFooter(false);
        $doc->SetMargins(15, 15, 15);
        $doc->SetAutoPageBreak(true, 15);
        $doc->AddPage();

        $doc->SetFont('helvetica', 'B', 16);
        $doc->Cell(0, 10, get_string('invoicetitle', 'paygw_razorpay'), 0, 1, 'C');
        $doc->Ln(2);

        $doc->SetFont('helvetica', 'B', 11);
        $doc->Cell(0, 6, $orgname, 0, 1, 'L');
        $doc->SetFont('helvetica', '', 9);
        if ($orgaddress !== '') {
            $doc->MultiCell(0, 5, $orgaddress, 0, 'L');
        }
        if ($orggstin !== '') {
            $doc->Cell(0, 5, get_string('invoicegstinlabel', 'paygw_razorpay', $orggstin), 0, 1, 'L');
        }
        if ($orgsupport !== '') {
            $doc->Cell(0, 5, get_string('invoicesupportlabel', 'paygw_razorpay', $orgsupport), 0, 1, 'L');
        }

        $doc->Ln(4);
        $doc->SetFont('helvetica', 'B', 10);
        $doc->Cell(95, 6, get_string('invoicebillto', 'paygw_razorpay'), 0, 0, 'L');
        $doc->Cell(0, 6, get_string('invoicedetails', 'paygw_razorpay'), 0, 1, 'L');

        $doc->SetFont('helvetica', '', 9);
        $left = fullname($user) . "\n" . $user->email;
        $right = get_string('invoicenumberlabel', 'paygw_razorpay') . ': ' . $invoicenumber . "\n"
            . get_string('invoicedatelabel', 'paygw_razorpay') . ': ' . $paidon . "\n"
            . get_string('paymenttxnreflabel', 'paygw_razorpay') . ': ' . ($txn->txnref ?? '');
        // CDAC: omit Razorpay order/payment IDs from student PDF (kept in admin email / DB).

        $y = $doc->GetY();
        $doc->MultiCell(95, 5, $left, 0, 'L', false, 0);
        $doc->SetXY(110, $y);
        $doc->MultiCell(0, 5, $right, 0, 'L', false, 1);

        $doc->Ln(6);
        $doc->SetFont('helvetica', 'B', 9);
        $doc->SetFillColor(240, 245, 255);
        $doc->Cell(110, 8, get_string('invoicedescription', 'paygw_razorpay'), 1, 0, 'L', true);
        $doc->Cell(65, 8, get_string('invoiceamount', 'paygw_razorpay'), 1, 1, 'R', true);

        $doc->SetFont('helvetica', '', 9);
        $description = get_string('invoicelineitem', 'paygw_razorpay', $coursename);
        $doc->Cell(110, 10, $description, 1, 0, 'L');
        $doc->Cell(65, 10, $amount, 1, 1, 'R');

        $doc->SetFont('helvetica', 'B', 10);
        $doc->Cell(110, 9, get_string('invoicetotal', 'paygw_razorpay'), 1, 0, 'R');
        $doc->Cell(65, 9, $amount, 1, 1, 'R');

        $doc->Ln(8);
        $doc->SetFont('helvetica', '', 8);
        $doc->MultiCell(0, 4, get_string('invoicepaidnote', 'paygw_razorpay'), 0, 'L');

        $footer = trim((string) get_config('paygw_razorpay', 'invoicefooter'));
        if ($footer === '') {
            $footer = get_string('invoicefooterdefault', 'paygw_razorpay');
        }
        $doc->Ln(4);
        $doc->MultiCell(0, 4, $footer, 0, 'L');

        return $doc->Output('', 'S');
    }

    /**
     * Store PDF in Moodle file API (user context).
     *
     * @param \stdClass $txn
     * @param string $filename
     * @param string $pdfcontent
     */
    protected static function store_pdf_file(\stdClass $txn, string $filename, string $pdfcontent): void {
        $fs = get_file_storage();
        $context = \context_user::instance((int) $txn->userid);

        $fs->delete_area_files($context->id, 'paygw_razorpay', self::FILEAREA, (int) $txn->id);

        $filerecord = [
            'contextid' => $context->id,
            'component' => 'paygw_razorpay',
            'filearea' => self::FILEAREA,
            'itemid' => (int) $txn->id,
            'filepath' => '/',
            'filename' => $filename,
            'userid' => (int) $txn->userid,
        ];
        $fs->create_file_from_string($filerecord, $pdfcontent);
    }
}
