# 13. Header Issues

## Finding

| Field | Report |
|-------|--------|
| Title | Header Issues |
| Impact | MEDIUM / CVSS 5.4 |
| URL | Site root / login (`staginglms.eci.gov.in`) |
| CWE | [CWE-693](https://cwe.mitre.org/data/definitions/693.html) — Protection Mechanism Failure |
| OWASP | A05:2021 – Security Misconfiguration |

> Implement CSP, nosniff, XSS filter, Referrer-Policy, ACAO, Clear-Site-Data. Also cited: misconfigured CSP / HSTS / missing Clear-Site-Data. Follow-up: **Cross-Origin Opener Policy Allows Cross-Origin Popups** (`COOP: same-origin-allow-popups`).

Auditor capture (login `/login/index.php`): `script-src` had `'unsafe-inline' 'unsafe-eval'`; HSTS was `max-age=31536000; includeSubDomains` **without** `preload`; no `Clear-Site-Data`. Retest still showed `Cross-Origin-Opener-Policy: same-origin-allow-popups` (that value keeps `window.opener` for cross-origin popups).

## Fix (theme_iiidem2 2024101064)

| Claim | Fix |
|-------|-----|
| Misconfigured CSP (`'unsafe-inline'` on **script-src**) | Per-request **nonce** stamped on every `<script>` / `<style>`. `script-src` uses `'nonce-…' 'strict-dynamic'` — **no `'unsafe-inline'`** on script-src. |
| `'unsafe-eval'` | **Kept** — Moodle RequireJS (`lib/requirejs/require.js`) still `eval()`s AMD modules. Removing it breaks login + the LMS UI. |
| Inline `onclick=` handlers | `script-src-attr 'unsafe-inline'` (separate from script-src) |
| Inline `style=` | `style-src` still allows `'unsafe-inline'` (Moodle templates) |
| HSTS missing `preload` | `.htaccess` sends **one** `max-age=31536000; includeSubDomains; preload` (PHP does **not** emit HSTS — that duplicated Apache). |
| Missing `Clear-Site-Data` on login GET | Login GET sends `Clear-Site-Data: "cache"` (header present; **does not** clear cookies). Logout still sends the full `"cache", "cookies", "storage", "executionContexts"`. |

### Why login Clear-Site-Data is cache-only

`Clear-Site-Data: "cookies"` on `GET /login/index.php` would delete `MoodleSession` and break `logintoken`. Spec intent for cookies/storage is **sign-out**, which is `login/logout.php`.

### Cross-Origin-Opener-Policy (theme_iiidem2 2024101085)

`same-origin-allow-popups` was sent so Razorpay Checkout / Webex could keep `window.opener`. Payments now use a **top-level Payment Link** (`location.replace`), not a checkout popup, so COOP is **`same-origin`**. Cross-origin popups get a null opener (tabnabbing / opener access blocked).

PHP: `theme/iiidem2/classes/security_headers.php`  
Apache: `.htaccess` unsets then sets COOP (overrides a weaker vhost). Snippets: [apache-security-headers.conf](snippets/apache-security-headers.conf), [nginx-coop-same-origin.conf](snippets/nginx-coop-same-origin.conf).

## Implementation

Helper: `theme/iiidem2/classes/security_headers.php`  
Sent via `after_config` / `before_http_headers`. Script nonces via HTML output buffer. Logout Clear-Site-Data via `\core\event\user_loggedout` **and** `login/logout.php`.

### Headers set

| Header | Value |
|--------|--------|
| `Content-Security-Policy` | `default-src 'self'`; `object-src 'none'`; `frame-ancestors 'self'`; **script-src nonce + strict-dynamic + unsafe-eval** (no script unsafe-inline); host allow-lists; `form-action 'self' https:`; `upgrade-insecure-requests` |
| `X-Content-Type-Options` | `nosniff` |
| `X-XSS-Protection` | `1; mode=block` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Access-Control-Allow-Origin` | Site origin from `$CFG->wwwroot` (not `*`) |
| `X-Frame-Options` | `DENY` on `/`, login, register, MFA; `SAMEORIGIN` on course/H5P |
| `Permissions-Policy` | Restrictive (payment=self) |
| `Cross-Origin-Opener-Policy` | `same-origin` (not `same-origin-allow-popups`) |
| `X-UA-Compatible` | **Not sent** (deprecated IE=edge removed) |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains; preload` (**once**, Apache `.htaccess` only) |
| `Clear-Site-Data` | `"cache"` on anonymous login GET; full list **on logout** |

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Theme **iiidem2** must be active. Staging Apache: `.htaccess` unsets both HSTS tables then sets **one** HSTS with `preload`, and COOP `same-origin` (needs `mod_headers`). If a vhost still sends `Cross-Origin-Opener-Policy: same-origin-allow-popups`, replace it with [snippets/apache-security-headers.conf](snippets/apache-security-headers.conf). Do **not** add a second CSP or a second HSTS at the edge. Duplicate HSTS: [duplicate-security-headers.md](duplicate-security-headers.md).

## Verify (after deploy)

```bash
curl -sI https://staginglms.eci.gov.in/login/index.php | grep -iE \
  'content-security-policy|strict-transport|clear-site-data|cross-origin-opener|x-ua-compatible'

# Expect:
# Content-Security-Policy: … script-src 'self' 'nonce-…' 'strict-dynamic' 'unsafe-eval' …
#   (no 'unsafe-inline' on script-src)
# Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
#   (exactly one line — see duplicate-security-headers.md)
# Cross-Origin-Opener-Policy: same-origin
# Clear-Site-Data: "cache"
# (no X-UA-Compatible line)

curl -sI https://staginglms.eci.gov.in/ | grep -ci '^strict-transport-security:'
# Expect: 1

curl -sI -X POST 'https://staginglms.eci.gov.in/login/logout.php' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -b 'MoodleSession=YOUR_SESSION' \
  --data 'sesskey=YOUR_SESSKEY&loginpage=1'
# Expect: Clear-Site-Data: "cache", "cookies", "storage", "executionContexts"
```

Retest login in DevTools: form still submits; password field crypto still runs; no CSP errors for theme scripts.
