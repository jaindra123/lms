# Directory listing — restrict access to sensitive directories

## Finding

> Directory listing  
> Restrict access to sensitive directories.

## Risk

When a web server lists directory contents (or serves files from ops/backup folders), attackers can discover dumps, logs, configs, and internal docs.

Sensitive paths in this project that must not be public:

| Path | Why |
|------|-----|
| `/backups/` | Database dumps |
| `/moodledata/` | Sessions, uploaded files, caches (dataroot) |
| `/local_dev_logs/` | Registration / debug logs |
| `/docs/` | Internal security remediation notes |
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
| `/backups/.htaccess` | `Require all denied` |
| `/moodledata/.htaccess` | `Require all denied` |
| `/local_dev_logs/.htaccess` | `Require all denied` |
| `/docs/.htaccess` | `Require all denied` |
| `/scripts/.htaccess` | `Require all denied` |

Requires `AllowOverride` (or equivalent) so `.htaccess` is honoured.

### 3. `.gitignore`

`/backups/` and `/local_dev_logs/` ignored so dumps/logs are less likely to be committed.

## Required on staging / production

Application code cannot fully replace edge config. Ensure the live vhost also disables listing and denies the same paths.

### nginx

```nginx
autoindex off;

location ^~ /backups/ { deny all; return 403; }
location ^~ /moodledata/ { deny all; return 403; }
location ^~ /local_dev_logs/ { deny all; return 403; }
location ^~ /docs/ { deny all; return 403; }
location ^~ /scripts/ { deny all; return 403; }
location ^~ /.git/ { deny all; return 403; }

location ~* ^/config\.(staging|production|dev)\.php$ { deny all; return 403; }

# Leftover phpinfo scripts (not Moodle admin/phpinfo.php)
location = /info.php { deny all; return 403; }
location = /phpinfo.php { deny all; return 403; }
location = /test.php { deny all; return 403; }

# Optional: Moodle internal artefacts (see docs.moodle.org/en/Nginx)
location ~* (?:/vendor/|/node_modules/|/composer\.(?:json|lock)$|/(?:readme|README)|/upgrade\.txt$|/db/install\.xml$|/behat/|/phpunit\.xml) {
    deny all;
    return 404;
}
```

Prefer keeping `dataroot` **outside** the document root on production (`$CFG->dataroot`).

### Apache

```apache
Options -Indexes
# Plus the .htaccess files from this repo, or equivalent <Directory> Deny/Require rules.
```

## Verify

```bash
# Directories must not list (403/404, never an HTML file index).
curl -sI https://YOUR-HOST/backups/
curl -sI https://YOUR-HOST/docs/
curl -sI https://YOUR-HOST/moodledata/
curl -sI https://YOUR-HOST/theme/iiidem2/

# Files under denied dirs must not download.
curl -sI https://YOUR-HOST/backups/any-dump.sql.gz
```

Expect **403** (or **404**) — not **200** with a listing or file body.

Site pages (`/`, `/login/`, `/course/…`) must still return **200**.

## Deploy

```bash
# DDEV
ddev restart

# Production nginx
sudo nginx -t && sudo systemctl reload nginx

# Production Apache
sudo apachectl configtest && sudo systemctl reload httpd
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Directory listing disabled | nginx `autoindex off` + Apache `Options -Indexes` |
| Sensitive dirs blocked | nginx `location ^~` deny + per-dir Apache `.htaccess` |
| Backup / log / dataroot | Explicit deny rules |
| Internal Moodle artefacts | MDL-69333-style location deny |
| Env config files | Direct HTTP to `config.staging.php` / `config.production.php` forbidden |
