# Host header injection

## Finding

| Field | Report |
|-------|--------|
| Title | Host Header Injection / Invalid Host header |
| Impact | MEDIUM / HIGH (poisoned redirects) |
| URL | `GET /login/?wantsurl=…` with `Host: vulnerable.com` |
| CWE | [CWE-644](https://cwe.mitre.org/data/definitions/644.html) |

### PoC (retest)

```
GET /login/?wantsurl=https://staginglms.eci.gov.in/course/view.php?id=4
Host: vulnerable.com
```

Wrong outcome: **302** `Location: https://awsellm.com/domain/vulnerable.com` (`Server: openresty/1.31.1.1`).

That 302 is the **OpenResty default vhost**, not Moodle. Moodle must never see an untrusted Host; the edge must **400** unknown names (never `$host` in Location).

## Fix

### 1. PHP allowlist (`config.php`) — required in the LMS tree

Before env detection / `setup.php`, `HTTP_HOST` must be one of:

- `staginglms.eci.gov.in`, `staging.iiidem.in`
- `lms.eci.gov.in`, `lms.iiidem.in`
- DDEV / LAN: `iiidem-certification.ddev.site`, `127.0.0.1`, `localhost`, `iiidem.local`, RFC1918, `164.100.26.245`

Anything else → **HTTP 400** `Bad Request` (no Location, no Moodle HTML).

Unknown Host no longer selects `$env = 'dev'` (that used to 500 on missing dataroot). Set **`MOODLE_ENV=staging`** in PHP-FPM.

### 2. OpenResty / nginx / Tengine (required for this PoC)

The 302 to awsellm.com is the catch-all server. Apply [snippets/nginx-host-allowlist.conf](snippets/nginx-host-allowlist.conf):

- Real LMS: `server_name staginglms.eci.gov.in;` only
- `default_server` → **`return 400;`** (not 301 to `$host`, not a parking URL)
- Never `return 301 https://$host$request_uri;`

Reload OpenResty after install.

Apache: [snippets/apache-host-allowlist.conf](snippets/apache-host-allowlist.conf) (`UseCanonicalName On`).

## Verify

```bash
# Expect 400 — never 302 to vulnerable.com or awsellm.com
curl -sI -H 'Host: vulnerable.com' 'https://staginglms.eci.gov.in/login/' | head -n 8
curl -sI -H 'Host: evil.com' 'https://staginglms.eci.gov.in/' | head -n 5

# Legitimate host still works
curl -sI 'https://staginglms.eci.gov.in/login/' | head -n 5
```

## Deploy

1. Deploy `config.php`
2. `MOODLE_ENV=staging` in the web SAPI
3. OpenResty/nginx catch-all `return 400` + exact `server_name`
4. `sudo nginx -t && sudo systemctl reload nginx` (or OpenResty unit)

Related: [mfa-otp-error-handling.md](mfa-otp-error-handling.md).
