<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Theme upgrade steps.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_theme_iiidem2_upgrade($oldversion) {
    if ($oldversion < 2024100729) {
        \theme_iiidem2\registration_profile::ensure_fields();
        upgrade_plugin_savepoint(true, 2024100729, 'theme', 'iiidem2');
    }

    if ($oldversion < 2024100746) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100746, 'theme', 'iiidem2');
    }

    if ($oldversion < 2024100747) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100747, 'theme', 'iiidem2');
    }

    if ($oldversion < 2024100748) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100748, 'theme', 'iiidem2');
    }

    if ($oldversion < 2024100749) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100749, 'theme', 'iiidem2');
    }

    if ($oldversion < 2024100750) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100750, 'theme', 'iiidem2');
    }

    if ($oldversion < 2024100751) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100751, 'theme', 'iiidem2');
    }

    if ($oldversion < 2024100752) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100752, 'theme', 'iiidem2');
    }

    if ($oldversion < 2024100753) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100753, 'theme', 'iiidem2');
    }

    if ($oldversion < 2024100754) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100754, 'theme', 'iiidem2');
    }

    return true;
}
