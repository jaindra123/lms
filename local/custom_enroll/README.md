# Retired: local_custom_enroll

CDAC PoC used:

`POST /local/custom_enroll/ajax.php?action=create_razorpay_order` with client `"course_fee": "10"`.

That trusted client amount is **removed**. This plugin is a **tombstone**: `ajax.php` always returns **410** and never creates Razorpay orders.

Use **`enrol_fee` + `paygw_razorpay`** (server-side payable). See `docs/payment-amount-validation.md`.
