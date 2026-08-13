# Web Application Source Code Disclosure Pattern Found

## Finding

> Remove source code files from your web-server and apply any relevant patches.

Scanners flag patterns such as:

- Exposed `/.git/`, IDE folders, `composer.json` / `composer.lock`
- Backup / editor leftovers: `*.php.bak`, `*~`, `*.swp`, `*.old`, `*.sql`
- Env files: `.env`, `config.staging.php` served as static downloads
- Directory indexes that reveal PHP/plugin trees

Moodle’s **application** PHP under `/lib`, `/theme`, etc. must remain on the server to run; the control is to **deny HTTP access** to VCS, secrets, backups, and non-runtime artefacts — not to delete the LMS codebase.

## Fixes in this repo

### 1. Nginx (DDEV + staging template)

`.ddev/nginx/directory-listing.conf`:

| Block | Examples |
|-------|----------|
| VCS / tooling dirs | `/.git/`, `/.ddev/`, `/.github/`, `/.cursor/` |
| Ops / data / docs | `/backups/`, `/moodledata/`, `/docs/`, `/scripts/`, `/local_dev_logs/` |
| Secrets | `config.staging.php`, `config.production.php`, `.env*` |
| Leftovers | `*.bak`, `*.old`, `*.swp`, `*~`, `*.sql`, `*.sql.gz` |
| Deps / tests | `composer.json/lock`, `vendor/`, `phpunit.xml`, Behat (MDL-69333) |
| Listing | `autoindex off` |

Copy the same `location` rules into the **staging/production** nginx vhost (DDEV conf is not used on bare metal).

### 2. Apache / XAMPP

Root `/.htaccess` + per-directory deny under `backups/`, `moodledata/`, `docs/`, `scripts/`, `local_dev_logs/`.

### 3. Ops hygiene (do on the server)

```bash
# Remove accidental leftovers from the document root (examples — adjust paths).
find /var/www/html -type f \( -name '*.bak' -o -name '*.old' -o -name '*~' -o -name '*.swp' -o -name '.env' \) -print
# Review then delete; do not leave dumps under the web root.
rm -f /var/www/html/*.sql /var/www/html/*.sql.gz /var/www/html/info.php

# Prefer dataroot outside the docroot:
# $CFG->dataroot = '/var/moodledata';
```

Do **not** deploy `.git` to production if avoidable; if present, nginx/Apache **must** deny `/.git/`.

## Deploy

```bash
# DDEV
ddev restart

# Staging nginx (after merging location rules)
sudo nginx -t && sudo systemctl reload nginx
```

## Verify

```bash
curl -sI https://YOUR-HOST/.git/config
curl -sI https://YOUR-HOST/composer.json
curl -sI https://YOUR-HOST/config.staging.php
curl -sI https://YOUR-HOST/config.php.bak
curl -sI https://YOUR-HOST/.env
# Expect 403 or 404 — not 200 with file body
```

Normal LMS URLs (`/`, `/login/`, `/course/view.php`) must still return **200**.

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| No directory listing | `autoindex off` / `Options -Indexes` |
| No VCS over HTTP | Deny `/.git/` |
| No secret configs | Deny `config.staging.php`, `.env*` |
| No backup / editor files | Deny `*.bak`, `*~`, `*.swp`, `*.sql` |
| No dependency manifests | Deny `composer.json` / `vendor/` (public) |
| Runtime PHP retained | Moodle app code remains; only non-runtime artefacts blocked |

Related: [directory-listing.md](directory-listing.md), [version-disclosure.md](version-disclosure.md), [restrict-admin-access.md](restrict-admin-access.md).
