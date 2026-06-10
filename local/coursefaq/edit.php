<?php
require('../../config.php');

global $DB, $OUTPUT, $PAGE;

require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$id = required_param('id', PARAM_INT);
$faq = $DB->get_record('local_coursefaq', ['id' => $id], '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/local/coursefaq/edit.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('editfaq', 'local_coursefaq'));
$PAGE->set_heading(get_string('editfaq', 'local_coursefaq'));

if (optional_param('submit', false, PARAM_BOOL) && confirm_sesskey()) {
    $faq->question = required_param('question', PARAM_TEXT);
    $faq->answer = required_param('answer', PARAM_RAW);

    $DB->update_record('local_coursefaq', $faq);

    redirect(
        new moodle_url('/local/coursefaq/index.php'),
        get_string('faqupdated', 'local_coursefaq'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
?>

<form method="post" action="<?= (new moodle_url('/local/coursefaq/edit.php', ['id' => $id]))->out(false) ?>">
    <input type="hidden" name="sesskey" value="<?= sesskey() ?>">
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="mb-3">
        <label for="question" class="form-label"><?= get_string('question', 'local_coursefaq') ?></label>
        <input type="text" class="form-control" name="question" id="question"
               value="<?= s($faq->question) ?>" required>
    </div>

    <div class="mb-3">
        <label for="answer" class="form-label"><?= get_string('answer', 'local_coursefaq') ?></label>
        <textarea class="form-control" name="answer" id="answer" rows="5" required><?= s($faq->answer) ?></textarea>
    </div>

    <button type="submit" name="submit" value="1" class="btn btn-primary"><?= get_string('savechanges') ?></button>
    <?= html_writer::link(new moodle_url('/local/coursefaq/index.php'), get_string('cancel'), ['class' => 'btn btn-secondary ms-2']) ?>
</form>

<?php
echo $OUTPUT->footer();
