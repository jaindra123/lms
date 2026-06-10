<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

$course = $DB->get_record('course', ['id' => 2], '*', IGNORE_MISSING)
    ?: $DB->get_record('course', [], '*', IGNORE_MISSING);
if (!$course) {
    echo "no course\n";
    exit(1);
}

$ctx = theme_iiidem2_get_course_curriculum_context($course);
echo 'previewlabel=' . $ctx['previewlabel'] . "\n";

$html = $OUTPUT->render_from_template('theme_iiidem2/course/curriculum', $ctx);
if (preg_match_all('/btn-primary[^>]*>([^<]*)</', $html, $matches)) {
    foreach ($matches[1] as $i => $text) {
        echo 'button ' . $i . ' text: [' . trim($text) . "]\n";
    }
} else {
    echo "no button found\n";
}
