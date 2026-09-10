# 13. Header Issues

## Finding

| Field | Report |
|-------|--------|
| Title | Header Issues |
| Impact | MEDIUM / CVSS 5.4 |
| URL | Site root / login (`staginglms.eci.gov.in`) |
| CWE | [CWE-693](https://cwe.mitre.org/data/definitions/693.html) — Protection Mechanism Failure |
| OWASP | A05:2021 – Security Misconfiguration |

> Implement CSP, nosniff, XSS filter, Referrer-Policy, ACAO, Clear-Site-Data. Also cited: misconfigured CSP / HSTS / missing Clear-Site-Data.

### PoC note

Older captures showed **no** CSP/nosniff and exposed `X-Powered-By`. Current login responses already send CSP, HSTS, nosniff, XSS-Protection, Referrer-Policy, ACAO.

### Retest (2026-09) — `/login/index.php` DevTools

| Auditor claim | What staging showed | Verdict |
|---------------|---------------------|---------|
| Misconfigured CSP (`'unsafe-inline'` / `'unsafe-eval'`) | Present in `script-src` / `style-src` | **Dispute as “misconfigured”** — required for Moodle AMD/YUI/Mustache; policy still has `default-src 'self'`, `object-src 'none'`, `frame-ancestors 'self'`, host allow-lists, `upgrade-insecure-requests` |
| Misconfigured HSTS (no `preload`) | `max-age=31536000; includeSubDomains` only | **Redeploy / fix edge** — app code already sends `; preload`. Align Apache snippet; remove older HSTS without preload |
| Missing `Clear-Site-Data` on login GET | Absent on `/login/index.php` | **Expected** — header is on **logout** response only. Sending it on login would clear cookies and break sign-in |
| `Server: Apache` | Present | Separate — [version-disclosure.md](version-disclosure.md) |
| `service.php?sesskey=` | Still in Network list | Separate — [session-token-in-url.md](session-token-in-url.md) |

## Implementation

Helper: `theme/iiidem2/classes/security_headers.php`  
Sent via `after_config` / `before_http_headers`. Logout Clear-Site-Data via `\core\event\user_loggedout` **and** explicit headers in `login/logout.php`.

### Headers set

| Header | Value |
|--------|--------|
| `Content-Security-Policy` | `default-src 'self'`; `object-src 'none'`; `frame-ancestors 'self'`; script/style allow Moodle + Razorpay + MathJax CDN; `form-action 'self' https:`; `upgrade-insecure-requests` |
| `X-Content-Type-Options` | `nosniff` |
| `X-XSS-Protection` | `1; mode=block` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Access-Control-Allow-Origin` | Site origin from `$CFG->wwwroot` (not `*`) |
| `X-Frame-Options` | `SAMEORIGIN` |
| `Permissions-Policy` | Restrictive (payment=self) |
| `Cross-Origin-Opener-Policy` | `same-origin-allow-popups` |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains; preload` (HTTPS wwwroot) |
| `Clear-Site-Data` | `"cache", "cookies", "storage", "executionContexts"` **on logout only** |

### Why Clear-Site-Data is logout-only

Sending it on `/login/index.php` (or every response) would wipe cookies/storage and break login. Spec intent = clear browser data when the user **signs out**.

```http
Clear-Site-Data: "cache", "cookies", "storage", "executionContexts"
```

**Retest:** Log in → Log out → capture **`/login/logout.php`** response headers — not the login page that follows.

### Why CSP still has unsafe-inline / unsafe-eval

Moodle core (AMD, Mustache, YUI) does not run without them in this version. The policy still blocks unexpected hosts (`object-src 'none'`, allow-listed CDNs). Nonce/`strict-dynamic` CSP is a Moodle-core migration, not a one-line fix.

### HSTS `preload`

App sends `preload`. If staging still omits it:

1. Redeploy `theme/iiidem2/classes/security_headers.php` + purge caches  
2. Apply [snippets/apache-security-headers.conf](snippets/apache-security-headers.conf) (includes `preload`)  
3. Remove any older edge HSTS line **without** `preload`  

`preload` in the header ≠ enrollment in the Chrome preload list — only keep the directive if ops accepts that commitment.

### Optional Apache mirror

[`docs/snippets/apache-security-headers.conf`](snippets/apache-security-headers.conf). Do **not** duplicate a conflicting CSP at the edge — PHP CSP is source of truth.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
# Ops: apache-security-headers.conf so HSTS includes preload
```

Ship `login/logout.php` for Clear-Site-Data on logout. Theme **`iiidem2`** must be active.

### Verify

```bash
curl -sI https://staginglms.eci.gov.in/login/index.php | grep -iE \
  'content-security-policy|strict-transport|clear-site-data|x-powered-by'

# Expect: CSP present; HSTS with preload; NO Clear-Site-Data on login GET

curl -sI -X POST 'https://staginglms.eci.gov.in/login/logout.php' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -b 'MoodleSession=YOUR_SESSION' \
  --data 'sesskey=YOUR_SESSKEY&loginpage=1'
# Expect: Clear-Site-Data: "cache", "cookies", "storage", "executionContexts"
# Expect: Strict-Transport-Security: ... preload
```

## Evidence for auditors

| Requirement | Implementation |
|-------------|----------------|
| CSP present | Yes — allow-list; Moodle needs limited unsafe-inline/eval |
| nosniff / XSS / Referrer / ACAO | Set |
| HSTS | `max-age=31536000; includeSubDomains; preload` |
| Clear-Site-Data | Logout response only — not login GET |
