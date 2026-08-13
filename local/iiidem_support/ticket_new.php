<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/form/ticket_form.php');

use local_iiidem_support\manager;

require_login();
require_capability('local/iiidem_support:submit', context_system::instance());

// Early throttle on create form POSTs (create_ticket also enforces user+IP limits).
if (class_exists('\theme_iiidem2\rate_limit') && strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    \theme_iiidem2\rate_limit::require_allowed('support_ticket_new_post', 10, 60);
}

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/iiidem_support/ticket_new.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('ticketnew', 'local_iiidem_support'));
$PAGE->set_heading(get_string('ticketnew', 'local_iiidem_support'));
local_iiidem_support_page_requirements();

require_once($CFG->libdir . '/enrollib.php');
$courses = enrol_get_users_courses($USER->id, true, 'id,fullname', 'fullname ASC');

$form = new local_iiidem_support_ticket_form(null, ['courses' => $courses]);

if ($data = $form->get_data()) {
    $ticketid = manager::create_ticket($USER->id, $data);
    redirect(
        new moodle_url('/local/iiidem_support/ticket.php', ['id' => $ticketid]),
        get_string('ticketcreated', 'local_iiidem_support'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('ticketnewlead', 'local_iiidem_support'), ['class' => 'iiidem-support-lead']);
$form->display();
echo $OUTPUT->footer();
