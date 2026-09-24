# Improper Input Validation / XSS injection (contact + course search + register)

## Finding

| Field | Report |
|-------|--------|
| Impact | MEDIUM / CVSS ~4.3 |
| CWE | [CWE-20](https://cwe.mitre.org/data/definitions/20.html) / [CWE-79](https://cwe.mitre.org/data/definitions/79.html) |
| URLs | `/contact-us/`, `/course/index.php?categoryid=8`, `/register/`, dashboard message search, `/message/index.php` |
| Host | `staginglms.eci.gov.in` |

> Validate all input fields. Encode if returned in the response.

Retest (2026-09) still showed:

| Instance | What they typed | Misleading UI |
|----------|-----------------|---------------|
| **1** Contact | Subject `@#$$$$$$$$$$`, Message `<script>alert(1)</script>` | Green ticks + leftover **Thank you** while junk stayed in the fields |
| **2** Category search | `@#$$$$$$$$$$$$$` | Value stayed in the search box |
| **3** Register | `<script>alert(1)</script>` in name / email / city / university | Payload visible in the inputs |
| **5** Dashboard message drawer | `{base},(select*from(select(sleep…` | Probe stayed in the search box and was sent to `core_message_*` (PARAM_RAW) |
| **6** `/message/index.php` | `@#$…{base},(select*from(select(sleep(2())a)` | Same — payload stayed; “No results” still reflected the query |
| **My Courses** `/my/courses.php` | `(base}" xmlns:xsi="{base}" xmlns:xsi="…` | Probe stayed in Course overview search and was sent as AJAX `searchvalue` |

Typing a script into a field is **not** XSS until it is stored or reflected. The retest looked “accepted” because (a) the contact page marked any non-empty field with a green tick, (b) a previous real send could leave **Thank you**, (c) failed submits reflected the payload, (d) **`form_input_guard.js` never ran on register First name** — `input.matches('form.mform input[type="text"]')` is always false, so `<script>alert('test')</script>` stayed visible while typing.

## Fix (theme_iiidem2 2024101097)

| Control | Behaviour |
|---------|-----------|
| Register `validation()` | Rejects raw `$_POST` markup; names must match `is_safe_person_name()`; rejected fields **blanked** |
| `form_input_guard.js` | Watches the input via `closest('form.mform')` / `data-iiidem-no-markup`. **`beforeinput` / `keydown` block `<` `>`**. Paste of markup is discarded. Value is cleared if markup appears. |
| Name fields | `data-iiidem-no-markup="1"` on first/middle/last name, city, occupation lines |

Instance 5–6 are **not** SQL injection in Moodle’s message search (the value is bound as a `LIKE` parameter). The scanner still flagged them because the SQL-style token was kept in the box and echoed in the AJAX `search` argument.

## Fix (theme_iiidem2 2024101066 + 2024101067)

| Control | Behaviour |
|---------|-----------|
| Contact / register `validation()` | Rejects raw `$_POST` markup and punctuation-only text |
| No reflection | Rejected fields are **blanked** in the HTML response (`redact_rejected_fields`) |
| Contact success banner | Only after a real send + redirect; **cleared** on any new submit |
| Contact green ticks | Only for real text — not `<script>` / `@#$$$` |
| Client `form_input_guard.js` | Clears markup, symbol-only text, and SQL-scanner fragments (`{base}`, `select…from`, `sleep(`) |
| Category search | `sanitize_keyword_token()` empties non-alnum `search=` and scanner fragments |
| Message search (5–6) | Drawer/index search boxes **cleared**; AJAX `search` arg stripped; `core_message_*` returns empty hits (never `LIKE '%%'`) |

## Verify after deploy (hard-refresh)

1. Contact — Message `<script>alert(1)</script>` → field cleared / `err_xss`; **no** Thank you; **no** alert dialog  
2. Contact — Subject `@#$$$` → rejected (`err_plaintextrequired`); no Thank you  
3. `/course/index.php?categoryid=8` search `@#$$$` → box empties; submit does not keep the junk  
4. `/register/` script in First name / City / University / email → `err_xss`; fields blank; **no account**  
5. Dashboard message drawer — paste `{base},(select*from(select(sleep(2())a)` → box **empties**; no AJAX search with that token  
7. `/my/courses.php` — paste `(base}" xmlns:xsi="{base}"` in Course overview search → box **empties**; AJAX `searchvalue` must not keep the probe  

Theme ≥ **`2024101103`**. HTML `pattern` is `[^<>\x22\x27]+` so Chrome’s unicodeSets `v` flag no longer rejects `\"` and block register submit. Related: [input-validation.md](input-validation.md), [json-xml-injection-section.md](json-xml-injection-section.md).
