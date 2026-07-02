# IIIDEM Razorpay Payment Gateway — Developer Guide

Complete reference for integrating, testing, and operating the **Razorpay** course-fee payment gateway on the IIIDEM Moodle LMS.

| Item | Value |
|------|-------|
| **Plugin** | `paygw_razorpay` |
| **Location** | `payment/gateway/razorpay/` |
| **Course fee method** | `enrol_fee` (Moodle core) |
| **UI integration** | `theme_iiidem2` |
| **Supported currency** | INR only |
| **Razorpay docs** | [https://razorpay.com/docs/](https://razorpay.com/docs/) |

---

## Table of contents

1. [Architecture overview](#1-architecture-overview)
2. [End-to-end payment flow](#2-end-to-end-payment-flow)
3. [Plugin file reference](#3-plugin-file-reference)
4. [Database tables](#4-database-tables)
5. [Web services](#5-web-services)
6. [Developer setup (from scratch)](#6-developer-setup-from-scratch)
7. [Test mode and mock simulator](#7-test-mode-and-mock-simulator)
8. [Live Razorpay configuration](#8-live-razorpay-configuration)
9. [Theme integration](#9-theme-integration)
10. [Transaction history (admin)](#10-transaction-history-admin)
11. [Security model](#11-security-model)
12. [Build, deploy, and cache](#12-build-deploy-and-cache)
13. [Troubleshooting](#13-troubleshooting)
14. [Comparison with PNB / ICICI](#14-comparison-with-pnb--icici)

---

## 1. Architecture overview

Three Moodle layers work together:

| Layer | Component | Role |
|-------|-----------|------|
| **What is sold** | `enrol_fee` | Defines course fee (e.g. INR 15,000 on course 4) |
| **How money is collected** | `paygw_razorpay` | Creates Razorpay order, opens Checkout, verifies signature, enrols user |
| **Course page UI** | `theme_iiidem2` | Fee card and **Pay with Razorpay** button |

Moodle **core payment** (`core_payment`) links fee enrolment to gateway plugins.

```
Course page (theme_iiidem2)
    → theme_iiidem2/course_payment.js
        → paygw_razorpay/gateways_modal.js
            → paygw_razorpay_get_checkout_data (web service)
                → Razorpay Orders API OR mock.php
                    → Razorpay Checkout popup (live)
                        → paygw_razorpay_verify_payment (web service)
                            → core_payment save_payment + deliver_order
                                → User enrolled in course
```

Unlike PNB/ICICI (redirect POST to bank), Razorpay uses:

1. **Server-side order creation** (Orders API)
2. **Client-side Checkout.js** modal
3. **Server-side signature verification** before enrolment

---

## 2. End-to-end payment flow

### 2.1 Sequence (step by step)

1. **User opens course page**  
   Example: `/course/view.php?id=4`  
   Theme loads fee context via `theme_iiidem2_get_course_fee_payment_context()` in `theme/iiidem2/lib.php`.

2. **Eligibility checks** (all must pass to show payment buttons):
   - User is logged in (not guest)
   - User is a **registered university student** (`iiidem_occupation = student`)
   - User is **not** EMB / working / instructor
   - User does **not** already have an **active fee enrolment** on this course
   - Razorpay gateway is enabled on the linked payment account

3. **User clicks “Pay with Razorpay”**  
   Button in `theme/iiidem2/templates/course/payment_sidebar.mustache`  
   Triggers `theme_iiidem2/course_payment.js` → `paygw_razorpay/gateways_modal`.

4. **Checkout data requested** — web service `paygw_razorpay_get_checkout_data`:
   - Validates login and `fee_access::user_can_pay_course_fee()`
   - Reads amount from `helper::get_payable('enrol_fee', 'fee', itemid)` (**server-side, not browser**)
   - Generates unique `txnref` (receipt id, e.g. `RZP1719...`)
   - Creates Razorpay order via API **or** mock order locally
   - Inserts pending row in `{paygw_razorpay_txn}`
   - Returns: `keyid`, `orderid`, `amount` (paise), `currency`, prefill data, `mock` flag

5. **Checkout opens**:
   - **Mock mode:** browser redirects to `mock.php?orderid=...`
   - **Live/test keys:** loads `https://checkout.razorpay.com/v1/checkout.js` and opens Razorpay popup

6. **User completes payment** in Razorpay Checkout (UPI, card, net banking, wallet).

7. **Client handler** receives:
   - `razorpay_payment_id`
   - `razorpay_order_id`
   - `razorpay_signature`

8. **Verification** — web service `paygw_razorpay_verify_payment`:
   - Loads transaction from `{paygw_razorpay_txn}` by `orderid`
   - Verifies user owns the transaction
   - Verifies HMAC signature: `hash_hmac('sha256', order_id + '|' + payment_id, key_secret)`
   - Calls `helper::save_payment()` and `helper::deliver_order()` → **enrols user**
   - Updates transaction status to `completed`
   - Returns redirect URL with `?razorpaypayment=success`

9. **Browser redirects** to course page with success notification.

### 2.2 Flow diagram

```
┌──────────────┐     ┌─────────────────────┐     ┌──────────────────────────────┐
│ Course page  │────▶│ course_payment.js   │────▶│ get_checkout_data            │
│ Pay Razorpay │     │ gateways_modal.js   │     │ (create order + txn record)  │
└──────────────┘     └─────────────────────┘     └──────────────┬───────────────┘
                                                                  │
                    ┌─────────────────────────────────────────────┴────────────────────────────┐
                    ▼                                                                          ▼
         ┌─────────────────────┐                                              ┌─────────────────────────┐
         │ mock.php (dev/test)   │                                              │ Razorpay Checkout.js    │
         │ Simulate success      │                                              │ (UPI / card / wallet)   │
         └──────────┬────────────┘                                              └────────────┬────────────┘
                    │                                                                       │
                    └─────────────────────────────┬─────────────────────────────────────────┘
                                                  ▼
                                   ┌──────────────────────────────┐
                                   │ verify_payment (web service)   │
                                   │ signature check + enrol user   │
                                   └──────────────────────────────┘
```

---

## 3. Plugin file reference

All paths relative to Moodle root (`/var/www/html/` in DDEV).

```
payment/gateway/razorpay/
├── README.md                           This document
├── version.php                         Plugin version
├── settings.php                        Admin plugin settings
├── transactions.php                    Admin transaction history page
├── mock.php                            Local test simulator (no Razorpay API keys)
│
├── lang/en/paygw_razorpay.php          Language strings
│
├── classes/
│   ├── gateway.php                     Admin config form (Key ID, Key Secret, environment)
│   ├── razorpay_helper.php             Orders API, signature verify, complete transaction
│   ├── fee_access.php                  Who may pay (students only)
│   ├── external/
│   │   ├── get_checkout_data.php       Web service: create order + return checkout data
│   │   └── verify_payment.php          Web service: verify signature + enrol user
│   └── privacy/provider.php            GDPR privacy API
│
├── amd/src/
│   ├── gateways_modal.js               Loads Checkout.js, opens popup, calls verify
│   └── repository.js                   AJAX wrappers for web services
├── amd/build/                          Compiled JS (rebuild after editing src)
│
├── db/
│   ├── install.xml                     Table mdl_paygw_razorpay_txn
│   ├── install.php                     Register plugin in paygw sort order
│   └── services.php                    Web service definitions
│
└── cli/
    └── enable_razorpay_gateway.php       Enable gateway on IIIDEM payment account
```

### Related theme files

```
theme/iiidem2/
├── lib.php
│   ├── theme_iiidem2_get_course_fee_payment_context()   hasrazorpaygateway flag
│   └── theme_iiidem2_get_course_payment_success_context()  razorpaypayment=success
├── templates/course/payment_sidebar.mustache            Pay with Razorpay button
├── amd/src/course_payment.js                          Gateway button click handler
└── classes/registration_profile.php                   Student eligibility rules
```

### Admin registration

```
admin/settings/payment.php              Adds "Razorpay transaction history" menu link
```

---

## 4. Database tables

| Table | Purpose |
|-------|---------|
| `{enrol}` | Fee instance: `enrol='fee'`, `cost`, `currency`, `customint1` = payment account id |
| `{payment_accounts}` | Named account (e.g. "IIIDEM PNB") |
| `{payment_gateways}` | Gateway config JSON: `keyid`, `keysecret`, `brandname`, `environment` |
| `{paygw_razorpay_txn}` | Pending/completed Razorpay transactions |
| `{payments}` | Moodle core payment records (created on success) |
| `{user_enrolments}` | User enrolled after `deliver_order()` |

### `{paygw_razorpay_txn}` columns

| Column | Description |
|--------|-------------|
| `txnref` | Internal receipt reference (e.g. `RZP1719...`) |
| `orderid` | Razorpay order id (`order_...` or `order_mock_...`) |
| `paymentid` | Razorpay payment id after success (`pay_...`) |
| `userid` | Payer |
| `component` | `enrol_fee` for course fees |
| `paymentarea` | `fee` |
| `itemid` | Fee enrol instance id |
| `amount` | Server-calculated amount in rupees (source of truth) |
| `currency` | `INR` |
| `status` | `pending` or `completed` |
| `timecreated` / `timemodified` | Unix timestamps |

---

## 5. Web services

| Service | Class | Purpose |
|---------|-------|---------|
| `paygw_razorpay_get_checkout_data` | `get_checkout_data` | Create order, insert txn, return checkout options |
| `paygw_razorpay_verify_payment` | `verify_payment` | Verify signature, save payment, enrol user |

Registered in `db/services.php`. Both require a logged-in user.

### get_checkout_data — input

| Parameter | Type | Description |
|-----------|------|-------------|
| `component` | string | `enrol_fee` |
| `paymentarea` | string | `fee` |
| `itemid` | int | Fee enrol instance id |
| `description` | string | Display description |

### get_checkout_data — output (key fields)

| Field | Description |
|-------|-------------|
| `keyid` | Razorpay Key ID (public) |
| `orderid` | Razorpay order id |
| `amount` | Amount in **paise** (INR × 100) |
| `currency` | `INR` |
| `mock` | `true` if using local simulator |
| `mockurl` | URL to `mock.php` when `mock=true` |

### verify_payment — input

| Parameter | Description |
|-----------|-------------|
| `orderid` | `razorpay_order_id` from Checkout handler |
| `paymentid` | `razorpay_payment_id` from Checkout handler |
| `signature` | `razorpay_signature` from Checkout handler |

---

## 6. Developer setup (from scratch)

### Prerequisites

- Moodle 4.5+ with `core_payment` and `enrol_fee` enabled
- DDEV or local PHP environment
- Course with fee enrolment configured (see PNB CLI: `setup_course_fee.php`)
- Test user with **student** occupation in IIIDEM registration profile

### Step 1 — Install the plugin

The plugin lives at `payment/gateway/razorpay/`. Run Moodle upgrade:

```bash
ddev exec php admin/cli/upgrade.php --non-interactive
```

This creates `{paygw_razorpay_txn}` and registers the gateway in Moodle.

### Step 2 — Build JavaScript

```bash
ddev exec bash -c "cd /var/www/html && ./node_modules/.bin/grunt rollup --root=payment/gateway/razorpay --force"
```

### Step 3 — Enable gateway on payment account

```bash
ddev exec php payment/gateway/razorpay/cli/enable_razorpay_gateway.php
```

This attaches Razorpay to the **IIIDEM PNB** payment account with mock test keys (`rzp_test_mock`).

Or manually:

**Site administration → Payments → Payment accounts → [your account] → Razorpay → Enable**

### Step 4 — Purge caches

```bash
ddev exec php admin/cli/purge_caches.php
```

### Step 5 — Verify course fee setup

```bash
ddev exec php payment/gateway/pnb/cli/diagnose_course_fee.php
```

Confirm:

- Fee enrol instance exists on course 4
- Cost is INR 15,000 (or your amount)
- `razorpay` appears in available gateways list

### Step 6 — Test payment

1. Log in as a **student** user (not EMB/instructor)
2. Open: `https://iiidem-certification.ddev.site/course/view.php?id=4`
3. Click **Pay with Razorpay**
4. On mock page, click **Complete payment (simulate success)**
5. Confirm enrolment and success message

### Step 7 — Retest same user

Unenrol fee enrolment first:

```bash
ddev exec php payment/gateway/pnb/cli/unenrol_fee_user.php 4 username@example.com
```

---

## 7. Test mode and mock simulator

### When mock mode is used

`razorpay_helper::should_use_mock()` returns `true` when:

- Key ID or Key Secret is empty
- Key contains `CHANGE_ME`
- Environment is `test` **and** Key ID starts with `rzp_test_mock`

In mock mode, no call is made to Razorpay API. User is sent to:

```
/payment/gateway/razorpay/mock.php?orderid=order_mock_RZP...
```

### Mock page actions

| Action | Result |
|--------|--------|
| **Complete payment (simulate success)** | Enrols user, redirects to course with success |
| **Cancel payment (simulate failure)** | Returns to course with error message |

### Mock signature

Mock payments use a local HMAC secret (`razorpay_mock_secret`) verified by `razorpay_helper::verify_mock_signature()`.

### Switching to Razorpay test API keys

1. Create account at [https://dashboard.razorpay.com](https://dashboard.razorpay.com)
2. Go to **Settings → API Keys → Generate Test Key**
3. In Moodle admin, set:
   - **Key ID:** `rzp_test_xxxxxxxx`
   - **Key Secret:** your test secret
   - **Environment:** Test
4. Purge caches
5. Click **Pay with Razorpay** — real Razorpay Checkout popup opens (test mode, no real money)

Use Razorpay test cards: [https://razorpay.com/docs/payments/payments/test-card-details/](https://razorpay.com/docs/payments/payments/test-card-details/)

---

## 8. Live Razorpay configuration

### Production checklist

1. Complete Razorpay KYC and activate live mode on your Razorpay account.
2. Generate **Live API Keys** in Razorpay Dashboard.
3. In Moodle: **Site administration → Payments → Payment accounts → [account] → Razorpay**
4. Set:
   - **Brand name:** `IIIDEM LMS` (shown on checkout)
   - **Key ID:** `rzp_live_xxxxxxxx`
   - **Key Secret:** live secret (keep confidential)
   - **Environment:** **Live**
5. Ensure site uses **HTTPS** in production.
6. Test one small real payment before announcing to students.
7. Disable mock keys (`rzp_test_mock`) on production.

### Admin settings reference

| Setting | Description |
|---------|-------------|
| Brand name | Shown on Razorpay Checkout screen |
| Key ID | Public key from Razorpay Dashboard |
| Key Secret | Private key — never expose to browser |
| Environment | `test` or `live` |
| Surcharge | Optional % added by Moodle core payment helper |

### Settlement

Money settles to the bank account linked in your **Razorpay Dashboard** (not configured in Moodle). Moodle only stores API keys and records transactions in `{paygw_razorpay_txn}`.

---

## 9. Theme integration

The theme does **not** use Moodle’s generic gateway picker modal for course fees. It shows dedicated buttons per gateway.

### payment_sidebar.mustache

```mustache
{{#hasrazorpaygateway}}
<button type="button"
        data-action="theme_iiidem2/triggerCoursePayment"
        data-gateway="razorpay"
        data-component="enrol_fee"
        data-paymentarea="fee"
        data-itemid="{{coursefeeinstanceid}}"
        data-description="{{coursefeedescription}}">
    Pay with Razorpay
</button>
{{/hasrazorpaygateway}}
```

### lib.php context flags

```php
'hasrazorpaygateway' => in_array('razorpay', $gateways, true),
```

Gateway list comes from:

```php
\core_payment\helper::get_available_gateways('enrol_fee', 'fee', $feeinstanceid);
```

### course_payment.js

Dynamically loads `paygw_razorpay/gateways_modal` and calls `process()` — no theme changes needed when adding logic inside the gateway plugin.

### Adding Razorpay to another page

1. Pass `hasrazorpaygateway`, `coursefeeinstanceid`, `coursefeedescription` in template context.
2. Include payment button with `data-gateway="razorpay"`.
3. Require `theme_iiidem2/course_payment` AMD module (or call `paygw_razorpay/gateways_modal` directly).

---

## 10. Transaction history (admin)

**Site administration → Payments → Razorpay transaction history**

Direct URL:

```
/payment/gateway/razorpay/transactions.php
```

Shows: date, user, course, amount, status, transaction ref, Razorpay order ID, Razorpay payment ID.

Cross-links to PNB and ICICI history pages are at the bottom.

Registered in `admin/settings/payment.php` as `paygw_razorpay_transactions`.

---

## 11. Security model

### Source of truth

| Data | Trusted source |
|------|----------------|
| Payment amount | `{enrol}.cost` via `core_payment\helper::get_payable()` |
| Who can pay | `fee_access` + `registration_profile` |
| Transaction amount | `{paygw_razorpay_txn}.amount` (written server-side) |
| Browser display | **Not trusted** |

### Protections

1. **Amount never from browser** — checkout web service only accepts `component`, `paymentarea`, `itemid`, `description`.
2. **Order created server-side** — Razorpay order amount matches server-calculated fee.
3. **Signature verification** — `verify_payment` validates `razorpay_signature` with Key Secret before enrolment.
4. **User ownership** — Transaction `userid` must match logged-in user.
5. **Idempotency** — Completed transactions redirect without double enrolment.
6. **Key Secret** — Stored in `{payment_gateways}.config` JSON; only used server-side.

### Signature algorithm (live)

```php
$expected = hash_hmac('sha256', $orderid . '|' . $paymentid, $keysecret);
hash_equals($expected, $signature);
```

Reference: [Razorpay — Verify payment signature](https://razorpay.com/docs/payments/payment-gateway/web-integration/standard/integration-steps/#step-5-verify-payment-signature)

---

## 12. Build, deploy, and cache

### After editing PHP

```bash
ddev exec php admin/cli/upgrade.php --non-interactive   # if version.php bumped
ddev exec php admin/cli/purge_caches.php
```

### After editing AMD (JS)

```bash
ddev exec bash -c "cd /var/www/html && ./node_modules/.bin/grunt rollup --root=payment/gateway/razorpay --force"
ddev exec php admin/cli/purge_caches.php
```

### After editing theme templates/lang

```bash
ddev exec php admin/cli/purge_caches.php
```

### Version bump

Update `payment/gateway/razorpay/version.php` when releasing changes, then run upgrade.

---

## 13. Troubleshooting

| Symptom | Likely cause | Action |
|---------|--------------|--------|
| No **Pay with Razorpay** button | Gateway not enabled or user ineligible | Run `diagnose_course_fee.php`; check student profile |
| Button does nothing | AMD not built or JS error | Rebuild AMD; check browser Console (F12) |
| `ordercreatefailed` | Invalid API keys | Verify Key ID/Secret in admin; check Razorpay Dashboard |
| `invalidsignature` | Wrong secret or tampered response | Confirm Key Secret matches Dashboard; retry payment |
| Mock page not found | Stale cache or missing order | Purge caches; start payment again from course page |
| Payment hidden for student | Already enrolled via fee | `unenrol_fee_user.php` to retest |
| `Could not load Razorpay checkout` | CDN blocked / no internet | Allow `checkout.razorpay.com` in firewall |
| Transaction not in history | Payment never completed | Check `{paygw_razorpay_txn}` for `pending` rows |

### Useful SQL

```sql
SELECT * FROM mdl_paygw_razorpay_txn ORDER BY timecreated DESC LIMIT 20;
```

### Debug web services

Browser **F12 → Network** → filter `ajax` → inspect:

- `paygw_razorpay_get_checkout_data`
- `paygw_razorpay_verify_payment`

---

## 14. Comparison with PNB / ICICI

| Aspect | PNB / ICICI | Razorpay |
|--------|-------------|----------|
| UX | Full-page redirect to bank | In-page Checkout popup |
| Integration | HTML form POST + return URL | Orders API + Checkout.js + signature |
| Test mode | `mock.php` redirect simulator | `mock.php` or Razorpay test keys |
| Return handler | `return.php` (POST from bank) | `verify_payment` web service (AJAX) |
| Reference fields | `txnref`, `bankref` | `txnref`, `orderid`, `paymentid` |
| Settlement | PNB / ICICI merchant account | Razorpay Dashboard linked bank |

All three gateways can be **enabled on the same payment account**. The course page shows separate buttons for each available gateway.

---

## Quick reference — key entry points

| Action | File |
|--------|------|
| Show fee + Razorpay button | `theme/iiidem2/lib.php` → `theme_iiidem2_get_course_fee_payment_context()` |
| Pay button HTML | `theme/iiidem2/templates/course/payment_sidebar.mustache` |
| Button click handler | `theme/iiidem2/amd/src/course_payment.js` |
| Open Razorpay Checkout | `payment/gateway/razorpay/amd/src/gateways_modal.js` |
| Create order (server) | `payment/gateway/razorpay/classes/external/get_checkout_data.php` |
| Razorpay API + signature | `payment/gateway/razorpay/classes/razorpay_helper.php` |
| Verify + enrol | `payment/gateway/razorpay/classes/external/verify_payment.php` |
| Local test page | `payment/gateway/razorpay/mock.php` |
| Admin transaction list | `payment/gateway/razorpay/transactions.php` |
| Enable gateway CLI | `payment/gateway/razorpay/cli/enable_razorpay_gateway.php` |
| Student eligibility | `payment/gateway/razorpay/classes/fee_access.php` |

---

*Document version: June 2026 — IIIDEM Certification LMS*
