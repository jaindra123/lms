<?php
define('CLI_SCRIPT', true);
require dirname(__DIR__) . '/config.php';
$dir = $CFG->localcachedir . '/requirejs';
$files = glob($dir . '/*') ?: [];
echo "dir=$dir\n";
foreach ($files as $f) {
    echo basename($f) . ' ' . filesize($f) . PHP_EOL;
}
$etag = 'f31f6de6de43bdfdbb5163ac78882b06b0ffa2f3';
$path = $dir . '/' . $etag;
if (!is_file($path) && $files) {
    $path = $files[0];
}
echo "using=$path\n";
if (!is_file($path)) {
    exit(1);
}
file_put_contents('/tmp/requirejs-combo.js', file_get_contents($path));
echo "wrote /tmp/requirejs-combo.js\n";
