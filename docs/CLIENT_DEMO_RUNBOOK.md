# Client static-IP demo runbook

## Purpose

Let a remote client open the disclosed synthetic Global English demo over the
workstation static public IP. This is presentation packaging only: in-memory
fixtures, no learner accounts, no API, no providers and no production data.

Ordinary local commands remain loopback-only (`make demo`, `make demo-serve`).
This runbook is an explicit opt-in for remote review.

## Start

From the repository root:

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

## One-time router setup

On the home router, forward:

```text
TCP 8080 on 117.254.108.143  ->  <laptop LAN IPv4>:8080
```

The laptop LAN address is currently detected as `192.168.1.3` when connected to
the home Wi-Fi (`192.168.1.1`). Re-check with `make demo-client-check` if the
machine moves networks.

Share only the printed client URL with the reviewer:

```text
http://117.254.108.143:8080
```

Optional Android demo APK (same synthetic bundle):

```text
http://117.254.108.143:8080/downloads/global-english-demo.apk
```

Build the APK first if needed:

```bash
make demo-android
```

## Immediate access without router changes

If TCP `8080` is not yet forwarded, start a temporary Cloudflare quick tunnel
after `make demo-client` is running:

```bash
make demo-client-tunnel
```

Share the printed `https://*.trycloudflare.com` URL. It is temporary,
account-less and suitable for a short client review. Prefer the static-IP URL
once the router forward above is in place.

```bash
make demo-client-tunnel-status
make demo-client-tunnel-stop
```

## Limits the client should know

- Synthetic demo only; content resets on reload.
- Draft UI locales show the existing preview notice.
- Plain HTTP is not a secure context, so browser microphone capture may be
  blocked on the static-IP URL; the HTTPS tunnel usually allows mic prompts.
  The mission still supports the microphone-free path either way.
- This is not production hosting, staging or an authorized deployment
  checkpoint.
