<?php
// Staging upload / login 403 diagnostic (CLI only — never expose in the browser).
// From Moodle root on the server:
//   php theme/iiidem2/cli/check_staging_boot.php
// Also run as the web user (root can read files PHP-FPM cannot):
//   sudo -u apache php theme/iiidem2/cli/check_staging_boot.php
//   sudo -u nginx  php theme/iiidem2/cli/check_staging_boot.php
//   sudo -u www-data php theme/iiidem2/cli/check_staging_boot.php

define('CLI_SCRIPT', true);

$root = dirname(__DIR__, 3);
if (!is_readable($root . '/config.php')) {
    fwrite(STDERR, "Not a Moodle root (config.php missing): {$root}\n");
    exit(1);
}

echo "Moodle root: {$root}\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "SAPI: " . PHP_SAPI . "\n";

$euid = function_exists('posix_geteuid') ? posix_geteuid() : getmyuid();
$uname = '?';
if (function_exists('posix_getpwuid')) {
    $pw = posix_getpwuid((int) $euid);
    $uname = is_array($pw) ? (string) ($pw['name'] ?? '?') : '?';
}
echo "process user: {$uname} (uid {$euid})\n";
if ((int) $euid === 0) {
    echo "WARNING: running as root. PHP-FPM is not root — re-run as apache/nginx/www-data.\n";
}
echo "\n";

$required = [
    'config.php',
    'lib/setup.php',
    'login/index.php',
    'theme/iiidem2/version.php',
    'theme/iiidem2/lib.php',
    'theme/iiidem2/layout/login.php',
    'theme/iiidem2/templates/login.mustache',
    'theme/iiidem2/templates/frontpage.mustache',
    'theme/iiidem2/templates/core/loginform.mustache',
    'theme/iiidem2/classes/output/core_renderer.php',
    'theme/iiidem2/cli/check_staging_boot.php',
    'theme/iiidem2/style/login-page.css',
    'theme/iiidem2/javascript/login_credentials_lock.js',
    'theme/iiidem2/javascript/field_crypto.js',
    'theme/iiidem2/lang/en/theme_iiidem2.php',
    'theme/iiidem2/db/upgrade.php',
    'theme/iiidem2/db/hooks.php',
    'theme/iiidem2/classes/hook_listener.php',
    'theme/iiidem2/classes/field_crypto.php',
    'theme/iiidem2/classes/session_security.php',
    'theme/iiidem2/classes/security_headers.php',
    'theme/iiidem2/classes/private_ip.php',
    'theme/iiidem2/classes/https_enforce.php',
    'theme/iiidem2/classes/input_validation.php',
    'theme/iiidem2/classes/rate_limit.php',
    'theme/iiidem2/classes/login_captcha.php',
    'theme/iiidem2/classes/password_policy.php',
    'theme/iiidem2/classes/ajax_request_guard.php',
];

$missing = [];
$parsefail = [];

echo "=== required files ===\n";
foreach ($required as $rel) {
    $path = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_readable($path)) {
        $missing[] = $rel;
        echo "MISSING  {$rel}\n";
        continue;
    }
    $perm = substr(sprintf('%o', (int) fileperms($path)), -4);
    $owner = (string) fileowner($path);
    if (function_exists('posix_getpwuid')) {
        $pw = posix_getpwuid((int) $owner);
        if (is_array($pw) && !empty($pw['name'])) {
            $owner = $pw['name'];
        }
    }
    echo "ok       {$perm} {$owner}  {$rel}\n";
    if (!str_ends_with($rel, '.php')) {
        continue;
    }
    $src = file_get_contents($path);
    if ($src === false || $src === '') {
        $parsefail[] = $rel . ' (empty)';
        continue;
    }
    try {
        token_get_all($src, TOKEN_PARSE);
    } catch (ParseError $e) {
        $parsefail[] = $rel . ' — ' . $e->getMessage();
    }
}

$secret = $root . DIRECTORY_SEPARATOR . 'config.staging.php';
echo "\n=== env secret file ===\n";
echo is_readable($secret)
    ? "ok       config.staging.php (present — do not overwrite from local DDEV)\n"
    : "MISSING  config.staging.php (staging cannot boot without this)\n";

if ($missing || $parsefail) {
    echo "\n=== file problems ===\n";
    foreach ($missing as $rel) {
        echo "upload missing file: {$rel}\n";
    }
    foreach ($parsefail as $msg) {
        echo "PHP parse error: {$msg}\n";
    }
}

$diskver = null;
$versionsrc = @file_get_contents($root . '/theme/iiidem2/version.php');
if (is_string($versionsrc) && preg_match('/\$plugin->version\s*=\s*(\d+)/', $versionsrc, $m)) {
    $diskver = $m[1];
}

echo "\n=== Moodle boot ===\n";
try {
    require($root . '/config.php');
} catch (Throwable $e) {
    echo "BOOT EXCEPTION: " . get_class($e) . "\n";
    echo $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
}

global $CFG, $DB;
echo "MOODLE_ENV=" . (defined('MOODLE_ENV') ? MOODLE_ENV : '(unset)') . "\n";
echo "wwwroot=" . ($CFG->wwwroot ?? '(unset)') . "\n";
echo "dataroot=" . ($CFG->dataroot ?? '(unset)') . "\n";
echo "dataroot writable=" . (!empty($CFG->dataroot) && is_writable($CFG->dataroot) ? 'yes' : 'NO') . "\n";

$dbver = null;
try {
    $dbver = $DB->get_field('config_plugins', 'value', [
        'plugin' => 'theme_iiidem2',
        'name' => 'version',
    ]);
} catch (Throwable $e) {
    echo "DB version lookup failed: " . $e->getMessage() . "\n";
}

echo "theme_iiidem2 disk version=" . ($diskver ?? '(unparsed)') . "\n";
echo "theme_iiidem2 db version=" . ($dbver !== null ? $dbver : '(none)') . "\n";
if ($diskver && $dbver && (string) $diskver !== (string) $dbver) {
    echo "NEED UPGRADE: php admin/cli/upgrade.php --non-interactive\n";
}

$classes = [
    \theme_iiidem2\field_crypto::class,
    \theme_iiidem2\private_ip::class,
    \theme_iiidem2\session_security::class,
    \theme_iiidem2\security_headers::class,
    \theme_iiidem2\login_captcha::class,
    \theme_iiidem2\password_policy::class,
    \theme_iiidem2\hook_listener::class,
];
echo "\n=== autoload ===\n";
foreach ($classes as $class) {
    try {
        echo (class_exists($class) ? 'ok       ' : 'MISSING  ') . $class . "\n";
    } catch (Throwable $e) {
        echo "FAIL     {$class} — " . $e->getMessage() . "\n";
    }
}

echo "\n=== upgrade lock ===\n";
try {
    $lock = get_config('core', 'upgraderunning');
    if (!empty($lock)) {
        echo "STUCK upgraderunning={$lock} — run:\n";
        echo "  php -r \"define('CLI_SCRIPT', true); require 'config.php'; unset_config('upgraderunning');\n";
    } else {
        echo "ok       no upgraderunning lock\n";
    }
} catch (Throwable $e) {
    echo "lock check failed: " . $e->getMessage() . "\n";
}

echo "\nCLI boot OK. Browser 403 is PHP-FPM (not this root CLI).\n";
echo "Next:\n";
echo "  1) sudo -u apache php theme/iiidem2/cli/check_staging_boot.php\n";
echo "  2) php admin/cli/upgrade.php --non-interactive && php admin/cli/purge_caches.php\n";
echo "  3) grep -E 'Moodle exception|PHP Fatal' /var/log/php-fpm/error.log | tail\n";
exit($missing || $parsefail ? 2 : 0);
