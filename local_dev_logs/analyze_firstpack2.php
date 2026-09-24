<?php
$pack = file_get_contents(__DIR__ . '/firstpack.js');
echo "len=" . strlen($pack) . "\n";

// Locate core/first define
$p = strpos($pack, 'define("core/first"');
echo "first_define_at=" . ($p === false ? 'MISSING' : $p) . "\n";

// Find invalid-ish patterns
$patterns = [
    'call_empty_arg' => '/\(\s*,/',
    'trailing_call_comma_close' => '/,\s*\)/',  // trailing commas in calls - valid in modern JS
    'object_empty_prop' => '/\{\s*,/',
    'comma_comma_not_array' => '/[^\[\],\s],\s*,[^,\]]/', 
];

foreach ($patterns as $name => $re) {
    if (preg_match($re, $pack, $m, PREG_OFFSET_CAPTURE)) {
        $pos = $m[0][1];
        echo "$name at $pos :: " . substr($pack, max(0, $pos - 40), 100) . "\n";
    } else {
        echo "$name: none\n";
    }
}

// Check if pack is valid enough for node/acorn - use php token? can't parse JS.
// Look near webpack elision - confirm only array elision.
$pos = strpos($pack, '__webpack_modules__=[');
echo "webpack_at=$pos\n";
echo substr($pack, $pos, 80) . "\n";

// Does Moodle serve this as the module for core/first - requirejs expects define name match.
// When first module in file is tooltip, require completes load for first.js URL
// but the requested module name is core/first - if define("core/first") appears LATER
// in same file it should still register. Unless SyntaxError aborts parse early!

// Find first syntax error candidate before core/first define: bare ,,
$chunk = substr($pack, 0, $p ?: 500000);
if (preg_match('/\(\s*,/', $chunk, $m, PREG_OFFSET_CAPTURE)) {
    echo "EARLY empty call arg at " . $m[0][1] . ": " . substr($chunk, max(0,$m[0][1]-50), 120) . "\n";
}

// Check for PHP warnings injected
if (preg_match('/Warning:|Notice:|Fatal error:|<br\s*\/?\s*>|<html/i', $pack)) {
    echo "PHP_OR_HTML_INJECTED\n";
} else {
    echo "no php/html injection\n";
}

// First 200 chars after last define before a potential break
echo "---\n";
// Count defines
echo 'define_count=' . substr_count($pack, 'define(') . "\n";
