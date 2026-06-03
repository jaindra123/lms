<?php
// CLI: Remove orphaned logo metadata and install placeholder images.
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/adminlib.php');

$fs = get_file_storage();
$context = context_system::instance();
$pixdir = $CFG->dirroot . '/theme/iiidem2/pix';

/**
 * Create a simple PNG placeholder.
 */
function theme_iiidem2_make_placeholder_png(string $path, string $text, int $w, int $h, array $bg, array $fg): void {
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false);
    imagesavealpha($im, true);
    $background = imagecolorallocatealpha($im, $bg[0], $bg[1], $bg[2], $bg[3] ?? 0);
    imagefilledrectangle($im, 0, 0, $w, $h, $background);
    $color = imagecolorallocate($im, $fg[0], $fg[1], $fg[2]);
    $font = 5;
    $tw = imagefontwidth($font) * strlen($text);
    $th = imagefontheight($font);
    imagestring($im, $font, (int)(($w - $tw) / 2), (int)(($h - $th) / 2), $text, $color);
    imagepng($im, $path);
    imagedestroy($im);
}

/**
 * Store a file in a component file area (replacing existing files).
 */
function theme_iiidem2_install_area_file(string $component, string $filearea, string $sourcepath, string $targetname): void {
    global $fs, $context;

    $fs->delete_area_files($context->id, $component, $filearea, 0);
    $filerecord = [
        'contextid' => $context->id,
        'component' => $component,
        'filearea' => $filearea,
        'itemid' => 0,
        'filepath' => '/',
        'filename' => $targetname,
    ];
    $fs->create_file_from_pathname($filerecord, $sourcepath);

    if (strpos($component, 'theme_') === 0) {
        $plugin = $component;
        set_config($filearea, '/' . $targetname, $plugin);
    }
}

/**
 * Delete file records whose binary is missing from moodledata (logo-related areas only).
 */
function theme_iiidem2_purge_missing_files(): int {
    global $DB, $fs;

    $removed = 0;
    $files = $DB->get_records_select(
        'files',
        "filename != '.' AND filesize > 0 AND (
            (component = 'core_admin' AND filearea IN ('logo', 'logocompact'))
            OR (component = 'theme_iiidem2' AND filearea IN ('headerlogo', 'footerlogo'))
            OR (component = 'theme_iiidem' AND filearea = 'logo')
            OR (component = 'user' AND filearea = 'draft' AND filename IN ('iiidem-vLogo.png', 'iiidem_about_logo.PNG', 'iiidem_new_footer_logo.PNG'))
        )"
    );
    foreach ($files as $record) {
        $file = $fs->get_file(
            $record->contextid,
            $record->component,
            $record->filearea,
            $record->itemid,
            $record->filepath,
            $record->filename
        );
        if (!$file) {
            continue;
        }
        try {
            $file->get_content();
        } catch (Exception $e) {
            $file->delete();
            $removed++;
            cli_writeln("Removed orphan metadata: {$record->component}/{$record->filearea}/{$record->filename}");
        }
    }
    return $removed;
}

if (!is_dir($pixdir)) {
    mkdir($pixdir, 0775, true);
}

$headerpath = $pixdir . '/iiidem-header-logo.png';
$footerpath = $pixdir . '/iiidem-white-logo-footer.png';
$existinglogo = $pixdir . '/iiidem-logo.png';

if (is_readable($existinglogo) && filesize($existinglogo) > 1000) {
    copy($existinglogo, $headerpath);
    copy($existinglogo, $footerpath);
    cli_writeln('Using theme/iiidem2/pix/iiidem-logo.png for site logos.');
} else {
    theme_iiidem2_make_placeholder_png($headerpath, 'IIIDEM', 280, 80, [11, 61, 145, 0], [255, 255, 255]);
    theme_iiidem2_make_placeholder_png($footerpath, 'IIIDEM', 280, 80, [7, 48, 89, 0], [255, 255, 255]);
    cli_writeln('Created placeholder images in theme/iiidem2/pix/');
}

$removed = theme_iiidem2_purge_missing_files();
cli_writeln("Removed {$removed} orphaned file record(s).");

theme_iiidem2_install_area_file('core_admin', 'logo', $headerpath, 'iiidem_about_logo.PNG');
theme_iiidem2_install_area_file('core_admin', 'logocompact', $footerpath, 'iiidem_new_footer_logo.PNG');
set_config('logo', '/iiidem_about_logo.PNG', 'core_admin');
set_config('logocompact', '/iiidem_new_footer_logo.PNG', 'core_admin');

theme_iiidem2_install_area_file('theme_iiidem2', 'headerlogo', $headerpath, 'iiidem-header-logo.png');
theme_iiidem2_install_area_file('theme_iiidem2', 'footerlogo', $footerpath, 'iiidem-white-logo-footer.png');

// Old theme component may still have broken metadata.
$fs->delete_area_files($context->id, 'theme_iiidem', 'logo', 0);

purge_all_caches();
cli_writeln('Done. Replace placeholders via Site administration → Appearance → Logos and Theme IIIDEM2 settings.');
