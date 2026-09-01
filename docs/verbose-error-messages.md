# Verbose / excessive error information disclosure

## Findings (CDAC)

| # | Title | Same control |
|---|-------|----------------|
| 18 | Verbose Error Messages Leading to Information Disclosure | This doc |
| **37** | **Excessive Error Information Disclosure** | This doc (same CWE-209) |

## Finding

| Field | Report (#18 / #37) |
|-------|--------|
| Title | Verbose / Excessive Error Information Disclosure |
| Impact | MEDIUM / CVSS 5.3 |
| CWE | [CWE-209](https://cwe.mitre.org/data/definitions/209.html) — Generation of Error Message Containing Sensitive Information |
| OWASP | A02/A05:2025 – Security Misconfiguration |
| CVSS | `CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N` |

> Return generic error messages to users. Do not return `debuginfo`, stack traces, SQL, file paths, framework/server internals, or other implementation details.

### URLs cited in #37

| URL | Expected safe behaviour (debug off) |
|-----|-------------------------------------|
| `/course/view.php?id=4` | Normal course page, or generic Moodle error — **no** stack/SQL/paths |
| `/course/edit.php?id=5` | Login / capability / generic error — no internals |
| `/question/bank/editquestion/question.php?…` | Auth/capability gate or generic error |
| `/pluginfile.php/…/user/icon/…` | Image or 404 — not PHP dumps |
| `/grade/report/grader/index.php?id='&…` | Invalid `id` → generic “invalid course ID” (PARAM_INT); **not** SQL |
| `/user/contactsitesupport.php` | Support form or redirect — no debug footer |

Also covered earlier: `/lib/ajax/service.php`, `service-nologin.php`, `/theme/yui_combo.php`, `/login/index.php`.

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

### PoC (#37 Instance — missing course / `invalidrecord`)

1. `course/view.php?id=4` → normal course.
2. Change to `id=8` (no such course) → historically named the DB table; later a soft “item could not be found” plus docs link.

**Hardened (theme ≥ 2024101014):** end users see only the theme generic message —  
“Something went wrong. Please try again…” — **no** “More information about this error” docs link, **no** SQL/stack. Local `MOODLE_FORCE_DEBUG=1` still shows developer detail.

### PoC (#37 Instance — AJAX SQL fuzz on `service.php`)

Intruder injects SQL/OOB payloads into `tiny_autosave_reset_session` args (`pageinstance` / `pagehash`). Response:

```json
{
  "error": true,
  "exception": {
    "message": "Invalid parameter value detected",
    "errorcode": "invalidparameter",
    "moreinfourl": "https://docs.moodle.org/405/en/error/debug/invalidparameter"
  }
}
```

That is **successful input rejection** (PARAM validation), not SQL injection and not a stack dump. No `debuginfo` / `backtrace` when `debugdisplay=0`. Dispute as SQLi; accept as evidence that verbose debug is off.

### PoC (#37 Instance 6 — grader `id` SQL quote fuzz)

Intruder on `/grade/report/grader/index.php?id='&sifirst=A&silast=` (payloads `'`, `"`, `\`, etc.):

| Observation | Meaning |
|-------------|---------|
| Status often **404** / shorter body vs baseline `id=4` | Invalid cleaned `id` — not a SQL error page |
| HTML: **“You are trying to use an invalid course ID”** | Moodle `invalidcourseid` after `required_param('id', PARAM_INT)` |
| No SQL / stack / `debuginfo` in body | Debug off — **not** CWE-209 disclosure |

Same control as [#32 OS command injection](os-command-injection.md) and [sql-injection-parameterized-queries.md](sql-injection-parameterized-queries.md): integer allow-list; quote never reaches SQL as syntax.

### PoC (#37 Instance 7 — `contactsitesupport.php` + `Origin: evil.com`)

Burp POST to `/user/contactsitesupport.php` with `Origin: https://evil.com` showed Moodle’s sesskey failure page (“session has most likely timed out…”).

| Claim | Result |
|-------|--------|
| Origin reflection / CORS allow evil.com | **No** — ACAO is fixed to site wwwroot only ([security-headers.md](security-headers.md)) |
| Changing Origin alone = vuln | **No** — form still requires valid `sesskey`; failure is CSRF/session check working |
| Message too verbose (CWE-209) | Softened `invalidsesskey` to a short generic reload prompt (no “timed out / check login” essay) |

### What is *not* CWE-209 (#37)

| Observation | Assessment |
|-------------|------------|
| “Invalid course ID” / “required parameter missing” / “Invalid parameter value detected” | Normal Moodle localization — OK |
| Softened sesskey / not-found messages | Generic reload / not-found — OK |
| Link to docs.moodle.org `missingparam` / `invalidcourseid` / `invalidrecord` / `invalidparameter` | Public docs — OK |
| “Can't find data record in database table …” | Softened to generic not-found (see above) |
| `Server: Apache` without version | Prefer hide at edge ([version-disclosure.md](version-disclosure.md)) — separate from error body |
| Stack trace / SQL / `/var/www/html/…` / `debuginfo` | **Must not** appear — fixed by debug off + sanitizers below |

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

# Finding #37 — malformed grader id / course view: no SQL or stack; no DB table name
curl -s 'https://staginglms.eci.gov.in/course/view.php?id=999999' \
  | grep -iE 'database table|stack trace|/var/www|SELECT |debuginfo' && echo FAIL || echo OK
curl -s 'https://staginglms.eci.gov.in/grade/report/grader/index.php?id=%27&sifirst=A&silast=' \
  | grep -iE 'stack trace|/var/www|SELECT |debuginfo|DML' && echo FAIL || echo OK
curl -sI 'https://staginglms.eci.gov.in/course/view.php?id=4' | head -n 5
```

Confirm on staging CLI:

```bash
php -r "define('CLI_SCRIPT', true); require 'config.php'; echo 'env=' . MOODLE_ENV . ' debug=' . \$CFG->debug . ' display=' . \$CFG->debugdisplay . PHP_EOL;"
# Expect: env=staging debug=0 display=0  (or debug bitflags with display=0 if MOODLE_FORCE_DEBUG)
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Debug off | `config.php` + `after_config` re-force |
| No AJAX debuginfo/stacktrace | `safe_errors::sanitize_ajax_json` |
| No programmer/WS dump text | `scrub_public_error_text` |
| No YUI combo admin advice | `yuicomboloading=0` + bare probe → generic combo 404 (endpoint itself stays enabled) |
| Generic custom errors | `safe_errors` on theme AJAX/UI |

Related: [session-token-in-url.md](session-token-in-url.md), [sql-injection-parameterized-queries.md](sql-injection-parameterized-queries.md), [debug-mode-staging.md](debug-mode-staging.md), [os-command-injection.md](os-command-injection.md) (#32 invalid course ID is not CWE-209).
