# Missing Account Lockout Mechanism — temporary lock

## Finding

> Lock the account temporarily

## Resolution

Moodle core already implements account lockout (`login_is_lockedout()` /
`login_attempt_failed()` in `lib/authlib.php`). It was disabled by default
(`lockoutthreshold = 0`). This project now **forces temporary lockout** on.

### Settings (all environments via `config.php`)

| Setting | Value | Meaning |
|---------|-------|---------|
| `lockoutthreshold` | **5** | Lock after 5 failed password attempts |
| `lockoutwindow` | **1800 s** (30 min) | Failed attempts counted within this window |
| `lockoutduration` | **1800 s** (30 min) | Account auto-unlocks after this temporary lock |
| `displayloginfailures` | **1** | Show failure count to the user after a successful login |

Locked users see Moodle’s “account locked” message and receive the core unlock
email (with unlock URL) when the threshold is hit.

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
(values appear forced/overridden when set in `config.php`).

### Unlock paths

1. Wait for `lockoutduration` (temporary lock expires)
2. Use the unlock link in the lockout email
3. Admin: **Users → Browse list of users** → unlock

### Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
# optional verify:
php theme/iiidem2/cli/enable_account_lockout.php
```

### Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Temporary lock | `lockoutduration = 30 minutes` (not permanent) |
| Threshold | 5 failed logins within 30-minute observation window |
| Server-side | Core `authenticate_user_login()` + user preferences `login_lockout` |
| Forced config | `$CFG->lockoutthreshold` etc. in `config.php` before `setup.php` |
