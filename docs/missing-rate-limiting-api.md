# Missing Rate Limiting on API Endpoints (CDAC #8)

## Finding

| Field | Report |
|-------|--------|
| Title | Missing Rate Limiting on API Endpoints |
| Impact | HIGH / CVSS 7.5 |
| CWE | CWE-770 |
| URLs | `/local/iidcm_support/tickets.php`, `/index.php/site/support_inquiry_list`, `/login/index.php`, `/user/files.php` |

> Implement server-side rate limiting per user, IP address, or API key.

## Fixes

Shared helper: `theme/iiidem2/classes/rate_limit.php` (see also `docs/rate-limiting.md`).

| Endpoint | Limit |
|----------|--------|
| Login POST | 20 / 5 min, 60 / hour **per IP** (+ username lockout after 5 fails) |
| Support ticket create | **5 / 10 min, 20 / day per user**; **10 / 10 min, 30 / hour per IP** |
| Support `ticket_new.php` POST | 10 / min per user (early gate) |
| Support `tickets.php` GET | 60 / min |
| Support `tickets.php` POST (Intruder flood) | 10 / min, 40 / hour |
| Support ticket view | 60 / min |
| Support admin reply | 30 / 10 min |
| Private files / draft upload | 40 / 10 min, 120 / hour per user |

### PoC note — support inquiry list flood

Auditor screenshot of many “test” / “General enquiry” rows seconds apart maps to **`/local/iiidem_support/tickets.php`** (columns Subject / Category / Status / Submitted). Report URL `/index.php/site/support_inquiry_list` is not a Moodle route in this repo (likely rewrite or mislabeled path on staging); the list UI is `tickets.php`, creates go through `ticket_new.php` → `manager::create_ticket()`.

After fix: rapid creates hit `ratelimited` instead of filling the table.

### PoC note (Instance 1 — POST to list)

Report posted repeatedly to `/local/iiitdcm_support/tickets.php` with `ticket_id` + `message` (all HTTP 200). Path name is a typo for `iiidem_support`. That POST body is **not** a create/reply handler on our list page (create is `ticket_new.php`; reply is `manage.php`), but POST floods are now throttled anyway. Over-limit → Moodle `ratelimited` exception (not endless 200s).

Also hide `X-Powered-By` / server version — see `docs/version-disclosure.md`.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Theme ≥ `2024100970`, `local_iiidem_support` ≥ `2026061822`.