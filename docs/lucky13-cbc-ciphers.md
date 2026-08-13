# 30. Potential Exposure to LUCKY13 Attack Due to CBC Cipher Suites

## Finding

| Field | Report |
|-------|--------|
| Title | 30. Potential Exposure to LUCKY13 Attack Due to CBC Cipher Suites |
| Impact | LOW (CVSS 3.7) |
| URL | `https://staginglms.eci.gov.in/login/index.php` (TLS is site-wide; host may cite `stagingiidem.eci.gov.in`) |
| CWE | [CWE-327](https://cwe.mitre.org/data/definitions/327.html) – Use of a Broken or Risky Cryptographic Algorithm |
| OWASP | A02:2021 – Cryptographic Failures |
| CVSS vector | AV:N/AC:H/PR:N/UI:N/S:U/C:L/I:N/A:N |
| Related | [CVE-2013-0169](https://nvd.nist.gov/vuln/detail/CVE-2013-0169) (LUCKY13) |

> The server supports TLS Cipher Block Chaining (CBC) cipher suites, which are associated with the LUCKY13 timing attack.

**Recommendation (ops):** Disable CBC cipher suites where possible. Prefer AEAD (AES-GCM, ChaCha20-Poly1305). TLS 1.3 has no CBC suites.

## PoC

| Instance | URL | Evidence |
|----------|-----|----------|
| 1 | `/login/index.php` (site-wide TLS) | `testssl` / similar: **LUCKY13 (CVE-2013-0169)** → *potentially VULNERABLE, uses cipher block chaining (CBC) ciphers…* |

Other checks in the same scan (Heartbleed, POODLE, ROBOT, SWEET32, FREAK, DROWN, LOGJAM, BEAST, RC4, …) were **not vulnerable** — only CBC / LUCKY13 was flagged.

**Recommendation (report):** Disable CBC cipher suites where possible; prefer AEAD (AES-128-GCM, AES-256-GCM, ChaCha20-Poly1305).  
**Reference:** [CWE-327](https://cwe.mitre.org/data/definitions/327.html).

## Scope

| Layer | Can fix CBC? |
|-------|----------------|
| Moodle / PHP / theme | **No** — application never chooses TLS ciphers |
| DDEV web nginx (`.ddev/nginx/*`) | **No** — TLS is terminated by the DDEV router, not this nginx |
| Staging / production nginx, Apache, or load balancer that terminates HTTPS | **Yes** — configure here |

Wrong-host PoCs do not apply until the **same edge** that serves staging/production LMS HTTPS is hardened.

## Fix (ops — required)

### nginx

Copy or `include` the repo snippet on the host that terminates TLS:

- Snippet: [`docs/snippets/nginx-tls-no-cbc.conf`](snippets/nginx-tls-no-cbc.conf)

```nginx
server {
    listen 443 ssl http2;
    server_name staginglms.eci.gov.in;  # adjust

    # certificates …
    include /path/to/nginx-tls-no-cbc.conf;

    # … proxy / root …
}
```

Effective settings:

```nginx
ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305';
ssl_prefer_server_ciphers off;
```

Reload:

```bash
nginx -t && systemctl reload nginx
```

### Apache

Snippet: [`docs/snippets/apache-tls-no-cbc.conf`](snippets/apache-tls-no-cbc.conf)

```apache
SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
SSLCipherSuite ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305
SSLHonorCipherOrder on
```

### Load balancer / CDN

If TLS ends at AWS ALB, Azure App Gateway, Cloudflare, Tengine, etc., set a **custom/modern security policy** that allows only TLS 1.2+ and **GCM / ChaCha20** (no `*_CBC_*` / `AES128-SHA` / `AES256-SHA`). Moodle `$CFG->sslproxy = true` stays as today.

## Verify

```bash
HOST=staginglms.eci.gov.in

# CBC suites should fail to negotiate
openssl s_client -connect "${HOST}:443" -tls1_2 -cipher 'AES128-SHA' </dev/null 2>&1 | head -20
# Expect handshake failure / no cipher

# AEAD should succeed
openssl s_client -connect "${HOST}:443" -tls1_2 -cipher 'ECDHE-RSA-AES128-GCM-SHA256' </dev/null 2>&1 | grep -E 'Cipher is|Protocol'

# Enumerate (optional)
nmap --script ssl-enum-ciphers -p 443 "$HOST"
# Expect: no CBC / CAMELLIA-CBC / AES-*-SHA (non-GCM) in accepted lists

# Retest auditor PoC
testssl.sh --vulnerable "$HOST" | grep -i LUCKY13
# Expect: not vulnerable (no CBC) — not "potentially VULNERABLE"
```

Qualys SSL Labs: aim for **A/A+** with no CBC-only weak suites advertised.

## Evidence for auditors

| Control | Evidence |
|--------|----------|
| TLS 1.0/1.1 off | `ssl_protocols TLSv1.2 TLSv1.3` / `SSLProtocol … -TLSv1 -TLSv1.1` |
| No CBC on TLS 1.2 | Cipher list is GCM + ChaCha20-Poly1305 only |
| TLS 1.3 | Enabled (AEAD-only by design) |
| App layer | N/A — cipher selection is edge-only; see this doc + snippets in repo |

Related: [https-sensitive-data.md](https-sensitive-data.md), [cache-control-sensitive-pages.md](cache-control-sensitive-pages.md) (#29 recommendation on prior page of report).
