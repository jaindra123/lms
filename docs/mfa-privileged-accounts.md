# Absence of Multi-Factor Authentication

## Finding

> Implement MFA for all privileged accounts.

## Resolution

Moodle core already ships **Multi-factor authentication** (`admin/tool/mfa`). This site enables it and scopes it to privileged users.

### Who must use MFA

| Account type | MFA required? |
|--------------|---------------|
| Site administrators | Yes |
| Manager | Yes |
| Course creator | Yes |
| Teacher / editing teacher | Yes |
| Students / authenticated users | No (role factor grants 100 points automatically) |

### How it works

1. **`factor_role`** (weight 100): privileged roles return **NEUTRAL** (no points). Everyone else gets **PASS** → 100 points → no MFA prompt.
2. **`factor_admin`**: same idea for site admins (defence in depth).
3. **`factor_totp`** (authenticator app) and **`factor_email`** (email OTP): each worth 100 points — privileged users verify with one of these.
4. **`factor_grace`** (7 days, force setup): allows first login while users register a factor; then forces setup.

A user needs **≥ 100 points** from factors in the PASS state to finish login.

### Code / deploy artefacts

| Item | Purpose |
|------|---------|
| `theme/iiidem2/classes/mfa_privileged.php` | Shared enable logic |
| `theme/iiidem2/cli/enable_mfa_privileged.php` | CLI to enable / re-apply |
| Theme upgrade `2024100963` | Applies on `admin/cli/upgrade.php` |

### Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php

# Or run explicitly (e.g. after clone / before upgrade catches up):
php theme/iiidem2/cli/enable_mfa_privileged.php
# Optional: no grace (immediate MFA):
php theme/iiidem2/cli/enable_mfa_privileged.php --grace=0
```

### Admin UI

**Site administration → Plugins → Admin tools → Multi-factor authentication**

Confirm:

- MFA **enabled**
- Factors: Role, Admin, Authenticator (TOTP), Email, Grace
- Role factor roles include Administrator + Manager / Course creator / Teacher

### User setup (privileged)

1. Log in (grace allows first access).
2. Open **Preferences → Multi-factor authentication**  
   (`/admin/tool/mfa/user_preferences.php`)
3. Register an authenticator app (Google Authenticator, Microsoft Authenticator, etc.).
4. Email OTP remains available as a backup.

### Ops notes

- Ensure outbound email works if relying on **Email** factor (`$CFG->noreplyaddress`, SMTP).
- After the grace period, privileged users without a usable factor cannot complete login until an admin resets factors (**Reset factor** under MFA admin tools).
- Do not enable **No setup** factor — it would bypass MFA.

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| MFA available | Core `tool_mfa` |
| Privileged-only enforcement | `factor_role` + `factor_admin` |
| Second factors | TOTP + Email OTP |
| Rollout without lockout | `factor_grace` (7 days) + force setup |
| Automated enable | Theme upgrade + CLI |
