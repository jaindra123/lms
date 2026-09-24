# Insufficient Rate Limiting on Prefill Data Encryption API

## Finding

| Field | Report |
|-------|--------|
| Title | Insufficient Rate Limiting on Prefill Data Encryption API |
| Recommendation | Implement rate limiting |
| Observation | For each request, new `prefill_data_v1` is generated |
| PoC | Burp Intruder → repeated `200` on encrypt endpoint |

## What the PoC hits

| Evidence | Meaning |
|----------|---------|
| Host | `api.razorpay.com` |
| Path | `/v1/standard_checkout/checkout/prefill/encrypt` |
| Origin / Referer | `https://api.razorpay.com` |
| Response | Razorpay sets `prefill_data_v1` cookie / JSON |

That path is **Razorpay Standard Checkout’s** prefill encryption API. IIIDEM LMS does not implement, host, or proxy it. HTTP `429` cannot be attached to `api.razorpay.com` from Moodle PHP.

LMS remediations below stop this origin from feeding that API, cap how often a hosted checkout session can be minted, and return **429** if the encrypt path is requested **on the LMS host**.

## LMS remediations (≥ `paygw_razorpay` `2025062923`, `theme_iiidem2` `2024101079`)

| Control | Behaviour |
|---------|-----------|
| Rate-limit checkout start | `paygw_razorpay_get_checkout_data`: **3 / 10 min per user**, **5 / 10 min per IP**, **8 / hour per IP**. Guests and logged-out sessions are rejected. |
| No PII on Payment Links | `create_payment_link` does **not** send `customer` name/email/contact. Hosted checkout `options.checkout.hidden` **email** and **contact** so the UI is less likely to call prefill-encrypt with LMS-supplied PII. |
| No Checkout.js on LMS origin | Pay Now is `window.location.assign` to a hosted Payment Link only. |
| CSP | `connect-src 'self'` — browser from LMS origin cannot call `api.razorpay.com`. |
| Client abort | `websocket_guard.js` rejects `fetch` / XHR / `sendBeacon` to `*.razorpay.com` paths matching `prefill/encrypt`. |
| LMS-origin path | Edge + PHP: URI containing `prefill/encrypt` → **HTTP 429** (`ratelimit`, `Retry-After: 600`). Nginx: `.ddev/nginx/prefill-encrypt-429.conf` / `docs/snippets/nginx-prefill-encrypt-429.conf`. Apache: `.htaccess` → `theme/iiidem2/prefill_encrypt_deny.php`. |
| Session length | Payment Links `expire_by` 45 minutes. |

Related: [rate-limiting.md](rate-limiting.md), [razorpay-key-id-exposure.md](razorpay-key-id-exposure.md), [sardine-websocket-token.md](sardine-websocket-token.md).

## Retest on LMS (not on `api.razorpay.com`)

1. Burst `paygw_razorpay_get_checkout_data` while logged in → `ratelimited` after 3 attempts / 10 minutes.
2. `POST https://<lms>/v1/standard_checkout/checkout/prefill/encrypt` (or any LMS path containing `prefill/encrypt`) → **429**, no `prefill_data_v1`.
3. From LMS origin, `fetch('https://api.razorpay.com/v1/standard_checkout/checkout/prefill/encrypt')` is blocked by CSP and by `websocket_guard.js`.
4. Start Pay Now → Network shows redirect to a Razorpay Payment Link URL; LMS AJAX has **no** name/email/contact for prefill.

Intruder **25× HTTP 200** against `Host: api.razorpay.com` is Razorpay’s own throttle. Escalate that remaining limit to Razorpay Support. It is not an LMS endpoint.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```
