# 14. Sensitive information disclosure — Server / Technology Version Disclosure

## Finding

| Field | Report |
|-------|--------|
| Title | Sensitive information disclosure — Server and Technology Version Disclosure |
| Impact | MEDIUM / CVSS 5.3 |
| URL | `https://staginglms.eci.gov.in/` (and production LMS hosts) |
| CWE | [CWE-200](https://cwe.mitre.org/data/definitions/200.html) — Exposure of Sensitive Information |
| OWASP | A05:2021 – Security Misconfiguration |

> The application discloses the web server type and PHP version through HTTP response headers (`Server`, `X-Powered-By`).

### PoC note

Auditor noted **`PHP 8.2.31`** via `X-Powered-By` and called that build EOL. Remediaiton has two parts:

1. **Stop disclosing** version strings in headers (this doc).
2. **Ops:** run a **supported** PHP on staging/production (this project’s DDEV target is **PHP 8.3**). Hiding headers does not replace upgrading EOL PHP.

**Related:** `GET /icons/apache_pb.gif` returning `Server: Apache` + the default Powered-By GIF is Apache’s **icons Alias** (directory-listing Instance 2). Block `/icons/` and set `ServerTokens Prod` — see [directory-listing.md](directory-listing.md).

Related finding #13 headers: [security-headers.md](security-headers.md).

## What leaks (before hardening)

| Header | Example leak |
|--------|----------------|
| `Server` | `Apache/2.4.52 (Ubuntu)` / `nginx/1.24.0` |
| `X-Powered-By` | `PHP/8.2.31` |

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

### PHP version (EOL note)

Use a currently supported PHP for Moodle 4.x (e.g. **8.2 still in security support only until end of calendar window, prefer 8.3**). Confirm with:

```bash
php -v
# Should not be an unsupported/EOL build in production
```

## PHP `info.php` / `phpinfo()` pages

- Wrong-host examples (`staginglma.cdac.gov.in/info.php`) are **not** this LMS.
- This site: no public webroot `info.php`; `/admin/phpinfo.php` requires site admin.
- Edge deny rules for `/info.php`, `/phpinfo.php`, `/test.php` — see [directory-listing.md](directory-listing.md).

```bash
curl -sI https://YOUR-HOST/info.php
# Expect 403 or 404
```

## Verify

```bash
curl -sI https://YOUR-HOST/login/index.php | grep -iE '^(Server|X-Powered-By|X-AspNet|X-Generator):'
# Expect: no PHP version; Server absent or generic (e.g. "nginx" / "Apache" without version)
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
| No PHP version in headers | `expose_php=Off` + `header_remove` / `fastcgi_hide_header` |
| No detailed Server token | `server_tokens off` / `ServerTokens Prod` (web server) |
| App-layer defense | `security_headers::suppress_version_headers()` |
| Supported runtime | Ops upgrades off EOL PHP (separate from header hide) |
