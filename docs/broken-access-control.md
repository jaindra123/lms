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

### 5. Custom certificate download without real login (MEDIUM)

**Files:** `mod/customcert/view.php`

**Report:** Instance 8/9 — `/mod/customcert/view.php?id=…&downloadown=1` labelled “accessible without login”, with `invalidparameter` + SQL `SELECT * FROM {user} WHERE id = ?` for `id = 0`.

**Root cause:**
1. Course-module lookup with `MUST_EXIST` ran **before** `require_login()`, so anonymous probes saw exception pages instead of a login redirect.
2. Guests (`$USER->id = 0`) could reach `downloadown` and trigger a user lookup dump when debug display was on.
3. SQL/stack text is **verbose error disclosure** ([verbose-error-messages.md](verbose-error-messages.md)), not a successful certificate theft.

**Fix:**
- Site `require_login()` immediately after bootstrap
- Course/`cm` `require_login` + `mod/customcert:view` unchanged
- Download / delete / report-download actions redirect guests to login
- PDF generation refuses empty / guest `userid`

**Instance 9 Razorpay URL:** `api.razorpay.com/.../payment/status?key_id=rzp_test_…` is a **third-party** public checkout status endpoint, not this LMS. The screenshot SQL stack is still Moodle `customcert` under debug — remediate as above; dispute Razorpay host as out of scope.

## Already OK (no change)

Dashboards, certificate download, support tickets, live quiz API, payment returns, admin chatbot — already use `require_login` / capabilities / ownership / sesskey as appropriate.

## Scanner finding: Broken Access Control (profile / edit IDOR)

| Field | Report |
|-------|--------|
| Title | Broken Access Control |
| CWE | CWE-639 |
| URLs | `/user/profile.php?id=N`, `/user/edit.php?id=N&course=1` |

Moodle core grants same-course peers `moodle/user:viewdetails`. That is not sufficient for this LMS — peer enumeration of profiles/email must be blocked for students.

### Instance 1 — `/user/profile.php?id=N` (view)

Login as student **id=51**, then change `id`.

| Step | URL | Expected after harden | Staging recheck (2026-09 screenshots) |
|------|-----|------------------------|----------------------------------------|
| 1 | Own session (id=51) | Own profile OK | Baseline |
| 2 | `?id=5` | Deny: “details … not available” | **Denied** (remediation live for this id) |
| 3 | `?id=3` | **Allow if course contact / instructor** | Instructor “Prof. Chanchal…” — **by design**, not peer IDOR |
| 4 | `?id=7` | Deleted / unavailable — no peer PII | “account has been deleted” — **not a successful profile dump** |
| 5 | `?id=32` (peer student) | Deny for pure students | **Still showed “test singh” user details** → guard **not fully effective on staging** for peers (redeploy theme + `upgrade.php` + purge, then retest) |

**Repo status:** hardened in `theme_iiidem2` (`restrict_profile_idor` + `theme_iiidem2_control_view_profile`).  
**Auditor status:** treat Instance 1 as **open until peer `id=32` (and similar) returns deny** for a non-teacher student session.

### Instance 2 — `/user/edit.php?id=N&course=1` (edit)

| Step | Action | Expected | Staging recheck |
|------|--------|----------|-----------------|
| 1 | `edit.php?id=51` (self) | Edit own profile OK | OK |
| 2 | Change to `id=32` | Deny: no permission to **Edit user profile** | **Denied** |
| 3 | Click Continue → other user details | Must **not** equal unauthorized **edit**. If Continue lands on `/user/profile.php?id=32`, that is **Instance 1** (view), not a separate edit IDOR | Edit path **fixed**; remaining risk is Instance 1 view |

**Verdict Instance 2:** **Fixed** (core `moodle/user:editprofile` + no student edit of peers). Do not reopen as edit BAC if only profile view still works.

### Controls (Instance 1)

| Control | Behaviour |
|---------|-----------|
| `theme_iiidem2_control_view_profile()` | Students → own profile only; teachers/admins OK; course contacts OK |
| `hook_listener::restrict_profile_idor()` | Early deny on `/user/profile.php` + `/user/view.php` |
| `$CFG->forceloginforprofiles = 1` | Guests cannot open profiles |
| `$CFG->profilesforenrolledusersonly = 1` | No profile without enrolment context |
| `$CFG->hiddenuserfields` (forced in `config.php`) | Hide email/phone/city/… from non-privileged viewers |
| `defaultpreference_maildisplay = 0` | New accounts do not publish email to participants |

### Retest (pass criteria)

```text
1. Login as student A (not teacher, not site admin) — confirm occupation/role is student only.
2. /user/profile.php?id=<peer student> → “not available” / redirect to own profile (MUST fail for id=32-class peers).
3. /user/profile.php?id=<course contact / instructor> → may still show (allowed).
4. /user/profile.php?id=<A> → own profile OK.
5. /user/edit.php?id=<peer>&course=1 → permission error only; no editable form for peer.
6. Logged out → /user/profile.php?id=2 → login redirect (not profile HTML).
```

### Verify

```bash
curl -sI 'https://staginglms.eci.gov.in/user/profile.php?id=2'
# Expect redirect to login when logged out
```

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
| AuthN | `require_login()` on privileged scripts; customcert before CM lookup |
| AuthZ | `require_capability()` / owner id checks / siteadmin |
| IDOR (custom) | Chatbot history session-bound; cancel bound to courseid |
| IDOR (profiles) | Early theme guard + `control_view_profile` + forced hiddenuserfields / forceloginforprofiles |
| Customcert download | Guests blocked; PDF requires real userid |
| Mock pay | POST+sesskey; production host blocked |
