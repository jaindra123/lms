# Broken Access Control — server-side authorization

## Finding

> Enforce server-side authorization checks for every request

## Fixes applied

### 1. Homepage chatbot history IDOR (HIGH)

**Files:** `theme/iiidem2/ajax/chatbot_query.php`, `theme/iiidem2/javascript/homepage_chatbot.js`, `theme/iiidem2/lib.php`

**Issue:** Any visitor with a sesskey could request history (or ask) with a victim email and read another user’s Q&A.

**Fix:** History is authorized by session-owned chatbot record ids only. Client-supplied email is never used as an authorization key.

### 2. Razorpay mock CSRF / free pay (HIGH)

**File:** `payment/gateway/razorpay/mock.php`

**Issue:** `action=pay|cancel` via GET without sesskey could complete enrolment.

**Fix:**
- `require_login()` + owner check (unchanged)
- Pay/cancel require **POST + `require_sesskey()`**
- Only `order_mock_*` orders
- Blocked on live host `iiidemlms.eci.gov.in`

### 3. Live-class cancel course binding (MEDIUM)

**File:** `local/iiidem_coursecalendar/schedule_event.php`

**Issue:** Cancel used event id without verifying it belongs to the URL `courseid`.

**Fix:** Load event and require `(int)$event->courseid === $courseid` before cancel (still behind `require_capability`).

### 4. Exception leakage (MEDIUM)

**File:** `theme/iiidem2/ajax/chatbot_query.php`

**Fix:** Generic error message to clients; details only in `error_log`.

## Already OK (no change)

Dashboards, certificate download, support tickets, live quiz API, payment returns, admin chatbot — already use `require_login` / capabilities / ownership / sesskey as appropriate.

## Scanner finding: `/user/profile.php?id=N` (IDOR)

| Field | Report value |
|-------|----------------|
| Example URL | `https://stagingbms.cci.gov.in/user/profile.php?id=6` |
| CWE | CWE-639 |
| Note | Host `stagingbms.cci.gov.in` is **not** this LMS (`staginglms.eci.gov.in`) |

### Moodle behaviour (this codebase)

`user/profile.php`:

1. Loads user by `id` (`PARAM_INT`)
2. Calls `user_can_view_profile($user)` — **server-side authorization**
3. Theme callback `theme_iiidem2_control_view_profile()` further restricts access:
   - **Own profile:** allowed
   - **Teachers / managers / admins:** may view other profiles
   - **Course contacts:** still viewable (core rule)
   - **Students viewing peers by changing `?id=`:** **blocked** (`VIEWPROFILE_PREVENT`)
   - Sensitive fields (`email`, `city`, `phone*`, `lastaccess`, …) hidden from non-privileged viewers via `$CFG->hiddenuserfields`

With `$CFG->forceloginforprofiles = 1` (forced on staging/production in `config.php`):

- Guests / anonymous users cannot open profiles
- Arbitrary IDOR from a student account to another student fails

### Verify on this LMS

```bash
# Logged out — must redirect to login (not show profile HTML)
curl -sI 'https://staginglms.eci.gov.in/user/profile.php?id=2'

# Logged in as student A, open student B — expect access denied (not full profile)
# Logged in as teacher, open student — expect profile OK
```

Hostnames in some CDAC pages (`staginglms.cci.gov.in`, `stagingbms.cci.gov.in`) may differ from `staginglms.eci.gov.in`; retest on the real LMS URL.

## Deploy to staging

```bash
# Deploy changed files, then:
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| CSRF | `require_sesskey()` / `confirm_sesskey()` on state changes |
| AuthN | `require_login()` on privileged scripts |
| AuthZ | `require_capability()` / owner id checks / siteadmin |
| IDOR (custom) | Chatbot history session-bound; cancel bound to courseid |
| IDOR (profiles) | `user_can_view_profile` + forced `forceloginforprofiles` |
| Mock pay | POST+sesskey; production host blocked |
