# Local server testing (your PC as the server)

Use your **dev PC as a temporary Moodle server** when the real server is unavailable. Other testers on the **same Wi‑Fi** open the **same URL** in their browser.

---

## How it works

```
┌─────────────────┐         Wi‑Fi          ┌─────────────────┐
│  Your dev PC    │ ◄────────────────────► │  Another PC     │
│  (DDEV server)  │   same URL both sides  │  (tester)       │
│  192.168.10.230 │                        │                 │
└─────────────────┘                        └─────────────────┘
         │
    ddev start
    port 8080
```

Your laptop runs Docker + DDEV. Other devices on Wi‑Fi connect to your PC’s IP on port **8080**.

---

## One-time setup (dev PC)

### 1. Start DDEV with network access

```powershell
cd C:\xampp\htdocs\iiidem_certification

ddev config --bind-all-interfaces=true --host-webserver-port=8080 --host-https-port=8443
ddev restart
```

### 2. Windows Firewall (**required for other PCs**)

**This is the most common reason another PC cannot connect.**

Right-click **PowerShell → Run as administrator**, then:

```powershell
cd C:\xampp\htdocs\iiidem_certification
.\scripts\allow-lan-firewall.ps1
```

Or run these commands manually:

```powershell
netsh advfirewall firewall add rule name="DDEV Moodle HTTP 8080" dir=in action=allow protocol=TCP localport=8080 profile=private,public
```

Without this rule, `http://192.168.10.230:8080` works on **your PC only** and is **blocked** for other PCs.

### 3. Find your Wi‑Fi IP

```powershell
ipconfig
```

Use **Wireless LAN adapter Wi‑Fi** → **IPv4** (example: `192.168.10.230`).

Ignore `172.x.x.x` (Docker/WSL only).

### 4. (Recommended) Same URL that never shows `ddev.site`

On **your PC** and **every tester PC**, edit as Administrator:

`C:\Windows\System32\drivers\etc\hosts`

Add one line (replace IP with yours from step 3):

```
192.168.10.230    iiidem.local
```

**Shared URL for everyone:**

```
http://iiidem.local:8080
```

If your Wi‑Fi IP changes, update this line on all PCs.

---

## Every testing session (dev PC)

```powershell
cd C:\xampp\htdocs\iiidem_certification
ddev start
```

Keep the PC **on** and **plugged in** while others test.

Check it works:

```powershell
curl.exe -sI http://192.168.10.230:8080/
```

Expected: `HTTP/1.1 200 OK`

---

## What testers do (other PC, same Wi‑Fi)

1. Connect to the **same Wi‑Fi** (not guest network).
2. Open browser.
3. Go to **one** of these (same on all PCs):

| URL | When to use |
|-----|-------------|
| `http://iiidem.local:8080` | Best — if hosts file is set on both PCs |
| `http://192.168.10.230:8080` | OK — replace with current IP from `ipconfig` |

**Important:** type **`http://`** at the start. Do not use `https://` on port 8080.

---

## URLs cheat sheet

| Who | URL |
|-----|-----|
| You (dev, on your PC) | `https://iiidem-certification.ddev.site` **or** `http://iiidem.local:8080` |
| You + testers (same Wi‑Fi) | **`http://iiidem.local:8080`** (same URL) |
| Public IP `164.100.26.245` | Not for same-Wi‑Fi testing — needs IT port forwarding |

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Timeout on other PC | Run `ddev start`; add firewall rule; same Wi‑Fi |
| Redirect to `ddev.site` | Use `http://IP:8080` or `http://iiidem.local:8080`, not bare IP without config |
| `ERR_TOO_MANY_REDIRECTS` | Ensure `config.php` LAN block is present; purge caches: `ddev exec php admin/cli/purge_caches.php` |
| IP changed | Run `ipconfig` on dev PC; update hosts file on all PCs |
| HTTPS / secure connection error | Use **`http://`** on port **8080** |

---

## Limitations

- Only works while **your PC is on** and **DDEV is running**.
- Testers must be on the **same network** (or VPN into it).
- Not a replacement for production hosting — for **temporary local testing only**.
- Wi‑Fi IP may change; use **`iiidem.local`** in hosts file for a stable shared URL.

See also: [LAN-ACCESS.md](./LAN-ACCESS.md)
