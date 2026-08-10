# Session token in URL

## Finding

> Do not transmit session identifiers in URLs.

Session IDs and CSRF session tokens in query strings leak via browser history, Referer headers, and proxy logs.

## Controls

### 1. PHP / Moodle session cookie only (`config.php`)

| Setting | Value |
|---------|--------|
| `session.use_cookies` | `1` |
| `session.use_only_cookies` | `1` |
| `session.use_trans_sid` | `0` |
| `$CFG->usesid` | `false` |

Query parameters named like session cookies (`MoodleSession`, `sid`, `PHPSESSID`, …) are stripped from `$_GET` before bootstrap.

### 2. Custom AJAX — `sesskey` in POST body only

| Client | Change |
|--------|--------|
| Homepage chatbot history / admin list | POST |
| Admin chatbot toast poll | POST |
| Support FAQ search | POST |
| Live quiz poll / teacher stats | POST |

Submit/reply actions were already POST.

### 3. Destructive actions — POST forms (not GET links)

- Calendar delete (`local_iiidem_coursecalendar/manage.php`)
- Cancel live class (`schedule_event.php`)
- Course FAQ delete confirm
- Webex “Connect” (admin settings)

### 4. OAuth state ≠ session token

Webex authorize `state` is a random value stored in `$SESSION`, not `sesskey()`.

### 5. Logout / contact URLs

Footer logout URL no longer embeds `sesskey`. Theme contact toggle href no longer embeds `sesskey` (AJAX uses `M.cfg.sesskey`).

## Note on Moodle core

Some core UI (e.g. user-menu logout) may still append `sesskey` on GET. Custom IIIDEM surfaces above do not. PHP session identifiers are never accepted from the URL.

## Deploy

```bash
php admin/cli/purge_caches.php
```

(Hard-refresh browsers so updated JS is loaded.)
