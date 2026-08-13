<?php
/**
 * Watch a class video (file player and/or external URL) when allowed.
 *
 * @package local_iiidem_classvideos
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/manager.php');

use local_iiidem_classvideos\manager;

require_login();

$id = required_param('id', PARAM_INT);
$video = manager::get_video($id);
if (!$video || (!(int) $video->visible && !manager::user_can_manage_video($video))) {
    throw new moodle_exception('invalidrecord', 'error');
}

$course = get_course((int) $video->courseid);
$context = context_course::instance($course->id);
require_login($course, false);

if (!manager::user_can_watch($video)) {
    throw new moodle_exception('noaccess', 'local_iiidem_classvideos');
}

$PAGE->set_url(new moodle_url('/local/iiidem_classvideos/watch.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(format_string($video->title));
$PAGE->set_heading(format_string($course->fullname));

$fileurl = manager::get_file_url($video);
$external = trim((string) ($video->externalurl ?? ''));

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($video->title));

if ($video->description) {
    echo html_writer::div(format_text(s($video->description), FORMAT_PLAIN), 'mb-3');
}

if ($fileurl) {
    echo html_writer::tag('video', '', [
        'src' => $fileurl->out(false),
        'controls' => 'controls',
        'controlslist' => 'nodownload',
        'style' => 'width:100%;max-width:960px;background:#000;',
        'playsinline' => 'playsinline',
    ]);
}

if ($external !== '') {
    echo html_writer::div(
        html_writer::link($external, get_string('openexternal', 'local_iiidem_classvideos'), [
            'class' => 'btn btn-primary mt-3',
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
        ]),
        'mt-3'
    );
}

$back = new moodle_url('/local/iiidem_classvideos/index.php');
if (manager::user_can_manage_video($video)) {
    $back = new moodle_url('/local/iiidem_classvideos/manage.php', ['courseid' => $video->courseid]);
}
echo html_writer::div(html_writer::link($back, get_string('back')), 'mt-4');

echo $OUTPUT->footer();
