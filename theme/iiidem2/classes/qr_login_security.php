<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Disable unused Moodle Mobile QR login surfaces.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class qr_login_security {

    /**
     * Persist Disabled QR mode, revoke keys, detach WS function from services.
     */
    public static function disable(): void {
        global $DB;

        // 0 = \tool_mobile\api::QR_CODE_DISABLED
        set_config('qrcodetype', 0, 'tool_mobile');
        // CDAC #28: no cross-domain mobile download promo in the footer.
        set_config('setuplink', '', 'tool_mobile');
        set_config('enablesmartappbanners', 0, 'tool_mobile');

        // Invalidate any outstanding QR login keys.
        $DB->delete_records('user_private_key', ['script' => 'tool_mobile/qrlogin']);

        // Remove WS from all external services so it is not advertised.
        $DB->delete_records('external_services_functions', [
            'functionname' => 'tool_mobile_get_tokens_for_qr_login',
        ]);

        purge_all_caches();
    }
}
