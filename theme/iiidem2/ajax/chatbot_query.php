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
 * Server-side authorization:
 * - require_sesskey() on every request
 * - History is bound to record ids created in this PHP session
 *   (never trusts a client-supplied email for reading other users' chats)
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
        \theme_iiidem2\rate_limit::require_json('chatbot_history', 60, 60);
        $history = theme_iiidem2_chatbot_history_for_ids(theme_iiidem2_chatbot_session_ids(), 30);
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'items' => $history,
        ]);
        exit;
    }

    // IP budgets for ask (emails admins) — complements session 10s gap.
    \theme_iiidem2\rate_limit::require_json('chatbot_ask_ip', 5, 600);
    \theme_iiidem2\rate_limit::require_json('chatbot_ask_ip_day', 20, 86400);

    $name = required_param('name', PARAM_TEXT);
    $email = required_param('email', PARAM_EMAIL);
    $query = required_param('query', PARAM_TEXT);

    $name = trim(clean_param($name, PARAM_TEXT));
    $email = trim($email);
    $query = trim(clean_param($query, PARAM_TEXT));

    if (\core_text::strlen($name) < 2 || \core_text::strlen($name) > 100
            || \core_text::strlen($query) < 2) {
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

    $recordid = theme_iiidem2_send_chatbot_query($name, $email, $query);
    $ok = $recordid !== 0;
    $history = [];
    if ($ok) {
        $SESSION->theme_iiidem2_chatbot_lastsent = time();
        $SESSION->theme_iiidem2_chatbot_name = $name;
        // Email is contact metadata only — never an authorization key for history.
        $SESSION->theme_iiidem2_chatbot_email = $email;
        if ($recordid > 0) {
            theme_iiidem2_chatbot_session_remember_id($recordid);
        }
        $history = theme_iiidem2_chatbot_history_for_ids(theme_iiidem2_chatbot_session_ids(), 30);
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
        'history' => $history,
    ]);
} catch (Throwable $e) {
    ob_end_clean();
    $payload = \theme_iiidem2\safe_errors::json($e, 'homepage_chatbot', false);
    $payload['message'] = theme_iiidem2_str(
        'homepagechatboterror',
        null,
        'Sorry, we could not send your question. Please try again.'
    );
    echo json_encode($payload);
}
