# 12. Payment Amount Manipulation

## Finding

| Field | Report |
|-------|--------|
| Title | Payment Amount Manipulation |
| Impact | HIGH / CVSS 8.1 |
| CWE | [CWE-472](https://cwe.mitre.org/data/definitions/472.html) — External Control of Assumed-Immutable Web Parameter |
| OWASP | A04:2021 – Insecure Design |

> The payment amount must be determined and validated **server-side**. The application must not trust a client-supplied fee.

## PoC (authoritative) — `local/custom_enroll`

```http
POST /moodle/local/custom_enroll/ajax.php?action=create_razorpay_order
{"course_id":…, "user_id":…, "course_fee":"10", "course_name":"…"}
```

Server returned success with Razorpay order `amount: 1000` (paise = **₹10**). Invoice showed **INR 10.00** for a full-priced course.

| Issue | Detail |
|-------|--------|
| Client controlled price | JSON field `course_fee` |
| Trusted by server | Order created for that amount |
| Result | Underpayment enrolment / invoice |

### Remediaiton for that endpoint

`local_custom_enroll` is **retired**. Repo ships a **tombstone** plugin:

- `local/custom_enroll/ajax.php` → **HTTP 410**, never creates orders, never reads `course_fee` / amount as price
- Live payments: Moodle **`enrol_fee` + `paygw_razorpay`** (and PNB/ICICI)

On staging/production: deploy this tree (overwrites any old vulnerable `ajax.php`), then:

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Confirm old path fails:

```bash
curl -s -o /dev/null -w '%{http_code}' -X POST \
  'https://YOUR-HOST/local/custom_enroll/ajax.php?action=create_razorpay_order'
# Expect: 410 (with session) or redirect/login — never status:success + low amount
```

## Other PoC (false positive) — `lumberjack.razorpay.com/v1/track`

Changing `"amount"` on Razorpay’s **analytics** track URL and getting `200 OK` does **not** settle a payment or enrol a user. Ignore as underpayment proof.

## Current payment controls (`paygw_*`)

### Create order / redirect

| Gateway | Amount source |
|---------|----------------|
| Razorpay | `helper::get_payable()` + `course_fee_amount::get_payment_amount()` (**no** client amount / `course_fee`) |
| PNB / ICICI | `helper::get_payable()->get_amount()` + surcharge only |

Checkout WS accepts `component`, `paymentarea`, `itemid`, `description` only — **no amount field**.

### Undercharge prevention

`get_payment_amount()` uses `max(configured, payable)` so a low gateway test fee cannot undercut a higher `enrol.cost`.

### Verify / return (fail closed)

| Gateway | Validation |
|---------|------------|
| **Razorpay** | Signature + `assert_txn_matches_payable()` + live API payment amount (paise) |
| **PNB / ICICI** | Callback amount required + match stored txn before `deliver_order` |

Enrolment runs only after checks pass. Stored `$txn->amount` is written server-side at order create.

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Old `course_fee` endpoint | Tombstone `local_custom_enroll` → 410 |
| Amount not from client | No amount / `course_fee` in `paygw_razorpay` checkout |
| Validate on complete | Payable + Razorpay/bank amount checks |
| Fail closed | Mismatch → no enrolment |
| Invoice amount | Reflects server txn, not POST fee |

Related: [password-change-sessions.md](password-change-sessions.md) (same report section family).
