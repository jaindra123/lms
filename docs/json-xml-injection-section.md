# 36. JSON/XML Injection — **False positive**

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
   - `{base}"N2Oxalm…` (`id=%7bbase%7d"…`)
   - `<script>…` style strings (listed in Intruder as “(base) \<script\>…”)
3. Observed: HTTP **200** with a different HTML page (e.g. site branding / “Welcome to IIIDEM LMS” / “section can’t be found” style content).

Report host may also show `stagingiims.eci.gov.in` — treat as typo; retest on `staginglms.eci.gov.in`.

## Why this is not JSON/XML injection

### 1. `id` is an integer allow-list

```php
// course/section.php
$sectionid = required_param('id', PARAM_INT);

if (!$section = $DB->get_record('course_sections', ['id' => $sectionid], '*')) {
    // Themed error / not-found UI — then die()
}
```

Moodle `PARAM_INT` / `clean_param()` strips non-numeric content. A payload like `{base}"…` or `<script>…` becomes **`0`** (or another integer prefix if any), never a JSON/XML document.

### 2. No JSON or XML parser on this path

`section.php` loads a `course_sections` row by integer primary key and renders HTML. There is no `json_decode` / XML parser fed from `id`.

### 3. “Response changed” is expected for invalid IDs

When the cleaned integer does not match a section, Moodle shows the **not-found** UI (site heading + error notification), not the section for `id=24`. Scanners often flag any content difference after fuzzing as “injection.” That is a **false positive** for CWE-91 / JSON-XML injection.

### 4. XSS / script payloads

Even if Intruder includes `<script>`, `id` is never echoed as HTML from this parameter after `PARAM_INT`. Residual XSS would require a different sink (not demonstrated by integer coercion + not-found page).

## Verdict for auditors

| Claim | Result |
|-------|--------|
| JSON/XML injection via `id` | **Not present** — false positive / scanner heuristic |
| Inadequate validation | **Allow-list present** — `PARAM_INT` |
| Recommendation (server-side validation + encoding) | **Already met** for this parameter |

**Dispute finding #36** for `/course/section.php?id=`.

## Retest evidence

```bash
# Invalid structured junk → not-found / error UI, not JSON parse errors or reflected payload
curl -sI 'https://staginglms.eci.gov.in/course/section.php?id=%7bbase%7d%22test' | head -n 5
curl -s 'https://staginglms.eci.gov.in/course/section.php?id=%7bbase%7d%22test' \
  | grep -iE 'sectioncantbefound|not found|Welcome' | head

# Must not echo raw payload as executable script in body
curl -s 'https://staginglms.eci.gov.in/course/section.php?id=%3Cscript%3Ealert(1)%3C/script%3E' \
  | grep -F '<script>alert(1)</script>' && echo FAIL || echo OK
```

## Related

- [os-command-injection.md](os-command-injection.md) (#32 — same `PARAM_INT` pattern)
- [input-returned-in-response.md](input-returned-in-response.md)
- [sql-injection-parameterized-queries.md](sql-injection-parameterized-queries.md)
- [input-validation.md](input-validation.md)
