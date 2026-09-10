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

That discloses edge/config advice. **Fix:** `theme/yui_combo.php` `combo_not_found()` always returns generic **“Combo resource not found, sorry.”** (404) — never admin-settings text. Valid rollup URLs must keep working.

**Staging note (2026-09 retest):** live staging still returned the old “Unsupported server…” string until `theme/yui_combo.php` is deployed from this repo.

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
| Successful `core_courseformat_get_state` / template JSON | Normal app data for authorized users — not an error dump |
| Razorpay `api.razorpay.com` JSON (`grpc`, `SERVER_ERROR`) | Third-party host — out of LMS scope; LMS shows generic copy only |

### PoC (Instance 8 / 9 — customcert)

`/mod/customcert/view.php?id=…&downloadown=1` without a real login showed `Exception - Invalid parameter` with SQL (`SELECT * FROM {user} WHERE id = ?` / `id = 0`) and a stack through `view.php`. That is **guest/anonymous + debugdisplay**, not a successful certificate download. Auth hardening: [broken-access-control.md](broken-access-control.md). After remediaiton + debug off: login redirect or generic error only — no SQL.

### PoC (third-party UPI — out of scope)

JSON from **`upiassembly.com`** (`code: bad_request_error`, “invalid characters”) is not served by this Moodle LMS. Dispute that host. Keep LMS errors generic per controls below.

### Retest round (2026-09) — auditor instances

| Instance | Observation | Verdict |
|----------|-------------|---------|
| **Instance 1:** `/user/index.php?id=4` → HTTP **500** + `<title>Error</title>` | Caused by throwing from `before_http_headers` → Moodle `early_error` (always 500). | **Fixed** — deny before output; early_error → **403** + generic body |
| **Instance 3:** `service.php?…&info=core_courseformat_get_state` | `"error": false` + course UI state JSON | **Dispute** — not an error; authorized course data (see below) |
| **Instance 4:** `service-nologin.php?…load_template…` | `"error": false` + Mustache UI templates | **Dispute** — not an error; public theme templates (see below) |
| `GET /theme/yui_combo.php` | “Unsupported server… disable YUI…” | **Fix** — deploy patched `theme/yui_combo.php` |
| Instance 5: remove checkout id → `api.razorpay.com` grpc JSON | Third-party Razorpay host, not LMS | **Dispute** LMS; LMS UI uses generic disruption copy |
| Instance 9: `api.razorpay.com/.../payment/status?key_id=` | Third-party Razorpay host | **Dispute** LMS |

### PoC (Instance 3 — `core_courseformat_get_state`) — **DISPUTE under CWE-209**

Burp: logged-in user on `/course/view.php?id=4` →

`POST /lib/ajax/service.php?sesskey=…&info=core_courseformat_get_state`  
Body: `[{"methodname":"core_courseformat_get_state","args":{"courseid":4}}]`

| Observation | Why it is **not** verbose-error disclosure |
|-------------|--------------------------------------------|
| `"error": false` | **Successful** API call — not an exception / stack / SQL dump |
| Large `data` JSON (sections, cm ids, titles, visibility) | Normal Moodle **course format state** for the reactive course UI; same structure the browser already needs to render `/course/view.php?id=4` |
| Requires valid session + `validate_context(course)` | Guests / users without course access get an auth/capability failure (sanitized) — not this payload |
| `sesskey` in query string | Separate finding **#17** ([session-token-in-url.md](session-token-in-url.md)); theme JS strips it and sends `X-Moodle-Sesskey` |

**Do not “fix” by stripping course state** — that breaks the course page. Retest as CWE-209 only if response contains `debuginfo`, `backtrace`, `stacktrace`, SQL, or `/var/www/…` paths.

### PoC (Instance 4 — `service-nologin.php` template load) — **DISPUTE under CWE-209**

Burp: `GET /lib/ajax/service-nologin.php?info=6-method-calls&…&args=[…core_output_load_template_with_dependencies…]`

| Observation | Why it is **not** verbose-error disclosure |
|-------------|--------------------------------------------|
| Every item `"error": false` | Successful template fetch — not an error page |
| Payload = Mustache HTML for `loading`, `modal`, `modal_backdrop`, etc. | Public UI chrome for theme `iiidem2`; no passwords, PII, SQL, or stack |
| `service-nologin.php` | By design allows cookie-less template/string loads used by Moodle AMD |
| `sesskey` in query | Again finding **#17**, not CWE-209 |

**Do not disable** `core_output_load_template_with_dependencies` — Moodle JS (modals, loading icons) depends on it.

### PoC (Instance 5 — Razorpay remove `checkout_id`) — **DISPUTE LMS**

Burp host is **`api.razorpay.com`**, not `staginglms.eci.gov.in`:

`POST https://api.razorpay.com/v1/standard_checkout/ads/ip/serve?key_id=rzp_test_…`  
Tamper: empty / remove `"checkout_id"` in JSON → Razorpay returns `500` + `"internal grpc error…"`.

| Claim | Assessment |
|-------|------------|
| LMS discloses gRPC internals | **No** — response is from **Razorpay’s** API |
| Moodle can change that JSON | **No** — third-party host |
| What LMS users see | Generic disruption copy via `paygw_razorpay` (`apiservererror` / `friendlyFailureMessage`) — never the raw grpc string |

Dispute against the LMS; optionally report to Razorpay. Related: [razorpay-key-id-exposure.md](razorpay-key-id-exposure.md).

### PoC (Instance 1 — `/user/index.php?id=4` HTTP 500)

Burp: logged-in student (`MoodleSession`) → `GET /user/index.php?id=4` → **500** + HTML error shell.

| Cause | Fix |
|-------|-----|
| Access guard threw `moodle_exception` inside `before_http_headers` (during `$OUTPUT->header()`) | Moodle treats that as early init → `bootstrap_renderer::early_error()` → **always HTTP 500** |
| Status 500 looked like a crash dump to scanners | Deny in `user/index.php` **before** any HTML; hook **redirects** instead of throw |
| `early_error` status / body | Staging/production: **403 Forbidden** + theme `genericerror` only (no stack/SQL/paths) |

**Expected after deploy:**

| Role | `GET /user/index.php?id=4` |
|------|----------------------------|
| Guest | Login redirect (3xx) |
| Student | **403** or redirect to course — generic message only, **never 500** |
| Teacher / manager | **200** participants table |

Deploy: `user/index.php`, `theme/iiidem2` (≥ `2024101041`), `lib/classes/output/bootstrap_renderer.php`.

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
| Bare `/theme/yui_combo.php` (no modules) | Always generic 404 body (“Combo resource not found, sorry.”) — `combo_not_found()` ignores any admin-advice `$message` |

Do **not** block all `yui_combo.php` traffic: that breaks file picker / YUI (`YUI is not defined`). See [vulnerable-javascript-dependency.md](vulnerable-javascript-dependency.md).

### 4. Shared helper for custom endpoints

`theme/iiidem2/classes/safe_errors.php` — logs full exceptions server-side; returns generic user text / JSON.

### 5. Razorpay checkout errors (Instance 5 — LMS side)

`paygw_razorpay` maps provider `SERVER_ERROR` / gRPC-style descriptions to `apiservererror` (server) and `friendlyFailureMessage()` (Checkout.js UI). Users never see “internal grpc error”.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
# MOODLE_FORCE_DEBUG unset; MOODLE_ENV=staging
```

Theme ≥ `2024101041`. paygw_razorpay ≥ `2025062916`. **Must deploy** `theme/yui_combo.php`, `user/index.php`, and `lib/classes/output/bootstrap_renderer.php` for Instance 1 / YUI fixes.

## Verify

```bash
# Instance 1 — participants: never HTTP 500; no stack/SQL
curl -sI -b 'MoodleSession=…' 'https://staginglms.eci.gov.in/user/index.php?id=4' | head -n 5
# Expect student: HTTP/1.1 403 or 303 — NOT 500
curl -s -b 'MoodleSession=…' 'https://staginglms.eci.gov.in/user/index.php?id=4' \
  | grep -iE 'stack trace|/var/www|SELECT |debuginfo|500 Internal' && echo FAIL || echo OK

# Instance 3 — successful course state (NOT an error dump)
# Expect: "error":false and NO debuginfo/backtrace
curl -s -X POST 'https://staginglms.eci.gov.in/lib/ajax/service.php' \
  -H 'Content-Type: application/json' -H 'X-Moodle-Sesskey: YOURKEY' -b 'MoodleSession=…' \
  --data '[{"index":0,"methodname":"core_courseformat_get_state","args":{"courseid":4}}]' \
  | grep -iE 'debuginfo|backtrace|stacktrace|/var/www|SELECT ' && echo FAIL || echo OK

# Instance 4 — successful templates (NOT an error dump)
curl -s 'https://staginglms.eci.gov.in/lib/ajax/service-nologin.php?info=1-method-call&args=%5B%7B%22index%22%3A0%2C%22methodname%22%3A%22core_output_load_template_with_dependencies%22%2C%22args%22%3A%7B%22component%22%3A%22core%22%2C%22template%22%3A%22loading%22%2C%22themename%22%3A%22iiidem2%22%2C%22lang%22%3A%22en%22%7D%7D%5D' \
  | grep -iE '"error"\s*:\s*true|debuginfo|backtrace|stacktrace' && echo FAIL || echo OK

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
| No YUI combo admin advice | Patched `theme/yui_combo.php` → generic combo 404 |
| Generic custom errors | `safe_errors` on theme AJAX/UI |
| Razorpay grpc text not shown in LMS UI | `paygw_razorpay` friendlyFailureMessage / apiservererror |
| `/user/index.php` never HTTP 500 for authz deny | Deny before output + `early_error` → 403 + generic text |

Related: [session-token-in-url.md](session-token-in-url.md), [sql-injection-parameterized-queries.md](sql-injection-parameterized-queries.md), [debug-mode-staging.md](debug-mode-staging.md), [os-command-injection.md](os-command-injection.md) (#32 invalid course ID is not CWE-209), [web-parameter-tampering.md](web-parameter-tampering.md).
