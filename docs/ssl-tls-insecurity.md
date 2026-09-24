# SSL/TLS Insecurity (Lucky13 / weak CBC ciphers)

## Finding

| Field | Report |
|-------|--------|
| Name | SSL/TLS Insecurity |
| Severity | Low |
| URL | `https://iiidemlms.eci.gov.in` (site-wide TLS) |
| Related | LUCKY13 [CVE-2013-0169](https://nvd.nist.gov/vuln/detail/CVE-2013-0169), [CWE-327](https://cwe.mitre.org/data/definitions/327.html) |

> testssl: **LUCKY13 potentially VULNERABLE** — CBC ciphers. SSL Labs: TLS 1.2 **WEAK** suites `TLS_ECDHE_RSA_WITH_AES_128_CBC_SHA256` and `TLS_ECDHE_RSA_WITH_AES_256_CBC_SHA384`.

SSLv2/3 and TLS 1.0/1.1 are already off. RC4 is not offered. The remaining issue is **TLS 1.2 CBC**.

**Fix:** On the host that terminates HTTPS, allow **TLS 1.2 + 1.3 only** and **AEAD ciphers only** (AES-GCM / ChaCha20-Poly1305 with SHA-256/384). Disable every `*_CBC_*` suite.

Moodle / PHP / `.htaccess` cannot choose TLS ciphers.

## PoC (auditor)

| Step | Tool | Result |
|------|------|--------|
| 1 | `testssl.sh https://iiidemlms.eci.gov.in` | TLS 1.2 + 1.3 offered (OK). LUCKY13 **potentially VULNERABLE** (CBC). |
| 2 | SSL Labs `iiidemlms.eci.gov.in` | TLS 1.2 WEAK: `AES_128_CBC_SHA256`, `AES_256_CBC_SHA384`. TLS 1.3 GCM suites are fine. |

OpenSSL names for those SSL Labs suites:

| IANA (SSL Labs) | OpenSSL |
|-----------------|---------|
| `TLS_ECDHE_RSA_WITH_AES_128_CBC_SHA256` | `ECDHE-RSA-AES128-SHA256` |
| `TLS_ECDHE_RSA_WITH_AES_256_CBC_SHA384` | `ECDHE-RSA-AES256-SHA384` |

## Apply on production (`iiidemlms.eci.gov.in`) and staging

Copy the snippet onto the **TLS terminator** (Tengine / nginx / Apache / load balancer), not into the Moodle docroot.

### nginx / Tengine

[`docs/snippets/nginx-tls-no-cbc.conf`](snippets/nginx-tls-no-cbc.conf) inside `listen 443 ssl`:

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

```bash
nginx -t && systemctl reload nginx
```

### Apache

[`docs/snippets/apache-tls-no-cbc.conf`](snippets/apache-tls-no-cbc.conf) inside `<VirtualHost *:443>`:

```apache
SSLProtocol -all +TLSv1.2 +TLSv1.3
SSLCipherSuite …GCM…CHACHA20…:!CBC:!RC4:!3DES
```

`SSLCipherSuite` is **not** valid in `.htaccess`.

### Load balancer / CDN

If TLS ends at AWS ALB, Azure App Gateway, or similar, pick a **modern** policy: TLS 1.2+ and GCM/ChaCha20 only (no `*_CBC_*`).

## Verify after reload

```bash
HOST=iiidemlms.eci.gov.in

# CBC (Lucky13) must fail
openssl s_client -connect "${HOST}:443" -servername "$HOST" -tls1_2 -cipher 'ECDHE-RSA-AES128-SHA256' </dev/null
# Expect: handshake failure

# TLS 1.2 AEAD SHA-256 must succeed
openssl s_client -connect "${HOST}:443" -servername "$HOST" -tls1_2 -cipher 'ECDHE-RSA-AES128-GCM-SHA256' </dev/null | grep -E 'Cipher is|Protocol'

testssl.sh --vulnerable "$HOST" | grep -i LUCKY13
# Expect: not vulnerable
```

SSL Labs: no orange **WEAK** CBC rows; TLS 1.3 GCM remains.

## Evidence

| Control | Evidence |
|--------|----------|
| No TLS 1.0/1.1 | Already OK on the scan (`not offered`) |
| No RC4 | Already OK |
| No CBC / Lucky13 | Cipher list is GCM + ChaCha20 + `!CBC` + explicit `!ECDHE-RSA-AES128-SHA256` |
| TLS 1.2 SHA-256 | `ECDHE-RSA-AES128-GCM-SHA256` (AEAD, not CBC-SHA256) |

Related: [lucky13-cbc-ciphers.md](lucky13-cbc-ciphers.md), [https-sensitive-data.md](https-sensitive-data.md).
