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