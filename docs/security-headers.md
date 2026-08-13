# 13. Header Issues

## Finding

| Field | Report |
|-------|--------|
| Title | Header Issues |
| Impact | MEDIUM / CVSS 5.4 |
| URL | Site root / login (report cites `staginglms.cci.gov.in` — retest on IIIDEM Moodle / `staginglms.eci.gov.in`) |
| CWE | [CWE-693](https://cwe.mitre.org/data/definitions/693.html) — Protection Mechanism Failure |
| OWASP | A05:2021 – Security Misconfiguration |

> Implement recommended HTTP security headers (CSP, nosniff, XSS filter, Referrer-Policy, ACAO, Clear-Site-Data).

### PoC note

Burp on the homepage showed `Server: Apache/…` and `X-Powered-By: PHP/…` and **did not** list CSP / nosniff / etc. That was pre-hardening (or wrong host / theme not active). After remediaiton, those security headers are present; version banners are suppressed (see [version-disclosure.md](version-disclosure.md)).

## Implementation

Helper: `theme/iiidem2/classes/security_headers.php`  
Sent on every web request via:

- `hook_listener::after_config` (covers AJAX / scripts without `$OUTPUT->header()`)
- `hook_listener::before_http_headers` (full page renders)

Logout Clear-Site-Data via observer on `\core\event\user_loggedout`.

### Headers set

| Header | Value |
|--------|--------|
| `Content-Security-Policy` | Restrictive policy (`default-src 'self'`, `object-src 'none'`, `frame-ancestors 'self'`, …). Allows Moodle AMD inline/`unsafe-eval`, Razorpay checkout hosts, and MathJax CDN (`cdn.jsdelivr.net`, MathJax **3.2.2**). |
| `X-Content-Type-Options` | `nosniff` |
| `X-XSS-Protection` | `1; mode=block` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Access-Control-Allow-Origin` | Site’s own origin from `$CFG->wwwroot` (not `*`) |
| `X-Frame-Options` | `SAMEORIGIN` |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` when wwwroot is HTTPS |
| `Clear-Site-Data` | `"cache", "cookies", "storage", "executionContexts"` **on logout only** |
| Auth page cache | `Cache-Control: no-store…` for logged-in + `/login/*` / AJAX (see [cache-control-sensitive-pages.md](cache-control-sensitive-pages.md)) |
| Version disclosure | Removes `X-Powered-By` / related tech headers (see [version-disclosure.md](version-disclosure.md)) |

### Why ACAO is the site origin (not `*`)

Reflecting `*` (or any `Origin`) on an authenticated LMS enables cross-site data reading. Auditors require the header to be **present**; binding it to the LMS origin satisfies that without opening CORS to the world.

### Why Clear-Site-Data is logout-only

Sending Clear-Site-Data on every response would wipe cookies/storage continuously and break the site. Spec intent is to clear browser data when the user signs out.

### Optional nginx mirror (edge)

```nginx
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header X-Frame-Options "SAMEORIGIN" always;
# CSP is set by Moodle; duplicate carefully if also set here.
```

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Requires active theme **`iiidem2`**.

Verify:

```bash
curl -sI https://YOUR-HOST/login/index.php | grep -iE \
  'content-security-policy|x-content-type-options|x-xss-protection|referrer-policy|access-control-allow-origin|x-powered-by'
# Expect CSP, nosniff, XSS-Protection, Referrer-Policy, ACAO (site origin)
# Expect: no X-Powered-By

# After logout response (or capture logout redirect):
# Expect Clear-Site-Data: "cache", "cookies", "storage", "executionContexts"
```

## Evidence for auditors

| Requirement | Implementation |
|-------------|----------------|
| CSP | `Content-Security-Policy` on all web responses |
| nosniff | `X-Content-Type-Options: nosniff` |
| XSS filter | `X-XSS-Protection: 1; mode=block` |
| Referrer | `strict-origin-when-cross-origin` |
| ACAO | Own wwwroot origin |
| Clear-Site-Data | On logout: cache, cookies, storage, executionContexts |
