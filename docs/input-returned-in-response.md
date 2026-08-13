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
| `/login/forgot_password.php/…` path junk | `/suw5xmqrrs` / `saw5xzmqrrg` style path-info | Redirect + sanitize `$FULLME` so form `action` stays clean ([form-action-hijacking-xss.md](form-action-hijacking-xss.md)) |
| `/login/forgot_password.php` | `email`, `username`, arbitrary GET | Moodle formslib `PARAM_*` + escaped redisplay; reset notices are **localized**, not “user X exists” when `protectusernames` is on |
| `/register/` | `city`, `email`, names, occupation fields, … | Markup scrub + `err_xss`; AJAX checks return **localized** JSON only ([input-validation-xss.md](input-validation-xss.md)) |
| `/contact-us/` | `name`, `email`, `subject`, `message` | Reject dangerous markup; sanitize before email ([input-validation-xss.md](input-validation-xss.md)) |
| `/course/search.php` | `q`, `search` | Strip tags + `PARAM_TEXT` before `optional_param` |
| `/login/index.php` | `Referer` header | `get_local_referer()` → `PARAM_LOCALURL` only (same-site); used as `wantsurl`, not raw HTML echo |

## Fixes in this repo

| Layer | Detail |
|-------|--------|
| Shared helpers | `theme_iiidem2\input_validation` — `clean_text`, `json_encode_safe`, `contains_dangerous_markup` |
| Contact | Reject XSS-like markup; plain-text email body |
| Course / message search | `hook_listener::sanitize_course_search_params` |
| Register AJAX | No echo of submitted email/phone in JSON errors |
| Login path-info | `hook_listener::neutralize_spurious_php_pathinfo` |
| Referer | Core `PARAM_LOCALURL` (external Referers discarded) |
| CSP | Restricts residual inline script |

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Theme ≥ `2024100992`.

## Verify

```bash
# Path junk must redirect to clean URL (no reflection of junk segment)
curl -sI 'https://staginglms.eci.gov.in/login/forgot_password.php/suw5xmqrrs' | head -n 5
# Expect: 302 Location: …/login/forgot_password.php

# Search tags stripped
curl -s 'https://staginglms.eci.gov.in/course/search.php?search=%3Cscript%3Ealert(1)%3C%2Fscript%3E' \
  | grep -i '<script>alert' && echo FAIL || echo OK

# Contact XSS payload rejected (manual form submit)
# Register check_email JSON must not contain the raw email string as HTML
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Escaped form round-trip | Moodle formslib + `PARAM_TEXT` |
| No dangerous markup (contact) | `contains_dangerous_markup` + reject |
| Search sanitize | Theme `after_config` |
| No raw JSON reflection | `json_encode_safe` / localized messages |
| Referer not attacker-controlled HTML | `PARAM_LOCALURL` |
| Path-info probes | 302 to script without junk path |

Related: [input-validation.md](input-validation.md), [input-validation-xss.md](input-validation-xss.md), [referrer-policy.md](referrer-policy.md).
