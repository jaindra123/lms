<?php
$ctx = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
$html = file_get_contents('https://iiidem-certification.ddev.site/login/index.php', false, $ctx);
if ($html === false) {
    fwrite(STDERR, "failed to fetch login\n");
    exit(1);
}
if (!preg_match('#(/lib/requirejs.php[^"\']+)#', $html, $m)) {
    fwrite(STDERR, "no requirejs url\n");
    exit(1);
}
$path = html_entity_decode($m[1], ENT_QUOTES);
echo "url=$path\n";
$js = file_get_contents('https://iiidem-certification.ddev.site' . $path, false, $ctx);
if ($js === false) {
    fwrite(STDERR, "failed to fetch js\n");
    exit(1);
}
echo "len=" . strlen($js) . "\n";
echo "head=" . str_replace("\n", "\\n", substr($js, 0, 80)) . "\n";
echo "has_nonce_tag=" . (preg_match('/<script\s+nonce=/i', $js) ? 'YES' : 'no') . "\n";
$lines = explode("\n", $js);
echo "lines=" . count($lines) . "\n";
if (isset($lines[590])) {
    echo "line591=" . substr($lines[590], 0, 200) . "\n";
}
exit(preg_match('/<script\s+nonce=/i', $js) ? 2 : 0);
