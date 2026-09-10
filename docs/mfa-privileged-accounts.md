# 21. Absence of Multi-Factor Authentication

## Finding

| Field | Report |
|-------|--------|
| Title | 21. Absence of Multi-Factor Authentication |
| Impact | MEDIUM / CVSS 5.3 |
| URL | `https://staginglms.eci.gov.in/login/index.php` |
| CWE | [CWE-308](https://cwe.mitre.org/data/definitions/308.html) — Use of Single-Factor Authentication |
| OWASP | A07:2021 – Identification and Authentication Failures |
| CVSS vector | `AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N` |

> MFA is missing on student login and is implemented for admin login. Implement MFA for users.

### PoC note

`/login/index.php` shows username/password only (expected). After password, **all** users — including students — are challenged by Moodle **tool_mfa** (TOTP / email OTP) before the session is fully usable. Site admins already hit MFA; students previously skipped it via `factor_role` / `factor_admin` PASS.

## Resolution (theme ≥ `2024101040`)

### Who must use MFA

| Account type | MFA required? |
|--------------|---------------|
| Site administrators | Yes |
| Manager / course creator / teacher | Yes |
| **Students / authenticated users** | **Yes** |

### How it works

1. **`factor_role` / `factor_admin` disabled** — those factors previously gave students an automatic PASS (100 points) and skipped the challenge.
2. **`factor_totp`** (authenticator app) and **`factor_email`** (email OTP): each worth 100 points — users verify with one of these.
3. **`factor_grace`** (7 days, force setup): allows first login while users register a factor; then forces setup.

A user needs **≥ 100 points** from factors in the PASS state to finish login.

### Code / deploy artefacts

| Item | Purpose |
|------|---------|
| `theme/iiidem2/classes/mfa_privileged.php` | Enable logic (all users) |
| `theme/iiidem2/cli/enable_mfa_privileged.php` | CLI to enable / re-apply |
| Theme upgrade `2024101040` | Applies on `admin/cli/upgrade.php` |

### Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php

# Or run explicitly:
php theme/iiidem2/cli/enable_mfa_privileged.php
# Optional: no grace (immediate MFA):
php theme/iiidem2/cli/enable_mfa_privileged.php --grace=0
```

Ensure **outbound email** works if users rely on Email OTP (`$CFG->noreplyaddress`, SMTP).

### Admin UI

**Site administration → Plugins → Admin tools → Multi-factor authentication**

Confirm:

- MFA **enabled**
- Factors: Authenticator (TOTP), Email, Grace — **Role** and **Admin** factors **disabled**
- Factor order: `totp,email,grace`

### User setup (all accounts)

1. Log in with password (grace allows first access).
2. Open **Preferences → Multi-factor authentication**  
   (`/admin/tool/mfa/user_preferences.php`)
3. Register an authenticator app (Google Authenticator, Microsoft Authenticator, etc.).
4. Email OTP remains available as a backup.

### Verify

```bash
php -r "define('CLI_SCRIPT', true); require 'config.php';
  echo 'mfa=' . get_config('tool_mfa', 'enabled') . PHP_EOL;
  echo 'order=' . get_config('tool_mfa', 'factor_order') . PHP_EOL;
  echo 'role=' . get_config('factor_role', 'enabled') . PHP_EOL;
  echo 'admin=' . get_config('factor_admin', 'enabled') . PHP_EOL;"
# Expect: mfa=1; order contains totp,email; role=0; admin=0

# As student: after password → /admin/tool/mfa/auth.php (email/TOTP challenge)
# As site admin: same MFA challenge
```

### Ops notes

- After the grace period, users without a usable factor cannot complete login until an admin resets factors (**Reset factor** under MFA admin tools).
- Do not enable **No setup** factor — it would bypass MFA.
- Do not re-enable **Role** / **Admin** factors unless you intentionally want students to skip MFA again.

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| MFA available | Core `tool_mfa` |
| Student + privileged enforcement | Role/Admin bypass factors **disabled** |
| Second factors | TOTP + Email OTP |
| Rollout without lockout | `factor_grace` (7 days) + force setup |
| Automated enable | Theme upgrade + CLI |

Related: [restrict-admin-access.md](restrict-admin-access.md), [account-lockout.md](account-lockout.md), [mfa-email-verification-cleartext.md](mfa-email-verification-cleartext.md).
