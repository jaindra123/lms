# 25. Input returned in response

## Finding

| Field | Report |
|-------|--------|
| Title | 25. Input returned in response |
| Impact | LOW |
| Host note | Some URLs use `staginglms.cci.gov.in` — retest on `staginglms.eci.gov.in` |
| Related CWE | [CWE-79](https://cwe.mitre.org/data/definitions/79.html) / [CWE-20](https://cwe.mitre.org/data/definitions/20.html) (report may mis-cite CWE-1022) |
| OWASP | A03:2021 – Injection / A05 misconfiguration family |

> Validate and sanitize all user input. Do not echo raw request data into HTML/JSON in a way that enables XSS.

Scanners flag parameters that reappear in the response. **Safe** behaviour is: typed fields may round-trip **escaped** (`PARAM_*` + formslib `s()` / `htmlspecialchars`); dangerous markup is **rejected** or **stripped**; JSON never embeds raw user strings as executable HTML.

## Affected URLs (mapping)

| Surface | Cited params / notes | Control |
|---------|----------------------|---------|
| `/course/section.php` | `id` with `{base}' xmlns:xsi=` style junk | Strict positive-int reject ([json-xml-injection-section.md](json-xml-injection-section.md)) |
| `/lib/ajax/service-nologin.php` | `core_get_string` `stringid` / `stringparams` → was `[[…]]` | Existing string required; **any** `[[…]]` AJAX result rejected (no payload echo) |
| `/login/forgot_password.php/…` path junk | `/suw5xmqrrs` / `saw5xzmqrrg` style path-info | Redirect + sanitize `$FULLME` so form `action` stays clean ([form-action-hijacking-xss.md](form-action-hijacking-xss.md)) |
| `/login/forgot_password.php` | `email`, `username`, arbitrary GET | Moodle formslib `PARAM_*` + escaped redisplay; reset notices are **localized**, not “user X exists” when `protectusernames` is on |
| `/register/` | `city`, `email`, names, occupation fields, … | Markup scrub + `err_xss`; AJAX checks return **localized** JSON only ([input-validation-xss.md](input-validation-xss.md)) |
| `/contact-us/` | `name`, `email`, `subject`, `message` | Reject dangerous markup; sanitize before email ([input-validation-xss.md](input-validation-xss.md)) |
| `/course/search.php` | `q`, `search` | Strip tags + `PARAM_TEXT` before `optional_param` |
| `/login/index.php` | `Referer` header | `get_local_referer()` → `PARAM_LOCALURL` only (same-site); used as `wantsurl`, not raw HTML echo |

## PoC — `core_get_string` reflection (fixed)

### Variant A — invented `stringid`

**Request:** `core_get_string` with invented `stringid` (e.g. `cancelowK0qcryhf`).

**Before:** `"data": "[[cancelowK0qcryhf]]"`

### Variant B — valid `stringid` + payload in `stringparams`

**Request:** `stringid=cancel` with `stringparams` containing a probe (e.g. `eWK0qcryhf`).

**Before:** `"data": "[[cancel,eWK0qcryhf]]"` (or similar missing-string / marker form embedding the param).

### After (`lib/external/externallib.php`)

1. `string_exists` must be true (unknown ids → `invalid_parameter`).
2. `assert_safe_lang_string_result()` — if the result is a `[[…]]` marker, or embeds a client `stringparams` value inside `[[…]]`, throw `invalid_parameter` (generic message; **no** probe echo).

Same guards on `core_get_strings`. Legitimate strings such as `Cancel` are unchanged.
## Fixes in this repo

| Layer | Detail |
|-------|--------|
| Shared helpers | `theme_iiidem2\input_validation` — `clean_text`, `json_encode_safe`, `contains_dangerous_markup`, `is_strict_positive_int` |
| Section `id` | `hook_listener::reject_non_integer_section_id` (theme ≥ `2024101032`) |
| AJAX get_string | `string_exists` + reject any `[[…]]` / param-in-marker results |
| Contact | Reject XSS-like markup; plain-text email body |
| Course / message search | `hook_listener::sanitize_course_search_params` |
| Register AJAX | No echo of submitted email/phone in JSON errors |
| Login path-info | `hook_listener::neutralize_spurious_php_pathinfo` |
| Referer | Core `PARAM_LOCALURL` (external Referers discarded) |
| CSP | Restricts residual inline script |

## Deploy

Ship **both**:

- Theme `theme_iiidem2` ≥ `2024101032`
- Core file `lib/external/externallib.php` (get_string hardening)

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## Verify

```bash
# Section junk — must not soft-render as curriculum / echo xmlns
curl -s 'https://staginglms.eci.gov.in/course/section.php?id=%7bbase%7d%27%20xmlns%3axsi%3d' \
  | grep -F "xmlns:xsi" && echo FAIL || echo OK

# Unknown stringid / stringparams must not return [[payload]]
curl -s 'https://staginglms.eci.gov.in/lib/ajax/service-nologin.php?args=%5B%7B%22index%22%3A0%2C%22methodname%22%3A%22core_get_string%22%2C%22args%22%3A%7B%22stringid%22%3A%22cancelowK0qcryhf%22%2C%22component%22%3A%22moodle%22%7D%7D%5D' \
  | grep -F '[[cancelowK0qcryhf]]' && echo FAIL || echo OK

curl -s 'https://staginglms.eci.gov.in/lib/ajax/service-nologin.php?args=%5B%7B%22index%22%3A0%2C%22methodname%22%3A%22core_get_string%22%2C%22args%22%3A%7B%22stringid%22%3A%22cancel%22%2C%22component%22%3A%22moodle%22%2C%22stringparams%22%3A%5B%7B%22value%22%3A%22eWK0qcryhf%22%7D%5D%7D%7D%5D' \
  | grep -F 'eWK0qcryhf' && echo FAIL || echo OK
# Expect: error:true or data "Cancel" — never [[cancel,eWK0qcryhf]]

# Path junk must redirect to clean URL
curl -sI 'https://staginglms.eci.gov.in/login/forgot_password.php/suw5xmqrrs' | head -n 5
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Strict section `id` | Theme allow-list + exception (no payload echo) |
| No `[[stringid]]` AJAX echo | `string_exists` gate in `externallib.php` |
| Escaped form round-trip | Moodle formslib + `PARAM_TEXT` |
| No dangerous markup (contact) | `contains_dangerous_markup` + reject |
| Search sanitize | Theme `after_config` |
| No raw JSON reflection | `json_encode_safe` / localized messages |
| Referer not attacker-controlled HTML | `PARAM_LOCALURL` |
| Path-info probes | 302 to script without junk path |

Related: [json-xml-injection-section.md](json-xml-injection-section.md), [input-validation.md](input-validation.md), [input-validation-xss.md](input-validation-xss.md).
