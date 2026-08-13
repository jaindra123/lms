# 29. Improper Cache Control on Sensitive Pages

## Finding

| Field | Report |
|-------|--------|
| Title | 29. Improper Cache Control on Sensitive Pages |
| Impact | LOW (CVSS 4.1) |
| URL | `https://staginglms.eci.gov.in/login/index.php` (also dashboard / AJAX; host may cite other names) |
| CWE | [CWE-525](https://cwe.mitre.org/data/definitions/525.html) – Use of Web Browser Cache Containing Sensitive Information |
| OWASP | A05:2021 – Security Misconfiguration |
| CVSS vector | AV:L/AC:L/PR:N/UI:R/S:U/C:L/I:N/A:N |

> Ensure authenticated pages cannot be served from the browser cache after logout.  
> Reference: [CWE-525](https://cwe.mitre.org/data/definitions/525.html).

If dashboards, profile pages, login, or authenticated AJAX JSON are cached, the browser **Back** button (or bfcache) can show sensitive data after the session ends.

## PoC analysis

### Instance 1 — Back button after logout (report)

| Step | Action | Expected after fix |
|------|--------|--------------------|
| 1 | User logs out (from an authenticated page, e.g. course / blog) | Session destroyed + `Clear-Site-Data` |
| 2 | User presses browser **Back** | Must not restore prior HTML from bfcache/disk cache |
| 3 | Report claimed “authenticated page is displayed” | Must show login / public reload only — **not** the prior logged-in UI |

Cited URL: `/login/index.php` (logout landing). Issue is prior authenticated pages restored via Back.

### A) Session cookie copy (often mixed into this finding)

Copying a live `MoodleSession` into another browser while still logged in is **session reuse**, not HTTP cache. See [session-fixation.md](session-fixation.md) / logout destroy + [cookie-httponly.md](cookie-httponly.md).

### B) Login page (`/login/index.php`)

Must not be stored long-term in the browser cache (credentials UI / error messages). Covered by `/login/*` → `no-store`.

### C) AJAX without `no-store` (Burp on `/lib/ajax/service.php`)

Auditor captures (e.g. `core_get_fragment`, `core_calendar_get_calendar_day_view`, **`core_message_get_conversation_messages`**) show Moodle’s weaker header:

```http
Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0
```

Missing **`no-store`**. Authenticated AJAX must not be stored in the browser disk cache.

**Instance 4 (report):**  
`/lib/ajax/service.php?sesskey=…&info=core_message_get_conversation_messages`  
→ covered by AJAX `no-store` + sesskey moved off the query string ([session-token-in-url.md](session-token-in-url.md)).

**Instance 5 (report):**  
`/theme/iiidem2/dashboard/index.php#overview` (authenticated dashboard)  
→ covered by logged-in `Cache-Control: no-store` + logout `Clear-Site-Data`.

**Host note:** some pages cite `cci.gov.in` / `oni.gov.in` — retest on the IIIDEM Moodle host with theme `iiidem2` deployed.

## Fixes

### 1. HTTP cache headers (authenticated + login + AJAX)

`theme_iiidem2\security_headers::send_sensitive_cache_control()` sets:

```http
Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0
Pragma: no-cache
Expires: 0
```

Applied when:

- The user is logged in (non-guest), **or**
- The request is under `/login/`

Includes `/lib/ajax/service.php` and other `AJAX_SCRIPT` / webservice paths for authenticated users.

Called from:

1. `security_headers::send()` (`after_config`)
2. `before_http_headers` (HTML pages — beats Moodle `send_headers()`)
3. **AJAX output buffer** (`ensure_ajax_cache_control_buffer`) so `no-store` wins at flush even if something weaker ran earlier

Intentional Moodle public AJAX (`GET` + `cachekey`) is left cacheable.

### 2. Logout — clear browser site data + destroy session

On `user_loggedout`:

```http
Clear-Site-Data: "cache", "cookies", "storage", "executionContexts"
```

### 3. HTML meta (defence in depth)

For logged-in pages, `<meta http-equiv="Cache-Control" …>` / `Pragma` in `<head>`.

### 4. Back-forward cache (bfcache) guard

`theme/iiidem2/javascript/auth_nocache_back.js` on authenticated pages: if the browser restores the document via **Back** (`pageshow` + `persisted`), force a full reload so the server re-checks the session (guest/login) instead of showing cached HTML.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## Verify

```bash
# Login page (finding #29 cited URL)
curl -sI 'https://staginglms.eci.gov.in/login/index.php' | grep -iE 'cache-control|pragma|expires'
# Expect: Cache-Control: … no-store …

# Authenticated AJAX (use session cookie + valid body; prefer no sesskey in URL after theme JS):
curl -sI -X POST -b 'MoodleSession…=…' \
  -H 'Content-Type: application/json' \
  -H 'X-Moodle-Sesskey: YOUR_SESSKEY' \
  --data '[{"index":0,"methodname":"core_message_get_conversation_messages","args":{}}]' \
  'https://staginglms.eci.gov.in/lib/ajax/service.php' | grep -i cache-control
# Expect: Cache-Control: … no-store …

# Dashboard while logged in:
curl -sI -b 'MoodleSession…=…' 'https://staginglms.eci.gov.in/theme/iiidem2/dashboard/index.php' | grep -i cache-control
# Expect: no-store
```

Browser: log out → **Back** from a prior authenticated page (course, blog, dashboard) → must not restore authenticated UI from cache (reload / login only).

Theme ≥ `2024100995`.

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| no-store on auth pages | `send_sensitive_cache_control()` |
| no-store on `/login/*` | Same (cited URL) |
| no-store on auth AJAX | Same + AJAX output-buffer reassert |
| Post-logout cache clear | `Clear-Site-Data` on logout |
| Meta fallback | Cache-Control / Pragma in `<head>` |
| Back / bfcache | `auth_nocache_back.js` reloads persisted pages |

Related: [security-headers.md](security-headers.md), [session-fixation.md](session-fixation.md), [referrer-policy.md](referrer-policy.md) (#28 recommendation on prior page).
