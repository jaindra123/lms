# Improper Input Validation / XSS injection (contact + course search + register)

## Finding

| Field | Report |
|-------|--------|
| Impact | MEDIUM / CVSS ~4.3 (register instances also LOW/reflected) |
| CWE | [CWE-79](https://cwe.mitre.org/data/definitions/79.html) / OWASP A03 |
| URLs | `/contact-us/`, `/course/search.php?search=…`, `/login/index.php`, `/course/index.php`, `/register/`, `/register/check_email.php` |
| Host note | Report may cite `cci.gov.in` — retest on `staginglms.eci.gov.in` |

> Malicious input can include code, scripts and commands, which if not validated correctly can be used to exploit vulnerabilities.  
> Recommendation: Validate and sanitize all user input. Encode/sanitize if returned in the response.

## PoC

| Instance | Payload | Risk |
|----------|---------|------|
| Contact **Message** | `"><script>alert(1)</script>` | XSS if reflected/stored unescaped |
| Course search | `search=<script>alert(1)</script>` | Reflected XSS if echoed as HTML |
| Messaging search (`/message/index.php`) | `<script>alert(1)</script>` in search | Often **text-only** preview (`alert(1)` via `textContent`); not script execution |
| Register `check_email.php` | `email=test@test.com'"><script>alert(document.cookie)</script>` | Reflected XSS if email echoed in HTML/JSON |
| Register `/register/` POST | Script / breakout payloads in name/profile fields | Reflected in form `value="…"` if unescaped |

Typing a script into a search box alone is not XSS; execution requires unsafe HTML injection. Controls below block markup and sanitize search.

## Fixes

### `/contact-us/`

- Length + `PARAM_TEXT` (existing)
- **Reject** HTML / script / event-handler patterns (`input_validation::contains_dangerous_markup`)
- Before email: `clean_public_text()` + plain-text `email_to_user` (empty HTML body)
- CSP already restricts inline script

### `/course/search.php` / `/course/index.php`

- Moodle core: `strip_tags()` on search
- Theme `after_config`: re-clean `search` / `q` with `PARAM_TEXT` + `strip_tags`, max 200 chars **before** `optional_param`
- CSP + Moodle escaping on headings

### `/message/index.php` (Instance 6)

- Core message drawer preview parses HTML then uses **`textContent`** (so `<script>…</script>` becomes plain `alert(1)` in the list — not an executing script)
- Theme: scrub `<>` from messaging search inputs (`message_xss_guard.js`)
- Theme: sanitize `search` / `q` / `query` GET params under `/message/`

### `/register/check_email.php` + OTP AJAX

- `input_validation::request_email()` — PARAM_RAW + validate; reject markup; **no** Moodle missing/invalid-param pages that can leak probes
- JSON via `json_encode_safe()` — localized messages only (never echo submitted email)
- OTP success: fixed string (no “sent to {$email}”)
- Client uses `textContent` for API messages (not `innerHTML`)

### `/register/` form POST

- Scrub dangerous markup from `$_POST` before formslib redisplay
- `register_form::validation` rejects markup (`err_xss`)
- Field types `PARAM_TEXT` + formslib attribute escaping

Form `action` PATH_INFO: [form-action-hijacking-xss.md](form-action-hijacking-xss.md).  
See [input-validation.md](input-validation.md) for broader register/support/chatbot rules.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Theme ≥ `2024100992`.

## Verify

1. Contact form — XSS in Message → rejected (`err_xss`); no alert
2. `/course/search.php?search=%3Cscript%3Ealert(1)%3C%2Fscript%3E` → no alert; tags stripped from query
3. `/message/index.php` — paste `<script>alert(1)</script>` in search → angle brackets stripped; **no** `alert()` dialog
4. `POST /register/check_email.php` with `email=test@test.com'"><script>alert(1)</script>` → JSON `invalidemail` only; response must **not** contain `<script>`
5. `POST /register/` with script in `firstname` → field cleared / `err_xss`; HTML must not contain raw `<script>alert`

## Evidence for auditors

| Control | Implementation |
|---------|----------------|
| Contact markup reject | `contact_form::validation` + `err_xss` |
| Contact email sanitize | `theme_iiidem2_send_contact_message` |
| Search sanitize | `hook_listener::sanitize_course_search_params` (course + message) |
| Messaging search UI | `message_xss_guard.js` |
| Register email AJAX | `request_email` + `json_encode_safe` / no echo |
| Register form | POST scrub + `err_xss` validation |

Full CDAC #25 URL list (forgot password, register, Referer): [input-returned-in-response.md](input-returned-in-response.md).  
Form `action` PATH_INFO reflection (forgot password / private files): [form-action-hijacking-xss.md](form-action-hijacking-xss.md).
