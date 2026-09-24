# Insufficient Session Expiration / Excessive Session Timeout

## Finding

| Field | Report |
|-------|--------|
| Title | Insufficient Session Expiration / Excessive Session Timeout |
| CWE | [CWE-613](https://cwe.mitre.org/data/definitions/613.html) |
| PoC | MFA email ~16 hours earlier; user still in `/grade/report/grader/` |

Idle timeout was still Moodle’s **8 hours**, and activity (or a long-lived tab) kept the session past **16 hours**.

## Fix (theme ≥ `2024101073`)

| Control | Value |
|---------|--------|
| Idle (`$CFG->sessiontimeout`) | **30 minutes** (1800 s) |
| Warning | **5 minutes** before idle expiry |
| Absolute from login (`currentlogin`) | **8 hours** — logged out even if the tab stays active |

Forced in `session_security::force_idle_timeout()` (after_config + `set_config` so `M.cfg` matches) and `enforce_session_limits()` (before headers).

Set `MOODLE_ALLOW_LONG_SESSION=1` only for local debugging.

## Verify

```bash
# Logged-in page source
grep -oE '"sessiontimeout":"[0-9]+"' 
# Expect: "sessiontimeout":"1800"
```

After 30 minutes with no requests → login. After 8 hours from login, even with clicks → login.

Related: [concurrent-sessions.md](concurrent-sessions.md), [password-change-sessions.md](password-change-sessions.md).
