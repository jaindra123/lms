<?php
$html = file_get_contents('https://iiidem-certification.ddev.site/login/index.php');
if ($html === false) {
    fwrite(STDERR, "fetch failed\n");
    exit(1);
}
echo 'html_bytes=' . strlen($html) . PHP_EOL;
if (preg_match_all('#https?://[^"\']+requirejs[^"\']+#', $html, $m)) {
    foreach (array_unique($m[0]) as $u) {
        echo "REQ $u\n";
    }
}
if (preg_match_all('#baseUrl\s*:\s*[\'"]([^\'"]+)#', $html, $m)) {
    foreach ($m[1] as $u) {
        echo "BASE $u\n";
    }
}
if (str_contains($html, 'websocket_guard')) {
    echo "has websocket_guard\n";
}
if (preg_match('#<script[^>]+src="([^"]*require[^"]*)"#', $html, $m)) {
    echo "REQUIREJS_SCRIPT {$m[1]}\n";
}
