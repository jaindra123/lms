# Sensitive information disclosure — Server / technology version headers

## Finding

> Sensitive information disclosure — Server and Technology Version Disclosure  
> Disable version disclosure in HTTP headers.

## What leaks

Typical response headers before hardening:

| Header | Example leak |
|--------|----------------|
| `Server` | `Apache/2.4.58 (Win64)` / `nginx/1.24.0` |
| `X-Powered-By` | `PHP/8.2.12` |

Attackers use these for targeted exploits.

## Fixes in this repo

### 1. PHP / Moodle (application)

| Location | Action |
|----------|--------|
| `config.php` | `ini_set('expose_php', '0')` + `header_remove('X-Powered-By')` early |
| `theme_iiidem2\security_headers::suppress_version_headers()` | Removes `X-Powered-By`, `X-AspNet-*`, `X-Generator`, attempts `Server` remove on every web response |

### 2. DDEV nginx

| File | Action |
|------|--------|
| `.ddev/nginx/hide-versions.conf` | `server_tokens off;` + `fastcgi_hide_header X-Powered-By` (and similar) |
| `.ddev/nginx/moodle-php.conf` | `fastcgi_hide_header X-Powered-By` on PHP location |

Restart DDEV after pull: `ddev restart`

## Required on staging / production (ops)

Application code cannot fully hide the web-server `Server` banner. Apply at the edge:

### nginx

```nginx
http {
    server_tokens off;
}
# Inside each PHP location / upstream:
fastcgi_hide_header X-Powered-By;
proxy_hide_header X-Powered-By;
```

### Apache (XAMPP / httpd)

```apache
ServerTokens Prod
ServerSignature Off
# In php.ini:
expose_php = Off
# Optional (mod_headers):
Header unset X-Powered-By
Header always unset X-Powered-By
```

### PHP-FPM pool / php.ini

```ini
expose_php = Off
```

## PHP `info.php` / `phpinfo()` pages

Audits often cite a public `/info.php` that dumps PHP configuration (CWE-200).

- **Wrong host example:** `https://staginglma.cdac.gov.in/info.php` is **not** this LMS.
- **This Moodle site:** there is no webroot `info.php`. Local checks return **404**.
- Moodle’s built-in viewer is `/admin/phpinfo.php` and requires a logged-in **site administrator** (`admin_externalpage_setup('phpinfo')`).
- Defence in depth: nginx/Apache deny rules block `/info.php`, `/phpinfo.php`, `/test.php` at the document root (see `docs/directory-listing.md`).

On staging/production, also delete any leftover `info.php` if ops created one outside the repo:

```bash
# Expect 403 or 404 — never a phpinfo HTML table
curl -sI https://staginglms.eci.gov.in/info.php
curl -sI https://staginglms.eci.gov.in/phpinfo.php
```

## Verify

```bash
curl -I https://staginglms.eci.gov.in/login/index.php | grep -iE '^(Server|X-Powered-By|X-AspNet|X-Generator):'
# Expect: no PHP version; Server absent or generic (e.g. "nginx" without version)
```

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
# DDEV:
ddev restart
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| No PHP version | `expose_php=Off` + `header_remove` / `fastcgi_hide_header` |
| No detailed Server token | `server_tokens off` / `ServerTokens Prod` (web server) |
| App-layer defense | `security_headers::suppress_version_headers()` on all Moodle web responses |
