<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');

$url = $CFG->wwwroot . '/lib/requirejs.php/' . $CFG->jsrev . '/core/first.js';
echo "Fetching $url\n";

// Fetch via file path simulation of what requirejs serves - use curl in-process is hard;
// instead read from localcache if present.
$candidates = [
    $CFG->localcachedir . '/js/' . $CFG->jsrev . '/requirejs/',
    $CFG->dataroot . '/localcache/js/' . $CFG->jsrev . '/',
    $CFG->dataroot . '/cache/js/',
];
foreach ($candidates as $dir) {
    echo "dir $dir exists=" . (is_dir($dir) ? '1' : '0') . "\n";
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        $n = 0;
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.js')) {
                echo '  ' . $f->getPathname() . ' size=' . $f->getSize() . "\n";
                if (++$n > 15) {
                    echo "  ...\n";
                    break;
                }
            }
        }
    }
}

// Direct HTTP via file_get_contents with stream context ignoring SSL.
$ctx = stream_context_create([
    'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    'http' => ['timeout' => 30, 'header' => "Host: iiidem-certification.ddev.site\r\n"],
]);
$pack = @file_get_contents('https://127.0.0.1/lib/requirejs.php/' . $CFG->jsrev . '/core/first.js', false, $ctx);
if ($pack === false) {
    // Try via CLI curl saved file approach using shell.
    echo "file_get_contents failed\n";
    exit(1);
}
echo 'pack_len=' . strlen($pack) . "\n";
echo 'starts=' . substr($pack, 0, 120) . "\n";
echo 'has_define_first=' . (strpos($pack, 'define("core/first"') !== false || strpos($pack, "define('core/first'") !== false ? '1' : '0') . "\n";

// Find first syntax-suspicious ",," near start of file.
$pos = strpos($pack, ',,');
echo 'first_double_comma=' . ($pos === false ? 'none' : (string) $pos) . "\n";
if ($pos !== false) {
    echo 'context=' . substr($pack, max(0, $pos - 60), 140) . "\n";
}

file_put_contents(__DIR__ . '/firstpack.js', $pack);
echo "saved local_dev_logs/firstpack.js\n";
