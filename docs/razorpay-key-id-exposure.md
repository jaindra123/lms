# Razorpay Key ID Exposure

## Finding

| Field | Report |
|-------|--------|
| Title | Razorpay Key ID Exposure |
| Recommendation | Hide sensitive details |
| Instances | (1) LMS AJAX `paygw_razorpay_get_checkout_data` (2) `lumberjack.razorpay.com/v1/track?key_id=` |

## Why it was raised again

Staging still ran **Checkout.js** (`get_checkout_data` returned `keyid` / `orderid`, CSP allowed `checkout.razorpay.com` + `lumberjack.razorpay.com`). That is the capture:

| Request | What Burp showed |
|---------|------------------|
| `POST /lib/ajax/service.php` `paygw_razorpay_get_checkout_data` | `"keyid":"rzp_test_…"`, `"orderid":"order_…"`, amount, brandname |
| `POST lumberjack.razorpay.com/v1/track?key_id=rzp_test_…` | Origin/Referer LMS — Checkout.js analytics |

**Key Secret was never in the browser.** Key ID is Razorpay’s publishable key; with Checkout.js it had to be in the page. LMS no longer uses Checkout.js, so the Key ID must not appear in LMS JSON.

## LMS remediations (`paygw_razorpay` ≥ `2025062925`, `theme_iiidem2` ≥ `2024101082`)

| Control | Behaviour |
|---------|-----------|
| Checkout AJAX | `paygw_razorpay_get_checkout_data` returns **only** `redirecturl`, `mock`, `mockurl`. No `keyid`, `orderid`, amount, name, or email. |
| AJAX strip | `ajax_request_guard::redact_payment_ajax` drops those fields even if an older paygw still emits them. |
| Pay Now | Top-level `location.replace` to a hosted Payment Link. No `new Razorpay({ key })`. |
| CSP | `script-src` / `connect-src` / `frame-src` do **not** include `checkout.razorpay.com`, `cdn.razorpay.com`, `api.razorpay.com`, or `lumberjack.razorpay.com`. |
| Client abort | `websocket_guard.js` blocks LMS-origin `fetch`/iframe to `*.razorpay.com` (including lumberjack). |

Key ID stays **server-only** (Payment Link create + webhook verify). It is not sent to the browser.

Lumberjack `key_id=` with Origin LMS cannot happen once Checkout.js is gone and CSP/connect is `'self'`. A lumberjack hit **after** the address bar is Razorpay’s hosted page is vendor telemetry.

## Retest on LMS

1. `POST /lib/ajax/service.php` → `paygw_razorpay_get_checkout_data` JSON has **no** `keyid`, **no** `orderid`, **no** `username` / `useremail`, **no** Key Secret. Only `redirecturl` (and mock flags).
2. Response `Content-Security-Policy` `connect-src` does **not** list lumberjack / checkout.razorpay.com.
3. Network from the LMS origin: **no** `lumberjack.razorpay.com/v1/track?key_id=`.
4. Pay Now changes the address bar to a Razorpay Payment Link.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Hard-refresh (AMD `gateways_modal`). Staging must run **this** paygw + theme — the Sep 18 capture is the old Checkout.js plugin.

Related: [windows-session-token.md](windows-session-token.md), [sardine-websocket-token.md](sardine-websocket-token.md).
