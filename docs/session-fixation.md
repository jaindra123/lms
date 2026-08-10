# Session Fixation — regenerate session ID immediately

## Finding

> Regenerate the session ID immediately

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

### 2. Explicit site hardening (this change)

| Control | Where |
|--------|--------|
| Immediate post-login regenerate + CSRF sesskey rotate | `theme_iiidem2\session_security::regenerate_id_now()` |
| Called on every successful login | `hook_listener::after_login_completed` |
| Registration auto-login | `register/index.php` → `complete_user_login()` (same hook path) |
| No session IDs in URLs | `config.php`: `session.use_only_cookies`, `usesid = false` |
| Secure session cookies (HTTPS) | Existing `cookiesecure` / `cookiehttponly` / `cookiesamesite` |

Helper: `theme/iiidem2/classes/session_security.php`

### Attack blocked

Attacker sets victim’s browser cookie to a known session id → victim logs in → **new** session id is issued immediately → attacker’s fixed id is destroyed and no longer authenticated.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Regenerate on login | Core `login_user()` + theme `session_security::regenerate_id_now()` |
| Immediate | Hook runs in `after_login_completed` before redirect |
| Old session destroyed | `manager::destroy($oldsid)` |
| CSRF token rotated | `unset($USER->sesskey); sesskey();` |
| Cookie-only sessions | `use_only_cookies` / `$CFG->usesid = false` |
