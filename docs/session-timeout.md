# 40. Insufficient Session Expiration / Excessive Session Timeout

## Finding

| Field | Report |
|-------|--------|
| Title | Insufficient Session Expiration / Excessive Session Timeout |
| Impact | MEDIUM / CVSS 6.5 |
| URL | `https://staginglms.eci.gov.in/login/index.php`, site root |
| CWE | [CWE-613](https://cwe.mitre.org/data/definitions/613.html) — Insufficient Session Expiration |
| OWASP | A07:2025 – Authentication Failures |
| CVSS | `CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:L/A:N` |

> Session remained valid after ~5 hours of inactivity. Default timeout was **8 hours** (`sessiontimeout: 28800`).

## PoC

1. MFA OTP email ~11:41; still editing questions at ~16:58 (same day) without re-login.
2. Page source / `M.cfg`: `"sessiontimeout":"28800"` (8 hours), `"sessiontimeoutwarning":"1200"`.

## Resolution

| Setting | Before (Moodle default) | After (staging / production) |
|---------|-------------------------|------------------------------|
| `$CFG->sessiontimeout` | 28800 (8 hours) | **1800 (30 minutes)** idle |
| `$CFG->sessiontimeoutwarning` | 1200 (20 minutes) | **300 (5 minutes)** before expiry |

Forced in:

1. `config.php` when `MOODLE_ENV` is not `dev`
2. Theme `after_config` re-force (so Site admin cannot leave 8h quietly)
3. Theme upgrade `2024101015` → `set_config('sessiontimeout', 1800)`

Moodle invalidates the server session after idle timeout; the user must authenticate again (including MFA when required).

**Local DDEV (`dev`):** keeps Moodle default (or whatever is in the DB) for convenience unless you set the same values manually.

## Absolute session lifetime

Moodle’s primary control is **idle** timeout (`sessiontimeout`). Combined with existing controls:

- Concurrent login limit = 1 ([concurrent-sessions.md](concurrent-sessions.md))
- Password change destroys other sessions ([password-change-sessions.md](password-change-sessions.md))
- Secure / HttpOnly cookies ([cookie-httponly.md](cookie-httponly.md), [https-sensitive-data.md](https-sensitive-data.md))

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Confirm Site administration → Server → Session handling shows **30 minutes** (or check `M.cfg`).

## Verify

```bash
# Logged-in HTML should expose 1800, not 28800
curl -s -b 'MoodleSession=…' 'https://staginglms.eci.gov.in/' \
  | grep -oE '"sessiontimeout":"[0-9]+"' 
# Expect: "sessiontimeout":"1800"

# After 30+ minutes with no requests, next page load → login / MFA
```

## Evidence for auditors

| Claim | Result |
|-------|--------|
| 8-hour idle session | **Remediated** — 30 minutes idle on staging/production |
| Server-side invalidation | Moodle core session GC / timeout |
| Re-auth required after idle | Yes (password + MFA factors as configured) |

## Related

- [concurrent-sessions.md](concurrent-sessions.md)
- [password-change-sessions.md](password-change-sessions.md)
- [session-fixation.md](session-fixation.md)
- [mfa-privileged-accounts.md](mfa-privileged-accounts.md)
