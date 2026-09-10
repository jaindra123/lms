# Insufficient Rate Limiting on Prefill Data Encryption API

## Finding

| Field | Report |
|-------|--------|
| Title | Insufficient Rate Limiting on Prefill Data Encryption API |
| Recommendation | Implement rate limiting |
| Observation | For each request, new `prefill_data_v1` is generated |
| PoC | Burp Intruder → repeated `200` on encrypt endpoint |

## Verdict: **Dispute — not this LMS**

| Evidence | Meaning |
|----------|---------|
| Host | `api.razorpay.com` |
| Path | `/v1/standard_checkout/checkout/prefill/encrypt` |
| Origin / Referer | `https://api.razorpay.com` |
| Response | Razorpay sets `prefill_data_v1` cookie / JSON |

This endpoint is **Razorpay Checkout’s** prefill encryption API. It is not implemented, hosted, or proxied by IIIDEM Moodle. LMS code cannot add rate limits to `api.razorpay.com`.

Repeated Intruder hits returning `200` + a new `prefill_data_v1` each time reflect **Razorpay’s** throttling policy, not a missing control on `staginglms.eci.gov.in`.

## What the LMS *does* rate-limit

Checkout entry points on **this** application (see [rate-limiting.md](rate-limiting.md), [missing-rate-limiting-api.md](missing-rate-limiting-api.md)):

| LMS control | Limit (approx.) |
|-------------|-----------------|
| `paygw_razorpay_get_checkout_data` | 5 / 10 min per user; 20 / hour per IP |
| `paygw_razorpay_verify_payment` | 20 / 10 min |
| `paygw_razorpay_report_payment_failure` | 5 / 15 min |

That caps how often the LMS creates Razorpay **orders**. It does not (and cannot) throttle Razorpay’s own `/prefill/encrypt` calls after Checkout UI is open.

## Related hardening (already done)

LMS Checkout no longer sends payer **name/email** in `get_checkout_data` and does not use Checkout prefill ([razorpay-key-id-exposure.md](razorpay-key-id-exposure.md), paygw ≥ `2025062918`). That reduces LMS-supplied contact/email into Razorpay’s encrypt API; users may still type details inside Razorpay’s hosted UI, which only Razorpay can rate-limit.

## Evidence for auditors

| Check | Result |
|-------|--------|
| Prefill encrypt implemented in LMS | No |
| PoC host | `api.razorpay.com` (third-party) |
| LMS order/checkout WS rate-limited | Yes |

**Reply:** Out of scope for IIIDEM LMS. Escalate rate limiting on `/v1/standard_checkout/checkout/prefill/encrypt` to Razorpay. No Moodle code change applies for this finding.
