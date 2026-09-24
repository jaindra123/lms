# Weak Password Policy

## Finding

| Field | Report |
|-------|--------|
| Title | Weak Password Policy |
| Impact | HIGH |
| URL | `/register/` (e.g. `https://iiidemlms.eci.gov.in/register/`) |
| CWE | [CWE-521](https://cwe.mitre.org/data/definitions/521.html) — Weak Password Requirements |
| OWASP | A07:2021 – Identification and Authentication Failures |

> The application has implemented weak password policy for the application users.

Auditor capture: password **requirements** were displayed, but the fields accepted `test` / `test` (HTML only required a non-empty value). Policy must be **enforced**, not only printed.

## Fix (theme_iiidem2 2024101096)

| Recommendation | Implementation |
|----------------|----------------|
| ≥ 8 characters, upper, lower, digit, special | Forced `$CFG->passwordpolicy` + `minpassword*` in `config.php`. Core `check_password_policy()` on register, user create, and change-password. |
| Client must not submit `test` | `minlength=8` + `register_password_policy.js` blocks submit until complexity matches. |
| Not the same as user ID | Extra check: password ≠ username, email, or email local-part. |
| Different from previous passwords | `$CFG->passwordreuselimit = 5`. |
| Change at regular intervals | After **90 days** Moodle sets `auth_forcepasswordchange` (preference `theme_iiidem2_pwset`). |
| Force change when policy fails | `$CFG->passwordpolicycheckonlogin = 1` — existing weak passwords must be changed at next login. |

Self-registration already chooses a password that must meet the policy, so a second “first login” change is not forced for new `/register/` accounts. Admin-created accounts still use Moodle’s **Force password change** checkbox.

## Deploy

Copy `config.php`, `register/index.php`, and theme **iiidem2**, then:

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## Verify

On `/register/`:

1. Password requirements still visible.
2. Type `test` / `test` and Create account — form **must not** succeed (HTML minlength and/or “Password must have …” error).
3. A value such as `Test#2026` (8+, upper, lower, digit, special, not equal to the email) is accepted by the policy (other register fields still apply).

```bash
php -r "define('CLI_SCRIPT', true); require 'config.php'; echo 'policy='.\$CFG->passwordpolicy.' len='.\$CFG->minpasswordlength.' logincheck='.\$CFG->passwordpolicycheckonlogin.' reuse='.\$CFG->passwordreuselimit.PHP_EOL;"
```

Expect: `policy=1 len=8 logincheck=1 reuse=5`.
