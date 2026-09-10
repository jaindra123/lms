<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace theme_iiidem2\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Strip .php from outgoing Moodle URLs (extensionless clean URLs).
 *
 * Incoming requests are mapped back to *.php by nginx/Apache.
 * Slash-argument URLs (pluginfile.php/…, javascript.php/…) are left unchanged.
 *
 * Do NOT mutate $PAGE->url here — MFA and other plugins compare raw paths to
 * *.php scripts (URL_MATCH_BASE); rewriting the page URL causes redirect loops.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class url_rewriter implements \core\output\url_rewriter {

    /**
     * Scripts that must keep .php in generated links (slash-arg / assets).
     *
     * @return array<string,bool>
     */
    private static function keep_php_basenames(): array {
        return [
            'pluginfile.php' => true,
            'tokenpluginfile.php' => true,
            'draftfile.php' => true,
            'file.php' => true,
            'javascript.php' => true,
            'requirejs.php' => true,
            'jslib.php' => true,
            'yui_combo.php' => true,
            'image.php' => true,
            'styles.php' => true,
            'jquery.php' => true,
            'html2text_test.php' => true,
            'r.php' => true,
        ];
    }

    /**
     * Rewrite a moodle_url to drop a trailing .php when safe.
     *
     * @param \moodle_url $url
     * @return \moodle_url
     */
    public static function url_rewrite(\moodle_url $url) {
        global $CFG;

        if ($url->get_slashargument() !== '') {
            return $url;
        }

        $path = $url->get_path(false);
        if ($path === '' || !str_ends_with($path, '.php')) {
            return $url;
        }

        // tool_mfa compares $ME / PAGE URL to auth.php — keep .php or redirect loops.
        if ($path === '/admin/tool/mfa/auth.php'
                || str_ends_with($path, '/admin/tool/mfa/auth.php')) {
            return $url;
        }

        // CSRF / AJAX endpoints: keep .php so POSTs never hit a dir rewrite or 404.
        if ($path === '/editmode.php'
                || $path === '/lib/ajax/service.php'
                || $path === '/lib/ajax/service-nologin.php'
                || str_ends_with($path, '/lib/ajax/service.php')
                || str_ends_with($path, '/lib/ajax/service-nologin.php')) {
            return $url;
        }

        $basename = basename($path);
        if (isset(self::keep_php_basenames()[$basename])) {
            return $url;
        }

        $cleanpath = substr($path, 0, -4);
        if ($cleanpath === '' || $cleanpath === '/') {
            return $url;
        }

        // /login/index.php → /login (before is_dir keep-.php; login/ is a real dir).
        if ($path === '/login/index.php' || $cleanpath === '/login/index') {
            $cleanpath = '/login';
        } else if (preg_match('#^(.*)/index$#', $cleanpath, $m)) {
            $dir = ($m[1] === '') ? '/' : $m[1];
            if (!empty($CFG->dirroot) && is_dir($CFG->dirroot . $dir)) {
                $cleanpath = ($dir === '/') ? '/' : $dir . '/';
            }
        } else if (!empty($CFG->dirroot)) {
            // Moodle often has both script.php and a same-named directory
            // (e.g. admin/settings.php + admin/settings/). Keep .php to avoid 403.
            $dirpath = $CFG->dirroot . $cleanpath;
            if (is_dir($dirpath)) {
                return $url;
            }
        }

        $anchor = null;
        $raw = $url->raw_out(false);
        $hashpos = strpos($raw, '#');
        if ($hashpos !== false) {
            $anchor = substr($raw, $hashpos + 1);
        }

        return new \moodle_url($cleanpath, $url->params(), $anchor);
    }

    /**
     * When the browser still shows a legacy *.php address, push the clean URL
     * into history. Does not change $PAGE->url (breaks MFA URL compares).
     *
     * @return string HTML snippet for &lt;head&gt;
     */
    public static function html_head_setup() {
        global $PAGE;

        if (empty($PAGE) || empty($PAGE->url)) {
            return '';
        }

        $clean = self::url_rewrite($PAGE->url);
        $orig = $PAGE->url->raw_out(false);
        $cleanout = $clean->raw_out(false);

        if ($orig === $cleanout) {
            return '';
        }

        $cleanjs = json_encode($clean->out(false), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $canonical = s($clean->out(true));

        return '<link rel="canonical" href="' . $canonical . '" />' . "\n"
            . '<script>history.replaceState&&history.replaceState({},"",' . $cleanjs . ');</script>' . "\n";
    }
}
