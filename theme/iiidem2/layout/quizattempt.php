<?php
// This file is part of Moodle - http://moodle.org/
//
// @package   theme_iiidem2
// @copyright 2026 IIIDEM
// @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

defined('MOODLE_INTERNAL') || die();

global $CFG, $PAGE, $OUTPUT;

theme_iiidem2_apply_custom_quiz_page_assets($PAGE);

$quizcontext = theme_iiidem2_get_custom_quiz_template_context();

$bodyclasses = [
    'iiidem-quiz-attempt-active',
    'pagelayout-quizattempt',
    'uses-quiz-mcq-ui',
];
if (!empty($quizcontext['quizcmid'])) {
    $bodyclasses[] = 'iiidem-custom-quiz-cmid-' . (int) $quizcontext['quizcmid'];
}

$templatecontext = array_merge($quizcontext, [
    'output' => $OUTPUT,
    'bodyattributes' => $OUTPUT->body_attributes($bodyclasses),
    'quizmcqcss' => (new moodle_url('/theme/iiidem2/style/quiz-mcq.css'))->out(false),
]);

echo $OUTPUT->render_from_template('theme_iiidem2/layout/quizattempt', $templatecontext);
