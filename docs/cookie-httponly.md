# 26. Cookie without HTTP Only Flag

## Finding

| Field | Report |
|-------|--------|
| Title | 26. Cookie without HTTP Only Flag |
| Impact | LOW (CVSS 3.1) |
| URL | `https://staginglms.eci.gov.in/` (report may cite `cci.gov.in`) |
| CWE | [CWE-1004](https://cwe.mitre.org/data/definitions/1004.html) – Sensitive Cookie Without 'HttpOnly' Flag |
| OWASP | A05:2021 / A05:2025 – Security Misconfiguration |
| CVSS vector | AV:N/AC:L/PR:N/UI:R/S:U/C:L/I:N/A:N |
| Related | [CWE-79](https://cwe.mitre.org/data/definitions/79.html) (XSS → cookie theft if HttpOnly missing) |

> Set the HttpOnly attribute on all session and sensitive cookies.

Without `HttpOnly`, JavaScript (including XSS) can read `document.cookie` and steal the session id.

## PoC

| Instance | URL |
|----------|-----|
| 1 | Site root / any response that sets `MoodleSession*` without `HttpOnly` |

## Fixes

### 1. Forced Moodle config (`config.php`)

Applied on **all** environments:

```php
$CFG->cookiehttponly = true;
@ini_set('session.cookie_httponly', '1');
@ini_set('session.cookie_samesite', 'Lax');
```

On staging/production HTTPS also:

```php
$CFG->cookiesecure = true;
$CFG->cookiehttponly = true; // reaffirm — secret file cannot weaken this
@ini_set('session.cookie_secure', '1');
$CFG->cookiesamesite = 'Lax';
```

Moodle uses these when setting `MoodleSession*` and `MoodleID*` (`lib/classes/session/manager.php`, `lib/sessionlib.php`).

### 2. Response-header belt-and-braces

`theme_iiidem2\session_security::enforce_httponly_on_set_cookie_headers()` runs from `after_config` / `before_http_headers` and adds `HttpOnly` to queued `Set-Cookie` headers for:

- `MoodleSession{suffix}`
- `MoodleID{suffix}`

### 3. Server secret file examples

`config.staging.php.example` / `config.production.php.example` include `cookiehttponly`, `cookiesecure`, `cookiesamesite`.

**On the server**, ensure `config.staging.php` does **not** set `$CFG->cookiehttponly = false`. Prefer:

```php
$CFG->cookiehttponly = true;
$CFG->cookiesecure = true;
$CFG->cookiesamesite = 'Lax';
```

## Deploy

```bash
# After uploading config.php (+ merge cookie lines into config.staging.php if needed)
php admin/cli/purge_caches.php
```

Log out and log in once so browsers receive a fresh Set-Cookie.

## Verify

```bash
curl -sI 'https://staginglms.eci.gov.in/login/index.php' | tr -d '\r' | grep -i set-cookie
```

Expect `MoodleSession…` (and after login `MoodleID…`) to include **`HttpOnly`** (and **`Secure`** on HTTPS).

Browser DevTools → Application → Cookies → `HttpOnly` column = ✓

## Evidence for auditors

| Cookie | HttpOnly | Secure (HTTPS) | SameSite |
|--------|----------|----------------|----------|
| MoodleSession* | Yes (forced) | Yes on staging/prod | Lax |
| MoodleID* | Yes (forced) | Yes on staging/prod | via Moodle cookie API |
| PHP `session.cookie_httponly` | `1` | — | Lax |

Related: [https-sensitive-data.md](https-sensitive-data.md), [session-token-in-url.md](session-token-in-url.md), [input-validation-xss.md](input-validation-xss.md).
