<?php
require('../../config.php');

global $DB, $OUTPUT;

require_login();

$id = required_param('id', PARAM_INT);

$faq = $DB->get_record('local_coursefaq', ['id' => $id], '*', MUST_EXIST);

if (optional_param('submit', false, PARAM_BOOL)) {

    $faq->question = required_param('question', PARAM_TEXT);
    $faq->answer   = required_param('answer', PARAM_RAW);

    $DB->update_record('local_coursefaq', $faq);

    redirect(new moodle_url('/local/coursefaq/index.php'), 'Updated successfully');
}

echo $OUTPUT->header();
?>

<form method="post">

    <input type="text" name="question" value="<?= $faq->question ?>" required><br><br>

    <textarea name="answer" required><?= $faq->answer ?></textarea><br><br>

    <button type="submit" name="submit" value="1">Update</button>

</form>

<?php
echo $OUTPUT->footer();