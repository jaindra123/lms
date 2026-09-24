<?php
$pack = file_get_contents(__DIR__ . '/firstpack.js');
// Extract core/ajax define module only
$start = strpos($pack, 'define("core/ajax"');
$end = strpos($pack, 'define("', $start + 10);
$mod = substr($pack, $start, $end - $start);
file_put_contents(__DIR__ . '/ajax_from_pack.js', $mod);
echo "ajax_mod_len=" . strlen($mod) . "\n";
echo $mod . "\n";
