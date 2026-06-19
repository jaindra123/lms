
# Local plugin — local/coursereviews/

local/coursereviews/
├── version.php              # Plugin version (run upgrade)
├── db/install.xml           # Creates table
├── lang/en/local_coursereviews.php
├── lib.php                  # Core logic + Mustache context
└── submit.php               # Form POST handler (save review)

#  Theme — theme/iiidem2/
theme/iiidem2/
├── settings.php                              # Admin testimonial settings
├── lib.php                                   # get_course_testimonials_context()
│                                             # get_course_student_reviews_context()
├── layout/course.php                         # Merges context for logged-in users
├── templates/
│   ├── course_drawers.mustache               # Main course page shell
│   ├── course/testimonials.mustache          # Admin cards
│   └── course/student_reviews.mustache       # Real reviews + form
├── amd/src/course_reviews.js                 # Star picker UI
└── style/custom.css                          # Card + star styles



This document describes how the **student course review / star rating system** works on IIIDEM course pages (for example `/course/view.php?id=4`).

It is written for developers maintaining or extending the feature.

---

## Overview

The site uses **two related but separate** review-style sections on the course page:

| System | Package | Data | Who creates content |
|--------|---------|------|---------------------|
| **Admin testimonials** | `theme_iiidem2` | Theme settings (`config_plugins`) | Site admin via Theme settings |
| **Student reviews** | `local_coursereviews` | Database table `local_coursereviews` | Enrolled students via form on course page |

This README covers **student reviews** (`local_coursereviews`) and how the **theme** displays them.

Admin testimonials are configured under:

**Site administration → Appearance → Themes → IIIDEM2 → Course testimonials**

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│  /course/view.php?id=N                                          │
│  layout: theme/iiidem2/layout/course.php                        │
│  template: theme/iiidem2/templates/course_drawers.mustache       │
└────────────────────────────┬────────────────────────────────────┘
                             │
         ┌───────────────────┴───────────────────┐
         ▼                                       ▼
 theme_iiidem2_get_course_              theme_iiidem2_get_course_
 student_reviews_context()             testimonials_context()
         │                                       │
         ▼                                       ▼
 local/coursereviews/lib.php            theme/iiidem2/lib.php
         │                               (reads theme settings)
         ▼
 mdl_local_coursereviews
         ▲
         │ POST
 local/coursereviews/submit.php
```

**Design principle**

- **`local_coursereviews`** — database, validation, save logic, submit endpoint (Moodle local plugin).
- **`theme_iiidem2`** — Mustache templates, CSS, star-picker JavaScript, page placement.

---

## File reference

### Local plugin (`local/coursereviews/`)

| File | Purpose |
|------|---------|
| `version.php` | Plugin version; bump and run upgrade after changes |
| `db/install.xml` | Creates `local_coursereviews` table |
| `lib.php` | Permissions, save/load, Mustache context builder |
| `submit.php` | HTTP POST handler for new/updated reviews |
| `lang/en/local_coursereviews.php` | Plugin language strings (errors, success messages) |

### Theme integration (`theme/iiidem2/`)

| File | Purpose |
|------|---------|
| `lib.php` | `theme_iiidem2_get_course_student_reviews_context()` bridges to plugin |
| `layout/course.php` | Merges review context into course page template |
| `templates/course_drawers.mustache` | Includes review section; loads AMD module |
| `templates/course/student_reviews.mustache` | Review form + review cards |
| `templates/course/testimonials.mustache` | Separate admin testimonial cards |
| `amd/src/course_reviews.js` | Interactive 1–5 star picker |
| `style/custom.css` | `.iiidem-student-reviews`, `.iiidem-testimonial-card`, stars |
| `lang/en/theme_iiidem2.php` | UI labels (section title, form labels, prompts) |

---

## Database

**Table:** `{prefix}local_coursereviews`  
**XMLDB:** `local/coursereviews/db/install.xml`

| Column | Type | Description |
|--------|------|-------------|
| `id` | int | Primary key |
| `courseid` | int | Course ID (`course.id`) |
| `userid` | int | Reviewer (`user.id`) |
| `rating` | int | Star rating 1–5 |
| `reviewtext` | text | Review body |
| `timecreated` | int | Unix timestamp (first submit) |
| `timemodified` | int | Unix timestamp (last update) |

**Constraints**

- Unique index on `(courseid, userid)` — **one review per user per course** (updates overwrite).
- Foreign keys to `course` and `user`.

**Inspect data (example)**

```sql
SELECT r.*, u.firstname, u.lastname
  FROM mdl_local_coursereviews r
  JOIN mdl_user u ON u.id = r.userid
 WHERE r.courseid = 4
 ORDER BY r.timemodified DESC;
```

---

## Permissions

Reviews can be submitted only when `local_coursereviews_user_can_review()` returns true:

1. User is logged in (not guest).
2. Course is not the site home course (`SITEID`).
3. User has **active enrolment** in the course:

```php
is_enrolled(context_course::instance($course->id), $userid, '', true);
```

There is no separate Moodle capability yet. Teachers/admins who are not enrolled follow the same rule unless they are enrolled.

---

## Request flows

### Read (page load)

1. User opens `/course/view.php?id={courseid}`.
2. `theme/iiidem2/layout/course.php` calls:

   ```php
   theme_iiidem2_get_course_student_reviews_context($COURSE);
   ```

3. Theme loads `local/coursereviews/lib.php` and calls:

   ```php
   local_coursereviews_get_course_context($course);
   ```

4. Plugin loads all reviews for the course, computes average rating, and sets flags:
   - `canreview` — show submit form
   - `needlogin` — show login prompt
   - `needenrol` — logged in but not enrolled
   - `hasreviewitems`, `reviewcount`, `averagerating`, etc.

5. `course_drawers.mustache` renders `course/student_reviews.mustache` when `showstudentreviewsection` is true.

**Public course view** (guests before login) uses `theme_iiidem2_render_public_course_view()` in `theme/iiidem2/lib.php`, which merges the same context.

### Write (submit review)

1. Enrolled student fills form in `#student-reviews` section.
2. Form POSTs to `/local/coursereviews/submit.php` with:
   - `sesskey`
   - `courseid`
   - `rating` (1–5, set by star picker JS)
   - `reviewtext`

3. `submit.php`:
   - `require_login()` + `require_sesskey()`
   - `require_course_login($course)`
   - `local_coursereviews_user_can_review($course)`
   - Validates rating and non-empty text
   - `local_coursereviews_save_review()` — insert or update
   - Redirects to `/course/view.php?id={id}#student-reviews` with success notification

---

## Mustache context (student reviews)

Key variables for `theme_iiidem2/course/student_reviews`:

| Variable | Type | Description |
|----------|------|-------------|
| `showstudentreviewsection` | bool | Render the whole section |
| `studentreviewstitle` | string | Section heading |
| `canreview` | bool | User may submit; show form |
| `needlogin` | bool | Show login CTA |
| `needenrol` | bool | Show enrol message |
| `hasreviewitems` | bool | At least one review exists |
| `reviewitems` | array | Cards (name, quote, stars, image, …) |
| `reviewcount` | int | Number of reviews |
| `hasaveragerating` | bool | Show average block |
| `averageratingdisplay` | string | e.g. `4.5` |
| `averagestarrating` | array | `{filled: true/false}` × 5 |
| `hasuserreview` | bool | Current user already reviewed |
| `userrating` | int | User's current rating |
| `userreviewtext` | string | User's current text |
| `starpicker` | array | Star buttons for form |
| `reviewformurl` | string | `/local/coursereviews/submit.php` |
| `sesskey` | string | CSRF token |
| `courseid` | int | Course ID for hidden field |

Each `reviewitems[]` entry uses the same card structure as admin testimonials (`name`, `subtitle`, `quote`, `starrating`, `imageurl`, `isown`).

---

## Frontend (star picker)

**AMD module:** `theme_iiidem2/course_reviews`  
**Source:** `theme/iiidem2/amd/src/course_reviews.js`  
**Build:** `theme/iiidem2/amd/build/course_reviews.min.js`

Behaviour:

- Finds `.iiidem-review-form` on the page.
- Clicking `.iiidem-review-stars__btn` sets hidden input `name="rating"`.
- Adds/removes `.is-active` on stars for visual feedback.

Loaded from `course_drawers.mustache` when `showstudentreviewsection` is true.

After editing AMD source, rebuild minified JS (Grunt) or update `build/course_reviews.min.js`, then purge caches.

---

## Page placement

In `course_drawers.mustache` (non-editing mode), order is:

1. Curriculum  
2. Instructors (if any)  
3. FAQ  
4. Admin testimonials (if configured)  
5. **Student reviews** (`#student-reviews`)  
6. Footer  

---

## Installation and upgrade

From Moodle root:

```bash
php admin/cli/upgrade.php
php admin/cli/purge_caches.php
```

Bump versions when you change code:

- `local/coursereviews/version.php`
- `theme/iiidem2/version.php` (if theme templates/AMD/CSS change)

---

## Development workflow

### Add a new field (example: `status` for moderation)

1. Create `local/coursereviews/db/upgrade.php` + bump `version.php`.
2. Add column in upgrade script (e.g. `status` tinyint, default 1 = published).
3. Update `local_coursereviews_save_review()` and `get_course_context()` to filter by status.
4. Add admin UI or capability for moderators (not implemented yet).
5. Run `php admin/cli/upgrade.php`.

### Change who can review

Edit `local_coursereviews_user_can_review()` in `lib.php`. Examples:

- Require fee enrolment: call `theme_iiidem2_user_has_active_fee_enrolment()` from theme (add wrapper in plugin to avoid tight coupling, or move check to theme before showing form).
- Require course completion: use `completion_info` in the same function.

### Switch to AJAX submit

1. Add `local/coursereviews/ajax/submit.php` with `define('AJAX_SCRIPT', true);`
2. Return JSON `{success: true}`.
3. Update `course_reviews.js` to `fetch()` instead of form POST.
4. Keep `submit.php` for non-JS fallback or remove after testing.

---

## Security checklist

- [x] `require_sesskey()` on submit  
- [x] `require_login()` + `require_course_login()`  
- [x] Enrolment check before save  
- [x] `PARAM_INT` / `PARAM_TEXT` on input  
- [x] Rating clamped to 1–5  
- [x] Reviewer name abbreviated (first name + last initial)  
- [ ] Capability-based moderation (not implemented)  
- [ ] Rate limiting / spam protection (not implemented)  

---

## Testing

| Scenario | Expected |
|----------|----------|
| Guest | Sees reviews; login prompt; no form |
| Logged in, not enrolled | Sees reviews; “Enrol to leave a review” |
| Enrolled student | Form visible; can submit |
| Second submit by same user | Updates existing row; “review updated” message |
| Invalid sesskey | Error / rejected |
| Not enrolled POST to submit.php | `notenrolled` exception |

**Manual test URL:**  
`https://your-site/course/view.php?id=4#student-reviews`

---

## Troubleshooting

| Problem | Check |
|---------|--------|
| Section not visible | `showstudentreviewsection` false only on SITEID; plugin `lib.php` readable |
| Form not shown | User must be actively enrolled (`is_enrolled(..., true)`) |
| Stars don’t click | AMD `course_reviews` loaded; purge caches; check browser console |
| Submit fails | PHP error log; enrolment; empty `reviewtext`; sesskey |
| Plugin table missing | Run `php admin/cli/upgrade.php` |
| Changes not on page | `php admin/cli/purge_caches.php` + hard refresh |

---

## Related code (not student reviews)

- **Curriculum preview + completion:** `theme/iiidem2/ajax/mark_activity_viewed.php`
- **Course fee / payment gate:** `theme_iiidem2_get_course_fee_payment_context()`
- **FAQ:** `local/coursefaq` (separate plugin)

---

## Version history

| Component | Version (approx.) | Notes |
|-----------|-------------------|--------|
| `local_coursereviews` | `2026060800` | Initial release: table, submit, list, average |
| `theme_iiidem2` | `2024100784+` | Templates, AMD star picker, CSS |

---

## Contact / ownership

Maintained as part of the IIIDEM certification Moodle project.  
For changes, edit `local/coursereviews` for data/rules and `theme/iiidem2` for presentation.
