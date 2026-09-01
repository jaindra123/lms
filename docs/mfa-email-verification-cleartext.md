# 33. Email Verification Code Exposed in Cleartext — **Mostly false positive / already mitigated**

## Finding

| Field | Report |
|-------|--------|
| Title | Email Verification Code Exposed in Cleartext |
| Impact claimed | MEDIUM / CVSS 5.9 |
| URL | `https://staginglms.eci.gov.in/admin/tool/mfa/auth.php` |
| CWE | [CWE-312](https://cwe.mitre.org/data/definitions/312.html) – Cleartext Storage of Sensitive Information |
| OWASP | A04 – Cryptographic Failures |
| CVSS | `CVSS:3.1/AV:N/AC:H/PR:N/UI:R/S:U/C:H/I:N/A:N` |

> Claim: email MFA verification code is transmitted/stored/displayed in cleartext so an interceptor can complete MFA.

## PoC (auditor)

`POST /admin/tool/mfa/auth.php` over **HTTPS** with form fields including:

```text
factor=email&…&verificationcode=123456&submitbutton=Continue
```

Burp (or similar) shows `verificationcode=…` in the **decrypted** request body. Response is the normal MFA HTML page (HTTP 200).

## Why this is not a Moodle defect as claimed

### 1. Expected MFA UX

The user receives a one-time code by email, types it into the MFA form, and the browser **POSTs** that value to the server for validation. Seeing `verificationcode` in the POST body of a successful (or attempted) submit is **how email MFA works**, not an accidental leak into a URL, JS bundle, or error page.

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

Interception proxies **terminate TLS** and display plaintext HTTP for the tester. That is **not** “cleartext on the wire” to a network eavesdropper. Same analysis as login passwords: [https-sensitive-data.md](https-sensitive-data.md).

### 3. PoC does not show the listed bad exposures

| Exposure type | In this PoC? |
|---------------|--------------|
| Code in **GET URL** / query string | No — POST body only |
| Code echoed in **HTTP response** HTML/JS | No evidence in report |
| Code in **client-side storage** | No |
| Cleartext **HTTP** (no TLS) | No — URL is `https://` |

CWE-312 is primarily about **storage** without protection. The PoC evidence is **form submission over TLS**, which is CWE-319-adjacent “sensitive data in transit” — and TLS already addresses that.

### 4. Server-side handling (Moodle core)

- Code is a short-lived 6-digit secret emailed to the user.
- Validated server-side; wrong codes increment lock / sleep (anti-brute-force).
- After success, email factor instance rows (except the base label) are **deleted** (`post_pass_state()`).

Hashing at rest would be a **Moodle core** change to `tool_mfa` / `factor_email`, not a site theme fix. Do not fork core for this finding alone.

## Verdict for auditors

| Claim | Result |
|-------|--------|
| Code visible in Burp POST body | **Expected** — user-submitted MFA field |
| Cleartext on the network | **No** — HTTPS encrypts in transit |
| Recommendation (HTTPS only; not in URL/response/logs/storage) | **Met** for the cited PoC on HTTPS MFA auth |
| Application code change required | **None** for this evidence |

**Dispute or downgrade finding #33** as a false positive / informational observation of normal MFA over TLS. Retest: confirm MFA only on `https://`, no `verificationcode` in Location/Referer query strings, and production logging does not print MFA POST bodies.

## Ops reminders (already documented)

- Force HTTPS site-wide: [https-sensitive-data.md](https-sensitive-data.md)
- Prefer stronger MFA for privileged accounts (TOTP / WebAuthn) where policy requires: [mfa-privileged-accounts.md](mfa-privileged-accounts.md)
- Ensure mail relay uses TLS to the mailbox provider (email channel is separate from the web PoC)

## Related

- [https-sensitive-data.md](https-sensitive-data.md) — passwords/PII in HTTPS POST bodies vs Burp
- [mfa-privileged-accounts.md](mfa-privileged-accounts.md)
