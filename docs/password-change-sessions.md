# Active Sessions Not Invalidated After Password Change

## Finding

> Invalidate all active sessions immediately after a successful password change.

## Resolution

### 1. Forced Moodle security settings (`config.php`)

| Setting | Value | Effect |
|---------|-------|--------|
| `passwordchangelogout` | **1** | Core destroys all other browser sessions on password change / reset / admin set-password |
| `passwordchangetokendeletion` | **1** | Core deletes web service / mobile app tokens (form checkbox frozen on) |

These are forced before `setup.php`, so they cannot be silently turned off in Site administration.

### 2. Theme callbacks (belt-and-suspenders)

| Callback | When |
|----------|------|
| `theme_iiidem2_post_change_password_requests` | Preferences → Change password |
| `theme_iiidem2_post_set_password_requests` | Forgot-password / set-password token flow |

Both call `theme_iiidem2\session_security::invalidate_sessions_after_password_change()` which:

1. `\core\session\manager::destroy_user_sessions($userid, session_id())` — kills every other session immediately
2. `\webservice::delete_user_ws_tokens($userid)` — revokes API/mobile tokens
3. Regenerates the remaining session ID + CSRF sesskey

### Behaviour

- **Other devices / browsers:** logged out immediately after the password change succeeds.
- **Current browser:** stays logged in, but receives a **new** session id (old cookie value is invalid).
- **Mobile / web services:** tokens deleted when passwordchangetokendeletion is on (forced).

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Invalidate sessions on password change | Forced `$CFG->passwordchangelogout` + theme post_* callbacks |
| Immediate | Runs in the same request after `user_update_password` succeeds |
| All other sessions | `destroy_user_sessions($userid, $keepsid)` |
| WS / mobile sessions | `passwordchangetokendeletion` + `delete_user_ws_tokens` |
| Admin UI | Site administration → Security → Site security settings (forced/overridden) |
