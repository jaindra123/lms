# Verbose Error Messages Leading to Information Disclosure

## Finding

> Return generic error messages to users.

Detailed exception text (SQL, filesystem paths, stack traces, third-party API bodies) must not appear in the browser. Operators diagnose via server logs.

## Scanner mis-report: IDOR via `?id=` showing SQL

CDAC PoCs that change `/mod/assign/view.php?id=` or `/mod/attendance/view.php?id=` and capture SQL/`dml_missing_record_exception` pages are **verbose error disclosure**, not Broken Access Control. Authorization failed (or the CM record is missing); the bug was staging showing `debuginfo`.

After deploy, those URLs must **not** render SQL or stack traces to the browser.

## Controls

### 1. Environment debug display (`config.php`)

| Environment | `$CFG->debug` | `$CFG->debugdisplay` | `display_errors` |
|-------------|----------------|----------------------|------------------|
| production | **off** | **off** | **off** |
| staging (default) | **off** | **off** | **off** |
| staging + `MOODLE_FORCE_DEBUG=1` | on (logs only) | **off** | **off** |
| dev (local) | on | on | on |

Staging no longer runs with developer debug enabled for end users. See `docs/debug-mode-staging.md`.

### 2. Shared helper

`theme/iiidem2/classes/safe_errors.php`

- Logs full exception (and stack when developer debug level is set) via `error_log`
- Returns a **generic** string (`theme_iiidem2` / `genericerror`) for unexpected failures
- Optionally surfaces intentional `moodle_exception` validation/permission text (never appends `debuginfo`)

### 3. Endpoints updated to stop leaking `$e->getMessage()`

| Surface | Behaviour |
|---------|-----------|
| Chatbot admin action / poll | Generic JSON via `safe_errors::json()` |
| Homepage chatbot | Generic user message; details in log |
| Teacher materials / assignments | `safe_errors::notify()` |
| Registration submit | Same |
| Live class schedule | Same |
| Google Calendar observer | Generic “sync failed” notice (no API text) |
| Webex OAuth | Generic failure string; provider/exception text logged only |
| Live quiz API / PNB / ICICI returns | Already generic UI; details → `error_log` |
| Dashboard load | Already generic template flag + `error_log` |

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Confirm on staging/production: force a benign failure (e.g. invalid chatbot AJAX) and verify the response body has no SQL, paths, or stack traces.

Also re-test the auditor PoC against `/lib/ajax/service.php` with an invalid `methodname` — response must **not** contain `debuginfo`, `SELECT`, or filesystem paths (see `docs/sql-injection-parameterized-queries.md`).
