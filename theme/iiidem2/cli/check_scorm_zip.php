<?php
// CLI: Inspect a zip for SCORM imsmanifest.xml (usage: php check_scorm_zip.php /path/to/file.zip).
define('CLI_SCRIPT', true);
require __DIR__ . '/../../../config.php';
require_once($CFG->dirroot . '/mod/scorm/lib.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(['help' => false], ['h' => 'help']);

if ($options['help'] || empty($unrecognized)) {
    echo "Usage: php check_scorm_zip.php /full/path/to/package.zip\n";
    exit(empty($unrecognized) ? 1 : 0);
}

$path = array_shift($unrecognized);
if (!is_readable($path)) {
    cli_error("File not readable: $path");
}

$fs = get_file_storage();
$ctx = context_system::instance();
$fs->delete_area_files($ctx->id, 'mod_scorm', 'ziptest', 0);
$file = $fs->create_file_from_pathname([
    'contextid' => $ctx->id,
    'component' => 'mod_scorm',
    'filearea' => 'ziptest',
    'itemid' => 0,
    'filepath' => '/',
    'filename' => basename($path),
], $path);

$packer = get_file_packer('application/zip');
$list = $file->list_files($packer);
if (!is_array($list)) {
    cli_error('Cannot read zip archive.');
}

$manifests = [];
foreach ($list as $info) {
    if (stripos($info->pathname, 'imsmanifest') !== false || preg_match('/\.cst$/i', $info->pathname)) {
        $manifests[] = $info->pathname;
    }
}

cli_writeln('Zip: ' . basename($path) . ' (' . count($list) . ' entries)');
if ($manifests) {
    cli_writeln('Manifest/AICC files found:');
    foreach ($manifests as $p) {
        cli_writeln('  - ' . $p . ($p === 'imsmanifest.xml' ? ' (OK for Moodle)' : ''));
    }
} else {
    cli_writeln('No imsmanifest.xml or AICC .cst file in zip.');
    cli_writeln('First 20 entries:');
    foreach (array_slice($list, 0, 20) as $info) {
        cli_writeln('  - ' . $info->pathname);
    }
}

$errors = scorm_validate_package($file);
if (empty($errors)) {
    cli_writeln('Moodle validation: PASS');
} else {
    cli_writeln('Moodle validation: FAIL - ' . reset($errors));
}

$fs->delete_area_files($ctx->id, 'mod_scorm', 'ziptest', 0);
