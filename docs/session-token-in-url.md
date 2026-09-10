# 17. Session token in URL

## Finding

| Field | Report |
|-------|--------|
| Title | Session token in URL |
| Impact | MEDIUM |
| Example URL | `/lib/ajax/service.php?sesskey=…&info=core_message_…` (also `core_message_get_conversation_messages`) |
| Hosts | Report may cite `staginglms.cci.gov.in` — retest IIIDEM / `staginglms.eci.gov.in` |
| CWE | [CWE-200](https://cwe.mitre.org/data/definitions/200.html) |
| OWASP | A07:2025 – Authentication Failures |

> Do not transmit session identifiers in URLs (browser history, Referer, proxy logs).  
> Store session identifiers in **Secure**, **HttpOnly** cookies; enable **SameSite** where appropriate.

### Important distinction

| Token | What it is | Must not be in URL |
|-------|------------|-------------------|
| `MoodleSession*` cookie | PHP session id | **Yes — cookie only** (already enforced) |
| `sesskey` | CSRF token (not the login session id) | Prefer **not** in query string (this remediation) |

PoC `service.php?sesskey=…` is Moodle core AJAX CSRF, not the session cookie.  
PoC HTML `<a href="…/login/logout.php?sesskey=…">Log out</a>` is the user-menu logout link.  
`GET /mod/quiz/edit.php?cmid=59` is a course-module id, **not** a session token — **dispute**.

Same report page (debug mode / CWE-489): [debug-mode-staging.md](debug-mode-staging.md).

## PoC instances (retest 2026-09)

| Instance | Observation | Fix |
|----------|-------------|-----|
| AJAX `service.php?sesskey=…&info=media_videojs_get_language` | CSRF in query + `X-Moodle-Sesskey` | Core `lib/amd/src/ajax.js` no longer puts `sesskey` in URL; header only |
| AJAX `service-nologin.php?…&sesskey=…` | Same | Client strip + header; core does not add sesskey for nologin |
| AJAX `service.php?sesskey=…&info=core_session_time_remaining` | Same | Same |
| AJAX `service.php?sesskey=…&info=core_message_get_conversation_messages` | Same | Same |
| HTML Log out `logout.php?sesskey=…` (e.g. About us) | Mustache usermenu | `user/lib.php` logout without sesskey; marketing pages use `theme_iiidem2_export_primary_menu`; HTML buffer strip |
| `/mod/quiz/edit.php?cmid=59` | Only `cmid` | **Dispute** — not a session token |

## Controls

### 1. PHP session cookie only (`config.php`)

| Setting | Value |
|---------|--------|
| `session.use_cookies` | `1` |
| `session.use_only_cookies` | `1` |
| `session.use_trans_sid` | `0` |
| `$CFG->usesid` | `false` |

Query params named like session cookies (`MoodleSession`, `sid`, `PHPSESSID`, …) are stripped from `$_GET`.

### 2. AJAX `sesskey` → header only (theme ≥ `2024101042`)

| Piece | Role |
|-------|------|
| **`lib/amd/src/ajax.js` + `ajax.min.js`** | Builds `/lib/ajax/service*.php?info=…` **without** `sesskey=`; sets `X-Moodle-Sesskey` |
| `javascript/ajax_sesskey_header.js` (`v=2024101042`) | Belt-and-braces: strip any leftover query `sesskey`, XHR + jQuery + fetch; header once |
| `import_sesskey_from_header` | Maps header → request **only if** query/body sesskey is missing |
| CORS | `Access-Control-Allow-Headers` includes `X-Moodle-Sesskey` |

> Do not apply the header rewrite to `repository/draftfiles_ajax.php` — overwriting that endpoint’s POST `sesskey` caused `invalidsesskey` on the file picker.

### 3. Custom IIIDEM AJAX — POST body

Chatbot, FAQ, live quiz, Webex join click, etc. send `sesskey` in POST, not the query string.

### 4. Destructive actions — POST forms

Calendar delete, cancel live class, FAQ delete, etc. — not GET + sesskey links.

### 5. Logout

| Control | Detail |
|---------|--------|
| `user/lib.php` | Logout nav item URL has **no** `sesskey` |
| Marketing / About us | `theme_iiidem2_get_marketing_page_context()` uses `theme_iiidem2_export_primary_menu()` (strips sesskey) |
| HTML buffer | `ensure_sesskey_url_strip_buffer()` strips `/login/*.php?sesskey=` from final HTML |
| Click → POST | `logout_post.js` posts `sesskey` in the form body (uses `M.cfg.sesskey`) |

### 6. Expected after deploy

| Request | Expect |
|---------|--------|
| `POST /lib/ajax/service.php?info=…` | **No** `sesskey=` in URL; `X-Moodle-Sesskey` present (single value) |
| `GET /lib/ajax/service-nologin.php?info=…` | **No** `sesskey=` in URL |
| View-source Log out href | `/login/logout.php` **without** `?sesskey=` |
| Click Log out | `POST /login/logout.php` with body `sesskey=…` |

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Ship at least:

- `lib/amd/src/ajax.js`, `lib/amd/build/ajax.min.js`
- `theme/iiidem2/` (≥ `2024101042`) — JS, hooks, security_headers, lib.php
- `user/lib.php`
- `lib/classes/output/core_renderer.php` (login_info logout href)

Hard-refresh browsers (Ctrl+F5). Confirm Network tab: service.php URL has **no** `sesskey=`.

## Verify

1. Log in → DevTools → Network → open course / messaging  
2. `service.php` / `service-nologin.php` request URL must **not** contain `sesskey=`  
3. Request headers must include `X-Moodle-Sesskey: …` (single value, not duplicated)  
4. View-source / user menu: logout href has no `sesskey`  
5. `/mod/quiz/edit.php?cmid=59` — dispute (module id only)

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Session id cookie-only | `session.use_only_cookies`, no trans_sid |
| AJAX CSRF off URL | Core `ajax.js` + theme strip + `X-Moodle-Sesskey` |
| Logout off URL | `user/lib.php` + usermenu export + HTML buffer + POST logout |
| Quiz `cmid` | Not a session token |

Related: [cookie-httponly.md](cookie-httponly.md), [https-sensitive-data.md](https-sensitive-data.md), [verbose-error-messages.md](verbose-error-messages.md).
