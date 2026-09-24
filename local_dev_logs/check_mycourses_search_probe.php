<?php
define('CLI_SCRIPT', true);
require dirname(__DIR__) . '/config.php';

$payload = '(base}" xmlns:xsi="{base}" xmlns:xsi="{base}" xmlns:xsi="';
$cases = [
    $payload => true,
    '{base}" a="' => true,
    '%7bbase%7d%22%20a%3d%22' => true,
    'Election Management' => false,
    'C++ basics' => false,
];
$fail = 0;
foreach ($cases as $s => $expectreject) {
    $probe = \theme_iiidem2\input_validation::contains_search_probe($s)
        || \theme_iiidem2\input_validation::contains_structured_injection($s);
    $clean = \theme_iiidem2\input_validation::sanitize_keyword_token($s);
    $rejected = $probe || $clean === '';
    if ($s === 'Election Management' || $s === 'C++ basics') {
        $rejected = $probe || $clean === '';
    }
    $ok = $rejected === $expectreject;
    if ($s === 'Election Management' || $s === 'C++ basics') {
        $ok = ($clean === $s) && !$probe;
    }
    echo ($ok ? 'OK' : 'FAIL') . ' reject=' . ($rejected ? '1' : '0')
        . ' clean=' . json_encode($clean) . ' [' . $s . "]\n";
    if (!$ok) {
        $fail++;
    }
}

$args = ['searchvalue' => $payload, 'classification' => 'search'];
\theme_iiidem2\ajax_request_guard::sanitize_search_args($args);
echo 'ajax searchvalue=' . json_encode($args['searchvalue']) . "\n";
if ($args['searchvalue'] === $payload) {
    echo "FAIL ajax kept payload\n";
    $fail++;
}
if ($args['searchvalue'] === '' || $args['searchvalue'] === "\x01") {
    echo "FAIL ajax empty/control sentinel (would return all courses)\n";
    $fail++;
}
exit($fail > 0 ? 1 : 0);
