# 23. Unused QR Login Endpoint Exposed

## Finding

| Field | Report |
|-------|--------|
| Title | 23. Unused QR Login Endpoint Exposed |
| Impact | LOW / CVSS 3.5 |
| URL | `https://staginglms.eci.gov.in/login/index.php` (PoC UI often on profile; host sometimes `cci.gov.in`) |
| CWE | [CWE-16](https://cwe.mitre.org/data/definitions/16.html) — Configuration |
| OWASP | A05:2021 – Security Misconfiguration |
| CVSS vector | `AV:N/AC:L/PR:N/UI:R/S:U/C:L/I:N/A:N` |

> The application exposes a QR login endpoint (`qrlogin`) even though no mobile app is in use. Disable unused authentication features to reduce attack surface.

### PoC note

1. Logged-in profile showed **“QR code for mobile app access”** with “GET QR CODE” / scan-to-login copy and a PNG QR.  
2. Decoded payload (Instance Step 3):

```text
moodlemobile://https://staginglms.eci.gov.in?qrlogin=<token>&userid=49
```

That is a **Moodle Mobile auto-login key** (`tool_mobile/qrlogin` private key) plus target userid. UI is rendered only when `tool_mobile/qrcodetype` is **URL** or **Login**. With **Disabled (0)** the profile block is not added, no new keys are minted, and outstanding keys are deleted.

## What was disabled

| Control | Implementation |
|---------|----------------|
| QR mode | `tool_mobile/qrcodetype` = **Disabled** (`0`) |
| Forced config | `$CFG->forced_plugin_settings['tool_mobile']['qrcodetype'] = 0` in `config.php` (admins cannot re-enable in UI) |
| Runtime re-force | Theme `after_config` keeps force + repairs DB if non-zero |
| Outstanding keys | Deleted from `mdl_user_private_key` where `script = tool_mobile/qrlogin` |
| Web service | `tool_mobile_get_tokens_for_qr_login` removed from all external service mappings |
| Query strip | `qrlogin` removed from `$_GET`/`$_REQUEST`/`$_POST` early |
| Theme helper | `theme_iiidem2\qr_login_security` |
| Upgrade / CLI | Theme ≥ `2024100994` + `php theme/iiidem2/cli/disable_qr_login.php` |
| Mobile download promo | `setuplink` cleared — no “Get the mobile app” → `download.moodle.org` ([referrer-policy.md](referrer-policy.md) #28) |
| Smart App Banners | Forced off |

When called, the WS throws `qrcodedisabled` even if somehow re-mapped.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
# optional verify / re-apply:
php theme/iiidem2/cli/disable_qr_login.php
```

## Verify

Site administration → Mobile app → Mobile authentication → **QR code access** shows **QR code disabled** (locked by forced settings).

Open **own profile** while logged in — must **not** show “QR code for mobile app access”.

```bash
php admin/cli/cfg.php --component=tool_mobile --name=qrcodetype
# Expect: 0

# PoC-style deep link must not authenticate
curl -sI 'https://staginglms.eci.gov.in/?qrlogin=6eae5335de9ba67c42de2b87afb83864&userid=49' | head -n 5
# Expect: normal page / login — not an authenticated session for userid 49
```

## Evidence for auditors

| Control | Implementation |
|--------|----------------|
| QR disabled | Forced `qrcodetype = 0` |
| No profile QR UI | `!empty(qrcodetype)` false → node not rendered |
| Keys purged | `user_private_key` script `tool_mobile/qrlogin` |
| WS unmapped | `tool_mobile_get_tokens_for_qr_login` detached |

Related: [concurrent-sessions.md](concurrent-sessions.md), [session-fixation.md](session-fixation.md).
