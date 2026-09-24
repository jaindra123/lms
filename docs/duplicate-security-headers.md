# Duplicate Security Response Header (HSTS)

## Finding

| Field | Report |
|-------|--------|
| Title | Duplicate Security Response Header |
| Severity | Low |
| URL | `https://iiidemlms.eci.gov.in` |
| Header | `Strict-Transport-Security` sent **twice** |

Apache SSL (`Header always set`) and PHP `header()` both emitted HSTS. Apache keeps those in two tables (`always` vs `onsuccess`), so both copies appear in the response. Browsers may honour the first or the last value.

## Fix (`theme_iiidem2` ≥ `2024101099`)

| Location | Action |
|----------|--------|
| `theme_iiidem2\security_headers` | Do **not** send HSTS from PHP |
| `login/logout.php` | Do **not** re-send HSTS |
| `.htaccess` | `Header unset` + `Header always unset`, then **one** `Header always set` with preload |

Value (single): `max-age=31536000; includeSubDomains; preload`

Snippet: [snippets/apache-security-headers.conf](snippets/apache-security-headers.conf). If the vhost also sets HSTS, keep only this unset/set pair — do not add a second `Header always set`.

## Retest

```bash
curl -sI https://iiidemlms.eci.gov.in/ | grep -i strict-transport-security
# Expect exactly one line:
# Strict-Transport-Security: max-age=31536000; includeSubDomains; preload

curl -sI https://iiidemlms.eci.gov.in/ | grep -ci '^strict-transport-security:'
# Expect: 1
```

## Deploy

Ship `.htaccess` with the app (needs `mod_headers`). Then:

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```
