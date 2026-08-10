# Multiple Concurrent Sessions Allowed for the Same User

## Finding

> Invalidate previous sessions when a new login occurs.

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
- **Web service / mobile tokens:** not revoked on login (still revoked on password change — see `docs/password-change-sessions.md`).

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Limit concurrent logins | Forced `$CFG->limitconcurrentlogins = 1` |
| Invalidate on new login | `hook_listener::after_login_completed` → `invalidate_other_sessions_on_login` |
| All other browser sessions | `destroy_user_sessions($userid, $keepsid)` |
| Core login path | `login/index.php` → `apply_concurrent_login_limit` |
| Admin UI | Plugins → Authentication → Limit concurrent logins (forced to 1) |
