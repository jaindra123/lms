<?php
define('CLI_SCRIPT', true);
require dirname(__DIR__) . '/config.php';
$url = $CFG->wwwroot . '/lib/requirejs.php/' . $CFG->jsrev . '/core/first';
echo "jsrev={$CFG->jsrev}\nurl=$url\n";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_ENCODING => '',
    CURLOPT_TIMEOUT => 60,
    CURLOPT_USERAGENT => 'probe',
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "http=$code bytes=" . strlen((string) $body) . " lines=" . substr_count((string) $body, "\n") . PHP_EOL;
file_put_contents('/tmp/requirejs-combo.js', $body);
echo 'has_core_first=' . (int) (str_contains((string) $body, 'define("core/first"') || str_contains((string) $body, "define('core/first'")) . PHP_EOL;
echo 'has_broken_leftover=' . (int) str_contains((string) $body, "define(\"theme_iiidem2/course_payment\",[],(function(){\n") . PHP_EOL;
