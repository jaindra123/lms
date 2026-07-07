<?php

unset($CFG);

global $CFG;
$CFG = new stdClass();

/* =========================================================================
 * ENVIRONMENT-AWARE MOODLE CONFIGURATION
 * =========================================================================
 * Supports three environments: dev, staging, production.
 *
 *   - dev        : local / DDEV / LAN — works out of the box (creds below).
 *   - staging    : real DB creds loaded from  config.staging.php    (NOT in git)
 *   - production : real DB creds loaded from  config.production.php  (NOT in git)
 *
 * Environment is chosen in this order:
 *   1. The MOODLE_ENV server variable  (recommended on staging/production)
 *   2. Hostname matching               (fallback)
 *   3. Default = 'dev'
 * ========================================================================= */

$env = getenv('MOODLE_ENV');

if (!$env) {
    $host = $_SERVER['HTTP_HOST'] ?? php_uname('n');

    if (str_contains($host, ':')) {
        [$host] = explode(':', $host, 2);
    }

    // TODO: replace these with your real staging/production hostnames.
    $productionhosts = ['lms.iiidem.in'];
    $staginghosts    = ['staging.iiidem.in'];

    if (in_array($host, $productionhosts, true) || str_contains($host, 'prod')) {
        $env = 'production';
    } else if (in_array($host, $staginghosts, true) || str_contains($host, 'staging') || str_contains($host, 'stage')) {
        $env = 'staging';
    } else {
        $env = 'dev';
    }
}

if (!defined('MOODLE_ENV')) {
    define('MOODLE_ENV', $env);
}

/* -------------------------------------------------------------------------
 * Shared database settings (same on every environment).
 * The host/name/user/pass are set per environment further below.
 * ------------------------------------------------------------------------- */
$CFG->dbtype    = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->prefix    = 'mdl_';

$CFG->dboptions = array(
    'dbpersist'    => 0,
    'dbport'       => '',
    'dbsocket'     => '',
    'dbcollation'  => 'utf8mb4_general_ci',
);

/* -------------------------------------------------------------------------
 * Per-environment settings.
 * ------------------------------------------------------------------------- */
if ($env === 'dev') {
    /* ---------------------------------------------------------------------
     * DEV / DDEV / LOCAL — non-secret defaults, safe to keep in git.
     * --------------------------------------------------------------------- */
    $CFG->dbhost = 'db';
    $CFG->dbname = 'db';
    $CFG->dbuser = 'db';
    $CFG->dbpass = 'db';

    $CFG->wwwroot = 'https://iiidem-certification.ddev.site';

    // Automatically adjust wwwroot for LAN, custom hostname, or public IP access.
    if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
        $hostheader = $_SERVER['HTTP_HOST'];
        $hostonly = $hostheader;

        if (str_contains($hostheader, ':')) {
            [$hostonly] = explode(':', $hostheader, 2);
        }

        $isprivateip = false;

        if (filter_var($hostonly, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // Detect RFC1918 private IPv4 addresses.
            $isprivateip = !filter_var(
                $hostonly,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        // Development hostnames (must be mapped in each developer's hosts file).
        $devhostnames = ['iiidem.local'];
        $isdevhostname = in_array($hostonly, $devhostnames, true);

        // Public IPs used for external development/testing.
        $devpublicips = ['164.100.26.245'];
        $isdevpublicip = in_array($hostonly, $devpublicips, true);

        if ($isprivateip || $isdevhostname || $isdevpublicip) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                ? 'https'
                : 'http';

            $CFG->wwwroot = $scheme . '://' . $hostheader;

            // Keep SERVER_PORT aligned with the forwarded host port to prevent
            // Moodle redirect loops when using DDEV or reverse proxies.
            if (str_contains($hostheader, ':')) {
                [, $port] = explode(':', $hostheader, 2);

                if (is_numeric($port)) {
                    $_SERVER['SERVER_PORT'] = $port;
                }
            } else {
                $_SERVER['SERVER_PORT'] = ($scheme === 'https') ? '443' : '80';
            }

            // Disable secure cookies for non-HTTPS local development.
            $CFG->cookiesecure = false;
        }
    }

} else {
    /* ---------------------------------------------------------------------
     * STAGING / PRODUCTION — real credentials live in an untracked file.
     * Create the file on the server by copying the matching template:
     *   cp config.production.php.example config.production.php
     * then fill in the real dbhost/dbname/dbuser/dbpass/wwwroot/dataroot.
     * --------------------------------------------------------------------- */
    $secretfile = __DIR__ . '/config.' . $env . '.php';

    if (!is_readable($secretfile)) {
        // Fail loudly instead of silently falling back to wrong credentials.
        http_response_code(500);
        die('Missing environment configuration file: config.' . $env . '.php');
    }

    // This file MUST set: $CFG->dbhost, dbname, dbuser, dbpass, wwwroot
    // and MAY set: $CFG->dataroot.
    require($secretfile);
}

/* -------------------------------------------------------------------------
 * Moodle data directory.
 * Staging/production may override $CFG->dataroot in their secret file above.
 * ------------------------------------------------------------------------- */
if (empty($CFG->dataroot)) {
    if (is_dir('/var/moodledata') && is_writable('/var/moodledata')) {
        $CFG->dataroot = '/var/moodledata';
    } else if (is_dir(__DIR__ . '/moodledata')) {
        $CFG->dataroot = __DIR__ . '/moodledata';
    } else {
        $CFG->dataroot = '/var/www/html/moodledata';
    }
}

/* -------------------------------------------------------------------------
 * Moodle administration directory.
 * ------------------------------------------------------------------------- */
$CFG->admin = 'admin';

/*
 * Enable slash arguments for improved file serving.
 * Requires PATH_INFO support in the web server configuration.
 */
$CFG->slasharguments = 1;

/* -------------------------------------------------------------------------
 * Reverse Proxy / HTTPS Detection.
 * Trust the X-Forwarded-Proto header when running behind a reverse proxy
 * or load balancer that terminates SSL.
 * ------------------------------------------------------------------------- */
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
    $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $_SERVER['HTTPS'] = 'on';
    $CFG->sslproxy = true;
}

/* -------------------------------------------------------------------------
 * Debugging — verbose on dev/staging, silent on production.
 * ------------------------------------------------------------------------- */
if ($env === 'production') {
    $CFG->debug = 0;
    $CFG->debugdisplay = 0;
    @ini_set('display_errors', '0');
    $CFG->cachejs = true;
    $CFG->themedesignermode = false;
} else {
    @error_reporting(E_ALL | E_STRICT);
    @ini_set('display_errors', '1');
    $CFG->debug = (E_ALL | E_STRICT);
    $CFG->debugdisplay = 1;
    $CFG->themedesignermode = ($env === 'dev');
}

/* -------------------------------------------------------------------------
 * Moodle Bootstrap.
 * ------------------------------------------------------------------------- */
require_once(__DIR__ . '/lib/setup.php');
