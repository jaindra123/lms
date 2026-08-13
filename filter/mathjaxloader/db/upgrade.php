<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * MathJAX filter upgrade code.
 *
 * @package    filter_mathjaxloader
 * @copyright  2014 Damyon Wiese (damyon@moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * @param int $oldversion the version we are upgrading from
 * @return bool result
 */
function xmldb_filter_mathjaxloader_upgrade($oldversion) {
    // Automatically generated Moodle v4.1.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.2.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.3.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.4.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v4.5.0 release upgrade line.
    // Put any upgrade step following this.

    // CDAC #19 / CWE-1104: MathJax 2.7.9 (CVE-2023-39663) → MathJax 3.2.2 (Moodle MDL-75486).
    if ($oldversion < 2024100701) {
        $newurl = 'https://cdn.jsdelivr.net/npm/mathjax@3.2.2/es5/tex-mml-chtml.js';
        $olddefaults = [
            'https://cdn.jsdelivr.net/npm/mathjax@2.7.9/MathJax.js',
            'https://cdn.jsdelivr.net/npm/mathjax@2.7.8/MathJax.js',
            'https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.9/MathJax.js',
        ];
        $current = (string) get_config('filter_mathjaxloader', 'httpsurl');
        if ($current === '' || in_array($current, $olddefaults, true) || str_contains($current, 'mathjax@2.')) {
            set_config('httpsurl', $newurl, 'filter_mathjaxloader');
            // MathJax 2 Hub.Config JS is invalid for v3 — clear to defaults (ui/safe forced in AMD).
            set_config('mathjaxconfig', '', 'filter_mathjaxloader');
        }

        upgrade_plugin_savepoint(true, 2024100701, 'filter', 'mathjaxloader');
    }

    return true;
}
