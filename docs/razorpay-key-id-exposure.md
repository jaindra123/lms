# Razorpay Key ID / sensitive payment details

## Finding

| Field | Report |
|-------|--------|
| Title | Razorpay Key ID Exposure |
| Recommendation | Hide sensitive details |
| Instances | (1) Student receipt IDs (2) `paygw_razorpay_get_checkout_data` JSON + Razorpay hosts |

## Distinction (important)

| Item | Sensitivity | Action |
|------|-------------|--------|
| **Key Secret** | Critical API credential | **Never** in browser / email — already server-only |
| **Key ID** (`rzp_test_…` / `rzp_live_…`) | **Publishable** client key (Razorpay docs). Checkout.js **requires** `key` | Keep for Checkout; **dispute** as secret leak |
| **Order ID** in checkout AJAX | Required as `order_id` for Checkout.js | Keep for Checkout; omit from **student receipts** |
| **Name / email** in checkout AJAX | PII prefill | **Removed** from LMS response (≥ `2025062918`) |
| `lumberjack.razorpay.com?key_id=` | Razorpay analytics | **Out of LMS scope** — third-party |
| `api.razorpay.com` `window.session_token` | Razorpay Checkout session (not Windows / Moodle session) | **Out of LMS scope** — third-party |

Compromise of payments requires the **Key Secret**. Key ID alone cannot capture funds or call privileged Razorpay APIs.

## Instance 2 — LMS `get_checkout_data`

PoC: `POST …/lib/ajax/service.php` → `paygw_razorpay_get_checkout_data` returned `keyid`, `orderid`, `username`, `useremail`.

| Field after fix (`2025062918`) | Still returned? | Why |
|--------------------------------|-----------------|-----|
| `keyid` | Yes | Public Key ID for `new Razorpay({ key })` |
| `orderid` | Yes | Required `order_id` for Checkout |
| `amount` / `currency` / `brandname` | Yes | Checkout display |
| `username` / `useremail` | **No** | PII removed; no Checkout prefill |
| Key Secret | **Never** | — |

`sesskey` on the AJAX URL is finding #17 ([session-token-in-url.md](session-token-in-url.md)), not a Razorpay secret.

## Instance — Razorpay-hosted (dispute)

| Host | What auditors saw | Verdict |
|------|-------------------|---------|
| `lumberjack.razorpay.com` | `key_id=rzp_test_…` in query / body | Razorpay tracking; uses public Key ID |
| `api.razorpay.com/v1/checkout/public` | `window.session_token=…` in HTML/JS | Razorpay Checkout session token — **not** Moodle `MoodleSession`, not a Windows OS token |

LMS cannot strip fields from Razorpay’s own responses.

## Controls (paygw_razorpay ≥ `2025062918`)

### 1. Student receipt — no Razorpay IDs

Success email / PDF: name, email, course, amount, invoice, site payment reference only. No `order_…` / `pay_…` for students. Admins still get gateway IDs.

### 2. Checkout AJAX — no payer PII

`get_checkout_data` no longer returns `username` / `useremail`. Checkout opens without name/email prefill.

### 3. Key Secret never client-side

Orders API + signature verification use secret only on the server.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Hard-refresh browsers after deploy (AMD `gateways_modal` changed).

## Verify

1. Start checkout → Network `paygw_razorpay_get_checkout_data` → **no** `username` / `useremail`; **no** Key Secret  
2. `keyid` / `orderid` may still appear (required public Checkout fields)  
3. Student success email has no Razorpay order/payment IDs  
4. Traffic to `api.razorpay.com` / `lumberjack.razorpay.com` is third-party — dispute for LMS findings  

**Staging recheck (2026-09):**

| Finding | Host / evidence | Verdict |
|---------|-----------------|---------|
| Instance 1–2 Key ID in LMS AJAX | `paygw_razorpay_get_checkout_data` → `keyid` | **Dispute** — public Key ID required by Checkout.js; Key Secret never returned |
| `orderid` in LMS AJAX | Same response | **Dispute** — required for Checkout `order_id` |
| `username` / `useremail` in LMS AJAX | PoC still showed `Ammu` / `maya@cdac.in` | **Fixed in repo** (`≥ 2025062918`); redeploy paygw + purge if staging still returns them |
| lumberjack `key_id` / analytics body | `lumberjack.razorpay.com` | **Dispute** — third-party; public Key ID |
| “Windows Session token” `window.session_token` | `api.razorpay.com/v1/checkout/public` | **Dispute** — Razorpay Checkout session JS; **not** Moodle / Windows OS session |
| Sentry `7.64.0` | `o515678.ingest.sentry.io`, Origin `api.razorpay.com` | **Dispute** — Razorpay’s SDK; not shipped by LMS ([outdated-sentry-sdk.md](outdated-sentry-sdk.md)) |

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| No Key Secret to browser | `get_checkout_data` |
| No name/email in checkout JSON | `get_checkout_data` + `gateways_modal` |
| Student receipts without gateway IDs | `notify_payment_result`, `invoice` |
| Key ID / Razorpay `session_token` on Razorpay hosts | Dispute — product / third-party behaviour |
