# 18. Verbose Error Messages Leading to Information Disclosure

## Finding

| Field | Report |
|-------|--------|
| Title | Verbose Error Messages Leading to Information Disclosure |
| Impact | MEDIUM / CVSS 5.3 |
| CWE | [CWE-209](https://cwe.mitre.org/data/definitions/209.html) — Generation of Error Message Containing Sensitive Information |
| OWASP | A02:2025 – Security Misconfiguration (report) |
| URLs | `/lib/ajax/service.php`, `/lib/ajax/service-nologin.php`, `/theme/yui_combo.php`, `/login/index.php`, `/course/view.php` |

> Return generic error messages to users. Do not return `debuginfo`, stack traces, or internal secrets in API/HTML responses.

### PoC (Instance 1)

Invalid AJAX `sesskey` against `/lib/ajax/service.php` returned JSON including:

- `debuginfo`: “The sesskey provided (YFv…) does not match the current sesskey (Mca…)”
- `backtrace`: full PHP stack with file paths

That only happens when Moodle **developer debug** is enabled (`debugging('', DEBUG_DEVELOPER)`). Staging/production must keep debug off **and** filter any residual fields.

### PoC (Instance 4 — HTML stack trace)

`/course/view.php` with an unexpected event showed Moodle error `unexpectedevent` plus a **Stack trace** block with `/var/www/html/...` paths. Same root cause: `$CFG->debugdisplay` / developer debug on staging. After remediation, users see a generic error only — no paths.

### PoC (Instance 5 — `service-nologin.php`)

Bare / invalid-token calls to `/lib/ajax/service-nologin.php` returned JSON with:

- `error`: “Invalid error detected. It must be fixed by a programmer: Invalid token…”
- `debuginfo`: Web services “internal error” text
- `stacktrace` present when developer debug was on

### PoC (Instance 6 — `yui_combo.php`)

`GET /theme/yui_combo.php` (no query) returned plain text:

> Unsupported server - query string can not be determined, try disabling YUI combo loading in admin settings.

That discloses edge/config advice on older Moodle builds. Bare probes should get a generic 404 body (core `combo_not_found`), while **valid rollup URLs must keep working**.

### PoC (docs.moodle.org help link)

Some Moodle errors offer “More information about this error” → `docs.moodle.org/.../error/...`. That is Moodle’s public docs index, not a staging stack dump. Keep debug/HTML traces off; the help link alone is not treated as CWE-209.

### PoC (Instance 8 / 9 — customcert)

`/mod/customcert/view.php?id=…&downloadown=1` without a real login showed `Exception - Invalid parameter` with SQL (`SELECT * FROM {user} WHERE id = ?` / `id = 0`) and a stack through `view.php`. That is **guest/anonymous + debugdisplay**, not a successful certificate download. Auth hardening: [broken-access-control.md](broken-access-control.md). After remediaiton + debug off: login redirect or generic error only — no SQL.

### PoC (third-party UPI — out of scope)

JSON from **`upiassembly.com`** (`code: bad_request_error`, “invalid characters”) is not served by this Moodle LMS. Dispute that host. Keep LMS errors generic per controls below.

## Controls

### 1. Environment debug display (`config.php` + theme)

| Environment | `$CFG->debug` | `$CFG->debugdisplay` | `display_errors` |
|-------------|----------------|----------------------|------------------|
| production | **off** | **off** | **off** |
| staging (default) | **off** | **off** | **off** |
| staging + `MOODLE_FORCE_DEBUG=1` | on (logs only) | **off** | **off** |
| dev (local) | on | on | on |

Theme `after_config` re-forces debug off on non-dev. See [debug-mode-staging.md](debug-mode-staging.md).

### 2. AJAX JSON sanitizer (belt-and-braces)

`theme_iiidem2\safe_errors::sanitize_ajax_json()` runs on every `AJAX_SCRIPT` response buffer (staging/production), including `service.php` / `service-nologin.php`:

- Removes `debuginfo`, `backtrace`, `stacktrace`, `reproductionlink`, exception `a`
- Replaces programmer / internal WS / sesskey-leak messages with generic user text

Wired from `security_headers::ensure_ajax_cache_control_buffer()`.

### 3. YUI combo (Instance 6)

| Control | Where |
|---------|--------|
| `$CFG->yuicomboloading = false` | `config.php` staging/production + upgrade `set_config` |
| Valid rollup/module combo URLs | **Must return 200** — required for Moodle JS (do not blanket-404) |
| Bare `/theme/yui_combo.php` (no modules) | Core returns generic 404 (“Combo resource not found”) — not admin-settings advice |

Do **not** block all `yui_combo.php` traffic: that breaks file picker / YUI (`YUI is not defined`). See [vulnerable-javascript-dependency.md](vulnerable-javascript-dependency.md).

### 4. Shared helper for custom endpoints

`theme/iiidem2/classes/safe_errors.php` — logs full exceptions server-side; returns generic user text / JSON.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
# MOODLE_FORCE_DEBUG unset; MOODLE_ENV=staging
```

Theme ≥ `2024100987`.

## Verify

```bash
# Instance 1 / 5 — no debug fields or programmer text
curl -s -X POST 'https://staginglms.eci.gov.in/lib/ajax/service-nologin.php' \
  -H 'Content-Type: application/json' \
  --data '[{"index":0,"methodname":"core_fetch_notifications","args":{}}]' \
  | grep -iE 'debuginfo|stacktrace|backtrace|must be fixed by a programmer|internal error in the Web' \
  || echo 'OK: no verbose AJAX leak'

# Instance 6 — bare probe: generic 404, not "Unsupported server…"
curl -s 'https://staginglms.eci.gov.in/theme/yui_combo.php' | head -c 200
# Expect: "Combo resource not found…" (or similar), NOT admin-settings advice

# Required rollup must work (200 + JS/CSS)
curl -sI 'https://staginglms.eci.gov.in/theme/yui_combo.php?rollup/3.18.1/yui-moodlesimple-min.js' | head -n 5
# Expect: HTTP 200
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Debug off | `config.php` + `after_config` re-force |
| No AJAX debuginfo/stacktrace | `safe_errors::sanitize_ajax_json` |
| No programmer/WS dump text | `scrub_public_error_text` |
| No YUI combo admin advice | `yuicomboloading=0` + bare probe → generic combo 404 (endpoint itself stays enabled) |
| Generic custom errors | `safe_errors` on theme AJAX/UI |

Related: [session-token-in-url.md](session-token-in-url.md), [sql-injection-parameterized-queries.md](sql-injection-parameterized-queries.md), [debug-mode-staging.md](debug-mode-staging.md).
