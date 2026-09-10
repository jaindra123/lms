# 33. Email Verification Code Exposed in Cleartext — **Dispute (TLS already encrypts)**

## Finding

| Field | Report |
|-------|--------|
| Title | Email Verification Code Exposed in Cleartext |
| Impact claimed | MEDIUM / CVSS 5.9 |
| URL | `https://staginglms.eci.gov.in/admin/tool/mfa/auth.php` |
| CWE | [CWE-312](https://cwe.mitre.org/data/definitions/312.html) – Cleartext Storage of Sensitive Information |
| OWASP | A04 – Cryptographic Failures |
| CVSS | `CVSS:3.1/AV:N/AC:H/PR:N/UI:R/S:U/C:H/I:N/A:N` |
| Report recommendation | “Encrypt the sensitive fields” |

> Claim: email MFA verification code is transmitted/stored/displayed in cleartext so an interceptor can complete MFA.

## PoC (auditor)

`POST /admin/tool/mfa/auth.php` over **HTTPS** with form fields including:

```text
factor=email&sesskey=…&verificationcode=810344
```

Burp shows `verificationcode=810344` in the **decrypted** request body. Successful submit → `303` to `/user/preferences.php?…` (HSTS and other security headers present).

## Why “Encrypt the sensitive fields” in the app is the wrong fix

| Approach | Verdict |
|----------|---------|
| **HTTPS/TLS** (already in use) | **Correct** encryption in transit. The entire POST body (including OTP) is encrypted on the wire to a network eavesdropper. |
| Client-side JS “encrypt field before POST” | **Do not implement.** Burp still terminates TLS and can see or break the JS crypto. Not Moodle-standard auth; fragile and false assurance. Same guidance as passwords: [https-sensitive-data.md](https-sensitive-data.md). |

Seeing `verificationcode` in Burp over `https://…` is **expected** for any login/MFA form — identical to `password=` on `/login/index.php`.

## Why this is not a Moodle defect as claimed

### 1. Expected MFA UX

The user receives a one-time code by email, types it into the MFA form, and the browser **POSTs** that value to the server for validation. Seeing `verificationcode` in the POST body is **how email MFA works**, not an accidental leak into a URL, JS bundle, or error page.

```php
// admin/tool/mfa/factor/email/classes/factor.php
$mform->setType('verificationcode', PARAM_ALPHANUM);
// … validate against short-lived DB secret …
```

### 2. Transport is HTTPS/TLS (recommendation already met)

Report recommendation:

> Transmit codes only over secure encrypted channels such as HTTPS/TLS; do not expose in URLs, HTTP responses, logs, browser storage, or client-side code.

| Check | Staging / production |
|-------|----------------------|
| MFA URL scheme | `https://staginglms.eci.gov.in/…` |
| `$CFG->wwwroot` | `https://…` |
| Cookies | `cookiesecure` when wwwroot is HTTPS |
| Behind LB | `$CFG->sslproxy = true` |
| HSTS | Theme `security_headers` + edge |

Interception proxies **terminate TLS** and display plaintext HTTP for the tester. That is **not** “cleartext on the wire” to a network eavesdropper.

### 3. PoC does not show the listed bad exposures

| Exposure type | In this PoC? |
|---------------|--------------|
| Code in **GET URL** / query string | No — POST body only |
| Code echoed in **HTTP response** HTML/JS | No (response is 303 redirect; wrong-code reflection fixed separately) |
| Code in **client-side storage** | No |
| Cleartext **HTTP** (no TLS) | No — URL is `https://` |

CWE-312 is primarily about **storage** without protection. The PoC evidence is **form submission over TLS** (CWE-319-adjacent) — and TLS already addresses that.

### 4. Server-side handling (Moodle core)

- Code is a short-lived 6-digit secret emailed to the user.
- Validated server-side; wrong codes increment lock / sleep (anti-brute-force).
- After success, email factor instance rows (except the base label) are **deleted** (`post_pass_state()`).
- Mustache form field does **not** re-echo submitted OTP (`verification_field::export_for_template`).

## Hardening in this repo (theme ≥ `2024101033`)

Not “JS field encryption” — real controls aligned with the report’s HTTPS recommendation:

| Control | Implementation |
|---------|----------------|
| Refuse HTTP on MFA | `https_enforce` covers `/admin/tool/mfa/` (staging/prod) |
| No OTP in query string | `scrub_mfa_verificationcode_from_query()` strips GET `verificationcode` |
| No-store on MFA HTML | `security_headers::send_sensitive_cache_control` includes `/admin/tool/mfa/` |
| No OTP in error HTML `value=` | `tool_mfa` `verification_field` clears value ([mfa-otp-reflected-response.md](mfa-otp-reflected-response.md)) |

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## Verdict for auditors

| Claim | Result |
|-------|--------|
| Code visible in Burp POST body over HTTPS | **Expected** — user-submitted MFA field after TLS decrypt |
| Cleartext on the network | **No** — HTTPS encrypts in transit |
| “Encrypt the sensitive fields” via app JS | **Reject** — TLS is the encryption; JS crypto is theater |
| Recommendation (HTTPS; not in URL/response/logs) | **Met** + hardened (HTTPS guard, no GET OTP, no-store, no response echo) |

**Dispute or downgrade finding #33** as a false positive / informational observation of normal MFA over TLS.

### Retest evidence

```bash
# HTTPS + HSTS on MFA
curl -sI 'https://staginglms.eci.gov.in/admin/tool/mfa/auth.php' | grep -iE 'HTTP/|strict-transport|cache-control'

# HTTP must not serve MFA (redirect or refuse)
curl -sI 'http://staginglms.eci.gov.in/admin/tool/mfa/auth.php' | head -5
```

Confirm: MFA only on `https://`, no `verificationcode` in Location/Referer query strings, production logging does not print MFA POST bodies.

## Ops reminders

- Force HTTPS site-wide: [https-sensitive-data.md](https-sensitive-data.md)
- Prefer stronger MFA for privileged accounts (TOTP / WebAuthn): [mfa-privileged-accounts.md](mfa-privileged-accounts.md)
- Ensure mail relay uses TLS to the mailbox provider (email channel is separate from the web PoC)

## Related

- [https-sensitive-data.md](https-sensitive-data.md) — passwords/PII in HTTPS POST bodies vs Burp
- [mfa-otp-reflected-response.md](mfa-otp-reflected-response.md) — OTP must not reappear in HTML `value=`
- [mfa-privileged-accounts.md](mfa-privileged-accounts.md)
