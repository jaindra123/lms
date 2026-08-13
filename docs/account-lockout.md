# 9. Missing Account Lockout Mechanism

## Finding

| Field | Report |
|-------|--------|
| Title | Missing Account Lockout Mechanism |
| Impact | HIGH / CVSS 8.1 |
| URL | `/login/index.php` (e.g. `?loginredirect=1`) |
| CWE | [CWE-307](https://cwe.mitre.org/data/definitions/307.html) — Improper Restriction of Excessive Authentication Attempts |
| OWASP | A07:2021 – Identification and Authentication Failures |

> The application does not enforce an account lockout or other protective controls after multiple consecutive failed login attempts.

### PoC note

Auditor screenshot showing **“Invalid login, please try again”** after a failed attempt is **expected for attempts 1–4**. Lockout applies **after 5** failed password attempts within the observation window. Until then Moodle must not reveal whether the username exists beyond the normal invalid-login message.

## Resolution

Moodle core already implements account lockout (`login_is_lockedout()` /
`login_attempt_failed()` in `lib/authlib.php`). It was disabled by default
(`lockoutthreshold = 0`). This project **forces temporary lockout** on.

### Settings (all environments via `config.php`)

| Setting | Value | Meaning |
|---------|-------|---------|
| `lockoutthreshold` | **5** | Lock after 5 failed password attempts |
| `lockoutwindow` | **1800 s** (30 min) | Failed attempts counted within this window |
| `lockoutduration` | **1800 s** (30 min) | Account auto-unlocks after this temporary lock |
| `displayloginfailures` | **1** | Show failure count to the user after a successful login |

Locked users see Moodle’s account-locked message and receive the core unlock
email (with unlock URL) when the threshold is hit.

### Complementary control

IP-based login POST rate limit (20 / 5 min, 60 / hour) via `theme_iiidem2` —
see `docs/rate-limiting.md` / `docs/missing-rate-limiting-api.md`. Lockout is
per **username**; IP throttle slows distributed floods.

### Also applied on theme upgrade

`theme_iiidem2` version `2024100951` writes the same defaults into `mdl_config`
when threshold was previously `0`.

### Manual / CLI

```bash
php theme/iiidem2/cli/enable_account_lockout.php
# or customise:
php theme/iiidem2/cli/enable_account_lockout.php --threshold=5 --window=1800 --duration=1800
```

Admin UI: **Site administration → Security → Site security settings → Account lockout**
(values are overridden when set in `config.php`).

### Unlock paths

1. Wait for `lockoutduration` (temporary lock expires)
2. Use the unlock link in the lockout email
3. Admin: **Users → Browse list of users** → unlock

### Deploy / verify

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
php theme/iiidem2/cli/enable_account_lockout.php
```

**Manual PoC retest** (use a non-production test account):

1. Open `/login/index.php`
2. Submit wrong password **5 times** for a valid username
3. Expect account-locked message (not endless “Invalid login”)
4. Confirm unlock after 30 minutes, unlock email, or admin unlock

### Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Temporary lock | `lockoutduration = 30 minutes` (not permanent) |
| Threshold | 5 failed logins within 30-minute observation window |
| Server-side | Core `authenticate_user_login()` + user preferences `login_lockout` |
| Forced config | `$CFG->lockoutthreshold` etc. in `config.php` before `setup.php` |
| IP flood brake | Login POST rate limit (complements per-account lockout) |
