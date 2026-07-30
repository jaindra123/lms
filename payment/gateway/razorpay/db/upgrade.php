<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for paygw_razorpay.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_paygw_razorpay_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025062908) {
        $table = new xmldb_table('paygw_razorpay_txn');
        $field = new xmldb_field('invoicenumber', XMLDB_TYPE_CHAR, '64', null, null, null, null, 'status');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('invoicenumber', XMLDB_INDEX_NOTUNIQUE, ['invoicenumber']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2025062908, 'paygw', 'razorpay');
    }

    return true;
}
