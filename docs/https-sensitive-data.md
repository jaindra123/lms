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

### 3. TLS versions and ciphers (LUCKY13 / CBC)

Allow **TLS 1.2+** only (disable TLS 1.0 / 1.1). Prefer **AEAD** suites only (AES-GCM, ChaCha20-Poly1305) — **disable CBC** cipher suites where possible (LUCKY13).

Ready-to-apply snippets and verification: [lucky13-cbc-ciphers.md](lucky13-cbc-ciphers.md), [`docs/snippets/nginx-tls-no-cbc.conf`](snippets/nginx-tls-no-cbc.conf).

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

## Finding #4 — Sensitive Data Exposure / cryptography failures (re-raised)

| Field | Report |
|-------|--------|
| Title | Sensitive Data Exposure due to cryptography failures |
| Impact claimed | HIGH / CVSS 7.5 / CWE-311 |
| URLs | `/login/index.php`, `/register/`, `/register/verify_otp.php`, Razorpay `otp_submit` |

### Verdict: **Dispute as false positive for HTTPS form POSTs**

Burp Suite **decrypts TLS** and shows the HTTP layer. Seeing `password=` or `code=` in the body of an `https://` request is **normal**. Encryption in transit is **TLS**, not hiding fields from a TLS-intercepting proxy.

| Instance | PoC | Reality |
|----------|-----|---------|
| Login | `POST https://…/login/index.php` body `password=Test%401234` | HTTPS form POST — expected |
| Register OTP | `POST https://…/register/verify_otp.php` body `code=123456` | HTTPS form POST — expected |
| Razorpay OTP | `POST https://api.razorpay.com/…/otp_submit/…` | Third-party; out of scope |

**Do not** add client-side JavaScript “encryption” of password/OTP before POST — that is not Moodle/standard auth and does not replace TLS (auditors with Burp still see or break the flow).

### Real controls (already / hardened)

| Control | Status |
|---------|--------|
| Site on HTTPS only (`$CFG->wwwroot`) | Required on staging/prod |
| HTTP → HTTPS redirect (Apache/nginx) | Ops — must verify |
| HSTS (`Strict-Transport-Security`) | Theme `security_headers` + edge |
| Secure + HttpOnly cookies | `config.php` when wwwroot is https |
| Login/register refuse plain HTTP | `theme_iiidem2\https_enforce` (staging/prod) |
| OTP stored hashed (`password_hash`) | `registration_otp` — not cleartext at rest in session |
| OTP rate limits | `send_otp` / `verify_otp` |

### Finding #4 retest (2026-09) — Instances 1–2

| Instance | PoC (Burp over `https://`) | Verdict |
|----------|----------------------------|---------|
| **1** Login password | `POST /login/index.php` body `username=…&password=Test%401234` | **Dispute** — HTTPS form POST; TLS encrypts in transit. Burp MITM decrypts for the tester. |
| **2** Register OTP | `POST /register/verify_otp.php` body `email=…&code=124565&sesskey=…` | **Dispute** — same; OTP must be POSTed for server verify. |

Recommendation “Encrypt the sensitive fields” at the application layer is **incorrect** for standard Moodle login/OTP. Do **not** ship client-side JS crypto for password/OTP.

**Resolved / in place (real crypto controls):**

- Site `wwwroot` is `https://…`
- HTTP → HTTPS redirect + HSTS on staging (verify with curl below)
- `https_enforce` on login/register OTP endpoints (staging/prod)
- Secure + HttpOnly cookies
- Registration OTP hashed at rest (`password_hash`) — not stored as cleartext in session

Burp will **always** still show `password=` / `code=` on HTTPS intercepts. That is **not** a fail if TLS + HSTS + Secure cookies are confirmed.

### Instance 3 — Razorpay OTP

Out of scope — host is `api.razorpay.com`. See earlier section.

### Retest evidence for auditors

```bash
# 1) HTTPS + HSTS
curl -sI https://staginglms.eci.gov.in/login/index.php | grep -iE 'HTTP/|strict-transport'

# 2) No successful clear-text login
curl -sI http://staginglms.eci.gov.in/login/index.php | head -5
# Expect 301/302 to https://

# 3) Cookies after login (browser DevTools)
# MoodleSession: Secure=yes, HttpOnly=yes
```

Burp will **still** show `password=` / `code=` on HTTPS captures — that must not be scored as CWE-311 if TLS is confirmed.

Razorpay `otp_submit` will still show `otp=` in Burp over HTTPS — that is **by design of Razorpay**, not a regression of an earlier LMS fix.

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
| Strong TLS | Qualys SSL Labs A/A+; TLS 1.2+; no CBC suites ([lucky13-cbc-ciphers.md](lucky13-cbc-ciphers.md)) |
| Burp over HTTPS shows form fields | Expected with TLS interception; not cleartext transmission |

## Note

OTP/password-reset emails leave Moodle over SMTP. Prefer TLS for mail (`smtphosts` with SSL/TLS in Moodle outgoing mail settings) so credentials in mail transport are also encrypted.
