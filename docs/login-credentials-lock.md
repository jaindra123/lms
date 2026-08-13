# 27. Copy Paste, Drag and Drop Enabled on Username and Password

## Finding

| Field | Report |
|-------|--------|
| Title | 27. Copy Paste, Drag and Drop Enabled on Username and Password |
| Impact | LOW (CVSS 3.1) |
| URL | `https://staginglms.eci.gov.in/login/index.php` (report may cite other hosts) |
| CWE | [CWE-602](https://cwe.mitre.org/data/definitions/602.html) – Client-Side Enforcement of Server-Side Security |
| OWASP | A05:2021 – Security Misconfiguration |
| CVSS vector | AV:N/AC:L/PR:N/UI:R/S:U/C:L/I:N/A:N |

> The application permits copy/paste and drag-and-drop into authentication fields (username / password).

## PoC

| Instance | Detail | URL |
|----------|--------|-----|
| 1 | Copy / paste enabled on username and password | `/login/index.php` |
| 2 | Drag and drop enabled on password (and username) | `/login/index.php` |

(Report host may show `cci.gov.in` — retest on `staginglms.eci.gov.in`.)

## Fix

| Layer | Detail |
|-------|--------|
| Login template | `theme/iiidem2/templates/core/loginform.mustache` — `autocomplete="off"`, `onpaste` / `ondrop` / `ondragover` return false |
| JS | `theme/iiidem2/javascript/login_credentials_lock.js` — blocks paste, drop, dragover, dragenter, copy, cut |
| Load | `hook_listener::require_login_credentials_lock_js()` on login / change / set / forgot password |

### Pages

- `/login/index.php`
- `/login/change_password.php`
- `/login/set_password.php`
- `/login/forgot_password.php` (username)

### Behaviour

| Control | Implementation |
|---------|----------------|
| Autocomplete | Form + fields `autocomplete="off"` (password may use `new-password` via JS) |
| Paste | Prevented (HTML attribute + JS capture) |
| Drag & drop | `drop` / `dragover` / `dragenter` prevented |
| Copy / cut | Blocked on those fields |

**Note:** Client-side only (expected for this auditor item). Does not replace MFA, lockout, or HTTPS. Some browsers/extensions may still autofill; UI policy is enforced for paste/drop.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Theme ≥ `2024100993`.

## Verify

1. Open `/login/index.php`
2. **Instance 1:** paste into username/password → blocked
3. **Instance 2:** drag text into password (or username) → blocked
4. View source / DevTools → `onpaste="return false;"`, `ondrop="return false;"`, `autocomplete="off"`

## Evidence for auditors

| Control | Result |
|---------|--------|
| Paste disabled | Yes (template + JS) |
| Drag-drop disabled | Yes |
| Autocomplete off | Yes |
| Scope | Login + password change/set/forgot |
