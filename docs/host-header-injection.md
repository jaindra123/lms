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
curl -sI -H 'Host: evil.com' 'https://staginglms.eci.gov.in/' | head -n 5

# Legitimate host still works
curl -sI 'https://staginglms.eci.gov.in/' | head -n 5
```

```bash
php -r "define('CLI_SCRIPT', true); require 'config.php'; echo \$CFG->wwwroot . PHP_EOL;"
# Expect fixed https://staginglms.eci.gov.in (no evil.com)
```

## Deploy

1. Deploy updated `config.php` (and ensure `config.staging.php` wwwroot is correct).  
2. Apply nginx host allowlist on the TLS edge; reload nginx.  
3. No theme upgrade required for this control.

```bash
# After nginx change
sudo nginx -t && sudo systemctl reload nginx
```

## Auditor mapping

| Instance | Evidence | Control |
|----------|----------|---------|
| 3 | `Host: evil.com` → 302 | Fixed wwwroot + PHP 400 + nginx `server_name` |
| 4 | Stack trace on `/course/view.php` | Debug off / safe errors (separate finding) |
