<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Tiny editor upgrade steps.
 *
 * @package    editor_tiny
 * @copyright  2026 IIIDEM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_editor_tiny_upgrade($oldversion) {
    if ($oldversion < 2024100702) {
        // CDAC #19: TinyMCE 7.9.3 + DOMPurify 3.2.6 vendor replace.
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100702, 'editor', 'tiny');
    }

    if ($oldversion < 2024100703) {
        // CDAC #38: DOMPurify 3.2.6 → 3.2.7 (CVE-2025-15599 SAFE_FOR_XML textarea).
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100703, 'editor', 'tiny');
    }

    if ($oldversion < 2024100704) {
        // CDAC: DOMPurify 3.2.7 → 3.4.15 (CVE-2026-0540+ SAFE_FOR_XML rawtext elements).
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100704, 'editor', 'tiny');
    }

    return true;
}
