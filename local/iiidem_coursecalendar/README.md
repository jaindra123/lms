# IIIDEM Course Calendar — Google Calendar Integration

Developer documentation for `local_iiidem_coursecalendar`.

This plugin links Moodle live-class scheduling (Webex activity or Webex URL) to **Google Calendar**, creates events, and notifies enrolled students.

---

## 1. What it does

When a teacher/admin **creates or updates** a live class:

1. Moodle creates/updates a **Google Calendar event** (title, date/time, Webex join link).
2. Moodle notifies **enrolled students** by email (and Moodle messaging) with session details.
3. On **Google Workspace** with Domain-Wide Delegation, Google can also send **Accept / Decline** calendar invites to students.
4. On **personal Gmail** + service account, Google **cannot** invite attendees; students still get the **Moodle reminder email**.

| Trigger | Result |
|---------|--------|
| Save **Webex activity** (`mod_webexactivity`) | Auto Google event + student notify |
| Save **URL activity** whose External URL contains `webex.com` | Same (uses Timeline reminder date as start) |
| Delete that activity | Cancels Google event (if linked) |
| Course admin → **Schedule live class** | Manual schedule form (optional) |
| Course admin → **Course schedule calendar** | Paste public Google Calendar iCal URL (read-only display + Moodle notify) |

---

## 2. End-to-end flow (Webex → Google → students)

```
Teacher saves Webex (or Webex URL)
        │
        ▼
Moodle event: course_module_created / updated
        │
        ▼
local_iiidem_coursecalendar\observer
        │
        ▼
manager::sync_from_course_module()
        │
        ├── Collect enrolled student emails (exclude teachers/guests)
        ├── Google Calendar API: create/update event
        │     ├── With attendees + sendUpdates=all  (Workspace + DWD)
        │     └── Without attendees if SA invite blocked (personal Gmail)
        └── Moodle email + message to students (always attempted)
```

**Student experience (typical / personal Gmail setup):**

- Reminder email on their Moodle profile email (title, when, Webex link).
- Event visible on the **site’s configured Google Calendar** (admin/teacher calendar).
- Not auto-added to the student’s own Google Calendar unless Workspace + DWD is configured.

---

## 3. Google Cloud setup (required)

### 3.1 Create / open a Google Cloud project

1. Go to [Google Cloud Console](https://console.cloud.google.com/).
2. Create or select a project.

### 3.2 Enable Google Calendar API

1. **APIs & Services → Library**.
2. Search **Google Calendar API**.
3. Click **Enable**.

Direct pattern (replace `PROJECT_ID` with numeric project id if needed):

`https://console.developers.google.com/apis/api/calendar-json.googleapis.com/overview?project=PROJECT_ID`

If API is disabled, Moodle fails with a 403 like: *Google Calendar API has not been used in project … or it is disabled*.

### 3.3 Create a service account

1. **APIs & Services → Credentials → Create credentials → Service account**.
2. Name it (e.g. `moodle-calendar`).
3. Open the service account → **Keys → Add key → Create new key → JSON**.
4. Download the JSON file and store it securely (never commit to git).

You will need from the JSON:

- `client_email` (e.g. `moodle-calendar@….iam.gserviceaccount.com`)
- `private_key`
- `project_id` / `private_key_id`

### 3.4 Create or choose a Google Calendar

1. Open [Google Calendar](https://calendar.google.com/).
2. Use an existing calendar or **Create new calendar** (recommended: e.g. `IIIDEM Live Classes`).
3. Open that calendar → **Settings and sharing**.

### 3.5 Share the calendar with the service account

1. **Shared with → Add people and groups**.
2. Paste the service account email (`….iam.gserviceaccount.com`).
3. Permission: **Make changes to events**.
4. Send / Share.

Without this share, event create fails (permission / not found errors).

### 3.6 Copy Calendar ID

1. Same calendar → **Integrate calendar**.
2. Copy **Calendar ID**  
   - Personal: often `you@gmail.com`  
   - Secondary: often `….@group.calendar.google.com`

---

## 4. Moodle configuration

**Path:** Site administration → Plugins → Local plugins → **IIIDEM course calendar**  
(` /admin/settings.php?section=local_iiidem_coursecalendar `)

| Setting | Required | Description |
|---------|----------|-------------|
| **Service account JSON** | Yes | Paste the **full** JSON key file contents |
| **Google Calendar ID** | Yes | From Integrate calendar |
| **Impersonate user email** | Optional | Google Workspace only (see §6) |
| **Auto-create Google Calendar for Webex / Webex URL activities** | Yes (ON) | Must be enabled or sync does nothing |
| **Default duration (minutes) for URL Webex sessions** | Optional | Default `60` (URL modules have no duration field) |

After changing settings: **Save changes**, then purge caches if needed:

```bash
php admin/cli/purge_caches.php
# or with DDEV:
ddev exec php admin/cli/purge_caches.php
```

---

## 5. Teacher / admin usage

### Option A — Webex activity (preferred if Webex plugin is used)

1. Course → Turn editing on → **Add an activity or resource → Webex**.
2. Set name, **start time**, **duration**, save.
3. Plugin creates/updates Google event and notifies students.

### Option B — URL with Webex link (current IIIDEM pattern)

1. Course → **Add → URL**.
2. **External URL** = Webex join link (`https://….webex.com/…`).
3. Enable **Set reminder in Timeline** and set the live session date/time  
   (this becomes the Google event start).
4. Save.

If Timeline reminder is empty, Google sync is **skipped** for URL activities.

### Option C — Manual “Schedule live class”

Course administration → **Schedule live class**  
(`/local/iiidem_coursecalendar/schedule_event.php?courseid=ID`)

### Option D — Link existing public calendar (iCal)

Course administration → **Course schedule calendar**  
Paste a public Google Calendar share/embed/iCal URL for course-page display + Moodle notifications on change (read-only feed; not the API write path).

---

## 6. Personal Gmail vs Google Workspace

### Personal Gmail (`@gmail.com`) — current typical setup

| Feature | Supported? |
|---------|------------|
| Create event on shared calendar | Yes |
| Moodle reminder email to enrolled students | Yes |
| Google “Accept” invite to students’ calendars | **No** |

Google error if attendees are forced:

> Service accounts cannot invite attendees without Domain-Wide Delegation of Authority.

Plugin behaviour: creates the event **without** Google attendees, still sends **Moodle** emails.

### Google Workspace (org domain) — for Google Accept invites

1. Use a Workspace calendar / Workspace user.
2. Google Admin → Domain-wide Delegation for the service account client ID.
3. Scope: `https://www.googleapis.com/auth/calendar` (and events if required).
4. In Moodle, set **Impersonate user email** to a Workspace user (e.g. `training@yourdomain.gov.in`).
5. Keep calendar shared / owned appropriately.

Then Google can add enrolled students as attendees and send invitation emails (Accept / Decline / Maybe).

---

## 7. Who gets notified

`manager::get_enrolled_student_emails()` / notify helpers:

- Enrolled users in the course.
- Skip guests, deleted, suspended.
- Skip users with `local/iiidem_coursecalendar:manage` (teachers/managers).
- Require a **valid email** on the Moodle user profile.

Students without email are not invited / not emailed.

---

## 8. Key files

```
local/iiidem_coursecalendar/
  README.md                          ← this file
  settings.php                       ← admin settings (JSON, calendar id, autosync)
  schedule_event.php                 ← manual live-class form
  manage.php                         ← paste iCal / manage linked calendar
  lib.php                            ← course admin nav links
  classes/
    google_calendar_client.php       ← JWT auth + Calendar API (create/update/delete)
    manager.php                      ← sync from Webex/URL, attendees, Moodle notify
    observer.php                     ← hooks course_module_created/updated/deleted
    form/liveclass_form.php
    form/calendar_form.php
    ical_parser.php                  ← public iCal read path
  db/
    events.php                       ← observer registration
    install.xml / upgrade.php        ← tables including local_iiidem_coursecalendar_event
  lang/en/local_iiidem_coursecalendar.php
```

### Database

- `local_iiidem_coursecalendar` — per-course pasted Google iCal/embed URLs.
- `local_iiidem_coursecalendar_event` — API-scheduled live classes (`cmid`, `webexid`, `googleeventid`, times, `invites_sent`, …).

---

## 9. Local (DDEV) testing

1. Configure JSON + Calendar ID + autosync ON (as above).
2. Share calendar with service account.
3. Enable Calendar API on the Cloud project.
4. Create/save a Webex URL or Webex activity with a future date.
5. Expect:
   - Green success notice (or short error notice — no stack dump).
   - Event on the configured Google Calendar.
   - Moodle student emails in **Mailpit** (`ddev mailpit`) — **not** Google invite emails on personal Gmail.

```bash
ddev mailpit
```

Google invitation emails (Accept) never appear in Mailpit; they come from Google only when Workspace + DWD works.

---

## 10. Production checklist

- [ ] Google Calendar API enabled on the correct Cloud project  
- [ ] Service account JSON pasted in Moodle (full file)  
- [ ] Calendar ID set  
- [ ] Calendar shared with service account (**Make changes to events**)  
- [ ] Autosync checkbox **ON**  
- [ ] SMTP / email configured so Moodle can reach real student inboxes  
- [ ] Students have correct emails on profiles  
- [ ] (Optional) Workspace + Domain-Wide Delegation + Impersonate email for Google Accept invites  
- [ ] Do **not** commit the service account JSON to git  

---

## 11. Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Nothing happens on save | Autosync OFF | Enable autosync in plugin settings |
| `Could not obtain Google access token: Bad Request` | OAuth/JWT transport or bad JSON | Re-paste full JSON; ensure Calendar API enabled; plugin uses native cURL for token |
| Calendar API not used / disabled | API off in Cloud project | Enable Calendar API, wait a few minutes |
| Permission / not found on create | Calendar not shared | Share calendar with SA email, Make changes to events |
| Stack traces on `modedit.php` | Old debugging() paths | Current code logs quietly + short notification |
| Event on admin calendar, no Google Accept mail | Personal Gmail + SA | Expected; use Moodle email or Workspace + DWD |
| URL Webex saved, no event | Timeline reminder empty | Enable Set reminder in Timeline with date/time |
| Looking at wrong day/time | Timezone difference | Check event time in Moodle timezone vs Google timezone |

---

## 12. Security notes

- Treat the service account JSON as a **secret** (rotate key if exposed).
- Restrict who can manage calendars (`local/iiidem_coursecalendar:manage`).
- Prefer a dedicated calendar for live classes, not a personal day-to-day calendar, when possible.
- On production, use HTTPS and proper SMTP.

---

## 13. Upgrade / deploy

1. Deploy `local/iiidem_coursecalendar` (and theme hooks if any).
2. Run Moodle upgrade:

```bash
php admin/cli/upgrade.php --non-interactive
```

3. Configure settings on the server (JSON + Calendar ID).  
4. Purge caches.  
5. Test one Webex save on a course with a test student email.

---

## 14. Product summary (for stakeholders)

**Aim:** Teacher creates Webex → enrolled students get a reminder on their registered email (date, time, join link), and the session is recorded on the institute Google Calendar.

**With current personal Gmail + service account:** that aim is met via **Moodle email + Google event on the shared calendar**.

**Google “Accept” into each student’s own calendar** requires **Google Workspace + Domain-Wide Delegation**, not personal Gmail alone.
