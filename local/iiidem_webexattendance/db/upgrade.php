<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_iiidem_webexattendance.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_iiidem_webexattendance_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026081300) {
        $table = new xmldb_table('local_iiidem_webexatt');

        $field = new xmldb_field('recordingstatus', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending', 'syncmessage');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('recordingurl', XMLDB_TYPE_TEXT, null, null, null, null, null, 'recordingstatus');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('classvideoid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'recordingurl');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('recordinglastsync', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'classvideoid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('recordingmessage', XMLDB_TYPE_TEXT, null, null, null, null, null, 'recordinglastsync');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('recording_status_end', XMLDB_INDEX_NOTUNIQUE, ['recordingstatus', 'endtime']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Default settings for recording import.
        if (get_config('local_iiidem_webexattendance', 'importrecordings') === false) {
            set_config('importrecordings', 1, 'local_iiidem_webexattendance');
        }
        if (get_config('local_iiidem_webexattendance', 'recordinggraceafterend') === false) {
            set_config('recordinggraceafterend', 60, 'local_iiidem_webexattendance');
        }
        if (get_config('local_iiidem_webexattendance', 'recordingmaxdays') === false) {
            set_config('recordingmaxdays', 7, 'local_iiidem_webexattendance');
        }
        if (get_config('local_iiidem_webexattendance', 'recordingaccesstype') === false) {
            set_config('recordingaccesstype', 'request', 'local_iiidem_webexattendance');
        }

        upgrade_plugin_savepoint(true, 2026081300, 'local', 'iiidem_webexattendance');
    }

    if ($oldversion < 2026081301) {
        // Connect button: link instead of nested form (admin settings UI fix).
        upgrade_plugin_savepoint(true, 2026081301, 'local', 'iiidem_webexattendance');
    }

    if ($oldversion < 2026081400) {
        // Clearer OAuth failure codes in admin notification.
        upgrade_plugin_savepoint(true, 2026081400, 'local', 'iiidem_webexattendance');
    }

    if ($oldversion < 2026081401) {
        upgrade_plugin_savepoint(true, 2026081401, 'local', 'iiidem_webexattendance');
    }

    if ($oldversion < 2026081402) {
        // Load Moodle curl via filelib before token exchange.
        upgrade_plugin_savepoint(true, 2026081402, 'local', 'iiidem_webexattendance');
    }

    if ($oldversion < 2026081403) {
        // Fix OAuth token POST: send urlencoded body (not multipart) so grant_type is accepted.
        upgrade_plugin_savepoint(true, 2026081403, 'local', 'iiidem_webexattendance');
    }

    if ($oldversion < 2026081404) {
        // Fix bad meetingNumber scraped from MTID; improve meeting resolve for ended classes.
        upgrade_plugin_savepoint(true, 2026081404, 'local', 'iiidem_webexattendance');
    }

    if ($oldversion < 2026081405) {
        // Recreate Webex session on the correct Attendance activity when remapping.
        upgrade_plugin_savepoint(true, 2026081405, 'local', 'iiidem_webexattendance');
    }

    if ($oldversion < 2026081406) {
        // Clearer email-match remarks / syncmessage for attendance marks.
        upgrade_plugin_savepoint(true, 2026081406, 'local', 'iiidem_webexattendance');
    }

    if ($oldversion < 2026081407) {
        $table = new xmldb_table('local_iiidem_webexatt_click');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('cmid_user', XMLDB_INDEX_NOTUNIQUE, ['cmid', 'userid']);
            $table->add_index('cmid_time', XMLDB_INDEX_NOTUNIQUE, ['cmid', 'timecreated']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026081407, 'local', 'iiidem_webexattendance');
    }

    if ($oldversion < 2026081408) {
        // Mark by join/leave vs class start (Present / Late / Absent), not total duration.
        set_config('lateminutes', 5, 'local_iiidem_webexattendance');
        upgrade_plugin_savepoint(true, 2026081408, 'local', 'iiidem_webexattendance');
    }

    return true;
}
