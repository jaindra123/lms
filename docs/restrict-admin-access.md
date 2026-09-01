# Restrict `/admin/` — unauthenticated access & sensitive config disclosure

## Finding (this audit)

| Field | Value |
|-------|--------|
| Title | Unauthenticated Access to Administrative Interface and Sensitive Server Configuration Information |
| Impact | **HIGH** |
| URLs | `https://staginglms.eci.gov.in/admin/`, `/admin/index.php` |
| CWE | CWE-200 – Exposure of Sensitive Information to an Unauthorized Actor |
| OWASP | A01:2025 – Broken Access Control |
| CVSS | 7.5 (`AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:N/A:N`) |

**Claim:** Anyone can open `/admin/` and see application / PHP / database versions and PHP extensions.

## What is true vs overstated

### Moodle application auth (already present)

On a **fully upgraded** site, unauthenticated `/admin/` does **not** show the admin dashboard. It redirects to login:

```http
HTTP/1.1 303 See Other
Location: /login/index.php
```

After login, only users with `moodle/site:config` (site administrators) see the notifications / environment UI. Students get an empty page.

Verified locally the same way scanners hit the URL: `curl -sI …/admin/` → **303 → login**, not 200 with config tables.

### Real leak path (must fix)

`/admin/index.php` runs **upgrade / environment checks before** `require_login()` when the codebase version is ahead of the DB (pending upgrade).

If **`$CFG->upgradekey` is not set**, that environment page (PHP version, DB version, extensions, server checks) is reachable **without authentication**. That matches the CWE-200 description.

`check_upgrade_key()` only blocks the page when `upgradekey` exists in `config.php` settings.

### Why scanners still fail “access to /admin/”

Even with a login redirect, many audits fail if `/admin/` is reachable from the public internet. They want a **web-server** deny (403) for untrusted IPs — defence in depth.

## Resolution (required on staging + production)

### 1. Force upgrade key (`config.php`) — done in repo

For non-dev environments:

1. Prefer `MOODLE_UPGRADEKEY` env var, or `$CFG->upgradekey` in `config.staging.php` / `config.production.php`
2. If still empty, Moodle gets a strong derived fallback so upgrades cannot stay unlocked

Set a **known** secret on the server (do not rely on the fallback long-term):

```bash
# Example — put in process env / secrets manager, then restart PHP-FPM
export MOODLE_UPGRADEKEY='long-random-secret-at-least-32-chars'
```

Or in `config.staging.php` / `config.production.php` (not in git):

```php
$CFG->upgradekey = 'long-random-secret-at-least-32-chars';
```

During web upgrades you must enter this key once; CLI `php admin/cli/upgrade.php` is unaffected.

### 2. Nginx IP allowlist on `/admin/` (ops — staginglms.eci.gov.in)

Only office / VPN / jump-host IPs may open `/admin/`. Everyone else gets **403** before PHP runs.

```nginx
# Restrict Moodle admin UI to trusted networks only.
location ^~ /admin/ {
    # --- EDIT THESE ---
    allow 127.0.0.1;
    allow ::1;
    allow 203.0.113.10;      # office / VPN public IP
    allow 203.0.113.0/24;    # optional office range
    deny all;

    location ~ \.php(?:$|/) {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php-fpm.sock;   # adjust
    }

    try_files $uri $uri/ /index.php?$query_string;
}
```

```bash
sudo nginx -t && sudo systemctl reload nginx
```

### 3. Apache equivalent (ops — staging/production RHEL)

Use [snippets/apache-admin-allowlist.conf](snippets/apache-admin-allowlist.conf) in the **vhost** (preferred).

**Critical:** MFA uses `/admin/tool/mfa/auth.php`. A blanket `/admin/` allowlist will **403 after login**. Always allow MFA for all clients:

```apache
# MFA must stay open for every user after login
<LocationMatch "^/admin/tool/mfa">
    Require all granted
</LocationMatch>

# Site administration UI — trusted IPs only
<LocationMatch "^/admin(/|$)(?!tool/mfa)">
    Require ip 127.0.0.1
    Require ip ::1
    Require ip 10.26.89.1
    Require ip 10.206.96.0/23
    # Require ip YOUR.PUBLIC.OFFICE.IP
</LocationMatch>
```

```bash
httpd -t && systemctl reload httpd
```

Public clients should get **403** on `/admin/` and `/admin/index.php`; MFA and login must still work from any IP.### 4. Optional: HTTP Basic Auth in front of `/admin/`

Use with `satisfy all` if auditors want a second password **and** IPs are unstable.

### 5. Keep Moodle controls

| Control | Status |
|--------|--------|
| `require_login` + `moodle/site:config` on admin UI | Core behaviour |
| `$CFG->upgradekey` | Forced for staging/production in `config.php` |
| MFA for privileged accounts | [mfa-privileged-accounts.md](mfa-privileged-accounts.md) |
| Version headers reduced | [version-disclosure.md](version-disclosure.md) |

## Verify (must pass the audit)

```bash
# From public / scanner network — expect 403 after nginx allowlist
curl -sI https://staginglms.eci.gov.in/admin/
curl -sI https://staginglms.eci.gov.in/admin/index.php

# From office/VPN — expect 303/302 to login (not environment tables)
curl -sI https://staginglms.eci.gov.in/admin/
# Expect: Location: …/login/index.php

# Body must NOT contain PHP/MariaDB extension tables while logged out
curl -sL https://staginglms.eci.gov.in/admin/index.php | grep -iE 'PHP version|MariaDB|environment'
# Expect: no matches (login page only)
```

## After deploy checklist

1. Set `MOODLE_UPGRADEKEY` (or `$CFG->upgradekey`) on staging/production; restart PHP-FPM.
2. Apply nginx/Apache allowlist with real IPs; reload web server.
3. Confirm **403** from outside, login works from office/VPN.
4. Confirm no environment/version tables appear while logged out.
5. Re-run the scan / attach evidence (403 + login redirect screenshots; IPs redacted).

## Important notes

- Do **not** block `/login/` site-wide — only `/admin/`.
- CLI (`php admin/cli/...`) is unaffected.
- If TLS terminates on a CDN, configure `real_ip_header` / `X-Forwarded-For` or the allowlist will be wrong.
- Finish pending upgrades promptly so `/admin/` does not sit in “upgrade mode”.
