<?php

// Direct HTTP to /config or /config.php must not boot the LMS (blank 200 / leak).
// Includes from index.php and CLI still work — SCRIPT_FILENAME is the entry script.
if (PHP_SAPI !== 'cli'
        && !empty($_SERVER['SCRIPT_FILENAME'])
        && @realpath($_SERVER['SCRIPT_FILENAME']) === @realpath(__FILE__)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit;
}

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
 *   3. Default = 'dev' (local / DDEV only)
 * ========================================================================= */

$productionhosts = ['lms.iiidem.in', 'lms.eci.gov.in'];
$staginghosts    = ['staging.iiidem.in', 'staginglms.eci.gov.in'];
$devhosts        = [
    'iiidem-certification.ddev.site',
    '127.0.0.1',
    'localhost',
    'iiidem.local',
    '164.100.26.245',
];

/**
 * CWE-644: reject Host: vulnerable.com (and any name not on the allowlist)
 * before env detection or Moodle bootstrap — never 302 to that Host.
 */
if (PHP_SAPI !== 'cli') {
    $rawhost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if (str_contains($rawhost, ':')) {
        [$rawhost] = explode(':', $rawhost, 2);
    }
    $allowedhosts = array_merge($productionhosts, $staginghosts, $devhosts);
    $hostok = $rawhost !== '' && in_array($rawhost, $allowedhosts, true);
    if (!$hostok && filter_var($rawhost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $hostok = !filter_var(
            $rawhost,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
    if (!$hostok) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        header('Connection: close');
        echo 'Bad Request';
        exit;
    }
}

$env = getenv('MOODLE_ENV');

if (!$env) {
    $host = $_SERVER['HTTP_HOST'] ?? php_uname('n');

    if (str_contains($host, ':')) {
        [$host] = explode(':', $host, 2);
    }

    if (in_array($host, $productionhosts, true) || str_contains($host, 'prod')) {
        $env = 'production';
    } else if (in_array($host, $staginghosts, true) || str_contains($host, 'staging') || str_contains($host, 'stage')) {
        $env = 'staging';
    } else {
        $isddev = (getenv('DDEV_PROJECT') || getenv('IS_DDEV_PROJECT'));
        if (!$isddev && is_readable(__DIR__ . '/config.staging.php')) {
            $env = 'staging';
        } else if (!$isddev && is_readable(__DIR__ . '/config.production.php')) {
            $env = 'production';
        } else {
            $env = 'dev';
        }
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
 * Debugging — verbose on local dev only. Staging/production must not
 * put SQL, stacks, or HTTP 500 crash shells in the browser (CWE-209).
 * ------------------------------------------------------------------------- */
if ($env === 'dev') {
    @error_reporting(E_ALL | E_STRICT);
    @ini_set('display_errors', '1');
    $CFG->debug = (E_ALL | E_STRICT);
    $CFG->debugdisplay = 1;
    // Theme designer mode recompiles SCSS on every request and makes admin
    // pages very slow. Enable only when explicitly developing theme CSS:
    //   set MOODLE_THEME_DESIGNER=1 in the environment.
    $CFG->themedesignermode = !empty(getenv('MOODLE_THEME_DESIGNER'));
    $CFG->cachejs = true;

    // DDEV / local: allow Moodle cURL to call its own wwwroot (mobile app
    // settings HEAD-check). Default private-IP blocklist breaks that and
    // floods admin pages with "URL is blocked" debugging.
    $CFG->curlsecurityblockedhosts = '';
} else {
    $CFG->debug = 0;
    $CFG->debugdisplay = 0;
    $CFG->debugdeveloper = false;
    @ini_set('display_errors', '0');
    $CFG->cachejs = true;
    $CFG->themedesignermode = false;
    $CFG->yuicomboloading = false;
}

/* -------------------------------------------------------------------------
 * Bare /theme/yui_combo.php probe (CWE-209). ABORT_AFTER_CONFIG skips theme
 * hooks, so this must run from config.php. Valid ?rollup/… URLs are untouched.
 * ------------------------------------------------------------------------- */
if (PHP_SAPI !== 'cli') {
    $yuiscript = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (str_ends_with($yuiscript, '/yui_combo.php')) {
        $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $pathinfo = (string) ($_SERVER['PATH_INFO'] ?? '');
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $hasparts = ($qs !== '' && $qs !== '/')
            || ($pathinfo !== '' && $pathinfo !== '/')
            || str_contains($uri, '/yui_combo.php/');
        if (!$hasparts) {
            if (!headers_sent()) {
                header('HTTP/1.0 404 Not Found');
                header('Content-Type: text/plain; charset=utf-8');
                header('Cache-Control: no-store');
            }
            echo 'Combo resource not found, sorry.';
            exit;
        }
    }
}

/* -------------------------------------------------------------------------
 * Account lockout (CDAC brute force / missing lockout on /login/index.php).
 * Forced here so Site administration cannot set threshold back to 0.
 * 5 failed passwords in 30 minutes → temporary lock for 30 minutes.
 * ------------------------------------------------------------------------- */
$CFG->lockoutthreshold = 5;
$CFG->lockoutwindow = 30 * 60;
$CFG->lockoutduration = 30 * 60;
$CFG->displayloginfailures = 1;

/* -------------------------------------------------------------------------
 * Password policy (CDAC weak password on /register/). Forced so admins cannot
 * disable complexity. Matches the on-screen requirements.
 * ------------------------------------------------------------------------- */
$CFG->passwordpolicy = 1;
$CFG->minpasswordlength = 8;
$CFG->minpassworddigits = 1;
$CFG->minpasswordlower = 1;
$CFG->minpasswordupper = 1;
$CFG->minpasswordnonalphanum = 1;
$CFG->maxconsecutiveidentchars = 3;
$CFG->passwordpolicycheckonlogin = 1;
$CFG->passwordreuselimit = 5;

/* -------------------------------------------------------------------------
 * Cookie attributes (CDAC insecure cookie / Path+Domain+Secure+HttpOnly).
 * MoodleSession must be site-wide: Path is the wwwroot path ("/" at site root).
 * Domain is the wwwroot host (explicit Domain=; not a parent like .eci.gov.in).
 * SameSite=Lax (not Strict) so SSO / payment returns still send the session.
 * ------------------------------------------------------------------------- */
$CFG->cookiehttponly = true;
@ini_set('session.cookie_httponly', '1');
@ini_set('session.cookie_samesite', 'Lax');

$wwwscheme = '';
$wwwhost = '';
$wwwpath = '/';
if (!empty($CFG->wwwroot)) {
    $wwwparts = parse_url($CFG->wwwroot);
    $wwwscheme = strtolower((string) ($wwwparts['scheme'] ?? ''));
    $wwwhost = strtolower((string) ($wwwparts['host'] ?? ''));
    $rawpath = (string) ($wwwparts['path'] ?? '');
    if ($rawpath !== '' && $rawpath !== '/') {
        $wwwpath = rtrim($rawpath, '/') . '/';
    }
}

if ($wwwscheme === 'https') {
    $CFG->cookiesecure = true;
    @ini_set('session.cookie_secure', '1');
}

$CFG->sessioncookiepath = $wwwpath;
$CFG->cookiesamesite = 'Lax';

// Explicit Domain=host on staging/production so Set-Cookie includes Domain.
// Never set Domain on DDEV / localhost / IPs — ddev.site is a public suffix,
// browsers reject Domain=*.ddev.site, the session cookie is dropped, and
// login 302s until ERR_TOO_MANY_REDIRECTS (MFA OTP never loads).
$skipcookiedomain = ($wwwhost === '' || $wwwhost === 'localhost'
    || str_ends_with($wwwhost, '.ddev.site')
    || str_ends_with($wwwhost, '.localhost')
    || str_ends_with($wwwhost, '.local')
    || filter_var($wwwhost, FILTER_VALIDATE_IP) !== false);
if (!$skipcookiedomain) {
    $CFG->sessioncookiedomain = $wwwhost;
    @ini_set('session.cookie_domain', $wwwhost);
} else {
    $CFG->sessioncookiedomain = '';
    @ini_set('session.cookie_domain', '');
}

/* -------------------------------------------------------------------------
 * Moodle Bootstrap.
 * ------------------------------------------------------------------------- */
@ini_set('expose_php', '0');
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header_remove('X-Powered-By');
    header_remove('Server');
}
require_once(__DIR__ . '/lib/setup.php');
