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

    // Sync disk version → DB after CSS/a11y/perf theme bumps (rebuilds admin/plugin caches).
    if ($oldversion < 2024100911) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100911, 'theme', 'iiidem2');
    }

    // Register form validation + contact-number padding fix.
    if ($oldversion < 2024100912) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100912, 'theme', 'iiidem2');
    }

    // Production: ensure intl-tel-input / Moodle end-of-body JS loads on register.
    if ($oldversion < 2024100913) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100913, 'theme', 'iiidem2');
    }

    // Teacher materials upload page for student-visible File resources.
    if ($oldversion < 2024100914) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100914, 'theme', 'iiidem2');
    }

    // Teacher create-assignment page (no course Edit mode).
    if ($oldversion < 2024100915) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100915, 'theme', 'iiidem2');
    }

    // Sticky grader pagination: keep above branded site footer (not fixed under it).
    if ($oldversion < 2024100916) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100916, 'theme', 'iiidem2');
    }

    // Teacher attendance: scope student list to learners under that teacher.
    if ($oldversion < 2024100917) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100917, 'theme', 'iiidem2');
    }

    // Create assignment page no longer depends on teacher_materials class.
    if ($oldversion < 2024100918) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100918, 'theme', 'iiidem2');
    }

    // Materials upload size display + AMD popover fix for file picker pages.
    if ($oldversion < 2024100919) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100919, 'theme', 'iiidem2');
    }

    // Teacher materials upload capped at ~5 MB.
    if ($oldversion < 2024100920) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100920, 'theme', 'iiidem2');
    }

    // Instructors (occupation) get teacher role + teacher dashboard.
    if ($oldversion < 2024100921) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100921, 'theme', 'iiidem2');
    }

    // Teacher sub-pages keep Professors sidebar; refresh lang cache for maxsize string.
    if ($oldversion < 2024100922) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100922, 'theme', 'iiidem2');
    }

    // Hide course secondary nav (Home / Content bank) on teacher dashboard pages.
    if ($oldversion < 2024100923) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100923, 'theme', 'iiidem2');
    }

    // Skip-link out of flow (header/banner white gap).
    if ($oldversion < 2024100924) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100924, 'theme', 'iiidem2');
    }

    // Rename bootstrap/popover AMD → bs4popover (live WAF/empty file broke every page JS).
    if ($oldversion < 2024100925) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100925, 'theme', 'iiidem2');
    }

    // Curriculum in-section group headings (Reading material, Quizzes, …).
    if ($oldversion < 2024100926) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100926, 'theme', 'iiidem2');
    }

    // Student dashboard: own attendance only; teachers keep full roster.
    if ($oldversion < 2024100927) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100927, 'theme', 'iiidem2');
    }

    // Certificate of completion issued after assignment completion.
    if ($oldversion < 2024100928) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('theme_iiidem2_cert_issues');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('code', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, null);
        $table->add_field('studentname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('coursename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('city', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('issuedate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('userid_courseid_uq', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);
        $table->add_index('userid_issuedate', XMLDB_INDEX_NOTUNIQUE, ['userid', 'issuedate']);
        $table->add_index('code_uq', XMLDB_INDEX_UNIQUE, ['code']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        set_config('certificateenabled', '1', 'theme_iiidem2');
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100928, 'theme', 'iiidem2');
    }

    // Account lockout: temporary lock after failed logins (also forced in config.php).
    if ($oldversion < 2024100951) {
        // Mirror config.php defaults into mdl_config for admin UI visibility.
        // Values in config.php remain authoritative (forced settings).
        if ((int) get_config('core', 'lockoutthreshold') <= 0) {
            set_config('lockoutthreshold', 5);
        }
        if ((int) get_config('core', 'lockoutwindow') <= 0) {
            set_config('lockoutwindow', 30 * 60);
        }
        if ((int) get_config('core', 'lockoutduration') <= 0) {
            set_config('lockoutduration', 30 * 60);
        }
        set_config('displayloginfailures', 1);
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100951, 'theme', 'iiidem2');
    }

    // Session fixation: ensure hooks/caches refreshed after session_security helper.
    if ($oldversion < 2024100952) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100952, 'theme', 'iiidem2');
    }

    // Password change must invalidate other active sessions + WS tokens.
    if ($oldversion < 2024100953) {
        set_config('passwordchangelogout', 1);
        set_config('passwordchangetokendeletion', 1);
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100953, 'theme', 'iiidem2');
    }

    // Security response headers (CSP, nosniff, XSS, Referrer, CORS, Clear-Site-Data).
    if ($oldversion < 2024100954) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100954, 'theme', 'iiidem2');
    }

    // Suppress Server / X-Powered-By version disclosure headers.
    if ($oldversion < 2024100955) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100955, 'theme', 'iiidem2');
    }

    // One concurrent browser session per user (new login invalidates previous).
    if ($oldversion < 2024100962) {
        set_config('limitconcurrentlogins', 1);
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100962, 'theme', 'iiidem2');
    }

    // MFA required for privileged accounts (admins / managers / teachers).
    if ($oldversion < 2024100963) {
        \theme_iiidem2\mfa_privileged::enable();
        upgrade_plugin_savepoint(true, 2024100963, 'theme', 'iiidem2');
    }

    // Profile IDOR: students cannot open other users via /user/profile.php?id=.
    if ($oldversion < 2024100964) {
        set_config('forcelogin', 1);
        set_config('forceloginforprofiles', 1);
        set_config('profilesforenrolledusersonly', 1);
        set_config(
            'hiddenuserfields',
            'email,city,country,address,phone1,phone2,icq,skype,yahoo,aim,msn,lastaccess,firstaccess,description'
        );
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100964, 'theme', 'iiidem2');
    }

    // Private files: block executable/script uploads (CWE-434 /user/files.php).
    if ($oldversion < 2024100965) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100965, 'theme', 'iiidem2');
    }

    return true;
}
