<?php
// Diagnostic for assignment / live-class notification emails.
// Usage (from Moodle root):
//   php theme/iiidem2/cli/diagnose_notify.php --cmid=55
//   php theme/iiidem2/cli/diagnose_notify.php --cmid=55 --send=1
//   php theme/iiidem2/cli/diagnose_notify.php --cmid=55 --testemail=you@eci.gov.in

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/moodlelib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'cmid' => 0,
        'send' => 0,
        'testemail' => '',
        'help' => false,
    ],
    [
        'h' => 'help',
    ]
);

if (!empty($options['help']) || (int) $options['cmid'] <= 0) {
    echo "Diagnose theme_iiidem2 assignment / live-class notification emails.\n\n";
    echo "Options:\n";
    echo "  --cmid=ID              Course module id from modedit.php?update=ID\n";
    echo "  --send=1               Send notification emails to enrolled users\n";
    echo "  --testemail=addr       Send one probe mail to this address and print result\n";
    exit(empty($options['help']) ? 1 : 0);
}

$cmid = (int) $options['cmid'];
$dosend = !empty($options['send']);
$testemail = trim((string) $options['testemail']);

echo "=== theme_iiidem2 notify diagnosis ===\n";
echo 'wwwroot: ' . $CFG->wwwroot . "\n";
echo 'theme version: ' . (get_config('theme_iiidem2', 'version') ?: 'MISSING') . "\n";
echo 'noemailever: ' . (!empty($CFG->noemailever) ? 'YES (blocks all mail!)' : 'no') . "\n";
echo 'divertallemails: ' . (!empty($CFG->divertallemailsto) ? $CFG->divertallemailsto : 'no') . "\n";
echo 'smtphosts: ' . (!empty($CFG->smtphosts) ? $CFG->smtphosts : '(empty - PHP mail())') . "\n";
echo 'smtpuser: ' . (!empty($CFG->smtpuser) ? $CFG->smtpuser : '(none)') . "\n";
echo 'smtpsecure: ' . (!empty($CFG->smtpsecure) ? $CFG->smtpsecure : '(none)') . "\n";
echo 'noreplyaddress: ' . (!empty($CFG->noreplyaddress) ? $CFG->noreplyaddress : '(default)') . "\n";

$foundassign = false;
$foundlive = false;
$eventsfile = $CFG->dirroot . '/theme/iiidem2/db/events.php';
if (is_readable($eventsfile)) {
    $observers = [];
    include($eventsfile);
    foreach ($observers as $observer) {
        $callback = (string) ($observer['callback'] ?? '');
        if (strpos($callback, 'assign_notifier') !== false) {
            $foundassign = true;
        }
        if (strpos($callback, 'liveclass_notifier') !== false) {
            $foundlive = true;
        }
    }
    echo 'events.php present: yes (' . count($observers) . " observers declared)\n";
} else {
    echo "events.php present: NO - upload theme/iiidem2/db/events.php\n";
}
echo 'class assign_notifier: ' . (class_exists('\\theme_iiidem2\\assign_notifier') ? 'LOADED' : 'MISSING') . "\n";
echo 'class liveclass_notifier: ' . (class_exists('\\theme_iiidem2\\liveclass_notifier') ? 'LOADED' : 'MISSING') . "\n";
echo 'observer assign_notifier in events.php: ' . ($foundassign ? 'YES' : 'NO') . "\n";
echo 'observer liveclass_notifier in events.php: ' . ($foundlive ? 'YES' : 'NO') . "\n";

$runtimeassign = false;
$runtimelive = false;
$allobservers = \core\event\manager::get_all_observers();
foreach ($allobservers as $eventname => $list) {
    if (!is_array($list)) {
        continue;
    }
    foreach ($list as $observer) {
        $callable = (string) ($observer->callable ?? '');
        if (strpos($callable, 'assign_notifier') !== false) {
            $runtimeassign = true;
        }
        if (strpos($callable, 'liveclass_notifier') !== false) {
            $runtimelive = true;
        }
    }
}
echo 'observer cache assign_notifier: ' . ($runtimeassign ? 'LOADED' : 'MISSING - purge caches!') . "\n";
echo 'observer cache liveclass_notifier: ' . ($runtimelive ? 'LOADED' : 'MISSING - purge caches!') . "\n";
$postactions = function_exists('theme_iiidem2_coursemodule_edit_post_actions');
echo 'coursemodule_edit_post_actions hook: ' . ($postactions ? 'LOADED' : 'MISSING - upload lib.php') . "\n";

$cm = get_coursemodule_from_id(null, $cmid, 0, false, IGNORE_MISSING);
if (!$cm) {
    echo "ERROR: course module {$cmid} not found\n";
    exit(1);
}

echo "cmid={$cm->id} modname={$cm->modname} name={$cm->name} course={$cm->course}\n";

if ($cm->modname === 'assign') {
    $assigndetails = \theme_iiidem2\assign_notifier::extract_details($cm);
    echo 'assign details: ' . ($assigndetails ? json_encode($assigndetails) : 'NULL (needs due date)') . "\n";
} else {
    $livedetails = \theme_iiidem2\liveclass_notifier::extract_session_details($cm);
    echo 'liveclass details: ' . ($livedetails ? json_encode($livedetails) : 'NULL (not detected as live class)') . "\n";
}

$context = context_course::instance((int) $cm->course);
$active = get_enrolled_users($context, '', 0, 'u.id,u.firstname,u.lastname,u.email,u.suspended', null, 0, 0, true);
$all = get_enrolled_users($context, '', 0, 'u.id,u.firstname,u.lastname,u.email,u.suspended', null, 0, 0, false);
echo 'enrolled ACTIVE only: ' . count($active) . "\n";
echo 'enrolled ALL (incl. suspended fee): ' . count($all) . "\n";

$eligible = [];
foreach ($all as $user) {
    $okemail = !empty($user->email) && validate_email($user->email);
    $okaccount = empty($user->suspended);
    $ismanager = has_capability('moodle/course:manageactivities', $context, $user);
    echo sprintf(
        "  user=%d %s <%s> account_suspended=%s email_ok=%s manager=%s\n",
        $user->id,
        trim($user->firstname . ' ' . $user->lastname),
        $user->email,
        !empty($user->suspended) ? 'yes' : 'no',
        $okemail ? 'yes' : 'no',
        $ismanager ? 'yes' : 'no'
    );
    if ($okemail && $okaccount && !$ismanager) {
        $eligible[$user->id] = $user;
    }
}
echo 'eligible recipients: ' . count($eligible) . "\n";

$sender = \core_user::get_noreply_user();
echo 'sender: ' . $sender->email . ' (' . fullname($sender) . ")\n";

if ($testemail !== '') {
    echo "\n=== SMTP probe to {$testemail} ===\n";
    $probe = (object) [
        'id' => -1,
        'email' => $testemail,
        'firstname' => 'Probe',
        'lastname' => 'Recipient',
        'maildisplay' => 1,
        'mailformat' => 1,
        'firstnamephonetic' => '',
        'lastnamephonetic' => '',
        'middlename' => '',
        'alternatename' => '',
        'auth' => 'manual',
        'suspended' => 0,
        'deleted' => 0,
        'emailstop' => 0,
        'lang' => current_language(),
        'username' => 'probe',
    ];
    $subject = 'IIIDEM SMTP probe ' . date('Y-m-d H:i:s');
    $body = "This is a probe email from theme_iiidem2/cli/diagnose_notify.php on {$CFG->wwwroot}.";
    $ok = email_to_user($probe, $sender, $subject, $body);
    echo 'email_to_user result: ' . ($ok ? 'TRUE (Moodle handed mail to SMTP)' : 'FALSE (Moodle/SMTP rejected)') . "\n";
    echo "If TRUE but inbox empty: server relay {$CFG->smtphosts} is likely blocking/dropping this domain.\n";
}

if (!$dosend) {
    if ($testemail === '') {
        echo "\nDry run only.\n";
        echo "  Re-run with --testemail=you@eci.gov.in to test SMTP to an official address.\n";
        echo "  Re-run with --testemail=you@gmail.com to test SMTP to Gmail.\n";
        echo "  Re-run with --send=1 to notify enrolled users.\n";
    }
    exit(0);
}

echo "\n=== Sending notification emails via notifier (HTML template) ===\n";
if ($cm->modname === 'assign') {
    unset_config('assignnotifyhash_' . $cmid, 'theme_iiidem2');
    \theme_iiidem2\assign_notifier::maybe_notify($cmid, 'updated', true);
} else {
    unset_config('liveclassnotifyhash_' . $cmid, 'theme_iiidem2');
    \theme_iiidem2\liveclass_notifier::maybe_notify($cmid, 'updated', true);
}
echo "Done. Check inbox for branded HTML email (logo + template).\n";
echo "Also check PHP error log for theme_iiidem2 *_notifier: sent=N\n";
