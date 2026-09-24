<?php
$c = file_get_contents('/tmp/ajax.new.min.js');
echo "len=" . strlen($c) . "\n";
$i = strpos($c, 'define');
echo substr($c, $i, 100) . "\n";
// Moodle expects named define in build files.
if (preg_match('/define\("core\/ajax"/', $c)) {
    echo "HAS_NAMED_DEFINE\n";
} else {
    echo "NEEDS_NAMED_DEFINE\n";
}
