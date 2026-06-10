<?php
require('../../config.php');

global $DB, $PAGE, $OUTPUT;

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url(new moodle_url('/local/coursefaq/add.php'));
$PAGE->set_context($context);
$PAGE->set_title('Add FAQ');
$PAGE->set_heading('Add Course FAQ');

echo $OUTPUT->header();

if (optional_param('submit', false, PARAM_BOOL) && confirm_sesskey()) {

    $courseid = required_param('courseid', PARAM_INT);
    $question = required_param('question', PARAM_TEXT);
    $answer   = required_param('answer', PARAM_RAW);

    $record = new stdClass();
    $record->courseid = $courseid;
    $record->question = $question;
    $record->answer   = $answer;

    if ($DB->insert_record('local_coursefaq', $record)) {

        // Redirect AFTER save (best practice)
        redirect(
            new moodle_url('/local/coursefaq/add.php'),
            'FAQ saved successfully!',
            2,
            \core\output\notification::NOTIFY_SUCCESS
        );

    } else {
        echo $OUTPUT->notification('Insert failed!', 'error');
    }
}

// Get courses
$courses = $DB->get_records('course', null, '', 'id, fullname');
?>

<form method="post">
    <input type="hidden" name="sesskey" value="<?= sesskey() ?>">

    <label>Course</label><br>
    <select name="courseid" required>
        <option value="">Select Course</option>
        <?php foreach ($courses as $course): ?>
            <option value="<?= $course->id ?>">
                <?= format_string($course->fullname) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <br><br>

    <label>Question</label><br>
    <input type="text" name="question" required style="width:400px">

    <br><br>

    <label>Answer</label><br>
    <textarea name="answer" rows="5" cols="50" required></textarea>

    <br><br>

    <button type="submit" name="submit" value="1">Save FAQ</button>

</form>

<?php
echo $OUTPUT->footer();