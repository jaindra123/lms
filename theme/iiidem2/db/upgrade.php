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

    // Unused QR login endpoint — disable Moodle Mobile QR auto-login.
    if ($oldversion < 2024100972) {
        \theme_iiidem2\qr_login_security::disable();
        upgrade_plugin_savepoint(true, 2024100972, 'theme', 'iiidem2');
    }

    // Harden target=_blank links with rel=noopener noreferrer (JS + templates).
    if ($oldversion < 2024100973) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100973, 'theme', 'iiidem2');
    }

    // Input returned in response — safe JSON encoding helpers on AJAX surfaces.
    if ($oldversion < 2024100974) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100974, 'theme', 'iiidem2');
    }

    // Cookie HttpOnly enforcement helpers (session_security header patch).
    if ($oldversion < 2024100975) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100975, 'theme', 'iiidem2');
    }

    // Login username/password: disable paste, drop, autocomplete (policy).
    if ($oldversion < 2024100976) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100976, 'theme', 'iiidem2');
    }

    // Cross-domain referrer leakage — force Referrer-Policy.
    if ($oldversion < 2024100977) {
        set_config('referrerpolicy', 'strict-origin-when-cross-origin');
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100977, 'theme', 'iiidem2');
    }

    // Authenticated pages: Cache-Control no-store (no browser cache after logout).
    if ($oldversion < 2024100978) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100978, 'theme', 'iiidem2');
    }

    // AJAX /lib/ajax/service.php: force no-store (CDAC weaker private,max-age=0 PoC).
    if ($oldversion < 2024100979) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100979, 'theme', 'iiidem2');
    }

    // Contact / course search XSS hardening (reject markup; sanitize search params).
    if ($oldversion < 2024100980) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100980, 'theme', 'iiidem2');
    }

    // AJAX sesskey moved from URL query to X-Moodle-Sesskey header.
    if ($oldversion < 2024100981) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100981, 'theme', 'iiidem2');
    }

    // Re-force debug off on staging/production in after_config (contact-us debug PoC).
    if ($oldversion < 2024100982) {
        set_config('debug', 0);
        set_config('debugdisplay', 0);
        set_config('themedesignermode', 0);
        set_config('perfdebug', 0);
        set_config('debugpageinfo', 0);
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100982, 'theme', 'iiidem2');
    }

    // Messaging / course search XSS guard (message/index.php search PoC).
    if ($oldversion < 2024100983) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100983, 'theme', 'iiidem2');
    }

    // Logout via POST — no sesskey in /login/logout.php URL (CDAC Instance 2).
    if ($oldversion < 2024100984) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100984, 'theme', 'iiidem2');
    }

    // Strip sesskey from all /login/*.php hrefs in user menu / login info HTML.
    if ($oldversion < 2024100985) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100985, 'theme', 'iiidem2');
    }

    // AJAX JSON: strip debuginfo/backtrace (CWE-209 invalidsesskey PoC).
    if ($oldversion < 2024100986) {
        set_config('debug', 0);
        set_config('debugdisplay', 0);
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100986, 'theme', 'iiidem2');
    }

    // CWE-209 Instances 5–6: scrub WS JSON; disable YUI combo loading.
    if ($oldversion < 2024100987) {
        set_config('debug', 0);
        set_config('debugdisplay', 0);
        set_config('yuicomboloading', 0);
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100987, 'theme', 'iiidem2');
    }

    // CDAC #23: re-assert QR login disabled (profile QR PoC).
    if ($oldversion < 2024100988) {
        \theme_iiidem2\qr_login_security::disable();
        upgrade_plugin_savepoint(true, 2024100988, 'theme', 'iiidem2');
    }

    // CDAC #24: target=_blank + rel=noopener (doc_link + HTML buffer).
    if ($oldversion < 2024100989) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100989, 'theme', 'iiidem2');
    }

    // CDAC #25: strip junk path-info on login/register scripts.
    if ($oldversion < 2024100990) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100990, 'theme', 'iiidem2');
    }

    // CDAC form-action XSS: neutralize PATH_INFO in $FULLME for non-slashargument scripts.
    if ($oldversion < 2024100991) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100991, 'theme', 'iiidem2');
    }

    // CDAC register XSS: check_email / OTP never echo input; form rejects markup.
    if ($oldversion < 2024100992) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100992, 'theme', 'iiidem2');
    }

    // CDAC #27: login credentials paste/drop lock in template + JS.
    if ($oldversion < 2024100993) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100993, 'theme', 'iiidem2');
    }

    // CDAC #28: clear mobile setuplink / smart banners (no download.moodle.org footer link).
    if ($oldversion < 2024100994) {
        set_config('setuplink', '', 'tool_mobile');
        set_config('enablesmartappbanners', 0, 'tool_mobile');
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100994, 'theme', 'iiidem2');
    }

    // CDAC #29: bfcache Back-button guard on authenticated pages.
    if ($oldversion < 2024100995) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100995, 'theme', 'iiidem2');
    }

    // Class videos dashboard nav (local_iiidem_classvideos).
    if ($oldversion < 2024100996) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100996, 'theme', 'iiidem2');
    }

    // AJAX sesskey: keep query fallback + header (fix missingparam).
    if ($oldversion < 2024100997) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100997, 'theme', 'iiidem2');
    }

    // Restrict sesskey header to service.php only (fix draftfiles invalidsesskey).
    if ($oldversion < 2024100998) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100998, 'theme', 'iiidem2');
    }

    // Class videos: list above form + panel overflow fix.
    if ($oldversion < 2024100999) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024100999, 'theme', 'iiidem2');
    }

    // Rename bs4popover → bs4flyout (WAF still empties URLs containing "popover").
    if ($oldversion < 2024101000) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024101000, 'theme', 'iiidem2');
    }

    // Bundle bs4flyout into tooltip; loader no longer fetches it (prod WAF).
    if ($oldversion < 2024101001) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024101001, 'theme', 'iiidem2');
    }

    // Force-init popover from bundled tooltip file (fix $.fn.popover is not a function).
    if ($oldversion < 2024101002) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024101002, 'theme', 'iiidem2');
    }

    // Defer enablePopovers until $.fn.popover exists (AMD race with bundled bs4flyout).
    if ($oldversion < 2024101003) {
        purge_all_caches();
        upgrade_plugin_savepoint(true, 2024101003, 'theme', 'iiidem2');
    }

    return true;
}
