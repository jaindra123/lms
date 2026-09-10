# Web Parameter Tampering — server-side authorization

## Finding

> Perform server-side authorization for every request.

## Fixes applied

### 1. Registration privilege escalation (HIGH)

**Files:** `register/index.php`, `theme/iiidem2/classes/registration_profile.php`, `theme/iiidem2/classes/registration_enrolment.php`, `theme/iiidem2/lib.php`

**Issue:** Client could POST `occupation=instructor` / `workingemb` / stealth `emb=1` and receive:
- fee waiver (`iiidem_emb`)
- `editingteacher` role
- teacher dashboard access (occupation treated as teacher)

**Fix:**
- Registration uses **validated form data only** (no `array_merge($_POST, …)`)
- Checkbox/occupation reads no longer trust raw `$_POST`
- Self-registration **never** sets `iiidem_emb` (admin-only “Allow without payment”)
- All new registrants require fee payment unless an admin waives it
- Teaching roles are **never** auto-assigned from occupation; Moodle role assignments only
- `theme_iiidem2_user_has_teacher_role()` no longer auto-heals roles from profile

### 2. Chatbot history IDOR via email (HIGH)

**Files:** `theme/iiidem2/ajax/chatbot_query.php`, `theme/iiidem2/lib.php`

**Issue:** `action=ask` with a victim email returned that email’s full Q&A history.

**Fix:** History is authorized by **session-owned record ids** created during this browser session. Client email is contact metadata only.

### 3. Mock payment completion on live (HIGH)

**Files:** `payment/gateway/razorpay/classes/razorpay_helper.php`, `…/external/verify_payment.php`, `razorpay/mock.php`, `pnb/mock.php`, `icici/mock.php`

**Issue:** Forged mock HMAC / mock pages could complete enrolment when mock orders existed; host guard was incomplete on verify path and missing on PNB/ICICI mocks.

**Fix:** Live hosts (`iiidemlms.eci.gov.in`, `lms.eci.gov.in`) refuse mock mode, mock signatures, and mock.php.

### 4. Support ticket `courseid` (MEDIUM)

**Files:** `local/iiidem_support/classes/form/ticket_form.php`, `classes/manager.php`

**Fix:** Form validation + `create_ticket()` require `courseid` ∈ user’s enrolments (or 0).

### 5. Live-class hidden `courseid` (MEDIUM)

**Files:** `local/iiidem_coursecalendar/classes/form/liveclass_form.php`, `schedule_event.php`

**Fix:** `setConstant('courseid')` + force `$data->courseid = $courseid` after capability-checked URL param.

### 6. CDAC URL list — Web Parameter Tampering / IDOR (HIGH) — analysis

Host variants in reports (`staginglms.cdac.gov.in`, `staginglma.cci.gov.in`) may differ from the real LMS host.

| Cited URL | Parameter meaning | Verdict |
|-----------|-------------------|---------|
| `/login/change_password.php?id=1` | `id` = **course** id | **Hardened** — `id` ignored (forced SITEID); password always for session `$USER` |
| `/user/preferences.php?userid=5` | Target user | Core authZ + theme forces non-privileged → own userid |
| `/user/forum.php?id=5` | Target user | `useredit_setup_preference_page` requires `moodle/user:editprofile` for others; theme redirects students to own `id` |
| `/user/calendar.php?id=5` | Target user | Same preference-page authZ + theme redirect |
| `/user/contentbank.php?id=5` | Target user | Same |
| `/message/edit.php?id=5` | Target user | Requires `moodle/user:editmessageprofile` for others; theme redirect for students |
| `/user/index.php?id=4` | Course id | `require_login($course)` + enrolment |
| `/mod/attendance/view.php?id=11` | Course module id | Module access + theme forces own `studentid` |
| `/mod/page/view.php?id=N` / `/mod/scorm/view.php?id=18` | Course module id | Enrolment / CM visibility |
| `/mod/customcert/view.php?…` | — | **Out of scope** — plugin not in this LMS |
| `/course/view.php?id=4&registered=1` | Course id + UI flag | `registered=1` only shows a success toast — **no privilege change** |
| `/?qlogin=…&userid=49` | — | **Not implemented** in this codebase; query keys stripped |
| `/blog/edit.php?action=add&userid=46` | Ignored `userid` on add | Add uses session user; blogs **disabled** on staging/prod (`$CFG->enableblogs = 0`) |
| `/lib/ajax/service.php?…core_calendar_get_calendar_event_by_id` | Event id | **Hardened** — `calendar_view_event_allowed()` + personal-event ownership; sesskey required |

CVSS 8.8 / CWE-639 is overstated where changing `id` only selects another **authorized** course module or course the user already can access.

**Extra hardening:** `restrict_preferences_userid_tampering()` covers preferences, forum, calendar, contentbank, editor, language, and message preferences.

### 7. PoC: `change_password.php?id=` (Instance 1) — **HARDENED**

| Claim | Fact |
|-------|------|
| `?id=` is another user’s primary key | **False** — Moodle uses `id` as **course** context only |
| Changing `id=1` → `id=4` changes whose password is updated | **False** — form always updates session `$USER` (same username in both screenshots) |
| `id=24` → “invalid course ID” | Course 24 missing; **not** an authZ bypass |

**Fix applied:**

| Control | Behaviour |
|---------|-----------|
| `login/change_password.php` | Forces `$id = SITEID`; ignores client `id` |
| Theme `harden_change_password_course_id()` | Pins `$_GET`/`$_POST` `id` to SITEID early (`after_config`) |
| Password target | Always `$USER` + `moodle/user:changeownpassword` |

**Retest:** `/login/change_password.php?id=1`, `?id=4`, `?id=24` → same own-user form; no `invalidcourseid` stack; submitting changes only the logged-in account.

**Staging recheck (2026-09):** `?id=1` and `?id=4` both show username `mayaaravind18` for session user MC — **no cross-user password target**. **Dispute / closed.**

### 8. Instance 2 — `/mod/attendance/view.php?id=11` → `id=1` (NOT successful IDOR)

Auditor changed course-module `id` from `11` to `1`.

| Observation | Meaning |
|-------------|---------|
| Response: “Sorry, but you do not have permission to view this page.” | **Authorization held** — not a data disclosure IDOR |
| Also showed `dml_missing_record_exception` + SQL fragment | **Verbose error** from staging debug — not privilege bypass |

`id` is the **attendance activity CM id**, not another student’s userid. Viewing another enrolled activity you are allowed into is normal; viewing one you cannot access is denied (as shown).

Student peer attendance is further restricted by `restrict_student_attendance_pages()` (own `studentid` only).

**Remediation for the SQL leak:** keep `$CFG->debugdisplay = 0` on staging/production (`docs/verbose-error-messages.md`, `docs/debug-mode-staging.md`). Retest after deploy — end users must see a generic error only.

### 9. Instance 3 / 8 / 9 — `/mod/customcert/view.php?…&downloadown=1`

**In scope:** `mod_customcert` is installed. Remediaiton in `mod/customcert/view.php` (≥ `2024042218`):

| Control | Behaviour |
|---------|-----------|
| Early `require_login()` | Anonymous → login redirect (not SQL dump) |
| Guest + `downloadown` | Redirect to login |
| PDF userid | Must be real non-guest user |
| SQL/stack in pink box | Debug leak — keep `debugdisplay=0` ([verbose-error-messages.md](verbose-error-messages.md)) |

**Instance 9 cited URL** `api.razorpay.com/...` is **not** this LMS; dispute that host. Retest certificate URL on `staginglms.eci.gov.in`.

See [broken-access-control.md](broken-access-control.md) §5.

### 10. Instance — `/mod/assign/view.php?id=5` (SQL in error page)

Changing CM `id` to a missing/invalid assignment produced:

- `dml_missing_record_exception`
- Full SQL `SELECT … FROM {assign} WHERE id = ?`
- PHP stack trace (`moodle_database.php`, …)

This is **not** successful unauthorized access to another user’s assignment data. It is **information disclosure via developer/debug error pages**.

With `$CFG->debug = 0` and `$CFG->debugdisplay = 0` (forced on staging/production in `config.php`), end users get a generic error without SQL or stack traces. See `docs/verbose-error-messages.md` and `docs/debug-mode-staging.md`.

Same class of finding as Instance 2 (attendance).

### 11. Instance 4 — `core_calendar_get_calendar_event_by_id` — **HARDENED**

AJAX: `POST /lib/ajax/service.php?…&info=core_calendar_get_calendar_event_by_id` with `args.eventid`.

| Control | Behaviour |
|---------|-----------|
| Sesskey | Required (Moodle AJAX) |
| `calendar_view_event_allowed()` | Course/module/group/category/site rules |
| Site events | Logged-in non-guest only |
| Category events | Category must be user-visible |
| Personal user events | Owner or `moodle/calendar:manageentries` only |
| Missing / denied `eventid` | Same `nopermissiontoviewcalendar` (no existence oracle) |

**Retest as student:** `eventid` of another user’s **personal** calendar → denied. `eventid` of a course/module event in a course they are **not** enrolled in → denied. Events they already see in their calendar UI may still load (authorized, not IDOR).

**Staging recheck (2026-09):**

| `eventid` | Result | Meaning |
|-----------|--------|---------|
| `8` / `3` | Event/course JSON (e.g. “Live Session on AI”, course `alert(1)`) | User is **authorized** for that course calendar (enrolled) — not IDOR |
| `30` | `nopermissiontoviewcalendar` | **AuthZ held** — tampered id denied |
| Changing id among events the student can already see | Different authorized rows | **Expected** Moodle calendar API |

**Verdict:** **Dispute / closed** as Web Parameter Tampering. Pass criteria = deny for out-of-scope / other-user personal events; allow for enrolled course events is normal.

### 12. Out of scope — `/api/admin/services.php` `eventid` IDOR

PoC posts to `/api/admin/services.php?request=get_detailed_services_info` with JSON `eventid`. That path is **not Moodle** and does not exist in this repository. Dispute / fix on the other application.

### 13. Support tickets `ticket_id` / `ticket.php?id=` (CWE-639)

Report path `local/iidcm_support` may be a typo for `local/iiidem_support`.

| Control | Status |
|---------|--------|
| List page `tickets.php` | Loads only `get_user_tickets($USER->id)` — no client ticket id |
| View `ticket.php?id=` | Non-admins: `get_user_ticket($id, $USER->id)` (SQL binds owner); mismatch → deny |
| Admins | `get_ticket_for_admin` behind `user_can_manage()` |
| JSON POST `ticket_id` → `user_details` | **Not implemented** in this plugin (only FAQ search JSON API) |

### 15. Finding #31 — Web Parameter Manipulation (additional URLs) — **mostly false positive**

| Field | Report |
|-------|--------|
| Title | Web Parameter Manipulation — Additional affected parameter/Endpoint |
| Impact claimed | HIGH / CVSS 8.8 / CWE-639 |
| Host | `https://staginglms.eci.gov.in/` |

| Cited URL | Parameter | Server-side control | Verdict |
|-----------|-----------|---------------------|---------|
| `/user/index.php?id=4` → `id=1` | Course / **site** id | **Fixed (tightened):** site course roster **never** served on this URL (all roles); site admins redirected to `/admin/user.php`; students denied peer course rosters | id=1 PII dump closed |
| `/report/competency/index.php?id=…` | Course id | Login + **`moodle/competency:coursecompetencyview`**; **SITEID hard-denied** (all roles) | `id=1` → access denied (not soft “No participants”); `id=4` only if enrolled + cap |
| `/report/competency/…&user=37` | Target user | **Fixed:** non-staff forced to `user=<self>` | Peer competency IDOR closed |
| `/report/loglive/index.php?id=…` | Course id | `report/loglive:view` + **SITEID never via `?id=`** (all roles; admins → `/report/loglive/index.php` no id) | Teacher/admin → site logs closed |
| `/course/edit.php?category=…` | Category | `moodle/course:create` / `update` | Students denied |
| `/contact-us/?sent=1` | UI flag | **Fixed:** session one-time flag after real submit; `?sent=` ignored | Cannot spoof “Thank you” |

**Why CVSS 8.8 is overstated for most rows:** Changing `id` while logged in as **site admin** is expected. Auditor PoC for participants used an account with Site administration (privileged). Retest as **student** and as **editing teacher** (not site admin).

**Staging recheck (2026-09) — admin session:**

| URL | PoC note | Verdict |
|-----|----------|---------|
| `/user/index.php?id=4` | Admin / privileged login; “24/31 participants”, Enrol users, Edit mode | **Dispute** — authorized course roster for staff/admin. Fail only if a **pure student** sees the peer list. `id=1` (site) must still deny/redirect. |
| `/mod/page/view.php?id=29` (also `id=31`, …) | Admin login; Intruder across CM ids → 200 for pages in accessible courses | **Dispute** — `id` is course-module id; admin/teacher may open enrolled activities. Fail only if a **student not enrolled** in that course gets page HTML. `alert(1)` in breadcrumb = stored course shortname (XSS / content hygiene), **not** parameter-tampering IDOR. |

**Contact-us Instance 5 (fixed):** Opening `/contact-us/?sent=1` without submitting must **not** show “Your message has been sent.”

**Participants Instance (fixed / tightened):** `/user/index.php?id=1` is the **site front-page course** (site-wide names + emails). Auditor PoC changed `id=4` → `id=1` and still saw emails when the account had **Site administration** (often a full site admin). Allowing site admins on this URL left the finding open.

| Actor | `?id=<their course>` | `?id=1` (SITEID) |
|-------|----------------------|------------------|
| Student | Denied (theme) | **Denied** |
| Editing teacher / Manager | Allowed if enrolled + caps | **Denied** |
| Site administrator | Allowed | **Redirect → `/admin/user.php`** (no participants/email table) |

Hardening: `user/index.php` + `user/classes/table/participants.php` + theme hook — **no role** may load the site-home participants list via this endpoint. Theme ≥ `2024101035`.

**Competency Instance 3 — `?user=37` → `?user=40` (fixed / clarified):**

| PoC step | Control |
|----------|---------|
| `user=37&id=4` then `user=40` | Non-graders forced to **own** `user` id; Jump-to-user dropdown only for grade/manage caps |
| `user=38&id=1` | SITEID → **Access denied** (not soft “No participants found”) |
| `user=40&id=4&mod=1` | Invalid/foreign `mod` ignored — **no** “Can't find data record” |

Teachers/graders viewing enrolled learners via Jump to user remains **authorized** (same as gradebook). Retest IDOR as a **student** without grading caps.**Competency Step 4 `mod=1` (fixed):** Invalid / foreign `mod` no longer uses `MUST_EXIST` (which produced “Can't find data record in database”). Bad `mod` is ignored; page stays on course-level report without a DB exception.

**IDs in the audit (`id=4`, `user=37`, `mod=1`, etc.) are staging examples only.** Production course/user/cm ids differ. Controls are by **capability + enrolment**, not by numeric id allowlists.

**loglive Instance 4 (fixed / tightened):** `/report/loglive/index.php?id=4` → `id=1` opened **Site home** live logs (names, user ids, IPs). PoC accounts with Site administration still passed the old “site admins only” check.

| Actor | `?id=<course>` | `?id=1` (SITEID) |
|-------|----------------|------------------|
| Student / teacher | Own course if `report/loglive:view` | **Denied** |
| Site administrator | Own course OK | **Redirect → `/report/loglive/index.php`** (no id; admin report, not course id=1 dump) |

Hardening: `report/loglive/index.php` + `loglive_ajax.php` + theme hook. Theme ≥ `2024101038`.

**IDs in the audit (`id=4`, `user=37`, `mod=1`, etc.) are staging examples only.** Production course/user/cm ids differ. Controls are by **capability + enrolment**, not by numeric id allowlists.

**Retest steps**

```text
1. Login as student (not teacher, not site admin). IDs = whatever exists on that environment.
2. /user/index.php?id=<enrolled course> → denied; id=<site/front / usually 1> → denied.
3. Login as editing teacher on a course:
   /user/index.php?id=<that course> → OK; change to id=1 → Access denied (no email table).
3b. Even as site admin: /user/index.php?id=1 → redirect to /admin/user.php (not participants PII list).
4. /report/competency/index.php?id=1 → Access denied (not “No participants found”).
5. /report/competency/index.php?id=<course>&user=<other> as student → must show only self.
6. /report/competency/index.php?id=<course>&user=<self>&mod=<invalid> → no DB error; report still loads.
7. /report/loglive/index.php?id=<course> as student → access denied; as teacher/admin id=1 → denied or redirect (no site-home IP table via ?id=1).
8. /contact-us/?sent=1 without submit → no fake Thank you.
9. /course/edit.php?category=<other cat> without moodle/course:create → access denied (capability, not IDOR).
```

Real IDOR issues in custom code were already fixed (registration privilege, chatbot history, support ticket ownership, preference userid) — see sections 1–5 and 13 above.

## Admin workflow after deploy
|----------------|-------------------------|--------------|
| Student / working / EMB form | Suspended fee enrolment (must pay) | Optional: check “Allow without payment course” |
| Instructor form | Same (student + fee pending) | Assign editingteacher + optional fee waiver |

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| No client-driven privilege | Fee waiver + teacher role admin-only |
| No raw POST privilege fields | Form data only; no `$_POST` merge |
| Chatbot authZ | Session record-id list, not email |
| Payment authZ | Amount from DB; mock blocked on live; owner check |
| Resource binding | Support courseid; live-class courseid constant |
| Prefs IDOR | Non-privileged users forced to own `id`/`userid` on prefs subpages + messaging |
| Module `?id=` | CM id + enrolment/capability (not user object IDOR) |
| change_password `?id=` | **Hardened** — course id ignored (SITEID); password = session user |
| Screenshot IDOR “Roopa” | Different app (`/var/www/html/change_password.php` + `find_user_by_id`) — dispute |
| `qlogin` | Not present; query keys stripped |
| Attendance / assign `?id=` to invalid CM | Access fail + SQL text = debug leak (not IDOR) |
| customcert `downloadown` | Early login + guest blocked; SQL text = debug leak |
| Calendar event AJAX | `calendar_view_event_allowed()` + personal ownership + sesskey |
