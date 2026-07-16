<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_iiidem_coursecalendar_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026071500) {
        $table = new xmldb_table('local_iiidem_coursecalendar_event');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('googleeventid', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('googlelink', XMLDB_TYPE_CHAR, '512', null, null, null, null);
        $table->add_field('summary', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('location', XMLDB_TYPE_CHAR, '512', null, null, null, null);
        $table->add_field('starttime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('endtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('attendeecount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('invites_sent', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('courseid_starttime_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'starttime']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026071500, 'local', 'iiidem_coursecalendar');
    }

    if ($oldversion < 2026071501) {
        $table = new xmldb_table('local_iiidem_coursecalendar_event');

        $field = new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'courseid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('webexid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'cmid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $index = new xmldb_index('cmid_ix', XMLDB_INDEX_NOTUNIQUE, ['cmid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $index = new xmldb_index('webexid_ix', XMLDB_INDEX_NOTUNIQUE, ['webexid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026071501, 'local', 'iiidem_coursecalendar');
    }

    if ($oldversion < 2026071502) {
        // Observer now also supports URL activities with Webex links; settings default only.
        upgrade_plugin_savepoint(true, 2026071502, 'local', 'iiidem_coursecalendar');
    }

    return true;
}
