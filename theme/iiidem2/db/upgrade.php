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
    global $CFG, $DB;

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

    if ($oldversion < 2024100846) {
        \theme_iiidem2\registration_profile::ensure_fields();
        upgrade_plugin_savepoint(true, 2024100846, 'theme', 'iiidem2');
    }

    if ($oldversion < 2024100864) {
        // The registration flow enforces unique email addresses, so Moodle can
        // safely accept either a username or email address on the login form.
        set_config('authloginviaemail', 1);
        upgrade_plugin_savepoint(true, 2024100864, 'theme', 'iiidem2');
    }

    if ($oldversion < 2024100866) {
        if (get_config('theme_iiidem2', 'registrationcourseids') === false) {
            set_config('registrationcourseids', '4', 'theme_iiidem2');
        }
        upgrade_plugin_savepoint(true, 2024100866, 'theme', 'iiidem2');
    }

    if ($oldversion < 2024100868) {
        // Repair pending fee enrolments for which payment was already
        // completed before the fee callback explicitly activated them.
        require_once($CFG->libdir . '/enrollib.php');
        $sql = "SELECT DISTINCT ue.id, ue.userid, ue.enrolid, ue.timestart, ue.timeend
                  FROM {user_enrolments} ue
                  JOIN {enrol} e
                    ON e.id = ue.enrolid
                   AND e.enrol = :enrol
                  JOIN {payments} p
                    ON p.component = :component
                   AND p.paymentarea = :paymentarea
                   AND p.itemid = e.id
                   AND p.userid = ue.userid
                 WHERE ue.status = :suspended";
        $pendingpaid = $DB->get_records_sql($sql, [
            'enrol' => 'fee',
            'component' => 'enrol_fee',
            'paymentarea' => 'fee',
            'suspended' => ENROL_USER_SUSPENDED,
        ]);
        $feeplugin = enrol_get_plugin('fee');
        if ($feeplugin) {
            foreach ($pendingpaid as $userenrolment) {
                $instance = $DB->get_record('enrol', [
                    'id' => $userenrolment->enrolid,
                    'enrol' => 'fee',
                ]);
                if (!$instance) {
                    continue;
                }
                $feeplugin->update_user_enrol(
                    $instance,
                    (int) $userenrolment->userid,
                    ENROL_USER_ACTIVE,
                    (int) $userenrolment->timestart,
                    (int) $userenrolment->timeend
                );
            }
        }
        upgrade_plugin_savepoint(true, 2024100868, 'theme', 'iiidem2');
    }

    if ($oldversion < 2024100882) {
        \theme_iiidem2\registration_profile::ensure_fields();

        // Reconcile enrolments for users already marked allow-without-payment.
        $embfieldid = (int) $DB->get_field('user_info_field', 'id', ['shortname' => 'iiidem_emb']);
        if ($embfieldid > 0) {
            $exemptuserids = $DB->get_fieldset_select(
                'user_info_data',
                'userid',
                'fieldid = ? AND data = ?',
                [$embfieldid, '1']
            );
            foreach ($exemptuserids as $userid) {
                \theme_iiidem2\registration_enrolment::enrol_user((int) $userid);
            }
        }

        upgrade_plugin_savepoint(true, 2024100882, 'theme', 'iiidem2');
    }

    if ($oldversion < 2024100886) {
        // Force rebuild of event observer cache after notifier files were uploaded.
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100886, 'theme', 'iiidem2');
    }

    if ($oldversion < 2024100892) {
        // Branded password-reset email strings + template support.
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100892, 'theme', 'iiidem2');
    }

    if ($oldversion < 2024100893) {
        // Homepage chatbot → admin Moodle notifications.
        message_update_providers('theme_iiidem2');
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100893, 'theme', 'iiidem2');
    }

    if ($oldversion < 2024100894) {
        // Chatbot Q&A table for admin replies.
        $dbman = $DB->get_manager();
        $table = new xmldb_table('theme_iiidem2_chatbot');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('question', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'open');
            $table->add_field('reply', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('replyuserid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timereplied', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('emailsent', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('status_timecreated', XMLDB_INDEX_NOTUNIQUE, ['status', 'timecreated']);
            $table->add_index('email_idx', XMLDB_INDEX_NOTUNIQUE, ['email']);
            $dbman->create_table($table);
        }
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100894, 'theme', 'iiidem2');
    }

    return true;
}
