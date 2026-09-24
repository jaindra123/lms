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

> Hide server details. Suppress unnecessary OS, web server, and runtime version information.

Auditor capture (`GET /login/`):

```http
Server: Apache
```

No PHP version in that capture (`X-Powered-By` already gone). The remaining banner is Apache’s product name.

## Fix (theme_iiidem2 2024101065 + `.htaccess`)

PHP `header_remove('Server')` cannot stop httpd from adding `Server: Apache` after the script finishes. The webroot **`.htaccess`** (already live on staging for rewrites) now:

| Directive | Purpose |
|-----------|---------|
| `Header always unset Server` (+ `early`) | Drop the `Server` response header |
| `Header always edit Server ^.*$ " "` | If Apache re-adds it, blank the value so it is not `Apache` |
| `Header always unset X-Powered-By` (and AspNet / Generator) | Runtime banners |

Also:

| Location | Action |
|----------|--------|
| `config.php` | `expose_php=0` + `header_remove` before `setup.php` |
| `theme_iiidem2\security_headers::suppress_version_headers()` | Removes `Server`, `X-Powered-By`, generator headers on every web response |
| `.user.ini` | `expose_php = Off` (CGI / PHP-FPM) |
| [snippets/apache-hide-versions.conf](snippets/apache-hide-versions.conf) | `ServerTokens Prod`, `ServerSignature Off`, same Header unset; optional `SecServerSignature` if mod_security is loaded |
| `.ddev/nginx/hide-versions.conf` | `server_tokens off` + `fastcgi_hide_header Server` |

**Ops (if `Server: Apache` remains after deploy):** copy `docs/snippets/apache-hide-versions.conf` into the SSL vhost / `conf.d` and reload httpd. `ServerTokens` cannot live in `.htaccess`.

### nmap OS fingerprint (#41)

Zenmap / `nmap -T4 -A` “Linux 4.18” is **TCP/IP stack fingerprinting**, not an HTTP banner. Hiding `Server` does not stop it. Dispute as residual after HTTP banners are clean.

## Verify (after deploy)

```bash
curl -sI https://staginglms.eci.gov.in/login/index.php | grep -iE '^(Server|X-Powered-By|X-AspNet|X-Generator):'
# Expect: no X-Powered-By; no "Apache/2.4"; no "PHP/". Prefer no Server line at all.
```

DevTools → Network → `login` → Response headers: **`Server` absent** (or not `Apache`).

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
# Staging: ship .htaccess with the app. If Server remains, apply apache-hide-versions.conf on httpd and reload.
```
