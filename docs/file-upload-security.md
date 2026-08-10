# File upload vulnerability — whitelist & content inspection

## Finding

| Field | Report value |
|-------|----------------|
| Title | File Upload Vulnerability / Unrestricted File Upload |
| URL cited (in scope) | `/user/files.php` (Private files) |
| PoC | Upload `info1.php` / `shell.php`; polyglot `content.pdf` with PHP; PDF `/JS`; double ext `mystest.php.pdf`; mass upload |
| CWE | CWE-434 (+ rate limit / DoS) |
| Host | `staginglms.cci.gov.in` (confirm against real LMS host) |

> Inspect the content of uploaded files, and enforce a whitelist of accepted, non-executable content types.

## Out of scope (different products — dispute)

These PoC paths are **not** this Moodle LMS codebase:

| PoC path | Product |
|----------|---------|
| `/wp-content/plugins/wp-file-manager/lib/php/connector.minimal.php` | WordPress **wp-file-manager** |
| `/api/v1/file-upload/upload` | Separate REST upload API (not Moodle) |
| `/filemanager/api.php?action=upload` | Non-Moodle file manager API (`{"status":"success","file_name":…}`) |

Moodle Private files use `/user/files.php` + `/repository/repository_ajax.php?action=upload`, not `/filemanager/api.php`. A UI label “Private files” on another product does not make that PoC an LMS finding.

Do not treat those as LMS findings. Remediate on the WordPress / API / other-app hosts separately.

## PoC analysis (Moodle Private files)

1. **`.php` via repository AJAX** — draft upload can use `accepted_types=*` before form save; form-only whitelist is insufficient.
2. **Polyglot PDF** — filename `content.pdf` with body `<?php echo system($_GET['cmd']); ?>` must be rejected by **content** inspection, not extension alone.
3. **PDF `/JS (app.alert(...))`** — stored XSS risk in PDF viewers; reject embedded PDF JavaScript for uploads.
4. **Double extension** `mystest.php.pdf` — block any filename segment that is a dangerous extension.
5. **Rate limit (Instance 2)** — mass `shell.php` uploads to fill storage; throttle user draft/private creates.

Even if a bad file were stored, Moodle keeps it under **dataroot** and serves via `pluginfile.php` (download), not as executable PHP — provided dataroot is outside the docroot (see `docs/directory-listing.md`).

## Fixes applied

### 1. File-storage hook (primary — blocks AJAX + polyglots)

**Files:** `upload_security.php`, `hook_listener.php`, `db/hooks.php`

On `\core_files\hook\before_file_created`:

| Check | Behaviour |
|-------|-----------|
| Blocked extensions | `.php`, `.phtml`, `.sh`, `.html`, `.js`, `.exe`, … |
| Double extension | e.g. `shell.php.pdf` |
| Content scan (64 KB) | Reject `<?php` / `<?=` / `<script` |
| PDF JavaScript | Reject `/JS` or `/JavaScript` in `.pdf` |
| Rate limit (user draft/private) | **40 / 10 min**, **120 / hour** per user |

### 2. Private files form whitelist

**File:** `theme/iiidem2/classes/form/private_files.php` — documents/images only (**no zip/archives**); validate on save.

Also enforced at storage: filename length ≤ 200; reject control/path characters in names.

### 3. Materials / assignments / theme images

Existing `upload_security` whitelists (assignments may still allow `.zip` for submissions).

## Size / directory execute (ops + Moodle)

| Recommendation | Status |
|----------------|--------|
| Size limit | Moodle `$CFG->userquota` / `maxbytes` on file areas |
| No execute in upload dir | Dataroot outside docroot + nginx/Apache deny (see `docs/directory-listing.md`) |
| Whitelist extensions | Private files + materials + hook blocklist |
| Filename sanitisation | `clean_filename` + theme length/charset checks |

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Theme version ≥ `2024100968`.

## Verify

| Test | Expected |
|------|----------|
| Upload `info1.php` / `shell.php` | Rejected |
| Upload `content.pdf` containing `<?php` | Rejected |
| Upload PDF with `/JS (app.alert('XSS'))` | Rejected |
| Upload `mystest.php.pdf` | Rejected |
| Upload `.sh` | Rejected |
| Burst >40 draft uploads / 10 min | Rate limited (`ratelimited`) |
| Normal PDF / DOCX | Accepted |

```bash
curl -sI https://YOUR-HOST/moodledata/
# Expect 403
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Storage-time block | `before_file_created` → `assert_safe_file_create` |
| Polyglot / PDF-JS | 64 KB content scan |
| Double extension | `filename_has_blocked_token` |
| Upload rate limit | `rate_limit` on user draft/private |
| Form whitelist | `private_files` override |
| No PHP execution | Dataroot / not in docroot |
| WP / `/api/v1/` PoCs | Out of scope for this LMS |
