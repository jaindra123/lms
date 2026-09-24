<?php
define('CLI_SCRIPT', true);
require __DIR__ . '/../config.php';

$idle = (int) $CFG->sessiontimeout;
$warn = (int) $CFG->sessiontimeoutwarning;
$dbidle = (int) get_config('core', 'sessiontimeout');
echo "CFG sessiontimeout={$idle} warning={$warn} db={$dbidle}\n";

$samples = ['10.206.97.211', '192.168.1.1', '127.0.0.1', '8.8.8.8', '117.193.86.77'];
foreach ($samples as $ip) {
    $priv = \theme_iiidem2\private_ip::is_private($ip) ? 'private' : 'public';
    echo "$ip => $priv => " . \theme_iiidem2\private_ip::display($ip) . "\n";
}

$pub = \theme_iiidem2\field_crypto::public_pem();
echo 'field_crypto_pub_len=' . strlen($pub) . "\n";
echo 'theme=' . $CFG->theme . " version=" . get_config('theme_iiidem2', 'version') . "\n";
