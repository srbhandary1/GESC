# VPS soft-launch deployment evidence — 18 September 2026

**Checkpoint:** `VPS-SOFT-LAUNCH-01`  
**Authority:** APP-0095 / D-0303  
**Deployed source revision:** `56f2caa74eed978ba4b20ef20dd14e93e21f6b2c`  
**Posture:** Invite-only soft launch; production, Play release, live payment and
live AI/Speech provider dispatch are not authorized.

## Host and release

- Ubuntu 24.04 LTS, four virtual CPUs, approximately 7.8 GiB RAM and 157 GB
  storage.
- Application service account: `globalenglish`; root is not used to run the
  application.
- Immutable release directory:
  `/srv/global-spoken-english/releases/56f2caa74eed978ba4b20ef20dd14e93e21f6b2c`.
- Active symlink: `/srv/global-spoken-english/current`.
- Release archive SHA-256:
  `779604d0cdf3be898ad63295a60f345c7b0cb3db422f3f08882d02ee3a84cf27`.
- Runtime secrets were generated on the VPS, remain outside Git and are
  mode `0600`. No secret value is recorded in this evidence.

## Restore points

- Pre-deployment host snapshot:
  `/root/pre-global-english-20260918T083018Z.tgz`.
- Snapshot SHA-256:
  `8c286601c264ccd64a128b6af00f8c1602aeb7df681f3dc7858f69ce0a370994`.
- Encrypted database backup:
  `/home/globalenglish/ge-offsite-backups/soft-launch-20260918T084524Z.sql.gz.enc`.
- Encrypted backup SHA-256:
  `fadb111c2f2ac2aed54065dbfcdcf9a8d54c773701f1442391bbc5a4a5ec8ee7`.
- Restore drill evidence:
  `artifacts/ops/soft-launch-restore-drill-20260918T084525Z.json` on the
  deployed release. The isolated restore passed in four seconds, matched the
  application schema, migrations and deletion-tombstone state, and did not
  overwrite the live database.
- The encrypted backup and recovery key are currently on the same VPS. This is
  a tested restore point, not off-box custody or continuous PITR. Copying
  either off the host requires an explicitly authorized secure destination.

## Runtime and network controls

- Docker Engine 29.1.3 and Compose 2.40.3 run digest-pinned PostgreSQL,
  Redis, SeaweedFS, Mailpit and WireMock services. All five reported healthy.
- PHP 8.4.23, Composer 2.10.2, Node.js 24.18.1 and pnpm 11.18.0 match the
  repository toolchain.
- A user-level `global-english-soft-launch.service` is enabled with linger and
  supervises the API, queue worker, scheduler, Caddy and Cloudflare tunnel
  across reboots.
- UFW is active with default-deny inbound and only OpenSSH allowed. PostgreSQL,
  Redis, SeaweedFS, Mailpit, the API and the primary Caddy proxy bind to
  loopback. The legacy staff HTTP listener is firewall-blocked from the WAN.
- fail2ban's SSH jail is active.
- The current temporary HTTPS entry is
  `https://boston-balanced-reflect-down.trycloudflare.com`. It is an ephemeral
  Quick Tunnel, not the production DNS/TLS endpoint, and may change after a
  supervisor or tunnel restart.
- Root/password SSH remains enabled pending owner credential rotation or an
  explicit authorization to disable password access. Because the supplied
  password crossed a chat channel, this is a release-blocking security
  residual, not an accepted production configuration.

## Application posture

- All migrations were applied and `FoundationSeeder` completed.
- Passport encryption keys were generated on-host.
- Public Android PKCE client:
  `01a0b3ae-8e76-70d1-95f9-1ed4e7ce4dad`, redirect
  `com.globalenglish.app:/oauth/callback`; no client secret exists.
- `APP_ENV=soft-launch`, `APP_DEBUG=false`,
  `AUTHENTICATION_MODE=password_only`.
- `PAYMENT_DRIVER=disabled`, `VOICE_UPLOAD_ENABLED=false`,
  `VOICE_PROVIDER_DRIVER=disabled` and every Azure/OpenAI/Speech/media live
  request switch is false. No live payment or provider request was made.
- `make soft-launch-ready`: **17 pass, 7 warn, 0 fail**. Warnings are the
  intentionally absent live-provider credentials/authorization and the lack
  of an active publication head.

## External verification

- HTTPS `/api/v1/health/live`: `status=ok`.
- HTTPS `/api/v1/health/ready`: `status=ready`.
- Android password mode: `password_only` with the public client above.
- The first staged APK was byte-valid but still embedded the retired static API
  origin. It was not retained as the deployment artifact. A fresh
  `android-closed-beta` debug build embeds the VPS Quick Tunnel origin and the
  public PKCE client above; Gradle unit tests and `assembleDebug` passed.
- After the testing-secret sync restarted the Quick Tunnel, the debug APK was
  rebuilt again for the current origin. Its public SHA-256 is
  `56a0dc745925d78a678eaeaf2b67fa6db96a63d3ab0e202f8a5aab8b5508e47c`
  (82,981,464 bytes). `aapt2` confirms package `com.globalenglish.app` and
  target SDK 36. This is debug-signed closed-beta evidence, not a Play artifact.

## Repository verification at deployed revision

- `make verify`: passed after the network-permitted rerun; Composer audit,
  Pint, PHPStan, 316 SQLite tests / 3,087 assertions, 360 PostgreSQL tests /
  5,225 assertions, locale/OpenAPI/release/soft-launch contracts, ESLint,
  TypeScript and 466 web tests passed. Two moderate pnpm advisories remain
  below the high-severity gate; five locale catalogues retain independent-
  review warnings.
- `make e2e`: passed after the local-port-permitted rerun; API reconciliation
  4/4 (103 assertions) and Playwright 29/29.
- `make build`: passed; the existing Vite large-chunk advisory remains and
  unlicensed tutor-avatar models were excluded.
- `make android-closed-beta`: the web bundle and Capacitor sync passed; the
  first Gradle invocation could not write the sandboxed user cache, then the
  exact Gradle `testDebugUnitTest assembleDebug` step passed with its existing
  cache permission (103 actionable tasks).

## Rollback

1. Stop `global-english-soft-launch.service` as `globalenglish`.
2. Point `/srv/global-spoken-english/current` to the previous immutable release
   when one exists, or remove the current symlink for a full application stop.
3. Restore the database only through the repository restore procedure, first
   validating the encrypted backup in a temporary database. Never overwrite
   live data merely to test a rollback.
4. The pre-deployment host snapshot above is the system-configuration recovery
   point; it is not an application-data substitute.

## Open production gates

- Rotate the disclosed root password immediately or disable password/root SSH
  after confirming key-based break-glass access.
- Establish stable product DNS/TLS instead of a Quick Tunnel.
- Place encrypted backups and recovery material in approved off-box custody;
  implement PITR and alerting.
- Provision named operator/test accounts through the governed workflow; no
  human credential was invented during deployment.
- Complete independent security, privacy/DPDP, tenancy, accessibility,
  language/content and legal review.
- Complete remaining Android device-matrix evidence, production signing, Play
  closed-track authorization and payment authorization.

## Testing secret sync — 18 September 2026

APP-0096/D-0304 added the four project provider credential families required
for later authorized testing: Azure OpenAI, Azure Speech, Sarvam Indic STT and
ElevenLabs. Their configuration and approval/vendor evidence were copied to the
equivalent `globalenglish` home workspaces. Files are non-empty, owner-only and
mode `0600`; no value or secret hash is recorded here.

The sync deliberately excluded all unrelated workstation material, personal
SSH/Azure sessions, platform/database/storage/Passport/backup secrets,
diagnostic token files, old user passwords, Android release signing material
and payment credentials. A protected pre-change archive is retained at
`~/ge-secret-rollbacks/20260918T090500Z/pre-secret-sync.tar`. The temporary
transfer archive was verified and deleted from both endpoints.

All provider dispatch remained disabled. Post-restart evidence:

- `make soft-launch-ready`: **21 pass / 2 warn / 0 fail**;
- Wave 3.8: **ready**, checkpoint closed, dispatch unauthorized, no provider
  request;
- Wave 6: **20 pass / 4 fail**, intentionally fail-closed because voice upload,
  live authorization and the live adapter binding remain disabled;
- external live/ready: healthy;
- `make android-closed-beta`: passed, followed by a byte-exact public-download
  verification of the current APK hash above.

## Provider activation addendum — 18 September 2026

**Checkpoint:** `PROVIDER-ACTIVATION-01`

**Authority:** APP-0097 / D-0316

**Source revision:** `56f2caa74eed978ba4b20ef20dd14e93e21f6b2c`

Before activation, `make soft-launch-backup` created encrypted dump
`soft-launch-20260918T101853Z.sql.gz.enc` (verified ciphertext SHA-256
`08a1b5ecbb283a0cdc01f36347fe2d913d94f2599b28edf98f4cdcc3f633c863`).
The two changed env files were preserved under
`~/ge-provider-activation-rollbacks/20260918T102300Z/`; provider secrets were
not copied into this document or Git.

The invite-only runtime now has these existing paths enabled:

- Azure guidance TTS;
- Sarvam recorded speech-to-text with Azure recorded-STT failover;
- Azure OpenAI configuration and live switch.

Synthetic requests used only fixed test text and provider-generated audio:

| Provider path | Result |
|---|---|
| Azure guidance TTS | Pass — `azure-speech-guidance`, 722 ms |
| Sarvam recorded STT | Pass — `indic-http-stt`, 417 ms, transcript present |
| Azure recorded-STT failover adapter | Pass — `azure-speech-soft-launch-stt`, 355 ms, transcript present |
| Azure OpenAI conversation model | Fail-closed — HTTP 403 mapped to `provider_authorization_failed` |

Azure CLI reports that interactive MFA is required before the resource's
network/authorization settings can be inspected or changed. The Azure OpenAI
switch remains on under the owner's instruction, but live conversation is not
reported as working until a synthetic request succeeds.

ElevenLabs was not activated: its current STT/TTS/realtime provider classes
stop at `SpeechDispatchAuthority`, the live router is hard-disabled, and no
live provider HTTP implementation exists. Bypassing the guard would cause a
runtime failure. ADR-0038's remaining implementation, key-rotation, deletion
and bake-off evidence remains necessary.

The service is healthy at the new temporary origin
`https://fallen-payroll-basename-mercy.trycloudflare.com`. The rebuilt debug
APK is available at
`/downloads/global-spoken-english-android.apk`, SHA-256
`b7bd4c4b191ceccdcd5583cdcdc1e4bf565c41ed514b4cca311e307578371fc2`,
82,981,426 bytes. It is debug-signed and is not a Play release artifact.

Final gates: `make verify` passed (316 SQLite, 360 PostgreSQL, 466 web),
`make e2e` passed (API 4/4, Playwright 29/29), deterministic `make ai-eval`
passed 15/15 at zero provider spend, `make build` passed, and
`make android-closed-beta` passed. Learner processing remains subject to the
existing separate consent purposes, organization scope, deletion and
retention rules. Final VPS readiness is 23 pass / 1 warning / 0 fail; the
warning is the missing active Content Studio publication head/enrollment pin.
`voice:deletion-status` is healthy with zero active, due, retrying,
dead-lettered or stale source-deletion entries.

## Stable-host cutover — 18 September 2026

**Checkpoint:** `STABLE-HOST-CUTOVER-01`

**Authority:** APP-0098 / D-0317

The `ai.globalspokenenglish.com` A record resolves to the VPS public IPv4
`172.235.18.171`. Before cutover, an encrypted database backup was created as
`soft-launch-20260918T114200Z.sql.gz.enc` (ciphertext SHA-256
`7ea092af530208da00a0bec411b69f891f2e44a71b2fce375e176d00bdb05ef8`).
The prior UFW configuration and mode-0600 runtime env were also retained.

Because this is a directly addressed VPS rather than the retired LAN/router
topology, root systemd socket units expose 80/443 and proxy to the unprivileged
Caddy edge on 8080/8443. UFW allows only OpenSSH, 80 and 443; an external raw
8080 request timed out. The exact socket/service units are versioned in
`infra/soft-launch/`.

The stable edge now provides:

- HTTP 308 redirect to `https://ai.globalspokenenglish.com/`;
- Let's Encrypt certificate with exact hostname SAN, valid until
  17 December 2026;
- API live `ok` and ready `ready`;
- browser and Android/mobile password mode `password_only`;
- successful privacy and account-deletion policy pages;
- stable Secure-cookie/same-origin state; and
- no running Quick Tunnel.

`make soft-launch-ready` reports 26 pass / 1 warning / 0 fail. The sole warning
is the missing active Content Studio publication head/enrollment pin. A
post-cutover synthetic Azure guidance TTS to Sarvam STT round trip passed, and
the deletion outbox remains healthy.

The stable-origin debug APK is available at
`https://ai.globalspokenenglish.com/downloads/global-spoken-english-android.apk`.
SHA-256 is
`37793a48c60fd07e10ac8141bb82ea99f6e90340b93aa56e4a72b76e433a2f15`,
size 82,981,384 bytes, package `com.globalenglish.app`, target SDK 36. The
artifact is debug-signed and is not a Play release AAB.
## Provider live-wiring addendum — 18 September 2026

APP-0099/D-0318 authorize a bounded provider-wiring pass. Azure guidance TTS,
Sarvam recorded STT and Azure recorded-STT failover remain the only deployed
provider paths with passing synthetic evidence.

The repository now contains project-owned ElevenLabs TTS and recorded-STT REST
adapters with exact India-residency host allowlisting and fixed zero-retention
query parameters. Runtime authority additionally requires global/candidate
enablement, kill-switch release, a mode-0600 external secret, learner-content
approval, current vendor/spend evidence, a ₹500-or-lower cap, a genuine key
rotation receipt and a matching content-free deletion-test receipt. Azure TTS
may use ElevenLabs only as its single provider fallback; recorded learner STT
remains Sarvam with Azure failover.

A metadata-only VPS request to the required India models endpoint returned
HTTP 400 `invalid_api_key` for the stored ElevenLabs test credential. No speech,
learner text, audio or transcript was sent. The credential remains unbound and
all ElevenLabs dispatch flags remain false with kill switches true. A new key
must be issued inside the separate India isolated workspace, and a valid voice
ID must be supplied, before synthetic TTS/STT and deletion evidence can run.

Azure OpenAI remains configured but not accepted: provider calls return HTTP
403 and Azure management-plane inspection requires the account owner to
complete interactive MFA. No Azure resource was changed in this addendum.

The verified source commit is `5376a20`. Before the release switch, the VPS
created encrypted database ciphertext SHA-256
`1faf9c0bf922cb8714de53d227211645048dab52f08a10f7c53ecf259c249d8c`.
The exact git archive SHA-256 was
`da4d0ab3d0639ec3190e7081a0f370f075dac2a381710492c1101b277983f2e2`.
It was staged at `/srv/global-spoken-english/releases/5376a20d4f5f`; syntax,
optimized autoload and route discovery passed before an atomic `current`
symlink switch. The previous `56f2caa…` release remains intact for rollback.

After restart, the supervised service was active, loopback readiness was
`ready`, and both public stable-host health endpoints passed. A deployed
authority check returned only the expected safe ElevenLabs TTS denial codes:
`http_enablement_not_authorized`, `kill_switch`, `dispatch_flag_off`,
`evidence_missing`, `secret_unbound`.
