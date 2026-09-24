<?php
/**
 * LMS does not host Razorpay POST /v1/standard_checkout/checkout/prefill/encrypt.
 * Apache rewrites that path here so Intruder against this origin gets HTTP 429
 * instead of a 404 or a proxied encrypt response.
 *
 * @package theme_iiidem2
 */

http_response_code(429);
header('Content-Type: application/json; charset=utf-8');
header('Retry-After: 600');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo '{"ok":false,"success":false,"error":"ratelimit"}';
exit;
