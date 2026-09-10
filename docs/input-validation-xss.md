# Improper Input Validation / XSS injection (contact + course search + register)

## Finding

| Field | Report |
|-------|--------|
| Impact | MEDIUM / CVSS ~4.3 |
| CWE | [CWE-20](https://cwe.mitre.org/data/definitions/20.html) / [CWE-79](https://cwe.mitre.org/data/definitions/79.html) |
| URLs | `/contact-us/`, `/course/index.php`, `/course/management.php`, `/register/`, messaging, dashboard search |
| Host | `staginglms.eci.gov.in` |

> Validate all input fields (range/length, allow-lists, anchored regex). Encode if returned in the response.

## PoC → control (2026-09 retest)

| Instance | Payload | Expected after theme ≥ `2024101043` |
|----------|---------|--------------------------------------|
| **1** Contact Message | `<script>alert(1)</script>` | **Rejected** — `err_xss`; **no** “Thank you” |
| **1** Contact Subject | `@#$$$$$$$$$$$` | **Rejected** — must contain a letter or digit |
| **2** Course / category search | `@#$$$$$$$!` | Scrubbed to empty (no alnum); management + index covered |
| **3** Register names / city / university | `<script>…` | `err_xss`; cannot create account |
| Messaging special chars `@#$…` | **Not XSS** — chat may contain punctuation; preview uses `textContent` |
| Message search SQLi-style | Treated as plain text search token; no SQL concat; punctuation-only cleared |
| **Course curriculum summary** | Stored `<script>…` scrubbed on output (theme ≥ `2024101031`) |

### Why staging PoCs still showed “Thank you”

1. **Stale deploy** — older theme still on staging.
2. **Misleading UI** — success banner is **session one-shot** after a *prior* valid send; browser can keep XSS typed in fields without a successful XSS submit.
3. **Old bug** — blanking `$_POST` XSS to `''` before validation hid `err_xss` (looked like “required” / confusing retest). **Removed** in `2024101043`.

Typing a script into a field is **not** proof of XSS. Execution requires unsafe HTML reflection. Submit with markup must show `err_xss` / fail HTML5 validation.

## Implementation

| Layer | Detail |
|-------|--------|
| Detect | `input_validation::contains_dangerous_markup()` (tags, `javascript:`, `alert(`) |
| Names | `is_safe_person_name()` — Unicode letters + `'` `-` `.` only |
| Plain fields | `is_safe_plain_line()` + `has_alnum_content()` |
| Contact / register | Server `validation()` on **raw** `$_POST` + HTML `pattern="[^<>\"']+"` |
| Search | Hook scrubs `search`/`q`/`query`/`keywords` on `/course/*` (incl. **management**), message, user; `PARAM_TEXT` on `course/management.php` + `course/search.php` |
| Client | `form_input_guard.js` — marks invalid; clears markup / punctuation-only search |
| Output | `s()` / `format_string` / `json_encode_safe` — no raw echo |

## Deploy

Ship at least:

- `contact-us/index.php`
- `register/index.php`
- `course/management.php`, `course/search.php`
- `theme/iiidem2/` (forms, `input_validation.php`, `hook_listener.php`, `form_input_guard.js`, version/upgrade)

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Theme ≥ **`2024101043`**. Hard-refresh after deploy.

## Verify

1. Contact — Message `<script>alert(1)</script>` → `err_xss`; no alert dialog; no Thank you  
2. Contact — Subject `@#$$$` → rejected  
3. `/course/management.php` or category search `@#$$$` → query emptied / no results; no crash  
4. `/register/` script in First name / City / University → `err_xss`; no account  
5. Messaging punctuation-only message → allowed (not a finding)

Related: [input-validation.md](input-validation.md), [input-returned-in-response.md](input-returned-in-response.md).
