<?php
/**
 * Retired CDAC-vulnerable endpoint tombstone.
 *
 * Old PoC: POST .../local/custom_enroll/ajax.php?action=create_razorpay_order
 * with client-supplied "course_fee" (e.g. "10") created a Razorpay order for ₹10.
 *
 * This file never creates orders and never reads course_fee / amount / user_id
 * as a price. Real payments: payment/gateway/razorpay (paygw_razorpay).
 *
 * @package local_custom_enroll
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');

require_login();

if (class_exists('\theme_iiidem2\rate_limit')) {
    \theme_iiidem2\rate_limit::require_json('local_custom_enroll_ajax', 10, 60);
}

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
if ($action === '' && !empty($_SERVER['CONTENT_TYPE'])
        && stripos($_SERVER['CONTENT_TYPE'], 'json') !== false) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '[]', true);
    if (is_array($json) && isset($json['action'])) {
        $action = clean_param((string) $json['action'], PARAM_ALPHANUMEXT);
    }
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
    http_response_code(410);
}

$message = get_string('retired', 'local_custom_enroll');
$payload = [
    'status' => 'error',
    'ok' => false,
    'success' => false,
    'error' => 'deprecated',
    'action' => $action,
    'message' => $message,
];

if (class_exists('\theme_iiidem2\input_validation')) {
    echo \theme_iiidem2\input_validation::json_encode_safe($payload);
} else {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}
exit;
