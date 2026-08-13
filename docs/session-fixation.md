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

Auditor step “Moodle session id generated after login for valid user” only shows that a session cookie exists after login. The control required is that the **pre-login** session id is **not** the authenticated one — i.e. regenerate (and destroy the old id) on successful authentication.

## Resolution

### 1. Moodle core (already present)

`complete_user_login()` → `\core\session\manager::login_user()`:

```php
$sid = session_id();
session_regenerate_id(true);
self::destroy($sid);
self::add_session($user->id);
```

Used by standard `/login/index.php` and custom registration auto-login.

### 2. Explicit site hardening (belt-and-braces)

| Control | Where |
|--------|--------|
| Immediate post-login regenerate + CSRF sesskey rotate | `theme_iiidem2\session_security::regenerate_id_now()` |
| Called on every successful login | `hook_listener::after_login_completed` |
| Registration auto-login | `register/index.php` → `complete_user_login()` (same hook path) |
| No session IDs in URLs | `config.php`: `session.use_only_cookies`, `usesid = false` |
| Secure session cookies (HTTPS) | `cookiesecure` / `cookiehttponly` / `cookiesamesite` |
| Single concurrent browser session | Other sessions destroyed on login (`docs/concurrent-sessions.md`) |

Helper: `theme/iiidem2/classes/session_security.php`

### Attack blocked

Attacker sets victim’s browser cookie to a known session id → victim logs in → **new** session id is issued immediately → attacker’s fixed id is destroyed and no longer authenticated.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## Verify

1. Open `/login/index.php` in a private window; note `MoodleSession*` cookie value (DevTools → Application → Cookies).
2. Log in successfully.
3. Confirm the cookie value **changed** after login.
4. Confirm the old pre-login session id cannot access `/my/` (reuse it in another client → not authenticated).

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Regenerate on login | Core `login_user()` + theme `session_security::regenerate_id_now()` |
| Immediate | Hook runs in `after_login_completed` before redirect |
| Old session destroyed | `manager::destroy($oldsid)` |
| CSRF token rotated | `unset($USER->sesskey); sesskey();` |
| Cookie-only sessions | `use_only_cookies` / `$CFG->usesid = false` |

Related finding #9 (account lockout): `docs/account-lockout.md`.
