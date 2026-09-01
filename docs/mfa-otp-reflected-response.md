# 35. OTP Exposed/Reflected in HTTP Response

## Finding

| Field | Report |
|-------|--------|
| Title | OTP Exposed/Reflected in HTTP Response |
| Impact claimed | MEDIUM / CVSS 5.9 |
| URL | `https://staginglms.eci.gov.in/admin/tool/mfa/auth.php` |
| CWE | [CWE-200](https://cwe.mitre.org/data/definitions/200.html) – Exposure of Sensitive Information |
| OWASP | A04:2025 – Cryptographic Failures |

> Claim: After submitting an MFA code, the HTML response re-renders  
> `<input … name="verificationcode" value="239151">`, exposing the submitted OTP in the response body.

## PoC

1. POST wrong `verificationcode=239151` to `/admin/tool/mfa/auth.php`.
2. Response still shows validation error (“Wrong code…”) **and** `value="239151"` on the input.

## Root cause

Moodle MFA uses `\tool_mfa\local\form\verification_field`. Older `toHtml()` cleared `_attributes['value']`, but modern Moodle renders text fields via **Mustache** (`export_for_template`), which still exported the sticky submitted value. So the clear never ran on the auth page.

Validation itself remained server-side (correct code vs DB/email secret). The issue was **reflection of the user-typed attempt**, not leaking the emailed secret to an unauthenticated party. Still worth fixing per auditor recommendation.

## Fix

In `admin/tool/mfa/classes/local/form/verification_field.php`:

- Override `export_for_template()` to force `value` to empty before Mustache render.
- Also `setValue('')` in `toHtml()` / `secure_js()` for non-template paths.

Applies to email OTP, TOTP, SMS, and any factor using `verification_field`.

## Deploy

```bash
# After deploying the patched file:
php admin/cli/purge_caches.php
```

Core file — **re-apply after Moodle upgrades**.

## Retest

1. Log in until MFA email challenge.
2. Enter a wrong 6-digit code; submit.
3. View source / Burp response: `id_verificationcode` must have **`value=""`** (or no value), not the typed digits.
4. Error text (“Wrong code…”) may still appear — that is OK.
5. Correct code still completes MFA.

## Related

- [mfa-email-verification-cleartext.md](mfa-email-verification-cleartext.md) (#33 — POST over HTTPS)
- [mfa-otp-error-handling.md](mfa-otp-error-handling.md) (#34)
- [mfa-privileged-accounts.md](mfa-privileged-accounts.md)
