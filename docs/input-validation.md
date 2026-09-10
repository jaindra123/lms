# 15. Improper Input Validation

## Finding

| Field | Report |
|-------|--------|
| Title | Improper Input Validation |
| Impact | MEDIUM |
| URLs | `/contact-us/?sent=1`, `/register/` (report sometimes cites `staginglms.cci.gov.in` — retest on IIIDEM / `staginglms.eci.gov.in`) |

> Validate all the input fields  
> Validate and sanitize all user input  
> Input returned in response

Custom forms and AJAX/payment endpoints must enforce **type**, **length**, and **allow-list** checks on the server, and must **not** echo raw request data into HTML/JSON in a way that enables XSS.

Related finding #14 (version headers on same report pages): [version-disclosure.md](version-disclosure.md).  
XSS / script injection PoCs on contact + course search: [input-validation-xss.md](input-validation-xss.md).  
Scanner “input returned in response” URL list (#25): [input-returned-in-response.md](input-returned-in-response.md).

## Shared helpers

`theme/iiidem2/classes/input_validation.php`

| Helper | Purpose |
|--------|---------|
| `clean_text()` | Trim + `PARAM_TEXT` + max length |
| `length_ok()` | Min/max length after trim |
| `clean_txn_ref()` / `clean_payment_params()` | Payment callback hygiene |
| `escape_html()` | `s()` for HTML contexts |
| `json_encode_safe()` | `JSON_HEX_TAG\|AMP\|APOS\|QUOT` — safe browser JSON |
| `json_exit()` | JSON response + exit (no raw echo) |

`theme/iiidem2/classes/safe_errors.php` — exceptions never return SQL/paths; messages cleaned with `PARAM_TEXT`.

## Input returned in response — controls

| Control | Detail |
|---------|--------|
| No raw reflection | Register/phone/email checks return **localized strings only**, not the submitted value |
| FAQ search | Query sanitized; response does **not** include the raw `q` |
| Chatbot ask | Name/query cleaned + length-bound; fixed success/error strings |
| JSON encoding | Custom AJAX uses `json_encode_safe()` (hex-escapes `<` `>` `&` quotes) |
| HTML output | Ticket/support UIs use `s()` / `format_string()` / `nl2br(s())` |
| Exceptions | `safe_errors` strips tags + `PARAM_TEXT`; never appends `debuginfo` |

## Coverage by surface

### `/register/` — Registration & OTP

- `register_form.php` — name fields maxlength 100 (client + server); occupation fields capped
- `registration_profile::get_submitted_value()` — form data only (no raw `$_POST`); `PARAM_TEXT` + 255
- `register/send_otp.php` — firstname ≤ 100; safe JSON exit
- `register/verify_otp.php` — OTP `PARAM_ALPHANUM`, max 12
- `register/check_email.php` / `check_phone.php` / OTP — `request_email()`; safe JSON; **no** email/phone echo
- `register/index.php` — scrub XSS markup from POST before redisplay; form `err_xss`

### `/contact-us/` — Contact form

- `theme_iiidem2\form\contact_form` — name ≤ 100, subject ≤ 255, message ≤ 5000 (`PARAM_TEXT`, client + server maxlength)
- `?sent=1` is `PARAM_INT` only (success flag, not reflected user text)
- Support ticket create — category whitelist; subject ≤ 255; message ≤ 5000
- Support admin reply — reply ≤ 5000; status whitelist
- FAQ API `q` — trimmed, max 200; not returned in JSON
- Homepage chatbot ask — name 2–100; query truncated to 2000
- Chatbot admin reply — ≤ 5000

### Teacher / live class / live quiz / payments

See payment docs and prior hardening (`paygw_*` never trust client amount).

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Theme ≥ `2024101027`.

## Verify

1. `/contact-us/` — XSS / oversized name/subject/message → rejected server-side (`err_xss`)  
2. `/register/` — script in names / invalid OTP → rejected; AJAX errors do not echo raw input  
3. Confirm no XSS reflection of submitted strings in HTML/JSON

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Contact form lengths + allow-list | `contact_form.php` + `is_safe_person_name` / `is_safe_plain_line` |
| Register / OTP | `register/*` + person-name / plain-line checks |
| Client defence | HTML `pattern` + `form_input_guard.js` |
| No raw echo | `json_encode_safe` / localized messages only |
| Server-side | Moodle formslib rules + PHP cleaners (not client-only) |

## Auditor notes

- Validation is **server-side**; HTML `maxlength` is defence-in-depth.
- Moodle core forms outside these plugins continue to use Moodle `PARAM_*` / formslib / HTMLPurifier.
