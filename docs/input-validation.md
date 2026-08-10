# Improper Input Validation

## Finding

> Validate all the input fields

Custom forms and AJAX/payment endpoints must enforce **type**, **length**, and **allow-list** checks on the server. Client `maxlength` / HTML5 rules are complementary only.

## Shared helper

`theme/iiidem2/classes/input_validation.php`

| Helper | Purpose |
|--------|---------|
| `clean_text()` | Trim + `PARAM_TEXT` + max length |
| `length_ok()` | Min/max length after trim |
| `clean_txn_ref()` | Payment reference sanitisation |
| `clean_payment_params()` | Flatten/sanitize gateway `GET`/`POST` callbacks |

## Coverage by surface

### Registration & OTP

- `register_form.php` — name fields maxlength 100 (client + server); occupation fields capped
- `registration_profile::get_submitted_value()` — form data only (no raw `$_POST`); `PARAM_TEXT` + 255
- `register/send_otp.php` — firstname ≤ 100
- `register/verify_otp.php` — OTP `PARAM_ALPHANUM`, max 12

### Contact / support / chatbot

- Contact form — name ≤ 100, subject ≤ 255, message ≤ 5000
- Support ticket create — category whitelist; subject ≤ 255; message ≤ 5000
- Support admin reply — reply ≤ 5000; status whitelist
- FAQ API `q` — trimmed, max 200
- Homepage chatbot ask — name 2–100; query truncated to 2000
- Chatbot admin reply — ≤ 5000 (`chatbot_admin_action.php` + `theme_iiidem2_chatbot_reply`)

### Teacher / live class / live quiz

- Materials & assignments — title ≤ 255; intro cleaned (`PARAM_CLEANHTML`) + plain-text length cap
- Live class form — summary ≤ 255; description ≤ 5000; duration whitelist; location URL + length
- Live quiz manage — session name ≤ 255; question ≤ 2000; options ≤ 255
- Live quiz submit API — answers JSON ≤ 4096 bytes, ≤ 50 questions, choice index 0–3

### Payments

- PNB / ICICI return — `input_validation::clean_payment_params()` on merged `GET`/`POST`
- Razorpay verify WS — order/payment id ≤ 64 (`PARAM_ALPHANUMEXT`); signature ≤ 128
- Razorpay failure report — orderid ≤ 64; reason ≤ 500
- Razorpay mock — orderid sanitized + length-bound

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## Auditor notes

- Validation is **server-side** on every listed path; HTML `maxlength` is defence-in-depth.
- Payment amounts remain server-authoritative (see `docs/payment-amount-validation.md`); this doc covers string/param hygiene.
- Moodle core forms outside these custom plugins continue to use Moodle’s own `PARAM_*` / formslib rules.
