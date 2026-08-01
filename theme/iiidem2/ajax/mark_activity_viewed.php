<?php
// This file is part of Moodle - http://moodle.org/

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

header('Content-Type: application/json; charset=utf-8');

// Drop any PHP notices/warnings that would break JSON.parse in the browser.
while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

try {
    require_login(null, false, null, false, true);
    require_sesskey();

    $cmid = required_param('cmid', PARAM_INT);
    $result = theme_iiidem2_mark_curriculum_activity_viewed($cmid);
    ob_end_clean();
    echo json_encode($result);
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'exception']);
}
