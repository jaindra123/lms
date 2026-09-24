# Outdated Sentry JavaScript Browser SDK (7.64.0)

## Finding

| Field | Report |
|-------|--------|
| Title | Outdated Sentry JavaScript Browser SDK version (7.64.0) detected |
| Recommendation | Update to latest stable version |
| PoC | `POST …/envelope/?sentry_key=…&sentry_client=sentry.javascript.browser/7.64.0` |

## Why this was raised again

The first LMS reply was a **dispute only**. CDAC retested and still saw Sentry **7.64.0** because Burp is capturing **Razorpay Checkout’s** telemetry, not an LMS package.

This capture (same class as the first):

| Evidence | Meaning |
|----------|---------|
| Host | `o416478.ingest.sentry.io` (earlier PoC used `o515678.ingest.sentry.io`) |
| Path | `/api/4507106608136192/envelope/` |
| Origin / Referer | `https://api.razorpay.com` |
| Body | `"name":"sentry.javascript.browser"`, `"version":"7.64.0"` |
| Response | Sentry ingest `200` + CORS for `ingest.sentry.io` |

This repository **does not ship** `@sentry/browser`, `sentry.io` client config, or version 7.64.0. A workspace search finds no Sentry SDK under Moodle / `theme_iiidem2` / `paygw_razorpay`.

LMS **cannot update** Razorpay’s bundled SDK on `api.razorpay.com`. After Pay Now the browser is on Razorpay’s hosted Payment Link; their page still loads Sentry 7.64.0 until **Razorpay** bumps it.

## LMS remediations (`theme_iiidem2` ≥ `2024101080`)

| Control | Behaviour |
|---------|-----------|
| No Sentry on LMS | Theme / payment AMD do not load Sentry. Pay Now is a top-level redirect to a hosted Payment Link (no Checkout.js on this origin). |
| CSP | `script-src` / `connect-src` are `'self'` (+ MathJax). `browser.sentry-cdn.com` and `*.ingest.sentry.io` are **not** allow-listed. |
| Client abort | `websocket_guard.js` stubs `window.Sentry`, strips Sentry/Razorpay/Sardine tags, and rejects `fetch` / XHR / `sendBeacon` / WebSocket to `*.sentry.io` / `*.sentry-cdn.com` / `/api/{id}/envelope`. |
| LMS-origin envelope | URI `/api/{digits}/envelope` or `sentry_key=` / `sentry_client=` → **HTTP 404**. Nginx: `.ddev/nginx/sentry-envelope-404.conf`. Apache: `.htaccess`. |

## Retest on LMS (not on `api.razorpay.com`)

1. LMS page source / Network: **no** `sentry.javascript.browser`, **no** `ingest.sentry.io`, **no** `7.64.0`.
2. From LMS origin, a request to `…/envelope/?sentry_key=…` or `/api/{id}/envelope` → **404**.
3. CSP `connect-src` does not include `sentry.io`.
4. Pay Now → browser leaves LMS for a Razorpay Payment Link URL (no Checkout.js on LMS).

A capture that still shows **7.64.0** with `Origin: https://api.razorpay.com` is Razorpay’s hosted UI. Escalate “update Sentry Browser SDK” to Razorpay Support.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

DDEV: `ddev restart` so nginx loads `sentry-envelope-404.conf`. Staging Tengine: include `docs/snippets/nginx-sentry-envelope-404.conf`.
