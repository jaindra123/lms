<?php
require('../../config.php');

global $DB, $COURSE;

$courseid = required_param('courseid', PARAM_INT);

$faqs = $DB->get_records('local_coursefaq', ['courseid' => $courseid]);

echo $OUTPUT->header();

foreach ($faqs as $faq) {
    echo "<h4>{$faq->question}</h4>";
    echo "<p>{$faq->answer}</p>";
}

echo $OUTPUT->footer();


<div class="accordion" id="faqAccordion">

<?php foreach ($faqs as $faq): ?>
  <div class="card">
    <div class="card-header">
      <button class="btn btn-link" data-toggle="collapse"
        data-target="#faq<?= $faq->id ?>">
        <?= $faq->question ?>
      </button>
    </div>

    <div id="faq<?= $faq->id ?>" class="collapse"
      data-parent="#faqAccordion">

      <div class="card-body">
        <?= $faq->answer ?>
      </div>
    </div>
  </div>
<?php endforeach; ?>

</div>