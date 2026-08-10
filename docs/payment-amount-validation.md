# Payment Amount Manipulation — server-side amount validation

## Finding

> The payment amount must be determined and validated server-side.

## Controls applied

### Create order / redirect (all gateways)

| Gateway | Amount source |
|---------|----------------|
| Razorpay | `helper::get_payable()` + `course_fee_amount::get_payment_amount()` (never a client param) |
| PNB / ICICI | `helper::get_payable()->get_amount()` + surcharge only |

WS/checkout APIs accept `component`, `paymentarea`, `itemid`, `description` only — **no amount field**.

### Undercharge prevention (Razorpay `coursefeeamount`)

`get_payment_amount()` now charges `max(configured, payable)` so a low test `coursefeeamount` cannot undercut a higher `enrol.cost`.

### Verify / return (fail closed)

| Gateway | Validation |
|---------|------------|
| **Razorpay** | Signature + `assert_txn_matches_payable()` + live API `GET /payments/{id}` (order_id, amount in paise, currency, status captured/authorized) |
| **PNB / ICICI** | Callback amount **required** and must match stored txn; then `assert_txn_matches_payable()` before `deliver_order` |

Previously PNB/ICICI skipped the amount check when the bank omitted AMOUNT (fail-open). That path now rejects.

### Enrolment

`complete_transaction` / bank return call `deliver_order` only after amount checks pass. Stored `$txn->amount` (server-written at create time) is what is saved to Moodle payments — never a POST amount.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Keep `enrol_fee` cost and Razorpay `coursefeeamount` aligned on staging/live (use CLI `set_course_fee_amount.php` or gateway account save to sync).

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Amount not from client | No amount in WS params; payable from Moodle DB |
| Validate on complete | Payable re-check + Razorpay API amount / bank callback amount |
| Fail closed | Missing/mismatched amount → no enrolment |
| Single source of truth | Moodle payable / `enrol.cost`; config cannot undercharge |
