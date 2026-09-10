# 19. Vulnerable JavaScript Dependency

## Finding

| Field | Report |
|-------|--------|
| Title | Vulnerable JavaScript Dependency |
| Impact | MEDIUM / CVSS 5.3 |
| CWE | [CWE-1104](https://cwe.mitre.org/data/definitions/1104.html) — Use of Unmaintained Third-Party Components |
| OWASP | A08:2021 – Software and Data Integrity Failures (report A08:2025) |

Scanner detected outdated MathJax / YUI / DOMPurify / TinyMCE (and related loader URLs). Host note: some pages cite `staginglma.cci.gov.in` — retest on `staginglms.eci.gov.in`.

## Instance mapping

### 1. MathJax 2.7.9 — CVE-2023-39663 (ReDoS)

**Was:** `cdn.jsdelivr.net/npm/mathjax@2.7.9/...` (`Safe.js`, `Accessible.js`).  
**Now:** MathJax **3.2.2** via `filter_mathjaxloader` (≥ `2024100701`) — CDN `mathjax@3.2.2/es5/tex-mml-chtml.js`, AMD loader uses MathJax 3 + `ui/safe`.

Vendor disputes practical risk of CVE-2023-39663; scanners still require leaving 2.7.9.

### 2. TinyMCE 7.3.0 — CVE-2024-47759 / 47761 / 47762

**Was:** `lib/editor/tiny/js/tinymce` TinyMCE **7.3.0**.  
**Now:** TinyMCE **7.9.3** (2026-05-19). Fixed branch for those XSS issues is ≥ 7.9.3.

**Instance 3 (PoC):** `GET /lib/editor/tiny/loader.php/…/themes/silver/theme.js` — scanner highlighted embedded `YUI.add('treeview'…)` / `version: 3.4.1` (Yahoo 2011) inside the old Tiny theme bundle. After the **7.9.3** vendor replace, `themes/silver/theme.js` is TinyMCE 7.9.3 only — **no** `YUI.add`, `treeview`, or `3.4.1` strings.

```bash
grep -E "YUI\.add|version: 3\.4\.1|developer\.yahoo\.com/yui" lib/editor/tiny/js/tinymce/themes/silver/theme.js || echo 'OK: no YUI 3.4.1 in Tiny theme'
```

**Instance 4 (PoC):** `GET /lib/editor/tiny/loader.php/…/plugins/anchor/plugin.js` — Burp highlighted `checkXFF` / `document.referrer === ""` around line 84. That pattern is **not** in TinyMCE **7.9.3** `plugins/anchor/plugin.js` (line 84 is `const anchor = getNamedAnchor(editor)`). Retest after purge; treat as stale fingerprint of the old 7.3.0 bundle. Site `Referrer-Policy` is already `strict-origin-when-cross-origin` ([referrer-policy.md](referrer-policy.md)).

```bash
grep -E "checkXFF|document\.referrer" lib/editor/tiny/js/tinymce/plugins/anchor/plugin.js || echo 'OK: no checkXFF/referrer probe in anchor plugin'
```

### 3. DOMPurify — CVE-2025-15599 / CVE-2026-0540 (#38)

DOMPurify is **bundled inside TinyMCE** (`tinymce.js` / `tinymce.min.js` and `themes/silver/theme.js` / `theme.min.js`), not a separate Moodle package.

| Finding | Before | After |
|---------|--------|--------|
| #19 | DOMPurify **3.0.5** (TinyMCE 7.3.0) | **3.2.6** (via TinyMCE 7.9.3) |
| #38 | DOMPurify **3.2.6** | **3.2.7** (CVE-2025-15599) |
| Retest 2026-09 | DOMPurify **3.2.7** flagged | **3.4.15** (CVE-2026-0540+; SAFE_FOR_XML rawtext list) |

**CVE-2025-15599:** XSS via missing `textarea` in `SAFE_FOR_XML` (3.1.3–3.2.6). Fixed in **3.2.7**.  
**CVE-2026-0540:** XSS via missing rawtext elements (`noscript`, `xmp`, `noembed`, `noframes`, `iframe`) through **3.3.1**. Fixed in **≥ 3.3.2**. Bundle bumped to **3.4.15** (current Cure53 stable).

**PoC URLs:** `/lib/editor/tiny/loader.php/…/themes/silver/theme.js` and `…/tinymce.js`.

```bash
grep -m1 "DOMPurify.version" lib/editor/tiny/js/tinymce/tinymce.js
grep -m1 "DOMPurify.version" lib/editor/tiny/js/tinymce/themes/silver/theme.js
# Expect: 3.4.15

grep -oE 'style\|script\|title\|xmp\|textarea\|noscript\|iframe\|noembed\|noframes' \
  lib/editor/tiny/js/tinymce/tinymce.js | head -1
# Expect: expanded SAFE_FOR_XML close-tag list
```

Addresses nesting mXSS / prototype-pollution class findings plus CVE-2025-15599 and CVE-2026-0540.

### 4. YUI 2.9.0 — CVE-2012-5881 / 5882 / 5883

Moodle core still ships **YUI 2.9.0** under `lib/yuilib/2in3` for legacy modules. Those 2012 SWF/charts XSS CVEs are **not practically exposed** on this LMS (no YUI Charts/SWF authoring surface).

**Instance 2 (PoC):** authenticated `GET /theme/yui_combo.php?<yui module list…>` returned concatenated YUI JS (versions visible in paths, e.g. `3.17.2`). That endpoint is how scanners fingerprint YUI.

| Control | Status |
|---------|--------|
| `$CFG->yuicomboloading = false` | Forced on staging/production — fewer combined module URLs |
| `/theme/yui_combo.php` | **Must stay available** — Moodle still loads `yui-moodlesimple` rollups through it. Blocking it 404s the whole LMS JS (file picker, etc.) |
| Full YUI2 tree removal | Requires Moodle major upgrade (platform roadmap) |

Treat remaining on-disk YUI under `lib/yuilib` and required rollup combo requests as **accepted residual / platform-owned** until Moodle drops YUI. Do not 404 `yui_combo.php` on staging/production.

## Other inventory (earlier remediations)

| Library | Location | Version | Status |
|---------|----------|---------|--------|
| Bootstrap 4 (theme AMD) | `theme/iiidem2/amd/.../bootstrap` | **4.6.2-iiidem1** (patched) | Selector hardened |
| Bootstrap 5 (course pages) | `theme/iiidem2/style/bootstrap5.*` | **5.3.8** | Current stable |
| intl-tel-input | `theme/iiidem2/javascript/intl-tel-input` | **21.2.7** | Upgraded |
| jQuery (Moodle core) | `lib/jquery` | **3.7.1** | Platform-managed |

## Related — UPI JSON errors

`upiassembly.com` verbose JSON is a **third-party** host — dispute ([verbose-error-messages.md](verbose-error-messages.md)).

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Verify:

```bash
# MathJax 3
curl -s 'https://staginglms.eci.gov.in/course/view.php?id=2' | grep -oE 'mathjax@[0-9.]+' | sort -u
# Expect: mathjax@3.2.2

# TinyMCE / DOMPurify (from filesystem after deploy)
grep -m1 'TinyMCE version' lib/editor/tiny/js/tinymce/tinymce.js
grep -m1 "DOMPurify.version" lib/editor/tiny/js/tinymce/tinymce.js
grep -m1 "DOMPurify.version" lib/editor/tiny/js/tinymce/themes/silver/theme.js
# Expect: TinyMCE 7.9.3 and DOMPurify 3.4.15

# YUI combo must 404 (Instance 2)
curl -sI 'https://staginglms.eci.gov.in/theme/yui_combo.php?3.17.2/build/yui/yui-min.js' | head -n 1
# Expect: HTTP/1.1 404
```
## Evidence for auditors

| Library | Report version | Remediation |
|---------|----------------|-------------|
| MathJax | 2.7.9 | **3.2.2** CDN + filter loader |
| TinyMCE | 7.3.0 (+ YUI 3.4.1 fingerprint in theme.js) | **7.9.3** — no YUI treeview in silver theme |
| DOMPurify | 3.0.5 / 3.2.6 / 3.2.7 | **3.4.15** (CVE-2025-15599 + CVE-2026-0540; TinyMCE 7.9.3 bundle patched) |
| YUI 2.9.0 | 2.9.0 | Combo off + **endpoint 404**; residual files in Moodle core |
