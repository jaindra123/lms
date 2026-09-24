<?php
define('CLI_SCRIPT', true);
require dirname(__DIR__) . '/config.php';
echo 'cachejs=' . (int) (!empty($CFG->cachejs)) . PHP_EOL;
echo 'jsrev=' . (isset($CFG->jsrev) ? $CFG->jsrev : 'unset') . PHP_EOL;
echo 'wwwroot=' . $CFG->wwwroot . PHP_EOL;
echo 'localcachedir=' . $CFG->localcachedir . PHP_EOL;
$dir = $CFG->localcachedir . '/requirejs';
echo 'requirejs_dir_exists=' . (int) is_dir($dir) . PHP_EOL;
if (is_dir($dir)) {
    $files = glob($dir . '/*') ?: [];
    echo 'requirejs_files=' . count($files) . PHP_EOL;
    foreach (array_slice($files, 0, 15) as $f) {
        echo basename($f) . ' ' . filesize($f) . PHP_EOL;
    }
}
$first = $CFG->dirroot . '/lib/amd/build/first.min.js';
echo 'first.min.js=' . (int) file_exists($first) . ' bytes=' . (file_exists($first) ? filesize($first) : 0) . PHP_EOL;
