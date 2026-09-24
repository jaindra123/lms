# 30. Potential Exposure to LUCKY13 Attack Due to CBC Cipher Suites

## Finding

| Field | Report |
|-------|--------|
| Title | 30. Potential Exposure to LUCKY13 Attack Due to CBC Cipher Suites |
| Impact | LOW (CVSS 3.7) |
| URL | `https://iiidemlms.eci.gov.in` / `https://staginglms.eci.gov.in` (TLS is site-wide) |
| CWE | [CWE-327](https://cwe.mitre.org/data/definitions/327.html) – Use of a Broken or Risky Cryptographic Algorithm |
| OWASP | A02:2021 – Cryptographic Failures |
| CVSS vector | AV:N/AC:H/PR:N/UI:N/S:U/C:L/I:N/A:N |
| Related | [CVE-2013-0169](https://nvd.nist.gov/vuln/detail/CVE-2013-0169) (LUCKY13) |

> The server supports TLS Cipher Block Chaining (CBC) cipher suites, which are associated with the LUCKY13 timing attack.

**Fix:** Disable CBC cipher suites. Offer **AEAD only** (AES-GCM, ChaCha20-Poly1305). TLS 1.3 has no CBC suites.

Moodle / PHP cannot choose TLS ciphers. This must be applied on the **HTTPS terminator** (staging Tengine / nginx / Apache / load balancer).

## PoC (auditor)

| Instance | URL | Evidence |
|----------|-----|----------|
| 1 | `/login/index.php` (site-wide TLS) | `testssl`: **LUCKY13 (CVE-2013-0169)** → *potentially VULNERABLE, uses cipher block chaining (CBC) ciphers…* |

Same scan: Heartbleed, POODLE, ROBOT, SWEET32, FREAK, DROWN, LOGJAM, BEAST, RC4 were **not vulnerable**. Only CBC / LUCKY13 was flagged.

## Scope

| Layer | Can fix CBC? |
|-------|----------------|
| Moodle / PHP / theme / `.htaccess` | **No** |
| DDEV web nginx (`.ddev/nginx/*`) | **No** — HTTP behind the router |
| DDEV Traefik (`.ddev/traefik/config/tls-no-cbc.yaml`) | **Yes** — local HTTPS |
| Staging / production Tengine, nginx, Apache, or load balancer | **Yes** — required for the auditor retest |

## Fix applied in this repo

### 1. Staging / production Tengine or nginx (required for CDAC retest)

CDAC **SSL/TLS Insecurity** on production is the same issue: SSL Labs flags TLS 1.2 CBC as WEAK (`AES_128_CBC_SHA256`, `AES_256_CBC_SHA384`). See [ssl-tls-insecurity.md](ssl-tls-insecurity.md).

Copy [`docs/snippets/nginx-tls-no-cbc.conf`](snippets/nginx-tls-no-cbc.conf) onto the host that terminates `https://iiidemlms.eci.gov.in` (and staging) and `include` it inside the `listen 443 ssl` server block:

```nginx
server {
    listen 443 ssl http2;
    server_name iiidemlms.eci.gov.in;

    ssl_certificate     /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;

    include /etc/nginx/snippets/nginx-tls-no-cbc.conf;

    # … proxy / root …
}
```

Then:

```bash
nginx -t && systemctl reload nginx
# Tengine: nginx -t && systemctl reload tengine   # or whatever unit they use
```

Effective policy: TLS 1.2 + 1.3 only; `ssl_ciphers` is GCM + ChaCha20 with **`!CBC`**.

### 2. Apache (if that vhost terminates TLS)

[`docs/snippets/apache-tls-no-cbc.conf`](snippets/apache-tls-no-cbc.conf) inside `<VirtualHost *:443>`:

```apache
SSLProtocol -all +TLSv1.2 +TLSv1.3
SSLCipherSuite …:!CBC:!aNULL:!eNULL:!EXPORT:!DES:!RC4:!MD5:!PSK
```

`SSLCipherSuite` is **not** valid in `.htaccess`.

### 3. DDEV (local HTTPS)

- [`.ddev/nginx/tls-no-cbc.conf`](../.ddev/nginx/tls-no-cbc.conf) — AEAD-only `ssl_ciphers` on the web container `listen 443 ssl` (host port **8443**).
- Do **not** put a top-level `tls:` YAML in `.ddev/traefik/config/` — Traefik merge **replaces** mkcert certificates and Chrome shows `ERR_SSL_UNRECOGNIZED_NAME_ALERT`.

Apply with `ddev restart` (or `ddev exec nginx -s reload` after nginx-only edits).

### 4. Load balancer / CDN

If TLS ends at AWS ALB, Azure App Gateway, Cloudflare, etc., use a **modern** policy: TLS 1.2+ and GCM / ChaCha20 only (no `*_CBC_*`, no `AES128-SHA` / `AES256-SHA`). Moodle `$CFG->sslproxy = true` stays as today.

## Verify

```bash
HOST=iiidemlms.eci.gov.in

# CBC must not negotiate
openssl s_client -connect "${HOST}:443" -tls1_2 -cipher 'AES128-SHA' </dev/null 2>&1 | head -20
# Expect: handshake failure

# AEAD must succeed
openssl s_client -connect "${HOST}:443" -tls1_2 -cipher 'ECDHE-RSA-AES128-GCM-SHA256' </dev/null 2>&1 | grep -E 'Cipher is|Protocol'

# Retest auditor finding
testssl.sh --vulnerable "$HOST" | grep -i LUCKY13
# Expect: not vulnerable — not "potentially VULNERABLE"
```

Qualys SSL Labs: **A/A+**, no CBC suites advertised.

## Evidence for auditors

| Control | Evidence |
|--------|----------|
| TLS 1.0/1.1 off | `ssl_protocols TLSv1.2 TLSv1.3` / `SSLProtocol -all +TLSv1.2 +TLSv1.3` |
| No CBC on TLS 1.2 | Cipher list is GCM + ChaCha20-Poly1305 + `!CBC` |
| TLS 1.3 | Enabled (AEAD-only by design) |
| App layer | N/A — cipher selection is edge-only |

Related: [https-sensitive-data.md](https-sensitive-data.md).
