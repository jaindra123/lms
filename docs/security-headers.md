# Header Issues — security response headers

## Finding

> Set CSP header, X-Content-Type-Options: nosniff, X-XSS-Protection: 1; mode=block,
> Referrer-Policy: strict-origin-when-cross-origin, Access-Control-Allow-Origin,
> Clear-Site-Data: “cache”, “cookies”, “storage”, “executionContexts”

## Implementation

Helper: `theme/iiidem2/classes/security_headers.php`  
Sent on every web request via:

- `hook_listener::after_config` (covers AJAX / scripts without `$OUTPUT->header()`)
- `hook_listener::before_http_headers` (full page renders)

Logout Clear-Site-Data via observer on `\core\event\user_loggedout`.

### Headers set

| Header | Value |
|--------|--------|
| `Content-Security-Policy` | Restrictive policy (`default-src 'self'`, `object-src 'none'`, `frame-ancestors 'self'`, …). Allows Moodle AMD inline/`unsafe-eval` and Razorpay checkout hosts. |
| `X-Content-Type-Options` | `nosniff` |
| `X-XSS-Protection` | `1; mode=block` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Access-Control-Allow-Origin` | Site’s own origin from `$CFG->wwwroot` (not `*`) |
| `X-Frame-Options` | `SAMEORIGIN` |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` when wwwroot is HTTPS |
| `Clear-Site-Data` | `"cache", "cookies", "storage", "executionContexts"` **on logout only** |
| Version disclosure | Removes `X-Powered-By` / related tech headers (see `docs/version-disclosure.md`) |

### Why ACAO is the site origin (not `*`)

Reflecting `*` (or any `Origin`) on an authenticated LMS enables cross-site data reading. Auditors require the header to be **present**; binding it to the LMS origin satisfies that without opening CORS to the world.

### Why Clear-Site-Data is logout-only

Sending Clear-Site-Data on every response would wipe cookies/storage continuously and break the site. Spec intent is to clear browser data when the user signs out.

### Optional nginx mirror (edge)

```nginx
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header X-Frame-Options "SAMEORIGIN" always;
# CSP is set by Moodle; duplicate carefully if also set here.
```

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Verify:

```bash
curl -I https://staginglms.eci.gov.in/login/index.php | grep -iE 'content-security|nosniff|xss-protection|referrer-policy|access-control-allow-origin'
```

## Evidence for auditors

| Requirement | Implementation |
|-------------|----------------|
| CSP | `Content-Security-Policy` on all web responses |
| nosniff | `X-Content-Type-Options: nosniff` |
| XSS filter | `X-XSS-Protection: 1; mode=block` |
| Referrer | `strict-origin-when-cross-origin` |
| ACAO | Own wwwroot origin |
| Clear-Site-Data | On logout: cache, cookies, storage, executionContexts |
