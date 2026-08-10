# Vulnerable JavaScript Dependency

## Finding

> Vulnerable JavaScript Dependency — upgrade or remediate outdated / flagged third-party JS libraries.

## Inventory (custom / theme)

| Library | Location | Version | Status |
|---------|----------|---------|--------|
| Bootstrap 4 (theme AMD) | `theme/iiidem2/amd/.../bootstrap` | **4.6.2-iiidem1** (patched) | Hardened selector; hero controls use `<button>` + `data-target` (no `href`) |
| Bootstrap 5 (course pages) | `theme/iiidem2/style/bootstrap5.*` | **5.3.8** | Current stable |
| intl-tel-input | `theme/iiidem2/javascript/intl-tel-input` | **21.2.7** | Upgraded from 18.2.1 |
| jQuery (Moodle core) | `lib/jquery` | **3.7.1** | Platform-managed; no known exploitable CVE for this build |

Moodle core also ships Bootstrap 4.6.2 via `theme_boost`. That copy is owned by the Moodle release train (`4.5.11+` here) and must **not** be upgraded in isolation (breaks AMD / SCSS). Full migration to Bootstrap 5 is a Moodle major-version path (Moodle 5.0+).

## Remediations applied

### 1. Bootstrap 4 carousel / selector hardening

Scanner findings around Bootstrap 4 often cite carousel `href` handling (CVE-2024-6531 class; advisory later withdrawn by upstream, but scanners still flag 4.6.2).

- `theme/iiidem2/amd/src/bootstrap/util.js` — `getSelectorFromElement()` accepts only a safe `#id` fragment (rejects `javascript:`, paths, queries)
- Matching change in `amd/build/bootstrap/util.min.js`
- Homepage slider controls converted from `<a href="#…">` to `<button data-target="#…">` (`templates/slider.mustache`)

### 2. intl-tel-input upgrade

- JS/CSS/utils → **21.2.7**
- Option rename: `separateDialCode` → `showSelectedDialCode` (register page + AMD)
- Theme CSS sheet `intltelinput.css` regenerated with Moodle `[[pix:]]` flag/globe URLs

### 3. Bootstrap 5 already current

Course-page bundle remains **5.3.8** (includes fixes through the 5.3 line).

## Residual / accepted risk

- Theme + Moodle core still use Bootstrap **4.x** for the main LMS chrome. Upstream EOL; no official 4.6.3. Mitigations above reduce the practical attack surface for custom pages. Long-term: upgrade Moodle when Bootstrap 5 is the platform default.
- Do **not** run `npm audit fix --force` against Moodle’s root `package.json` for production browser assets — those are **build-time** tools, not served to users.

## Deploy

```bash
php admin/cli/purge_caches.php
```

Hard-refresh browsers (AMD / theme CSS/JS). Smoke-test: homepage slider, `/register/` phone country dropdown, course page layout.
