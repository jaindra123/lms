# Improper Implementation of Security Response Headers

## Finding

| Field | Report |
|-------|--------|
| Title | Improper Implementation of Security Response Headers |
| Severity | Low |
| URL | `https://iiidemlms.eci.gov.in/` (`GET /`) |

Auditor PoC still showed the **old** production CSP (`script-src` with `'unsafe-inline'`, `'unsafe-eval'`, Razorpay) plus `X-Frame-Options: SAMEORIGIN`. They asked for:

| Header | Auditor ask | This LMS |
|--------|-------------|----------|
| CSP `script-src` | `'self'` only | `'self'` + **nonce** + `'strict-dynamic'` + `'unsafe-eval'` (RequireJS). **No** `'unsafe-inline'`, **no** Razorpay |
| HSTS | `max-age` + `includeSubDomains` | One `max-age=31536000; includeSubDomains; preload` (Apache) |
| `X-XSS-Protection` | `1; mode=block` | Sent (and re-asserted at flush) |
| `X-Frame-Options` | `DENY` | **`DENY`** on `/`, login, register, MFA. **`SAMEORIGIN`** on course/H5P pages (same-origin iframes) |

## Why not `script-src 'self'` alone

Moodle 4.5 AMD/RequireJS still `eval()`s modules. Dropping `'unsafe-eval'` blanks the UI. Inline scripts use a **per-request nonce** instead of `'unsafe-inline'`. `'strict-dynamic'` lets those nonce’d scripts load further same-origin modules.

## Why not `DENY` on every page

H5P, TinyMCE, and the file picker iframe **same-origin** course pages. `DENY` there breaks content. Cross-origin clickjacking is still blocked: `DENY` on the public/auth surfaces, `SAMEORIGIN` + CSP `frame-ancestors 'self'` on the LMS.

## Retest (`GET /`)

```bash
curl -sI https://iiidemlms.eci.gov.in/ | tr -d '\r' | grep -iE \
  'content-security-policy|strict-transport|x-xss-protection|x-frame-options'
```

Expect:

```
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
X-XSS-Protection: 1; mode=block
X-Frame-Options: DENY
Content-Security-Policy: … frame-ancestors 'none'; script-src 'self' 'nonce-…' 'strict-dynamic' 'unsafe-eval' …
```

`script-src` must **not** contain `'unsafe-inline'`, `checkout.razorpay.com`, or `cdn.razorpay.com`.

Login/register: `X-Frame-Options: DENY`. A course page: `SAMEORIGIN`.

## Deploy

Theme **2024101101**, `.htaccess`, `theme/iiidem2/classes/security_headers.php`. Then upgrade + purge caches.

Related: [security-headers.md](security-headers.md), [duplicate-security-headers.md](duplicate-security-headers.md).
