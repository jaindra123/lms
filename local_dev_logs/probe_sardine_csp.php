<?php
define('CLI_SCRIPT', true);
require dirname(__DIR__) . '/config.php';
$p = \theme_iiidem2\security_headers::csp_policy();
echo $p, PHP_EOL;
foreach (['sardine', 'checkout.razorpay', 'lumberjack', 'cdn.razorpay', 'api.razorpay'] as $bad) {
    echo ($bad . (stripos($p, $bad) === false ? ' ABSENT' : ' PRESENT')) . PHP_EOL;
}
echo file_exists($CFG->dirroot . '/theme/iiidem2/javascript/websocket_guard.js') ? "guard ok\n" : "guard missing\n";
