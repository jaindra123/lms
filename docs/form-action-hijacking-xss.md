# Form action hijacking (reflected PATH_INFO / XSS)

## Finding

| Field | Report |
|-------|--------|
| Title | Cross-site scripting — form `action` reflects path junk |
| Impact | LOW (CVSS 3.1) |
| CWE | [CWE-79](https://cwe.mitre.org/data/definitions/79.html) |
| OWASP | A03:2021 – Injection |
| CVSS vector | AV:N/AC:L/PR:N/UI:R/S:U/C:L/I:N/A:N |
| Host note | Report host may show `cci.gov.in` — retest on `staginglms.eci.gov.in` |

> The name of an arbitrarily supplied URL parameter (path segment) is copied into the HTML `form action` URL.

## PoC

| Instance | URL | Issue |
|----------|-----|--------|
| 1 | `/login/forgot_password.php/saw5xzmqrrg` | `<form action="/login/forgot_password.php/saw5xzmqrrg" …>` |
| 2 | `/user/files.php` (+ path junk / same mechanism) | Form action hijacking (reflected) |

**Root cause:** Moodle `moodleform` defaults `action` to `strip_querystring($FULLME)`. `$FULLME` includes `PATH_INFO`, so scanner junk after `.php/` is echoed into the form.

## Fix

| Control | Detail |
|---------|--------|
| Theme `after_config` | `hook_listener::neutralize_spurious_php_pathinfo()` |
| Globals | Strip post-`.php` path from `$FULLME`, `$ME`, `$SCRIPT`, `$FULLSCRIPT` |
| Redirect | `302` to the clean `.php` (+ query string) so HTML never contains the probe |
| Allowlist | Real slashargument scripts (`pluginfile.php`, theme assets, etc.) are untouched |

Related “input returned” URL list: [input-returned-in-response.md](input-returned-in-response.md).  
Contact / search XSS: [input-validation-xss.md](input-validation-xss.md).

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Theme ≥ `2024100992`.

## Verify

```bash
# Expect 302 Location without /saw5xzmqrrg
curl -sI 'https://staginglms.eci.gov.in/login/forgot_password.php/saw5xzmqrrg' | head -n 5

# Follow redirect: form action must be clean
curl -sL 'https://staginglms.eci.gov.in/login/forgot_password.php/saw5xzmqrrg' \
  | grep -o 'action="[^"]*forgot_password[^"]*"' | head -n 3
# Expect: action="…/login/forgot_password.php" (no /saw5xzmqrrg)

curl -sI 'https://staginglms.eci.gov.in/user/files.php/saw5xzmqrrg' | head -n 5
```

## Evidence for auditors

| Control | Result |
|---------|--------|
| Path probe | Redirected; not rendered in `form action` |
| `$FULLME` | Sanitized before formslib builds the action |
| File serving | `pluginfile.php` / draft / theme assets still use slasharguments |
