# Extensionless URLs (strip `.php`)

## Goal

Outgoing Moodle links omit `.php` where safe:

| Legacy | Clean |
|--------|--------|
| `/admin/search.php#linkcourses` | `/admin/search#linkcourses` |
| `/course/view.php?id=4` | `/course/view?id=4` |
| `/login/index.php` | `/login` |

PHP files stay on disk. Nginx/Apache map extensionless requests back to `*.php`.

**Login:** Navbar, `get_login_url()`, and `url_rewriter` all emit `/login`. DDEV maps it via [`.ddev/nginx/clean-login.conf`](../.ddev/nginx/clean-login.conf). After changing nginx snippets run `ddev restart`.

## Layers

| Layer | Role |
|-------|------|
| `$CFG->urlrewriteclass` | [`theme_iiidem2\output\url_rewriter`](../theme/iiidem2/classes/output/url_rewriter.php) — strips trailing `.php` from `moodle_url->out()`; `/login/index.php` → `/login` |
| `get_login_url()` | Returns `{wwwroot}/login` |
| DDEV nginx | [`.ddev/nginx/extensionless-php.conf`](../.ddev/nginx/extensionless-php.conf) + [`.ddev/nginx/clean-login.conf`](../.ddev/nginx/clean-login.conf) |
| Apache | [`.htaccess`](../.htaccess) login rule + [snippets/apache-extensionless-php.conf](snippets/apache-extensionless-php.conf) on staging/prod |

### Staging / production (Apache) — extensionless `.php`

Do **not** copy `.ddev/nginx/extensionless-php.conf`. Use the Apache snippet:

```bash
# From Moodle docroot on the server (adjust path)
sudo cp /var/www/html/lms_stage/docs/snippets/apache-extensionless-php.conf \
  /etc/httpd/conf.d/extensionless-php.conf
# Prefer pasting the RewriteRule block into the HTTPS VirtualHost instead of a global conf.d include.
sudo apachectl configtest && sudo systemctl reload httpd
```

App side (must already be deployed): `$CFG->urlrewriteclass` + theme `url_rewriter` so links emit `/admin/search` instead of `/admin/search.php`.

```bash
curl -sI 'https://staginglms.eci.gov.in/admin/search' | head -5
# Expect 200 / login redirect — not 404
```

### Staging `/login` pitfall (Apache)

`login/` is a real Moodle directory.

**Do not add `login/.htaccess` unless it is world-readable.** If Apache cannot read it you get:

`AH00529: .../login/.htaccess pcfg_openfile: unable to check htaccess file` → **403** for `/login/`, `/login/index.php`, etc.

**Deploy:**

1. Root `.htaccess`: `RewriteRule ^login/?$ login/index.php [END,QSA]` (no 301 to `/login/`)
2. **No** `login/.htaccess` (or `chmod 644` + `chmod 755 login/`)
3. VirtualHost (recommended):

```apache
RewriteEngine On
RewriteRule ^/login/?$ /login/index.php [L]
```

4. `$CFG->sslproxy = true` when TLS terminates in front of Apache
5. `$CFG->urlrewriteclass` + `get_login_url()` → `/login` (enabled in `config.php`)

**Diagnose:**

```bash
# Permissions (must allow Apache to traverse login/ and read any .htaccess)
namei -l /var/www/html/lms_stage/login/.htaccess
# If .htaccess exists and is unreadable: remove it or chmod 644

curl -sI -X GET 'https://staginglms.eci.gov.in/login/index.php' \
  | grep -iE 'HTTP/|X-Moodle-Exception|X-Moodle-Errorcode'

# PHP Error message (RHEL often uses www-error.log, not php-fpm/error.log)
grep -i 'Moodle exception' /var/log/php-fpm/www-error.log | tail -5
journalctl -u php-fpm -n 50 --no-pager | grep -i 'Moodle exception'
```

Do not rely on `.ddev/nginx/clean-login.conf` on staging.

## Kept with `.php`

Slash-argument / asset pipelines are **not** rewritten:

- `pluginfile.php/…`, `tokenpluginfile.php/…`, `draftfile.php/…`, `file.php/…`
- `javascript.php/…`, `requirejs.php/…`, `yui_combo.php`, `image.php`, `styles.php`, …

## Deploy

1. Ship theme rewriter + `config.php` (`urlrewriteclass`) + nginx/Apache rules  
2. `ddev restart` (or reload nginx / Apache)  
3. `php admin/cli/upgrade.php --non-interactive`  
4. `php admin/cli/purge_caches.php`  

Theme ≥ `2024101044`.

## Verify

1. Open Site administration → URL should become `/admin/search` (or `#…` fragment) without `.php` after load (`history.replaceState`)  
2. Direct hit `https://…/admin/search` loads the same page as `search.php`  
3. Course images / `pluginfile.php/…` still contain `.php`  
4. `/login` still works  
5. Saving an admin form via an extensionless action URL succeeds  

## Directory vs script conflicts

Moodle has pairs like `admin/settings.php` **and** `admin/settings/` (subdirectory).  
Stripping to `/admin/settings` made nginx treat it as a directory → **403 Forbidden** (autoindex off).

**Control:** `url_rewriter` keeps `.php` when the clean path is an existing directory; nginx/Apache prefer `*.php` over `$uri/`.

## Dashboard `/my/`

Extensionless regex must not match trailing-slash paths. A pattern like `/[^.]+` matched `/my/` and rewrote to missing `my.php` → nginx **404**.

**Control:** match only paths without `.` and without a trailing `/`; if the path is a directory, redirect to `…/`; map `/my/index.php` → `/my/`.

  
