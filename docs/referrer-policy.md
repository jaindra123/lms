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

## Affected URLs (report)

| URL |
|-----|
| `/` |
| `/user/profile.php?id=5` |
| `/course/search.php?search=…` |
| `/course/index.php?categoryid=3` |
| `/index.php?` |

## PoC

| Instance | Detail |
|----------|--------|
| 1 | Pages with a query string link to another domain (`https://moodle.com/` / `https://download.moodle.org/mobile?…`). Without Referrer-Policy, the full origin URL (path + query) can be sent as `Referer` when the user clicks **Get the mobile app**. |

Report note: “This issue was found in multiple locations under the reported path.”

## Policy chosen

Suggested policies (either is acceptable; this site uses the first):

- `Referrer-Policy: strict-origin-when-cross-origin`
- `Referrer-Policy: no-referrer`

| Navigation | Referrer sent |
|------------|---------------|
| Same-origin | Full URL |
| Cross-origin HTTPS→HTTPS | Origin only (`https://host`) — **no path/query** |
| HTTPS→HTTP (downgrade) | Nothing |

## Implementation

| Layer | Detail |
|-------|--------|
| `config.php` | `$CFG->referrerpolicy = 'strict-origin-when-cross-origin'` (all envs) |
| Theme HTTP header | `security_headers::send()` → `Referrer-Policy: …` |
| Moodle core | `weblib.php` emits the same when `$CFG->referrerpolicy` is set |
| HTML meta | `<meta name="referrer" content="strict-origin-when-cross-origin">` via theme hook |
| `target=_blank` | Also `rel="noopener noreferrer"` ([unsafe-blank-target-links.md](unsafe-blank-target-links.md)) |
| Mobile app promo | `tool_mobile/setuplink` forced empty — no footer link to `download.moodle.org` ([qr-login-disabled.md](qr-login-disabled.md)) |
| Smart App Banners | Forced off (meta must not embed current page URL for external app stores) |

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
curl -sI https://staginglms.eci.gov.in/login/index.php | grep -i referrer-policy
# Expect: Referrer-Policy: strict-origin-when-cross-origin

curl -sI 'https://staginglms.eci.gov.in/course/search.php?search=test' | grep -i referrer-policy

curl -sL https://staginglms.eci.gov.in/login/index.php | grep -i 'download.moodle.org' && echo FAIL || echo OK
# Expect: OK (no Get the mobile app cross-domain link)
```

Site administration → Security → HTTP security → **Referrer Policy** = `strict-origin-when-cross-origin`.

Browser: from a page with a query string, open DevTools → Network → click any remaining external link → request `Referer` must be **origin only** (no path/query), or absent on downgrade.

## Evidence for auditors

| Control | Value |
|---------|--------|
| Response header | `Referrer-Policy: strict-origin-when-cross-origin` |
| Config force | `$CFG->referrerpolicy` |
| Meta fallback | `name="referrer"` |
| Cross-origin leak of path/query | Prevented |
| `download.moodle.org` promo link | Removed (`setuplink` empty) |

Related: [security-headers.md](security-headers.md), [session-token-in-url.md](session-token-in-url.md), [unsafe-blank-target-links.md](unsafe-blank-target-links.md).
