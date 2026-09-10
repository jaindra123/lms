# 28. Cross Domain Referrer Leakage

## Finding

| Field | Report |
|-------|--------|
| Title | 28. Cross Domain Referrer Leakage |
| Impact | LOW (CVSS 3.1) |
| CWE | [CWE-200](https://cwe.mitre.org/data/definitions/200.html) – Exposure of Sensitive Information |
| OWASP | A05:2021 / A05:2025 – Security Misconfiguration |
| CVSS vector | AV:N/AC:L/PR:N/UI:R/S:U/C:L/I:N/A:N |
| Host note | Report may cite `cci.gov.in` — retest on `staginglms.eci.gov.in` |

> The application does not enforce an appropriate Referrer-Policy, allowing the browser to send the originating URL in the `Referer` header to external domains.

**Recommendation:** Configure a restrictive Referrer-Policy, e.g.:

- `Referrer-Policy: strict-origin-when-cross-origin`
- `Referrer-Policy: no-referrer`

## Affected URLs (report)

| URL |
|-----|
| `/` |
| `/theme/iiidem2/dashboard/index.php` (footer “Powered by Moodle” → `https://moodle.com`) |
| `/user/profile.php?id=5` |
| `/course/search.php?search=…` |
| `/course/index.php?categoryid=3` |
| `/index.php?` |

## PoC

| Instance | Detail |
|----------|--------|
| 1 | Pages with a query string link to another domain (`https://moodle.com/` / `https://download.moodle.org/mobile?…`). Without Referrer-Policy, the full origin URL (path + query) can be sent as `Referer` when the user clicks the link. |
| 2 | Dashboard HTML contained `Powered by <a href="https://moodle.com">Moodle</a>` plus Moodle version text in the (hidden) system footer. |

## Policy chosen

This site uses **`strict-origin-when-cross-origin`** (first suggested value). Element-level **`referrerpolicy="no-referrer"`** is also applied on cross-origin anchors as belt-and-braces.

| Navigation | Referrer sent (document policy) |
|------------|----------------------------------|
| Same-origin | Full URL |
| Cross-origin HTTPS→HTTPS | Origin only (`https://host`) — **no path/query** |
| HTTPS→HTTP (downgrade) | Nothing |
| External `<a>` after harden | **Nothing** (`referrerpolicy="no-referrer"`) |

## Implementation (theme ≥ `2024101034`)

| Layer | Detail |
|-------|--------|
| `config.php` | `$CFG->referrerpolicy = 'strict-origin-when-cross-origin'` (all envs) |
| Theme HTTP header | `security_headers::send()` → `Referrer-Policy: …` |
| Moodle core | `weblib.php` emits the same when `$CFG->referrerpolicy` is set |
| HTML meta | `<meta name="referrer" content="strict-origin-when-cross-origin">` via theme hook |
| Footer PoC | Replaced core `poweredbymoodle` (moodle.com link) with plain `poweredbyplain`; removed version line |
| External anchors | Response buffer adds `rel="noopener noreferrer"` + `referrerpolicy="no-referrer"` on cross-origin `http(s)` links |
| `target=_blank` | Also `rel="noopener noreferrer"` ([unsafe-blank-target-links.md](unsafe-blank-target-links.md)) |
| Mobile app promo | `tool_mobile/setuplink` forced empty — no footer link to `download.moodle.org` ([qr-login-disabled.md](qr-login-disabled.md)) |
| Apache snippet | [`docs/snippets/apache-security-headers.conf`](snippets/apache-security-headers.conf) |

### Optional nginx (edge)

```nginx
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Ensure staging secret config does **not** override `$CFG->referrerpolicy` to empty/`default`.

## Verify

```bash
HOST=staginglms.eci.gov.in

curl -sI "https://${HOST}/theme/iiidem2/dashboard/index.php" | grep -i referrer-policy
# Expect: Referrer-Policy: strict-origin-when-cross-origin

curl -sL "https://${HOST}/theme/iiidem2/dashboard/index.php" | grep -i 'moodle.com' && echo FAIL || echo OK
# Expect: OK (no Powered-by moodle.com href)

curl -sL "https://${HOST}/login/index.php" | grep -i 'name="referrer"' | head
# Expect: meta name="referrer" content="strict-origin-when-cross-origin"
```

Site administration → Security → HTTP security → **Referrer Policy** = `strict-origin-when-cross-origin`.

Browser: from a page with a query string, DevTools → Network → click any remaining external link → `Referer` must be **absent** (element policy) or **origin only** (document policy) — never full path/query.

## Evidence for auditors

| Control | Value |
|---------|--------|
| Response header | `Referrer-Policy: strict-origin-when-cross-origin` |
| Config force | `$CFG->referrerpolicy` |
| Meta fallback | `name="referrer"` |
| Cross-origin leak of path/query | Prevented |
| `moodle.com` powered-by link | Removed from footer |
| `download.moodle.org` promo link | Removed (`setuplink` empty) |

Related: [security-headers.md](security-headers.md), [session-token-in-url.md](session-token-in-url.md), [unsafe-blank-target-links.md](unsafe-blank-target-links.md), [version-disclosure.md](version-disclosure.md).
