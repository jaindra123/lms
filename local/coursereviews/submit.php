<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();
require_sesskey();

$courseid = required_param('courseid', PARAM_INT);
$rating = required_param('rating', PARAM_INT);
$reviewtext = required_param('reviewtext', PARAM_TEXT);

$course = get_course($courseid);
$returnurl = new moodle_url('/course/view.php', ['id' => $courseid]);
$returnurl->set_anchor('student-reviews');

require_course_login($course);

if (!local_coursereviews_user_can_review($course)) {
    throw new moodle_exception('notenrolled', 'local_coursereviews');
}

if ($rating < 1 || $rating > 5) {
    throw new moodle_exception('ratingrequired', 'local_coursereviews');
}

if (trim($reviewtext) === '') {
    throw new moodle_exception('reviewtextrequired', 'local_coursereviews');
}

global $USER, $DB;

$existing = $DB->get_record('local_coursereviews', [
    'courseid' => $courseid,
    'userid' => (int) $USER->id,
]);

if (!local_coursereviews_save_review($courseid, (int) $USER->id, $rating, $reviewtext)) {
    throw new moodle_exception('reviewerror', 'local_coursereviews');
}

$message = $existing
    ? get_string('reviewupdated', 'local_coursereviews')
    : get_string('reviewsubmitted', 'local_coursereviews');

redirect($returnurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
