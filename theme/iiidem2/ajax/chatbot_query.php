<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * AJAX endpoint: homepage chatbot ask + conversation history.
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
    global $PAGE, $SESSION;

    $PAGE->set_context(context_system::instance());
    require_sesskey();

    $action = optional_param('action', 'ask', PARAM_ALPHANUMEXT);

    if ($action === 'history') {
        $email = required_param('email', PARAM_EMAIL);
        $email = trim($email);
        $history = theme_iiidem2_chatbot_history_for_email($email, 30);
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'items' => $history,
        ]);
        exit;
    }

    $name = required_param('name', PARAM_TEXT);
    $email = required_param('email', PARAM_EMAIL);
    $query = required_param('query', PARAM_TEXT);

    $name = trim(clean_param($name, PARAM_TEXT));
    $email = trim($email);
    $query = trim(clean_param($query, PARAM_TEXT));

    if (\core_text::strlen($name) < 2 || \core_text::strlen($query) < 2) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'validation',
            'message' => theme_iiidem2_str(
                'homepagechatbotvalidation',
                null,
                'Please enter your name, a valid email, and a short question.'
            ),
        ]);
        exit;
    }

    if (\core_text::strlen($query) > 2000) {
        $query = \core_text::substr($query, 0, 2000);
    }

    $last = (int) ($SESSION->theme_iiidem2_chatbot_lastsent ?? 0);
    if ($last && (time() - $last) < 10) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => 'throttle',
            'message' => theme_iiidem2_str(
                'homepagechatbotthrottle',
                null,
                'Please wait a moment before sending another question.'
            ),
        ]);
        exit;
    }

    $ok = theme_iiidem2_send_chatbot_query($name, $email, $query);
    if ($ok) {
        $SESSION->theme_iiidem2_chatbot_lastsent = time();
    }

    ob_end_clean();
    echo json_encode([
        'success' => (bool) $ok,
        'message' => $ok
            ? theme_iiidem2_str(
                'homepagechatbotsuccess',
                null,
                'Sent to the administrator. Their reply will appear here and in your email.'
            )
            : theme_iiidem2_str(
                'homepagechatboterror',
                null,
                'Sorry, we could not send your question. Please try again.'
            ),
        'history' => $ok ? theme_iiidem2_chatbot_history_for_email($email, 30) : [],
    ]);
} catch (Throwable $e) {
    ob_end_clean();
    error_log('IIIDEM homepage chatbot error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo json_encode([
        'success' => false,
        'error' => 'exception',
        'message' => theme_iiidem2_str(
            'homepagechatboterror',
            null,
            'Sorry, we could not send your question. Please try again.'
        ) . ' (' . $e->getMessage() . ')',
    ]);
}
