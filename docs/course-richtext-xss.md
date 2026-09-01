# 39. Improper Input Validation – Course / question rich text (stored XSS)

## Finding

| Field | Report |
|-------|--------|
| Title | Improper Input Validation – Additional Affected Parameter/Endpoint |
| Impact | MEDIUM / CVSS 4.3 |
| CWE | [CWE-693](https://cwe.mitre.org/data/definitions/693.html) / [CWE-79](https://cwe.mitre.org/data/definitions/79.html) |
| OWASP | A05:2025 – Injection |
| URLs | `/course/view.php?id=4`, `/course/edit.php?id=4`, `/question/bank/editquestion/question.php`, `/user/index.php?…`, `/grade/report/grader/index.php?id='…` |

> Malicious input (scripts/commands) can exploit vulnerabilities if not validated. Sanitize and encode user input.

## PoC

| Instance | Evidence |
|----------|----------|
| 1 | `course/view.php?id=4` — Curriculum / overview showed repeated `<script>alert(1)</script>` text |
| 2 | `course/edit.php?id=4` — **Instructor Data** (course custom field / TinyMCE) contained the same payload |
| 3–4 | Frontpage cards / `alert(1)` course title; question bank URL cited |
| 5 | Question edit — **General feedback** TinyMCE + **ID number** field with `<script>alert(1)</script>` |
| 6 | Participants `/user/index.php?…` — unified filter chip showed `<script>alert(1)</script>` |

**Note:** With CSP + Mustache escaping, many payloads **display as text** rather than executing `alert()`. Auditors still correctly flag **acceptance and storage** of dangerous markup. Grader `id='` is separate — already `PARAM_INT` ([os-command-injection.md](os-command-injection.md), [verbose-error-messages.md](verbose-error-messages.md)).

## Resolution

### 1. Purify on save (theme `after_config`)

For POST to `/course/edit.php`, `/course/editadvanced.php`, and question edit scripts:

| Field | Action |
|-------|--------|
| `fullname`, `shortname` | Strip all HTML (`purify_plain_title`) |
| `summary_editor[text]` | Moodle `clean_text` / HTML Purifier + script/iframe strip |
| `customfield_*` / `customfield_*_editor` (e.g. Instructor Data) | Same HTML purify |
| Question `questiontext` / `generalfeedback` / nested answer `text` | Same HTML purify |
| Question `idnumber` | Reject markup → empty / `PARAM_NOTAGS` only |
| Participants / table `keywords` (string filters) | Core `string_filter` strips tags; no HTML chips |

### 2. Safer output (theme)

| Surface | Control |
|---------|---------|
| Course summary on curriculum / detail | `format_text` + `purify_html_fragment` before `{{{ coursesummary }}}` |
| Frontpage course cards | Plain text summary; strip tags; titles via `purify_plain_title` |
| Instructor bios / FAQs | Purified HTML only |

### 3. Defence in depth

- Site CSP restricts inline script execution ([security-headers.md](security-headers.md))
- DOMPurify in TinyMCE ≥ 3.2.7 ([vulnerable-javascript-dependency.md](vulnerable-javascript-dependency.md) #38)

## Cleanup of existing PoC data (ops)

After deploy, **re-save** affected courses (edit → Save) so stored Instructor Data / summary are re-purified, or manually clear the script payload from course settings. Purge caches:

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Optional SQL (staging only, after backup) to find dirty summaries:

```sql
SELECT id, fullname FROM mdl_course WHERE summary LIKE '%<script%' OR fullname LIKE '%alert(%';
```

## Verify

1. Edit course → Instructor Data / summary → paste `<script>alert(1)</script>` → Save.
2. Re-open edit: payload must be **removed** (empty or harmless markup), not stored raw.
3. `course/view.php` and frontpage cards: **no** raw `<script>` in HTML; **no** `alert()` dialog.
4. Course fullname with `<script>` → saved as plain text without tags.
5. Question **General feedback** + **ID number** with script → save → feedback purified, idnumber empty/clean.
6. Participants filter keyword `<script>alert(1)</script>` → chip must not keep raw tags (stripped); no alert.
7. Grader `id='` → generic error only (not this finding’s core issue).

## Related

- [input-validation-xss.md](input-validation-xss.md) (contact / search / register)
- [input-validation.md](input-validation.md)
- [vulnerable-javascript-dependency.md](vulnerable-javascript-dependency.md) (#38 DOMPurify)
- [form-action-hijacking-xss.md](form-action-hijacking-xss.md)
