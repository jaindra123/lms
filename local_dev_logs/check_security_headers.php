<?php
$ch = curl_init('http://127.0.0.1/login/index.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
]);
$raw = curl_exec($ch);
curl_close($ch);
[$headers, $body] = preg_split("/\r\n\r\n/", $raw, 2) ?: ['', ''];
preg_match("/Content-Security-Policy:.*nonce-([^'\\s]+)/i", $headers, $hm);
preg_match('/<script nonce="([^"]+)"/', $body, $bm);
echo 'header_nonce=' . ($hm[1] ?? 'MISSING') . PHP_EOL;
echo 'html_nonce=' . ($bm[1] ?? 'MISSING') . PHP_EOL;
echo 'match=' . ((($hm[1] ?? '') !== '' && ($hm[1] ?? null) === ($bm[1] ?? null)) ? 'yes' : 'no') . PHP_EOL;
echo 'script_src_has_unsafe_inline=' . (preg_match("/script-src [^;]*'unsafe-inline'/", $headers) ? 'yes' : 'no') . PHP_EOL;
echo 'has_clear_site_data=' . (stripos($headers, 'Clear-Site-Data:') !== false ? 'yes' : 'no') . PHP_EOL;
echo 'hsts=' . (preg_match('/Strict-Transport-Security: ([^\r\n]+)/i', $headers, $t) ? $t[1] : 'none (http)') . PHP_EOL;
