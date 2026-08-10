<?php
// This file is part of Moodle - http://moodle.org/
//
// CLI: enable / verify temporary account lockout settings.
//
// Usage (from Moodle root):
//   php theme/iiidem2/cli/enable_account_lockout.php
//   php theme/iiidem2/cli/enable_account_lockout.php --threshold=5 --window=1800 --duration=1800

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params([
    'help' => false,
    'threshold' => 5,
    'window' => 30 * 60,
    'duration' => 30 * 60,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if (!empty($options['help'])) {
    echo "Enable Moodle temporary account lockout after failed logins.

Options:
  --threshold=N   Failed attempts before lock (default 5)
  --window=SEC    Observation window seconds (default 1800)
  --duration=SEC  Lock duration seconds (default 1800; 0 = until admin unlock)
  -h, --help      Show this help
";
    exit(0);
}

$threshold = max(1, (int) $options['threshold']);
$window = max(60, (int) $options['window']);
$duration = max(0, (int) $options['duration']);

set_config('lockoutthreshold', $threshold);
set_config('lockoutwindow', $window);
set_config('lockoutduration', $duration);
set_config('displayloginfailures', 1);

cli_writeln('Account lockout enabled:');
cli_writeln('  lockoutthreshold = ' . $threshold);
cli_writeln('  lockoutwindow    = ' . $window . 's');
cli_writeln('  lockoutduration  = ' . $duration . 's' . ($duration === 0 ? ' (until admin unlock)' : ''));
cli_writeln('  displayloginfailures = 1');
cli_writeln('');
cli_writeln('Note: config.php forces these values when present; restart PHP-FPM/Apache if needed.');
cli_writeln('Admin unlock: Site administration → Users → Accounts → Browse list of users.');

exit(0);
