# Server / technology / OS version disclosure

## Findings (CDAC)

| # | Title | Same control family |
|---|-------|---------------------|
| 14 | Server / Technology Version Disclosure (`Server`, `X-Powered-By`) | This doc |
| **41** | **Operating System Version Disclosure** (nmap → Linux 4.x) | This doc |

## Finding

| Field | Report (#14 / #41) |
|-------|--------|
| Title | Server / Technology / OS Version Disclosure |
| Impact | MEDIUM / CVSS 5.3 |
| URL | `https://staginglms.eci.gov.in/`, `/login/index.php` |
| CWE | [CWE-200](https://cwe.mitre.org/data/definitions/200.html) — Exposure of Sensitive Information |
| OWASP | A05:2025 – Security Misconfiguration |
| CVSS | `CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N` |

> Suppress unnecessary OS, web server, and runtime version information.

### PoC note (#14 — HTTP headers)

Auditor noted **`PHP 8.2.31`** via `X-Powered-By` and called that build EOL. Remediaiton has two parts:

1. **Stop disclosing** version strings in headers (this doc).
2. **Ops:** run a **supported** PHP on staging/production (this project’s DDEV target is **PHP 8.3**). Hiding headers does not replace upgrading EOL PHP.

### Retest (2026-09) — `GET /` → `Server: Apache`

Burp on `https://staginglms.eci.gov.in/` highlights:

```http
HTTP/1.1 200 OK
Server: Apache
```

| Observation | Assessment |
|-------------|------------|
| `Server: Apache` **without** `/2.4.x` or `(Ubuntu)` | **`ServerTokens Prod` effect** — version string already suppressed |
| No `X-Powered-By: PHP/…` in the same capture | App + edge hide working |
| Bare product name `Apache` | Residual banner; Moodle PHP **cannot** reliably remove it (httpd sets it after PHP) |

**App status:** version disclosure remediaiton is in place. Remaining `Server: Apache` is **ops edge** — apply [snippets/apache-hide-versions.conf](snippets/apache-hide-versions.conf) (`ServerTokens Prod` + optional `Header unset Server`). Many auditors accept product-only `Server` after versions are gone; full unset is best-effort.

**Dispute / residual:** CWE-200 for **version** strings is addressed when headers have no `Apache/2.4…` and no `PHP/8.x…`. Generic `Server: Apache` alone is low residual fingerprint, not a Moodle code defect.

### PoC note (#41 — nmap OS fingerprint)

Zenmap / `nmap -T4 -A -v staginglms.eci.gov.in` reported **Operating System: Linux 4.18** (host `164.100.59.10`).

That is **TCP/IP stack fingerprinting** (TTL, window size, TCP options, etc.), **not** an application banner Moodle can turn off. Hiding `Server` / `X-Powered-By` does **not** stop nmap OS guesses.

| Layer | Controllable from this LMS repo? | Action |
|-------|----------------------------------|--------|
| HTTP `Server` / `X-Powered-By` / PHP expose | Partially (app + edge) | Apply sections below |
| nmap OS detection (“Linux 4.18”) | **No** (kernel/network stack) | Ops: keep patched kernel; optional edge filtering; **dispute as residual OS fingerprint**, not an app defect |
| Exact kernel string in HTTP/HTML | Must not appear | Keep `debugdisplay=0`; no phpinfo publicly |

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

Ready-to-copy snippet: [snippets/apache-hide-versions.conf](snippets/apache-hide-versions.conf)

```apache
ServerTokens Prod
ServerSignature Off
# In php.ini:
expose_php = Off
# Optional (mod_headers):
Header unset X-Powered-By
Header always unset X-Powered-By
```

Also deny public `/info.php`, `/phpinfo.php`, `/test.php` (see the same snippet). Moodle admin phpinfo stays at `/admin/phpinfo.php` (authenticated).

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

# Finding #41 — nmap OS guess is residual (not fixed by Moodle). Confirm no OS/kernel string in HTTP:
curl -sI https://staginglms.eci.gov.in/ | grep -iE 'Linux|Ubuntu|Debian|kernel|4\.18' && echo FAIL || echo OK
curl -s https://staginglms.eci.gov.in/login/index.php | grep -iE 'Linux 4\.|kernel' && echo FAIL || echo OK
```

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
# DDEV:
ddev restart
# Staging/production edge: apply apache-hide-versions / nginx server_tokens (ops)
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| No PHP version in headers | `expose_php=Off` + `header_remove` / `fastcgi_hide_header` |
| No detailed Server token | `server_tokens off` / `ServerTokens Prod` (web server) |
| App-layer defense | `security_headers::suppress_version_headers()` |
| Apache edge (ops) | [snippets/apache-hide-versions.conf](snippets/apache-hide-versions.conf) |
| Supported runtime | Ops upgrades off EOL PHP (separate from header hide) |
| #41 nmap “Linux 4.18” | **Residual OS fingerprint** — not an LMS code defect; dispute or accept as network-stack residual after HTTP banners are cleaned |

## Related

- [directory-listing.md](directory-listing.md)
- [security-headers.md](security-headers.md)
- [verbose-error-messages.md](verbose-error-messages.md) (no paths/SQL in errors)