# Windows Session token exposed in HTTP response

## Finding

| Field | Report |
|-------|--------|
| Title | Windows Session token exposed in HTTP response |
| Recommendation | Hide sensitive details |
| PoC | `GET /v1/checkout/public` → HTML sets `window.session_token="rzp_test_…"` |

## What the PoC actually is

This is **not** a Windows OS session, and **not** Moodle `MoodleSession`.

| Evidence | Meaning |
|----------|---------|
| Host | `api.razorpay.com` |
| Path | `/v1/checkout/public` |
| `Sec-Fetch-Dest` | `iframe` |
| Referer | LMS origin (`https://staginglms.eci.gov.in/`) |
| Body | Razorpay Checkout JS: `window.session_token="…"` |

Razorpay Standard Checkout loads that iframe and prints a **Checkout session token** in its own HTML. LMS PHP never emits `window.session_token`.

It was raised again because the first reply was a **dispute only**. If Pay Now still used Checkout.js on the LMS page, Burp would keep seeing that iframe with Referer = LMS.

## LMS remediations (`theme_iiidem2` ≥ `2024101081`, `paygw_razorpay` ≥ `2025062924`)

| Control | Behaviour |
|---------|-----------|
| No Checkout.js on LMS | Pay Now is a **top-level** `location.replace` to a hosted Payment Link. LMS AJAX returns only `redirecturl` — no key id, order id, name, email, or session token. |
| CSP | `frame-src` / `connect-src` / `script-src` do **not** allow `api.razorpay.com` or `checkout.razorpay.com`. |
| Client abort | `websocket_guard.js` blocks iframe/`fetch`/XHR to `*.razorpay.com` (including `/v1/checkout/public`), removes Checkout frames, and does not keep `window.session_token` on this origin. |
| LMS-origin path | `GET /v1/checkout/public` on the LMS host → **HTTP 404**. |

After Pay Now the browser **leaves** the LMS. Any remaining `window.session_token` on `api.razorpay.com` is Razorpay’s hosted Checkout page. Only Razorpay can strip that field from **their** HTML.

## Retest on LMS (not on `api.razorpay.com`)

1. LMS Network while logged in on a course/payment page: **no** iframe to `/v1/checkout/public`, **no** `window.session_token` in LMS HTML/JS.
2. `GET https://<lms>/v1/checkout/public` → **404**.
3. Pay Now → top-level navigation to a Razorpay Payment Link URL (address bar changes). Checkout iframe must not appear **on the LMS origin**.

A capture that still shows `Host: api.razorpay.com` and `window.session_token` **after** the address bar is Razorpay’s site. Escalate “hide session_token in Checkout HTML” to Razorpay Support.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

DDEV: `ddev restart`. Staging Tengine: include `docs/snippets/nginx-checkout-public-404.conf`.

Related: [razorpay-key-id-exposure.md](razorpay-key-id-exposure.md), [sardine-websocket-token.md](sardine-websocket-token.md).
