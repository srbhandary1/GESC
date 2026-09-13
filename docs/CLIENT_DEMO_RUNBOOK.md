# Client static-IP demo runbook

## Purpose

Let a remote client open the disclosed synthetic Global English Speaking
Course demo over the workstation static public IP. This is presentation
packaging only: in-memory fixtures, Azure neural TTS when the local demo
process is configured (D-0092) otherwise on-device browser speech, no learner
accounts, no ordinary application API and no production data.

Ordinary local commands remain loopback-only (`make demo`, `make demo-serve`).
For Azure voice setup on `make demo`, see [DEMO_AZURE_TTS.md](DEMO_AZURE_TTS.md).
This runbook is an explicit opt-in for remote review.

For the path from this presentation to real Azure Speech evidence, see
[PROFESSIONAL_CLIENT_SETUP.md](PROFESSIONAL_CLIENT_SETUP.md).

## Recommended client presentation (HTTPS)

Microphone capture and browser speech recognition need a secure context.
Prefer the temporary HTTPS tunnel for any session that includes Speak your
turn or local recording practice.

From the repository root:

```bash
make demo-android   # optional; builds the APK used by /downloads
make demo-client
make demo-client-tunnel
```

Share only the printed `https://*.trycloudflare.com` URL with the client.
Confirm status or stop with:

```bash
make demo-client-status
make demo-client-tunnel-status
make demo-client-tunnel-stop
make demo-client-stop
```

Optional Android APK (same synthetic bundle) while the client serve is up:

```text
https://<tunnel-host>/downloads/global-english-demo.apk
```

Local APK path after `make demo-android`:

```text
apps/web/android/app/build/outputs/apk/debug/app-debug.apk
```

## Static-IP start (HTTP)

Use this when router port-forward is ready. The Caddy demo at
`http://117.254.108.143:8080` can show the Mira GESC-green chrome after
`make demo-build` and a Caddy reload. Microphone capture still needs a
secure context: use the HTTPS tunnel or the bundled Capacitor APK
(`https://localhost` + runtime `RECORD_AUDIO`). Do not point the Android
WebView at the HTTP static IP.

```bash
make demo-client
```

Defaults:

| Setting | Value |
|---|---|
| Public host | `117.254.108.143` |
| Bind host | `0.0.0.0` |
| Public port | `8080` (port `8000` is already used by another local demo) |
| Bundle | `apps/web/dist-demo` (built automatically if missing) |
| APK download | `/downloads/global-english-demo.apk` when the debug APK exists |

Useful commands:

```bash
make demo-client-check
make demo-client-status
make demo-client-stop
```

Overrides:

```bash
GE_CLIENT_DEMO_PUBLIC_HOST=117.254.108.143 \
GE_CLIENT_DEMO_PORT=8080 \
make demo-client
```

### One-time router setup (DIGISOL DG-GR6011)

Confirmed from the ISP router UI:

| Item | Value |
|---|---|
| Router | DIGISOL `DG-GR6011` at `192.168.3.1` |
| Public/WAN IPv4 | `117.254.108.143` (PPPoE / VID 567) |
| Digisol LAN DHCP client seen | `R6220` → `192.168.3.2` |
| Demo host | Ubuntu server `Real-Studioz` at `192.168.1.3` behind Netgear `R6220` (`192.168.1.1`) |

That means there are **two NATs**. Digisol alone cannot reach `Real-Studioz`
at `192.168.1.3`. You must either flatten the network or forward on both
routers.

#### Option 1 — Recommended for a client meeting: skip port-forward

```bash
make demo-client
make demo-client-tunnel
```

Share the printed `https://*.trycloudflare.com` URL (mic works; no router edit).

#### Option 2 — Port-forward through both routers (static IP)

**A. On Digisol (`http://192.168.3.1`) — reserve the inner router IP**

1. Open **Network → LAN**.
2. Under **Reserve IP Address List**, click **Add**.
3. Bind MAC `10:da:43:e2:35:c1` (`R6220`) to IP `192.168.3.2`.
4. Save/apply.

**B. On Digisol — Application → Advance NAT → Virtual Server → Add**

Fill the **Virtual Server Configuration** dialog exactly like this:

| Field | Value | Notes |
|---|---|---|
| Server Name | `ge-demo-8080` | any label |
| External IP Address | leave **blank** | or `117.254.108.143` if blank is rejected |
| External Start Port | `8080` | |
| External End Port | `8080` | same as start for one port |
| Protocol | `TCP` | |
| Server IP Address | `192.168.3.2` | Digisol LAN IP of `R6220` — **not** `192.168.1.3` |
| Source Port | `8080` | host/LAN port on Digisol firmware |

Click **Add**, then confirm the row appears in the Virtual Server table.

Common mistake: putting `Real-Studioz` (`192.168.1.3`) in Digisol **Server IP
Address**. Digisol cannot route to `192.168.1.x` directly; it only sees
`192.168.3.2`.

**C. On the inner Netgear `R6220` (`http://192.168.1.1`)**

Do **not** stay on **Setup → LAN Setup** for the forward itself. Use:

1. **ADVANCED → Setup → LAN Setup → Address Reservation → Add**
   - Reserve `Real-Studioz` as `192.168.1.3` (current DHCP lease), Apply.
2. **ADVANCED → Advanced Setup → Port Forwarding / Port Triggering**
   - Choose **Port Forwarding** (not Port Triggering).
   - **Add Custom Service** (or Add).
   - Fill approximately:

| Field | Value |
|---|---|
| Service Name | `ge-demo-8080` |
| Service Type / Protocol | TCP |
| External Starting Port | `8080` |
| External Ending Port | `8080` |
| Internal IP address | `192.168.1.3` (`Real-Studioz`) |
| Internal Starting Port | `8080` (if shown) |

3. Apply / Save. Confirm the rule is listed as enabled.

**D. On `Real-Studioz` (this Ubuntu server)**

```bash
make demo-android
make demo-client
make demo-client-check
```

Client URLs after both forwards work:

```text
http://117.254.108.143:8080
http://117.254.108.143:8080/downloads/global-english-demo.apk
```

Re-check the server LAN IP if networking changes:

```bash
make demo-client-check
```

#### Option 3 — Single NAT (simpler long-term)

Connect `Real-Studioz` by cable/Wi‑Fi **directly to Digisol** so it gets a
`192.168.3.x` address, reserve that IP on Digisol **Network → LAN → Reserve IP
Address List**, then forward Digisol TCP `8080` straight to that server IP. No
inner `R6220` forward is needed.

## Disclosure script (say this to the client)

Use this wording so the review stays accurate:

> This is a professional presentation of the accepted product slices using
> synthetic in-memory fixtures. Speech you hear and speak uses your device’s
> built-in browser voice features when available. Nothing is uploaded to a
> cloud speech or AI provider. There are no learner accounts, no production
> curriculum approval, and no live Azure or LLM path in this build. Content
> resets when you reload the page.

Do not claim: production hosting, approved Kannada/curriculum, cloud STT/TTS,
real AI Friend, OTP login, or Play-store Android readiness.

## Suggested walkthrough (about 8 minutes)

1. Open the shared HTTPS URL in Chrome or Edge; select **English (India)** if
   the client prefers English UI for the review.
2. **Home — Today’s lesson:** open the recommended mission **Introduce
   yourself**.
3. **Mission:** play **model audio** (device TTS speaks the model line), read
   the Kannada guidance, continue to speaking practice.
4. **Speaking practice:** either allow the mic briefly or use **Continue
   without recording**, then submit the correct check option.
5. **Result:** confirm deterministic check/mastery evidence and that spoken
   assessment remains unassessed in this demo.
6. **Progress:** show the two synthetic objectives.
7. **Practice:** start speaking practice, type or **Speak your turn**, send,
   hear/auto-play the Friend reply, optionally send a second turn that shows a
   gentle pattern tip.

## Static-IP troubleshooting (DIGISOL + Netgear R6220)

Verified working local rules (still may fail from the internet):

| Router | Rule |
|---|---|
| Digisol Advance NAT Virtual Server | TCP `8080` → `192.168.3.2` (`R6220`), Source Port `8080` |
| Netgear R6220 Port Forwarding | TCP `8080` → `192.168.1.3` (`Real-Studioz`) |

If `make demo-client` serves locally/`192.168.1.3:8080` but
`http://117.254.108.143:8080` times out from mobile data, the ISP or Digisol
WAN firewall is almost certainly dropping inbound TCP `8080`. Double-NAT
hairpin tests from inside the LAN are also unreliable.

When that happens, use `make demo-client-tunnel` for client review instead of
fighting the carrier. Optional Digisol checks if you must keep static IP:

1. **Security** — allow WAN→LAN TCP `8080` / disable overly strict firewall for
   that port.
2. Confirm Digisol **WAN Info** still shows `117.254.108.143` on the INTERNET
   PPPoE interface (not VOICE).
3. Confirm Digisol **User Info** still lists `R6220` as `192.168.3.2`.
4. Try a different external port (for example Digisol `18080` → Source `8080`,
   and R6220 external `18080` → internal `8080`), then share
   `http://117.254.108.143:18080`.

## Application error on plain HTTP

Chromium treats `http://127.0.0.1` as a secure context, but
`http://117.254.108.143` (and other public/LAN HTTP hosts) as insecure.
Older demo builds crashed there because `crypto.randomUUID()` was missing.
Current builds use a secure-context-safe client-id helper. Prefer the HTTPS
tunnel for microphone/speech; plain HTTP still works for typed review after a
fresh `make demo-build` / `make demo-client` restart.

## Limits the client should know

- Synthetic demo only; content resets on reload.
- Draft UI locales show the existing preview notice.
- Plain HTTP is not a secure context, so browser microphone capture may be
  blocked on the static-IP URL; the HTTPS tunnel usually allows mic prompts.
  The mission still supports the microphone-free path either way.
- Browser speech quality and recognition availability vary by device/browser;
  typing always remains available.
- This is not production hosting, staging, an authorized deployment
  checkpoint, or PR-009 Azure acceptance evidence.
