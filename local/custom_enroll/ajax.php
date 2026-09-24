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
 * HTTP 410 is sent before require_login so unauthenticated probes do not look
 * like a live 200 API (CDAC amount-manipulation retest).
 *
 * @package local_custom_enroll
 */

define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/../../config.php');

$action = '';
if (!empty($_GET['action'])) {
    $action = preg_replace('/[^a-z0-9_]/i', '', (string) $_GET['action']);
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
    http_response_code(410);
}

$message = 'This enrolment endpoint is retired.';
if (function_exists('get_string')) {
    try {
        $message = get_string('retired', 'local_custom_enroll');
    } catch (Throwable $ignored) {
        // Keep the static fallback.
    }
}

echo json_encode([
    'status' => 'error',
    'ok' => false,
    'success' => false,
    'error' => 'deprecated',
    'action' => $action,
    'message' => $message,
], JSON_UNESCAPED_UNICODE);
exit;
