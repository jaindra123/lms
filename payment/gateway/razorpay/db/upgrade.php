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

    if ($oldversion < 2025062910) {
        // Force language string reload for branded payment success emails.
        get_string_manager()->reset_caches();
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2025062910, 'paygw', 'razorpay');
    }

    if ($oldversion < 2025062915) {
        // CDAC #12: block mock free-pay on staging; require captured; no fee sync-down.
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2025062915, 'paygw', 'razorpay');
    }

    if ($oldversion < 2025062916) {
        // CWE-209: scrub Razorpay grpc/internal checkout errors shown to users.
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2025062916, 'paygw', 'razorpay');
    }

    if ($oldversion < 2025062917) {
        // Hide Razorpay order/payment IDs from student emails and PDF invoices.
        get_string_manager()->reset_caches();
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2025062917, 'paygw', 'razorpay');
    }

    if ($oldversion < 2025062918) {
        // CDAC Instance 2: omit payer name/email from get_checkout_data JSON.
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2025062918, 'paygw', 'razorpay');
    }

    if ($oldversion < 2025062919) {
        // CDAC Payment Amount Manipulation: lock amount on server order; re-read Razorpay.
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2025062919, 'paygw', 'razorpay');
    }

    if ($oldversion < 2025062920) {
        // CDAC Key ID exposure: hosted Payment Link; no keyid/orderid/name/email in LMS AJAX.
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2025062920, 'paygw', 'razorpay');
    }

    if ($oldversion < 2025062921) {
        // CWE-209: never surface Razorpay grpc/internal errors; Payment Link redirect only.
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2025062921, 'paygw', 'razorpay');
    }

    if ($oldversion < 2025062922) {
        // CDAC Sardine: checkout requires live session; tighter rate limit.
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2025062922, 'paygw', 'razorpay');
    }

    if ($oldversion < 2025062923) {
        // CDAC prefill/encrypt: no customer PII on Payment Links; hide email/contact;
        // tighter checkout-start rate limit (3/10 min user, 5/10 min IP, 8/hour IP).
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2025062923, 'paygw', 'razorpay');
    }

    if ($oldversion < 2025062924) {
        // CDAC window.session_token: top-level Payment Link replace; no Checkout iframe.
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2025062924, 'paygw', 'razorpay');
    }

    if ($oldversion < 2025062925) {
        // CDAC Key ID: get_checkout_data returns only redirecturl (no keyid/orderid/PII).
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2025062925, 'paygw', 'razorpay');
    }

    return true;
}
