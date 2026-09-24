<?php
define('CLI_SCRIPT', true);
require dirname(__DIR__) . '/config.php';

$etag = 'fe21f373cb91f3c1720c648fd8bbe1bb9db5a94d';
$cache = $CFG->localcachedir . '/requirejs/' . $etag;
echo 'cache_exists=' . (int) file_exists($cache) . PHP_EOL;
if (file_exists($cache)) {
    echo 'cache_bytes=' . filesize($cache) . PHP_EOL;
    echo 'cache_lines=' . (int) exec('wc -l ' . escapeshellarg($cache)) . PHP_EOL;
    $tail = substr(file_get_contents($cache), -200);
    echo "cache_tail=" . str_replace(["\n", "\r"], ['\\n', ''], $tail) . PHP_EOL;
}

$url = $CFG->wwwroot . '/lib/requirejs.php/' . $CFG->jsrev . '/core/first';
echo "url=$url\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_ENCODING => '',
    CURLOPT_TIMEOUT => 60,
]);
$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
$err = curl_error($ch);
curl_close($ch);
echo "http=$code curl_err=$err download=$size\n";
$sep = strpos($raw, "\r\n\r\n");
$headers = substr($raw, 0, $sep === false ? 0 : $sep);
$body = substr($raw, $sep === false ? 0 : $sep + 4);
echo "body_bytes=" . strlen($body) . " body_lines=" . substr_count($body, "\n") . PHP_EOL;
echo "headers:\n$headers\n";
echo "body_tail=" . str_replace(["\n", "\r"], ['\\n', ''], substr($body, -200)) . PHP_EOL;
echo 'has_core_first_define=' . (int) (str_contains($body, 'define("core/first"') || str_contains($body, "define('core/first'")) . PHP_EOL;
echo 'valid_end=' . (int) (substr(rtrim($body), -2) === '});' || substr(rtrim($body), -3) === '});') . PHP_EOL;
