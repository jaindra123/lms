# IIIDEM PNB Payment Gateway — Developer Guide

This document describes how course fee payment works on the IIIDEM Moodle site: architecture, file map, test simulator, security, and production setup.

**Plugin:** `paygw_pnb`  
**Location:** `payment/gateway/pnb/`  
**Course fee method:** `enrol_fee` (Moodle core)  
**UI integration:** `theme_iiidem2`

---

## 1. Architecture overview

Three Moodle layers work together:

| Layer | Component | Role |
|-------|-----------|------|
| **What is sold** | `enrol_fee` | Defines course fee (e.g. INR 15,000 on course 4) |
| **How money is collected** | `paygw_pnb` | Redirects to PNB IPG (or local mock), verifies return, completes enrolment |
| **Course page UI** | `theme_iiidem2` | Fee card, “Pay with PNB” button, eligibility rules |

Moodle **core payment** (`core_payment`) connects fee enrolment to gateway plugins.

```
Course page (theme)
    → Payment modal (core_payment)
        → PNB plugin (paygw_pnb)
            → Bank IPG or mock.php
                → return.php
                    → Enrol user (enrol_fee)
```

---

## 2. End-to-end payment flow

### 2.1 Sequence (step by step)

1. **User opens course page**  
   Theme loads fee context from `theme_iiidem2_get_course_fee_payment_context()` in `theme/iiidem2/lib.php`.

2. **Eligibility checks** (must all pass to show Pay button):
   - User is logged in (not guest)
   - User is a **registered university student** (occupation = `student` in profile)
   - User is **not** EMB / working / instructor (no fee required)
   - User does **not** already have an **active fee enrolment** on this course
   - At least one payment gateway is available for the fee instance

3. **User clicks “Pay with PNB”**  
   Button in `theme/iiidem2/templates/course/payment_sidebar.mustache` triggers Moodle’s `core_payment/gateways_modal`.

4. **Payment modal opens**  
   Core JS calls web service `core_payment_get_available_gateways` and shows gateway name, description, and cost.

5. **User clicks Proceed**  
   Core dynamically loads `paygw_pnb/gateways_modal` and calls web service `paygw_pnb_get_redirect_form`.

6. **Server creates transaction** (`classes/external/get_redirect_form.php`):
   - Validates login and `fee_access::user_can_pay_course_fee()`
   - Reads amount from **`helper::get_payable('enrol_fee', 'fee', itemid)`** (database — not from browser)
   - Generates unique `txnref`
   - Inserts pending row in `{paygw_pnb_txn}`
   - Builds redirect form fields + HMAC checksum via `pnb_helper::build_redirect_form()`

7. **Browser POSTs to gateway**  
   JS auto-submits hidden form to:
   - **Test:** `mock.php` (local simulator), or
   - **Live:** PNB Internet Payment Gateway URL

8. **Bank / mock returns to site**  
   POST to `return.php` with `TXNREFNO`, `STATUS`, `AMOUNT`, `CHECKSUM`, etc.

9. **return.php verifies and completes**:
   - Loads transaction from `{paygw_pnb_txn}` by `txnref`
   - Verifies user owns the transaction
   - Verifies checksum (`pnb_helper::verify_return`)
   - Verifies returned amount matches stored amount (`pnb_helper::amounts_match`)
   - Calls `helper::save_payment()` and `helper::deliver_order()` → **enrols user**
   - Updates transaction status to `completed`
   - Redirects to course with success message

### 2.2 Flow diagram

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────────────────┐
│ Course page │────▶│ core_payment     │────▶│ paygw_pnb_get_redirect  │
│ (theme)     │     │ gateways_modal   │     │ _form (web service)     │
└─────────────┘     └──────────────────┘     └───────────┬─────────────┘
                                                           │
                                                           ▼
                                              ┌─────────────────────────┐
                                              │ Insert paygw_pnb_txn    │
                                              │ (pending, server amount)│
                                              └───────────┬─────────────┘
                                                           │
                         ┌─────────────────────────────────┴─────────────────────────────────┐
                         ▼                                                                   ▼
              ┌─────────────────────┐                                             ┌─────────────────────┐
              │ mock.php (test)     │                                             │ PNB IPG (live)      │
              │ Simulate pay/cancel │                                             │ Real bank payment   │
              └──────────┬──────────┘                                             └──────────┬──────────┘
                         │                                                                   │
                         └─────────────────────────────┬─────────────────────────────────────┘
                                                       ▼
                                            ┌─────────────────────┐
                                            │ return.php          │
                                            │ verify + enrol      │
                                            └─────────────────────┘
```

---

## 3. Plugin file reference

All paths relative to Moodle root (`/var/www/html/` in DDEV).

```
payment/gateway/pnb/
├── version.php                         Plugin version metadata
├── settings.php                        Admin settings registration
├── lang/en/paygw_pnb.php               Language strings (gatewayname, errors, mock UI)
│
├── classes/
│   ├── gateway.php                     Admin config form (merchant ID, URLs, environment)
│   ├── pnb_helper.php                  Checksum, redirect form, amount verify, result pages
│   ├── fee_access.php                  Who may pay (students only; links to theme profile)
│   ├── external/
│   │   └── get_redirect_form.php       Web service: start payment, create txn record
│   └── privacy/provider.php            GDPR / privacy API
│
├── amd/src/
│   ├── gateways_modal.js               Entry point called by core_payment on Proceed
│   └── repository.js                   AJAX wrapper for get_redirect_form
├── amd/build/                          Compiled JS (run grunt after editing amd/src)
│
├── db/
│   ├── install.xml                     Table mdl_paygw_pnb_txn
│   ├── install.php                     Register plugin in paygw sort order
│   └── services.php                    Web service definition
│
├── mock.php                            Local test simulator (pretends to be PNB)
├── return.php                          Bank callback handler
├── transactions.php                    Admin transaction history page
│
├── cli/
│   ├── setup_course_fee.php            Create payment account + fee enrolment on a course
│   ├── diagnose_course_fee.php         Debug fee instance and available gateways
│   ├── unenrol_fee_user.php            Remove fee enrolment so user can retest payment
│   └── check_gateway_config.php        Check resolved gateway URL and recent transactions
│
└── docs/
    └── PAYMENT_GATEWAY_DEVELOPER_GUIDE.md   This document
```

### Theme integration (required for UI)

```
theme/iiidem2/
├── lib.php                                    theme_iiidem2_get_course_fee_payment_context()
├── templates/course/payment_sidebar.mustache  Pay with PNB button
├── templates/course/hero.mustache             Fee card for guests (login first)
└── classes/registration_profile.php           Student vs EMB eligibility
```

### Moodle core (not custom)

```
payment/          core_payment — modal, accounts, save_payment, deliver_order
enrol/fee/        Fee enrolment plugin — cost stored on course enrol instance
```

---

## 4. Database tables

| Table | Purpose |
|-------|---------|
| `{enrol}` | Fee instance: `enrol='fee'`, `cost`, `currency`, `customint1`=payment account id |
| `{payment_accounts}` | Named payment account (e.g. “IIIDEM PNB”) |
| `{payment_gateways}` | Gateway config JSON per account (merchant id, secret, URLs) |
| `{paygw_pnb_txn}` | Pending/completed PNB transactions (amount, txnref, status) |
| `{payments}` | Moodle core payment records (created on success) |
| `{user_enrolments}` | User enrolled after successful `deliver_order` |

### `{paygw_pnb_txn}` columns

| Column | Description |
|--------|-------------|
| `txnref` | Unique reference sent to bank (e.g. `PNB1717...`) |
| `userid` | Payer |
| `component` | Always `enrol_fee` for course fees |
| `paymentarea` | Always `fee` |
| `itemid` | Fee enrol instance id |
| `amount` | Server-calculated amount (source of truth) |
| `currency` | e.g. `INR` |
| `status` | `pending` or `completed` |
| `bankref` | Bank reference on success |

---

## 5. Web services

| Service | Class | Purpose |
|---------|-------|---------|
| `core_payment_get_available_gateways` | Core | List gateways + display cost for modal |
| `paygw_pnb_get_redirect_form` | `get_redirect_form` | Create txn + return POST form data |

Registered in `db/services.php`. Requires logged-in user.

---

## 6. Test simulator (mock.php)

### What it is

The **simulator is not a separate tool**. It is a PHP page on your Moodle site that **imitates PNB’s payment page** for development and UAT when real bank credentials are not available.

| Real PNB | Mock simulator |
|----------|----------------|
| User pays on PNB website | User pays on your site: `/payment/gateway/pnb/mock.php` |
| Bank transfers money | You click “Complete payment (simulate success)” |
| Requires merchant credentials | Works with any test secret key |

### When mock is used

Mock is used when **Environment = Test** and the test gateway URL points to `mock.php`, OR when the configured URL is empty / still the placeholder `gateway.example.pnb.in` (auto-fallback in `pnb_helper::resolve_gateway_url()`).

### How to test payment (manual checklist)

**Prerequisites**

1. Fee enrolment enabled on course (e.g. course id 4, INR 15,000)
2. Payment account “IIIDEM PNB” with PNB gateway enabled
3. Test user logged in with **student** occupation in profile
4. User **not** already enrolled via fee on that course

**Steps**

1. Open: `https://iiidem-certification.ddev.site/course/view.php?id=4`
2. Click **Pay with PNB**
3. In modal, confirm gateway shows **Punjab National Bank (PNB)** and cost **INR 15,000.00**
4. Click **Proceed**
5. On mock page, read the notice (“This is a local test page…”)
6. Click **Complete payment (simulate success)**
7. You should be redirected to the course with a success message and be enrolled

**To test failure:** click **Cancel payment (simulate failure)** on the mock page.

**To retest the same user:** unenrol fee enrolment first:

```bash
ddev exec php payment/gateway/pnb/cli/unenrol_fee_user.php 4 username@example.com
```

### Mock rules

- Do **not** refresh `mock.php` after completing payment — start again from the course page
- Mock uses the **amount from `{paygw_pnb_txn}`**, not tampered POST fields
- **Never** leave mock URL as the live gateway URL in production

### CLI helpers (run inside DDEV)

```bash
# Initial setup: payment account + fee enrolment on course 4, INR 15000
ddev exec php payment/gateway/pnb/cli/setup_course_fee.php 4 15000

# Diagnose fee instance, gateways, enrolled users
ddev exec php payment/gateway/pnb/cli/diagnose_course_fee.php

# Check gateway URL resolution and recent transactions
ddev exec php payment/gateway/pnb/cli/check_gateway_config.php

# Reset user for payment retest
ddev exec php payment/gateway/pnb/cli/unenrol_fee_user.php 4 username@example.com

# Rebuild AMD after editing JS
ddev exec bash -c "cd /var/www/html && npx grunt amd --root=payment/gateway/pnb"

# Purge caches after lang/template changes
ddev exec php admin/cli/purge_caches.php
```

### View transaction history

Open (as admin):

`/payment/gateway/pnb/transactions.php`

Or query `{paygw_pnb_txn}` directly in the database.

---

## 7. Admin configuration

**Site administration → Plugins → Payment gateways → PNB**

| Setting | Description |
|---------|-------------|
| Brand name | Shown on bank page |
| Merchant ID | From PNB after IPG onboarding |
| Secret key | HMAC checksum signing |
| Gateway URL | Live PNB payment URL |
| Test gateway URL | Mock or PNB sandbox URL |
| Environment | `test` or `live` |

**Site administration → Plugins → Enrolments → Fee payment**

Ensure fee enrolment is enabled site-wide.

**Course → Enrolment methods → Fee**

- Cost and currency
- Payment account linked (`customint1`)

---

## 8. Security

### Source of truth

| Data | Trusted source |
|------|----------------|
| Payment amount | `{enrol}.cost` via `core_payment\helper::get_payable()` |
| Who can pay | `fee_access` + `registration_profile` |
| Transaction amount | `{paygw_pnb_txn}.amount` (written server-side) |
| Modal display in browser | **Not trusted** — cosmetic only |

### Protections

1. **Amount not sent from browser** — `get_redirect_form` only accepts `component`, `paymentarea`, `itemid`, `description`.
2. **Checksum on redirect** — HMAC-SHA256 with secret key on outbound form.
3. **Checksum on return** — Bank/mock response verified in `return.php`.
4. **Amount match on return** — Returned `AMOUNT` must match `{paygw_pnb_txn}.amount`.
5. **User ownership** — Transaction `userid` must match logged-in user.
6. **Eligibility** — Students only; EMB users cannot pay online.

Changing INR 15,000 in browser DevTools affects **display only**, not the charge or enrolment.

---

## 9. PNB only, or other banks (HDFC, ICICI)?

**This plugin is built for PNB’s Internet Payment Gateway protocol:**

- Field names: `MERCHANTID`, `TXNREFNO`, `AMOUNT`, `CURRENCYCODE`, `RETURNURL`, `CHECKSUM`
- Checksum algorithm: HMAC-SHA256 (as implemented in `pnb_helper.php`)
- Redirect POST flow per PNB integration document

| Provider | Supported today? | Notes |
|----------|------------------|-------|
| **PNB** | Yes | Mock + live when credentials configured |
| **HDFC** | No | Requires separate `paygw_hdfc` plugin |
| **ICICI** | No | Requires separate `paygw_icici` plugin |
| **PayPal** | Via core | Moodle includes `paygw_paypal` |
| **Razorpay / PayU** | No | Would need custom or third-party plugin |

Moodle can show **multiple gateways** in one modal, but each bank needs its own plugin (different APIs, fields, and checksum rules).

To add another bank later: copy the plugin structure, implement that bank’s API in a new `paygw_*` plugin, register a second gateway on the same payment account.

---

## 10. Going live (production checklist)

1. Obtain **Merchant ID**, **Secret key**, and **production gateway URL** from PNB.
2. In admin, set **Environment** to **Live** and enter production **Gateway URL**.
3. Remove or disable mock URL from live configuration.
4. Confirm **Return URL** registered with PNB points to:  
   `https://your-production-domain/payment/gateway/pnb/return.php`
5. Test one real small payment in PNB sandbox (if provided) before go-live.
6. Verify checksum field names match PNB’s final integration document (adjust `pnb_helper.php` if bank spec differs).
7. Ensure HTTPS is enabled on production site.
8. Restrict mock.php from public use (test environment only).

---

## 11. Troubleshooting

| Symptom | Likely cause | Action |
|---------|--------------|--------|
| No Pay button | User not student, already enrolled, or no gateway | Run `diagnose_course_fee.php`; check profile occupation |
| `[[gatewayname]]` in modal | Missing lang strings | Add `gatewayname` / `gatewaydescription` in `lang/en/paygw_pnb.php`; purge caches |
| Blank OK on Proceed | JS/AMD not built or PHP error | Rebuild AMD; check web service response in browser Network tab |
| Invalid checksum | Wrong secret or field order | Match PNB doc; check admin secret key |
| Amount mismatch | Tampered redirect form | Expected — security working; restart from course page |
| Mock session expired | Reloaded mock.php | Return to course and click Pay again |
| Payment hidden for student | Active fee enrolment | `unenrol_fee_user.php` to retest |

---

## 12. Key code entry points

| Action | File |
|--------|------|
| Show fee on course page | `theme/iiidem2/lib.php` → `theme_iiidem2_get_course_fee_payment_context()` |
| Pay button HTML | `theme/iiidem2/templates/course/payment_sidebar.mustache` |
| Open payment modal | `payment/amd/src/gateways_modal.js` (core) |
| Start PNB payment | `payment/gateway/pnb/classes/external/get_redirect_form.php` |
| Build bank form | `payment/gateway/pnb/classes/pnb_helper.php` |
| Test payment page | `payment/gateway/pnb/mock.php` |
| Complete enrolment | `payment/gateway/pnb/return.php` |
| Student eligibility | `payment/gateway/pnb/classes/fee_access.php` |

---

*Document version: June 2026 — IIIDEM Certification LMS*
