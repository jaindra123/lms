<?php  

unset($CFG);

global $CFG;
$CFG = new stdClass();

/* ===== Database settings ===== */
$CFG->dbtype    = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost    = 'db';
$CFG->dbname    = 'db';
$CFG->dbuser    = 'db';
$CFG->dbpass    = 'db';
$CFG->prefix    = 'mdl_';

$CFG->dboptions = array(
    'dbpersist'    => 0,
    'dbport'       => '',
    'dbsocket'     => '',
    'dbcollation'  => 'utf8mb4_general_ci',
);

/* ===== Site URL ===== */
$CFG->wwwroot = 'https://iiidem-certification.ddev.site';

// Dev: allow access via IP:port (LAN or port-forwarded public IP). Not used for ddev.site.
if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
    $hostheader = $_SERVER['HTTP_HOST'];
    $hostonly = $hostheader;
    if (str_contains($hostheader, ':')) {
        [$hostonly] = explode(':', $hostheader, 2);
    }

    $isprivateip = false;
    if (filter_var($hostonly, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        // Private/reserved ranges (192.168.x.x, 10.x.x.x, 172.16–31.x.x).
        $isprivateip = !filter_var(
            $hostonly,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
    // Shared dev hostname — add "IP iiidem.local" to hosts on each PC (see docs/LAN-ACCESS.md).
    $devhostnames = ['iiidem.local'];
    $isdevhostname = in_array($hostonly, $devhostnames, true);
    // Institution public IP — requires router port-forward to this dev PC.
    $devpublicips = ['164.100.26.245'];
    $isdevpublicip = in_array($hostonly, $devpublicips, true);

    if ($isprivateip || $isdevhostname || $isdevpublicip) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $CFG->wwwroot = $scheme . '://' . $hostheader;
        // DDEV maps host :8080 -> container :80; align SERVER_PORT or Moodle redirect-loops.
        if (str_contains($hostheader, ':')) {
            [, $port] = explode(':', $hostheader, 2);
            if (is_numeric($port)) {
                $_SERVER['SERVER_PORT'] = $port;
            }
        } else {
            $_SERVER['SERVER_PORT'] = ($scheme === 'https') ? '443' : '80';
        }
        $CFG->cookiesecure = false;
    }
}

/* ===== Moodle data directory ===== */
// DDEV: use Docker volume (/var/moodledata) — much faster than moodledata/ on Windows mount.
if (is_dir('/var/moodledata') && is_writable('/var/moodledata')) {
    $CFG->dataroot = '/var/moodledata';
} else if (is_dir(__DIR__ . '/moodledata')) {
    $CFG->dataroot = __DIR__ . '/moodledata';
} else {
    $CFG->dataroot = '/var/www/html/moodledata';
}

/* ===== Admin directory ===== */
$CFG->admin = 'admin';

// Requires .ddev/nginx/moodle-php.conf (PATH_INFO). See .ddev/nginx/moodle-php.conf.
$CFG->slasharguments = 1;

/* ===== Reverse proxy / HTTPS ===== */
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
    $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
    $CFG->sslproxy = true;
}

/* ===== Debug (only for development) ===== */
// Leave debug levels in Site administration; forcing E_ALL here slows every page load.
@ini_set('display_errors', '0');

/* ===== Core setup ===== */
require_once(__DIR__ . '/lib/setup.php');