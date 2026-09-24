<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../config.php');

$files = [
    'ajax.min' => $CFG->dirroot . '/lib/amd/build/ajax.min.js',
    'first.min' => $CFG->dirroot . '/lib/amd/build/first.min.js',
    'first' => $CFG->dirroot . '/lib/amd/build/first.js',
];
foreach ($files as $label => $path) {
    echo $label . ': exists=' . (file_exists($path) ? '1' : '0');
    if (file_exists($path)) {
        $c = file_get_contents($path);
        echo ' size=' . strlen($c) . ' start=' . substr(preg_replace('/\s+/', ' ', $c), 0, 80);
    }
    echo PHP_EOL;
}

// Simulate head setup JSON for admin search URL.
$PAGE = new moodle_page();
$PAGE->set_url(new moodle_url('/admin/search.php', [], 'linkusers'));
$clean = \theme_iiidem2\output\url_rewriter::url_rewrite($PAGE->url);
$cleanjs = json_encode($clean->out(false), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
echo 'head_script=history.replaceState&&history.replaceState({},"",' . $cleanjs . ');' . PHP_EOL;
echo 'json_encode_ok=' . ($cleanjs !== false ? '1' : '0') . PHP_EOL;
echo 'html_head=' . \theme_iiidem2\output\url_rewriter::html_head_setup() . PHP_EOL;
