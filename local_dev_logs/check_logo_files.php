<?php
define('CLI_SCRIPT', true);
require __DIR__ . '/../config.php';

$fs = get_file_storage();
$ctx = context_system::instance();

foreach (['core_admin' => ['logo', 'logocompact'], 'theme_iiidem2' => ['headerlogo', 'footerlogo']] as $comp => $areas) {
    foreach ($areas as $area) {
        $files = $fs->get_area_files($ctx->id, $comp, $area, 0, '', false);
        echo $comp . '/' . $area . ': ' . count($files) . ' files, config=' . get_config($comp, $area) . PHP_EOL;
        foreach ($files as $file) {
            echo '  ' . $file->get_filename() . PHP_EOL;
        }
    }
}
