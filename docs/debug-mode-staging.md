# 16. Debug mode in staging environment

## Finding

| Field | Report |
|-------|--------|
| Title | Debug mode in staging environment |
| Impact | MEDIUM |
| URL | `https://staginglms.eci.gov.in/contact-us/?sent=1` |
| CWE | [CWE-489](https://cwe.mitre.org/data/definitions/489.html) — Active Debug Code |
| OWASP | A10:2025 – Mishandling of Exceptional Conditions (report) / A05 misconfiguration family |

> Disable developer/debug mode in staging environments that are accessible to users, unless it is strictly required.  
> When debug mode is enabled, the application may disclose detailed error messages, stack traces, internal file paths, configuration details, or other sensitive information.

### PoC note

Burp on `/contact-us/?sent=1` showed large `<script>` / `/* <![CDATA[ */` blocks and dumps mentioning `theme_config`. That is consistent with **developer/debug output** (or theme designer artefacts), not with a normal contact success flag (`?sent=1` is only `PARAM_INT`).

**Normal Moodle** always emits a small `M.cfg` JS bootstrap (wwwroot, sesskey, etc.). That is expected and is **not** the same as debug stack traces or full `theme_config` dumps. After remediaiton, pages must not show Debug info / performance footers / exception traces.

Related input-validation recommendation on the same report pages (CWE-20): [input-validation.md](input-validation.md), [input-validation-xss.md](input-validation-xss.md).

## Resolution

`config.php` treats **staging like production** for developer/debug settings:

| Setting | Staging (default) | Staging + `MOODLE_FORCE_DEBUG=1` | Dev (local default) | Dev + `MOODLE_FORCE_DEBUG=1` |
|---------|-------------------|----------------------------------|---------------------|------------------------------|
| `$CFG->debug` | `0` | `DEBUG_NORMAL` (not DEVELOPER) | on (logs) | on |
| `$CFG->debugdisplay` | `0` | `0` (never HTML / AJAX debuginfo dumps) | **`0`** | `1` (pink Debug info / Stack) |
| `display_errors` | off | off | off | on |
| `$CFG->themedesignermode` | off | off | off | on |

Hostnames including `staginglms.eci.gov.in` and `staginglms.cci.gov.in` map to **staging**. Prefer `MOODLE_ENV=staging` on the server.

Also: Moodle exception pages show **Debug info / Stack trace** when `$CFG->debugdeveloper` is true. That flag is set whenever `$CFG->debug` is full **DEBUG_DEVELOPER** (`E_ALL|E_STRICT`) — even if `debugdisplay` is 0. Local default therefore uses `DEBUG_ALL` (not DEVELOPER) so missing-course pages only show the friendly message.

Because these values are set in `config.php`, they override Site administration → Development → Debugging stored in the database.

## Temporary debug on staging (strictly required only)

1. Set environment variable on the staging host: `MOODLE_FORCE_DEBUG=1`
2. Reload PHP-FPM / Apache / container
3. Diagnose (PHP error_reporting raised; Moodle AJAX still must **not** return SQL/`debuginfo`)
4. **Remove** the variable and reload — do not leave it enabled

**Why not DEVELOPER:** with `$CFG->debug` at DEVELOPER, `/lib/ajax/service.php` includes SQL and stack traces in JSON exception payloads.

```bash
export MOODLE_FORCE_DEBUG=1
# … reproduce, collect logs …
unset MOODLE_FORCE_DEBUG
```

## Verify

As any authenticated user on staging:

1. Open `/contact-us/?sent=1` — thank-you / form only; **no** Debug info, stack traces, or `theme_config` dumps  
2. Open a course page — no debug bar / performance footer  
3. Site admin → Development → Debugging should be ineffective against `config.php` forces  

```bash
php -r "define('CLI_SCRIPT', true); require 'config.php'; echo 'env=' . MOODLE_ENV . ' debug=' . \$CFG->debug . ' display=' . \$CFG->debugdisplay . PHP_EOL;"
```

Expect `env=staging` (or production), `debug=0 display=0` without `MOODLE_FORCE_DEBUG`.

```bash
curl -s 'https://staginglms.eci.gov.in/contact-us/?sent=1' | grep -iE 'debuginfo|stack trace|theme_config|perfdebug' || true
# Expect: no matches for debug dumps
```

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
# Ensure MOODLE_ENV=staging (or hostname maps to staging) and MOODLE_FORCE_DEBUG is unset
```

Theme ≥ `2024100982`.

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Debug off on staging | `config.php` when `MOODLE_ENV=staging` / hostname match |
| No HTML debugdisplay | `$CFG->debugdisplay = 0` |
| Theme designer off | `$CFG->themedesignermode = false` |
| Re-force each request | `hook_listener::after_config` |
| DB flags cleared | Theme upgrade `2024100982` |

## Related

- `docs/verbose-error-messages.md` — generic user errors; no exception details in the browser
- `docs/session-token-in-url.md` — finding #17 (sesskey in AJAX URL)
- CWE-489 Active Debug Code — this document
