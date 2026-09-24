<?php
define('CLI_SCRIPT', true);
require __DIR__ . '/../config.php';

$fn = [\theme_iiidem2\input_validation::class, 'contains_structured_injection'];
$samples = [
    '{base}" a="' => true,
    '%7bbase%7d%22%20a%3d%22' => true,
    '{base}\' xmlns:xsi=' => true,
    'Hello, I have a question' => false,
    "Don't call me" => false,
    'Minu' => false,
];
$fail = 0;
foreach ($samples as $s => $expect) {
    $got = \theme_iiidem2\input_validation::contains_structured_injection($s)
        || \theme_iiidem2\input_validation::contains_dangerous_markup($s);
    $ok = $got === $expect;
    echo ($ok ? 'OK' : 'FAIL') . " expect=" . ($expect ? 'reject' : 'allow') . " got=" . ($got ? 'reject' : 'allow') . " [" . $s . "]\n";
    if (!$ok) {
        $fail++;
    }
}
exit($fail > 0 ? 1 : 0);
