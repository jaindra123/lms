<?php
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';

$fs = get_file_storage();
$ctx = context_system::instance();

foreach (['core_admin' => ['logo', 'logocompact'], 'theme_iiidem2' => ['headerlogo', 'footerlogo']] as $comp => $areas) {
    foreach ($areas as $area) {
        foreach ($fs->get_area_files($ctx->id, $comp, $area, 0, '', false) as $file) {
            if ($file->is_directory()) {
                continue;
            }
            try {
                $size = $file->get_filesize();
                $read = strlen($file->get_content());
                echo "$comp/$area/{$file->get_filename()}: OK ({$read} bytes)\n";
            } catch (Exception $e) {
                echo "$comp/$area/{$file->get_filename()}: FAIL - {$e->getMessage()}\n";
            }
        }
    }
}
