<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin review queue for pending portal registrations.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/theme/iiidem2/admin/pending_registrations.php', $userid ? ['userid' => $userid] : []));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pendingregistrations', 'theme_iiidem2'));
$PAGE->set_heading(get_string('pendingregistrations', 'theme_iiidem2'));

if ($action !== '' && $userid > 0 && confirm_sesskey()) {
    if ($action === 'approve') {
        $result = \theme_iiidem2\registration_approval::approve($userid, (int) $USER->id);
    } else if ($action === 'reject') {
        $result = \theme_iiidem2\registration_approval::reject($userid, (int) $USER->id);
    } else {
        $result = ['success' => false, 'message' => get_string('error')];
    }

    if (!empty($result['success'])) {
        \core\notification::success($result['message']);
    } else {
        \core\notification::error($result['message'] ?? get_string('error'));
    }
    redirect(new moodle_url('/theme/iiidem2/admin/pending_registrations.php'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pendingregistrations', 'theme_iiidem2'));
echo html_writer::tag('p', get_string('pendingregistrationsintro', 'theme_iiidem2'), ['class' => 'text-muted']);

\theme_iiidem2\registration_profile::ensure_fields();
$pending = \theme_iiidem2\registration_approval::get_pending_users(100);

if (!$pending) {
    echo $OUTPUT->notification(get_string('pendingregistrationsempty', 'theme_iiidem2'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('fullname'),
    get_string('email'),
    get_string('registercontact', 'theme_iiidem2'),
    get_string('registeroccupation', 'theme_iiidem2'),
    get_string('city'),
    get_string('country'),
    get_string('date'),
    get_string('actions'),
];
$table->attributes['class'] = 'generaltable iiidem-pending-regs';
$table->data = [];

foreach ($pending as $user) {
    $occupation = \theme_iiidem2\registration_profile::get_profile_value((int) $user->id, 'iiidem_occupation');
    $profilelink = html_writer::link(
        new moodle_url('/user/profile.php', ['id' => $user->id]),
        fullname($user)
    );
    if ($userid === (int) $user->id) {
        $profilelink = html_writer::tag('strong', $profilelink);
    }

    $approveurl = new moodle_url('/theme/iiidem2/admin/pending_registrations.php', [
        'action' => 'approve',
        'userid' => $user->id,
        'sesskey' => sesskey(),
    ]);
    $rejecturl = new moodle_url('/theme/iiidem2/admin/pending_registrations.php', [
        'action' => 'reject',
        'userid' => $user->id,
        'sesskey' => sesskey(),
    ]);

    $actions = html_writer::link($approveurl, get_string('pendingregapprove', 'theme_iiidem2'), [
        'class' => 'btn btn-sm btn-success me-1',
        'onclick' => 'return confirm(' . json_encode(get_string('pendingregapproveconfirm', 'theme_iiidem2')) . ');',
    ]);
    $actions .= html_writer::link($rejecturl, get_string('pendingregreject', 'theme_iiidem2'), [
        'class' => 'btn btn-sm btn-danger',
        'onclick' => 'return confirm(' . json_encode(get_string('pendingregrejectconfirm', 'theme_iiidem2')) . ');',
    ]);

    $row = new html_table_row([
        $profilelink . '<br><small class="text-muted">' . s($user->username) . '</small>',
        s($user->email),
        s($user->phone1 ?? ''),
        s($occupation !== '' ? $occupation : get_string('none')),
        s($user->city ?? ''),
        s($user->country ?? ''),
        userdate((int) $user->timecreated),
        $actions,
    ]);
    if ($userid === (int) $user->id) {
        $row->attributes['class'] = 'table-warning';
    }
    $table->data[] = $row;
}

echo html_writer::table($table);
echo $OUTPUT->footer();
