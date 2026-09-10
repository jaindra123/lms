<?php
// This file is part of Moodle - http://moodle.org/
//
// CLI: enable MFA for ALL accounts (students, teachers, admins).
//
// Usage (from Moodle root):
//   php theme/iiidem2/cli/enable_mfa_privileged.php
//   php theme/iiidem2/cli/enable_mfa_privileged.php --grace=604800 --forcesetup=1
//   php theme/iiidem2/cli/enable_mfa_privileged.php --grace=0

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params([
    'help' => false,
    'grace' => (string) \theme_iiidem2\mfa_privileged::DEFAULT_GRACE_SECONDS,
    'forcesetup' => true,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if (!empty($options['help'])) {
    echo "Enable Moodle Multi-Factor Authentication for ALL users (CDAC #21).

Students, teachers, and site administrators must complete a second factor
(TOTP authenticator app or email OTP) after password login.

Options:
  --grace=SEC       Grace period for first-time factor setup (default 604800 = 7 days).
                    Use 0 to disable the grace factor (users must verify immediately).
  --forcesetup=0|1  When grace ends, force factor setup (default 1).
  -h, --help        Show this help

After enable, each user should open:
  Preferences → Multi-factor authentication
and register an authenticator app (TOTP). Email OTP is also available.
";
    exit(0);
}

$grace = max(0, (int) $options['grace']);
$forcesetup = !empty($options['forcesetup']) && $options['forcesetup'] !== '0';

cli_writeln('Enabling MFA for all users (including students)…');
$lines = \theme_iiidem2\mfa_privileged::enable($grace, $forcesetup);
foreach ($lines as $line) {
    cli_writeln('  ' . $line);
}
cli_writeln('');
cli_writeln('Done. Verify: Site administration → Plugins → Admin tools → Multi-factor authentication');
cli_writeln('User setup: /admin/tool/mfa/user_preferences.php');

exit(0);
