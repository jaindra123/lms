<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
/**
 * CDAC directory-listing probes on pluginfile slasharguments.
 *
 * Auditors hit URLs like:
 *   /pluginfile.php/1/core_admin/logo/360x104/{rev}/
 * which look like directories. Moodle never serves a file index there — but a
 * themed exception page is still flagged. Respond with plain 403 Forbidden.
 *
 * Include from pluginfile entry points BEFORE config.php (no Moodle bootstrap).
 *
 * @package    core
 * @copyright  2026 IIIDEM
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Exit with plain 403 if this request is a directory-style pluginfile path.
 *
 * @return void
 */
function iiidem_reject_pluginfile_directory_listing(): void {
    if (PHP_SAPI === 'cli') {
        return;
    }

    $pathinfo = isset($_SERVER['PATH_INFO']) ? (string) $_SERVER['PATH_INFO'] : '';
    $requestpath = '';
    if (!empty($_SERVER['REQUEST_URI'])) {
        $parsed = parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $requestpath = is_string($parsed) ? $parsed : '';
    }

    $isdirectory = false;
    // Raw PATH_INFO still has the trailing slash (Moodle PARAM_PATH strips it later).
    if ($pathinfo !== '' && substr($pathinfo, -1) === '/') {
        $isdirectory = true;
    } else if ($requestpath !== '' &&
            preg_match('#/(?:webservice/)?(?:token)?pluginfile\.php/.+/$#', $requestpath)) {
        $isdirectory = true;
    }

    if (!$isdirectory) {
        return;
    }

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    if (!headers_sent()) {
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        if (!is_string($protocol) || !preg_match('#^HTTP/\d#', $protocol)) {
            $protocol = 'HTTP/1.1';
        }
        header($protocol . ' 403 Forbidden');
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }

    echo 'Forbidden';
    exit(0);
}

/**
 * Plain 403 when a pluginfile callback has no filename (directory / incomplete path).
 *
 * @return never
 */
function iiidem_send_pluginfile_directory_forbidden(): void {
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    if (!headers_sent()) {
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        if (!is_string($protocol) || !preg_match('#^HTTP/\d#', $protocol)) {
            $protocol = 'HTTP/1.1';
        }
        header($protocol . ' 403 Forbidden');
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }
    echo 'Forbidden';
    exit(0);
}
