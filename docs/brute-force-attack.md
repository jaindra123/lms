# Brute Force Attack (login CAPTCHA + lockout)

## Finding

| Field | Report |
|-------|--------|
| Title | Brute Force Attack |
| Impact | HIGH |
| URL | `/login/index.php` (e.g. `https://iiidemlms.eci.gov.in/login/index.php`) |
| CWE | [CWE-307](https://cwe.mitre.org/data/definitions/307.html) — Improper Restriction of Excessive Authentication Attempts |
| OWASP | A07:2021 – Identification and Authentication Failures |

> The application does not use any CAPTCHA or account lockout for entering the wrong login credential.

Auditor capture: login form with username/password only; no challenge widget.

## Fix (theme_iiidem2 2024101095)

The recommendation is lockout after **3–5** failed logins, optionally with CAPTCHA. This LMS applies **all three**:

| Control | Implementation |
|--------|----------------|
| Account lockout | **5** failed passwords in **30 minutes** → lock **30 minutes**. Forced in `config.php` (`$CFG->lockoutthreshold`). Core `login_attempt_failed()` / `login_is_lockedout()`. |
| Login CAPTCHA | Visible **Security check** (addition) on `/login/index.php`. Answer is HMAC’d in the session; wrong or missing answer never reaches `authenticate_user_login()`. If Site administration enables Google reCAPTCHA (`enableloginrecaptcha` + keys), that widget is used instead. |
| IP throttle | **10** login POSTs / 5 min and **30** / hour per IP (`theme_iiidem2\rate_limit`), applied in `after_config` **before** password verification. |

Related: [account-lockout.md](account-lockout.md), [rate-limiting.md](rate-limiting.md).

## Deploy

Copy `config.php` plus theme **iiidem2**, then:

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Confirm lockout values:

```bash
php -r "define('CLI_SCRIPT', true); require 'config.php'; echo 'threshold='.\$CFG->lockoutthreshold.' window='.\$CFG->lockoutwindow.' duration='.\$CFG->lockoutduration.PHP_EOL;"
```

## Verify (after deploy)

```bash
curl -sL 'https://staginglms.eci.gov.in/login/index.php' | grep -E 'iiidem_login_captcha|Security check'
```

**Pass**

- Login GET HTML contains `iiidem_login_captcha` and a “Security check: what is … + …?” label
- Submit without solving (or with a wrong number) stays on login with **Security check failed** — no MoodleSession for a user
- After **5** wrong passwords on a **test** account (with a correct CAPTCHA each time), Moodle shows the account-locked message — not endless “Invalid login”

Do not run password-guessing tools against production accounts.
