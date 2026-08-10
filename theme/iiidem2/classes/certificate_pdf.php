<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the IIIDEM certificate of completion PDF.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificate_pdf {

    /**
     * Create and store the PDF for an issue record.
     *
     * @param \stdClass $issue
     */
    public static function store_pdf(\stdClass $issue): void {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');
        require_once($CFG->libdir . '/filelib.php');

        $pdfbinary = self::render_binary($issue);
        $fs = get_file_storage();
        $context = \context_system::instance();

        $fs->delete_area_files($context->id, certificate_issuer::COMPONENT, certificate_issuer::FILEAREA, (int) $issue->id);

        $filerecord = [
            'contextid' => $context->id,
            'component' => certificate_issuer::COMPONENT,
            'filearea' => certificate_issuer::FILEAREA,
            'itemid' => (int) $issue->id,
            'filepath' => '/',
            'filename' => 'certificate-' . $issue->code . '.pdf',
        ];
        $fs->create_file_from_string($filerecord, $pdfbinary);
    }

    /**
     * @param \stdClass $issue
     * @return string PDF binary
     */
    public static function render_binary(\stdClass $issue): string {
        global $CFG;

        require_once($CFG->libdir . '/pdflib.php');

        $signatory = trim((string) get_config('theme_iiidem2', 'certificatesignatory'));
        if ($signatory === '') {
            $signatory = 'Rakesh Kumar Verma';
        }
        $signatorytitle = trim((string) get_config('theme_iiidem2', 'certificatesignatorytitle'));
        if ($signatorytitle === '') {
            $signatorytitle = 'Director General (IIIDEM)';
        }
        $locationline = trim((string) get_config('theme_iiidem2', 'certificatelocation'));
        if ($locationline === '') {
            $locationline = 'Delhi India';
        }

        $dateline = userdate((int) $issue->issuedate, '%d %B %Y');
        $student = \core_text::strtoupper($issue->studentname);
        $course = $issue->coursename;
        $city = trim((string) $issue->city);

        $pdf = new \pdf('P', 'mm', 'A4', true, 'UTF-8');
        $pdf->SetCreator('IIIDEM LMS');
        $pdf->SetAuthor('IIIDEM');
        $pdf->SetTitle('Certificate of Completion — ' . $student);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        // Outer soft blue canvas.
        $pdf->SetFillColor(214, 228, 242);
        $pdf->Rect(0, 0, 210, 297, 'F');

        // White certificate panel.
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect(14, 18, 182, 261, 'F');

        // Thin gold border.
        $pdf->SetDrawColor(198, 160, 68);
        $pdf->SetLineWidth(0.8);
        $pdf->Rect(18, 22, 174, 253);

        $logoiiidem = $CFG->dirroot . '/theme/iiidem2/pix/iiidem-white-logo-footer.png';
        if (is_readable($logoiiidem)) {
            // Dark logo on white: print smaller at top center-right area.
            $pdf->Image($logoiiidem, 118, 30, 38, 0, '', '', '', false, 300);
        }

        $pdf->SetTextColor(90, 90, 90);
        $pdf->SetFont('times', 'B', 36);
        $pdf->SetXY(20, 72);
        $pdf->Cell(170, 14, 'CERTIFICATE', 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetXY(55, 88);
        $pdf->Cell(40, 6, '', 'T', 0, 'C');
        $pdf->SetXY(20, 86);
        $pdf->Cell(170, 8, 'OF COMPLETION', 0, 1, 'C');
        $pdf->SetXY(115, 88);
        $pdf->Cell(40, 6, '', 'T', 0, 'C');

        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetFont('times', 'B', 14);
        $pdf->SetXY(20, 104);
        $pdf->Cell(170, 8, 'PROUDLY AWARDED TO', 0, 1, 'C');

        $pdf->SetTextColor(198, 160, 68);
        $pdf->SetFont('times', 'B', 22);
        $pdf->SetXY(20, 118);
        $pdf->MultiCell(170, 10, $student, 0, 'C');

        $y = $pdf->GetY() + 2;
        if ($city !== '') {
            $pdf->SetTextColor(30, 30, 30);
            $pdf->SetFont('times', '', 13);
            $pdf->SetXY(20, $y);
            $pdf->Cell(170, 7, $city, 0, 1, 'C');
            $y = $pdf->GetY() + 4;
        } else {
            $y += 4;
        }

        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetFont('times', '', 13);
        $body = "For active participation in the '{$course}'";
        $pdf->SetXY(28, $y);
        $pdf->MultiCell(154, 7, $body, 0, 'C');

        $y = $pdf->GetY() + 8;
        $pdf->SetFont('times', '', 12);
        $pdf->SetXY(20, $y);
        $pdf->Cell(170, 6, $dateline, 0, 1, 'C');
        $pdf->SetXY(20, $pdf->GetY());
        $pdf->Cell(170, 6, $locationline, 0, 1, 'C');

        // Signature block.
        $sigy = 230;
        $pdf->SetDrawColor(40, 40, 40);
        $pdf->SetLineWidth(0.4);
        $pdf->Line(70, $sigy, 140, $sigy);

        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetFont('times', 'B', 12);
        $pdf->SetXY(20, $sigy + 3);
        $pdf->Cell(170, 6, $signatory, 0, 1, 'C');
        $pdf->SetFont('times', '', 11);
        $pdf->SetXY(20, $sigy + 10);
        $pdf->Cell(170, 6, $signatorytitle, 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->SetXY(20, 268);
        $pdf->Cell(170, 5, 'Certificate ID: ' . $issue->code, 0, 1, 'C');

        return $pdf->Output('', 'S');
    }

    /**
     * Send stored PDF to browser.
     *
     * @param \stdClass $issue
     */
    public static function send_download(\stdClass $issue): void {
        $fs = get_file_storage();
        $context = \context_system::instance();
        $files = $fs->get_area_files(
            $context->id,
            certificate_issuer::COMPONENT,
            certificate_issuer::FILEAREA,
            (int) $issue->id,
            'filename',
            false
        );

        $file = reset($files);
        if (!$file) {
            self::store_pdf($issue);
            $files = $fs->get_area_files(
                $context->id,
                certificate_issuer::COMPONENT,
                certificate_issuer::FILEAREA,
                (int) $issue->id,
                'filename',
                false
            );
            $file = reset($files);
        }

        if (!$file) {
            throw new \moodle_exception('filenotfound', 'error');
        }

        send_stored_file($file, 0, 0, true);
    }
}
