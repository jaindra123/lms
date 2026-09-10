# Outdated Sentry JavaScript Browser SDK (7.64.0)

## Finding

| Field | Report |
|-------|--------|
| Title | Outdated Sentry JavaScript Browser SDK version (7.64.0) detected |
| Recommendation | Update to latest stable version |
| PoC host | `o515678.ingest.sentry.io` |
| PoC client | `sentry.javascript.browser/7.64.0` |

## Verdict: **Dispute — not this LMS**

Burp shows:

| Evidence | Meaning |
|----------|---------|
| `Origin: https://api.razorpay.com` | Request from **Razorpay Checkout**, not `staginglms.eci.gov.in` |
| `Referer: https://api.razorpay.com/` | Same |
| `Host: o515678.ingest.sentry.io` | Sentry ingest for Razorpay’s project |
| URL `sentry_client=sentry.javascript.browser/7.64.0` | Razorpay’s bundled SDK version |

This repository (**IIIDEM Moodle / theme_iiidem2 / paygw_razorpay**) does **not** ship, load, or configure the Sentry Browser SDK. A workspace search finds no `@sentry`, `sentry.io`, or `7.64.0` client assets under the LMS.

Checkout opens Razorpay’s hosted UI (`api.razorpay.com` / Checkout.js). That third-party page may send its own telemetry to Sentry. LMS operators cannot update Razorpay’s SDK; only Razorpay can.

## Related LMS payment notes

- LMS loads Checkout via `https://checkout.razorpay.com/v1/checkout.js` (Razorpay CDN).
- Public Key ID in Checkout is expected — see [razorpay-key-id-exposure.md](razorpay-key-id-exposure.md).
- No action required on Moodle for this Sentry finding.

## Evidence for auditors

| Check | Result |
|-------|--------|
| LMS packages include Sentry | No |
| Theme / payment AMD loads Sentry | No |
| PoC Origin/Referer | `api.razorpay.com` (third-party) |

**Reply:** Finding is out of scope for IIIDEM LMS. Escalate to Razorpay if a current Checkout SDK is required; no LMS code change applies.
