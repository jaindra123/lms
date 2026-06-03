<?php

defined('MOODLE_INTERNAL') || die();

$extracontext = $OUTPUT->dashboard_context();

$templatecontext = [
    'output' => $OUTPUT,
];

$templatecontext = array_merge(
    $templatecontext,
    $extracontext
);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template(
    'theme_iiidem2/dashboard/main',
    $templatecontext
);

echo $OUTPUT->footer();