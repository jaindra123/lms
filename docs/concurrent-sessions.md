# 22. Multiple Concurrent Sessions Allowed for the Same User

## Finding

| Field | Report |
|-------|--------|
| Title | 22. Multiple Concurrent Sessions Allowed for the Same User |
| Impact | MEDIUM / CVSS 5.3 |
| URL | `https://staginglms.eci.gov.in/login/index.php` (PoC sometimes `cci.gov.in`) |
| CWE | [CWE-613](https://cwe.mitre.org/data/definitions/613.html) — Insufficient Session Expiration |
| OWASP | A07:2021 – Identification and Authentication Failures |
| CVSS vector | `AV:N/AC:L/PR:L/UI:N/S:U/C:L/I:L/A:N` |

> The application allows the same user account to maintain multiple active sessions across different devices or browsers simultaneously. Invalidate previous sessions when a new login occurs.

### PoC note

Instance 1: same account open in two browsers (e.g. profile + programme overview) at once. After remediaiton, the **older** session is destroyed on the newer login — Browser 1 must re-authenticate.

## Resolution

### 1. Forced Moodle setting (`config.php`)

| Setting | Value | Effect |
|---------|-------|--------|
| `limitconcurrentlogins` | **1** | Core `/login/index.php` calls `apply_concurrent_login_limit()` and keeps only the newest browser session |

Forced via `config.php` before `setup.php`, so it cannot be turned off quietly in Site administration.

### 2. Theme hook (all login paths)

On every successful authentication (`\core_user\hook\after_login_completed`):

1. Regenerate session id (existing fixation hardening)
2. `theme_iiidem2\session_security::invalidate_other_sessions_on_login($userid, session_id())`
   - `\core\session\manager::destroy_user_sessions($userid, $keepsid)` — destroy every other browser session
   - `\core\session\manager::apply_concurrent_login_limit($userid, $keepsid)` — belt-and-suspenders with the forced limit

Covers standard login, registration auto-login (`complete_user_login`), and any other path that dispatches `after_login_completed`.

### Behaviour

- **New login (this browser):** stays authenticated with a fresh session id.
- **Previous devices / browsers for the same account:** logged out immediately (next request has no valid session).
- **Guest:** skipped.
- **Web service / mobile tokens:** not revoked on login (still revoked on password change — see [password-change-sessions.md](password-change-sessions.md)).

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## Verify

1. Log in as user A in Browser 1.  
2. Log in as the same user in Browser 2.  
3. Reload a protected page in Browser 1 → must require login again.  
4. Browser 2 remains logged in.

```bash
php -r "define('CLI_SCRIPT', true); require 'config.php'; echo 'limit=' . \$CFG->limitconcurrentlogins . PHP_EOL;"
# Expect: limit=1
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Limit concurrent logins | Forced `$CFG->limitconcurrentlogins = 1` |
| Invalidate on new login | `hook_listener::after_login_completed` → `invalidate_other_sessions_on_login` |
| All other browser sessions | `destroy_user_sessions($userid, $keepsid)` |
| Core login path | `login/index.php` → `apply_concurrent_login_limit` |
| Admin UI | Plugins → Authentication → Limit concurrent logins (forced to 1) |

Related: [session-fixation.md](session-fixation.md), [password-change-sessions.md](password-change-sessions.md), [mfa-privileged-accounts.md](mfa-privileged-accounts.md).
