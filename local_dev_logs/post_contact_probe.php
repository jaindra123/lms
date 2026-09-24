<?php
/**
 * Local retest: JSON/XML Intruder payload on /contact-us/ must not reflect or send.
 */
$base = getenv('WWWROOT') ?: 'http://127.0.0.1:8080';
$cookiefile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cjar_contact';
@unlink($cookiefile);

function http_req(string $url, string $cookiefile, ?string $post = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookiefile,
        CURLOPT_COOKIEFILE => $cookiefile,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER => ['Expect:'],
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    }
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$code, (string) $body, $err];
}

[$code, $html, $err] = http_req($base . '/contact-us/', $cookiefile);
file_put_contents(__DIR__ . '/contact_get_out.html', $html);
if ($code !== 200 || $html === '') {
    fwrite(STDERR, "GET failed http=$code err=$err len=" . strlen($html) . "\n");
    exit(2);
}

if (!preg_match('/name="sesskey"[^>]*value="([^"]+)"/', $html, $m)
        && !preg_match('/"sesskey":"([^"]+)"/', $html, $m)) {
    fwrite(STDERR, "No sesskey\n");
    exit(3);
}
$sesskey = $m[1];

$qf = '_qf__theme_iiidem2_form_contact_form';
if (!str_contains($html, $qf)) {
    // Moodle may shorten or hash; find any _qf__
    if (preg_match('/name="(_qf__[^"]+)"/', $html, $qm)) {
        $qf = $qm[1];
    }
}

$payload = '{base}" a="';
$fields = [
    'sesskey' => $sesskey,
    $qf => '1',
    'name' => 'Minu',
    'email' => 'maya@cdec.in',
    'subject' => $payload,
    'message' => $payload,
];
$post = http_build_query($fields);

[$code2, $html2, $err2] = http_req($base . '/contact-us/', $cookiefile, $post);
file_put_contents(__DIR__ . '/contact_post_out.html', $html2);
echo "POST http=$code2\n";
if ($err2 !== '') {
    echo "curlerr=$err2\n";
}

$needles = [
    '{base}" a="',
    '{base}',
    '%7bbase%7d',
    '%7Bbase%7D',
    'contactusformsent',
    'Thank you for contacting',
];
$fail = 0;
foreach ($needles as $n) {
    $hit = str_contains($html2, $n);
    echo ($hit ? 'HIT ' : 'ok  ') . $n . "\n";
    if ($hit && $n !== 'contactusformsent') {
        // success string in lang pack is not in HTML unless sent; contactusformsent is lang key, not body.
    }
    if (in_array($n, ['{base}" a="', '{base}', '%7bbase%7d', '%7Bbase%7D', 'Thank you for contacting'], true) && $hit) {
        $fail++;
    }
}

$haserr = str_contains($html2, 'JSON/XML fragments') || str_contains($html2, 'not allowed in this field');
echo ($haserr ? 'ok  ' : 'MISS') . " err_xss visible\n";
if (!$haserr) {
    $fail++;
}

$subjectval = '';
if (preg_match('/id="id_subject"[^>]*value="([^"]*)"/', $html2, $sv)) {
    $subjectval = html_entity_decode($sv[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
echo 'subject_value=' . json_encode($subjectval) . "\n";
if ($subjectval !== '' && (str_contains($subjectval, '{base}') || str_contains($subjectval, 'a="'))) {
    echo "FAIL reflected subject\n";
    $fail++;
}

$msgval = '';
if (preg_match('/id="id_message"[^>]*>([\s\S]*?)<\/textarea>/', $html2, $mv)) {
    $msgval = html_entity_decode($mv[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
echo 'message_value=' . json_encode($msgval) . "\n";
if ($msgval !== '' && (str_contains($msgval, '{base}') || str_contains($msgval, 'a="'))) {
    echo "FAIL reflected message\n";
    $fail++;
}

echo "--- encoded payload ---\n";
$fields['subject'] = '%7bbase%7d%22%20a%3d%22';
$fields['message'] = '%7bbase%7d%22%20a%3d%22';
[$code3, $html3] = http_req($base . '/contact-us/', $cookiefile, http_build_query($fields));
echo "POST2 http=$code3\n";
foreach (['%7bbase%7d', '{base}', 'Thank you for contacting'] as $n) {
    $hit = str_contains($html3, $n);
    echo ($hit ? 'HIT ' : 'ok  ') . $n . "\n";
    if ($hit) {
        $fail++;
    }
}
$haserr2 = str_contains($html3, 'JSON/XML fragments') || str_contains($html3, 'not allowed in this field');
echo ($haserr2 ? 'ok  ' : 'MISS') . " err_xss visible\n";
if (!$haserr2) {
    $fail++;
}

exit($fail > 0 ? 1 : 0);
