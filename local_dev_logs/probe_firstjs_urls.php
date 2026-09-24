<?php
$urls = [
    'https://iiidem-certification.ddev.site/lib/requirejs.php/1789985023/core/first',
    'https://iiidem-certification.ddev.site/lib/requirejs.php/1789985023/core/first.js',
    'http://iiidem-certification.ddev.site/lib/requirejs.php/1789985023/core/first',
    'http://iiidem-certification.ddev.site/admin/search.php',
    'https://iiidem-certification.ddev.site/lib/javascript.php/1789985023/lib/requirejs/require.min.js',
];
foreach ($urls as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'probe',
    ]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
    curl_close($ch);
    $header = substr($raw, 0, strpos($raw, "\r\n\r\n") ?: 400);
    $body = substr($raw, (strpos($raw, "\r\n\r\n") ?: 0) + 4, 80);
    echo "URL $url\nCODE $code SIZE $size\nHEAD " . str_replace("\r\n", ' | ', substr($header, 0, 300)) . "\nBODY " . str_replace("\n", ' ', substr($body, 0, 80)) . "\n\n";
}

$combo = '/var/moodledata/localcache/requirejs/fe21f373cb91f3c1720c648fd8bbe1bb9db5a94d';
$fh = fopen($combo, 'rb');
$start = fread($fh, 120);
fclose($fh);
echo "COMBO_START $start\n";
echo 'has_core_first=' . (int) (str_contains(file_get_contents($combo, false, null, 0, 500000), 'define("core/first"') || str_contains(file_get_contents($combo, false, null, 0, 500000), "define('core/first'")) . "\n";
