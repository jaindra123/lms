# API Mass Assignment

## Finding

| Field | Report |
|-------|--------|
| Title | API Mass Assignment |
| Recommendation | Implement strict server-side input validation and define an explicit allowlist/schema of permitted API parameters |
| Endpoint | `POST /lib/ajax/service.php` (`media_videojs_get_language`) |

## PoC

**Step 1 — permitted schema** (`lang` only):

```json
[{"index":0,"methodname":"media_videojs_get_language","args":{"lang":"en"}}]
```

Returns Video.js English strings (`error: false`). That is the **allowed** call.

**Step 2 — extra object / privilege keys** (not in the schema):

```json
[
  {"index":0,"methodname":"media_videojs_get_language","args":{"lang":"en"}},
  {"isadmin":true,"issso":true,"role":"admin"}
]
```

or extra keys inside `args`:

```json
[{"index":0,"methodname":"media_videojs_get_language","args":{"lang":"en","isadmin":true}}]
```

Must **not** grant admin/SSO/role. Must fail closed with `invalidparameter`.

## LMS remediations (`theme_iiidem2` ≥ `2024101083`)

| Layer | Allowlist / validation |
|-------|------------------------|
| Batch envelope | Only `index`, `methodname`, `args`. Extra keys (`isadmin`, `role`, …) → `invalidparameter`. |
| Forbidden arg keys | `isadmin`, `issso`, `role`, `roles`, `capability`, `sesskey`, `wstoken`, `auth`, … |
| `media_videojs_get_language` schema | **Only** `lang`. Extra or missing keys rejected. |
| `lang` value | Must match `xx` or `xx-YY` / `xx_YY` **and** exist as `media/player/videojs/videojs/lang/{lang}.json` (realpath, no `..`). |
| Moodle schema | `external_function_parameters` still rejects unexpected keys on every other AJAX method. |
| AuthZ | Roles / admin come from the session, never from JSON. |

`get_language_content()` no longer concatenates `$lang` into a file path without an allowlist.

## Retest

1. `args: {"lang":"en"}` → `error: false`, JSON language pack.
2. Second batch item `{"isadmin":true,"issso":true,"role":"admin"}` → `invalidparameter`, user still not admin.
3. `args: {"lang":"en","isadmin":true}` → `invalidparameter`.
4. `args: {"lang":"../config"}` → `invalidparameter` (not a file read).

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Deploy `theme/iiidem2`, `lib/ajax/service.php`, and `media/player/videojs` (`get_language.php` + `plugin.php`).
