<?php
define('CLI_SCRIPT', true);
require dirname(__DIR__) . '/config.php';
use theme_iiidem2\input_validation;

$cases = [
    '{"withcheckboxes":true}' => true,
    '{base}' => false,
    '{base}" a="' => false,
    '<script>alert(1)</script>' => false,
    'admin' => true,
    'xmlns:xsi=' => false,
];
foreach ($cases as $in => $keep) {
    $out = input_validation::sanitize_keyword_token($in);
    $ok = $keep ? ($out !== '') : ($out === '');
    echo ($ok ? 'OK' : 'FAIL') . " in=" . $in . " out=" . var_export($out, true) . "\n";
}
echo 'json_doc withcheckboxes=' . (int) input_validation::is_plain_json_document('{"withcheckboxes":true}') . "\n";
echo 'json_doc {base}=' . (int) input_validation::is_plain_json_document('{base}') . "\n";
echo 'structured {base}=' . (int) input_validation::contains_structured_injection('{base}" a="') . "\n";
