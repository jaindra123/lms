<?php
$path = dirname(__DIR__) . '/theme/iiidem2/amd/build/course_payment.min.js';
$t = file_get_contents($path);
$marker = 'return{init:function(){initialised||(initialised=!0,registerEventListeners())}}}));';
$idx = strpos($t, $marker);
if ($idx === false) {
    fwrite(STDERR, "marker not found\n");
    exit(1);
}
$out = substr($t, 0, $idx + strlen($marker)) . "\n";
file_put_contents($path, $out);
echo "bytes=" . strlen($out) . PHP_EOL;
echo "tail=" . substr(rtrim($out), -90) . PHP_EOL;
