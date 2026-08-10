# Sensitive data exposure / cryptography failures

## Finding

> Ensure that the sensitive data is never transmitted in clear text.

## What this means

Passwords, session cookies, OTP flows, and personal data must only travel over **HTTPS (TLS)**. HTTP clear text fails the audit.

## Fixes applied in this repo

### Moodle `config.php` (staging / production)

When `wwwroot` is `https://…`:

- `$CFG->cookiesecure = true` — cookies only on HTTPS  
- `$CFG->cookiehttponly = true` — not readable by JavaScript  
- `$CFG->cookiesamesite = 'Lax'`  
- `$CFG->sslproxy = true` — correct HTTPS detection behind a load balancer  

Templates updated:

- `config.staging.php.example`
- `config.production.php.example`

**On the server**, merge these into the real `config.staging.php` / `config.production.php` (those files are not in git):

```php
$CFG->wwwroot = 'https://staginglms.eci.gov.in'; // no http://
$CFG->sslproxy = true;
$CFG->cookiesecure = true;
$CFG->cookiehttponly = true;
$CFG->cookiesamesite = 'Lax';
```

Then:

```bash
php admin/cli/purge_caches.php
```

## Required on the web server (staging)

Moodle alone cannot force TLS at the edge. Configure nginx/Apache:

### 1. Redirect all HTTP → HTTPS

```nginx
server {
    listen 80;
    server_name staginglms.eci.gov.in;
    return 301 https://$host$request_uri;
}
```

### 2. Send HSTS (stops SSL stripping)

Inside the HTTPS `server { }`:

```nginx
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header X-Content-Type-Options nosniff always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy strict-origin-when-cross-origin always;
add_header X-Frame-Options "SAMEORIGIN" always;
# Full CSP + Clear-Site-Data on logout are also set by theme_iiidem2 (see docs/security-headers.md).
```

### 3. TLS versions

Allow **TLS 1.2+** only (disable TLS 1.0 / 1.1).

### 4. Verify

```bash
# Must redirect
curl -I http://staginglms.eci.gov.in/login/index.php
# Expect: 301 Location: https://...

# Must include HSTS
curl -I https://staginglms.eci.gov.in/login/index.php | grep -i strict-transport

# Cookie flags (after login in browser DevTools → Application → Cookies)
# Secure = yes, HttpOnly = yes
```

## Moodle admin UI checks

**Site administration → Security → HTTP security**

- Use HTTPS for logins is obsolete when the whole site is HTTPS (preferred).
- Secure cookies should match config above.

**Site administration → Security → Site policies**

- Password policy enabled.

Optional: **HTTPS conversion tool** (`/admin/tool/httpsreplace/`) to rewrite old `http://` embedded content.

## Scanner PoC analysis (cleartext credentials / PII / OTP)

Typical CDAC-style PoCs show Burp Suite with:

1. **Login POST** body `email=…&password=…`
2. **Registration POST** with name / personal fields  
3. **Razorpay** URL containing `/otp_submit/…` on `api.razorpay.com`

### What this usually means

| PoC | Real issue? |
|-----|-------------|
| Form fields readable in Burp over `https://…` | **No** — Burp terminates TLS and shows the decrypted HTTP. Application-layer forms always send password/PII in the request body; **TLS** is the encryption. This is not “cleartext on the wire” if the URL is HTTPS. |
| Same capture over `http://…` (no TLS) | **Yes** — must redirect HTTP→HTTPS and use HSTS (ops). |
| Host like `staginglma.cci.gov.in` / `staginglma.cdac.gov.in` | **Wrong target** — not this Moodle LMS (`staginglms.eci.gov.in`). |
| `https://api.razorpay.com/v1/payments/…/otp_submit/…` | **Third-party Razorpay API** — OTP path is Razorpay’s checkout flow, not Moodle code. Still HTTPS to Razorpay. Raise with Razorpay only if their docs require a different integration; do not treat as an IIIDEM app defect. |

### Correct auditor evidence for this LMS

1. Login and register URLs are `https://…` only (no successful HTTP login).
2. `Strict-Transport-Security` present.
3. Session cookie has `Secure` + `HttpOnly`.
4. CSP includes `upgrade-insecure-requests` (theme security headers).

Do **not** attempt to “encrypt the password field in JavaScript before POST” as a fix for Burp-visible HTTPS form bodies — that is not standard Moodle auth and does not replace TLS.

## Evidence for auditors

| Control | Evidence |
|--------|----------|
| Site URL HTTPS only | `$CFG->wwwroot` starts with `https://` |
| No clear-text cookies | `Secure` + `HttpOnly` on MoodleSession |
| HTTP blocked | `curl -I http://…` → 301 to HTTPS |
| HSTS | Response header `Strict-Transport-Security` |
| Strong TLS | Qualys SSL Labs A/A+ or internal TLS scan |
| Burp over HTTPS shows form fields | Expected with TLS interception; not cleartext transmission |

## Note

OTP/password-reset emails leave Moodle over SMTP. Prefer TLS for mail (`smtphosts` with SSL/TLS in Moodle outgoing mail settings) so credentials in mail transport are also encrypted.
