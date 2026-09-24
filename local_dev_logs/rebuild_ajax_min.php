<?php
/**
 * Rebuild lib/amd/build/ajax.min.js from src with valid syntax + sesskey header.
 */
passthru('cd /var/www/html && npx --yes terser lib/amd/src/ajax.js -c -m --comments false -o /tmp/ajax.new.min.js', $code);
if ($code !== 0) {
    fwrite(STDERR, "terser failed: $code\n");
    exit($code);
}
passthru('node --check /tmp/ajax.new.min.js', $code);
if ($code !== 0) {
    fwrite(STDERR, "syntax check failed\n");
    exit($code);
}

$c = file_get_contents('/tmp/ajax.new.min.js');
$c = preg_replace('/^define\(\[/', 'define("core/ajax",[', $c, 1);
$c = preg_replace('/^(define\("core\/ajax",\[[^\]]+\],)function\(/', '$1(function(', $c, 1);
$c = rtrim($c);
if (str_ends_with($c, '});')) {
    $c = substr($c, 0, -2) . '));'; // }}); -> }));
}
$c .= "\n//# sourceMappingURL=ajax.min.js.map\n";

$header = <<<'HDR'
/**
 * Standard Ajax wrapper for Moodle. It calls the central Ajax script,
 * which can call any existing webservice using the current session.
 * In addition, it can batch multiple requests and return multiple responses.
 *
 * @module     core/ajax
 * @copyright  2015 Damyon Wiese <damyon@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      2.9
 */
HDR;

$out = $header . $c;
file_put_contents('/var/www/html/lib/amd/build/ajax.min.js', $out);

// Check only the define body (skip comment header for node --check of define).
file_put_contents('/tmp/ajax.check.js', $c);
passthru('node --check /tmp/ajax.check.js', $code);
if ($code !== 0) {
    fwrite(STDERR, "final syntax check failed\n");
    exit($code);
}

echo "OK bytes=" . strlen($out) . "\n";
echo (str_contains($out, 'X-Moodle-Sesskey') ? "has sesskey header\n" : "MISSING sesskey header\n");
echo (preg_match('/service\.php\?[^"\']*sesskey=/', $out) ? "WARN sesskey still in query\n" : "no sesskey in query\n");
echo substr($out, strpos($out, 'define'), 90) . "\n";
