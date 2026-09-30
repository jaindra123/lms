# RT-001 — Public exposure of npm-shrinkwrap.json

## Finding

| Field | Value |
|-------|--------|
| ID | RT-001 |
| Title | Public Exposure of NPM Dependency Lock file |
| Severity | LOW |
| Host | `https://iiidemlms.eci.gov.in/` |
| Path | `/npm-shrinkwrap.json` |
| CWE | CWE-200 — Exposure of Sensitive Information to an Unauthorized Actor |
| OWASP | A02:2025 – Security Misconfiguration |
| CVSS 3.1 | `AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N` |

Unauthenticated `GET /npm-shrinkwrap.json` returned **200** with Moodle’s Node lockfile (package names, exact versions, registry URLs, integrity hashes, `devDependencies`).

## What is true

Moodle **ships** `npm-shrinkwrap.json` in the document root. It is the Grunt/ESLint/Sass toolchain lockfile (devDependencies only). It is **not** application runtime config and contains **no** DB passwords, upgrade keys, or API secrets.

Serving it over HTTP still helps an attacker map the frontend build stack. That is a real CWE-200 misconfiguration.

## What not to do

- Do **not** delete `npm-shrinkwrap.json` from the git tree. Developers need it for `grunt` / `npm ci`.
- Do **not** move it outside the repo; Moodle’s build paths expect it at the root.

## Fix in this repo

Deny HTTP. Keep the file on disk.

| Layer | File |
|-------|------|
| DDEV nginx | `.ddev/nginx/directory-listing.conf` — `location = /npm-shrinkwrap.json` → **404** |
| Staging / production nginx | Copy `docs/snippets/nginx-directory-listing.conf` into the LMS `server { }` |
| Apache / XAMPP | Root `.htaccess` rewrite + `<FilesMatch>` deny |

Also blocked: `package.json`, `package-lock.json`, `yarn.lock`, `pnpm-lock.yaml` (same class of lock/manifest).

## Deploy (production)

1. Merge this change.
2. On the production nginx/Tengine vhost, add the same `location` blocks from `docs/snippets/nginx-directory-listing.conf` (DDEV conf is **not** used on bare metal).
3. If production is Apache, deploy the updated root `.htaccess` and confirm `AllowOverride` is on.
4. Test config and reload:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

## Verify

```bash
curl -sI https://iiidemlms.eci.gov.in/npm-shrinkwrap.json
curl -sI https://iiidemlms.eci.gov.in/package.json
curl -sI https://iiidemlms.eci.gov.in/package-lock.json
# Expect: 404 (or 403) — not 200 with JSON body

curl -sI https://iiidemlms.eci.gov.in/login/index.php
# Expect: 200 / 303 — LMS still works
```

Local DDEV after `ddev restart`:

```bash
curl -sI https://iiidem-certification.ddev.site/npm-shrinkwrap.json
```

## Auditor evidence

| Before | After |
|--------|--------|
| `GET /npm-shrinkwrap.json` → 200 JSON lockfile | `GET /npm-shrinkwrap.json` → **404/403**, no body with package versions |
| File remains in document root for grunt | HTTP deny only |

Related: [source-code-disclosure.md](source-code-disclosure.md), [directory-listing.md](directory-listing.md).
