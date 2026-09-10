# Authentication Token Exposed in Client-Side WebSocket (Sardine)

## Finding

| Field | Report |
|-------|--------|
| Title | Authentication Token Exposed in Client-Side WebSocket Communication |
| Service | Sardine fraud/risk (`api.sardine.ai`) |
| Claims | Token in WebSocket; new token on connect; rate-limit token generation; tokens after Moodle logout |

## Verdict: **Dispute — not this LMS**

| Evidence | Meaning |
|----------|---------|
| WebSocket URL | `wss://api.sardine.ai/v1/events/stream` (or HTTPS upgrade to that host) |
| Payload `location` / checkout URL | `https://api.razorpay.com/v1/checkout/public?…` |
| `referrer` | `https://staginglms.eci.gov.in/` (page that opened Checkout) |
| `flow`: `checkout` | Razorpay Checkout fraud SDK |
| Token fields | `deviceToken` / `deviceId` — Sardine **device** identifiers, not Moodle `MoodleSession` / `sesskey` |

This repository has **no** Sardine client, WebSocket code, or `deviceToken` handling (workspace search: zero matches). Razorpay Checkout embeds Sardine for risk scoring. LMS operators cannot rate-limit or change Sardine’s token APIs.

### “Token exposed to the client”

By design, a browser fraud SDK must hold a device/session token in the client to send telemetry. That is not Moodle authentication and does not grant LMS access.

### “New token on connection” / rate limiting

Token minting is performed by **Sardine/Razorpay**. Implement rate limiting there — not in Moodle.

### “Even after logout able to generate new tokens”

Moodle logout clears `MoodleSession` on `staginglms.eci.gov.in`. It does **not** tear down Razorpay Checkout iframes or Sardine device fingerprinting on `api.sardine.ai` / `api.razorpay.com`. Device tokens surviving LMS logout is expected for a third-party device SDK and is **not** session fixation on Moodle.

LMS session controls: [session-fixation.md](session-fixation.md), [session-token-in-url.md](session-token-in-url.md), [cookie-httponly.md](cookie-httponly.md).

## Related third-party payment findings (same Checkout flow)

| Finding | Doc |
|---------|-----|
| Razorpay Key ID / Checkout JSON | [razorpay-key-id-exposure.md](razorpay-key-id-exposure.md) |
| Prefill encrypt rate limit | [razorpay-prefill-rate-limit.md](razorpay-prefill-rate-limit.md) |
| Outdated Sentry on Checkout | [outdated-sentry-sdk.md](outdated-sentry-sdk.md) |

## Evidence for auditors

| Check | Result |
|-------|--------|
| LMS ships Sardine / opens `api.sardine.ai` | No |
| Token is Moodle session id | No — Sardine `deviceToken` |
| LMS can rate-limit Sardine WS | No |

**Reply:** Out of scope for IIIDEM LMS. Escalate to Razorpay/Sardine. No Moodle code change applies.
