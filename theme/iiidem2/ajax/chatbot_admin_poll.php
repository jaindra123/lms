<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Poll pending homepage chatbot toasts for the logged-in admin.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/theme/iiidem2/lib.php');

header('Content-Type: application/json; charset=utf-8');

while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

try {
    global $PAGE;

    $PAGE->set_context(context_system::instance());
    require_login(null, false, null, false, true);
    require_sesskey();

    \theme_iiidem2\rate_limit::require_json('chatbot_admin_poll', 30, 60);

    if (!is_siteadmin()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'items' => []]);
        exit;
    }

    $sinceid = optional_param('sinceid', 0, PARAM_INT);
    $items = theme_iiidem2_chatbot_pending_since($sinceid);

    ob_end_clean();
    echo json_encode(['success' => true, 'items' => array_values($items)]);
} catch (Throwable $e) {
    ob_end_clean();
    $payload = \theme_iiidem2\safe_errors::json($e, 'chatbot_admin_poll', false);
    $payload['items'] = [];
    echo json_encode($payload);
}
