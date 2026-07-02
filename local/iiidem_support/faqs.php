<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_iiidem_support\manager;

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/iiidem_support/faqs.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('faqs', 'local_iiidem_support'));
$PAGE->set_heading(get_string('faqs', 'local_iiidem_support'));
local_iiidem_support_page_requirements();

$faqs = manager::get_user_faqs($USER->id);

echo $OUTPUT->header();
echo html_writer::start_div('iiidem-support-page iiidem-course-faq');
echo html_writer::tag('p', get_string('faqslead', 'local_iiidem_support'), ['class' => 'iiidem-support-lead']);

if (empty($faqs)) {
    echo $OUTPUT->notification(get_string('nofaqs', 'local_iiidem_support'), 'notifymessage');
} else {
    echo html_writer::start_div('accordion iiidem-accordion', ['id' => 'iiidemSupportFaq']);
    foreach ($faqs as $faq) {
        $headingid = 'faqheading' . $faq->id;
        $collapseid = 'faqcollapse' . $faq->id;
        echo html_writer::start_div('accordion-item');
        echo html_writer::tag('h3',
            html_writer::tag('button', s($faq->question), [
                'class' => 'accordion-button collapsed',
                'type' => 'button',
                'data-bs-toggle' => 'collapse',
                'data-bs-target' => '#' . $collapseid,
                'aria-expanded' => 'false',
                'aria-controls' => $collapseid,
            ]),
            ['class' => 'accordion-header', 'id' => $headingid]
        );
        echo html_writer::start_div('accordion-collapse collapse', ['id' => $collapseid, 'data-bs-parent' => '#iiidemSupportFaq']);
        echo html_writer::start_div('accordion-body');
        echo html_writer::tag('div', $faq->coursename, ['class' => 'iiidem-support-faq__course']);
        echo $faq->answer;
        echo html_writer::end_div();
        echo html_writer::end_div();
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}

$actions = html_writer::link(
    new moodle_url('/local/iiidem_support/ticket_new.php'),
    get_string('raiseticket', 'local_iiidem_support'),
    ['class' => 'btn btn-primary']
);
$actions .= html_writer::link(
    new moodle_url('/contact-us/'),
    get_string('contactsupport', 'local_iiidem_support'),
    ['class' => 'btn btn-outline-primary']
);
echo html_writer::div($actions, 'iiidem-support-actions');

echo html_writer::end_div();
echo $OUTPUT->footer();
