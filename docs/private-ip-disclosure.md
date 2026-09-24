# Private IP address disclosed

## Finding

| Field | Report |
|-------|--------|
| Title | Private IP address disclosed |
| URL | `/report/log/index.php?chooselog=1&showusers=1&showcourses=1&id=4&…&logreader=logstore_standard` |
| Evidence | HTML showed `10.206.97.211` and `/iplookup/index.php?ip=10.206.97.211` |

RFC1918 addresses (here a `10.x` proxy/client IP) must not appear in LMS HTML.

## Fix (`theme_iiidem2` ≥ `2024101088`)

`theme_iiidem2\private_ip` treats RFC1918 / loopback / link-local / CGNAT as private **without** depending on autoload order.

| Surface | Behaviour |
|---------|-----------|
| Logs / live logs IP column | **Private address** — no iplookup link |
| User profile last IP, user sessions, MFA IP columns | Same |
| HTML/JSON output buffer | Strips remaining `iplookup?ip=10…` links and bare private IPv4 |
| `/iplookup/index.php?ip=10.…` | Existing `iplookupprivate` error (no map) |

Stored log rows are unchanged (forensics via DB/CLI). Public IPs still link to iplookup.

## Verify

```bash
# HTML must not contain 10.206.97.211 or iplookup?ip=10.
curl -s 'https://staginglms.eci.gov.in/report/log/index.php?chooselog=1&id=4&logreader=logstore_standard' \
  -b 'MoodleSession=YOUR_SESSION' | grep -E '10\.206\.|iplookup.*ip=10'
# Expect: no output
```

Direct `/iplookup/index.php?ip=10.206.97.211` → private-IP error, not a map.

## Deploy

```bash
php admin/cli/upgrade.php --non-interactive
php admin/cli/purge_caches.php
```

Ship `report/log/classes/table_log.php`, `report/loglive/classes/table_log.php`, and theme **iiidem2 2024101088**.
