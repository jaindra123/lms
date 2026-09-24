# Authentication Token Exposed in Client-Side WebSocket (Sardine)

## Finding

| Field | Report |
|-------|--------|
| Title | Authentication Token Exposed in Client-Side WebSocket Communication |
| Service | Sardine fraud/risk (`wss://api.sardine.ai/v1/events/stream`) |
| Claims | `deviceToken` in WebSocket; new token on connect; rate-limit token minting; tokens after Moodle logout |

`deviceToken` / `deviceId` are Sardine **device** identifiers, not Moodle `MoodleSession` / `sesskey`. They were loaded by **Razorpay Checkout.js** embedded on the LMS origin. This LMS no longer embeds that SDK.

## Fix (theme_iiidem2 2024101077, paygw_razorpay 2025062922)

| Claim | Control |
|-------|---------|
| Token in LMS-origin WebSocket | CSP `connect-src 'self'` — `api.sardine.ai` / `wss:` to Sardine is **not** allowed. Early `websocket_guard.js` throws `SecurityError` if a script still calls `new WebSocket` to `*.sardine.ai`. |
| Checkout.js / iframe minting tokens on LMS | CSP no longer allow-lists `checkout.razorpay.com`, `cdn.razorpay.com`, `api.razorpay.com`, or `lumberjack.razorpay.com` for script / connect / frame. Pay Now **redirects** to a hosted Payment Link (`window.location.assign`). |
| Rate-limit token-generation | The only LMS request that starts a payment session is `paygw_razorpay_get_checkout_data`: **3 / 10 min per user**, **5 / 10 min per IP**, **8 / hour per IP**. Guests and logged-out sessions are rejected. |
| Tokens after Moodle logout | Checkout WS requires a live Moodle session. Logout POST runs `iiidemCloseRiskSockets()` (close sockets, drop Razorpay/Sardine iframes, drop `deviceToken` sessionStorage) then `Clear-Site-Data` including `executionContexts`. Replay of `get_checkout_data` without `MoodleSession` fails `require_login`. |

Sardine/Razorpay may still mint device tokens **on their own origins** after the browser has left the LMS. That is outside this application. LMS pages must not open `api.sardine.ai`.

## Verify (after deploy)

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php

# CSP must not mention sardine or Razorpay Checkout hosts.
curl -sI https://staginglms.eci.gov.in/my/ | grep -i content-security-policy
```

Retest on the LMS origin:

1. DevTools → Network → WS: no `api.sardine.ai` while browsing the LMS (including Pay Now, which should navigate away).
2. Console: `new WebSocket('wss://api.sardine.ai/v1/events/stream')` → blocked (CSP and/or `SecurityError`).
3. Logout, then call `paygw_razorpay_get_checkout_data` → login/session error, not a new order.
4. Burst `get_checkout_data` while logged in → `ratelimited` after 3 attempts / 10 minutes.

## Related

| Topic | Doc |
|-------|-----|
| Hosted Payment Link (no Checkout.js key id) | [razorpay-key-id-exposure.md](razorpay-key-id-exposure.md) |
| Prefill encrypt on `api.razorpay.com` | [razorpay-prefill-rate-limit.md](razorpay-prefill-rate-limit.md) |
| Sentry 7.64.0 on `api.razorpay.com` | [outdated-sentry-sdk.md](outdated-sentry-sdk.md) |
| Razorpay `window.session_token` | [windows-session-token.md](windows-session-token.md) |
| Logout Clear-Site-Data | [security-headers.md](security-headers.md) |
