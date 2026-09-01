# 34. Improper Error Handling During Multiple OTP Attempts / Invalid Host header

## Finding

| Field | Report |
|-------|--------|
| Title | Improper Error Handling During Multiple OTP Attempts, Invalid Host header |
| Impact claimed | MEDIUM / CVSS 5.9 |
| URL | `https://staginglms.eci.gov.in/admin/tool/mfa/auth.php` |
| Also cited | `…/login/index.php?testsession=1`, `…/user/action_redir.php` |
| CWE | [CWE-703](https://cwe.mitre.org/data/definitions/703.html) – Improper Check or Handling of Exceptional Conditions |
| OWASP | A10 – Mishandling of Exceptional Conditions |

> Claim: Rapid/repeated OTP posts (Burp Intruder) cause **HTTP 500** instead of a clean OTP failure or rate-limit response. Title also mentions invalid Host header.

## PoC (what actually appears)

1. Intruder posts many `verificationcode` values (e.g. `800100`…`800106`) to `/admin/tool/mfa/auth.php`.
2. Responses show **HTTP 500**.
3. Browser error text is Moodle MFA:

   > Unsupported redirect detected, script execution terminated.  
   > Redirection error occurred between MFA and `https://staginglms.eci.gov.in/admin/tool/mfa/auth.php`.

4. Footer in the report may say “Injecting SQL payloads” — the visible payloads are **numeric OTP guesses**, not SQL syntax. There is **no** SQL error message in the response.

`Host` on the captured request is the legitimate `staginglms.eci.gov.in` (not an evil Host).

## Root cause (Moodle core MFA — not unhandled OTP crash)

### OTP failures already have controlled handling

On invalid code submit (`auth.php`):

- Form validation fails → `increment_lock_counter()` (factor lockout after configured attempts).
- `sleep_timer()` backs off repeated tries.
- Wrong code returns a normal form error (`error:wrongverification`), not a shell/SQL path.

Email factor compares the submitted code to a short-lived DB secret with typed params (`PARAM_ALPHANUM`) — not dynamic SQL built from the OTP string.

### Why Intruder sees HTTP 500

Moodle MFA tracks redirect loops (`mfa_redir_count` / `REDIR_LOOP_THRESHOLD = 5`). When the threshold is exceeded, core **intentionally** stops with:

```php
throw new \moodle_exception('redirecterrordetected', 'tool_mfa', …);
```

That matches the PoC page text exactly. Rapid automated posts to `auth.php` (often with session/redirect races) trip this **fail-closed loop guard**. It is not evidence that OTP input crashed the database or bypassed MFA.

Recommendation “gracefully reject with OTP validation or rate-limit” is already met for normal wrong codes; the 500 only appears after the **redirect-loop safety stop**, which is deliberate.

### Invalid Host header (separate control)

Host allowlisting is already documented: mismatched `Host` → **HTTP 400** before bootstrap continues ([host-header-injection.md](host-header-injection.md)). That is the correct response for an invalid Host — not related to OTP field fuzzing on a valid Host.

### Instance 4: `/user/action_redir.php` (not OTP)

| Report claim | Detail |
|--------------|--------|
| URL | `https://staginglms.eci.gov.in/user/action_redir.php` |
| Claim | “Page is accessible without login” |
| Observed | Moodle error: **A required parameter (formaction) was missing** → docs `missingparam` |
| Recommendations in report | OTP attempt limits / rate limiting — **do not apply to this URL** |

This script is the participants bulk-action wrapper. A bare GET with no POST fields correctly fails `required_param('formaction')`. It never ran bulk enrol logic without `formaction`, `id`, **sesskey**, and capability checks.

**Hardening applied:** early `require_login()` in `user/action_redir.php` so guests are sent to **Log in** instead of a public `missingparam` page. Real actions still need sesskey + enrol/participant capabilities.

## Verdict for auditors

| Claim | Result |
|-------|--------|
| Unhandled OTP / SQL injection → 500 | **Not supported** — message is MFA redirect-loop guard; payloads shown are numbers |
| Missing rate limit / lockout | **Present** — MFA lock counter + sleep backoff |
| Should use allow-list validation | **Present** — `PARAM_ALPHANUM` + server-side secret check |
| Invalid Host → 500 | **No** — allowlist returns **400** ([host-header-injection.md](host-header-injection.md)) |
| `action_redir.php` unauthenticated | **Hardened** — `require_login()`; was error-only, not privilege bypass |
| MFA OTP Intruder 500 | **No app change** (Moodle core loop guard) |

**Dispute or reclassify** finding #34:

- Not CWE-89 SQL injection.
- Not a missing OTP validator.
- Instance 4 is unrelated to OTP; remediaiton is login gate on `action_redir.php`.
- Residual note: redirect-loop exception may surface as HTTP 500; with `$CFG->debugdisplay = 0` the page stays a generic Moodle error (no stack/SQL). Prefer that ops setting over forking `tool_mfa`.

## Ops / config reminders

- Staging/production: `$CFG->debugdisplay = 0`, `$CFG->debug = 0` ([verbose-error-messages.md](verbose-error-messages.md), [debug-mode-staging.md](debug-mode-staging.md)).
- Confirm Site administration → Plugins → Admin tools → Multi-factor authentication **lockout** threshold is set (default is fine; document the value for auditors).
- Host allowlist + edge `server_name`: [host-header-injection.md](host-header-injection.md).
- Deploy patched `user/action_redir.php` to staging/production (core file — re-apply after Moodle upgrades).

## Retest evidence to provide CDAC

1. Single wrong OTP → form error “wrong verification”, **not** 500 / redirect loop text.
2. After lockout threshold → locked factor / cannot login path (still no SQL dump).
3. `Host: evil.com` → **400** (or nginx 444), not 302 to evil and not OTP-related.
4. Confirm no `verificationcode` SQL fragments in error body when `debugdisplay` is off.
5. Guest GET `https://…/user/action_redir.php` → **login redirect**, not public “missing formaction”.
6. Logged-in teacher bulk action from participants still works (with sesskey).

## Related

- [mfa-email-verification-cleartext.md](mfa-email-verification-cleartext.md) (#33)
- [host-header-injection.md](host-header-injection.md)
- [mfa-privileged-accounts.md](mfa-privileged-accounts.md)
- [sql-injection-parameterized-queries.md](sql-injection-parameterized-queries.md)
- [web-parameter-tampering.md](web-parameter-tampering.md)