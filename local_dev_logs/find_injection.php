<?php
$pack = file_get_contents(__DIR__ . '/firstpack.js');

foreach (['Warning:', 'Notice:', 'Fatal error:', '<br', '<html', '<?php', '<b>', 'Stack trace'] as $needle) {
    $pos = stripos($pack, $needle);
    if ($pos !== false) {
        echo "FOUND '$needle' at $pos\n";
        echo substr($pack, max(0, $pos - 80), 300) . "\n----\n";
    }
}

// Also find any < that isn't inside a string... hard. Find literal HTML tags.
if (preg_match('/<(?:br|html|b|div|span|p)\b/i', $pack, $m, PREG_OFFSET_CAPTURE)) {
    $pos = $m[0][1];
    echo "HTML tag at $pos: " . substr($pack, max(0, $pos - 100), 250) . "\n";
}
