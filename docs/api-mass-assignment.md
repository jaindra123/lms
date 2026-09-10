# API Mass Assignment

## Finding

| Field | Report |
|-------|--------|
| Title | API Mass Assignment |
| Impact | MEDIUM (typical report) |
| CWE | [CWE-915](https://cwe.mitre.org/data/definitions/915.html) — Improperly Controlled Modification of Dynamically-Determined Object Attributes |
| Endpoint | `POST /lib/ajax/service.php` (`media_videojs_get_language`) |

> Implement strict server-side input validation and define an explicit allowlist/schema of permitted API parameters.

### PoC (auditor)

**Step 1 — baseline** (succeeds):

```json
[{"index":0,"methodname":"media_videojs_get_language","args":{"lang":"en"}}]
```

**Step 2 — injected object** (second array element, not inside `args`):

```json
[
  {"index":0,"methodname":"media_videojs_get_language","args":{"lang":"en"}},
  {"isadmin":true,"issso":true,"role":"admin"}
]
```

Observed response: error (`codingerror` / generic message) — **not** privilege elevation. Roles and admin status are never taken from AJAX JSON; they come from the Moodle session and capability checks.

**Staging recheck (2026-09):** Step 1 returns Video.js strings (`error: false`). Step 2 with `"isadmin"/"issso"/"role":"admin"` returns `errorcode: codingerror` / generic message — **no admin session, no role change**. **Dispute / closed** as CWE-915.

## How Moodle already prevents mass assignment

| Layer | Behaviour |
|-------|-----------|
| Per-function schema | `external_function_parameters` / `execute_parameters()` (e.g. `media_videojs` allows only `lang`) |
| `external_api::validate_parameters()` | **Throws** on unexpected keys in `args` (`Unexpected keys (…) detected`) |
| AuthZ | `require_capability` / login / sesskey — not binder flags like `isadmin` |

Injecting `"isadmin":true` **inside** `args` for `media_videojs_get_language` is rejected by schema validation. The auditor’s Step 2 adds a **malformed second batch item** (no `methodname` / `args`) — that is not object mass assignment; it is an invalid batch envelope.

## Additional control (theme ≥ `2024101030`)

| Piece | Role |
|-------|------|
| `theme_iiidem2\ajax_request_guard` | Allowlist envelope keys: `index`, `methodname`, `args` only |
| Forbidden keys in `args` | `isadmin`, `issso`, `role`, `roles`, `admin`, `capability`, … |
| `lib/ajax/service.php` | Calls the guard before `call_external_function`; fail-closed on cookie sessions |

Rejected calls return `errorcode: invalidparameter` (no stack / no privilege grant).

## Dispute guidance

| Claim | Response |
|-------|----------|
| Adding `isadmin` / `role` elevates privileges | **False** — never mapped to `$USER` / roles |
| HTTP 200 with `error: true` means mass assignment worked | **False** — Moodle AJAX uses 200 + JSON error payload |
| Need allowlist of parameters | **Already present** in each external function schema; envelope allowlist added for the batch shape |

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Deploy both `theme/iiidem2` (guard class) and `lib/ajax/service.php`.

## Verify

```bash
# Baseline — must succeed (error:false)
# POST service.php body:
# [{"index":0,"methodname":"media_videojs_get_language","args":{"lang":"en"}}]

# Mass-assignment style second object — must fail, no admin grant:
# [ {...valid...}, {"isadmin":true,"issso":true,"role":"admin"} ]
# Expect: invalidparameter (or generic error), user still non-admin

# Extra key inside args — must fail:
# [{"index":0,"methodname":"media_videojs_get_language","args":{"lang":"en","isadmin":true}}]
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Schema allowlist per API | Moodle `external_function_parameters` |
| Reject unknown `args` keys | `external_api::validate_parameters` |
| Reject privilege keys / bad envelope | `ajax_request_guard` + `service.php` |
| No role from JSON | Session + capabilities only |
