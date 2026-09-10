# 12. Payment Amount Manipulation

## Finding

| Field | Report |
|-------|--------|
| Title | Payment Amount Manipulation |
| Impact | HIGH / CVSS 8.1 |
| CWE | [CWE-472](https://cwe.mitre.org/data/definitions/472.html) — External Control of Assumed-Immutable Web Parameter |
| OWASP | A04:2021 – Insecure Design |
| Report URL | `lumberjack.razorpay.com/v1/track` (analytics — not LMS) |

> The payment amount must be determined and validated **server-side**. The application must not trust a client-supplied fee.

Also claimed: **able to complete payment without scanning the UPI QR**.

## PoC (authoritative) — retired `local/custom_enroll`

```http
POST /moodle/local/custom_enroll/ajax.php?action=create_razorpay_order
{"course_id":…, "user_id":…, "course_fee":"10", "course_name":"…"}
```

Server returned success with Razorpay order `amount: 1000` (paise = **₹10**).

| Issue | Detail |
|-------|--------|
| Client controlled price | JSON field `course_fee` |
| Trusted by server | Order created for that amount |
| Result | Underpayment enrolment / invoice |

### Remediation

`local_custom_enroll` is **retired**. Repo ships a **tombstone**:

- `local/custom_enroll/ajax.php` → **HTTP 410**, never creates orders, never reads `course_fee`
- Live payments: Moodle **`enrol_fee` + `paygw_razorpay`**

```bash
curl -s -o /dev/null -w '%{http_code}' -X POST \
  'https://YOUR-HOST/local/custom_enroll/ajax.php?action=create_razorpay_order'
# Expect: 410 — never status:success + low amount
```

## False positives / out of scope

| PoC | Verdict |
|-----|---------|
| Tamper `amount` on `lumberjack.razorpay.com/v1/track` | **Analytics only** — does not settle payment or enrol |
| Tamper body on `api.razorpay.com/.../checkout/order` | Razorpay Checkout API; charge is bound to **server-created** `order_id` |
| Complete UPI without scanning QR (test mode) | Razorpay Checkout UX / test instruments — **not** LMS amount bypass |
| Modal shows ₹10 | Fee comes from **server** `enrol.cost` / gateway config — check admin fee is not ₹10 |

### Retest (2026-09) — Burp `amount=1000` → `20`, no QR scan

| Observation | Meaning |
|-------------|---------|
| Host = **`api.razorpay.com`** (not LMS) | Client ↔ Razorpay Checkout traffic |
| Body `amount=1000` | Razorpay uses **paise**: 1000 = **₹10.00**, not ₹1000 |
| Change to `amount=20` / UI shows ₹20 | Tamper of Checkout display/request — **not** the LMS order |
| LMS receipt **Amount Paid: INR 10.00** | Matches **server-created** order / `enrol.cost` (₹10 test fee) — underpay did **not** enrol at ₹0.20 |
| Payment without scanning UPI QR | Razorpay **test mode** Checkout can complete via alternate test paths; not an LMS QR-bypass bug |
| Course title `alert(1)…` on receipt | Separate XSS / input finding — not amount IDOR |

**Auditor clarification:** If staging fee is configured as ₹10 for Razorpay test keys, seeing `1000` in the gateway request is correct (paise). Raising production fee to ₹1000 requires admin `enrol.cost` / gateway course fee = **1000** (INR), which creates order amount **100000** paise.

**LMS controls (unchanged):** checkout WS accepts no client amount; verify calls `assert_txn_matches_payable` + `assert_remote_payment_matches_txn` (remote payment must be `captured` at txn paise). Mismatch → no enrolment.

## Current payment controls (`paygw_razorpay`)

### Create order

| Control | Behaviour |
|---------|-----------|
| Checkout WS | Args: `component`, `paymentarea`, `itemid`, `description` only — **no amount** |
| Amount | `helper::get_payable()` + `course_fee_amount::get_payment_amount()` → `max(configured, enrol.cost)` |
| Order | Created server-side via Razorpay Orders API (or mock only when explicitly allowed) |

### Verify / enrol (fail closed)

| Check | Behaviour |
|-------|-----------|
| HMAC signature | Order id \| payment id |
| Owner | Txn userid must be current user |
| `assert_txn_matches_payable` | Stored txn vs current server payable |
| `assert_remote_payment_matches_txn` | Razorpay payment **amount (paise)**, order id, currency, status **`captured`** |
| Mock | **Disabled** on `staginglms.eci.gov.in` and production; local needs `$CFG->paygw_razorpay_allow_mock` |

### Fee sync

Gateway `coursefeeamount` sync **never lowers** existing `enrol.cost` (prevents admin test ₹10 overwriting ₹1000).

### “Pay without scanning QR”

| Cause | Mitigation |
|-------|------------|
| Mock “Pay” button / `order_mock_*` | Blocked on staging + production |
| Razorpay test Checkout alternate methods | Expected in **test** keys; use live keys on production; not an LMS undercharge |
| Client changes Checkout `amount` | Ignored — Razorpay charges the **order**; LMS re-checks remote payment amount before enrol |

## Admin checklist (staging)

1. Payment account **course fee / enrol.cost** = intended full price (e.g. ₹1000), not ₹10.  
2. Razorpay keys = real `rzp_test_…` or live — **not** empty / `CHANGE_ME` / `rzp_test_mock`.  
3. `$CFG->paygw_razorpay_allow_mock` **unset** on staging/prod.  
4. Tombstone `local/custom_enroll/ajax.php` returns **410**.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Old `course_fee` endpoint | Tombstone → 410 |
| Amount not from client | No amount in checkout WS |
| Validate on complete | Payable + Razorpay captured amount |
| Fail closed | Mismatch → no enrolment |
| No free mock on UAT/prod | `mock_payments_allowed()` false for staginglms + live hosts |
| Fee sync cannot undercharge | Sync never lowers `enrol.cost` |
| Lumberjack / QR UX | Out of scope / not amount IDOR |

Related: [web-parameter-tampering.md](web-parameter-tampering.md), [broken-access-control.md](broken-access-control.md).
