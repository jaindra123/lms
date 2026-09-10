# 24. Unsafe third-party link

## Finding

| Field | Report |
|-------|--------|
| Title | 24. Unsafe third-party link |
| Impact | LOW / CVSS 3.5 |
| URL | `https://staginglms.eci.gov.in/admin/index.php` (environment checks) |
| CWE | [CWE-1022](https://cwe.mitre.org/data/definitions/1022.html) — Use of Web Link to Untrusted Target with `window.opener` Access |
| OWASP | A05:2021 – Security Misconfiguration |
| CVSS vector | `AV:N/AC:L/PR:N/UI:R/S:U/C:L/I:N/A:N` |

> Add `rel="noopener"` or `rel="noopener noreferrer"` to all external links using `target="_blank"`. Remove unnecessary external links where practical.

Without `noopener`, a page opened via `target="_blank"` can access `window.opener` (tabnabbing).

### PoC (Instance 1)

Admin environment / upgrade UI links to Moodle docs with `target="_blank"` and **no** `rel`:

- `https://docs.moodle.org/405/en/admin/environment/php_extension/sodium`
- `https://docs.moodle.org/405/en/admin/environment/moodle`
- `https://docs.moodle.org/405/en/admin/environment/unicode`

Those come from `core_admin_renderer` → `$this->doc_link(..., true)` → core `doc_link()`.

## Fixes (theme ≥ `2024101039`)

### 1. Core `doc_link` (source of environment docs links)

`lib/classes/output/core_renderer.php` — whenever a docs link opens in a new window, set `rel="noopener noreferrer"`.

### 2. Theme `doc_link` override

`theme_iiidem2\output\core_renderer::doc_link()` also sets `rel` before calling parent (belt-and-braces).

### 3. HTML response buffer

`security_headers::ensure_noopener_blank_targets_buffer()` rewrites any remaining `a[target=_blank]` in full-page HTML so Burp/View Source shows `rel` without waiting for JS. Started from `after_config` **and** `before_http_headers`.

### 4. Theme templates (static)

| File | Status |
|------|--------|
| `theme/iiidem2/templates/footer.mustache` (social links) | `rel="noopener noreferrer"` |
| `theme/iiidem2/templates/course/schedule.mustache` | `rel="noopener noreferrer"` |
| Footer / live-class `window.open(...)` | Features include `noopener,noreferrer` |

### 5. Client-side belt-and-braces

`theme/iiidem2/javascript/enterprise_a11y.js` (footer JS):

- On load, every `a[target="_blank"]` gets `noopener` + `noreferrer` if missing
- `MutationObserver` covers dynamically inserted links

## Deploy

Ship **core** `lib/classes/output/core_renderer.php` **and** theme:

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## Verify

```bash
# Logged-in admin — raw HTML must include rel= on docs links
curl -s -b 'MoodleSession=…' 'https://staginglms.eci.gov.in/admin/environment.php' \
  | grep -oE 'target="_blank"[^>]*' | head
# Expect: each match also contains rel="…noopener…"

curl -s -b 'MoodleSession=…' 'https://staginglms.eci.gov.in/admin/environment.php' \
  | grep -F 'docs.moodle.org' | grep -F 'target="_blank"' | grep -v noopener && echo FAIL || echo OK
```

DevTools on `/admin/index.php` / environment: every `target="_blank"` includes `noopener` (and preferably `noreferrer`).

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Environment docs links | Core + theme `doc_link()` set `rel` |
| All HTML pages | Output buffer harden |
| Dynamic / leftover markup | `enterprise_a11y.js` |
| Theme static links | Mustache already has `rel` |
