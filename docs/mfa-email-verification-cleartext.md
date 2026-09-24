# Email Verification Code Exposed in Cleartext

## Finding

| Field | Report |
|-------|--------|
| Title | Email Verification Code Exposed in Cleartext |
| URL | `POST /admin/tool/mfa/auth.php` |
| CWE | [CWE-312](https://cwe.mitre.org/data/definitions/312.html) |
| Recommendation | Encrypt the sensitive fields |

Burp showed `verificationcode=757814` in the MFA POST body (TLS already encrypts the wire; the auditor decrypts in the proxy).

## Fix

Login password and MFA `verificationcode` are wrapped in the browser with **RSA-OAEP + AES-256-GCM** before POST (`theme_iiidem2` `field_crypto`). The server unwraps to the real OTP. Burp then shows `verificationcode=iiidemenc.…`, not the six digits.

Email MFA still emails a short-lived code (that is how email OTP works). Private IPs are not printed in that email.

## Verify

1. Open `/admin/tool/mfa/auth.php`, submit a code.
2. In DevTools / intercepting proxy, POST body `verificationcode` must start with `iiidemenc.` (not `757814`).
3. Login still succeeds.

Related: [mfa-otp-reflected-response.md](mfa-otp-reflected-response.md), [https-sensitive-data.md](https-sensitive-data.md).
