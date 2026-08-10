# Debug mode in staging environment

## Finding

> Disable developer/debug mode in staging environments that are accessible to users, unless it is strictly required.

## Resolution

`config.php` now treats **staging like production** for developer/debug settings:

| Setting | Staging (default) | Staging + `MOODLE_FORCE_DEBUG=1` | Dev (local) |
|---------|-------------------|----------------------------------|-------------|
| `$CFG->debug` | `0` | `DEBUG_NORMAL` (not DEVELOPER) | on |
| `$CFG->debugdisplay` | `0` | `0` (never HTML / AJAX debuginfo dumps) | on |
| `display_errors` | off | off | on |
| `$CFG->themedesignermode` | off | off | on |
| `$CFG->cachejs` | on | on | (default) |
| `$CFG->perfdebug` / page info / string ids | off | off | off |

Because these values are set in `config.php`, they override Site administration → Development → Debugging stored in the database.

## Temporary debug on staging (strictly required only)

1. Set environment variable on the staging host: `MOODLE_FORCE_DEBUG=1`
2. Reload PHP-FPM / Apache / container
3. Diagnose (PHP error_reporting raised; Moodle AJAX still must **not** return SQL/`debuginfo`)
4. **Remove** the variable and reload — do not leave it enabled

**Why not DEVELOPER:** with `$CFG->debug` at DEVELOPER, `/lib/ajax/service.php` includes SQL and stack traces in JSON exception payloads. Scanners cite that as “Blind SQL Injection” even though queries use `?` placeholders. See `docs/sql-injection-parameterized-queries.md`.

Example (systemd / container env):

```bash
export MOODLE_FORCE_DEBUG=1
# … reproduce, collect logs …
unset MOODLE_FORCE_DEBUG
```

## Verify

As any authenticated user on staging:

1. Open a normal course page — no debug bar, no stack traces, no “Debug info” footers
2. Site admin → Development → Debugging should reflect forced-off values from `config.php` (or be ineffective against them)

```bash
# From app root (CLI)
php -r "define('CLI_SCRIPT', true); require 'config.php'; echo 'debug=' . \$CFG->debug . ' display=' . \$CFG->debugdisplay . PHP_EOL;"
```

Expect `debug=0 display=0` on staging without `MOODLE_FORCE_DEBUG`.

## Related

- `docs/verbose-error-messages.md` — generic user errors; no exception details in the browser
