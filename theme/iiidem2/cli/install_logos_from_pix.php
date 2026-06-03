<?php
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/filelib.php');

$pixdir = $CFG->dirroot . '/theme/iiidem2/pix';
$fs = get_file_storage();
$context = context_system::instance();

$map = [
    ['core_admin', 'logo', 'iiidem_about_logo.PNG', $pixdir . '/iiidem_about_logo.PNG'],
    ['core_admin', 'logocompact', 'iiidem_new_footer_logo.PNG', $pixdir . '/iiidem_new_footer_logo.PNG'],
    ['theme_iiidem2', 'headerlogo', 'iiidem_about_logo.PNG', $pixdir . '/iiidem_about_logo.PNG'],
    ['theme_iiidem2', 'footerlogo', 'iiidem-white-logo-footer.png', $pixdir . '/iiidem-white-logo-footer.png'],
];

foreach ($map as [$component, $filearea, $filename, $source]) {
    if (!is_readable($source)) {
        cli_writeln("Skip missing: $source");
        continue;
    }
    $fs->delete_area_files($context->id, $component, $filearea, 0);
    $fs->create_file_from_pathname([
        'contextid' => $context->id,
        'component' => $component,
        'filearea' => $filearea,
        'itemid' => 0,
        'filepath' => '/',
        'filename' => $filename,
    ], $source);
    if (strpos($component, 'theme_') === 0) {
        set_config($filearea, '/' . $filename, $component);
    } else {
        set_config($filearea === 'logo' ? 'logo' : 'logocompact', '/' . $filename, $component);
    }
    cli_writeln("Installed $component/$filearea/$filename");
}

set_config('logo', '/iiidem_about_logo.PNG', 'core_admin');
set_config('logocompact', '/iiidem_new_footer_logo.PNG', 'core_admin');
purge_all_caches();
cli_writeln('Done.');
