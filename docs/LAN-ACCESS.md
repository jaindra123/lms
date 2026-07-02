# LAN access (DDEV + Moodle)

Expose the local Moodle site to other PCs on the same Wi‑Fi/network during development.

| Who | URL |
|-----|-----|
| **Dev PC (you)** | `https://iiidem-certification.ddev.site` |
| **Same Wi‑Fi (private IP)** | `http://10.206.97.68:8080` *(check `ipconfig` — IP can change)* |
| **Same URL via hostname** | `http://iiidem.local:8080` *(hosts file on each PC — see below)* |
| **Public IP** | `http://164.100.26.245:8080` — **not for same Wi‑Fi**; needs IT port-forward |

**Use one shared URL on the same Wi‑Fi:** `http://<YOUR_WIFI_IP>:8080` on **both** your PC and the other PC.

Run `ipconfig` on the dev PC and use the **Wi‑Fi** IPv4 address (currently `10.206.97.68`, was `192.168.10.230`).

**Do not use `164.100.26.245` on the same Wi‑Fi** — it will time out without router setup. Use your Wi‑Fi IP instead.

---

## One-time setup

### 1. DDEV — bind to the network

From the project root:

```powershell
cd C:\xampp\htdocs\iiidem_certification

ddev config --bind-all-interfaces=true --host-webserver-port=8080 --host-https-port=8443
ddev restart
```

Verify with `ddev describe`:

- `bind-all-interfaces ENABLED`
- HTTP port `8080` is listed

These settings live in `.ddev/config.yaml`:

```yaml
bind_all_interfaces: true
host_webserver_port: "8080"
host_https_port: "8443"
```

### 2. Moodle — `config.php`

The project already includes a LAN block in `config.php` that:

- Sets `$CFG->wwwroot` for private IPs or addresses in `$devpublicips` (includes `164.100.26.245`)
- Aligns `$_SERVER['SERVER_PORT']` so Moodle does not redirect-loop (DDEV maps host `:8080` → container `:80`)
- Disables secure cookies for HTTP LAN access

Do not remove this block if you need LAN access.

### 3. Windows Firewall

Run **PowerShell as Administrator** on the dev PC:

```powershell
netsh advfirewall firewall add rule name="DDEV Moodle HTTP 8080" dir=in action=allow protocol=TCP localport=8080
netsh advfirewall firewall add rule name="DDEV Moodle HTTPS 8443" dir=in action=allow protocol=TCP localport=8443
```

### 4. Public IP — router port forwarding (required for `164.100.26.245`)

Your dev PC only has a **private** Wi‑Fi IP (`192.168.10.230`). The address `164.100.26.245` is **not on your PC** — it is the network’s **public** IP (router/internet side).

For `http://164.100.26.245:8080` to work, a network admin must configure **port forwarding** on the gateway (`192.168.10.2`):

| Setting | Value |
|---------|--------|
| External IP | `164.100.26.245` |
| External port | `8080` (TCP) |
| Internal IP | `192.168.10.230` (your dev PC) |
| Internal port | `8080` |

Also confirm:

- Institutional firewall allows **inbound TCP 8080** to `164.100.26.245`
- Your dev PC keeps a **fixed/reserved** DHCP address (`192.168.10.230`) so forwarding does not break after reboot

**Same Wi‑Fi + public IP:** Some routers block accessing your own public IP from inside the network (no NAT loopback). If `164.100.26.245:8080` fails on your PC but works elsewhere, use `192.168.10.230:8080` on Wi‑Fi instead.

After port forwarding is active, test:

```powershell
curl.exe -sI http://164.100.26.245:8080/
```

Expected: `HTTP/1.1 200 OK`

---

## Daily workflow

```powershell
ddev start
```

After config changes:

```powershell
ddev exec php admin/cli/purge_caches.php
```

Quick check from the dev PC:

```powershell
curl.exe -sI http://<YOUR_WIFI_IP>:8080/
```

Expected: `HTTP/1.1 200 OK`

---

## Find your Wi‑Fi IP

```powershell
ipconfig
```

Use the **Wi‑Fi** adapter IPv4 address (e.g. `192.168.10.230`).

Ignore `172.x.x.x` addresses — those are Docker/WSL virtual adapters.

Share with testers:

```
http://<YOUR_WIFI_IP>:8080
```

---

## Same URL on your PC and another PC (same Wi‑Fi)

### Option A — Wi‑Fi IP (simplest)

1. On **your dev PC**, run `ipconfig` and note the Wi‑Fi IPv4 (e.g. `10.206.97.68`).
2. On **both PCs**, open the **same** URL:

```
http://10.206.97.68:8080
```

Replace with your current IP if it changes after reconnecting to Wi‑Fi.

### Option B — Stable hostname (`iiidem.local`)

If the Wi‑Fi IP changes often, use a fixed name on **each PC**:

1. Edit hosts file as Administrator: `C:\Windows\System32\drivers\etc\hosts`
2. Add one line (use your current Wi‑Fi IP from `ipconfig`):

```
10.206.97.68    iiidem.local
```

3. On **both PCs**, open:

```
http://iiidem.local:8080
```

Update the hosts file if your Wi‑Fi IP changes.

---

1. Connect to the **same Wi‑Fi** (not guest Wi‑Fi).
2. Open a browser and go to `http://<DEV_PC_IP>:8080`.
3. Use **HTTP** and include **`:8080`**.
4. Do not use `iiidem-certification.ddev.site` on other machines unless you add a hosts-file entry.

Optional connectivity test:

```cmd
ping <DEV_PC_IP>
```

---

## Troubleshooting

| Problem | Likely cause | Fix |
|---------|--------------|-----|
| `ERR_TOO_MANY_REDIRECTS` | Port mismatch (8080 vs 80 inside container) | Ensure the LAN block in `config.php` is present |
| Timeout / cannot connect | Windows Firewall | Add firewall rules (step 3) |
| Works locally, not on other PCs | Wrong IP or guest Wi‑Fi | Use Wi‑Fi IP from `ipconfig`; same network |
| Redirect to `ddev.site` | Request not matched as allowed IP | Open via `http://IP:8080`; check `$devpublicips` in `config.php` |
| `164.100.26.245:8080` timeout | No port forwarding / firewall | Ask network admin (step 4) |
| “Does not support a secure connection” / SSL error | Browser used **HTTPS** on port **8080** | Use **`http://`** explicitly (see below) |

---

## HTTPS vs HTTP (important)

DDEV on your setup uses:

| Port | Protocol | URL example |
|------|----------|-------------|
| **8080** | **HTTP only** | `http://164.100.26.245:8080` |
| **8443** | HTTPS (self-signed cert) | `https://164.100.26.245:8443` |

**Do not** open `https://164.100.26.245:8080` — port 8080 does not speak SSL, so Chrome/Edge show *“doesn’t support a secure connection”*.

### Correct way to open the site

Type the full URL in the address bar:

```
http://164.100.26.245:8080
```

Include **`http://`** at the start. If you only type `164.100.26.245:8080`, many browsers default to **HTTPS** and fail.

If Chrome still upgrades to HTTPS:

1. Turn off **Settings → Privacy → Security → Always use secure connections**
2. Or use **Incognito** and paste `http://164.100.26.245:8080` again

For LAN on the same Wi‑Fi, prefer:

```
http://192.168.10.230:8080
```

## Notes

- The dev PC must stay on and `ddev start` must be running.
- Wi‑Fi IPs can change after reboot; re-run `ipconfig` if the URL stops working.
- Prefer HTTP (`8080`) on LAN; HTTPS (`8443`) will show certificate warnings.
- This setup is for **local development only**, not production.
