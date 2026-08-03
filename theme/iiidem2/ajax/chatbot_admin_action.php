<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Admin chatbot actions: list open questions / reply.
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
    global $PAGE, $USER;

    $PAGE->set_context(context_system::instance());
    require_login(null, false, null, false, true);
    require_sesskey();

    if (!is_siteadmin()) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'message' => 'Only site administrators can reply.',
        ]);
        exit;
    }

    $action = required_param('action', PARAM_ALPHANUMEXT);

    if ($action === 'list') {
        $items = theme_iiidem2_chatbot_list_open(40);
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'items' => $items,
            'count' => count($items),
        ]);
        exit;
    }

    if ($action === 'reply') {
        $id = required_param('id', PARAM_INT);
        $reply = required_param('reply', PARAM_TEXT);
        $result = theme_iiidem2_chatbot_reply($id, $reply, (int) $USER->id);
        ob_end_clean();
        echo json_encode($result);
        exit;
    }

    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
    ]);
}
