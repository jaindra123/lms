<?php
// This file is part of Moodle - http://moodle.org/
//
// CLI: disable Moodle Mobile QR login (unused endpoint hardening).
//
// Usage (from Moodle root):
//   php theme/iiidem2/cli/disable_qr_login.php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params([
    'help' => false,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if (!empty($options['help'])) {
    echo "Disable Moodle Mobile QR login (qrcodetype=Disabled).

Removes tool_mobile/qrlogin keys and detaches tool_mobile_get_tokens_for_qr_login
from external services. Also forced via \$CFG->forced_plugin_settings in config.php.

Options:
  -h, --help   Show this help
";
    exit(0);
}

\theme_iiidem2\qr_login_security::disable();

cli_writeln('QR login disabled (tool_mobile/qrcodetype = 0).');
cli_writeln('Deleted tool_mobile/qrlogin user keys.');
cli_writeln('Detached tool_mobile_get_tokens_for_qr_login from services.');
exit(0);
