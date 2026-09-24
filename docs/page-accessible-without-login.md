# Page is accessible without login

## Finding

| Field | Report |
|-------|--------|
| Title | Page is accessible without login |
| LMS URL | `/course/view.php?id=4&razorpaypayment=success` |
| Also cited | `https://api.razorpay.com/v1/checkout/qr_code/…/payment/status?key_id=rzp_test_…` |

Anonymous GET of the LMS success URL returned **HTTP 200** with the public course marketing page and a **Payment successful** modal, while the header still showed **Sign up / Log in**.

The Razorpay QR `payment/status` URL is **Razorpay’s host**, not the LMS. Pay Now on this LMS is a hosted Payment Link; that QR status page is not an LMS route.

## Fix (`theme_iiidem2` ≥ `2024101087`)

| Control | Behaviour |
|---------|-----------|
| `?razorpaypayment=success` (also `pnbpayment` / `icicipayment`) | **`require_login()`** — guests are sent to `/login` (no guest autologin) |
| Public course browse | Never used for a payment-success query; never shows the success modal |
| Success modal | Only for a **logged-in** user with an **active fee enrolment** |
| `return.php` | Already `require_login()` before completing enrolment |
| LMS `/v1/checkout/qr_code` | **404** (this origin does not host Razorpay Checkout) |

The catalogue URL `/course/view.php?id=4` **without** the success flag remains the public marketing page (Login / Pay now). That is not a payment-result page.

## Retest

```bash
# Expect: 303/302 to /login (not 200 with "Payment successful")
curl -sI 'https://staginglms.eci.gov.in/course/view.php?id=4&razorpaypayment=success'
```

1. Logged-out browser: open the success URL → **Log in**, not the course hero + success modal.
2. After a real payment while logged in: return.php → course page with the modal only if enrolment exists.
3. `GET https://<lms>/v1/checkout/qr_code/…` → **404**. A capture whose host is `api.razorpay.com` is Razorpay’s site.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```
