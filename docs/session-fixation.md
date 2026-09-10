# 10. Session Fixation

## Finding

| Field | Report |
|-------|--------|
| Title | Session Fixation |
| Impact | HIGH / CVSS 8.1 |
| URL | `/login/index.php` |
| CWE | [CWE-384](https://cwe.mitre.org/data/definitions/384.html) — Session Fixation |
| OWASP | A07:2021 – Identification and Authentication Failures |

> The application does not regenerate the session identifier after successful user authentication. An attacker can force or predict a valid session ID before the victim logs in. If the victim authenticates using the same session ID, the attacker can reuse that session to hijack the authenticated session.

### PoC note

| Auditor step | Correct classification |
|--------------|------------------------|
| `MoodleSession` **same** before login and after login | **Session fixation** — must regenerate |
| Copy **post-login** `MoodleSession` into another browser | **Session theft / reuse** — not fixation; mitigated by HttpOnly, timeout, single concurrent session |

**Staging recheck (2026-09) — auditor Steps 1–2:**

| Step | What was shown | Classification |
|------|----------------|----------------|
| 1 | Capture `MoodleSession` of logged-in user MC (`t4uev…`) | Post-auth cookie |
| 2 | Paste that value into another browser’s cookies | **Session theft**, not fixation (CWE-384) |

That PoC proves cookies work as session bearers (expected). It does **not** prove the SID failed to rotate at login.

**Correct fixation retest:**

1. Logged-out browser → note `MoodleSession` = **A**
2. Log in in the **same** browser
3. Confirm cookie is **B** ≠ **A**
4. Client with cookie **A** must stay logged out

**Verdict for Steps 1–2 as written:** **Dispute** (wrong test for CWE-384). Regeneration controls remain in theme + core; retest with pre/post login SID comparison above.

## Root cause (re-raised finding)

Theme `security_headers::send()` ran in `after_config` and emitted CSP/HSTS/`Cache-Control` **before** `complete_user_login()`. Once any header is sent, PHP `session_regenerate_id()` cannot issue a new `Set-Cookie` for `MoodleSession`, so the pre-login cookie value survived authentication.

## Resolution

### 1. Moodle core

`complete_user_login()` → `\core\session\manager::login_user()`:

```php
$sid = session_id();
session_regenerate_id(true);
self::destroy($sid);
self::add_session($user->id);
```

### 2. Site hardening (theme_iiidem2)

| Control | Where |
|--------|--------|
| Defer security headers on auth POST/SSO | `hook_listener::after_config` skips `send()` when `is_session_auth_post_request()` |
| Immediate post-login regenerate + CSRF rotate | `session_security::regenerate_id_now()` |
| Explicit `Set-Cookie` for new `MoodleSession` | `emit_moodle_session_cookie()` |
| Fallback if `session_regenerate_id` no-ops | `session_create_id` + restart |
| Called on every successful login | `after_login_completed` (before theme-only redirects) |
| Security headers after cookie rotate | `security_headers::send()` at end of successful login hook; also `before_http_headers` |
| Destroy old sid | `manager::destroy($oldsid)` |
| Single concurrent browser session | Other sessions destroyed on login ([concurrent-sessions.md](concurrent-sessions.md)) |
| No session IDs in URLs | `session.use_only_cookies`, `$CFG->usesid = false` |

Helper: `theme/iiidem2/classes/session_security.php`

### Attack blocked

Attacker sets victim’s browser cookie to a known session id → victim logs in → **new** session id is issued immediately (Set-Cookie) → attacker’s fixed id is destroyed and no longer authenticated.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Theme version ≥ `2024101023`.

## Verify

1. Private window → open site (logged out) → note `MoodleSession` value.
2. Log in successfully.
3. Confirm `MoodleSession` **changed** (DevTools → Application → Cookies).
4. Paste the **old** pre-login value into another client → must **not** be authenticated.
5. (Optional) Paste the **new** post-login value into another browser while still logged in → may appear logged in until timeout / other login; that is cookie theft, not fixation. New login elsewhere invalidates other sessions.

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Regenerate on login | Core `login_user()` + theme `session_security::regenerate_id_now()` |
| Headers do not block Set-Cookie | Auth POST defers CSP/HSTS until after regenerate |
| Immediate | Hook runs in `after_login_completed` before redirect |
| Old session destroyed | `manager::destroy($oldsid)` |
| CSRF token rotated | `unset($USER->sesskey); sesskey();` |
| Cookie-only sessions | `use_only_cookies` / `$CFG->usesid = false` |

Related: [concurrent-sessions.md](concurrent-sessions.md), [cookie-httponly.md](cookie-httponly.md), [session-timeout.md](session-timeout.md).
