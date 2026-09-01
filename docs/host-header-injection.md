# Host header injection

## Finding

| Field | Report |
|-------|--------|
| Title | Host Header Injection |
| Impact | MEDIUM / HIGH (poisoned redirects, password-reset links, cache) |
| URL | `https://staginglms.eci.gov.in/` |
| CWE | [CWE-644](https://cwe.mitre.org/data/definitions/644.html) — Improper Neutralization of HTTP Headers for Scripting Syntax |
| Related | Instance 4 stack traces on `/course/view.php` → [verbose-error-messages.md](verbose-error-messages.md), [debug-mode-staging.md](debug-mode-staging.md) |

### PoC note

Burp: request with `Host: evil.com` received `302 Found`. That means either the edge (nginx/Apache) or the app built an absolute redirect from the client-supplied Host. Password-reset and notification links must never use an untrusted Host.

### Instance 3 (chatbot AJAX) — improper handling of invalid Host

| Field | Report |
|-------|--------|
| URL | `POST /theme/iiidem2/ajax/chatbot_admin_poll.php` |
| Step 1 | `Host: staginglms.eci.gov.in` → `200` `{"success":true,"items":[]}` |
| Step 2 | `Host: evil.eci.gov.in` → **500** `Fatal error: $CFG->dataroot is not configured properly…` |

**Root cause (fixed in `config.php`):** without `MOODLE_ENV`, environment was inferred from `HTTP_HOST`. An unknown Host did not match staging/production lists, so PHP selected **`$env = 'dev'`**, skipped the staging Host allowlist, loaded DDEV-style defaults, then Moodle fatal’d on missing `dataroot` (information disclosure via 500).

**Fix:** for web requests with an unknown Host, if `config.staging.php` / `config.production.php` exists (and the process is not DDEV), select that environment instead of `dev`. The existing Host allowlist then returns **HTTP 400 Bad Request** — no dataroot fatal, no JSON bootstrap with wrong config.

Also set **`MOODLE_ENV=staging`** (or `production`) in the Apache/PHP-FPM environment so Host is never used to pick the env file.

## Resolution

### 1. Fixed `$CFG->wwwroot` (staging / production)

`config.staging.php` / `config.production.php` set an absolute HTTPS wwwroot (e.g. `https://staginglms.eci.gov.in`). Moodle redirects and absolute URLs use that value — not `$_SERVER['HTTP_HOST']`.

Dev-only LAN/DDEV rewriting of wwwroot from Host stays **behind** `$env === 'dev'` and only for private IPs / allowlisted dev names.

### 2. PHP Host allowlist (staging / production)

After the secret config loads, `config.php` rejects any request whose `Host` (hostname only) is not:

- the host of `$CFG->wwwroot`, or  
- an optional alias in `$CFG->wwwroot_allow_hosts`

Mismatched Host → **HTTP 400** `Bad Request` (before Moodle bootstrap continues).

```php
// Optional aliases in config.staging.php:
// $CFG->wwwroot_allow_hosts = ['staginglms.eci.gov.in']; // usually unnecessary if same as wwwroot
```

CLI / cron are skipped (`PHP_SAPI === 'cli'`).

### 3. Edge: exact `server_name` (required)

PHP alone does not stop a proxy that issues `Location: https://evil.com/...` before PHP runs. On staging/production TLS vhosts:

1. Set `server_name` to the real hostname only.  
2. Add a catch-all / default server that returns **444** or **400** for unknown hosts.  
3. Do not use `$host` in absolute redirects; prefer `$server_name` or a hard-coded site URL.

Snippet: [snippets/nginx-host-allowlist.conf](snippets/nginx-host-allowlist.conf).

## Verify

```bash
# Expect 400 (PHP allowlist) or 444/400 from nginx — never 302 to evil.com
# Never: 500 "$CFG->dataroot is not configured properly"
curl -sI -H 'Host: evil.com' 'https://staginglms.eci.gov.in/' | head -n 5
curl -sI -H 'Host: evil.eci.gov.in' 'https://staginglms.eci.gov.in/' | head -n 5

curl -s -X POST -H 'Host: evil.eci.gov.in' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  -d 'sesskey=invalid&sinceid=0' \
  'https://staginglms.eci.gov.in/theme/iiidem2/ajax/chatbot_admin_poll.php'
# Expect: HTTP 400 + body "Bad Request" (not dataroot fatal)

# Legitimate host still works
curl -sI 'https://staginglms.eci.gov.in/' | head -n 5
```

```bash
php -r "define('CLI_SCRIPT', true); require 'config.php'; echo \$CFG->wwwroot . PHP_EOL;"
# Expect fixed https://staginglms.eci.gov.in (no evil.com)
```

## Deploy

1. Deploy updated `config.php` (and ensure `config.staging.php` wwwroot is correct).  
2. Set `MOODLE_ENV=staging` (or `production`) in the web SAPI environment so env is not inferred from Host.  
3. Apply nginx host allowlist on the TLS edge; reload nginx.  
4. No theme upgrade required for this control.

```bash
# After nginx change
sudo nginx -t && sudo systemctl reload nginx
```

## Auditor mapping

| Instance | Evidence | Control |
|----------|----------|---------|
| 3 | `Host: evil.eci.gov.in` on chatbot poll → 500 dataroot fatal | Fixed env detection + PHP 400 allowlist + `MOODLE_ENV` |
| 3 (older) | `Host: evil.com` → 302 | Fixed wwwroot + PHP 400 + nginx `server_name` |
| 4 | Stack trace on `/course/view.php` | Debug off / safe errors (separate finding) |
