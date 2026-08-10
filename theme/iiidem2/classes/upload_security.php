<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace theme_iiidem2;

defined('MOODLE_INTERNAL') || die();

/**
 * Server-side upload hardening: whitelist extensions + MIME checks.
 *
 * @package   theme_iiidem2
 * @copyright 2026 IIIDEM
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_security {

    /** @var string[] Non-executable document / image types for course materials. */
    public const MATERIAL_EXTENSIONS = [
        '.pdf', '.doc', '.docx', '.ppt', '.pptx', '.xls', '.xlsx',
        '.odt', '.ods', '.odp', '.txt', '.rtf',
        '.png', '.jpg', '.jpeg', '.gif', '.webp',
    ];

    /** @var string[] Student assignment submission types. */
    public const ASSIGNMENT_EXTENSIONS = [
        '.pdf', '.doc', '.docx', '.ppt', '.pptx', '.xls', '.xlsx',
        '.odt', '.ods', '.odp', '.txt', '.rtf', '.zip',
        '.png', '.jpg', '.jpeg', '.gif', '.webp',
    ];

    /** @var string[] Private files: documents/images only (no zip/archives — auditor CWE-434). */
    public const PRIVATE_FILES_EXTENSIONS = [
        '.pdf', '.doc', '.docx', '.ppt', '.pptx', '.xls', '.xlsx',
        '.odt', '.ods', '.odp', '.txt', '.rtf',
        '.png', '.jpg', '.jpeg', '.gif', '.webp',
    ];

    /** @var string[] Theme / branding images only (no SVG — XSS risk). */
    public const IMAGE_EXTENSIONS = [
        '.png', '.jpg', '.jpeg', '.gif', '.webp',
    ];

    /** Max stored filename length (NTFS / portable path safety). */
    public const MAX_FILENAME_LENGTH = 200;

    /** @var string[] Always rejected (executable / script / markup). */
    public const BLOCKED_EXTENSIONS = [
        '.php', '.phtml', '.phar', '.php3', '.php4', '.php5', '.php7', '.phps',
        '.exe', '.bat', '.cmd', '.com', '.msi', '.scr', '.dll',
        '.js', '.mjs', '.html', '.htm', '.shtml', '.xhtml', '.svg', '.svgz',
        '.htaccess', '.sh', '.bash', '.cgi', '.pl', '.py', '.rb', '.asp', '.aspx',
        '.jsp', '.war', '.jar', '.class', '.wasm',
    ];

    /**
     * Map of extension => allowed MIME prefixes/types.
     *
     * @return array<string, string[]>
     */
    public static function material_mime_map(): array {
        return [
            '.pdf' => ['application/pdf'],
            '.doc' => ['application/msword', 'application/octet-stream'],
            '.docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'application/octet-stream',
            ],
            '.ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
            '.pptx' => [
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/zip',
                'application/octet-stream',
            ],
            '.xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
            '.xlsx' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/octet-stream',
            ],
            '.odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
            '.ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
            '.odp' => ['application/vnd.oasis.opendocument.presentation', 'application/zip'],
            '.txt' => ['text/plain'],
            '.rtf' => ['application/rtf', 'text/rtf'],
            '.png' => ['image/png'],
            '.jpg' => ['image/jpeg'],
            '.jpeg' => ['image/jpeg'],
            '.gif' => ['image/gif'],
            '.webp' => ['image/webp'],
            '.zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        ];
    }

    /**
     * Normalize filename extension (leading dot, lowercase).
     */
    public static function extension_of(string $filename): string {
        $filename = clean_filename($filename);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return $ext === '' ? '' : '.' . $ext;
    }

    /**
     * Whether extension is on the hard blocklist.
     */
    public static function is_blocked_extension(string $ext): bool {
        return in_array(strtolower($ext), self::BLOCKED_EXTENSIONS, true);
    }

    /**
     * Detect double-extension tricks (e.g. shell.php.jpg, info1.PHP.).
     */
    public static function filename_has_blocked_token(string $filename): bool {
        $lower = strtolower(clean_filename($filename));
        foreach (self::BLOCKED_EXTENSIONS as $blocked) {
            $token = ltrim($blocked, '.');
            if ($token === '') {
                continue;
            }
            // Ends with blocked ext, or blocked ext appears before another suffix.
            if (preg_match('/\.' . preg_quote($token, '/') . '(\.|$)/', $lower)) {
                return true;
            }
        }
        return false;
    }

    /** Bytes to scan for polyglot / embedded script checks. */
    public const CONTENT_SCAN_BYTES = 65536;

    /**
     * Whether raw bytes look like PHP/HTML script.
     */
    public static function content_looks_executable(string $head): bool {
        if ($head === '') {
            return false;
        }
        return (bool) preg_match('/<\?php|<\?=|<script[\s>]/i', $head);
    }

    /**
     * PDF OpenAction / embedded JavaScript (browser XSS when opened in PDF viewers).
     */
    public static function content_has_pdf_javascript(string $head): bool {
        if ($head === '') {
            return false;
        }
        return (bool) preg_match('/\/(?:JavaScript|JS)\b/i', $head);
    }

    /**
     * Read a content sample from path or string for inspection.
     */
    public static function read_content_sample(?string $filepath, ?string $filecontent, int $maxlen = 0): string {
        $maxlen = $maxlen > 0 ? $maxlen : self::CONTENT_SCAN_BYTES;
        if ($filepath !== null && $filepath !== '' && is_readable($filepath)) {
            $raw = @file_get_contents($filepath, false, null, 0, $maxlen);
            return is_string($raw) ? $raw : '';
        }
        if ($filecontent !== null) {
            return substr($filecontent, 0, $maxlen);
        }
        return '';
    }

    /**
     * Whether this filerecord is a user private/draft upload (audit surface).
     */
    public static function is_user_upload_area(?\stdClass $filerecord): bool {
        if ($filerecord === null) {
            return false;
        }
        $component = (string) ($filerecord->component ?? '');
        $filearea = (string) ($filerecord->filearea ?? '');
        return $component === 'user' && in_array($filearea, ['draft', 'private'], true);
    }

    /**
     * Reject dangerous uploads at file-storage creation time (covers repository AJAX).
     *
     * @param \stdClass|null $filerecord
     * @param string|null $filepath Temp path on disk
     * @param string|null $filecontent In-memory content
     * @throws \moodle_exception
     */
    public static function assert_safe_file_create(
        ?\stdClass $filerecord,
        ?string $filepath = null,
        ?string $filecontent = null
    ): void {
        $filename = '';
        if ($filerecord !== null && !empty($filerecord->filename)) {
            $filename = (string) $filerecord->filename;
        }
        // Directory placeholders have filename '.' — skip.
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return;
        }

        if (\core_text::strlen($filename) > self::MAX_FILENAME_LENGTH) {
            throw new \moodle_exception('uploaderror', 'moodle');
        }

        // Reject path separators / control junk that survived client naming.
        if (preg_match('/[\x00-\x1f\\\\\/:*?"<>|%\$;]/', $filename)) {
            throw new \moodle_exception('uploaderror', 'moodle');
        }

        $ext = self::extension_of($filename);
        if (self::is_blocked_extension($ext) || self::filename_has_blocked_token($filename)) {
            throw new \moodle_exception('uploaderror', 'moodle');
        }

        $sample = self::read_content_sample($filepath, $filecontent);

        // Polyglot: "content.pdf" that is actually PHP (or HTML/JS).
        if (self::content_looks_executable($sample)) {
            throw new \moodle_exception('uploaderror', 'moodle');
        }

        // PDF with embedded viewer JS (report Step 3 style payloads).
        if ($ext === '.pdf' && self::content_has_pdf_javascript($sample)) {
            throw new \moodle_exception('uploaderror', 'moodle');
        }
    }

    /**
     * Comma-separated list for assignsubmission_file_filetypes.
     */
    public static function assignment_filetypes_string(): string {
        return implode(',', self::ASSIGNMENT_EXTENSIONS);
    }

    /**
     * Validate a stored_file against an allowed extension whitelist (+ MIME).
     *
     * @param \stored_file $file
     * @param string[] $allowedexts
     * @return string Empty if OK, otherwise error detail
     */
    public static function validate_stored_file(\stored_file $file, array $allowedexts): string {
        $filename = $file->get_filename();
        if ($filename === '.' || $filename === '..') {
            return 'invalid';
        }

        $ext = self::extension_of($filename);
        if ($ext === '' || self::is_blocked_extension($ext) || self::filename_has_blocked_token($filename)) {
            return $ext !== '' ? $ext : 'unknown';
        }
        if (!in_array($ext, $allowedexts, true)) {
            return $ext;
        }

        $mime = strtolower((string) $file->get_mimetype());
        $map = self::material_mime_map();
        if (isset($map[$ext]) && $mime !== '' && $mime !== 'document/unknown') {
            $ok = false;
            foreach ($map[$ext] as $allowed) {
                if ($mime === $allowed || str_starts_with($mime, rtrim($allowed, '*'))) {
                    $ok = true;
                    break;
                }
            }
            // Soft fail for odd but non-executable types already extension-whitelisted.
            if (!$ok && (str_starts_with($mime, 'application/') || str_starts_with($mime, 'image/')
                    || str_starts_with($mime, 'text/'))) {
                // Still reject if MIME clearly indicates HTML/JS/PHP.
                if (str_contains($mime, 'html') || str_contains($mime, 'javascript')
                        || str_contains($mime, 'php') || str_contains($mime, 'svg')) {
                    return $ext . ' (' . $mime . ')';
                }
            } else if (!$ok) {
                return $ext . ' (' . $mime . ')';
            }
        }

        // Peek at content for PHP/HTML / PDF-JS in "documents".
        $content = $file->get_content();
        if ($content !== false && $content !== '') {
            $sample = substr($content, 0, self::CONTENT_SCAN_BYTES);
            if (self::content_looks_executable($sample)) {
                return $ext . ' (executable content)';
            }
            if ($ext === '.pdf' && self::content_has_pdf_javascript($sample)) {
                return $ext . ' (pdf javascript)';
            }
        }

        return '';
    }

    /**
     * Validate all files in a user draft area.
     *
     * @param int $userid
     * @param int $draftitemid
     * @param string[] $allowedexts
     * @return string Empty if OK, otherwise rejected extension/detail
     */
    public static function validate_user_draft(int $userid, int $draftitemid, array $allowedexts): string {
        if ($userid <= 0 || $draftitemid <= 0) {
            return 'invalid';
        }

        $usercontext = \context_user::instance($userid);
        $fs = get_file_storage();
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
        if (empty($files)) {
            return 'empty';
        }

        foreach ($files as $file) {
            $err = self::validate_stored_file($file, $allowedexts);
            if ($err !== '') {
                return $err;
            }
        }

        return '';
    }
}
