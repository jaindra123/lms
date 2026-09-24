<?php
define('CLI_SCRIPT', true);
require __DIR__ . '/../config.php';

$cases = [
    '{base},(select*from(select(sleep(2())a)' => true,
    '@#$%^%^ ^&&&&&{base},(select*from(select(sleep(2())a)' => true,
    'select*from(select(sleep(2())a)' => true,
    'UNION SELECT username FROM mdl_user' => true,
    'Maya' => false,
    'select' => false,
    'sleep' => false,
    'course search' => false,
];

$fail = 0;
foreach ($cases as $input => $expectreject) {
    $out = \theme_iiidem2\input_validation::sanitize_keyword_token($input);
    $probe = \theme_iiidem2\input_validation::contains_search_probe($input);
    $rejected = ($out === '');
    $ok = $rejected === $expectreject;
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? 'OK  ' : 'FAIL') . ' reject=' . ($rejected ? '1' : '0')
        . ' probe=' . ($probe ? '1' : '0')
        . ' expect=' . ($expectreject ? '1' : '0')
        . ' in=' . substr($input, 0, 48) . PHP_EOL;
}

$args = ['userid' => 2, 'search' => '{base},(select*from(select(sleep(2())a)', 'limitfrom' => 0];
\theme_iiidem2\ajax_request_guard::sanitize_search_args($args);
echo 'ajax search after sanitize len=' . strlen($args['search'])
    . ' hex=' . bin2hex($args['search']) . PHP_EOL;
if ($args['search'] === '{base},(select*from(select(sleep(2())a)') {
    $fail++;
    echo "FAIL ajax still has probe\n";
}

exit($fail > 0 ? 1 : 0);
