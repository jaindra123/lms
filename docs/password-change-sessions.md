# 11. Active Sessions Not Invalidated After Password Change

## Finding

| Field | Report |
|-------|--------|
| Title | Active Sessions Not Invalidated After Password Change |
| Impact | HIGH / CVSS 8.1 |
| URL | `/login/index.php` (password change via Preferences) |
| CWE | [CWE-613](https://cwe.mitre.org/data/definitions/613.html) — Insufficient Session Expiration |
| OWASP | A07:2021 – Identification and Authentication Failures |

> The application does not invalidate existing authenticated sessions after a user changes their password. Other active sessions (different browsers or devices) remain valid without re-authentication.

### PoC (auditor steps)

Typical CDAC write-up:

1. Log in on **Browser A** and **Browser B** (same account). Change password in one browser.
2. See “Password has been changed”.
3. The **other** browser still reaches Programme Overview / protected pages without logging in again.

**After remediaiton:** the browser that did **not** change the password must be logged out on the next request. The browser that completed the change may stay signed in, but receives a **new** session id.

**Host note:** some pages cite `staginglms.cci.gov.in` — this LMS is `staginglms.eci.gov.in` / production ECI hosts. Retest on the IIIDEM Moodle URL.

Related: session fixation ([session-fixation.md](session-fixation.md)); concurrent login limit also prevents long-lived dual sessions ([concurrent-sessions.md](concurrent-sessions.md)).

## Resolution

### 1. Forced Moodle security settings (`config.php`)

| Setting | Value | Effect |
|---------|-------|--------|
| `passwordchangelogout` | **1** | Core `destroy_user_sessions()` on change password / set password / reset |
| `passwordchangetokendeletion` | **1** | Deletes web service / mobile app tokens |

Forced before `setup.php` (cannot silently disable in Site administration).

Core path: `login/change_password.php` after successful update:

```php
\core\session\manager::destroy_user_sessions($USER->id, session_id());
```

### 2. Theme callbacks (belt-and-suspenders)

| Callback | When |
|----------|------|
| `theme_iiidem2_post_change_password_requests` | Preferences → Change password |
| `theme_iiidem2_post_set_password_requests` | Forgot-password / set-password token flow |

Both call `theme_iiidem2\session_security::invalidate_sessions_after_password_change()` which:

1. Destroys every **other** browser session immediately  
2. Revokes API/mobile tokens  
3. Regenerates the remaining session ID + CSRF sesskey  

### Behaviour

| Client | After password change |
|--------|------------------------|
| Browser that changed the password | Stays logged in; **new** `MoodleSession` value |
| Other browsers / devices | Session destroyed → login required |
| Mobile / WS tokens | Deleted |

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Confirm in config / admin: `passwordchangelogout` and `passwordchangetokendeletion` are on.

## Verify (matches auditor PoC)

1. Log in as the same user in **Browser A** and **Browser B**  
   (If `limitconcurrentlogins = 1`, the second login may already drop the first — that is intentional; for this test you can change password then check any older cookie.)
2. In **A**: Preferences → Change password → succeed (“Password has been changed”).
3. In **B**: refresh `/my/` or Programme Overview → must require login (not stay on the dashboard).
4. In **A**: still usable, but `MoodleSession*` cookie value should have changed.

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Invalidate other sessions | Forced `$CFG->passwordchangelogout` + `destroy_user_sessions` |
| Immediate | Same request as successful password update |
| Theme reinforcement | `post_change_password_requests` / `post_set_password_requests` |
| WS / mobile | `passwordchangetokendeletion` + `delete_user_ws_tokens` |
| Session id rotate | `session_security::regenerate_id_now()` on remaining session |
