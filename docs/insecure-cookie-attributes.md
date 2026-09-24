# Insecure Cookie Attributes

## Finding

| Field | Report |
|-------|--------|
| Title | Insecure Cookie Attributes |
| Severity | Low |
| URL | `https://iiidemlms.eci.gov.in` |
| Cookie | `MoodleSession` |

PoC (Application → Cookies): **HttpOnly** and **Secure** already true; **SameSite=Lax**; **Path=/**. Auditors asked for Path, Domain, Secure, and HttpOnly on every session cookie, an explicit **Domain** (or SameSite=Strict), and Path not `/`.

## Fix (`theme_iiidem2` ≥ `2024101100`)

| Attribute | Value | Notes |
|-----------|--------|--------|
| **HttpOnly** | yes | Forced in `config.php` + Set-Cookie patch |
| **Secure** | yes on HTTPS | Forced when `wwwroot` is `https://` |
| **Domain** | wwwroot host on staging/prod (e.g. `iiidemlms.eci.gov.in`). **Not** on DDEV / localhost (public suffix drops the cookie and login redirect-loops) |
| **Path** | wwwroot path | **`/` when the LMS is the site root** — required so `/login`, `/course`, `/my` share the session |
| **SameSite** | `Lax` | Not `Strict`: SSO and Razorpay return are cross-site top-level GETs; Strict would drop `MoodleSession` |

`SameSite=Strict` was the auditor’s alternative to Domain. Domain is set, so Lax stays.

PHP: `config.php` (before bootstrap) + `theme_iiidem2\session_security` patches outgoing `MoodleSession*` / `MoodleID*` / `MFA_TOKEN_*` Set-Cookie headers.

## Path=/

The site is hosted at origin root (`https://iiidemlms.eci.gov.in/`), not a subdirectory. A Path other than `/` would hide the session from most LMS URLs and break login. If wwwroot is ever `/moodle`, Path becomes `/moodle/` automatically.

## Retest

Clear site cookies (or use a private window), then:

```bash
curl -sI 'https://iiidemlms.eci.gov.in/login/index.php' | tr -d '\r' | grep -i set-cookie
```

Expect one `MoodleSession…` line with **all** of:

- `Domain=iiidemlms.eci.gov.in` (host of that site)
- `Path=/`
- `HttpOnly`
- `Secure`
- `SameSite=Lax`

DevTools → Application → Cookies → `MoodleSession`: Domain = host, Path = `/`, HttpOnly ✓, Secure ✓, SameSite = Lax.

Log out and log in once so the browser replaces any old host-only cookie.

## Deploy

Ship `config.php` with the theme (1100). Then:

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Related: [cookie-httponly.md](cookie-httponly.md).
