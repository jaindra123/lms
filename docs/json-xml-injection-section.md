# 36. JSON/XML Injection — `/course/section.php?id=`

## Finding

| Field | Report |
|-------|--------|
| Title | JSON/XML Injection |
| Impact claimed | MEDIUM / CVSS 6.1 |
| URL | `https://staginglms.eci.gov.in/course/section.php?id=…` |
| CWE | [CWE-20](https://cwe.mitre.org/data/definitions/20.html) – Improper Input Validation |
| OWASP | A05:2025 – Injection |
| CVSS | `CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:C/C:L/I:L/A:N` |

> Claim: JSON/XML metacharacters in a user-controlled parameter change the server response/page content, indicating inadequate validation of structured-data input.

## PoC (auditor)

1. Baseline: `GET /course/section.php?id=24` → normal section / curriculum page.
2. Fuzz `id` with payloads such as:
   - `{base}' xmlns:xsi=` / `{base}" xmlns:xsi="`
   - `{base}", "x":1` (JSON fragment)
   - `<!--xx-->`, `<![CDATA[…]]>`, `<a>{base}</a>`
3. Observed (before harden): HTTP **200** with not-found / site branding page (scanner treats “response changed” as injection).

Report host may also show `stagingiims.eci.gov.in` — treat as typo; retest on `staginglms.eci.gov.in`.

## Why this was not JSON/XML injection (parser)

### 1. `id` is an integer allow-list (core)

```php
// course/section.php
$sectionid = required_param('id', PARAM_INT);
```

Moodle `PARAM_INT` strips non-numeric content. A payload like `{base}'…` becomes **`0`**, never a JSON/XML document. There is no `json_decode` / XML parser on this path.

### 2. Soft coercion still looked “interesting” to scanners

Invalid cleaned IDs returned HTTP **200** with a themed not-found UI — content differs from `id=24`, so Intruder flagged every payload as a hit.

## Fix (theme ≥ `2024101032`)

`theme_iiidem2\hook_listener::reject_non_integer_section_id()` runs in `after_config`:

- Raw `id` must match **strict positive decimal integer** (`^[1-9][0-9]*$`) via `input_validation::is_strict_positive_int()`.
- Junk (`{base}' xmlns:xsi=`, tags, JSON fragments) → `moodle_exception('invalidparameter')` — **no** soft coerce to `0`, **no** echo of the raw payload.

Legitimate `id=24` (and other real section PKs) unchanged.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## Retest evidence

```bash
# Junk must NOT return soft 200 curriculum/not-found with payload semantics
curl -sI 'https://staginglms.eci.gov.in/course/section.php?id=%7bbase%7d%27%20xmlns%3axsi%3d' | head -n 5
# Expect: error / invalid parameter (not a normal section page)

# Must not echo raw payload in body
curl -s 'https://staginglms.eci.gov.in/course/section.php?id=%7bbase%7d%27%20xmlns%3axsi%3d' \
  | grep -F "xmlns:xsi" && echo FAIL || echo OK

# Valid section still works
curl -sI 'https://staginglms.eci.gov.in/course/section.php?id=24' | head -n 5
```

## Verdict for auditors

| Claim | Result |
|-------|--------|
| JSON/XML injection via `id` | **Not present** — no structured-data parser on `id` |
| Inadequate validation | **Hardened** — strict positive-int allow-list before `PARAM_INT` |
| Recommendation (server-side validation) | **Met** — theme reject + core `PARAM_INT` |

Related: [input-returned-in-response.md](input-returned-in-response.md), [os-command-injection.md](os-command-injection.md), [input-validation.md](input-validation.md).
