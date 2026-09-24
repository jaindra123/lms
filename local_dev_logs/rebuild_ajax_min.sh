#!/bin/bash
set -e
cd /var/www/html
npx --yes terser lib/amd/src/ajax.js -c -m --comments false -o /tmp/ajax.new.min.js
node --check /tmp/ajax.new.min.js
php <<'PHP'
<?php
$c = file_get_contents('/tmp/ajax.new.min.js');
$c = preg_replace('/^define\(\[/', 'define("core/ajax",[', $c, 1);
// Prefer IIFE style used by Moodle build files.
$c = preg_replace('/^(define\("core\/ajax",\[[^\]]+\],)function\(/', '$1(function(', $c, 1);
if (str_ends_with(rtrim($c), '});')) {
    $c = preg_replace('/\}\);\s*$/', '}));', rtrim($c)) . "\n";
}
$c .= "//# sourceMappingURL=ajax.min.js.map\n";
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
file_put_contents('/tmp/ajax.check.js', $c);
echo "written bytes=" . strlen($out) . "\n";
PHP
node --check /tmp/ajax.check.js
grep -E 'X-Moodle-Sesskey|sesskey=' /var/www/html/lib/amd/build/ajax.min.js || true
php -r 'echo substr(file_get_contents("/var/www/html/lib/amd/build/ajax.min.js"), 200, 120), "\n";'
