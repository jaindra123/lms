# Missing Rate Limiting — server-side API throttling

## Finding

> Implement server-side rate limiting per user, IP address, or API key.

## Approach

Shared helper: `theme/iiidem2/classes/rate_limit.php`

- Application cache counters (survive new browser sessions)
- Default identity: logged-in `userid`, else client IP (`getremoteaddr()`)
- Optional email-scoped identity for OTP
- AJAX → HTTP **429** + JSON `{error: ratelimit}`
- Web services → `moodle_exception('ratelimited')`

## Limits applied

| Endpoint | Bucket | Limit |
|----------|--------|-------|
| `register/send_otp.php` | IP | 3 / 15 min, 10 / hour |
| `register/send_otp.php` | email | 1 / 30 s |
| `register/verify_otp.php` | IP | 20 / 10 min (+ existing 5 attempts/OTP) |
| `register/check_email.php` | IP | 30 / min |
| `register/check_phone.php` | IP | 30 / min |
| `ajax/chatbot_query.php` ask | IP | 5 / 10 min, 20 / day (+ 10 s session gap) |
| `ajax/chatbot_query.php` history | user/IP | 60 / min |
| `ajax/chatbot_admin_*` | admin user | poll 30 / min; actions 60 / hour |
| `ajax/mark_activity_viewed.php` | user | 60 / min |
| `certificate/download.php` | user | 10 / min |
| `local/iiidem_support/api.php` | user | 30 / min |
| `local/iiidem_livequiz/api.php` | user | poll 40 / min; submit 10 / min |
| Razorpay checkout | user + IP | 5 / 10 min / user; 20 / hour / IP |
| Razorpay verify / failure report | user | 20 / 10 min; 5 / 15 min |
| PNB / ICICI checkout | user | 5 / 10 min |
| Private files / user draft uploads | user | 40 / 10 min; 120 / hour |
| Login POST `/login/index.php` | IP | 20 / 5 min; 60 / hour (+ account lockout) |
| Support ticket create | user | 5 / 10 min; 20 / day |
| Support ticket list (GET) | user | 60 / min |
| Support ticket list (POST flood) | user | 10 / min; 40 / hour |
| Support ticket view | user | 60 / min |
| Support admin reply / manage | user | reply 30 / 10 min; manage page 120 / min |
| Support FAQ search API | user | 30 / min (existing) |

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| Per-IP | Guest/anonymous endpoints use `ip:` identity |
| Per-user | Authenticated endpoints use `u:{id}` |
| Per-email | OTP send uses `email:` identity |
| Server-side | Application cache; not client/session-only |
| Fail closed | Over-limit requests rejected before side effects (email, order create) |
