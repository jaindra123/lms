<?php
// This file is part of Moodle - http://moodle.org/.

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Branded HTML wrapper for assignment / live-class notification emails.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class notify_email {

    /**
     * Absolute site logo URL for email clients.
     *
     * @return string
     */
    public static function logo_url(): string {
        global $CFG;

        try {
            require_once($CFG->dirroot . '/theme/iiidem2/lib.php');
            $url = theme_iiidem2_get_navbar_logo_url();
        } catch (\Throwable $e) {
            return '';
        }
        if ($url === '') {
            return '';
        }
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        } else if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
            $url = rtrim($CFG->wwwroot, '/') . '/' . ltrim($url, '/');
        }
        return $url;
    }

    /**
     * Build branded HTML email body.
     *
     * @param array{
     *   sitename:string,
     *   firstname:string,
     *   badge:string,
     *   title:string,
     *   intro:string,
     *   rows:array<int,array{label:string,value:string}>,
     *   ctaurl:string,
     *   ctalabel:string,
     *   secondaryurl?:string,
     *   secondarylabel?:string,
     *   note?:string
     * } $data
     * @return string
     */
    public static function render(array $data): string {
        global $CFG;

        $sitename = s($data['sitename'] ?? 'IIIDEM');
        $firstname = s($data['firstname'] ?? '');
        $title = s($data['title'] ?? '');
        $intro = !empty($data['introhtml']) ? (string) $data['introhtml'] : s($data['intro'] ?? '');
        $note = !empty($data['notehtml']) ? (string) $data['notehtml'] : s($data['note'] ?? '');
        $signoff = s($data['signoff'] ?? $data['sitename'] ?? 'IIIDEM');
        $signoffextra = s($data['signoffextra'] ?? '');
        $regards = s($data['regards'] ?? 'Regards');
        $ctaurl = $data['ctaurl'] ?? '';
        $ctalabel = s($data['ctalabel'] ?? 'Open');
        $secondaryurl = $data['secondaryurl'] ?? '';
        $secondarylabel = s($data['secondarylabel'] ?? '');
        $logourl = self::logo_url();
        $wwwroot = s($CFG->wwwroot);
        $greetingname = $firstname !== '' ? $firstname : 'Student';

        $rowshtml = '';
        foreach ($data['rows'] ?? [] as $row) {
            $label = s($row['label'] ?? '');
            if (!empty($row['valuehtml'])) {
                $value = (string) $row['valuehtml'];
            } else {
                $value = s($row['value'] ?? '');
            }
            if ($value === '') {
                continue;
            }
            $rowshtml .= '
              <tr>
                <td style="padding:10px 0;border-bottom:1px solid #e8edf5;width:38%;vertical-align:top;font-size:13px;color:#5d6785;font-weight:600;">'
                . $label . '</td>
                <td style="padding:10px 0;border-bottom:1px solid #e8edf5;vertical-align:top;font-size:14px;color:#1f2a56;font-weight:500;">'
                . $value . '</td>
              </tr>';
        }

        $logohtml = $logourl !== ''
            ? '<img src="' . s($logourl) . '" alt="' . $sitename . '" width="180" style="display:block;margin:0 auto;max-width:180px;height:auto;border:0;" />'
            : '<div style="font-size:22px;font-weight:700;letter-spacing:0.5px;color:#ffffff;">' . $sitename . '</div>';

        $ctahtml = '';
        if ($ctaurl !== '') {
            $ctahtml .= '
              <a href="' . s($ctaurl) . '"
                 style="display:inline-block;background:#0b3d91;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:14px 28px;border-radius:8px;box-shadow:0 4px 14px rgba(11,61,145,0.28);">
                ' . $ctalabel . '
              </a>';
        }
        if ($secondaryurl !== '' && $secondarylabel !== '') {
            $ctahtml .= '
              <div style="height:12px;"></div>
              <a href="' . s($secondaryurl) . '" style="color:#0b3d91;font-size:13px;font-weight:600;text-decoration:underline;">'
                . $secondarylabel . '</a>';
        }

        $notehtml = $note !== ''
            ? '<div style="margin:18px 0 0;font-size:13px;line-height:1.55;color:#5d6785;">' . $note . '</div>'
            : '';

        $detailblock = $rowshtml !== ''
            ? '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f7f9fc;border:1px solid #e4ebf5;border-radius:12px;padding:4px 18px;">'
                . $rowshtml . '</table>'
            : '';

        $signoffextrahtml = $signoffextra !== ''
            ? '<br />' . $signoffextra
            : '';

        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>' . $title . '</title>
</head>
<body style="margin:0;padding:0;background:#d9e3f2;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:linear-gradient(180deg,#c9d8ef 0%,#e8eef7 45%,#f4f7fb 100%);background-color:#d9e3f2;padding:28px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 12px 40px rgba(15,40,80,0.14);">
          <tr>
            <td style="background:#0b3d91;background-image:linear-gradient(135deg,#0b3d91 0%,#1456b8 55%,#1a6fd0 100%);padding:28px 24px;text-align:center;">
              ' . $logohtml . '
              <div style="margin-top:14px;font-size:12px;letter-spacing:1.2px;text-transform:uppercase;color:rgba(255,255,255,0.85);font-weight:600;">
                ' . $sitename . '
              </div>
            </td>
          </tr>
          <tr>
            <td style="padding:28px 28px 8px;">
              <h1 style="margin:0 0 10px;font-size:22px;line-height:1.35;color:#102a56;font-weight:700;">'
                . $title . '</h1>
              <p style="margin:0;font-size:15px;line-height:1.6;color:#33415c;">
                Dear ' . $greetingname . ',
              </p>
              <div style="margin:10px 0 0;font-size:15px;line-height:1.6;color:#33415c;">'
                . $intro . '</div>
            </td>
          </tr>
          <tr>
            <td style="padding:8px 28px 8px;">
              ' . $detailblock . '
            </td>
          </tr>
          <tr>
            <td style="padding:22px 28px 8px;text-align:center;">
              ' . $ctahtml . '
            </td>
          </tr>
          <tr>
            <td style="padding:8px 28px 24px;">
              ' . $notehtml . '
            </td>
          </tr>
          <tr>
            <td style="background:#f3f6fb;border-top:1px solid #e4ebf5;padding:18px 28px;text-align:center;">
              <p style="margin:0;font-size:12px;line-height:1.5;color:#6b7690;">
                ' . $regards . ',<br /><strong style="color:#0b3d91;">' . $signoff . '</strong>'
                . $signoffextrahtml . '
              </p>
              <p style="margin:10px 0 0;font-size:11px;color:#8a93a8;">
                <a href="' . $wwwroot . '" style="color:#0b3d91;text-decoration:none;">' . $wwwroot . '</a>
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
    }
}
