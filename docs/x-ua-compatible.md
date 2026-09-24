# Use of Deprecated X-UA-Compatible Header

## Finding

| Field | Report |
|-------|--------|
| Title | Use of Deprecated X-UA-Compatible Header |
| URL | `/login/index.php` |
| Header | `X-UA-Compatible: IE=edge` |

Internet Explorer document-mode switching is obsolete. The header does not apply to current browsers and is flagged as a deprecated / unnecessary response header.

Moodle core `send_headers()` (`lib/weblib.php`) sent `IE=edge` on every HTML page, including login.

## Fix (`theme_iiidem2` ≥ `2024101086`)

| Location | Action |
|----------|--------|
| `lib/weblib.php` `send_headers()` | Do not emit; `header_remove('X-UA-Compatible')` |
| `bootstrap_renderer.php` / maintenance / installer | Same |
| `theme_iiidem2\security_headers` | Remove on send + `header_register_callback` at flush |
| `.htaccess` | `Header always unset X-UA-Compatible` |
| nginx | `fastcgi_hide_header X-UA-Compatible` — [nginx-hide-x-ua-compatible.conf](snippets/nginx-hide-x-ua-compatible.conf) |

No replacement header is sent.

## Retest

```bash
curl -sI https://staginglms.eci.gov.in/login/index.php | grep -i x-ua-compatible
# Expect: no output
```

DevTools → Network → `login/index.php` → Response headers: **no `X-UA-Compatible`**.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Ship `.htaccess` with the app.
