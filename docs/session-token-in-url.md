# 17. Session token in URL

## Finding

| Field | Report |
|-------|--------|
| Title | Session token in URL |
| Impact | MEDIUM |
| Example URL | `/lib/ajax/service.php?sesskey=…&info=core_message_…` (also `core_message_get_conversation_messages`) |
| Hosts | Report may cite `staginglms.cci.gov.in` — retest IIIDEM / `staginglms.eci.gov.in` |

> Do not transmit session identifiers in URLs (browser history, Referer, proxy logs).

### Important distinction

| Token | What it is | Must not be in URL |
|-------|------------|-------------------|
| `MoodleSession*` cookie | PHP session id | **Yes — cookie only** (already enforced) |
| `sesskey` | CSRF token (not the login session id) | Prefer **not** in query string (this remediaiton) |

PoC `service.php?sesskey=…` is Moodle core AJAX CSRF, not the session cookie.

Same report page (debug mode / CWE-489): [debug-mode-staging.md](debug-mode-staging.md).

## Controls

### 1. PHP session cookie only (`config.php`)

| Setting | Value |
|---------|--------|
| `session.use_cookies` | `1` |
| `session.use_only_cookies` | `1` |
| `session.use_trans_sid` | `0` |
| `$CFG->usesid` | `false` |

Query params named like session cookies (`MoodleSession`, `sid`, `PHPSESSID`, …) are stripped from `$_GET`.

### 2. AJAX `sesskey` → header (theme)

| Piece | Role |
|-------|------|
| `javascript/ajax_sesskey_header.js` | For `/lib/ajax/service.php` only: send `X-Moodle-Sesskey` **and** keep query `sesskey` |
| `config.php` + `import_sesskey_from_header` | Maps header → request **only if** sesskey is missing (never overwrites filemanager POST) |
| CORS | `Access-Control-Allow-Headers` includes `X-Moodle-Sesskey` |

> Do not apply the header rewrite to `repository/draftfiles_ajax.php` — overwriting that endpoint’s POST `sesskey` caused `invalidsesskey` on the file picker.

Loaded early (theme `$THEME->javascripts` + `<head>` script).

### 3. Custom IIIDEM AJAX — POST body

Chatbot, FAQ, live quiz, etc. already send `sesskey` in POST, not the query string.

### 4. Destructive actions — POST forms

Calendar delete, cancel live class, FAQ delete, etc. — not GET + sesskey links.

### 5. Logout / contact

| Control | Detail |
|---------|--------|
| User menu / login info | `theme_iiidem2\output\core_renderer` strips `sesskey` from `logout.php` hrefs |
| Click → POST | `javascript/logout_post.js` posts `sesskey` in the form body (uses `M.cfg.sesskey` if needed) |
| Footer | Logout URL built without query `sesskey` |

**Instance 2 (report):** `GET /login/logout.php?sesskey=…` — after remediaiton, logout is **POST** without sesskey in the query string.

### 6. AJAX (Instance 1)

`/lib/ajax/service.php?sesskey=…` plus header `X-Moodle-Sesskey` (query kept as fallback so proxies cannot cause `missingparam`).

## Note on Moodle core

Without the JS patch, core `lib/amd/src/ajax.js` still builds `?sesskey=`. With theme `iiidem2`, browsers also send `X-Moodle-Sesskey`. The query param is retained as a **compat fallback** so `missingparam` cannot occur if the header is stripped at the edge.

**HTML source PoC (report):** `<a href="…/login/logout.php?sesskey=…">Log out</a>` and similar `/login/…?sesskey=` links — stripped from user menu / login info; logout uses POST.

Recommendations (Secure / HttpOnly / SameSite cookies): already forced — see [cookie-httponly.md](cookie-httponly.md), [https-sensitive-data.md](https-sensitive-data.md).

Next finding on same pages: [verbose-error-messages.md](verbose-error-messages.md) (#18).

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Theme ≥ `2024100984`. Hard-refresh browsers.

## Verify

1. Log in → DevTools → Network → trigger messaging/AJAX  
2. `service.php` request may still include `sesskey=` (compat fallback) **and** header `X-Moodle-Sesskey`  
3. Request must succeed (no `missingparam` / `invalidsesskey`)  
4. User menu → Log out → request is **POST** `/login/logout.php` with `sesskey` in form body (not query string)  
5. Cookie `MoodleSession` still present; never in the query string  

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| No session id in URL | `usesid=false` + cookie-only PHP session |
| AJAX CSRF dual delivery | `ajax_sesskey_header.js` (header + query fallback) + early header import |
| Logout CSRF off URL | `logout_post.js` + renderer strip |
| Custom apps | POST body sesskey |
| Referrer | `strict-origin-when-cross-origin` |
