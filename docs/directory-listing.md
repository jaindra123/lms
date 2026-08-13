# Directory listing — Restrict access to sensitive directories

## Finding

| Field | Report |
|-------|--------|
| Title | 20. Directory listing |
| Impact | MEDIUM / CVSS 5.3 |
| CWE | [CWE-548](https://cwe.mitre.org/data/definitions/548.html) — Exposure of Information Through Directory Listing |
| OWASP | A05:2021 – Security Misconfiguration |
| CVSS vector | `AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N` |
| Host note | Some URLs use `staginglms.cci.gov.in` — retest on `staginglms.eci.gov.in` |

> The web server allows directory listing when no default index file is present. Disable autoindex / `Options Indexes`.

### PoC note (Instance 1)

Report URL `https://staginglms.eci.gov.in/lib/default` — the included screenshot shows **Forbidden** (“You don't have permission to access this resource”), **not** an `Index of /` file table. That is the **correct** outcome with listing disabled. Treat scanner URL lists below as probes; success for auditors is **403/404/login redirect/empty index**, never an HTML directory index.

Earlier Tengine PoC (page with `backup/` table row) is the real listing evidence — fixed by `autoindex off` on the edge.

## Report URLs (mapping)

| Cited URL | Expected after remediaiton |
|-----------|----------------------------|
| `/lib/default` | **403 Forbidden** or login redirect — **not** a listing (matches Instance 1 screenshot) |
| `/info`, `/info.php` | **403** (blocked droppings); Moodle admin phpinfo stays at `/admin/phpinfo.php` (auth) |
| `/register/` | **200** app page (`register/index.php`) — not a file list |
| `/admin/`, `/admin/lib`, `/admin/cron`, `/admin/upgrade` | Login redirect / admin UI — not a file list |
| `/auth/upgrade`, `/auth/index`, `/analytics/upgrade` | Login redirect or Moodle routing — not a file list |
| `/auth/index.html` | Empty Moodle placeholder (**200**, 0 bytes) — prevents listing; not a file table |
| `/notes` / `/notes/` | Moodle notes UI (`notes/index.php`) — not a file list |
| `/about-us/index.php`, `/about-us/index` | Theme/page route or redirect — not a file list |
| `/backup/` (core) | **403** when autoindex off (no `index.php`; named scripts still work) |
| `/backups/` (ops dumps) | **403** deny-all |
| `/README`, `/composer`, `/package`, `/version`, `/security`, `/config` | **404** / redirect / blocked artefact rules — not a listing |
| `/wp-login.php` | **404** (not WordPress) |
| `/index`, `/index.php` | Front page / redirect — normal |
| `/icons/`, `/icons/apache_pb.gif` | **404/403** — Apache default icon alias disabled (Instance 2) |
| `pluginfile.php/…/` trailing slash | Moodle **404**/error — not a filesystem index |

### PoC note (Instance 2 — `/icons/`)

Burp / browser: `https://staginglms.eci.gov.in/icons/` showed **Index of /icons** (a.gif, apache_pb.gif, …) and `GET /icons/apache_pb.gif` returned **200** GIF + `Server: Apache`. That is the **stock Apache `/icons/` Alias**, not Moodle. Disable the Alias and block `/icons/` at the edge (see below). Host in some lines is `cci.gov.in` — retest on `eci.gov.in`.

Report CWE link CWE-1104 on this page is mis-tagged; listing is **CWE-548**.

## Risk

When a web server lists directory contents (or serves files from ops/backup folders), attackers can discover dumps, logs, configs, and internal docs.

Sensitive paths that must not be public:

| Path | Why |
|------|-----|
| `/backups/` | Database dumps |
| `/moodledata/` | Sessions, uploaded files, caches (dataroot) |
| `/local_dev_logs/` | Registration / debug logs |
| `/docs/` | Internal security remediaiton notes |
| `/scripts/` | Ops scripts |
| `/.git/`, `/.ddev/` | Source / environment metadata |
| `config.staging.php` / `config.production.php` | Credentials (if present under web root) |

## Resolution in this repo

### 1. Nginx (DDEV)

`.ddev/nginx/directory-listing.conf`:

- `autoindex off;` — never emit directory indexes
- `location ^~ /…/` deny for the sensitive directories above
- Block direct fetch of `config.(staging|production|dev).php` and `config-dist.php`
- Moodle-recommended deny pattern for `/vendor/`, `/node_modules/`, `composer.json`, `readme`, `db/install.xml`, Behat/PHPUnit artefacts (MDL-69333)

Restart after pull: `ddev restart`

### 2. Apache / XAMPP

| File | Purpose |
|------|---------|
| `/.htaccess` | `Options -Indexes` + rewrite forbid for sensitive paths |
| `/backups/.htaccess` | `Require all denied` (create if missing) |
| `/moodledata/.htaccess` | `Require all denied` |
| `/local_dev_logs/.htaccess` | `Require all denied` |
| `/docs/.htaccess` | `Require all denied` |
| `/scripts/.htaccess` | `Require all denied` |

Requires `AllowOverride` (or equivalent) so `.htaccess` is honoured.

### 3. `.gitignore`

`/backups/` and `/local_dev_logs/` ignored so dumps/logs are less likely to be committed.

## Required on staging / production

**Tengine / nginx on staging must set `autoindex off;`** (PoC Server header was `Tengine`). Copy [snippets/nginx-directory-listing.conf](snippets/nginx-directory-listing.conf).

Application code cannot fully replace edge config.

### nginx / Tengine

```nginx
autoindex off;

location ^~ /backups/ { deny all; return 403; }
location ^~ /moodledata/ { deny all; return 403; }
location ^~ /local_dev_logs/ { deny all; return 403; }
location ^~ /docs/ { deny all; return 403; }
location ^~ /scripts/ { deny all; return 403; }
location ^~ /.git/ { deny all; return 403; }

location ~* ^/config\.(staging|production|dev)\.php$ { deny all; return 403; }

location = /info.php { deny all; return 403; }
location = /phpinfo.php { deny all; return 403; }
location = /test.php { deny all; return 403; }
```

Prefer keeping `dataroot` **outside** the document root on production (`$CFG->dataroot`).

### Apache

```apache
Options -Indexes
ServerTokens Prod
ServerSignature Off

# Disable default icon tree (CDAC Instance 2: /icons/apache_pb.gif).
# Comment out or remove in httpd.conf / apache2.conf / conf.d:
#   Alias /icons/ "/usr/share/apache2/icons/"
#   <Directory "/usr/share/apache2/icons"> ... </Directory>
# Belt-and-braces (also in repo root .htaccess):
#   RewriteRule ^icons(/|$) - [F,L]

# Plus the .htaccess files from this repo, or equivalent <Directory> Deny/Require rules.
```

## Verify

```bash
# Must NOT return HTML file tables (Index of / …).
curl -sI https://staginglms.eci.gov.in/backup/
curl -sI https://staginglms.eci.gov.in/backups/
curl -sI https://staginglms.eci.gov.in/docs/
curl -sI https://staginglms.eci.gov.in/theme/iiidem2/
curl -sI https://staginglms.eci.gov.in/info.php
curl -sI https://staginglms.eci.gov.in/lib/default
curl -sI https://staginglms.eci.gov.in/README
curl -sI https://staginglms.eci.gov.in/wp-login.php
curl -sI https://staginglms.eci.gov.in/icons/
curl -sI https://staginglms.eci.gov.in/icons/apache_pb.gif

# Expect 403/404/3xx — not 200 with <title>Index of
curl -s https://staginglms.eci.gov.in/backup/ | grep -iE 'Index of|Parent Directory|<a href="backup/' && echo FAIL || echo OK
curl -s https://staginglms.eci.gov.in/lib/default | grep -iE 'Index of|Parent Directory' && echo FAIL || echo OK
# Expect: not image/gif for apache_pb.gif
curl -sI https://staginglms.eci.gov.in/icons/apache_pb.gif | grep -iE '^HTTP|^[Cc]ontent-[Tt]ype'

# App entry points still work
curl -sI https://staginglms.eci.gov.in/register/   # 200 (or redirect)
curl -sI https://staginglms.eci.gov.in/admin/      # redirect to login OK
```

## Deploy

```bash
# DDEV
ddev restart

# Production nginx / Tengine
sudo nginx -t && sudo systemctl reload nginx

# Production Apache — also remove Alias /icons/ and set ServerTokens Prod
sudo apachectl configtest && sudo systemctl reload httpd
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Directory listing disabled | nginx/`Tengine` `autoindex off` + Apache `Options -Indexes` |
| Apache `/icons/` disabled | nginx `location ^~ /icons/` deny; remove Apache `Alias /icons/` |
| Sensitive dirs blocked | nginx `location ^~` deny + per-dir Apache `.htaccess` |
| Backup / log / dataroot | Explicit deny for `/backups/` etc.; `/backup/` has no index → 403 with autoindex off |
| Probe scripts | `/info.php` denied |
| Internal Moodle artefacts | MDL-69333-style location deny |
| Env config files | Direct HTTP to `config.staging.php` / `config.production.php` forbidden |

Related: [source-code-disclosure.md](source-code-disclosure.md), [version-disclosure.md](version-disclosure.md).
