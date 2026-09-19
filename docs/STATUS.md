# Repository status

## APP-0104 native home identity and utility-bar correction — 19 September 2026

- D-0325 records the bounded correction: the native header now visibly names
  **Global Spoken English App**, keeps compact utilities right aligned, and
  exposes the existing System/Light/Dark selector to signed-out users.
- Authenticated learners retain the API-authoritative live-AI allowance in the
  top toolbar immediately beside Account; no allowance is fabricated for a
  signed-out session.
- The product name remains the invariant English proper name in Kannada UI,
  while the Kannada home greeting is now `ಶುಭ ದಿನ`. Catalogue revision
  `ui-kn-IN-2026-09-19.2` remains `pending-independent-review` under ADR-0005.
- The legal footer identifies Northstar Enterprise Solutions Private Limited,
  Mangalore and links `sales@northstar-es.in`.
- Full gates pass: `make verify` (SQLite 330/330; PostgreSQL 360/360; web
  468/468), `make e2e` (API 4/4; Playwright 30/30) and `make build`.
- Exact source `a6d36af` is active as immutable VPS release
  `/srv/global-spoken-english/releases/a6d36af`; public live/ready pass and the
  readiness gate reports 27 pass / 1 existing content-publication warning / 0
  fail. The public and RMX3085-installed APK SHA-256 is
  `5b454d209e90a92199948468e68e3a5f1a4b3eddab49ad8c8da9cb69ffc3ff76`
  (83,050,631 bytes).
- RMX3085 UI hierarchy confirms exact **Global Spoken English App**, `ಶುಭ ದಿನ`,
  right-side Sign in/Theme/Language controls and the Northstar footer. All
  temporary screenshot, hierarchy and transfer artefacts were deleted.

## APP-0103 compact title-bar and Direct-content recovery — 19 September 2026

- D-0324 records the bounded title-bar continuation. On Android-sized native
  chrome, Language and Theme remain accessible native selects but render as
  compact 48×48 icon-led controls; Account and Sign out are consolidated into
  one keyboard-operable menu; and the paid-minutes control uses compact
  icon-plus-value presentation. Variant C and all underlying semantics remain
  unchanged.
- The exact 320 CSS-pixel/200% browser journey now passes without horizontal
  overflow. Account-menu component coverage, App integration coverage,
  typecheck, lint, all six catalogue contracts, `make verify`, `make e2e`
  (API 4/4; Playwright 30/30) and `make build` pass.
- RMX3085 physical verification found and closed three title-bar integration
  defects before acceptance: anonymous text actions were not compact, the OEM
  splash fallback recreated a legacy native ActionBar over the web header, and
  compact controls assumed a green rather than white header. Both wrappers now
  enforce the existing NoActionBar theme before bridge creation. The final
  hierarchy contains no `android:id/action_bar_container`; the title bar has
  one logo and horizontally aligned 48px green/tint icon controls.
- Final source commit `f6e0881` is active as immutable VPS release
  `/srv/global-spoken-english/releases/f6e0881`. Public live/ready pass and the
  readiness gate reports 27 pass / 1 content-publication warning / 0 fail.
  The installed and public debug APK SHA-256 is
  `3e5e495851301ce181ea94c599e3f4402054b1b296722b4d89bf5ee1ce03a97c`
  (83,050,611 bytes).
- Direct commissioning audit lookup now uses the stable organization key
  `global-english-direct` and programme key
  `global-spoken-english-lpl-pilot`, not workstation-specific ULIDs.
- Before any VPS content write, an encrypted production backup was created at
  `/home/globalenglish/ge-offsite-backups/soft-launch-20260919T035337Z.sql.gz.enc`
  with SHA-256
  `1b96eb42e147e5b1bdb4d944db487955c2e21d52179b4afc7006c7344481986d`.
- The exact accepted restore set is 17 latest published blueprints, 68 media
  attachments and 67 unique private audio objects. The database metadata is
  available locally, but the 67 accepted audio bytes are no longer present in
  the local private object store or recovered archives. The import therefore
  stopped before writing production. The VPS still has zero Direct lesson
  media, so its 17 levels correctly remain unavailable rather than becoming
  corrupt metadata-only lessons.
- Required recovery input: restore the 67 exact MP3 objects from the legacy
  authorized source or another verified backup. Each byte stream must match
  its recorded SHA-256 before the tenant-remapped import can proceed.

## KANNADA-LEARNER-UI-FIX-02 learner chrome catalogue correction — 18 September 2026

- APP-0102/D-0323 continue the owner-reported Kannada mixed-language defect as
  a bounded learner-interface catalogue correction. No application logic,
  learning content, API, storage, consent or payment semantic changed.
- Fifty-one English-identical Kannada interface values across Plans, Progress,
  delayed review, model-audio controls and entitlement messages now have a
  Kannada implementation draft. Intentional English learning content,
  authoritative API plan names and the language-neutral `GST` acronym remain
  unchanged.
- `kn-IN` is revision `ui-kn-IN-2026-09-18.2`, with canonical hash
  `sha256:e2481299bf5ce15b3ef8aaa5dd5c867937e9ef315ac0d97a1426aedbbe37a463`,
  and remains visibly `pending-independent-review` under ADR-0005.
- A regression test now rejects future English-source copy in these learner
  chrome namespaces except the explicit `subscription.gst` exception.
- Gates pass: localization 36/36, LearnerJourney 14/14, `make verify` (SQLite
  330/330; PostgreSQL 360/360; web 467/467), `make e2e` (API 4/4; Playwright
  30/30), and `make build` (existing large-chunk advisory only).
- Commit `1bdea67` is active as immutable VPS release
  `/srv/global-spoken-english/releases/1bdea6793424`; readiness is 27 pass / 1
  content-publication warning / 0 fail. The stable public APK and local build
  match at SHA-256
  `28bda09e06d7b062171bdcdc6c41f90c1e89d30630926d38597b8a2a2dcfff4c`
  (83,050,428 bytes), and its preserve-data installation succeeded.
- RMX3085 physical inspection now passes the implementation check for Kannada
  Progress and Plans copy. Authoritative API plan names and intentional English
  learning content remain English; independent Kannada linguistic approval is
  still open. Temporary screenshots and UI hierarchies were deleted.

## KANNADA-ONBOARDING-FIX-01 first-use catalogue correction — 18 September 2026

- APP-0101/D-0322 record the owner's physical-device defect report and bounded
  correction authority. The app was resolving `kn-IN`; the defect was that all
  newly appended first-use questionnaire values in the Kannada catalogue were
  still verbatim English.
- First-use headings, instructions, accessibility choices, adult/goal hints,
  audio checks, separate recording/AI consent explanations, placement choices,
  microphone fallback, private practice and acknowledgement copy now have a
  Kannada implementation draft. The intentional English practice sentence
  remains English.
- `kn-IN` is revision `ui-kn-IN-2026-09-18.1` with a matching canonical hash
  and remains visibly `pending-independent-review`; no independent linguistic
  approval is claimed.
- The first rebuilt follow-up APK exposed a packaging error: `/api/v1` had been
  supplied as part of `VITE_MOBILE_API_ORIGIN`, which the Android validator
  rejects because it requires the bare origin and appends the API prefix. A
  corrected bare-origin build restored authentication and physically proved
  that the 120-minute chip opens Plans without `Level undefined`.
- Gates pass: locale validation (five expected independent-review warnings),
  localization 35/35, focused onboarding/payment-chip 9/9, `make verify`
  (SQLite 330/330; PostgreSQL 360/360; web 467/467), `make e2e` (API 4/4;
  Playwright 30/30) and `make build`.
- Commit `4f4f4fc` is active as immutable VPS release
  `/srv/global-spoken-english/releases/4f4f4fcbf04f`; readiness is 27 pass / 1
  content-publication warning / 0 fail. The stable public APK and local build
  match at SHA-256
  `dddd00ccd940a8d6e64864ad1bee45a57feaf82d315e38884b12b663ce5c388b`
  (83,049,774 bytes), and its preserve-data installation succeeded.
- Device screenshots and UI hierarchy dumps are temporary `/tmp` and `/sdcard`
  diagnostics only. They are not stored in Git, the VPS release or production
  data; all captures from this session were deleted after inspection.

## PAYMENT-RAZORPAY-01 server-authoritative Direct checkout — 18 September 2026

- APP-0100/D-0321 and ADR-0039 authorize the Razorpay vertical slice. The
  supplied credential is test-mode; it is never represented as real capture.
- The API now creates an immutable tenant/learner-bound checkout before the
  provider call, freezes price/tax/plan terms, verifies callback signatures,
  independently reconciles paid/captured provider state and grants exactly
  once. Signed webhooks retain only identifiers and a payload digest.
- The plans UI opens Razorpay only after a deliberate plan selection and sends
  no learner profile or learning content. Pending and ambiguous states remain
  recoverable through the authoritative **Check access** path.
- Focused evidence is green: payment/provider/webhook/tenancy suite 20/20 (131
  assertions), SubscriptionPanel 9/9, typecheck and OpenAPI drift check.
- Repository gates are green: `make verify` (SQLite 330/330, PostgreSQL
  360/360, web 467/467), `make e2e` (API 4/4; Playwright 30/30) and
  `make build` (existing large-chunk advisory only).
- Follow-up commit `3fedf08` introduced the Passport readiness guard and
  paid-minutes callback fix. It is retained as an immutable rollback release;
  `4f4f4fc` is now active on the stable-host invite-only runtime. Razorpay
  test-mode credentials remain held outside Git.
- The original `3ab1dd5` deployment used
  Razorpay test-mode credentials held outside Git. A real-device sandbox card
  flow completed: provider order paid, callback confirmation 200, checkout
  completed and exactly one active 120-minute entitlement granted.
- The release switch initially omitted the existing VPS Passport key files,
  causing Android password sign-in to return 500. The identical prior-release
  keys were restored (matching hashes), sign-in then passed, and the readiness
  gate now fails when a usable key pair is absent. The deployed hardened gate
  reports **27 pass / 1 warning / 0 fail**; the sole warning is the missing
  active Content Studio publication head/enrollment pin.
- The paid-minutes control no longer forwards a React click event as level
  context. On the corrected bare-origin APK it physically opened Plans over the
  retained paid test account without `Level undefined`.
- Live capture is blocked until matching `rzp_live_` credentials, separate
  live Dashboard webhook registration, low-value canary/refund and
  reconciliation evidence exist. Disclosed secrets must be rotated before
  long-lived use. The RMX3085 test also found provider-owned checkout controls
  below the three-button navigation inset; that device defect remains open.

## UX-REM-01 accessibility, reflow and adaptive layout — 19 September 2026

- D-0320 records the bounded adaptive-layout implementation under D-0319. All
  five ADR-0026 destinations remain; no navigation history, quiz behavior,
  API, stored state or authoritative record changed.
- English and Kannada navigation now wrap without clipping at 320/360/412 CSS
  px and a 200% text proxy; authenticated header controls and the paid-minutes
  chip reflow; high-contrast glyphs stay explicit; and short Android landscape
  retains the five-destination bottom grid with a non-sticky header.
- Onboarding now consumes native/web safe-area and IME variables. Quiz actions
  use one inherited native-bottom-navigation offset and no longer double-count
  the IME as internal padding.
- Repository gates are green: `make verify` (SQLite 321/321, PostgreSQL
  360/360, web 466/466), `make e2e` (API 4/4 and Playwright 30/30), `make
  build`, and the production-wrapper debug build (`BUILD SUCCESSFUL`, 103
  tasks). The affected learner/level-path subset is 11/11.
- Installed candidate SHA-256 is
  `e132cc26dc9250420fe8bcb0be1c1283c37a4c9c66da81e518f23fe739941db9`
  (82,981,429 bytes). Android's on-device hash matches exactly.
- The initial locked/guest attempt could not establish physical acceptance:
  shell font/density writes were rejected and the API 36 AVD could not finish
  a software-only boot without KVM. The authenticated follow-up below
  supersedes the RMX3085 guest-state limitation; the API 36 limitation remains.

### 19 September authenticated physical rerun

- The current installed candidate is commit `1bdea67`, APK SHA-256
  `28bda09e06d7b062171bdcdc6c41f90c1e89d30630926d38597b8a2a2dcfff4c`
  (83,050,428 bytes), on realme RMX3085 / Android 13 / API 33 / three-button
  navigation. Split-screen entered through Android task APIs and preserved the
  signed-in app task, so that single device-acceptance row is now evidenced.
- Exact 320 CSS pixels was reached through the OEM Large display-size preset
  (1080px at density 540). Portrait remained visually bounded, but short
  landscape was unusably constrained. At the OEM maximum font scale 1.6,
  English `Progress` split off its final letter and Kannada destination labels
  wrapped awkwardly; exact 2.0 remains unavailable and unclaimed.
- Physical failures were also reproduced for IME-open Type-instead action
  visibility, high-contrast bottom-navigation legibility, and Plans
  close/focus restoration. TalkBack first-use/quiz/result and the physical
  quiz CTA remain not run because the signed-in learner has completed first
  use and has no published levels.
- Bounded defects `UXREM01-DEV-01` through `UXREM01-DEV-06` are recorded in
  the remediation evidence. No app behavior changed and UX-REM-02 did not
  start. Original font/density/rotation/accessibility settings were restored;
  all temporary screenshots and UI dumps were deleted from workstation and
  handset.
- The documentation-only evidence update passed `make verify`: SQLite 330/330,
  PostgreSQL 360/360, web 467/467, with static, contract, locale and security
  gates green. The first sandboxed attempt stopped at Composer audit solely
  because DNS was blocked; the network-enabled rerun passed.

## UX-REM-00 Android candidate and device evidence path — 18 September 2026

- D-0319 records the Accountable Product Owner's instruction to follow the
  Android Mobile UX Remediation and Completion Plan one checkpoint at a time.
  Stated decision, independent-human-review, analytics, payment, Play and
  production gates remain in force.
- The Mac USB ADB daemon is reachable from the Linux repository host through
  the reverse SSH endpoint at `127.0.0.1:5038`. The attached device is realme
  RMX3085 (`RMX3085L1`), serial `J7GUXWEENBNZTW85`, Android 13 / API 33,
  WebView 151.0.7922.199 and three-button navigation.
- A production-package debug candidate was built from exact HEAD `b1660dc`
  against `https://ai.globalspokenenglish.com` with the public Android PKCE
  client. `make android-closed-beta` completed with `BUILD SUCCESSFUL` after
  the exact host rerun required access to the existing Gradle cache.
- Candidate and installed `base.apk` match exactly: SHA-256
  `fffbcec64978b98eaad9658670245fa03a27acf39b64e2612dbedfd663665012`,
  82,981,433 bytes, `com.globalenglish.app` 1.0 (1), min SDK 24, target SDK 36.
- The unlocked baseline is the public guest Home surface in Kannada preview
  mode and contains no personal learner data. Screenshot and UI hierarchy are
  temporary `/tmp` evidence and are not committed.
- UX-REM-00 is complete. UX-REM-01 accessibility/reflow is next; no
  accessibility FAIL has been fixed or promoted to PASS by this baseline.

## PROVIDER-LIVE-WIRING-01 Azure, Sarvam and ElevenLabs — 18 September 2026

- APP-0099/D-0318 authorize the bounded live-wiring checkpoint. The existing
  Azure guidance TTS, Sarvam recorded STT and Azure recorded-STT failover paths
  remain live on the stable-host invite-only stack; their prior synthetic
  round trip remains the current acceptance evidence.
- ElevenLabs TTS and recorded-STT now have project-owned REST implementations:
  exact India-residency host allowlisting, `enable_logging=false` on every
  request, mode-0600 external secrets/evidence, no redirects, response limits,
  safe error mapping and owned result contracts. Azure guidance TTS may attempt
  ElevenLabs only as its one provider fallback after every authority gate
  passes; otherwise it preserves the existing text-first result.
- The authority is no longer an unconditional source stub, but it remains
  fail-closed unless the global router and candidate flag are on, the kill
  switch is off, the implementation exists, and current learner-content,
  India-residency, zero-retention, spend, key-rotation and deletion evidence is
  valid. Unimplemented realtime and Sarvam Bulbul candidates remain explicitly
  unavailable.
- The stored ElevenLabs test credential returned HTTP 400 `invalid_api_key`
  from the required India-residency models endpoint. It remains unbound; no
  ElevenLabs speech or learner content was sent. Activation needs a newly
  issued India-workspace key and a voice ID, followed by synthetic TTS/STT and
  a truthful deletion/zero-retention check.
- Azure management-plane inspection remains blocked on human MFA, so Azure
  OpenAI's HTTP 403 is not resolved or represented as live. No Azure resource
  policy was changed.
- Commit `5376a20` is deployed as immutable release
  `/srv/global-spoken-english/releases/5376a20d4f5f`; the previous `56f2caa…`
  release remains the rollback target. A pre-switch encrypted database backup
  was written with ciphertext SHA-256 `1faf9c0b…249d8c`. Public stable-host
  live/ready both pass after the switch. Deployed ElevenLabs denial reasons are
  exactly router off, kill switch on, candidate flag off, evidence missing and
  secret unbound.
- Repository gates are green: `make verify` (321 SQLite / 360 PostgreSQL / 466
  web), `make e2e` (API 4/4 and Playwright 29/29), `make ai-eval` (15/15,
  deterministic) and `make build`. The first sandboxed `make ai-eval` could
  not reach loopback PostgreSQL; the identical authorized host run exited 0.

## STABLE-HOST-CUTOVER-01 VPS DNS/TLS and Android cutover — 18 September 2026

- APP-0098/D-0317 record the owner's confirmation that the DNS work was done
  and authorize completing the already planned stable-host cutover.
  `ai.globalspokenenglish.com` resolves to VPS IPv4 `172.235.18.171`.
- Persistent root systemd socket units expose only public TCP 80 and 443 and
  forward to the unprivileged Caddy edge on 8080/8443. UFW allows OpenSSH,
  80/tcp and 443/tcp; raw WAN 8080 remains blocked. The exact unit files are
  now versioned under `infra/soft-launch/`.
- HTTP returns a permanent redirect to the exact stable HTTPS hostname. Caddy
  obtained a Let's Encrypt certificate for `ai.globalspokenenglish.com`, valid
  from 18 September through 17 December 2026. Live/ready and browser/mobile
  password-mode checks pass; the rotating Quick Tunnel is stopped.
- A pre-cutover encrypted database backup exists as
  `soft-launch-20260918T114200Z.sql.gz.enc` with ciphertext SHA-256
  `7ea092af…bdb05ef8`. UFW and runtime env rollback copies were captured before
  the change.
- Final VPS readiness is **26 pass / 1 warning / 0 fail**. The remaining
  warning is the missing active Content Studio publication head/enrollment
  pin. Post-cutover Azure guidance TTS → Sarvam STT synthetic round-trip
  passed, and source-audio deletion status remains healthy.
- The stable-host debug APK is published at
  `https://ai.globalspokenenglish.com/downloads/global-spoken-english-android.apk`:
  SHA-256 `37793a48c60fd07e10ac8141bb82ea99f6e90340b93aa56e4a72b76e433a2f15`,
  82,981,384 bytes, `com.globalenglish.app`, target SDK 36. It is not a signed
  Play release artifact.

## PROVIDER-ACTIVATION-01 bounded live-provider activation — 18 September 2026

- APP-0097/D-0316 record the Accountable Product Owner's instruction to
  authorize live provider calls and consented learner-data processing for the
  production launch effort. Existing purpose-specific consent, deletion,
  retention, organization allowlist, spend-cap and fail-closed controls remain
  in force; no consent or tenancy safeguard was bypassed.
- A verified encrypted database backup was created before activation
  (`soft-launch-20260918T101853Z.sql.gz.enc`, ciphertext SHA-256
  `08a1b5ec…f633c863`) and the changed runtime env files were copied to
  `~/ge-provider-activation-rollbacks/20260918T102300Z/` with owner-only
  permissions.
- The deployed revision remains `56f2caa74eed978ba4b20ef20dd14e93e21f6b2c`
  in `APP_ENV=soft-launch`. Azure guidance TTS, Sarvam recorded STT and Azure
  recorded-STT failover are enabled. Synthetic, non-learner calls passed:
  Azure TTS 722 ms, Sarvam STT 417 ms and Azure STT 355 ms.
- Azure OpenAI is configured and its live switch is enabled, but its synthetic
  request received HTTP 403 (`provider_authorization_failed`). The Azure CLI
  session requires interactive MFA before the new VPS egress IP can be
  inspected/allowlisted, so end-to-end live conversation is not claimed.
- ElevenLabs is not activated. The current ElevenLabs/Sarvam realtime and
  ElevenLabs TTS classes are fail-closed contract scaffolds with no provider
  HTTP implementation, and `SpeechDispatchAuthority` denies live deployment.
  Deleting that guard would create a runtime failure, not enable a provider.
- The public HTTPS entry is now
  `https://fallen-payroll-basename-mercy.trycloudflare.com`. A rebuilt
  production-package debug APK is published at `/downloads/global-spoken-english-android.apk`:
  SHA-256 `b7bd4c4b191ceccdcd5583cdcdc1e4bf565c41ed514b4cca311e307578371fc2`,
  82,981,426 bytes, package `com.globalenglish.app`, target SDK 36.
- Pinned gates pass: `make verify` (316 SQLite, 360 PostgreSQL, 466 web),
  `make e2e` (API 4/4 and Playwright 29/29), deterministic `make ai-eval`
  (15/15, zero provider spend), `make build`, and `make android-closed-beta`.
- Final VPS readiness is **23 pass / 1 warning / 0 fail**. The warning is that
  Content Studio has no active publication head/enrollment pin; this remains a
  learner-content launch action, not a provider-activation failure.
- This activation does not manufacture production readiness. Stable DNS/TLS,
  root-password rotation, off-box backup/PITR, independent reviews, remaining
  device/accessibility/language evidence, Android release signing/Play
  delivery and payment credentials/capture remain open.

## VPS-TEST-SECRETS-01 testing credential sync — 18 September 2026

- APP-0096/D-0304 authorize only Global English test-provider credential
  transfer. Azure OpenAI, Azure Speech, Sarvam Indic STT and ElevenLabs secrets,
  configuration and evidence are installed outside Git with mode `0600`.
- VPS-generated application/database/storage/Passport/backup secrets were
  preserved. Personal/unrelated credentials, signing keys, diagnostic tokens,
  stale test-user passwords and payment credentials were not copied.
- A protected pre-sync rollback archive exists under
  `~/ge-secret-rollbacks/20260918T090500Z/`. The verified temporary transfer
  archive was removed from both endpoints after extraction.
- All live-request switches, voice upload and payment remain disabled.
  `make soft-launch-ready` improved to **21 pass / 2 expected warnings / 0
  fail**. Wave 3.8 is `ready` / `checkpoint_closed=true` with
  `dispatch_authorized=false`; no provider call was made.
- Wave 6 Indic preflight intentionally remains fail-closed while dispatch is
  disabled: 20 pass / 4 fail for the disabled upload switch/provider binding,
  absent live authorization and its live-binding test. This is not reported as
  a passed Wave 6 activation.
- Restarting the Quick Tunnel changed the temporary HTTPS entry to
  `https://boston-balanced-reflect-down.trycloudflare.com`. The rebuilt
  VPS-bound debug APK is published with SHA-256
  `56a0dc745925d78a678eaeaf2b67fa6db96a63d3ab0e202f8a5aab8b5508e47c`.

## VPS-SOFT-LAUNCH-01 new VPS deployment — 18 September 2026

- APP-0095/D-0303 authorize deployment of exact revision
  `56f2caa74eed978ba4b20ef20dd14e93e21f6b2c` to the newly supplied Ubuntu VPS
  as invite-only soft launch only. Production, Play, live payment and live
  provider dispatch remain unauthorized.
- The application runs under `globalenglish` from an immutable release with an
  enabled user-systemd supervisor. PostgreSQL, Redis, object storage, Mailpit
  and OTP stub are healthy; UFW permits only SSH inbound and application/data
  services remain behind loopback plus an outbound HTTPS Quick Tunnel.
- External live/ready, Android password-mode and APK-integrity checks pass. The
  public PKCE client uses `com.globalenglish.app:/oauth/callback`; no client
  secret exists. A stale-origin staged APK was rejected and rebuilt; the
  downloadable VPS-bound debug APK SHA-256 is
  `034fa83d724f0c31b91f73b4e7a7e0a460857c74f8c73fa12af263c57157d9e9`.
- Encrypted database backup and an isolated four-second restore drill pass.
  The backup/key are still same-host and therefore do not close off-box custody
  or PITR. The pre-deployment host restore snapshot is retained.
- Final pinned gates pass: `make verify` (SQLite 316/316, PostgreSQL 360/360,
  web 466/466), `make e2e` (API 4/4, Playwright 29/29) and `make build`.
  `make soft-launch-ready` reports 17 pass / 7 intentional warnings / 0 fail.
- Immediate production residuals: rotate the disclosed root password or
  disable password SSH, establish stable DNS/TLS, approve off-box backup/PITR,
  provision governed human accounts, complete independent reviews and Android/
  Play/payment release gates. See
  [deployment evidence](VPS_SOFT_LAUNCH_DEPLOYMENT_2026-09-18.md).

## DEVICE-GATE-FIX-02 deterministic Android gate repair — 16 September 2026

- APP-0094/D-0302 authorize the bounded follow-up requested after the RMX3085
  session: preserve the spoken-recording Back explanation and repair only the
  deterministic test defects exposed while collecting device evidence.
- Android Back during active capture still cancels the recording and returns
  the Speak loop to waiting, but no longer remounts `RecordingTurn` before its
  localized `learner.recording.back_cancelled` status can be announced. A
  parent/child regression test passes.
- Both Android smoke suites now change routes with valid, asserted JavaScript;
  their app, instrumentation and JVM-test source sets compile. Connected
  execution remains open because the phone is no longer attached to this host.
- The keyboard skip-link check now waits for the application link before its
  first Tab. The Wave 3.8 unconfigured test explicitly clears its config
  precondition, and guidance playback ignores late state callbacks after
  unmount. These are deterministic test/lifecycle repairs, not feature changes.
- Final pinned gates pass: `make verify` (SQLite 316/316, PostgreSQL 360/360,
  web 466/466), `make e2e` (API 4/4, Playwright 29/29), `make build`, and both
  wrapper Gradle compile/unit-test commands.
- UX-10A remains open for the repaired APK's physical Back announcement,
  connected seeded smoke journeys, predictive gesture Back, accessibility and
  the other recorded device/human gates. Production, Play and live payment
  remain unauthorized.

## APP-0064 renewal and Wave 3.8 reclose — 16 September 2026

- APP-0064 outside-Git vendor and spend approvals were renewed:
  `reviewed_at` **2026-09-16T13:15:00Z**, `valid_until`
  **2026-12-14T13:15:00Z**, `cap_inr` **500** unchanged.
- `bash scripts/run-speech-wave38-preflight.sh` returned development
  **`ready`**, **`checkpoint_closed`: true**, **`dispatch_authorized`: false**,
  with `deferred_until_live_deployment`:
  `elevenlabs_key_not_rotated`, `elevenlabs_deletion_test_missing` (APP-0081).
- `SPEECH_WAVE38_STATUS_FILE` bound to
  `/home/srbhandary/ge-speech-benchmark/evidence/wave38/wave38-status.json` in
  soft-launch and API env. Soft-launch health live/ready ok after restart.
- Guidance TTS should speak again in Speak/Learn; text-first fallback no longer
  triggered solely by expired APP-0064 evidence.
- Challenger dispatch, ElevenLabs HTTP, Sarvam router enablement and production
  remain unauthorized.

## §10.2 current-phone rerun — 16 September 2026

- Same attached realme RMX3085 (`J7GUXWEENBNZTW85`, API 33, WebView
  151.0.7922.199, three-button). Not a new device.
- Device-walk documentation HEAD `d62a25a`; its installed product build was
  based on the earlier green GATE-FIX-01 candidate `7746a96`. Closure gates
  were subsequently refreshed green at `179b534`. Installed
  `com.globalenglish.app` SHA-256
  `0409e52835b41271fca5e4bdb82da7d2c2598926cfdb11cd0acc6bc62ed493b3`
  (28,307,558 bytes). The previously recorded `779b5b90…` hash is stale.
- Per-row walk superseded the earlier undifferentiated tester Pass. **PASS:**
  fresh-install no launch mic, three-button Back, quiz resume, process
  recreation, IME, light/dark/high-contrast. **FAIL:** permission
  grant/permanent-deny, combined rotation/split-screen, TalkBack critical
  journey, 200% English, 200% Kannada, offline checkpoint recovery.
  **NOT RUN:** gesture predictive Back.
- Six current-phone rows stay marked. 58 of 64 non-N/A cells are unmarked.
  API 33 is not copied into API 24/35/36 or the low-cost column.
- Bounded defects UX10-DEV-01 through UX10-DEV-06 cover the six FAIL rows:
  permission branch coverage, rotation/split-screen, TalkBack journey, English
  and Kannada enlarged text, and offline checkpoint recovery. Gesture Back is
  NOT RUN, not a FAIL. No fix was made.
- Production, Play release and live payment remain unauthorized.

## AUDIT-CLOSE-01 plan §15 closure — 16 September 2026

- APP-0093/D-0300 close the Android mobile UX audit programme with accepted
  residuals. No application code, schema, API/OpenAPI, provider or payment
  behaviour changed in this pass.
- Remaining 51 non-N/A §10 cells (API 24, API 35, API 36, physical low-cost
  phone) are residual. RMX3085 API 33 results are not copied into those
  columns. The later same-device rerun unmarked seven current-phone rows; that
  superseding evidence is not converted to Pass by the earlier closure record.
- UX-EXP-01, UX-EXP-02 and UX-EXP-03 are Deferred: funnel-event emission is
  not implemented, independent privacy/security/tenancy review has not run,
  and no live traffic exists. Experiments are not used as correctness
  evidence.
- Closure gates were re-run at actual candidate HEAD `179b534`: `make verify`
  (SQLite 316/316, PostgreSQL 360/360, web Vitest 465/465), `make e2e` (API
  4/4, Playwright 29/29) and `make build` all passed. Initial sandbox attempts
  were not counted: Composer audit required Packagist network access and
  Playwright required its local web server; each exact command subsequently
  exited 0 with the required host access.
- Production, Play release and live payment remain unauthorized.

## §10.2 current-phone device session — 16 September 2026

- A physical realme RMX3085 (Android 13 / API 33) was attached as
  `J7GUXWEENBNZTW85`. Production debug APK from HEAD `7746a96` was installed
  (`com.globalenglish.app`, SHA-256
  `779b5b904a3b8f0a29d2661269993b74fc33a056e1395781005d0b31268e18b0`) with
  API origin `http://127.0.0.1:8080` over `adb reverse`.
- Agent-verified: fresh install did not show a launch-time microphone
  prompt; `RECORD_AUDIO` remained denied.
- The Accountable Product Owner reported **Pass** for the remaining
  current-phone §10 rows. That undifferentiated Pass is **superseded** by the
  §10.2 rerun at the top of this file.
- `:app:connectedDebugAndroidTest` ran 6/6 failed on unsigned guest Home;
  that is recorded and is not used to fail the manual rows. Demo APK was
  not installed.

## GATE-FIX-01 repository gate repair — 16 September 2026

- APP-0092/D-0299 authorize only the four pre-existing §13.2 gate failures.
  No feature, device, experiment, API/OpenAPI, schema, provider, payment,
  production or release work was performed.
- The Variant-C source test now validates the release-independent
  `jezweb-cN` cache-buster shape instead of encoding `c4`; the current product
  value remains `c8`.
- All five ESLint findings are resolved. Conversation recording phases use
  the parent-controlled phase where supplied, lock transitions remount the
  recorder so its existing unmount cleanup cancels capture, and the unchanged
  `VoicePracticePanel`/`RecordingTurn` tests pass 37/37 with the chrome test.
- Pending independent-review text is white only in green light-theme Studio
  and operator headers. The learner paid-minutes chip uses dark text on its
  existing light chip surface. D-0284/D-0288 green, the Mira white learner
  header and the global muted-text token are unchanged.
- Git history proves `0952364` intentionally added the staff-LAN stateful
  domain before localhost. Only the stale shell-test expectation changed; the
  generator, domains, ports, `APP_URL` and live soft-launch behavior did not.
- Final pinned gates are green: `make verify` completed with SQLite 316/316,
  PostgreSQL 360/360 and web Vitest 465/465; `make e2e` completed API 4/4
  (103 assertions) and Playwright 29/29; `make build` completed the production
  web bundle and tutor-avatar licence enforcement.
- No migration or configuration was added. The existing physical-device,
  catalogue-review, representative-user and experiment/closure gates remain.

## AUDIT-FIX-01 post-implementation audit corrections — 16 September 2026

- APP-0091/D-0298 authorize and record only §13.2 defects 2, 3, 4 (partial),
  5 and 7 (partial). Defects 2, 3 and 5 are resolved; the authorized portions
  of defects 4 and 7 are resolved. Prompt 8b, the closure pass and experiments
  were not started.
- First-use resume is now version 2 and keyed by the same SHA-256
  organization/user/membership binding as the offline store, without raw IDs.
  It expires within 24 hours, rejects mismatched/invalid records and remains
  best effort when storage is unavailable. Stored state contains only the
  cursor, goal, placement, binding hash and timestamps.
- Adult confirmation always starts unchecked. Neither adult confirmation nor
  an onboarding-saved shortcut is persisted; an incomplete authoritative
  Home response forces a resumed post-goal cursor back before submission.
  Focused tests cover learner switching, expiry and the unchecked adult state.
- Remaining direct Home/sign-in hash mutations now use learner history, so
  sign-in has Home as its prior entry. Provider-pending checkout retains its
  pending message and now offers the existing **Check access** reconciliation
  action without claiming success.
- Both wrappers guard the absent bridge/WebView startup path, explicitly pin
  the already-resolved AndroidX Activity 1.11.0 dependency, remove stale
  template unit tests and resolve installed Capacitor source independently of
  pnpm's store layout.
- Both native shells release a pending JavaScript Back request after 1,500 ms,
  cancel its preview and fall through the AndroidX dispatcher; late callbacks
  are ignored. New package-correct JVM tests pass in both wrappers. Connected
  predictive back-to-launcher behavior remains a device gate.
- Focused Vitest passes 22/22 and typecheck passes. Both Gradle commands pass
  (production 62 tasks, demo 60; 6/6 unit tests in each). Full lint, unit and
  E2E retain exactly their §13.2 baselines: five lint findings, the stale
  `jezweb-c4`/`c8` assertion, and seven contrast failures respectively. No new
  failure was introduced.
- No API/OpenAPI, schema, migration, credentials, learner text, quiz-draft,
  review semantics/interval, plans structure, payment provider, production or
  release behavior changed.

## UX-10A Android Back, lifecycle and accessibility hardening — 16 September 2026

- APP-0090/D-0297 authorize and record only UX-10A findings MUX-005,
  MUX-009, MUX-015 and MUX-016. The installed Capacitor 8.5.0
  `BridgeActivity` has no Back policy, so both target-SDK-36 wrappers now own
  AndroidX `OnBackPressedCallback` integration without a predictive-Back
  manifest opt-out or legacy Back/key override.
- Content-free browser history represents primary destination, selected level,
  session part, assessment phase and question ordinal. Back follows question
  → session → level list → prior primary destination, restores focus to the
  originating control and exposes the true prior route to the predictive
  progress label. Level/part/question changes are announced politely.
- Back during permission, recording, pause or processing cancels the in-flight
  recording before navigation and explains the outcome; the existing typed
  fallback remains available. No audio, transcript, learner text or credential
  is added to history or ordinary storage.
- Both wrappers enable edge-to-edge and translate system-bar, display-cutout
  and IME insets into density-correct CSS variables. The UX-04A sticky action
  accounts for the native IME inset. Reload resumes the exact nested question
  route while the existing tenant/member-bound option-only draft preserves the
  stable attempt and signed offline submission.
- Variant C remains light and professional. Narrow navigation now has equal
  48×48-or-larger targets, 12px-or-larger labels and a tint/weight/edge active
  indicator; forced colors uses a 3px system-color edge. Browser dark
  preference retains D-0288's `#FAFBFA` canvas and `#006B3F` action. No dark
  theme was introduced.
- Placeholder native tests are replaced in both packages by compiling
  Espresso/UiAutomator suites for first use, lesson/Back, quiz resume, Speak
  denial/typed fallback, plans return and question/focus Back.
- `adb devices -l` found no device or emulator. All 64 non-N/A device-matrix
  cells remain open; the audit plan §10.1 now gives the exact human execution
  sequence. No connected, predictive-preview, TalkBack, 200% font,
  low-memory, physical IME or physical appearance result is claimed.
- Focused evidence is green: 70 Vitest tests, both UX-10A/offline Playwright
  journeys, application/androidTest compilation for both wrappers, web build
  and production debug APK build. The current debug APK is 28,307,558 bytes
  with SHA-256
  `1dead3e63353f62c6681bd9722ac835e75b35ac7f15270ced7028d13d4c30a39`;
  it was not installed or published.
- UX-10A implementation scope is complete, but checkpoint closure remains
  open. `make verify` reaches only the pre-existing soft-launch fixed-string
  failure after all prior gates pass. `make e2e` passes 22/29, including both
  Level-path journeys; seven retained shared-header/live-minutes contrast
  failures remain. Full web Vitest retains the stale `c4`/`c8` test and scoped
  lint retains the existing RecordingTurn effect error.
- No experiment, API/OpenAPI, schema, migration, authoritative progression,
  payment, provider, dark-theme, production or release action was performed.

## UX-08A contextual plans and recoverable checkout presentation — 15 September 2026

- APP-0089/D-0296 authorize and record only UX-08A/MUX-011. Every
  entitlement-locked level now has a contextual **View plans for Level N**
  action. The Learn tree stays mounted; only a genuinely passed earlier level
  is recapped, and Close or Continue free returns focus to the same locked
  level without changing progress. UX-10A was not started.
- The account surface now presents current entitlement first, then exactly two
  non-demo API-catalogued membership cards. Benefits, plan names, period,
  ex-GST amount, GST and one exact payable total are catalogue-derived.
  Premium receives a visual badge/border only and is never preselected.
- Talk packs are absent until an active non-demo paid entitlement exists. The
  free path remains explicit as **Continue free / review prior lessons**.
- Checkout presentation distinguishes loading, unavailable, pending,
  ambiguous, failed, reconciled and active states. A dummy-provider
  `completed` response is reconciled only after the refreshed authoritative
  entitlement reflects the selected offer; disabled or uncertain behavior
  never produces a success claim.
- Institutional organizations now receive an empty Direct plan catalogue and
  checkout rejection before any row is written. The UI first reads
  `applies=false`, requests no catalogue and renders no Direct checkout.
  Focused service and component tenancy tests pass.
- Focused evidence is green: 45 application/component tests, 11 service tests/85
  assertions, scoped lint, web typecheck/build, six-catalogue validation and
  the contextual plans Chromium journey including exact tax values, scoped
  axe, 320-pixel reflow and focus/route recovery.
- UX-08A implementation scope is complete, but its checkpoint remains open.
  `make verify` again stops only at the pre-existing soft-launch fixed-string
  assertion after both full API suites and all contract gates pass. `make e2e`
  passes 20/28 including UX-08A; eight retained shared-chrome contrast failures
  remain. Representative tier/price comprehension and independent review of
  five non-English `ui-*-2026-09-15.10` catalogues are also open.
- UX-EXP-02 is protocol-ready only for plan-selection intent and the existing
  dummy/disabled provider behavior; it was not activated. Live payment, Play
  Billing, real capture, provider credentials and production remain
  unauthorized. No OpenAPI, migration, Android or persistence change was made.

## UX-04A one-question quiz and resumable drafts — 15 September 2026

- APP-0088/D-0295 authorize and record only UX-04A findings MUX-003,
  MUX-004, MUX-005 and MUX-014. Quick checks and checkpoints now share one
  one-question surface with explicit position, 48 CSS-pixel answer cards,
  visible check-and-text selection state, previous-question restoration and
  one sticky safe-area action. No UX-08A or UX-10A history work was started.
- Every selection is autosaved against organization, learner membership,
  level, part and a stable attempt identifier. The 24-hour allowlisted draft
  stores only blueprint/item identifiers, numeric option identifiers and
  navigation metadata in the existing ADR-0006 encrypted personal-device
  partition. Without that prior opt-in, the draft remains memory-only. No
  learner-authored text, audio, transcript or credential is stored.
- The stable attempt identifier is reused through an uncertain submission and
  handed to the unchanged signed offline checkpoint queue. A draft is cleared
  only after authoritative acceptance or durable queue handoff. Existing
  scoring, attempt limits, lease checks, APIs and idempotency remain unchanged.
- Results show one item at a time with icon, **Correct**/**Not yet**, color,
  the selected answer and the existing server rationale. Retry focuses on
  incorrect items while resubmitting the required full answer map under a new
  attempt identifier. Motion is 180 ms, non-blocking and removed under
  `prefers-reduced-motion`; no sound or haptic was added.
- Focused evidence is green: Level-path Vitest 14/14, web typecheck, scoped
  lint, locale validation, production web build and the UX-04A Chromium
  offline journey. That browser journey covers 48-pixel targets, non-color
  selection, axe, reduced motion, 320-pixel reflow, encrypted reload restore,
  exact offline handoff and item rationale.
- UX-04A implementation scope is complete, but its exit gate is **open**.
  Pinned `make verify` passed both full API suites and contract generation,
  then stopped at the separately owned soft-launch fixed-string assertion.
  Pinned `make e2e` passed 19/27 journeys, including UX-04A; the eight failures
  are the retained serious shared-chrome contrast defects. Physical TalkBack,
  real 200% font, 320-pixel IME/safe-area, assistive keyboard/Switch Access and
  Android lifecycle restoration checks remain human gates. Five non-English
  `ui-*-2026-09-15.8` catalogues remain pending independent review.
- No API, OpenAPI, schema, migration, Android source, scoring, attempt bound or
  offline queue contract changed, and no additional decision request was
  needed.

## UX-03A one-action Home and retention semantics — 15 September 2026

- APP-0087/D-0294 authorize and record only UX-03A findings MUX-010,
  MUX-012 and MUX-013. Authenticated `#home` no longer renders the duplicate
  Tutor hero above `LearnerJourney`; the deterministic mission card's Continue
  is the only primary learning action above the fold. Guest Home is unchanged.
- ADR-0028 selection is unchanged: a due review is still selected before the
  next mission. Meaningful-speaking streak remains a small, neutral,
  non-gating status.
- Review UI now says **ready/overdue**, **reviewed on time** and **reviewed
  late**. The former `learner.progress.delayed_review_missed` key and modifier
  are removed from all catalogues. Progress and both Level-path blocking
  placements provide one-tap routes to the blocking review on Home.
- Mission results now show compact capability progress and one primary
  **Continue from Home** action, with Retry secondary. No XP, hearts or reward
  currency was introduced.
- Demo live-AI seconds are absent from global chrome and appear only on Speak
  and plan/account surfaces. Paid minutes remain globally available using
  non-countdown availability wording.
- Focused evidence is green: 60/60 Vitest tests, web typecheck, production web
  build and locale validation. All seven learner Playwright journeys pass,
  including Kannada Home and the online mission/result journey.
- UX-03A implementation scope is complete, but its exit gate is **open**.
  Pinned `make verify` passes through both full API suites and contract
  generation, then stops at the existing soft-launch contract assertion.
  Pinned `make e2e` passes 18/27; the nine failures are the existing serious
  contrast defect on the Kannada pending-review note in shared header chrome.
  Representative-learner Continue comprehension and independent review of the
  five non-English `ui-*-2026-09-15.7` catalogues also remain open.
- No API, schema, migration, authoritative review rule/interval, onboarding,
  quiz, Android or provider behavior changed in UX-03A. UX-04A was not started.

## UX-02A contextual microphone permission and onboarding recovery — 15 September 2026

This is the historical UX-02A checkpoint record. The current UX-02B state is
recorded under D-0293 below.

- APP-0085 authorizes UX-02 beginning with UX-02A only. D-0292 supersedes
  D-0284's launch-time `RECORD_AUDIO` clause while retaining its bundled
  `https://localhost` WebView origin.
- Production `com.globalenglish.app` and demo `com.globalenglish.demo` now
  register app-owned contextual microphone plugins. Neither `MainActivity`
  requests permission during launch. Their WebChrome clients grant only
  already-authorized audio capture from HTTPS localhost and deny every other
  origin/resource/permission combination.
- The shared speaking path shows localized rationale after deliberate intent,
  then calls Android before `getUserMedia`. Granted, denied-once and
  denied-permanently states are surfaced; **Type instead** remains available
  and the non-spoken path remains unassessed.
- LearnerJourney now distinguishes validation, offline, 15-second timeout and
  server onboarding failures. **Retry same submission** retains the frozen
  payload and command id; **Edit choices** discards it, unlocks the fields and
  allocates a new command id. All six catalogues carry the new copy at
  `ui-*-2026-09-15.4`; five non-English catalogues remain pending independent
  review.
- Focused web tests pass 45/45; focused Chromium first-use/recovery passes 3/3;
  production and demo Android unit suites pass 6/6 each. `make build` and the
  production `make android-closed-beta` target pass. The synced local debug APK
  is 28,287,126 bytes, SHA-256
  `4fba09d802a0c7e862a32257e99bda5d784428375968281b2cd03c33cf66164f`;
  it was not published.
- UX-02A implementation scope is complete, but its exit gate is **open**.
  `make verify` still stops at the pre-existing soft-launch fixed-string
  contract after 316 SQLite and 360 PostgreSQL tests pass. `make e2e` passes
  17/27 Chromium journeys; all three UX-02A additions pass and all ten failures
  are the existing theme contrast debt. No Android device is attached, so the
  fresh-install/system-dialog and TalkBack checks remain open. UX-02B was not
  started in this UX-02A slice and was subsequently implemented under D-0293.
  No API, schema, migration, credential storage or learner-text persistence
  changed in UX-02A, and no additional DR was required.

## UX-07A accepted review semantics implementation — 15 September 2026

- D-0289's on-time rule is implemented by a single classifier: an attempt
  before `due_at` is ineligible; an eligible attempt on the same local date as
  `due_at` in the occurrence's persisted timezone is reviewed on time; an
  eligible attempt on a later local date is reviewed late. Server and sync
  timezones are not inputs.
- D-0290's existing write behavior is now explicit in method and product
  vocabulary. An eligible attempt clears one open due occurrence regardless of
  correctness. An incorrect attempt remains non-mastering and schedules the
  unchanged deterministic `immediate_retry` remediation review.
- OpenAPI 0.47.0, generated contracts and all repository consumers atomically
  replace delayed-review `completed_*` metrics with `reviewed_*` and operator
  `review_return_completed_count` with `review_return_attempted_count`. All six
  UI catalogues and visible labels say reviewed/attempted. The internal
  `completed_at` storage column remains; no migration or review-interval change
  was needed.
- Exact persisted-timezone edge tests, exact-command replay/no-duplicate proof,
  incorrect-attempt mastery/remediation proof and cross-tenant read/write
  tests pass. Focused API tests passed 14 tests / 143 assertions across SQLite
  and PostgreSQL; focused web tests passed 17/17. OpenAPI generation check,
  locale validation, typecheck and PHPStan pass.
- UX-07A application scope is complete, but its repository-wide exit gate is
  **open**. Literal `make verify` encounters ambient Node 22.22.1 instead of the
  pinned 24.18.1; the pinned rerun passes both full API suites (316 SQLite,
  360 PostgreSQL) and then fails a pre-existing soft-launch fixed-substring
  assertion. Full web Vitest passes 432/433 and fails the pre-existing APK
  cache-buster `c4`/`c8` assertion; lint retains three unrelated existing
  errors. `make e2e` reaches Playwright with the pinned toolchain and passes
  13/24, with all 11 failures from pre-existing color-contrast violations.
  These out-of-scope failures were not repaired.
- No Home layout, onboarding, quiz rendering or Android code changed. No
  ADR-0028 or master-plan amendment is required because this implements its
  previously accepted clarification without changing deterministic authority.
  No DR was opened because no additional authority is needed.

## UX-AUDIT-00 metric taxonomy and review decisions — 15 September 2026

- UX-AUDIT-00's documentation scope is complete under APP-0084. D-0289
  accepts the persisted-timezone local-date definition of reviewed on time;
  D-0290 accepts correctness-independent clearing and reviewed/attempted UX
  vocabulary.
- Required boundaries, existing service behavior and UX-07A's exact future
  obligations are recorded in
  [UX_AUDIT_00_DECISION_PACKET.md](UX_AUDIT_00_DECISION_PACKET.md).
- [UX_FUNNEL_EVENT_CONTRACT.md](UX_FUNNEL_EVENT_CONTRACT.md) defines versioned,
  allowlisted events, forbidden content, reproducible denominators, 30-day raw
  retention, 365-day aggregate retention, organization scoping, access and
  deletion behavior. No events are emitted by this checkpoint.
- The supplied 35% “profile creation” claim maps only to the authenticated
  `#home` LearnerJourney onboarding card while
  `onboarding.completed=false`. Its external source was left as an unfilled
  placeholder and is therefore **unverified**. The supplied paywall-abandonment
  claim is also unverified.
- At UX-AUDIT-00 reconciliation time, Git history contained no decision-ledger
  revision for the legacy D-0294–D-0297, D-0300 or D-0302 progress-log
  references. The non-authoritative UI/UX companion was corrected rather than
  retroactively approved. D-0294 was subsequently assigned to the unrelated,
  properly authorized UX-03A decision under APP-0087.
- UX-07A subsequently closed the completion-labelled delayed-review API/UI
  gate under D-0291. Independent privacy/security/tenancy review remains the
  sole pre-emission gate; no funnel event emission was implemented.
- Pinned-toolchain `make verify` passed Composer/Pint/PHPStan, 309 SQLite and
  359 PostgreSQL tests, locale, OpenAPI/generated-contract and release-contract
  stages, then failed on pre-existing soft-launch test drift. Supplemental
  typecheck passed; lint has three existing errors/two warnings and web Vitest
  has one stale cache-buster expectation (`c4` versus current `c8`). No
  out-of-scope repair was made; exact evidence is in `docs/QUALITY_REVIEW.md`.

## Introduction drill UX — 14 September 2026

- Play model audio no longer shows on-screen “Model audio is playing / paused”
  (status stays for assistive tech only).
- Introduction/Practice show the current spoken sentence (for example
  `I can drink water.`) next to audio and Hold to talk.
- After a try, Swathi speaks the next English model and the L1 correction
  in the selected language even when the turn payload has no audio.
- Extra chrome (notices, pattern lists, activity copy, voice intros) is
  collapsed or hidden on the drill so the next turn can start automatically.
- Homepage APK cache-buster is `?v=jezweb-c8`. Listen panel no longer
  spoils the model sentence before audio downloads. Introduction drill
  uses tap-to-speak / tap-to-send. After a try, Swathi speaks the L1
  correction or praise in the selected language, then the English model
  to say next.

## Staff LAN login + jezweb-c4 closed-beta — 14 September 2026

- `northstar` password sign-in is valid. Earlier “credentials not accepted”
  on Android/web against `http://117.254.108.143:8080` was a **502** from a
  stale `:8080` Caddy proxying `/api` and `/oauth` to a dead Laravel port,
  plus password-sign-in **429** after repeated staff attempts (5 / 15 min
  per username).
- Public HTTP now kills leftover `:8080` listeners, proxies the live API
  port, and keeps `APP_URL=http://117.254.108.143:8080` when
  `GE_SOFT_LAUNCH_SERVE_PUBLIC_HTTP=true`. Staff password sign-in is allowed
  on that origin (port 8080 only).
- Verified live: Android mobile password grant **200**, `GET /me`, 17-level
  catalogue, Level 1 introduction audio **200**; browser sign-in + Home /
  Learn / Speak / Progress / 1:1 + Level 1 audio toolbar.
- Homepage APK cache-buster is `?v=jezweb-c4`.
  `http://117.254.108.143:8080/downloads/global-spoken-english-android.apk?v=jezweb-c4`
- Introduction grammar drills auto-accept transcripts for learners (no
  confirmation step). Audio pause/stop/slow stay on one toolbar row.

## Variant C jezweb-ux Android learner chrome — 13 September 2026

- Visible product name is **Global Spoken English App** (`app.name`,
  shell header, `document.title`, `index.html` title, Capacitor `appName`,
  Android `strings.xml`, and static auth/privacy pages).
- On-screen AI teacher is **Swathi** everywhere in learner UI copy
  (`shell.mira.*`, `tutor.name`, conversation strings). Illustration
  filenames may remain `tutor-maya.png` (D-0218).
- Level Introduction listen audio adds **Stop** and a seek **slider**
  beside Play/Pause/Resume and Slow. Introduction grammar drills auto-accept
  the transcript for learners. The hold-to-talk button stays visible but
  **disabled** while Swathi speaks, so TTS cannot auto-submit the next
  pattern early on Android APK.
- Learner chrome on web and the production Capacitor APK
  (`com.globalenglish.app`) uses design-audit Variant C jezweb-ux:
  professional light product shell, Continue Learning flow, and an
  Android-first bottom tab bar (Home · Learn · Speak · Progress · 1:1).
  Speak is an equal tab, not a raised FAB.
- Homepage APK link is `/downloads/global-spoken-english-android.apk?v=jezweb-c3`.
  Staff LAN download (requires Real-Studioz deploy):
  `http://117.254.108.143:8080/downloads/global-spoken-english-android.apk?v=jezweb-c3`.
  **Until Real-Studioz runs `bash scripts/deploy-jezweb-c2-apk.sh`, the live
  server still serves the 13 Sep build (~55 MB) with Mira strings.** Install
  the jezweb-c3 release instead:
  https://github.com/srbhandary1/GESC/releases/tag/jezweb-c3-closed-beta-20260914
  (15,497,406 bytes, SHA-256
  `2664adc7d65a1eb70213a796d9061872174c0c1974d5f45ba239cf021d23bf18`).
  Rebuild on Real-Studioz after merge:
  `VITE_MOBILE_API_ORIGIN=http://117.254.108.143:8080 make android-closed-beta`
  then `make soft-launch-refresh-web`.
- jezweb-c3 closed-beta APK (agent build, 14 Sep 2026): Swathi naming,
  listen stop/slider, disabled hold-to-talk while Swathi speaks. Package
  `com.globalenglish.app` 1.0 (1), targetSdk 36, 15,497,406 bytes. Live
  `HEAD` of the LAN download URL still returned `content-length: 55616920`
  until Real-Studioz publish completes.
- Routes, APIs, room types, auth, LessonPractice, audio curriculum,
  ConversationOrchestrator and TTS adapters are unchanged.
- This does not authorize Play, production, or marketing cutover.

## Variant A learner chrome (design-audit) — 13 September 2026

- Learner Learn / demo My Levels now uses the locked Variant A pattern:
  Continue Learning hero, horizontal level chips, room icon chips, and a
  calm Home · Learn · Speak · Progress pill cluster. ADR-0026 still keeps
  1:1 as the fifth destination.
- Learner tokens align to `#006B3F` / `#005533` / `#e6f2ec` / `#1a1a1a` /
  `#5c6670` / `#e2e8e5` / `#fafbfa` / `#ffffff`. Learner titles use the UI
  sans stack (no serif page titles).
- Removed from learner chrome: “Phase 1 learner shell” / synthetic
  dashboard lead; the sticky yellow language-fallback banner (now a note
  under the language control); a skip-link that painted over content
  (skip remains, visually hidden until focus).
- Routes, APIs, room types, auth and learning logic are unchanged.
  LessonPractice, audio curriculum, ConversationOrchestrator and TTS
  adapters are untouched. Marketing guest landing is unchanged.
- Operator rebuild after merge:
  `VITE_UI_TRANSLATION_PREVIEW=true pnpm --filter @global-english/web build`
  or `make soft-launch-refresh-web`. Do not deploy from this agent.

## Guest `#home` uses Mira chrome — 13 September 2026

- Unsigned / guest `#home` no longer renders `MarketingLanding`. Soft-launch
  guests (anonymous / checking / unavailable, not demoMode) see
  `GuestMiraHome`: Mira portrait, Today hero, next-lesson card, Speak now
  and Sign in → `#sign-in`.
- Dedicated `#sign-in` is unchanged. `MarketingLanding` remains at
  `#about` and `#marketing`.
- LessonPractice, audio curriculum, ConversationOrchestrator, and TTS
  adapters are untouched. `miraGescGreenPin.css` and
  `miraLearnerChrome.css` stay.
- Operator rebuild after merge:
  `VITE_UI_TRANSLATION_PREVIEW=true pnpm --filter @global-english/web build`
  or `make soft-launch-refresh-web`. Do not deploy from this agent.

## Mira / Wonder learner chrome on soft-launch — 13 September 2026

- Ports PR #6 learner chrome (Mira hero, next-lesson card, center Speak
  FAB, Grove first-run overlay, Wonder-led green mobile shell) onto
  `codex/confidence-conversation` without merging PR #6's LessonPractice
  cherry-picks.
- Soft-launch `#home` for authenticated learners is still
  `TutorHomeHero` + `LearnerJourney` (not `DemoPhase1Shell`). Those
  surfaces now use Mira portraits, Speak now, and the next-lesson card.
  Demo `#home` keeps Phase 1 Attendance / AI Teacher / Daily Activity /
  My Levels / Notice Board wiring and only restyles the dashboard chrome.
- Five-destination nav (ADR-0026) is unchanged: Home · Learn · Speak ·
  Progress · 1:1. Speak is the center FAB on narrow viewports.
- `miraGescGreenPin.css` stays after Studio; `miraLearnerChrome.css`
  loads after the pin. Brand green `#006B3F`.
- LessonPractice, LevelSessionAudioStreamer, ConversationOrchestrator,
  listen-and-learn / Levels audio, and TTS adapters are untouched.
- UI catalogues are `ui-*-2026-09-13.1` (pending independent review).
- Do **not** deploy from this branch. Operator rebuild after merge:
  `VITE_UI_TRANSLATION_PREVIEW=true pnpm --filter @global-english/web build`
  or `make soft-launch-refresh-web`.

## LPL-05 all-level commissioning wave — 13 September 2026

- **Completeness matrix:** `DirectPathwayCommissioningAuditService` audits all
  17 configured Direct levels against media, transcript metadata, objective,
  validated blueprint, English/Kannada review, quiz rationales, Apply task,
  rights, publication eligibility and `level_checkpoint`.
- **Wave grouping:** Level 1 (wave 1), Levels 2–3 (wave 2), Levels 4–17
  (wave 3) — all derived from config, not hard-coded lesson counts.
- **Operator command:** `php artisan lesson-practice:audit-direct-pathway-commissioning`
  (exit 0 when every enabled level is complete).
- **Live audit (13 September 2026):** `global_english` reports 17/17 complete
  across all three waves (`matrix_totals` green for every field). Evidence:
  `docs/evidence/lpl-01/lpl05-commissioning-wave-result.json`.
- **Publication chain:** APP-0065 authored Levels 2–17 → APP-0066 English review
  → APP-0067 Kannada review → WAVE-7.1 publish → LPL-04 checkpoint republish.
- **Known quality debt (unchanged):** Kannada reviews are authored summaries,
  not independent specialist sign-off; Level 15 practice reuses a combined asset.
- **Still out of scope:** LPL-07 production release acceptance and independent
  Kannada re-review campaign.

## LPL-07 rollout gate + level-path offline E2E — 13 September 2026

- **Playwright:** `apps/web/e2e/level-path-offline.spec.ts` covers personal-device
  enablement, signed level-bundle prefetch, offline checkpoint queue with
  pending-authority copy, and reconnect sync through
  `POST /api/v1/offline-level-checkpoint-submissions` (no online checkpoint POST).
- **Rollout gate:** `make lpl-07-rollout-gate` runs production commissioning
  evidence check (`lpl05-commissioning-wave-result.json`), focused PostgreSQL
  LPL tests, the level-path offline Playwright journey, and writes
  `docs/evidence/lpl-01/lpl07-rollout-gate-result.json`.
- **Operator evidence command:** `php artisan lesson-practice:lpl-07-rollout-evidence`
  emits commissioning JSON for live rollout review (separate from repository gate).
- **Still out of scope:** production/public release acceptance, Android device
  UAT matrix, migration/backup-restore closure in this slice, and independent
  Kannada re-review campaign.

## LPL-04 level checkpoint + sequential unlock — 13 September 2026

- Level *N* (> 1) stays progress-locked until Level *N − 1* has all four
  sessions complete **and** a passing level checkpoint. Level 1 is always
  allowed when commissioned.
- Backend: `LevelCheckpointService` serves bounded checkpoint attempts (3 max,
  80% pass); `LevelSessionProgressService` unlock rule is now `level_unlock_v1`;
  append-only `level_checkpoint_attempts` and `level_unlock_decisions` record
  evidence; unlock fires on checkpoint pass, not on the last session completion.
- Learner API: `GET /api/v1/me/levels` adds `checkpoint_status` and
  `checkpoint_attempts_remaining`; `GET/POST .../checkpoint(-submissions)` for
  the novel end-of-level quiz (derived from blueprint `check` items when no
  dedicated `level_checkpoint` block exists).
- Web: after all four sessions, `LevelPathPanel` shows the level checkpoint
  form; demo API mirrors checkpoint + progression (session routes stay open for
  content exploration).
- OpenAPI updated for checkpoint routes and level list fields.
- Tests: `LevelSessionProgressServiceTest`, `LevelSessionLevelsHttpTest`
  (includes failed-then-pass checkpoint HTTP), `LevelPathPanel.test.tsx`,
  `localDemo.test.ts`.
- Authored Levels 2–17 blueprints include a dedicated `level_checkpoint`
  block (novel “Level review:” prompts, `{concept}-cp-0N` item ids). Level 1
  receives `level_checkpoint` at publish time from its existing `check` block.
- UI adds checkpoint-ready and attempts-exhausted sections on the level path;
  generated contract types include checkpoint fields/routes.
- **Live republish (13 September 2026):** APP-0065/0066/0067 replace + review
  on `global_english`, then `lesson-practice:publish-direct-pathway
  --approval=WAVE-7.1` published 17/17 with `level_checkpoint` (3 items each).
  Evidence: `docs/evidence/lpl-01/lpl04-checkpoint-republish-result.json`.
  Replay is hash-idempotent (`lpl04-checkpoint-*` keys).
- **Review-due blocking (13 September 2026):** `ReviewDueBlockingService`
  blocks level checkpoint and unlock while an incomplete delayed review is
  due (`checkpoint_status=review_due_blocked`). Later-due reviews do not
  revoke an issued unlock. Web level path surfaces the block with Home-first
  guidance.
- **Spoken-evidence checkpoint claims (13 September 2026):**
  `LevelSpeakingEvidenceService` projects `spoken_evidence` on level list and
  checkpoint submissions (`evidence_state`, `spoken_state=unassessed`,
  `spoken_progress_claim=false`). Unlock evidence stores the same snapshot;
  checkpoint attempts persist `spoken_evidence_json`. Knowledge pass never
  awards spoken mastery.
- **Offline checkpoint equivalence (13 September 2026):** personal-device
  offline store caches checkpoint detail (hash-bound), queues encrypted
  checkpoint submissions while disconnected, and syncs through the existing
  idempotent `POST .../checkpoint-submissions` route on reconnect. UI shows
  pending-authority copy until the server returns score/unlock; no local pass
  or unlock is shown before authoritative scoring.
- **LPL-04 verification closure (13 September 2026):**
  - Checkpoint idempotency replay returns the original attempt and unlock
    (`LevelSessionLevelsHttpTest`); pre-pass idempotency lookup fixes offline
    reconnect after a passing submit.
  - Unlock recording uses row lock + idempotent replay (`LevelSessionProgressService`).
  - Variable session-part counts come from config, not a hard-coded four
    (`LevelCheckpointVariablePartsTest`).
- **LPL-06a shared due-review inventory (13 September 2026):**
  `ReviewDueBlockingService` is the single earliest-due query for checkpoint
  blocking, Home (`due_review`, `upcoming_reviews`) and Progress
  (`actionable_due_review`). Home resolves review titles from the blocked
  mission, not only today's mission; Progress and Home surface continue CTAs
  when reviews block level unlock.
- **LPL-06b signed level offline bundles (13 September 2026):**
  `GET /api/v1/me/levels/{n}/offline-bundle` issues
  `global-english.offline-level-bundle.v1` leases (checkpoint + practice check
  + introduction metadata, no answer keys). Offline checkpoint queue requires a
  stored signed bundle; sync uses `POST /api/v1/offline-level-checkpoint-submissions`.
- **LPL-06c content-free learning metrics (13 September 2026):**
  `OperatorLearningMetricsQuery` adds seven org-scoped signals to pilot
  analytics (stage completions, speaking turns, quiz retries, review return,
  AI fallback, latency samples, session cost).
- **LPL-06d session limits and budget alerts (13 September 2026):**
  `ConversationSessionBudget` enforces configurable per-session learner-turn and
  token ceilings before live dispatch. Speech economics summary exposes a
  content-free `budget_alert` when estimated spend crosses the configured INR
  warning threshold.
- **LPL-06e privacy inventory and LPL drill coverage (13 September 2026):**
  `LearnerPrivacyInventory` v12 inventories level session/checkpoint/unlock
  tables with retain-immutable outcomes; `OperatorPrivacySupportTest` asserts
  the new record classes.
- **Still out of scope:** full LPL-00 §7 multi-lesson publication rules,
  learner self-service privacy export UI, and operator privacy export/erasure
  console panel.

## Mira GESC-green theme pin + Android mic — 13 September 2026

- Theme-only pin of Superdesign Futuristic Learning Studio chrome to Mira
  GESC-green (`#006B3F` / `#0B1210` / `#3DDC97`). The pin lives in
  `apps/web/src/theme/miraGescGreenPin.css` and loads after
  `studioExperience.css`. LessonPractice, LevelSessionAudioStreamer,
  ConversationOrchestrator, listen-and-learn / Levels audio, and TTS wiring
  are unchanged.
- The ~`142d7a0` “Pin the Superdesign Futuristic Learning Studio theme
  separately from lesson content” commit was **not** on
  `origin/codex/confidence-conversation` (local-only on real-studioz). This
  branch incorporates the same isolation idea cleanly.
- Android demo and production wrappers now request `RECORD_AUDIO` at launch.
  The bundled APK stays on `https://localhost` so Speak can use getUserMedia.
  HTTP `http://117.254.108.143:8080` remains the Caddy web demo only — do not
  live-load that origin in the WebView.
- Existing learner UX PR #6 (`cursor/learner-ux-elevate-5f47`) already mixed
  these fixes with curriculum cherry-picks. This branch is the theme-only +
  mic slice on `codex/confidence-conversation`.
- Production `globalspokenenglish.com` and marketing/Plesk cutover are
  untouched.

## Sign-in restored — 3 September 2026

- The generic “Sign-in could not be completed” error was an API outage: Laravel could not bind `:8000` (held by another local process) and the user systemd service was crash-looping.
- Soft-launch now selects a free loopback port (`GE_SOFT_LAUNCH_API_PORT`, currently `8012`) and Caddy proxies to it. After a tunnel URL change, the API is restarted so Sanctum stateful domains match the live origin.
- Live origin: `https://viii-throw-literature-caroline.trycloudflare.com` (`health live: ok`, password mode `password_only`). Use this URL, not the previous Quick Tunnel hostname.
- Android APK was not rebuilt.

## Legacy Academy public template — 3 September 2026 (direction superseded)

- Guest `#home` now follows the Global Academy **page** from `globalspokenenglish.com`: teal `#17ad90` bar, **Login** + coral **Join Now**, full-bleed 5-image slider (1600×746, dots/arrows), teal intro band with Sign-in CTAs, 6 why tiles, Vision/Mission splits, 6 how-it-works steps, Demo/Modules/Premium cards (₹0 / ₹350 / ₹500), and a join band.
- All visible sentences are the **current** product catalogues (Kannada-guided path, no ₹320/₹3000, no fluency-guarantee, no View Students).
- Guest chrome is theme-independent (`ge-application-frame--academy`). Default appearance is **light**.
- Browser-checked guest chrome on the previous tunnel origin. Sign-in is restored on `https://viii-throw-literature-caroline.trycloudflare.com` (`health live: ok`, password mode `password_only`). Laravel now binds `127.0.0.1:8012` when `:8000` is held by another local process; Caddy uses `GE_SOFT_LAUNCH_API_PORT`.
- Public shell cache now uses the source-derived
  `global-english-public-shell-<sha256>` release identity from D-0281. Android
  APK was not rebuilt and still targets an older tunnel origin.
- D-0279 supersedes this visual direction with Futuristic Learning Studio. The
  legacy implementation remains in the current build only until UX-01B replaces
  it; it is not the target template.

## UX-02B — Progressive first use and first success — 15 September 2026

This is the historical UX-02B checkpoint record. UX-03A was not part of this
slice and was subsequently implemented under APP-0087/D-0294.

- APP-0086/D-0293 authorize and record only UX-02B/MUX-006.
- Production `LearnerJourney` now uses the shared `OnboardingStepper` for the
  §6.2 order: value, adult confirmation, ephemeral language/accessibility,
  goal, beginning/defer placement, microphone-free output check, two separate
  optional consent purposes, contextual mic try, one private spoken/typed turn
  and acknowledgement before Home. The synthetic demo remains covered.
- The authoritative onboarding command is unchanged and contains only
  `adult_confirmed` and `goal_key`. No API, OpenAPI, schema or migration was
  added. Placement does not infer ability from accent, location or first
  language.
- A strict versioned resume record stores only the safe step, authoritative
  adult/goal values, placement enum and saved marker. Locale/accessibility,
  consent choices, credentials, audio, transcripts and learner-authored text
  are not persisted; completion and logout clear the record.
- Recording-upload/transcription and automated provider processing remain
  distinct, unselected ADR-0007 purposes. The audio-output check does not touch
  the mic; optional recording still enters through UX-02A only after a
  deliberate action; typed/non-AI learning remains available.
- Focused evidence is green: 5 Vitest files / 48 tests, web typecheck, scoped
  lint, locale validation, diff check, and 3/3 Chromium first-use/recovery
  journeys (English plus Kannada preview with process-reload restoration).
- Pinned `make verify` reached the retained pre-existing soft-launch contract
  failure after all earlier stages passed. Pinned `make e2e` passed 18/27;
  every UX-02B journey passed and the nine failures are the retained shared
  contrast defects. TalkBack, real 200% font, 320px/IME, representative-adult
  comprehension and independent catalogue review remain human gates.

## Current checkpoint

| Field | Value |
|---|---|
| Checkpoint | PAYMENT-RAZORPAY-01 server-authoritative Direct checkout |
| Status | APP-0100/D-0321 Razorpay implementation and invite-only sandbox deployment are complete. The RMX3085 test payment produced a paid provider order, a server-verified completed checkout and one active entitlement. The supplied key is test-mode, so real-money capture remains technically unavailable. Test-mode Dashboard webhook registration is still external and no webhook receipt was inferred. UX-REM-01 remains independently in progress. Azure Speech and Sarvam recorded speech are live; ElevenLabs remains fail-closed; Azure OpenAI remains pending its external gate. `APP_ENV` remains `soft-launch`. |
| Canonical baseline | `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.15 |
| Updated date | 18 September 2026 |
| Git branch | `main` |
| Product code | Five-destination nav (ADR-0026); teacher name Swathi (D-0218); in-app Android password (ADR-0033); Google/Facebook extra sign-in (ADR-0034); Direct subscriptions (ADR-0035); Premium courtesy month (ADR-0036); paid live-AI caps and talk packs (ADR-0037); Indic STT port with 25s cap and Azure failover (ADR-0027 Wave 2A) |
| Next required action | Register `https://ai.globalspokenenglish.com/api/v1/payments/razorpay/webhook` separately in Razorpay Test and Live modes with the required events; supply rotated `rzp_live_` credentials; run a low-value live canary, refund and reconciliation; and repair/retest the provider-checkout bottom-inset defect. UX-REM-01 physical acceptance, Azure/ElevenLabs, catalogue, academic, analytics and Play gates remain open. |
| Production/public release | Authorized in principle by APP-0100; not yet executed or accepted because the stated technical, external-provider, accessibility, review and Play gates still lack evidence |
| Soft launch | Azure guidance TTS, Sarvam Indic STT and Azure recorded-STT failover are live. Azure OpenAI is configured but blocked by HTTP 403 and is not accepted as live. ElevenLabs source support exists but dispatch remains off. UI catalogues are at `ui-*-2026-09-15.11`; the five non-English catalogues remain pending independent review. Razorpay test-mode deployment and one exact-once entitlement smoke are evidenced; live capture is unavailable without live credentials. |

## D-0283: UX-01 Studio programme complete — 6 September 2026

- The Accountable Product Owner instruction to remove UI/UX blockers and
  complete the implementation closed the UX-01B screenshot review without a
  further ceremony. UX-01C–G are implemented on the same Futuristic Learning
  Studio shell.
- Guest landing now carries the full hierarchy: hero, Listen→Speak→Understand
  →Improve preview with Type instead, five real-life outcomes, Swathi/AI
  boundary, catalogue-true Demo/Modules/Premium cards, trust/readiness and a
  closing CTA. Sign-in is a Studio handoff; credential transport is unchanged.
  Authenticated learners use the Studio frame. Guest, learner and operator
  routes are lazy-loaded.
- UI-only product holes from the UX audit are repaired without new APIs: due
  review is no longer coupled to `next_mission` duration; Role-play presents
  Observe and Rehearse (D-0215 optional examples; the API still starts at
  observe); transcripts always offer accept/edit/discard; model audio has
  0.75× slow and resume; Progress retries and uses catalogue labels.
- Exact pinned `make verify` passed: 249 standard backend tests (2,652
  assertions), 341 PostgreSQL tests (4,775 assertions), 71 web test files (343
  tests), 35 localization tests and 12 design-system tests. `make e2e` passed
  4/4 integration tests (103 assertions) and 21/21 Chromium journeys.
  `make build` passed. Initial JS is 78.58 kB gzip; guest first visit plus the
  marketing chunk is 235.77 kB gzip (under the 250 kB target).
- Bilingual 320/1280 evidence is in `docs/evidence/ux01g/` (landing, sign-in,
  Home) and `docs/evidence/ux01c/` (landing). Kannada is development-preview.
- Unused Academy CSS was removed. No schema, payment-provider, APK or
  production change. UX-02–10 remain proposed.

## D-0282: UX-01B futuristic first screen implemented — 6 September 2026

- Guest mode no longer loads the Academy presentation class/logo or the
  five-image autoplay carousel. The first fold is an original CSS-native deep
  Studio composition with controlled cyan/teal/violet accents.
- It retains the governed message catalogue and contains one primary sign-in
  action, one secondary four-session action, the canonical four-stage path and
  explicit Swathi AI disclosure. It introduces no remote media or fonts.
- Exact pinned `make verify` passed: 249 standard backend tests (2,652
  assertions), 341 PostgreSQL tests (4,775 assertions), 71 web test files (339
  tests), 35 localization tests and 12 design-system tests. `make e2e` passed
  4/4 integration tests (103 assertions) and 21/21 Chromium journeys;
  `make build` passed and the served release was refreshed.
- Browser evidence covers axe across all interface locales, keyboard entry,
  320/360/412/768/1280 widths, 200%/400% text stress, 44-pixel controls,
  self-hosted fonts, reduced motion and zero horizontal overflow.
- Accountable review of `docs/evidence/ux01b/hero-{en-IN,kn-IN}-{320,1280}.png`
  remains before closure. Kannada is explicitly development-preview evidence.
- No migration, API, authentication, payment, provider, learner-record,
  navigation, APK or production change. UX-01C remains pending.

## D-0281: UX-01A served-build freshness complete — 6 September 2026

- Soft-launch web builds are now keyed by a deterministic SHA-256 over served
  sources, configuration, lockfiles, shared contracts/design-system sources
  and locale catalogues instead of a permanent existence stamp.
- `make soft-launch-refresh-web` rebuilds only when those inputs change. Vite
  places the same release identity in the service-worker URL, and the worker
  retires only older `global-english-public-shell-*` caches without touching
  protected learner IndexedDB data.
- A controlled source change moved the fingerprint from `a14a9311…d631` to
  `a9d06362…4557` and the entry asset from `index-DRG7SBfg.js` to
  `index-DARKM3lG.js`; an unchanged repeat refresh preserved the same digest.
- Exact pinned `make verify` passed: 249 standard backend tests (2,652
  assertions), 341 PostgreSQL tests (4,774 assertions), 71 web test files (339
  tests), 35 localization tests and 12 design-system tests. `make e2e` passed
  4/4 reconciliation integration tests (103 assertions) and 21/21 Chromium
  journeys; `make build` passed.
- No schema, API, authentication, payment, AI-provider, learner-record,
  navigation, APK or production change. UX-01B is the next bounded step.

## D-0280: UX-00 complete — 6 September 2026

- The Accountable Product Owner reported `Keyboard: passed` for the final
  320-CSS-pixel human smoke.
- Together with D-0278's automated, browser, build and responsive evidence,
  this closes UX-00. Representative Android TalkBack remains a release gate.

## D-0279: Futuristic Learning Studio direction accepted — 6 September 2026

- The Accountable Product Owner rejects continued use of the legacy Academy
  template and selects Futuristic Learning Studio for UX-01.
- The direction is a calm, premium adult AI-learning environment: deep
  ink/navy depth, controlled cyan/teal/violet luminosity, accessible layered
  surfaces, an original disclosed Swathi AI presence and restrained motion.
  It explicitly excludes a recolored school template, low-contrast glass,
  excessive neon, copied competitor trade dress and an autoplay carousel.
- [UI_UX_MASTER_PLAN.md](UI_UX_MASTER_PLAN.md) now defines UX-01A→G. UX-01A
  first repairs served-build/cache freshness so future source changes cannot be
  hidden behind an existing soft-launch `dist` stamp. UX-01B then replaces the
  public top fold; later sub-checkpoints complete the guest journey, sign-in,
  learner frame, route split and closure evidence.
- This is a documentation and sequencing decision. It changes no production
  code, schema, API, payment, authentication, AI authority or navigation.
  UX-00's remaining human keyboard gate is not waived, and UX-02 onward remains
  unauthorized.

## Closed-beta APK published on tunnel origin — 2 September 2026

- `make android-closed-beta` with Temurin 21 (`~/.local/jdk/jdk-21.0.12+8`) and `ANDROID_HOME=$HOME/Android/Sdk`. Gradle `testDebugUnitTest assembleDebug` BUILD SUCCESSFUL (103 tasks).
- Baked origin: `https://serious-writer-associate-interest.trycloudflare.com`. Passport public client `019fdd3f-4bba-720b-9e4e-a771ecccdd7f` (`com.globalenglish.app:/oauth/callback`). Package `com.globalenglish.app` 1.0 (1), targetSdk 36.
- SHA-256 `0f946ec6b2caea217f0824232ad93637d8f15a0678efa42d292fa6f40d382aae` (28,654,905 bytes). Copy: `apps/web/public/downloads/global-spoken-english-android.apk` and `~/ge-soft-launch/artifacts/closed-group/global-spoken-english-tunnel-20260902.apk`.
- Tunnel HEAD `/downloads/global-spoken-english-android.apk` returned `content-length: 28654905`. No Tailscale required while this Cloudflare hostname is live. Rebuild if the tunnel hostname rotates.
- Debug-signed only. Not Play. Physical `adb install` not run (no device attached).

## D-0278: UX-00 automated implementation complete — 6 September 2026

- All nine bounded UX-00 repairs are implemented: local script fonts, WCAG AA
  color pairs, accessible carousel controls, narrow/enlarged-text reflow,
  operator landmarks/headings, explicit entitlement states, current browser
  fixtures and non-root artifact isolation.
- Exact pinned-toolchain `make verify` passed with PHP 8.4.23, Composer 2.10.2,
  Node 24.18.1, pnpm 11.18.0 and PostgreSQL 18.4: 249 standard backend tests
  (2,652 assertions), 341 PostgreSQL tests (4,775 assertions), 69 web test
  files (336 tests), 35 localization tests and 12 design-system tests.
- `make e2e` passed 4/4 reconciliation integration tests (103 assertions) and
  all 21 Chromium journeys. `make build` passed; the existing 1.319 MB
  minified bundle warning is deferred to UX-01 code splitting.
- Responsive visual evidence: [English 320](evidence/ux00/marketing-en-IN-320.png),
  [English 1280](evidence/ux00/marketing-en-IN-1280.png),
  [Kannada 320](evidence/ux00/marketing-kn-IN-320.png) and
  [Kannada 1280](evidence/ux00/marketing-kn-IN-1280.png). Kannada evidence is
  an explicit development preview.
- Human keyboard smoke at 320 CSS pixels remains the only UX-00 closure gate.
  Representative Android TalkBack remains a release gate; UX-01 and later
  checkpoints remain unauthorized.
- New UI copy changed every catalogue hash. The prior D-0274/D-0275 approvals
  remain valid evidence for their exact old revisions but cannot approve the
  new `ui-*-2026-09-05.1` bytes. All five non-English revisions are therefore
  truthfully `pending-independent-review`; approved-only builds fall back to
  English until renewed independent review.

## UX-00: Restore UX quality baseline started — 5 September 2026

- D-0277 accepts [UI_UX_MASTER_PLAN.md](UI_UX_MASTER_PLAN.md) as a delivery companion and authorizes only UX-00.
- Exact scope: fonts, contrast, carousel semantics/controls, 320-pixel reflow, operator landmarks/headings, entitlement states, Playwright contract reconciliation and reproducible test output paths.
- No navigation, payment, schema, APK publication or later UX checkpoint is authorized by this transition.

## D-0276: Wave 6 Indic STT staff tests completed — 5 September 2026

- Automated preflight: `make wave6-indic-preflight` checks Wave 6 env (6.1), Indic DI binding and configuration preflight (6.2), migration `2026_09_01_000300`, 25s cap, health/ready and Indic adapter PHPUnit.
- Staff runbook: [docs/ops/WAVE6_INDIC_STT_STAFF_TESTS.md](ops/WAVE6_INDIC_STT_STAFF_TESTS.md). Completed evidence: [docs/evidence/wave6/wave6-indic-stt-staff-test-result.json](evidence/wave6/wave6-indic-stt-staff-test-result.json).
- Recorded STT writes `voice_provider_runs` with `provider_key=indic-http-stt` (not `ai_cost_ledger`; the ledger CHECK exists for allowlisted keys).
- Staff matrix 6.3–6.9 passed: English 3/3, code-mixed Kannada/English 3/3, noisy room 3/3, 25-second cap, failover, demo meter, Role-play Perform, Azure chat/guidance TTS and provider-run evidence.
- Final preflight on 5 September: 24 pass, 0 warn, 0 fail. Safe tester counts record 50 successful Indic STT runs and 13 successful Azure STT failovers; no transcript or audio is stored in Git evidence.
- This does not set `APP_ENV=production` or authorize Play.

## D-0275: Phase-1 UI catalogues approved — 2 September 2026

- Decision artefacts: APP-0076, D-0275. Governance revisions `ui-ml-IN-2026-09-02.2`, `ui-ta-IN-2026-09-02.2`, `ui-te-IN-2026-09-02.2` and `ui-hi-IN-2026-09-02.2` now have `reviewStatus=approved`, `reviewedBy=S R Bhandary`, `approvedAt=2026-09-02T11:35:00+05:30`, `independentReview=true`.
- With APP-0075 Kannada approval, all five non-English Phase-1 UI catalogues are approved for `approved-only` resolution.
- Soft-launch learner locale picker remains kn-IN + en-IN only under D-0206; approved Malayalam/Tamil/Telugu/Hindi strings are available to API resolution, demo surfaces and future picker expansion.
- Production/public release, Role-play script review and curriculum publication remain unauthorized.

## D-0274: Kannada UI catalogue approved — 2 September 2026

- Decision artefacts: APP-0075, D-0274. Governance revision `ui-kn-IN-2026-09-02.2` now has `reviewStatus=approved`, `reviewedBy=S R Bhandary`, `approvedAt=2026-09-02T11:30:00+05:30`, `independentReview=true`.
- Soft-launch web dist now uses the ordinary `approved-only` build (no `VITE_UI_TRANSLATION_PREVIEW`); Kannada chrome no longer needs a preview build.
- Ordinary `approved-only` web builds resolve Kannada navigation, sign-in, learn, speak, progress and account chrome without the language-fallback banner when `kn-IN` is selected.
- Malayalam, Tamil, Telugu and Hindi UI catalogues remain `pending-independent-review` and still fall back to English under `approved-only`.
- Production/public release, Role-play script review and curriculum publication remain unauthorized.

## Web sign-in failure looked like a no-op — 2 September 2026

- Failed password sign-in POSTs were reaching `/api/v1/auth/password/sign-ins`, but the dedicated `#sign-in` card is taller than a typical viewport. The error sat below Sign in / Forgot password / invitation, mostly off-screen, and the password field was cleared, so the form looked unchanged.
- Sign-in now keeps the password on failure, renders the alert above the submit button, treats a non-authenticated `current()` after a 200 as failure, and uses a Chromium `/v`-flag-safe username `pattern` (`[A-Za-z][A-Za-z0-9._\\-]{3,63}`). Dedicated sign-in uses a smaller tutor and hides the lede on short viewports. Successful sign-in leaves `#sign-in` for `#home`.
- Live `/api/v1/me/entitlement` was HTTP 500 because `live_ai_talk_packs` had not been migrated. `2026_09_01_000310_create_live_ai_talk_packs` is now applied on the soft-launch database.

## Soft-launch operator test accounts restored — 2 September 2026

- `trainer` and `reviewer` were missing after the August PostgreSQL restore. Idempotent provisioning script: `scripts/soft-launch-provision-operator-test-accounts.php` (passwords via `GE_SOFT_LAUNCH_TRAINER_PASSWORD` / `GE_SOFT_LAUNCH_REVIEWER_PASSWORD` only; never commit).
- Both accounts now have Global English Direct trainer memberships, system role packs (`trainer` / `content_reviewer`), and credential-ready password sign-in on the tunnel origin.
- Tunnel verification: `trainer` opens Learning (Content Studio, Media Library, review queues); `reviewer` opens Learning + Safety (review queues, audit read).

## D-0273: Paid live-AI caps and talk packs — 1 September 2026

- Decision artefacts: DR-0047 Option A, ADR-0037, APP-0074, master plan 3.14. OpenAPI **0.40.0**. Privacy inventory `learner-privacy-v8`.
- Modules `live_ai_cap_seconds=2400`. Premium `5400`. Demo remains 300.
- Talk packs `talk_pack_30` / `talk_pack_60` / `talk_pack_180` (₹199 / ₹349 / ₹899 ex-GST) add seconds to the same pool. Require an active calendar-month grant. Expire at `min(membership end, +30 days)`. Do not unlock Role-play.
- Existing Direct membership grants with a null snapshot cap use the named-code defaults. Operator grants cannot replace a membership with a talk-pack plan id.
- Migration `2026_09_01_000310_create_live_ai_talk_packs` (table plus PostgreSQL `billing_period` CHECK including `talk_pack`). Speak/Role-play surface `entitlement.ai_exhausted` / `entitlement.demo_ai_exhausted` instead of a generic Swathi error.

## Wave 7.1: Direct pathway published — 1 September 2026

- `lesson-practice:publish-direct-pathway --approval=WAVE-7.1`: 17/17 latest blueprints `published`, eligible, empty blockers. Prior drafts retained. Replay skipped 17.
- Learn already attached four `lesson_audio` parts per concept; this is the publish action.
- Graph `CurriculumPublisher` was not used (LPL program has no graph).
- Wave 2A was already complete; Indic staff tests remain Wave 6.

## Wave 5: stable host (in progress) — 1 September 2026

- DNS: no A record for `ai.globalspokenenglish.com`. No Cloudflare token on disk.
- Router: WAN 80 is not this app; WAN 443 not forwarded. LAN `192.168.1.4`.
- `make soft-launch-configure-stable` and tunnel-stop **not** run.
- 5.9: `/privacy.html` and `/account-deletion.html` plus in-app Legal links. Operator erasure API unchanged.
- Android APK rebuilt 2 September 2026 against the current Quick Tunnel (not `ai.globalspokenenglish.com`). No adb device.

## Wave 4: backup, observe, rollback — 1 September 2026

- Gzip dump `~/ge-soft-launch/backups/soft-launch-20260901T162027Z.sql.gz` mode 0600; encrypted copy in `~/ge-offsite-backups/`. Temp-DB restore RTO 2s; live DB not overwritten. REL-01 drill also passed.
- `global-english-soft-launch.service` active; worker and scheduler running. Health live/ready ok. Readiness 24/0/0.
- Rollback: [ops/SOFT_LAUNCH_ROLLBACK.md](ops/SOFT_LAUNCH_ROLLBACK.md). STT: `VOICE_PROVIDER_DRIVER=azure`. Previous APK in-app-login-20260901.
- Observe timer every minute; JSONL counts only. This host’s env already has `VOICE_PROVIDER_DRIVER=indic`; Wave 6 staff tests remain open.
- Dummy payments, `APP_ENV=soft-launch`, Quick Tunnel still in use (Wave 5).

## Wave 3: vendor host inventory — 1 September 2026

- Azure OpenAI: `$HOME/ge-conversation-model/secrets/azure-openai.secret` mode 0600; kill switch `CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED`; cap ₹500 (APP-0022).
- Azure Speech: `$HOME/ge-speech-benchmark/secrets/azure-speech.secret` mode 0600; voices Neerja + Sapna; cap ₹500 (APP-0064) until **2026-12-14** (renewed 16 September 2026).
- Sarvam: `$HOME/ge-indic-stt/secrets/sarvam.secret` mode 0600. Driver not flipped. Rotate + DPA + monthly cap still open.
- Payments dummy. Google/Facebook unset until Wave 5. Play Console not opened (`com.globalenglish.app`).

## Wave 2A: Indic STT prerequisites — 1 September 2026

- 25-second hold-to-talk and recorded-STT cap. Learn tap-to-record stays 120 seconds.
- `ai_cost_ledger` CHECK and `ConversationProviderUsagePolicy` allow `indic-http-stt` (`indic-http-stt-v1:`) and Azure Fast Transcription failover `azure-speech-soft-launch-stt`.
- `IndicWithAzureFailoverSpeechToTextProvider` retries once on Azure after Indic HTTP/connection/transport failure.
- Consent notices name Sarvam as the transcription processor and Azure as failover (`voice-processing-notice-2026-09-01`).
- Indic remains off. Secret path is documented in `.env.example` comments only.

## D-0267: Premium flagship and courtesy month — 1 September 2026

- Decision artefacts: DR-0046 Option A, ADR-0036, APP-0073, master plan 3.13. OpenAPI **0.39.0**.
- Premium (`monthly_modules_roleplay`) is the recommended Direct paid SKU. Modules stays ₹350 without Role-play.
- Operators and `php artisan entitlements:grant-courtesy-premium` grant one complimentary Premium month to current Direct learners. Idempotent. Institutional orgs 404.
- Old-website students, passwords and progress are not imported. CSV filter matches usernames already in this tenant.

## D-0266: Direct subscriptions, GST and dummy in-app checkout — 1 September 2026

- Decision artefacts: DR-0045 Option C, ADR-0035, ADMIN-ENT-01, APP-0072, master plan 3.12. OpenAPI **0.38.0**. Privacy inventory `learner-privacy-v7`.
- Direct only. Institutional orgs stay invite-based and unmetered.
- Demo: Level 1–2, 48 hours from first sign-in, inclusive 300-second live-AI cap. ₹350 unlocks all levels without Role-play. ₹500 adds Role-play.
- Dummy GSTIN `29AAAAA0000A1Z5` / 18% until replaced on the operator tax page. Checkout stores the GST snapshot; later GSTIN changes do not rewrite it.
- Operator Subscriptions page: GSTIN, plans, grants, demo clock/meter restart, sign-in throttle clear, revoke. Marks and evidence are not wiped.

## DR-0045: Direct subscription commercial contract — 1 September 2026

- Catalogue: [SUBSCRIPTIONS.md](SUBSCRIPTIONS.md). DR-0045 Option C recorded then implemented as D-0266.
- Confirmed: first 2 modules = Level 1 and Level 2; demo = 48 hours from first sign-in; prices GST exclusive.
- Demo is free, with one inclusive **5-minute (300s)** cap across speech, Role-play and AI chats. Dummy GSTIN `29AAAAA0000A1Z5` / 18% until the owner replaces it on the admin tax page.
- Implemented in this checkpoint (schema, meter, dummy checkout, learner paywall, operator page). Play Billing remains unauthorized.

## D-0264: Google and Facebook extra sign-in — 1 September 2026

- Decision artefacts: DR-0044, ADR-0034, master plan 3.11, APP-0071. OpenAPI **0.37.0**.
- Social never creates accounts. Linked Google/Facebook issue the same web session or Passport family (`federated_sign_in`).
- Android federated authorization uses origin-pinned Custom Tabs. Password stays in-app. Tokens are exchanged from a single-use ticket, not the custom-scheme URL.
- Verified: SQLite `FederatedSignInTest` 6/6 (85 assertions), web typecheck, `openapi:check`, 72 focused web tests, Pint and PHPStan 0 on new PHP. PostgreSQL not run on this host (no `pdo_pgsql`). Closed-beta APK not rebuilt.

## D-0263: In-app Android username/password login — 1 September 2026

- Decision artefacts: DR-0043, ADR-0033, master plan 3.10, APP-0070. OpenAPI **0.36.0**.
- First-party mobile password, mode, recovery and invitation routes. Family grant name `password_sign_in`.
- Android adapter posts those routes over CapacitorHttp and stores only the refresh token in EncryptedSharedPreferences.
- Guest header and marketing Sign in go to `#sign-in`. Custom Tabs is unused for guest sign-in.
- Verified: SQLite `MobilePasswordSignInTest` 7/7, `MobileOAuthPolicyTest` 9/9, web typecheck, `openapi:check`, 53 focused web tests, Pint and PHPStan 0 on new PHP. PostgreSQL not run on this host (no `pdo_pgsql`).
- Closed-beta APK: `make android-closed-beta` BUILD SUCCESSFUL (103 tasks). Artifact `~/ge-soft-launch/artifacts/closed-group/global-spoken-english-in-app-login-20260901.apk`, SHA-256 `aa94cacc3f4e244a9a258aee4f62e345f92bac6654ff3c22140678282213cca0`. Old binaries still open Chrome.

## DR-0038 C: Speak hold-to-talk latency — 30 August 2026

- Client loop now logs `record_stop` → `wav_ready` → `upload_done` → `transcript_ready` → `turn_json_ready` → `first_audio` as millisecond durations only (no transcript, audio, or PII). Dev overlay is `aria-hidden`.
- Hold UI shows listening / processing / preparing / speaking (§7.4) with a live waveform while held. Cancel aborts the in-flight upload and turn (`AbortSignal`).
- PCM with audible speech encodes WAV immediately and does not wait for the backup MediaRecorder `stop`. Reply audio plays when the turn JSON arrives, in parallel with `GET .../events`. Sanctum CSRF is reused after the first fetch on that API instance.
- Type-instead and low-confidence “Swathi heard” retry are unchanged. No new provider, no ledger change, no consent-flow change.
- Client-only synthetic release-to-first-audio on this machine (21 samples, 80ms recorder wait + 40ms events RTT model): **p50 125→43ms, p95 127→44ms**. Live Azure STT/model/TTS was not measured and is unchanged.
- Verified: `pnpm run lint`, `pnpm run typecheck`, `pnpm run test` (295 web tests), `pnpm run locale:check`. PHP not touched.

## CI `verify` e2e failures fixed locally — 30 August 2026

- CI run 33314026223 on PR #3 cleared PHPStan and web lint but failed `make e2e` with 12 Playwright failures. Root causes, all fixed in `745ab6c` and `9818fe2` with the full 21-test suite passing at `--workers=1` (CI's configuration): (1) brass eyebrow text `#8f6f28` failed WCAG AA contrast at 13px bold on light surfaces — token darkened to `#7d6124` (≥4.5:1 on surface, panel and notice backgrounds); (2) `.operator-console__notice` inherited the dark heading panel's light text onto its own light notice background (1.18:1) — explicit ink colour added; (3) the e2e `home()` stub predated checkpoint A's `streak`/`due_review` fields, so the Home render threw and three learner journeys timed out — stub updated; (4) headless recording had no capture device (`playwright.config.ts` passed the non-existent `--use-fake-device-for-media-capture` flag) — the spec now synthesizes an oscillator `MediaStream` for `getUserMedia`, the config carries the real `--use-fake-device-for-media-stream` flag, recording press windows widened to 1200ms for the 4096-frame PCM buffers, and the stale `audio/webm` receipt-mime assertion accepts the canonical opus-tagged variant; (5) marketing landing overflowed horizontally at 320px/400% enlarged text (grid children `min-width:auto`, rem paddings) — `min-width: 0`, `overflow-wrap: anywhere` and px card padding at narrow widths; (6) the invited-account journey never stubbed `/sanctum/csrf-cookie`, which the credential adapter requires — stub plus XSRF cookie added, matching the operator-shell pattern; (7) the header sign-in link toggles its href on state and React flushed the click's state update before navigation, so clicking navigated to the flipped target — the handler now owns the navigation explicitly.
- Home e2e journeys reference the new `learner.home.continue` label from the flagship Home commit (`d3697b6`). Commits `d3697b6`–`9818fe2` are pushed.
- CI run [33317082476](https://github.com/srbhandary1/GESC/actions/runs/33317082476) passed all four jobs (`verify` 9m15s, `android`, `operations`, `release-gate`). Full `make verify` inside `verify` exercised Pint, PHPStan at zero, SQLite and PostgreSQL integration suites (including ADR-0028 cross-tenant and mastery-evidence constraint tests), contract and locale validation, lint, typecheck, workspace tests, 21 Playwright journeys, and build. Checkpoint A is accepted per D-0220.

## DR-0038 B: Progress read-model API — 30 August 2026

- `GET /api/v1/me/progress-dashboard` returns organization-scoped mastery by objective, speaking activity (meaningful speaking days + completed turn digests/durations), first-attempt vs retry improvement, delayed-review performance, and recurring-error trends. No schema changes; advisory AI values never appear as marks.
- OpenAPI **0.35.0** and generated contracts updated. Feature tests cover auth and contract shape on SQLite; PostgreSQL cross-tenant isolation test is in `ProgressDashboardTenancyTest` (CI only — this host has no `pdo_pgsql`).
- Progress tab UI (`#progress`) still renders `LevelPathPanel`; that remains the next B slice.

## Checkpoint A verified — 30 August 2026

- Acceptance evidence: GitHub Actions run [33317082476](https://github.com/srbhandary1/GESC/actions/runs/33317082476) on PR [#3](https://github.com/srbhandary1/GESC/pull/3) at `9818fe2`. Recorded as [D-0220](DECISIONS.md); quality review in [QUALITY_REVIEW.md](QUALITY_REVIEW.md#dr-0038-a-daily-loop--30-august-2026).
- Delivered: meaningful-speaking-day projector and streak reader, deterministic delayed-review scheduler, migration `2026_08_30_000270_create_daily_mission_home_read_models`, Home API `streak` and `due_review`, regenerated contracts, localized flagship Home UI (`LearnerJourney`), privacy-inventory revision, and ADR-0028 PostgreSQL constraint coverage.
- B and C remain unstarted. Production/Play, curriculum publication, and independent Kannada UI review stay unauthorized.

## Checkpoint A implemented, verification blocked — 30 August 2026

- Added `MeaningfulSpeakingDayPredicate`, `MeaningfulSpeakingDayProjector`, `MeaningfulSpeakingStreakCalculator`, `MeaningfulSpeakingStreakReader`, `DelayedReviewScheduler`, migration `2026_08_30_000270_create_daily_mission_home_read_models`, `config/review.php` (provisional intervals `[1, 3, 7, 14]`), Home API fields, regenerated contracts, localized Home UI, and a privacy-inventory revision.
- The projector reads only `completed_at`, `assistant_reply_digest` and `failed_stage`, is organization-scoped, uses `insertOrIgnore` against a unique constraint, and persists the resolved timezone. `organizations.timezone` already exists with an `Asia/Kolkata` default, so the in-transaction lookup cannot fail the way the 29 August ledger CHECK did.
- **`make verify` has never run for this checkpoint.** `scripts/check-toolchain.sh` compares `.php-version` (8.4.23) against the installed runtime; this host has only PHP 8.5.4 and no 8.4 binary, so the gate exits before any check. This is pre-existing and unrelated to checkpoint A.
- **No integration test has run on this host.** `tests/Integration` is registered only in `phpunit.postgres.xml`, and PHP 8.5.4 here has no `pdo_pgsql` extension, so every integration case errors with "could not find driver". Any earlier report of passing integration tests for this checkpoint could not have executed. CI pins PHP 8.4.23 and is the only place these run.
- Runnable locally: `tests/Unit` + `tests/Feature` = 206 tests, 201 passed. The 5 failures are pre-existing OTP/auth 401s (`AccountSetupRecoveryTest`, `InviteAwareOtpAuthenticationTest`, `WebSessionPolicyTest`) on the password-only branch; none touch the checkpoint A path. Pint passes.
- **Two ADR-0028 test areas were missing and are now written:** active cross-tenant rejection, and proof that a review occurrence cannot be created without deterministic mastery evidence. Idempotency of replayed mastery events was already covered inside the PR-006 lifecycle test. Both new blocks are guarded to `pgsql` because they assert a Postgres trigger and composite foreign keys, so they are exercised only in CI.
- `ConversationTurnService` gained only the projector hook. The guidance-TTS synthesis and second `ai_cost_ledger` row in that file are pre-existing 29 August work (migration `2026_08_29_000250` predates this slice); checkpoint A added no provider call and no spend.
- Stale expectations in `ConversationFakeProviderTest` and `CurriculumPublicationLifecycleTest` were updated to match that pre-existing guidance-TTS behaviour. A pre-existing `storage/.../voice` permission denial still affects the curriculum lifecycle test.
- First CI run on PR [#3](https://github.com/srbhandary1/GESC/pull/3) (run 33308251724): `operations` pass, `android` pass, **`verify` fail**, `release-gate` fail as a dependant. `verify` stopped at PHPStan with 50 errors, at `Makefile:148`. PHPStan runs before any test phase, so that run never executed tests.
- PHPStan is now **0 errors** locally (independently re-run after the cleanup). Pint passes. SQLite PHPUnit remains 206 tests / 201 passed / 5 pre-existing OTP-session 401s.
- Root cause of 38 of the 46 remaining errors: `LessonPracticeBlueprint` had no `@property` docblock, so Larastan typed `canonical_json` as `string` (the migration's `$table->json()`). `LevelSessionDeliveryService` was never wrong; it was not edited.
- Latent runtime bug fixed: `bootstrap/app.php` called `env('TRUSTED_PROXIES')` inside `withMiddleware`, which runs before the environment file loads, so a `.env` value was silently ignored. Proxies now come from `config/trustedproxy.php` at request time. Default remains `127.0.0.1,::1`. **If a live `.env` already has a broader `TRUSTED_PROXIES`, that value will start taking effect.**
- **Superseded (30 August 2026):** CI run [33317082476](https://github.com/srbhandary1/GESC/actions/runs/33317082476) passed full `make verify`, including the Integration suite and both pgsql-guarded ADR-0028 constraint tests. Checkpoint A is **accepted** per D-0220; see "Checkpoint A verified" above.
- A reconciled product brief ("Master Planning Brief: Phase 1 – Reconciled") was received and folded into DR-0038 as an addendum. It confirms the APP-0069 / D-0219 decisions and changes no accepted decision; the addendum adopts its claim-grounding rules and strict A→B→C sequence, and corrects its Sarvam line against D-0217 / ADR-0027 §6.
- A third product-owner specification ("Phase 1 – Multi-Language Ready") arrived on 30 August. Its reappearing Flutter / Miss Nova / attendance / 19-level / Notice Board items were confirmed by the product owner as stale template text; D-0219 is unchanged. Its five genuinely new items — advisory pronunciation colour map, viseme-driven animated tutor, speech-speed control, Kanglish transliteration, and te/ta/ml activation gates — are drafted as [DR-0039](DR-0039_PRONUNCIATION_AVATAR_PACING_TRANSLITERATION_LANGUAGES_REQUEST.md), proposed, implementation sequenced after DR-0038 A/B/C are verified.

## DR-0038 acceptance: daily loop, Progress, Speak latency — 30 August 2026

- A product-owner "Master Orchestration Specification (Phase 1 – Revised)" proposed a Flutter rewrite, Sarvam/GPT-4o/ElevenLabs as named architecture, 19 levels with two pre-levels, an attendance percentage gauge, a Notice Board, a teacher rename to "Miss Nova", and a compute-profile orchestration protocol. DR-0038 separated it into seven decidable items.
- **Accepted (APP-0069 / D-0219):** A — Daily Mission Home with a meaningful-speaking-day streak and deterministic delayed review; B — Progress conforms to master plan §7.3 and stops rendering the Learn level path; C — Speak latency pass with no new provider.
- **Confirmed:** client stays React PWA + Capacitor 8 (Flutter/iOS deferred, §9.2); levels stay generic and Levels 2–17 keep APP-0065/0066/0067 authored content; no attendance percentage in canonical records; Notice Board deferred; teacher remains Swathi.
- **Not accepted:** DR-0038 E (Sarvam driver flip stays under D-0217; ElevenLabs deferred pending a costed comparison against Azure `kn-IN-SapnaNeural`), and the compute-profile orchestration protocol.
- ADR-0028 amends master plan **§7.2** only. DR-0038 had claimed §7.1; that was wrong and is corrected in APP-0069, ADR-0028 and the DR itself. §7.3 already required the Progress contract, so B is conformance rather than an amendment.
- The proposed `LegacyAudioSyncService` pulling from `globalspokenenglish.com` is not authorized. Teaching audio was re-ingested on 29 August from local object storage only, under the ADR-0016 rights path.
- Master plan is version 3.9. No product code changed in this slice.

## Guidance TTS cost ledger + Swathi identity — 29 August 2026

- Speak/Learn hold-to-talk failed after STT with `conversation.error` (“Maya is unavailable”) because `POST .../turns` returned HTTP 500. Azure OpenAI and guidance TTS succeeded, then inserting `ai_cost_ledger` for `azure-speech-guidance` violated `ai_cost_ledger_values_check` (APP-0064 TTS was never added to the CHECK). The turn transaction rolled back, so no reply was stored.
- Migration `2026_08_29_000250` allowlists `provider_key = azure-speech-guidance` with `model_version` prefix `soft-launch-guidance-tts-v1:`.
- Android turn-event SSE now prefixes the baked API origin when `events_url` is a relative `/api/v1/...` path.
- D-0218: the on-screen teacher is Swathi end-to-end (prompts, Azure output contract, UI catalogues `ui-*-2026-08-29.6`). Role-play scene characters are unchanged. Illustration files may remain `tutor-maya.png`.
- Master plan §7.1 Speak wording is version 3.8.

## Android voice answers + lesson question after audio — 29 August 2026

- Capacitor keeps WAV uploads only when the FormData part is a named `File` and native fetch still sees `init.body` as FormData. An unnamed `Blob` becomes `[object Blob]`; wrapping FormData in a `Request` first turns it into a stream that Capacitor UTF-8-decodes.
- After Introduction audio, Maya asks a lesson question from the blueprint (`Can I use your pen?`, not the internal “Ask one Can I…?” coach note) and waits for a spoken answer. If the English needs work, the correction block shows clearer English plus the Kannada (or UI-locale) why. Marks and mastery stay unchanged.
- Azure Speech STT/TTS is the voice path. Azure OpenAI from this Ubuntu host is reachable (allowlist confirmed 29 August 2026).

## Android lesson-audio playback — 29 August 2026

- Learn session players used a native `<audio src="/api/v1/me/levels/.../audio">`. On Android that request hits the Capacitor WebView origin without a Passport bearer, so the play control stayed disabled.
- Level-session audio now uses `ModelAudioPlayer` and, on Android, downloads bytes through the authenticated fetch adapter into a revocable blob URL (same private-media boundary as model audio and lesson video). Web/PWA still uses cookie same-origin media.
- Path allowlist is `/api/v1/me/levels/{n}/sessions/{part}/audio` only. Native `<audio controls>` is no longer the Learn player.

## Five-destination nav + Indic STT port — 29 August 2026

- DR-0037 **B** accepted: primary nav is Home · Learn · Speak · Progress · 1:1. Learn is the four-session level path. Speak is the hold-to-talk room. 1:1 is the waitlist plus optional institute contact links from env. `#practice` aliases Speak. Master plan §7.1 is version 3.7 (ADR-0026 / D-0216).
- Indic recorded STT HTTP adapter is implemented behind `RecordedSpeechToTextProvider` (`VOICE_PROVIDER_DRIVER=indic`, translate-to-English). Soft-launch stays Azure. No live Indic keys. ADR-0027 / D-0217.
- UI catalogues `ui-*-2026-08-29.5`. OpenAPI `0.33.0`. Kannada remains `pending-independent-review`.

## Speak room hold-to-talk — 29 August 2026

- Practice tabs are **Levels | Speak**. Speak is everyday talk with Maya plus a situation picker. Confidence Conversation and Role-play are no longer sibling tabs.
- After one session consent, a single hold button records; release uploads, auto-accepts a confident transcript, and sends the turn. Low-confidence results still show what Maya heard for fix/retry. Type instead remains.
- Talk and Perform start immediately. Warm-up, Listen, Observe, and Rehearse are optional “Hear an example” details, not gates.
- Four-session names stay Introduction → Practice → Conversation → Interaction (hear the rule → short drills → guided talk → free speech). Later review stays Progress.
- ADR-0007 per-turn submit is amended by D-0215. DR-0037 A is accepted; B (1:1 last nav item) is not.
- UI catalogues `ui-*-2026-08-29.4`. Kannada remains `pending-independent-review`. Copy is original Maya phrasing, not SuperNova branding.

## Maya identity + disclosure copy — 29 August 2026

- Talk-stage Azure replies could invent a personal name (heard as Swathi) because `friend.turn.v1` only said “patient Friend coach” while the UI teacher is Maya. Active prompts now pin Maya and forbid any other name. Azure output contract requires `assistant_reply_en` spoken as Maya.
- Duplicate “About this AI Friend” came from a visually hidden heading plus the same summary text. Disclosure is now “About Maya”; product copy names Maya; voice consent steps are unchanged.
- UI catalogues `ui-*-2026-08-29.3`. Kannada remains `pending-independent-review`.
- PostgreSQL integration PHPUnit inherited live `DB_*` values via `$_SERVER` even when `phpunit.postgres.xml` set `global_english_test`. That refreshed the live schema during this slice. Live data was restored from `~/ge-soft-launch/backups/soft-launch-20260813T060939Z.sql.gz`, migrations `000200`–`000240` were reapplied, APP-0062–APP-0067 and Level 1 were recommissioned from local object storage (no legacy site), credentials were re-established for `admin` / `northstar` / `srbhandary`, and a new snapshot was written at `~/ge-soft-launch/backups/soft-launch-20260829T073234Z.sql.gz`. `tests/bootstrap.postgres.php` now forces the test database into putenv/`$_ENV`/`$_SERVER`, and tests refuse to boot against `global_english`.

## Teaching-audio restore + sign-in page — 29 August 2026

- Levels 2–17 teaching MP3s were still in local object storage after the 13 August database restore. They were copied from that store only (no legacy-site access), re-ingested under APP-0062 (63 accepted, 4 Level 1 hashes already present), then commissioned APP-0063–APP-0067. Level 1 was re-attached from the existing APP-0047 assets. Live DB now has 67/67 inventory hashes and all 17 levels available on Practice → Levels path.
- `http://127.0.0.1:5173/#sign-in` could render as an empty main area when the shell enter animation froze at opacity 0 (`animation-fill-mode: both`) or when a stale Vite HMR tab failed to reload modules. The shell content now stays visible if the animation does not run, the hash no longer targets a colliding `id="sign-in"`, and the dedicated sign-in view scrolls to the top.

## Learner tutor UI + in-session teacher — 29 August 2026

- Local Vite sign-in (`127.0.0.1:5173`) failed because the live API treated that origin as non-stateful and used `__Host-` Secure cookies. Soft-launch now includes `127.0.0.1:5173` in Sanctum stateful domains and uses a non-`__Host-` session cookie so password sign-in works on loopback HTTP; the HTTPS tunnel origin remains listed.
- Username HTML `pattern` is Chromium `/v`-flag safe (`[A-Za-z][A-Za-z0-9._\\-]{3,63}`).
- Product owner required a Super Nova-class animated learner app: live role-play, mistake help in the chosen language, and a mock teacher after voice training — without stopping for extra process.
- Maya is the on-screen teacher (original character art). Home, levels, conversation and role-play share pulse/orbit motion, chat bubbles, and a large speak control. Android uses the same web UI plus a dark status/navigation bar.
- After Introduction/Practice audio ends, the bound tutor session starts in place (no tab jump). Practice MCQ is an optional details check; it still gates Practice complete in the API.
- UI catalogues `ui-*-2026-08-29.1`. Kannada remains `pending-independent-review`.

## Soft-launch sign-in restore — 15 August 2026

- Live Postgres had been emptied (schema replayed as migration batch 1; `users` = 0). Password POSTs were CSRF-complete and recorded `password.sign_in` failures with `known_user: false`.
- Restored `~/ge-soft-launch/backups/soft-launch-20260813T060939Z.sql.gz` after a safety dump of the empty database, then applied migrations `000200`–`000230`. Authentication mode is `password_only` again (two username/password credentials).
- Lesson-practice rows from APP-0063–APP-0067 (14 August) are not in that backup. Role-play catalog was re-seeded. Levels 2–17 private drafts need the existing commission commands if those lessons must return.
- Marketing landing now uses owned Global Academy photos from https://globalspokenenglish.com in place of Unsplash tiles. Legacy blog/news images were not imported.
- Sign-in page now has a Home close control back to the marketing landing.
- After the 13 August restore, owner-provided passwords for the two username accounts were reset through `PasswordCredentialService::resetPassword` (argon2id). The secrets are not stored in the repository.

## APP-0068 — Neural listen voice + playable recording — 14 August 2026

- Warm-up, Listen, Observe and Rehearse now call `POST /conversation-sessions/{id}/speech` so Azure `en-IN-NeerjaNeural` speaks those lines. The phone `speechSynthesis` voice is no longer the product path.
- Azure SSML uses a slightly slower `prosody rate="0.92"` for a more natural read. Friend replies already used neural WAV when TTS succeeded.
- Microphone capture records PCM and reviews a WAV. The previous MediaRecorder blob could look recorded but play as silence.
- OpenAPI `0.32.0`.

## APP-0068 — Soft-launch voice-first learner STT — 14 August 2026

- APO stated typed text is only on request and everything else should be voice based.
- Conversation and Role-play default to speaking. Type instead reveals the text box.
- Warm-up, Listen, Observe, Rehearse and Friend replies auto-play spoken English. Transcript correction typing is behind Type instead.
- Listen/warmup/Role-play model lines use Azure neural WAV (`POST /conversation-sessions/{id}/speech`). Device `speechSynthesis` is not the product voice.
- Microphone capture records PCM and reviews a WAV so local playback is audible.
- Soft-launch may bind Azure recorded STT for the Direct organization allowlist. Production stays off.
- Live enablement is invite-only Direct (`VOICE_ALLOWED_ORGANIZATION_IDS` for `01KYS97G7MX374P303XJHDXT72`) on the APP-0064 Speech resource. Production/Play `.env.example` remains `VOICE_UPLOAD_ENABLED=false`.
- Live TTS→STT roundtrip on the same Speech resource returned a non-empty English transcript (`stt_contains_market: true`, confidence 9936 bps). A consented browser-microphone learner upload was not captured.
- Microphone capture now stops Friend/listen playback before `getUserMedia`, retries a busy mic once, and accepts browser MIME suffixes such as `audio/mp4;codecs=mp4a.40.2`.

## APP-0067 — Named Kannada review of authored Levels 2–17 — 14 August 2026

- The Accountable Product Owner stated `Level 2-17 Kannada approved.`
- Direct drafts are revision 4 with APP-0067 Kannada review metadata (evidence `docs/evidence/lpl-01/levels-2-17-kannada-review-result.json`; 16 levels; `kannada_independent_review: true`). Publication remains blocked by `curriculum_publication_gate`.
- Kannada UI catalogue is still `pending-independent-review`. Role-play remains `synthetic_unreviewed`.

## Honest listen-then-type copy + Kannada UI preview — 14 August 2026

- Soft-launch Caddy was serving an ordinary `approved-only` `dist/`, so choosing Kannada still showed English chrome. `scripts/run-soft-launch.sh` now builds with `VITE_UI_TRANSLATION_PREVIEW=true` and stamps `apps/web/dist/.soft-launch-ui-preview`. Production/Play builds stay approved-only (D-0210).
- Conversation / Role-play / marketing no longer advertise microphone speaking as available. That honesty copy was later superseded for invite-only Direct by APP-0068 voice-first STT; production/Play remain off.
- Kannada UI catalogue remains `pending-independent-review`. Named Kannada *lesson* review of Levels 2–17 `kannada_summary` is recorded under APP-0067.

## APP-0066 — Named English academic review of authored Levels 2–17 — 14 August 2026

- The Accountable Product Owner stated `Levels 2–17 English approved`.
- Direct drafts are revision 3 with APP-0066 English review metadata (evidence `docs/evidence/lpl-01/levels-2-17-english-review-result.json`). Kannada `independent_review` remains false. Publication remains blocked.

## APP-0065 — Authored levels, Role-play scripts, 1:1 request — 14 August 2026

- Levels 2–17 lesson text is authored in `AuthoredFoundationLessons` (not APP-0063 templates). Live Direct drafts were replaced on 14 August 2026 (16 levels, revision 2; evidence `docs/evidence/lpl-01/levels-2-17-authored-replace-result.json`). Named English review is recorded under APP-0066; Kannada independent review remains outstanding.
- Role-play still discloses `synthetic_unreviewed`. Each of the 12 scenarios now has Observe model lines, Rehearse chunks and a Perform prompt.
- Marketing no longer says “hire”. Signed-in learners submit `/me/tuition-requests`; operators with `learners.read` see and acknowledge the queue. No payments, calendar or trainer marketplace.
- Privacy inventory is `learner-privacy-v5` (tuition request text is redacted on erasure). OpenAPI `0.32.0`.

## Release-gate repair — 14 August 2026

- Four sessions are ordered in the UI and API (`session_locked`); Practice complete requires a passing check.
- Level-bound AI Tutor hands the created session into Confidence Conversation instead of starting a second generic Friend.
- Authenticated Home shows the daily mission; Progress shows the levels path.
- Role-play Perform requires a typed learner turn; reflect no longer shows evidence digests.
- Practice tabs are semantic `role="tab"` / `tabpanel`. Sign-in username uses a Chromium-valid pattern, duplicate `#sign-in` IDs removed, header Sign in focuses the form.
- Missing `operator.password.confirm_hint` restored. Kannada production fallback notice is explicit; catalogue is not marked approved.

## Learner marketing four-session path + pedagogy — 14 August 2026

- Level session HTTP hosts no longer call `crypto.randomUUID()` (plain LAN/static-IP HTTP). English UI shows English lesson copy; Kannada stays behind a disclosure.
- New “What you get here” tiles: hybrid AI + human courses, in-app 1:1 tuition, one path with two kinds of support. No competitor names.
- Licensed Unsplash photographs in `apps/web/public/marketing/features/` with attribution.
- Web chrome uses `logo-web.png`; native Android uses `logo-android.png` (APP lockup).
- Guest primary Learn/Practice/Progress nav is hidden until sign-in.

## Learner UI locale + chrome polish — 14 August 2026

- `INTERFACE_LOCALES` = kn-IN + en-IN (other Phase-1 catalogues retained for API/tests only).
- Marketing landing fully localized; sign-in lives in the join section.
- Level path / practice surface strings move through localization catalogues.
- Design tokens: smaller `--ge-font-size-*` / hero sizes; quieter practice tabs + landing motion.

## Soft-launch guidance TTS + Role-play catalog — 14 August 2026

- APO exact instruction authorized Kannada+English guidance TTS under soft-launch
  and ≥10 additional common Role-plays (APP-0064 / D-0205).
- Soft-launch DI binds `SoftLaunchGuidanceAzureTextToSpeechProvider` when
  `CONVERSATION_GUIDANCE_TTS_LIVE_REQUESTS_ENABLED=true`; failures fall back to
  text-first. Turn speech may include model/guidance WAV base64.
- Role-play catalog: 12 scenarios (clinic + 11 common). Seed via FoundationSeeder
  or `php artisan conversation:seed-roleplay-catalog`.
- Production `CONVERSATION_PROVIDER_DRIVER=disabled` and guidance TTS off posture
  unchanged for real production promote / Play.

## Live Azure conversation enablement — 14 August 2026

- APO instructed: enable Azure under ₹500 and stop using fake conversation
  providers for the active non-production stack.
- Driver: `azure-openai-dev`; kill-switch on; spend evidence APP-0022
  `cap_inr=500`; `LIVE_AUTHORIZATION.txt` set to the ₹500 phrase.
- Verified as `northstar`: conversation-session **201**, turn **201**,
  `priority_correction.explanation_locale=kn-IN` with Kannada
  `explanation_l1`; clinic reception role-play session **201**.
- Speech for ordinary Confidence Conversation now uses soft-launch guidance TTS
  under APP-0064 when enabled (`SoftLaunchGuidanceAzureTextToSpeechProvider`),
  with text-first fallback. Confirmed-text STT remains; full learner STT DI is
  still separately gated.
- Production `CONVERSATION_PROVIDER_DRIVER=disabled` posture unchanged for
  real production promote / Play.

## Levels 2–17 private auto-commission — 14 August 2026

- Accountable Product Owner authorized APP-0063 / DR-0034 / D-0204:
  auto-create blueprints, provisional English + Kannada approvals, and attach
  four APP-0062 `lesson_audio` parts per level for Direct private pilot use.
- Evidence:
  [levels-2-17-private-commission-result.json](evidence/lpl-01/levels-2-17-private-commission-result.json)
  (`levels_commissioned: 16`; Level 1 already attached under APP-0061).
- Learner delivery verification: all 17 levels `available`.
- Quality debt (must fix before publication): template objectives/checks,
  provisional Kannada summaries, placeholder metadata transcripts where
  needed, Level 15 Practice reuses the combined Introduction/Practice asset.
- Curriculum publication and production/public release remain unauthorized.

## Closed checkpoint — PR-011 evaluation harness and memory controls

- Accepted under [APP-0020](APPROVALS.md#app-0020) at
  `220628f4d4c69284af0b1f2534e8902f3b272088` with recorded limitations
  ([PR-011_APO_ACCEPTANCE_PACKAGE.md](PR-011_APO_ACCEPTANCE_PACKAGE.md)).
- Delivered: learner-visible `/me/ai-memory` CRUD + audit; Practice
  `AiMemoryPanel`; context allowlist caps (5×200); hash-locked
  `ai:evaluate` suite with human-review export/import and zero-cost fake
  comparison report; D-0090 / D-0091.
- Branch artifact: `codex/pr-011-evaluation-memory`. Real model providers,
  auto-ingest of conversation into memory, production and Play remain
  unauthorized by that acceptance.
- Re-verified on 14 August 2026 on `codex/confidence-conversation`:
  `LearnerMemoryTest` + `AiEvaluationRunnerTest` (11 passed),
  `AiMemoryPanel` vitest (2 passed), `artisan ai:evaluate`
  (`passed_count=15`, `estimated_cost_microunits=0`).

## Legacy teaching-audio inventory — 14 August 2026

- Owner-designated legacy admin was used read-only to inventory all 17 Audio
  lessons. Credentials and object URLs are not stored in the repository.
- Hash-only evidence:
  [levels-1-17-teaching-audio-hash-inventory.json](evidence/lpl-01/levels-1-17-teaching-audio-hash-inventory.json)
  — 67 unique MP3s / ~300 MB; Level 1 matches APP-0047 exactly.
- Bytes remain in ephemeral local `/tmp` staging only. No Git binary commit of
  media was performed.
- [DR-0033](DR-0033_LEVELS_2_17_TEACHING_AUDIO_PROCESSING_REQUEST.md) Option A
  is accepted under APP-0062 / D-0203. Exact-hash private non-production
  `lesson_audio` ingest completed for the inventory: 4 Level 1 hashes skipped
  (already present under APP-0047), 63 new assets accepted, 63 APP-0062 Direct
  pilot rights confirmations recorded. DB verification: 67/67 inventory hashes
  present as `audio/mpeg` `lesson_audio` with approved program-scoped rights on
  program `01KZNK3BWD8A8NJ5A5JW0ZVZMT`.
- Ingest evidence (no credentials, no media bytes):
  [levels-2-17-lesson-audio-ingest-result.json](evidence/lpl-01/levels-2-17-lesson-audio-ingest-result.json).
- Attachment, publication, production/public release, and Assignment audio
  remain excluded until their own gates.

## Level 1 private four-session attachment — 14 August 2026

- Under APP-0061, Direct Level 1 concept `can` is now a private unpublished
  draft with English + Kannada reviews approved and all four APP-0047
  `lesson_audio` assets attached.
- Evidence:
  [level1-private-attachment-result.json](evidence/lpl-01/level1-private-attachment-result.json),
  [level1-attachment-readiness.json](evidence/lpl-01/level1-attachment-readiness.json)
  (`state: private_draft_attached`).
- Learner delivery reports Level 1 `available` with four sessions; Levels 2–17
  remain `commissioning_pending` until blueprints/reviews exist.
- Private pilot delivery now streams lesson audio from
  `GET /api/v1/me/levels/{n}/sessions/{part}/audio` (session-scoped, not
  enrollment/mission media gates) and starts bound free-speech / AI Tutor
  sessions when `CONVERSATION_PROVIDER_DRIVER=fake` in local. Verified as
  learner `northstar`: audio HTTP 200 / playable MPEG; conversation-sessions
  HTTP 201; UI handoff to Confidence Conversation.
- Curriculum publication and production/public release remain unauthorized.

## All-levels four-session learner journey — 14 August 2026

- Product-owner accepted DR-0032 **Option A** under APP-0061 / D-0201 /
  ADR-0025 and authorized a single continuous implementation stroke.
- Master plan version **3.6** records Introduction → Practice → Conversation →
  Interaction for every configured level.
- Implemented in this stroke:
  - operator `lesson-practice` blueprint create/review/attach APIs;
  - learner `/me/levels` catalog and session delivery with deterministic Check
    scoring and participation completions;
  - Levels 1–17 shells (`commissioning_pending` until attached);
  - Practice UI `LevelPathPanel` as the default practice surface;
  - OpenAPI `0.30.0` + generated contracts;
  - SQLite and PostgreSQL `LevelSessionDeliveryTest` (3 tests / 12 assertions);
  - web `LevelPathPanel` vitest.
- Blanket development waiver does **not** invent Levels 2–17 media, authorize
  production/public release, AI mastery, auto-memory ingest or removal of
  tenant/security controls. Exact Level 1 asset attachment in the live Direct
  database and curriculum publication remain separate operator actions.

## One-login role-group operator authorization — 14 August 2026

- The product owner approved the immediately preceding one-login proposal and
  expressly asked to remove the repeated enterprise-style authentication
  ceremony in favor of username/password and role-based groups. APP-0060,
  D-0193 and ADR-0023 record the exact contextual approval and accepted active-
  session risk; master-plan version 3.5 is canonical.
- Routine learner/enrollment, branch/cohort, trainer/appointment, content,
  publication, Media Library, transcription reconciliation, privacy and
  session-revocation routes no longer use `auth.password-confirmed`. Every
  route remains first-party Web only, selected-organization scoped and subject
  to exact command-time role/permission and resource checks.
- Nine administrator-authority routes retain current-password confirmation:
  staff invitation issue/revocation, role-group create/update/retire, staff
  role assignment/revocation and staff membership activation/deactivation.
  The two platform account-recovery routes retain their exact capability plus
  fresh confirmation. Credential changes still verify the current password.
- The user-facing **Username and password security** surface is now the smaller
  optional **Account settings** surface. It says routine work needs no further
  password check and reserves its confirmation control for administrator-
  access changes.
- The completed `lpl01-reconciliation.html` aid and its special Caddy handling
  are removed. The soft-launch contract now fails if that page or handler is
  restored. Future campaigns belong in the ordinary authenticated Media/
  Content workflow; the completed campaign has no retained candidate to show.
- OpenAPI `0.29.0`, generated clients, UI catalogue revision
  `ui-*-2026-08-14.10`, the administrator guide and governance matrix match the
  new boundary. No migration, schema, credential, role assignment, tenant data,
  provider call or deployment was performed.
- Required gates pass: `make verify` (177 SQLite tests / 1,935 assertions; 315
  PostgreSQL tests / 4,469 assertions; 282 package/Web tests plus static,
  contract, locale and audit checks), `make e2e` (4 reconciliation API tests /
  103 assertions and 21 Chromium journeys) and `make build`. `make ai-eval`
  was not required because no prompt, model, provider or orchestration changed.

## LPL-01 human transcript reconciliation — 14 August 2026

- The authorized Direct operator completed the private HTTPS workflow for
  campaign `01KZP5129TNAQPE542XM15604C`, listening to each complete exact
  asset, inspecting every returned segment and selecting `confirmed` for
  Introduction, Practice, Conversation and Interaction.
- The four append-only reconciliations created these English metadata
  revisions and content hashes without placing transcript text in repository
  evidence:

| Part | Metadata revision | Content hash |
|---|---|---|
| Introduction | `01KZZB3DJFKAN8BQD30K6WJGV6` | `2e8335f5998dace0a619acbd24945beafbb3b9a2e427bd71dd01f473b3b17e0b` |
| Practice | `01KZZBHZXE93YYHPGNKFJZ8EBY` | `a185249064c4e153237e1a54f8375fe1e7e4fac2ce45525684a929ea174a6298` |
| Conversation | `01KZZBPQSS5ECMMGEBGAA2W3M4` | `830bba76c97bb19d3b132da8bd7a021cb5160be146592cfffcaaaa810bc48635` |
| Interaction | `01KZZC03NX6QGCWGMD9WX1AH1D` | `5db31dd35f5d9fe1f0d02fc116e2793f718bf6b4cff1dde00c25c93ec8ba2d37` |

- The scheduled retention worker produced four completed candidate-deletion
  receipts, each on its first deletion attempt. The campaign moved from
  `provider_complete` to `completed` at 14 August 2026 05:35:20 UTC with zero
  retry/dead-letter backlog. Its existing finite usage evidence remains four
  successful provider attempts, zero failures/retries, 18,760,190 uploaded
  bytes, 1,172,513 billable milliseconds and ₹11.189394 estimated usage.
- This establishes metadata-author reconciliation only. It is not English
  academic approval, Kannada review, blueprint validation, publication or
  LPL-01 completion. No LPL-02 work is authorized.
- The 19-minute-32.513-second workflow also proved that the existing separate
  five-minute password confirmation for candidate read and submission creates
  repeated same-factor prompts. [DR-0031](DR-0031_LPL01_RECONCILIATION_AUTHENTICATION_SIMPLIFICATION_REQUEST.md)
  recorded the bounded one-login design choice. Opening the request changed no
  behavior; APP-0060 / ADR-0023 subsequently resolved and broadened it as
  recorded above.

## LPL-01 local evidence manifest — 14 August 2026

- The exact four-part media bindings are represented in the local review-only
  manifest at `docs/evidence/lpl-01/level1-source-snapshot-manifest.json` and
  the ordered shell at `docs/evidence/lpl-01/level1-blueprint-draft.json`.
- This is **not** claimed as the typed source snapshot: its rich-text field
  list is empty until an exact read-only source export is supplied. It
  contains no raw HTML, transcript text, Assignment audio or learner data.
- `scripts/validate-lpl01-evidence.mjs` deterministically verifies the four
  source order positions, all four metadata revision/content hashes, the
  blocked publication state and the excluded-media boundary. It passes.
- No legacy-site request, database write, migration, curriculum attachment,
  publication, review approval or LPL-02 work occurred. LPL-01 remains blocked
  on the exact source body and all downstream content/review gates.

## Local administrator setup transition — 13 August 2026

- A fresh gzip-valid PostgreSQL backup was written outside the repository at
  `~/ge-soft-launch/backups/soft-launch-20260813T060939Z.sql.gz`, 138,142
  bytes, SHA-256
  `357714361f38ed162d63bbed53513946fba47af3cb7218a80b54b91d33791b31`.
  Its inherited `0664` mode was corrected to owner-only `0600`; the backup
  command now sets `umask 077` so later snapshots are private at creation.
- Content-free preflight reconfirmed 16 active users, two credential-ready
  users, 14 append-only exclusions and one `waiting_delay` case with two
  approvals. The target remains active, credential-unready and backed by one
  verified recovery contact. Its manifest owner is active, credential-ready,
  was the original case requester and already belonged to the reviewed legacy
  actor set.
- Migration `2026_08_13_000200_create_unified_account_recovery.php` ran as
  batch 8. The old case is now `superseded` with reason
  `unified_account_recovery`; both historical approvals remain. No row was
  deleted or rewritten into a successful recovery.
- Only the exact manifest owner is configured locally through
  `PLATFORM_ACCOUNT_RECOVERY_MANAGER_USER_IDS`; the retired two-actor setting
  was removed. No role, permission, membership, appointment, enrollment or
  content authority changed.
- A loopback-only API/Web ceremony was opened for ten minutes. No manager
  password-confirmed session appeared, so the service correctly created zero
  account-recovery management events and zero `account_setup` challenges. No
  token, username or password was generated, displayed or inspected. The
  temporary target-bound page was removed and the managed soft-launch service
  restored healthy on its existing temporary HTTPS origin.
- A later human retry signed in as the exact designated manager but entered an
  incorrect current password in Account Security. The fresh-confirmation guard
  failed closed with zero recovery evidence, while exposing a misleading HTTP
  500 status. The error contract now returns an explicit safe HTTP 403
  `fresh-password-confirmation-required` problem; SQLite and PostgreSQL feature
  regressions prove denial creates no management event or setup challenge and
  that correct confirmation still permits exactly one protected setup.
- The manager then completed a valid same-password confirmation and authorized
  exactly one setup. The target chose the username/password through the
  protected-local page; one accepted challenge was consumed, the owner-only
  delivery was deleted and normal password sign-in loaded the existing
  operator workspace. Codex never read the token, contact, username or
  password. The before/after tenant-authority SHA-256 is identical at
  `d7ed3f404a5a986f7199882a38454563af6999eee69a0fd3534fe2fabd557cf6`.
- The protected-local metadata GET initially failed 401 because the page's
  `no-referrer` policy removed Sanctum's stateful same-origin signal. The page
  now uses `same-origin` only; public fragments are erased before the request
  and no referrer is sent cross-origin. A browser regression covers the exact
  request and the existing memory-only fragment behavior.
- The migration reconciliation switch is disabled, the empty protected
  delivery directory is removed and the managed soft-launch service is healthy.
  A subsequent managed-HTTPS sign-in and current-password confirmation both
  succeeded with the current credential version before privileged work resumed.
- Final `make verify` passes against this exact state: 177 SQLite tests /
  1,934 assertions, 315 PostgreSQL tests / 4,477 assertions and 285
  JavaScript tests, plus audits, Pint, PHPStan, localization, OpenAPI/generated
  clients, release/runtime contracts, lint and typecheck.

## AUTH-SIMPLE-01 repository closure — 13 August 2026

- One global username/password sign-in now presents the website-inspired split
  login layout without importing the legacy site's phone, Google or remember-
  me credential behavior. Role, organization and enrollment are still resolved
  only after authentication.
- Learners and privileged users share the same enumeration-safe, 256-bit,
  15-minute, single-use verified-contact recovery lifecycle. Successful reset
  globally revokes Web sessions and Android token families.
- One freshly password-confirmed configured actor with exactly
  `platform.account_recovery.manage` can issue a migration-exclusion setup link
  or replace a lost verified contact after bounded external evidence. The actor
  never receives a token or chooses target credentials; contact replacement
  globally revokes credentials and records content-free append-only evidence.
- Migration `2026_08_13_000200_create_unified_account_recovery.php` preserves
  old case/approval rows, supersedes incomplete exceptional cases, adds the new
  management/notification ledgers and fails rollback closed after new evidence.
  A PostgreSQL migration test proves a live waiting case and approval survive
  transition and that the permitted pre-evidence rollback restores the case and
  pending challenge.
- The two-actor/24-hour APIs, services, configuration authority and local
  commissioning page are absent from active runtime. Dedicated setup/recovery
  pages erase URL fragments immediately, retain tokens only in page memory,
  bypass service-worker caching and receive no-store/same-origin referrer
  protections.
- OpenAPI 0.28.1 and generated TypeScript clients match. Focused evidence is 11
  SQLite tests / 109 assertions, 10 PostgreSQL auth/migration tests / 101
  assertions and one PostgreSQL race test / 8 assertions. Complete verification
  passes 177 SQLite tests / 1,934 assertions and 315 PostgreSQL tests / 4,477
  assertions plus audits, formatting, PHPStan, locale/OpenAPI/generated-client,
  release/runtime, lint, type and 285 JavaScript tests. `make e2e` passes 4 API
  tests / 105 assertions and 20/20 Chromium journeys. `make build` passes with
  the retained Vite chunk-size advisory. The `global_english_test` logical
  backup/restore drill passes with matching migration/schema/deletion evidence
  and a measured two-second RTO.
- Production remains fail closed when the reviewed delivery or former-contact
  notification adapter is unavailable. The local administrator transition is
  recorded separately under D-0190; no production deployment/provider, tenant
  authority, learner record, AI/voice state, curriculum publication or Play
  release changed.
- Account-recovery manager actions are currently API/runbook operations; a
  dedicated React support console is not part of AUTH-SIMPLE-01.

## Stable static-IP HTTPS edge — 12 August 2026

- The runtime now supports `GE_SOFT_LAUNCH_STABLE_HOST` with exact host/origin
  validation, Secure-cookie/Sanctum convergence and a generated Caddy edge.
  WAN 80/443 are intentionally mapped to unprivileged host ports 8080/8443;
  the stable edge proxies only to loopback port 9080.
- Stable mode disables rotating Quick-Tunnel startup and changes systemd
  supervision to require only API, worker, scheduler, loopback Caddy and the
  stable public edge. HTTP redirects to the exact HTTPS host; Caddy manages the
  public certificate and emits HSTS, nosniff, frame-denial and strict-origin
  referrer headers.
- `bash -n`, the soft-launch environment contract and Caddy validation pass.
  Complete `make verify` also passes: 177 SQLite tests / 1,928 assertions, 314
  PostgreSQL tests / 4,472 assertions and 285 JavaScript tests plus audits,
  formatting, PHPStan, locale/OpenAPI/generated-client/release/runtime checks,
  lint and typecheck. `make build` passes with the retained measured Vite
  chunk-size advisory. The configured-host contract proves exact APP_URL,
  mode-0600 environment, stable routing, public-certificate policy and
  rejection of mismatched origins.
- Read-only network evidence found Cloudflare authoritative DNS and verified
  the host's expected public IPv4 address, but `ai.globalspokenenglish.com`
  currently has no A record. The live runtime was deliberately not switched,
  avoiding certificate retries and closed-test downtime before the DNS/router
  prerequisites exist.
- Activation is explicitly deferred to 13 August 2026 because authorized
  Cloudflare credentials are unavailable. No credential is to be supplied in
  chat, no DNS bypass is permitted and no completion is claimed.
- No schema, API, credential, tenant authority, provider call, content or
  Android package changed. Stable hosting closes only the rotating-origin gap;
  production signing/Play, independent reviews, reviewed L1 content, provider
  gates and remaining physical-device evidence stay open.

## Levels 1–17 accelerated governed rollout approval — 12 August 2026

- Read-only PostgreSQL and governed-inventory inspection confirms that Levels
  1–17 are not ready or live as a reviewed AI pathway. There are zero governed
  level rows; two duplicate staging-smoke imports each contain only 12 numbered
  video modules plus a scaffold and one generic mission.
- Current numbered media omits Levels 4, 5, 12, 15 and 16. All levels lack the
  complete transcript/objective/quiz/rationale/Talk/Apply/review evidence
  required for a learning loop. AI scenarios are development-only and
  synthetic-unreviewed; live model and ordinary learner Speech remain gated.
- [DR-0029](DR-0029_LEVELS_1_17_LIVE_ACTIVATION_REQUEST.md) Option A is
  accepted exactly under APP-0058 / D-0184. LPL-01 is the only active content
  checkpoint; Level 1, Levels 2–3 and later source-complete levels remain
  ordered reviewed waves rather than a blanket activation.
- Approval changed no publication, enrollment, provider, permission, rights,
  content or learner record. Missing content cannot be fabricated and every
  later provider/publication/device/release gate remains intact.

## Unified production login and recovery decision stop — 13 August 2026

> Resolved by DR-0030 Option A under APP-0059 / D-0186 / ADR-0022.

- The product owner requested the same simple authentication model in
  production: one username/password sign-in for every role, role-based access
  after authentication and student tiers represented by enrollment rather
  than separate login types.
- The live DR-0027 recovery page exposed an environment mismatch before any
  mutation: password confirmation succeeded, but the protected recovery route
  remained restricted to `local`/`testing` while the managed service runs as
  `soft-launch`. The case remains `waiting_delay`; no challenge, username or
  password credential was created.
- [DR-0030](DR-0030_UNIFIED_ROLE_BASED_LOGIN_AND_RECOVERY_REQUEST.md) records
  the requested production simplification. Option A replaces privileged
  recovery actors and the 24-hour delay with the same verified-contact,
  15-minute single-use setup/recovery link for every role while retaining
  secure sessions, hashing, revocation, tenancy and audit controls.
- APP-0059 now authorizes only the bounded AUTH-SIMPLE-01 implementation: the
  same verified-contact setup/recovery for every role, historical preservation
  of old case evidence, one exact platform lost-contact capability and the
  existing independent tenant/permission checks. Production delivery and
  independent review remain gated.

## Closed-group Web and Android test pack — 11 August 2026

- The enabled `global-english-soft-launch.service` foreground user unit owns the API,
  queue worker, scheduler, loopback web proxy, port-8080 redirect and Cloudflare
  Quick Tunnel, has user lingering enabled for reboot startup, monitors all six
  children and restarts after a child or repeated health failure. All six
  processes are running. The external readiness endpoint returns `ready`, and
  the external authentication-mode endpoint returns exactly `password_only`.
- `make android-closed-beta` passed against the current HTTPS origin and the
  existing public Passport PKCE client. The resulting package is
  `com.globalenglish.app`, version `1.0 (1)`, target SDK 36, v2 debug-signed for
  controlled sideload testing. It contains the HTTPS Quick-Tunnel origin and
  contains neither the static HTTP origin nor the demo package identifier.
- The protected artifact is
  `~/ge-soft-launch/artifacts/closed-group/global-spoken-english-closed-group-20260812-r4.apk`,
  8,461,998 bytes, SHA-256
  `d3e55fe4790cb4e7fd7cebf428fb6818f3c419df849f877039e550669366f448`.
  No unauthenticated APK download route is enabled.
- Password-only invited learner self-setup is implemented as one atomic
  invitation + self-chosen global username/password operation. It creates no
  browser-stored credential and leaves no partial user, credential, membership
  or enrollment on failure. The operator never chooses or receives the
  tester's password.
- The complete tester procedure and Learn / Practice / Progress evidence
  checklist are in the root [closed-group runbook](../CLOSED_GROUP_TEST_RUNBOOK.md).
- The DR-0027 case remains `waiting_delay` until 12 August 2026 10:44:26 UTC /
  16:14:26 IST. It was not bypassed. Its impact is limited to the excluded
  organization-administrator account and issuing new invitations through that
  account; the two credential-ready learner accounts can test now.
- The Quick Tunnel has no uptime or stable-hostname guarantee. A hostname
  rotation requires an APK rebuild and redistribution. A named tunnel or
  product HTTPS domain is still recommended once DNS authority is supplied.
- The current 12 August hostname rotation has been closed for the staged artifact:
  JDK 21 `make android-closed-beta` passed 103 Gradle tasks, the package embeds
  only the current HTTPS origin and active public PKCE client, and APK Signature
  Scheme v2 verification passed. Revision 4 installed successfully on the
  attached Realme 8 (Android 13); final authenticated return and DM-01/DM-02
  evidence remain open.
- Complete repository verification passes: 177 SQLite tests / 1,928
  assertions, 314 PostgreSQL tests / 4,473 assertions and 285 JavaScript tests,
  plus dependency audits, Pint, PHPStan, localization/OpenAPI/release/runtime
  contracts, ESLint and TypeScript.
- LPL-01 publication, independent review, physical-device evidence,
  production signing, VPS migration and public/Google Play authorization all
  remain open. No production or Play claim is made.
- The ordered evidence and authority gates are tracked in the
  [production readiness closure matrix](PRODUCTION_READINESS_CLOSURE_MATRIX.md).

## APP-0053 Conversation Learning Flow closure

- The soft-launch Friend turn now uses confirmed learner text for the STT-stage
  contract, the allowlisted Azure OpenAI adapter for model generation when its
  external evidence/secret/kill switch pass, and explicit client text-first /
  on-device speech playback. The cost ledger accepts only those exact
  stage/provider combinations outside local/testing; local/testing remains
  deterministic fake-only.
- Confidence Chat, Skill Focus and Review share the Friend engine but have
  distinct immutable scenario snapshots. Skill Focus defaults to Tutor with a
  five-turn threshold; Confidence Chat uses four turns and Review uses two.
  The existing Observe → Rehearse → Perform → Reflect Role-play remains the
  fourth session type.
- Product copy no longer claims the ordinary soft-launch path is a fake. It
  discloses that the partner is AI, feedback is advisory, marks/mastery are
  unchanged, turn evidence is content-free and optional voice retention is
  controlled by explicit consent. Local/demo copy remains synthetic.
- OpenAPI 0.24.0 and generated clients contain all Friend scenario keys and the
  live text-first speech/cost response contract. Migration `000180` narrowly
  permits the same provider/stage combinations in PostgreSQL.
- A repository-owned persistent user unit is enabled at
  `~/.config/systemd/user/global-english-soft-launch.service`; `Linger=yes`
  provides reboot startup. API, worker, scheduler, loopback Caddy, static-IP
  HTTP listener and Cloudflare tunnel are running. Readiness is 13 pass, six
  expected Azure-configuration warnings and zero failures.
- A pre-migration backup was written to
  `~/ge-soft-launch/backups/soft-launch-20260810T170110Z.sql.gz`; migration,
  idempotent foundation seed and production web build passed before service
  replacement.

The separate Azure OpenAI endpoint, South India regional deployment and
outside-repository secret are now installed. Readiness is 17 pass, one warning
and zero failures. The remaining warning is the intentionally disabled live
kill switch. A forced live-enabled no-call preflight returns exactly
`vendor_evidence_logging_enabled` because Azure has not granted modified abuse
monitoring and the resource does not expose `ContentLogging=false`. No model
request or model-token spend occurred.
ADR-0007/0008 still withhold ordinary real learner-audio Azure STT activation.
The Azure Speech synthetic-benchmark environment and one active credential are
already present outside the repository with mode `0600`. Its completed
5 August run passed all recorded gates; the repaired no-call wrapper now reports
the configured environment as `ready` instead of falsely reporting the
unloaded application defaults as blocked. This does not enable learner audio.
The product name is now **Global spoken English Course**. Caddy binds
`0.0.0.0:8080`; local root/readiness return HTTP 200 and `:8088` is closed.
The router forward must target this host's LAN address `192.168.1.3:8080`.
Public-IP testing from the same LAN times out, which may be missing NAT
loopback; external cellular/VPS verification and Ubuntu firewall inspection
remain required. The existing `https://www.globalspokenenglish.com` site was
not accessed or changed.
Production/Play release and independent review are not authorized.

## DR-0028 ordinary learner Speech activation decision stop — 12 August 2026

- The existing Azure Speech synthetic benchmark, consent ledger, private
  recording lifecycle and deletion reconciliation do not authorize ordinary
  learner audio. Ordinary recorded-speech DI and `VOICE_UPLOAD_ENABLED` remain
  fail closed.
- [DR-0028](DR-0028_ORDINARY_LEARNER_SPEECH_ACTIVATION_REQUEST.md) now proposes
  an exact two-learner Global English Direct pilot: Central India `en-IN` STT,
  English neural reply TTS, separate consent, transcript correction, ADR-0007
  retention/deletion, hard 20-recording/₹1,000 caps, independent reviews and
  distinct implementation versus live-smoke approvals.
- No Speech binding, configuration, schema, provider request, learner content
  processing or spend changed. Phase A implementation requires the exact APO
  statement in DR-0028; live Phase B remains separately gated.
- The Azure OpenAI resource was checked read-only on 12 August. It still
  exposes no `ContentLogging` capability, so modified-abuse-monitoring evidence
  is absent, the model kill switch remains false and no model call is allowed.

## Independent L1 review gate — 12 August 2026

- `make ai-eval-review-export REVIEW_PATH=<absolute-path>` now exports all 15
  hash-locked synthetic cases with the actual English reply, correction, L1
  explanation, safety/category context and a canonical per-row payload hash.
- `make ai-eval-human-review REVIEW_PATH=<absolute-path>` fails closed unless
  every exact output is accepted by an independently attributed, locale-
  matched reviewer with a valid role, instant and evidence reference. Changed
  suite or output hashes, missing/duplicate rows and pending/rejected rows are
  rejected.
- The mechanism is verified, but no bilingual human review is claimed. The
  protected packet must be completed under
  [the review runbook](L1_INDEPENDENT_REVIEW_RUNBOOK.md); the five Indic UI
  catalogues remain pending independent review and production still falls back
  to approved English.
- The byte-verified mode-0640 synthetic packet is staged at
  `~/ge-soft-launch/artifacts/review/friend-l1-review-20260812-47d5f2f1.json`:
  17,364 bytes, SHA-256
  `47d5f2f10fe0d0d6d8cdcb8ea57cf39e6dadf9b67c031aa2ea072cc61341a05d`.

## Audience documentation

The repository now includes three evidence-based audience guides:

- [Learner user guide](guides/LEARNER_USER_GUIDE.md) for sign-in, daily
  missions, offline use, private media, Confidence Conversation, voice/privacy
  choices, results and troubleshooting;
- [Administrator and operator user guide](guides/ADMINISTRATOR_USER_GUIDE.md)
  for privileged access, People, Learners, Content Studio, Media Library,
  operations, safety and governed lifecycle checklists; and
- [Sales feature and solution guide](SALES_FEATURE_GUIDE.md) for positioning,
  buyer use cases, feature status, responsible-AI boundaries, pilot discovery,
  claims guardrails and roadmap communication; and
- [Ubuntu soft-launch runbook](SOFT_LAUNCH_UBUNTU_RUNBOOK.md) for the invite-only
  real-product host under APP-0053.

Each guide distinguishes repository-implemented, controlled/conditional,
demonstration-only, planned and deferred behavior. They introduce no runtime,
API, schema, provider, permission, release or architecture change.

## Active learner-experience plan

[LESSON_PRACTICE_LOOP_PLAN.md](LESSON_PRACTICE_LOOP_PLAN.md) version 2.0
records the future-proof Learn → Talk → Check → Apply → Review platform plan.
It covers dynamic media analysis, governed lesson/assessment generation,
all-level commissioning, Skill Chat, deterministic mastery, personalization,
speech, trainer workflows, accessibility, offline delivery, analytics,
integrations and separately gated platform-evolution horizons. LPL-00–LPL-07
form the launch-critical core; LPL-08+ cannot be pulled forward silently. The
plan has completed LPL-00. [DR-0020](DR-0020_LESSON_PRACTICE_FOUNDATION_REQUEST.md)
Option A and [ADR-0015](adr/0015-lesson-practice-foundation.md) are accepted
under [APP-0046](APPROVALS.md#app-0046) / D-0140. The active bounded
[LPL-00 specification](LPL-00_SPEC.md) and locked
[verification scope](LPL-00_VERIFICATION_SCOPE.md) fix the exact schema,
scoring, speaking-evidence, answer-isolation and unlock policy. LPL-01 is now
active. Its [exact inventory](LPL-01_CONTENT_INVENTORY.md) found no current
governed set of four Level 1 audio lessons. A subsequent authenticated,
read-only [legacy source map](LPL-01_LEGACY_SOURCE_MAP.md) identified the
reported structure: one Level 1 `Can` source lesson with four ordered MP3 parts
(Introduction, Practice, Conversation and Interaction), inside a repeated
17-level × six-lesson architecture. APP-0047 now authorizes those four exact
hashes for bounded private non-production ingestion, transcription and
AI-assisted derivative drafting. DR-0021 Option A is now accepted under
[APP-0048](APPROVALS.md#app-0048) / D-0145 /
[ADR-0016](adr/0016-long-form-lesson-audio-media.md), and its bounded
`lesson_audio` Media Library prerequisite has passed repository verification,
E2E and build gates. The four authorized source files were reacquired into
temporary non-repository storage, matched byte-for-byte and hash-for-hash, and
passed the project-owned real ffprobe and ClamAV adapters. DR-0022 Option A and
APP-0049 bound one normal phone identity to the exact synthetic Direct
administrator. DR-0023 Option A then preserved the locked incident in a
validated snapshot, passed a strict expected-delta audit, restored the exact
pinned baseline, reran migrations/seed and replayed only that identity
binding. Ordinary OTP, exact membership and retained TOTP now work without a
support reset. Through explicit ffprobe/ClamAV adapters and the first-party
HTTP lifecycle, all four APP-0047 hashes are active private `lesson_audio`
assets with matching byte counts, durations and clean scans. Failed adapter
attempts remain failure evidence, and quarantine objects use the normal
reconciliation worker. DR-0024 Option A is accepted and executed under
[APP-0051](APPROVALS.md#app-0051), D-0150/D-0151 and
[ADR-0019](adr/0019-local-lpl-pilot-and-rights-authority-bootstrap.md). One
standalone private LPL pilot program now owns one least-privilege role and one
accepted program-scoped rights-confirmer appointment. Ordinary selected-
membership/fresh-TOTP HTTP commands created exactly four approved APP-0047
rights revisions for later gated web/Android delivery in India, derivatives
allowed, sharing false and no expiry. The persistent bootstrap switch is
absent. No lesson-audio metadata, attachment, transcript, curriculum version,
publication, enrollment or learner delivery was inferred. The next exact stop
was accepted as
[DR-0025](DR-0025_LPL01_TRANSCRIPTION_COMMISSIONING_REQUEST.md) Option A under
[APP-0052](APPROVALS.md#app-0052), D-0153 and
[ADR-0020](adr/0020-governed-curriculum-media-transcription-commissioning.md). It fixes
a separate provider-neutral curriculum-media path using direct exact-MP3 Azure
Fast Transcription, encrypted expiring candidates, immutable content-free
provenance and a first-party human reconciliation command. Its fixed one-
campaign caps are four assets, eight HTTP attempts, 37,520,380 uploaded bytes,
2,345,026 billable input milliseconds and ₹100 estimated Azure usage. The
bounded repository implementation is now complete and verified under D-0154:
it adds exact campaign/attempt/event/reconciliation provenance, an authorized
Azure adapter behind the project port, encrypted expiring candidates,
content-free retention/dead-letter processing, a private fresh-TOTP
reconciliation API and safe external reports. OpenAPI 0.23.0 and generated
clients changed with that API. Ordinary DI remains disabled and no live
credential, provider call, candidate, transcript or metadata revision was
created. A subsequent D-0157 live-preflight audit applied the additive
commissioning migration only after a content-free PostgreSQL backup, captured
same-day official price/FX evidence and projected ₹22.378788 maximum spend.
That preflight dispatch still failed closed: prior evidence identified the contracting-entity
review only by an opaque reference, and Azure security defaults require
interactive reauthentication before the exact billing entity or current key
could be verified. No campaign permit existed and database/provider counts
remained zero at that historical stop. The subsequently authorized exact
campaign completed with four successful attempts, zero failures/retries,
18,760,190 uploaded bytes, 1,172,513 billable input milliseconds and
₹11.189394 estimated usage. Under D-0191 the authorized operator then listened
to every complete exact asset, inspected every returned segment and confirmed
all four candidates into exact English metadata revisions. The retention
worker completed all four encrypted-candidate deletions on their first
attempt, with no retry/dead-letter backlog, and the campaign is now
`completed`.
The current local smoke data still has only one unique Level 1 MP4
with no transcript/topic objective and derivative use set to false. The
[Level 1 review packet](LPL-01_LEVEL1_BLUEPRINT_REVIEW_PACKET.md) therefore
remains blocked and unapproved pending the typed source snapshot, blueprint,
English academic review and Kannada creation/review; no source text was
promoted into an answer key and no LPL practice runtime code exists yet. Under
[APP-0053](APPROVALS.md#app-0053) LPL-01 remains the active critical path while
Ubuntu invite-only soft launch is active. The product owner's live-AI and
repository-media allowance applies to bounded non-production soft-launch work
within the APP-0053 ₹10,000 Azure ceiling and existing privacy, tenancy,
review and release controls; it is not unlimited spend or Play/public release
authority.

D-0158 hardens the APP-0053 launch boundary after an operational review found
that the dedicated conversation env named by the runbook was not loaded by the
runtime/preflight and that syntactically valid setup placeholders could satisfy
parts of provider authorization. Runtime and preflight now share one precedence
contract: provider settings come from the dedicated conversation env while the
main soft-launch env owns `APP_ENV`, driver and kill switch. Setup preserves an
existing conversation env, new evidence scaffolds are unapproved/invalid, and
the provider rejects both old and new scaffold sentinels before HTTP. The live
host remained healthy through its HTTPS tunnel with two active publication
heads and two active enrollments; a fresh mode-`0600` gzip-valid database dump
was taken. Live tutoring remains off because current external vendor evidence,
endpoint, deployment and secret are still placeholders/missing. No provider
request or spend occurred.

## Current governance boundary

The approved ADMIN program is
[ADMIN_REFERENCE_PARITY_PLAN.md](ADMIN_REFERENCE_PARITY_PLAN.md), with the
implementation boundary frozen in
[ADMIN_GOVERNANCE_MATRIX.md](ADMIN_GOVERNANCE_MATRIX.md). ADMIN-SHELL-01
implements only the matrix's derived `O-READ` surface.

Delivered shell:

- `GET /operator/overview` derives organization metadata, navigation and panel
  keys from the current membership's existing content, audit and support read
  permissions without disclosing permission names;
- `GET /operator/search` federates fixed safe descriptor fields across only
  authorized, SQL organization-scoped sources with a bounded encrypted cursor
  tied to tenant, normalized query and source set;
- the existing audit listing now uses encrypted organization-bound
  `(occurred_at, id)` keyset continuation without changing its safe action or
  field allowlist;
- the responsive React workspace hides learner navigation, loads every panel
  independently, retries partial failures, keeps search ephemeral and provides
  keyboard-operable safe audit detail at 320 CSS pixels; and
- OpenAPI 0.17.0, generated clients and all six source-linked UI catalogues
  changed together. No migration, feature schema, permission seed, provider,
  environment variable or mutable domain behavior was added.

Delivered ADMIN-ORG slice:

- the permission-derived People workspace now covers safe current-organization
  metadata, branches, cohorts, staff memberships, code-only invitations,
  custom roles, derived trainer profiles, existing authority appointments and
  count-only membership-selected credential administration;
- mutations are web-only, fresh-TOTP, header-idempotent and version/fingerprint
  guarded, with tenant-scoped transactions, safe audit and content-minimized
  administration events;
- staff suspension strips current authority and only credentials still
  selected for that membership; reactivation restores no role, assignment,
  appointment, credential or authority;
- invitation secrets are show-once, terminal transitions advance version and
  the hourly maintenance path clears terminal digests, retries revoked web
  session storage cleanup and expires/purges bounded operational history; and
- OpenAPI 0.18.0, generated clients, six used permission definitions, all six
  source-linked UI catalogues and the responsive People journey changed
  together. Writable organization settings, learner administration and
  global identity/recovery authority were not added.

Delivered ADMIN-LEARNER slice:

- the manifest-gated Learners workspace provides bounded current-organization
  directory/detail, enrollment history and code-only learner invitation tasks
  with independent loading/retry, cursor pagination and safe server-derived
  actions;
- exact learner-safe invitation roles, attributed append-only current-goal
  correction, one versioned current enrollment, immutable verified curriculum
  pins, cohort-derived trainer summaries and reversible learner suspension are
  enforced transactionally under fresh TOTP and header idempotency;
- suspension revokes only credentials still selected for the target learner
  membership, retains learning/enrollment evidence and restores no credential,
  enrollment or pin on reactivation;
- the fixed privacy inventory is now `learner-privacy-v2`, including the
  current-goal projection, correction history and linked content-minimized
  administration events; and
- OpenAPI 0.19.0, generated clients, three used learner permissions, all six
  source-linked catalogues and the responsive Learners browser journey changed
  together. Support notes, global identity/account controls, learner exports,
  direct trainer assignment and enrollment reactivation were not added.

Delivered ADMIN-CONTENT slice:

- append-only current-organization workspaces now create programs, explicit
  graph-v2 levels through delivery bundles, immutable draft revisions,
  descriptor corrections, abandonment and same-owner successors while every
  existing graph-v1 publication remains byte-stable and immutable;
- typed governed-text and daily-speaking-mission adapters provide bounded
  English/Kannada authoring and private no-store preview without raw JSON,
  markup execution, public URLs or browser persistence;
- exact graph, English, Kannada and readiness queues derive safe blockers and
  semantic actions, retain reviewer/translator separation and appointment
  authority, and expose stable tenant-safe conflict codes;
- publication status and commands cover immediate publish, future scheduling,
  due activation, guarded cancellation, retirement and rollback with exact
  heads, evidence revalidation and idempotency; forced PostgreSQL races cover
  workspace, source, variant, review, lifecycle, permission and appointment
  overlap; and
- OpenAPI 0.20.0, generated clients, all six governed catalogues and the
  manifest-gated responsive Content Studio changed together under D-0137. No
  permission, grant, platform capability or media-management authority was
  added; media-dependent daily missions remain blocked until ADMIN-MEDIA-01.

Support-note writes, notices/messages, moderated group conversation,
provider-backed entitlement/payment work, platform configuration, legacy data
import and production/public release remain blocked by the exact retained
decisions in the matrix.

## Ordered remediation state

| Checkpoint | Repository state | Principal evidence |
|---|---|---|
| LV-01 | Complete | Draft-only, caller-idempotent legacy video import; no fabricated identity, authority, approval, rights or publication |
| PRIV-DEL-01 | Complete | Durable content-free deletion intent, retry-safe object deletion and exact privacy receipt links |
| AND-AUTH-01 | Repository implementation complete | One Android Passport transport, secure native refresh storage and single-use PKCE callback handling; physical device evidence remains external |
| MFA-01 | Repository implementation complete | RFC 6238 TOTP, one-use recovery, fresh scoped assertions and global credential revocation; independent production review remains open |
| PIPE-01 | Complete | Pinned dependency/security updates and green formatting/static/type/localization gates |
| VIDEO-01 | Complete | Verified bounded single-range delivery, constant-query evidence and bounded Android Blob fallback |
| PRIV-DSR-01 | Complete | Full fixed-class privacy inventory, deterministic erasure outcomes and shared voice deletion reconciliation |
| REL-01 | Complete | Fail-closed CI aggregate, bounded outbox dead letters, recovery commands and executable load/restore evidence |
| MAINT-01 | Complete | Shared request/test fixtures, self-bootstrapping isolated test storage, measured diagnostics indexes and current-status reconciliation pass the full gate |

No ADMIN feature schema is part of these remediation checkpoints.

## ADMIN program state

| Checkpoint | Repository state | Principal evidence |
|---|---|---|
| ADMIN-DISC-01 | Complete | Read-only reference capability inventory and approved safe parity specification |
| ADMIN-GOV-01 | Complete | Frozen governance matrix plus executable common operator-route policy; no feature schema or route |
| ADMIN-SHELL-01 | Complete | Permission-derived overview/navigation, independent panels, safe bounded search, encrypted audit continuation and accessible responsive drill-down |
| ADMIN-ORG-01 | Repository implementation complete | Staff-only tenant administration, strict delegation and final-manager boundaries, show-once invitations, reversible authority-stripping suspension, selected-credential revocation, bounded history/cleanup, OpenAPI 0.18.0 and accessible People UI |
| ADMIN-LEARNER-01 | Repository implementation complete | Bounded learner directory/detail, learner-safe invitations, append-only goal correction, versioned enrollment/pins, cohort-derived trainers, selected-credential lifecycle, OpenAPI 0.19.0 and accessible Learners UI; support notes remain excluded |
| ADMIN-CONTENT-01 | Repository implementation complete | Graph-v2 owner workspaces, typed authoring/preview, exact review/readiness queues, guarded publication lifecycle, OpenAPI 0.20.0 and accessible Content Studio under D-0136/D-0137; media management remains excluded |
| ADMIN-MEDIA-01 | Repository implementation and retained gates complete | Exact-asset private quarantine, inspection/scanning, accessibility metadata/review, appointed rights, immutable attachment/replacement, retirement and private preview; OpenAPI 0.21.0, generated contracts and Media Library UI; PostgreSQL races, full verify/E2E/build and backup/restore passed on 10 August 2026 |

## Current product and architecture boundary

### Identity, tenancy and privileged access

- Users and authentication records are platform-global; memberships, roles
  and domain resources remain organization-owned under ADR-0003.
- Web/PWA uses Sanctum cookie sessions. Production Android uses Passport
  Authorization Code with PKCE through the project authentication abstraction.
- Android access tokens remain memory-only; refresh credentials and pending
  PKCE state use the reviewed native secure store. Browser `localStorage`,
  IndexedDB and ordinary Capacitor preferences are not credential stores.
- Mutable operator actions are web-only and require fresh TOTP where specified.
  TOTP is not phishing resistant, and support-assisted reset is not yet an
  implemented product path.

### Learning, content and video

- Learning evidence, mastery, review and progression remain deterministic.
  AI paths cannot write authoritative learning records.
- Published curriculum and submitted evidence remain immutable/versioned.
  The canonical seed is synthetic and unpublished. The local staging-smoke
  legacy publications contain approved generic English/Kannada wrappers and
  learner-delivery rights, but those records do not approve a transcript,
  topic objective or lesson-practice blueprint and explicitly disallow
  derivative use.
- Lesson video delivery is exact-pin and current-rights gated. It supports one
  bounded byte range with a strong governed SHA-256 ETag. Browsers use native
  range loading; Android uses an authenticated, type/length checked Blob no
  larger than 20 MB.

### Conversation, voice and privacy

- Local/testing uses deterministic provider fakes. Production conversation
  and speech bindings remain fail closed unless their separately approved
  environment and evidence gates are satisfied.
- Learner audio upload requires the accepted ADR-0007 consent and retention
  path. Raw sensitive audio, transcripts, object keys and provider payloads
  are excluded from logs and operational health output.
- Voice source deletion records a durable tenant-owned receipt and content-free
  leased outbox event before storage I/O. Five failed attempts dead-letter the
  unpublished work without clearing the object key or completing the receipt.
- Privacy export emits the fixed `learner-privacy-v2` class inventory without
  silent row caps. Erasure records its pre-mutation outcome, removes mutable
  authorization/personalization, ends only the selected membership, revokes
  active credentials and retains explicitly justified immutable authority.

### Release enforcement

- CI has separate `verify`, `android` and `operations` jobs plus an `always()`
  `release-gate` that accepts only exact success from every dependency.
- The release-contract validator checks required commands, dependencies,
  action SHA pins and PostgreSQL digest pinning.
- Voice-deletion status is aggregate/content-free and fails non-zero for stale
  or dead-lettered work. Requeue requires the exact CLI event identifier after
  dependency remediation.
- The load harness fails for any non-200 or p95 threshold breach. The logical
  restore drill compares column schema, migrations and aggregate
  deletion/tombstone state without printing records.

Repository contract and local drill evidence is not a clean remote CI run,
branch-protection configuration, production capacity result or promotion.

## Authorization and next gate

[DR-0015](DR-0015_PRODUCTION_REVIEW_WAIVER_REQUEST.md) Option A is accepted
under [APP-0040](APPROVALS.md#app-0040) / D-0127. Ordered non-production
implementation, testing and staging may continue without repeated requests for
the retained legal or independent reviews. Missing review still cannot be
described as completed, passed or waived, and this authority does not permit
production or public release.

[ADR-0014](adr/0014-secure-reference-admin-functional-parity.md), the
[approved parity plan](ADMIN_REFERENCE_PARITY_PLAN.md) and accepted DR-0016
Option A authorized the now-complete ADMIN-ORG-01 repository slice. The
implementation remains limited to the organization/staff boundary in
[ADMIN-ORG-01_SPEC.md](ADMIN-ORG-01_SPEC.md); it does not authorize reference
code/data/media import, payment/provider spend, deployment or production.

ADMIN-LEARNER-01 has passed its repository exit gates under DR-0017 Option A,
[APP-0042](APPROVALS.md#app-0042) / D-0134 and
[ADMIN-LEARNER-01_SPEC.md](ADMIN-LEARNER-01_SPEC.md). ADMIN-CONTENT-01 has now
passed its repository exit gates under accepted
[DR-0018](DR-0018_ADMIN_CONTENT_AUTHORING_PUBLICATION_REQUEST.md) Option A,
[APP-0043](APPROVALS.md#app-0043), D-0136, D-0137 and the bounded
[specification](ADMIN-CONTENT-01_SPEC.md). The Accountable Product Owner has
now supplied the exact owner-level media-rights statement preserved under
[APP-0044](APPROVALS.md#app-0044), D-0138 and the
[rights attestation](ADMIN_MEDIA_RIGHTS_ATTESTATION.md). That statement
authorizes the next decision/specification step but does not automatically
approve an asset or bypass ADR-0004's exact-hash, use-scope, permission,
appointment and confirmation predicates. [DR-0019](DR-0019_ADMIN_MEDIA_LIBRARY_RIGHTS_LIFECYCLE_REQUEST.md)
is retained as the historical pre-implementation gate record. ADMIN-MEDIA-01
implementation and retained repository gates are now complete within its
accepted specification. Support-note tables, keys, routes and UI remain
blocked. LPL-00 is complete. LPL-01 has identified its legacy source, recorded
exact processing authorization, implemented the accepted long-form lesson-
audio prerequisite, passed the exact four-file real-adapter ingestion and
completed DR-0024's private pilot/runtime-rights authority. DR-0025 Option A is
accepted, and the bounded transcription/candidate/reconciliation repository
slice has passed its required gates under D-0154. LPL-01 is now stopped before
an exact contracting-entity/current-credential verification, campaign permit,
provider run and human transcript reconciliation. Same-day price/FX and the
isolated encrypted candidate-store path passed the D-0157 audit, but neither
can waive the missing gates. LPL-02 remains unauthorized.

## Verification state

Current LPL-01 evidence:

- read-only local PostgreSQL inventory found 3 programs, 27 lessons, 26 media
  rows and 2 local smoke publications, representing one synthetic unpublished
  lesson and two duplicate imports of 12 unique legacy level MP4s plus a
  synthetic scaffold;
- repository and gitignored artifact searches found no Level 1 audio lesson
  files; the retained legacy catalog records 17 Video rows, 12 MP4 downloads
  and missing video files for Levels 4, 5, 12, 15 and 16;
- authenticated read-only legacy inspection then reconciled one course, 19
  level descriptors and 102 numbered-level lesson rows: each Level 1–17 has
  Video, Audio, PDF/Text Book, Workshop, Group Practice and Assignment nodes;
  the Audio pages expose 67 MP3 references across all levels;
- Level 1 is explicitly `Can — I can speak in English`; its Audio node contains
  four ordered teaching MP3s. Ephemeral reads recorded exact SHA-256 and byte
  size for Introduction, Practice, Conversation and Interaction without
  retaining credentials, cookies or media in the repository;
- all 12 local MP4 SHA-256 values match the retained inventory; the exact
  private first-import Level 1 object was present and a fresh object-store
  read produced the governed SHA-256
  `8cc0ab3ceae5247e15b9feb17c2b9836d102a131b42232e6d74627b706edb5c6`;
- every imported level wrapper lacks transcript, can-do, target pattern,
  question, answer-rationale and Apply-rubric content; its only objective is
  the shared generic `legacy.level_video.orientation` objective;
- all local imported video rights rows allow web/Android learner delivery in
  India but disallow sharing and derivative use. APP-0047 separately
  authorizes only the four selected MP3 hashes for bounded private ingestion,
  transcription and derivative drafting;
- DR-0021 Option A / APP-0048 / D-0145 / ADR-0016 added only the governed
  `lesson_audio` kind and retained the current private Media Library lifecycle;
- each exact APP-0047 source was reacquired into temporary non-repository
  storage, matched its recorded SHA-256 and byte size, inspected as MPEG audio
  by the project ffprobe adapter, and returned clean through the project
  ClamAV adapter. The exact operational evidence is recorded in
  [LPL-01_INGESTION_PREFLIGHT.md](LPL-01_INGESTION_PREFLIGHT.md);
- final `make verify`, `make e2e` (16/16 Chromium journeys) and `make build`
  passed for the lesson-audio prerequisite. `make ai-eval` was not run because
  no prompt, model, provider or orchestration behavior changed;
- DR-0024 Option A created one standalone private pilot program, one exact
  two-permission non-system role assigned only to the retained membership and
  one program-scoped appointment through the existing authority service;
- ordinary OTP, exact membership and fresh TOTP accepted that appointment,
  after which four exact HTTP rights confirmations created four approved
  initial rights revisions with APP-0047 evidence, `android`/`web`, `IN`,
  derivatives and later-gated learner delivery allowed, sharing false, no
  expiry and the approved retention/deletion keys; and
- exact bootstrap replay and read-only persisted-state checks found one
  audit/outbox pair, appointment events `appointed` then `accepted`, four and
  only four rights rows and zero pilot curriculum versions. No attachment,
  transcript, provider run, learner record, enrollment or publication was
  created; and
- the final DR-0024 `make verify` passed 144 SQLite tests / 1,815 assertions,
  280 PostgreSQL tests / 4,017 assertions, PHPStan, audits, contracts,
  localization, lint/typecheck and 270 package/web tests; `make e2e` passed all
  16 Chromium journeys. `make ai-eval` and a separate `make build` were not
  required because no provider orchestration or packaging changed (the E2E
  server's production Vite build passed with the retained chunk-size warning);
  and
- accepted DR-0025 Option A now governs the exact-byte curriculum-media campaign,
  additive provenance/reconciliation model, seven-day encrypted candidate
  retention and hard four-asset/eight-attempt/37,520,380-byte/2,345,026-ms/
  ₹100 limits. Current official provider evidence was checked and the APP-0052
  approval-recording `make verify` passed 144 SQLite tests / 1,815 assertions,
  280 PostgreSQL tests / 4,017 assertions and all static, audit, contract,
  localization and 270 package/web tests. The acceptance created no runtime
  artifact and no provider call occurred; and
- the D-0154 implementation adds six tenant-owned commissioning tables, a
  provider-neutral adapter boundary, exact reauthorization/cap reservation,
  PostgreSQL single-run locking, encrypted private candidates, seven-day
  expiry, retry/dead-letter deletion, 365-day unreferenced provenance purge,
  private no-store fresh-TOTP reconciliation, safe external reporting and a
  disabled-default dedicated command. Focused SQLite passed 32 tests / 344
  assertions; focused PostgreSQL passed 16 tests / 305 assertions. Final
  `make verify` passed 160 SQLite tests / 1,876 assertions, 297 PostgreSQL
  tests / 4,344 assertions and all static, audit, contract, localization and
  270 package/web tests. `make e2e` passed the real reconciliation API journey
  (4 tests / 105 assertions) and 16/16 Chromium journeys; deterministic
  `make ai-eval` passed 15/15 with zero estimated cost and correctly skipped
  its separately gated speech half; `make build` passed with the retained Vite
  chunk-size warning. No live transcription provider call occurred; and
- the D-0157 execution audit created a PostgreSQL custom-format pre-migration
  backup (799,372 bytes; SHA-256
  `a1510723c88f350a60d99844ff0ae268d01b482eae4512165d40ef33643370d2`),
  applied only migration `2026_08_10_000170`, and confirmed zero campaigns,
  provider attempts, reconciliations and candidate-deletion rows. Official
  Central India retail pricing was $0.36/hour; the 10 August ECB cross-rate
  derived 95.30315880571182 INR/USD and a conservative 9,531 microunits per
  billable second, making the maximum approved manifest estimate ₹22.378788.
  The external mode-`0600` preflight audit has SHA-256
  `2c56e5a359636dc4da3e55a3917953019dce3738af40816caaf2eb4fe6d63abb`.
  Azure management-plane billing/key verification then failed with the
  allowlisted `interactive_reauthentication_required` condition, so no vendor
  evidence projection or campaign permit was created and no request/spend
  occurred. A mode-`0700` external resume utility (SHA-256
  `281e0cd64ced70652b7d5d1c233666a7f2f63165e31939a1086b5d31cee6276f`)
  now performs the exact billing-account/subscription correlation and
  resource/key comparison after normal Azure reauthentication without
  printing identifiers or credentials; Bash syntax validation passed and it
  cannot create the campaign permit. The documented mono-response
  compatibility correction passed a 17-test/50-assertion focused boundary
  suite, final `make verify` (165 SQLite tests / 1,883 assertions; 297
  PostgreSQL tests / 4,345 assertions; 270
  package/web tests), `make e2e` (4 API tests / 105 assertions and 16/16
  Chromium) and deterministic `make ai-eval` (15/15, zero cost).

LPL-01 remains active rather than complete. Accepted DR-0025 Option A fixes the
finite provider run ceiling, fail-closed kill switch, no-training/minimum-
retention settings and immutable provenance/human-reconciliation path. Its
repository implementation, external campaign and provider-candidate creation
are complete. Four attempts succeeded for four exact assets with zero failures
or retries. D-0191 records four human-confirmed metadata revisions, four
completed encrypted-candidate deletion receipts and terminal campaign state
with no cleanup backlog. The typed source snapshot, deterministic blueprint,
English academic review, Kannada creation/review and one exact
source-supported publishable Level 1 blueprint remain pending.
LPL-02 has not started.

Final ADMIN-MEDIA-01 evidence:

- the implementation follows DR-0019 Option A / D-0139: organization-scoped
  model-audio and lesson-video quarantine, exact hash/inspection/scanner
  evidence, human acceptance, governed accessibility metadata/reviews,
  appointed rights confirmation, attachment, replacement, retirement and
  private preview;
- the focused real-PostgreSQL `ConcurrentMediaLibraryTest` passed 5 tests / 53
  assertions in disposable PHP 8.4 with `pdo_pgsql` and `pcntl`;
- final `make verify` passed using the repository's pinned-compatible PHP
  8.4.24 and Node 24.18.1/pnpm 11.18.0 environment: Composer validation/audit,
  Pint (587 files), PHPStan (565 files), 130 SQLite tests / 1,665 assertions,
  271 PostgreSQL tests / 3,890 assertions, pnpm audit, locale validation,
  OpenAPI/client drift, release contract, ESLint, TypeScript and package/web
  tests all passed;
- final `make e2e` passed 16/16 Chromium journeys, including Media Library;
- final `make build` passed with the retained Vite chunk-size warning; and
- `POSTGRES_DB=global_english_test make backup-restore-drill` passed with a
  measured local RTO of 1 second and artifact
  `artifacts/ops/backup-restore-20260810T050100Z.json`.

The first disposable PHP image lacked `pcntl`; the first full-container static
analysis used a mismatched mount path; and initial E2E/build/restore attempts
met sandbox or temporary-storage setup failures. None reached a failing product
assertion. Each environment issue was corrected and the exact applicable gate
then passed. `make ai-eval` was not required for ADMIN-MEDIA because no prompt,
model, provider or orchestration behavior changed.

APP-0044 remains reusable umbrella ownership evidence only. It does not
automatically approve a file, rights scope, program, appointment or
publication. Independent security/privacy/tenancy/accessibility/language
review, production object-store/scanner validation, deployment and
production/public release remain unclaimed.

Final ADMIN-CONTENT-01 evidence:

- the master plan, ADR-0003/0004/0013/0014, D-0136, APP-0043, the frozen ADMIN
  matrices, existing curriculum/governance/publication code and the bounded
  specification were inspected before runtime edits;
- migration apply/rollback/reapply passed on SQLite and PostgreSQL (1 test / 20
  assertions on each), preserving graph-v1/publication tables while restoring
  only the additive workspace contract;
- focused Content Studio API, publication lifecycle and shell suites passed on
  both databases; focused web component/API-client behavior passed 27 tests;
- forced real PostgreSQL concurrency passed 9 tests / 95 assertions, with both
  workers entering the lock queue for graph, English head, Kannada head,
  structure review/edit, publish, schedule and rollback races, plus permission
  and appointment invalidation overlap;
- final `make verify` passed Composer/pnpm validation and audits, Pint,
  PHPStan, 128 SQLite tests / 1,526 assertions, 257 PostgreSQL tests / 3,541
  assertions, locale governance, OpenAPI/generated-client drift,
  release-contract validation, ESLint, recursive TypeScript and 247
  package/web tests;
- final `make e2e` passed all 15/15 Chromium journeys, including Content
  Studio partial failure, structured authoring, exact review, stale-state
  retention, axe and 320-pixel no-overflow coverage;
- final `make build` passed Laravel cache preparation/cleanup and the
  production web build with the retained Vite chunk-size warning;
- `POSTGRES_DB=global_english_test make backup-restore-drill` passed with exact
  schema/migration/aggregate reconciliation and measured local RTO 1 second;
  and
- `make ai-eval` was not run because no prompt, model, provider or
  orchestration behavior changed. The five non-English catalogues retain their
  declared pending-independent-review warnings and approved-English fallback.

Earlier ADMIN-LEARNER-01 evidence remains recorded below:

Final ADMIN-LEARNER-01 evidence:

- the master plan, accepted tenancy/authentication/content/offline/privacy
  ADRs, privacy inventory, frozen ADMIN matrices and existing learner/profile/
  enrollment code and tests were inspected before any runtime edit;
- DR-0017 Option A is accepted under APP-0042 / D-0134 and translated into
  exact schema, permission, route, lifecycle, retention, UI and test contracts;
- focused learner API/schema suites passed on SQLite and PostgreSQL; focused
  learner web/API-client behavior passed 23 tests;
- the real PostgreSQL administration concurrency class passed 11 tests / 85
  assertions, forcing lock-queue overlap for learner invitation accept/revoke,
  duplicate current-enrollment creation, competing goal corrections, cohort
  change versus withdrawal and verified activation, and membership selection
  versus suspension alongside the retained ADMIN-ORG races;
- OpenAPI 0.19.0 validates, generated TypeScript contracts match, and all six
  catalogues validate with only the five declared independent-review warnings;
- final `make verify`: passed — Composer/pnpm audits; Pint; PHPStan level 8;
  128 SQLite tests / 1,390 assertions; 249 PostgreSQL tests / 3,369 assertions;
  locale governance; OpenAPI/generated-client drift; release-contract
  validation; ESLint; recursive TypeScript; and 227 package/web tests;
- final `make e2e`: passed all 14/14 Chromium journeys, including the Learners
  partial-failure, goal-correction, ephemeral-code, axe and 320-pixel journey;
- final `make build`: passed with the existing measured Vite chunk-size
  warning; and
- `POSTGRES_DB=global_english_test make backup-restore-drill`: passed with an
  exact schema, migration-ledger and deletion/learner-goal authority aggregate
  match; measured local RTO 1 second; and
- `make ai-eval` was not required because no prompt, model, provider or
  orchestration behavior changed.

The final repository-wide result is recorded in the ADMIN-LEARNER-01 section
of [QUALITY_REVIEW.md](QUALITY_REVIEW.md).

The immediately preceding REL-01 final evidence remains:

- `make verify`: 120 SQLite tests / 932 assertions, 203 PostgreSQL tests /
  2,759 assertions and 182 package/web tests passed with audits, formatting,
  PHPStan, localization, OpenAPI/client drift, release-contract validation,
  lint and TypeScript checks;
- `make e2e`: 11/11 Chromium journeys passed;
- `make build`: passed;
- production-path Android `testDebugUnitTest assembleDebug`: passed;
- readiness drill: 100/100 HTTP 200, p95 202 ms under its explicit 1,000 ms
  non-production threshold; and
- logical restore drill: exact schema/migration/deletion-state match, 1 second.

## Known limitations and retained gates

- Remote GitHub-hosted CI has not run on a commit containing the current
  working-tree changes; branch protection must require `release-gate`.
- Physical DM-01/DM-02 device evidence, representative low-memory/network
  video evidence, a signed AAB and Play closed/public release evidence remain
  open.
- The readiness drill is not the authenticated normal-API SLO, a 2× pilot
  capacity result or a production soak. Empty-schema query plans prove index
  eligibility, not production latency/selectivity.
- The logical restore drill is not encrypted production backup, offsite
  retention, continuous WAL/PITR or production RPO evidence.
- Production alert routing, qualified legal review and independent security,
  privacy, tenancy, accessibility, language/content and release review remain
  open.
- Kannada, Malayalam, Tamil, Telugu and Hindi UI wording remains
  pending-independent-review; production resolves visibly to approved English.
- No complete human-reviewed pilot pathway or approved production media exists;
  the media library currently has only non-production fixture evidence.
- The existing Vite chunk-size warning and optional Rolldown WASM peer-version
  mismatch remain measured maintenance limitations, not passing performance
  evidence.
- Synchronous privacy export still needs production-volume measurement and an
  approved encrypted case-delivery mechanism.
- No production object-store load test, trusted media verification cache,
  Android native streaming data source or multipart byte-range response exists.
- The operator workspace includes the bounded ADMIN-ORG People,
  ADMIN-LEARNER Learners, ADMIN-CONTENT Content Studio and ADMIN-MEDIA Media
  Library slices. PostgreSQL race evidence and the retained release gates are
  incomplete; groups, activity review, communications, moderation, CMS,
  entitlement and configuration parity remain incomplete.
- Federated search is a bounded live database projection, not a durable index;
  its production-volume latency and concurrent-update behavior have not been
  measured.

## Professional client path

- Staging Friend: [STAGING_CONFIDENCE_CONVERSATION.md](STAGING_CONFIDENCE_CONVERSATION.md)
- Closed beta: [CLOSED_BETA_PILOT_RUNBOOK.md](CLOSED_BETA_PILOT_RUNBOOK.md)
- Android lifecycle: [ANDROID_AUTHENTICATED_REQUEST_LIFECYCLE.md](ANDROID_AUTHENTICATED_REQUEST_LIFECYCLE.md)
- Password authentication: [ops/PASSWORD_AUTHENTICATION_RUNBOOK.md](ops/PASSWORD_AUTHENTICATION_RUNBOOK.md)
- Historical privileged MFA: [PRIVILEGED_MFA_RUNBOOK.md](PRIVILEGED_MFA_RUNBOOK.md)
- Voice deletion: [VOICE_DELETION_RECONCILIATION.md](VOICE_DELETION_RECONCILIATION.md)
- Privacy inventory: [PRIVACY_DATA_INVENTORY.md](PRIVACY_DATA_INVENTORY.md)
- Release gates: [ops/RELEASE_GATES.md](ops/RELEASE_GATES.md)

## DR-0026 password-only authentication decision stop — 11 August 2026

> Historical stop record. APP-0056 subsequently authorized AUTH-PASSWORD-01;
> the completed repository implementation is recorded below.

- The product owner requested username/password sign-in for every learner and
  privileged user, with no TOTP or other second factor.
- This conflicts with the direct/institutional OTP onboarding contract, the
  master plan's privileged-MFA baseline, ADR-0002 and accepted DR-0013 Option A.
  No authentication code, schema, API, credential or session was changed.
- [DR-0026](DR-0026_PASSWORD_ONLY_AUTHENTICATION_REQUEST.md) Option B is now
  accepted under APP-0055 / D-0163 / ADR-0021 with explicit acceptance of the
  documented single-factor risk. Master-plan version 3.3 records
  username/password-only authentication for every role and fresh same-password
  confirmation for sensitive commands.
- The exact LPL-01 Azure Fast Transcription campaign completed before this
  decision stop: four attempts succeeded for four exact assets, with zero
  failures or retries, 18,760,190 uploaded bytes, 1,172,513 billable input
  milliseconds and ₹11.189394 estimated usage under the ₹100 cap. Provider
  candidates remain non-authoritative and protected pending human
  reconciliation.
- [AUTH-PASSWORD-01](AUTH-PASSWORD-01_SPEC.md) now defines the additive
  credential schema, APIs, migration/cutover, privileged recovery authority,
  OTP/TOTP retirement and verification boundary. Runtime implementation is not
  yet authorized. Human reconciliation, English/Kannada review and a
  publishable Level 1 blueprint remain incomplete; the current endpoint still
  requires fresh TOTP and no bypass is authorized.
- LPL-02 and later LPL checkpoints, remaining ADMIN checkpoints, independent
  reviews, physical-device evidence, production and Google Play authorization
  remain outstanding. Remote CI has not run against the current working tree.

## AUTH-PASSWORD-01 repository implementation — 11 August 2026

- APP-0056 / D-0164 authorized the exact specification. The additive migration
  now supplies platform-global canonical usernames, versioned Argon2id
  credentials, session-bound password freshness, enumeration-safe ordinary
  recovery, delayed two-operator privileged recovery, append-only safe security
  evidence and an idempotent global cutover marker.
- Web and Android use username/password while retaining Sanctum cookie/CSRF and
  system-browser Passport S256 PKCE boundaries. Account Security is available
  to learners and operators. The local web port is 5174 and does not conflict
  with Anamaya's reserved port.
- Every formerly MFA-protected route remains on the exact protected set through
  mode-aware `auth.password-confirmed`: pre-cutover migration can still fail
  closed on legacy evidence, while password-only mode accepts only a current
  five-minute same-password confirmation plus the existing independent tenant,
  permission and appointment checks.
- The final migration was rolled back and reapplied before local credentials or
  a cutover marker existed. PostgreSQL then passed the password/Passport slice
  (14 tests / 605 assertions) and the isolated full integration suite (306 /
  4,454); SQLite passed 172 / 1,904. PHPStan reported zero errors, Pint passed,
  dependency audits found no known vulnerabilities, OpenAPI/generated clients,
  release/soft-launch contracts, locale validation, ESLint, TypeScript and all
  workspace tests passed.
- The exact root `make verify` passed end-to-end. `make e2e` prerequisites
  passed and Chromium passed 16/16 axe/reflow/journey
  tests. `make build` passed. The PostgreSQL backup/restore drill passed with a
  two-second RTO. Production-path Capacitor sync plus Android JDK 21
  `testDebugUnitTest assembleDebug` passed.
- The local password-only cutover completed after two users independently
  established credentials and two ready recovery actors were configured. A
  reviewed manifest accounted for all 16 active users as two credential-ready
  users plus 14 explicit content-free local pre-production exclusions. The
  command retired legacy OTP/TOTP material and globally revoked sessions and
  tokens; its replay reported the already-complete state without mutation.
- Pre-cutover recovery evidence is the readable custom PostgreSQL dump
  `/tmp/gse-pre-password-cutover-20260811.dump` (898,961 bytes; SHA-256
  `de3c59e76ffaa6132cf08fb4cd8bb728691f87070b05a5b8f4fafde4c4680374`).
  Aggregate post-cutover checks found zero active sessions, mobile token
  families, OAuth access tokens, MFA factors/challenges/recovery codes and OTP
  challenges. The live local mode endpoint returned `password_only`; a
  synthetic legacy OTP request returned HTTP 410.
- A credential-setup usability follow-up now makes the exact 4–64-character
  username and 15–128-character non-common-password rules visible and
  browser-validated, adds a show-password control, keeps the save action at a
  full usable width, and explains that successful one-time setup signs the
  current session out before the normal Sign in button appears. Rejections now
  retain generic server handling while giving safe corrective guidance. The
  live Vite service on port 5174 hot-reloaded the fix; no user credential was
  inspected, logged or manufactured.
- After routine password sign-in, **Username and password security** is
  collapsed behind an explicit button and does not load its forms until
  opened. The authenticated learner/operator workspace remains the primary
  view, and a separate Sign out action is always available from the compact
  secure-session summary.
- A live follow-up found that the signed-in learner-only session was correctly
  classified non-privileged but the shared security panel still offered
  privileged confirmation and username-change controls. Those controls are now
  shown only for a privileged current session; ordinary users retain password
  change and session management. Failed privileged confirmation has a specific
  safe corrective message instead of the generic load/update error. Focused
  component coverage passes 6 tests, the complete web suite passes 242 tests,
  ESLint and TypeScript pass, and locale governance revision `.7` validates all
  six catalogues with the five declared independent-review warnings.
- A deeper authority check found that both credential-ready accounts are
  learner-only and that exactly one of the 14 exclusions is an active
  permission-derived organization administrator with a verified contact. The
  configured platform recovery actors were not recognized by the session
  classifier; a composite classifier now recognizes only explicit recovery/
  emergency allowlist entries before falling back to organization permissions.
  Focused unit, real-PostgreSQL and PHPStan evidence passes. This grants no
  organization authority.
- DR-0027 records the remaining fail-closed stop: privileged recovery rejects
  a username-less credential-unready target, so the excluded operator cannot
  yet establish a credential. No role mutation, direct credential write,
  OTP/TOTP restore or recovery case was performed.
- Remote CI, independent security/privacy/tenancy/accessibility/language review,
  production blocklist and recovery-delivery evidence, physical-device
  inspection, production deployment and Google Play authorization remain open.
  LPL-01 human reconciliation/English-Kannada review/blueprint and all later
  LPL/ADMIN checkpoints remain separate and incomplete.

## Android release-signing mechanism — 11 August 2026

- The production Android Gradle tree now has a fail-closed upload-signing
  configuration. It accepts only the four `GE_ANDROID_UPLOAD_*` process
  variables, rejects partial input, requires an absolute readable keystore
  outside the repository and refuses release APK/AAB tasks when the inputs are
  absent. Debug builds remain independent of release keys.
- `make android-release-bundle` requires a deployed HTTPS API origin, builds
  the production web bundle, synchronizes only `com.globalenglish.app`, runs
  native debug unit tests and builds the signed release AAB. The release-gate
  contract now protects this command from accidental removal.
- Negative validation passed: Gradle configuration succeeded without secrets,
  while `bundleRelease` failed before execution with the expected missing-
  signing error. Positive validation with a disposable `/tmp` self-signed test
  key passed 172 Gradle tasks and produced a signed 4,807,426-byte AAB with
  SHA-256
  `096097a1871e24d5f46e0410ace3fb5db18a379bccf3b646d20f97fb2c`.
  `jarsigner -verify` reported `jar verified`. Native `testDebugUnitTest
  assembleDebug` separately remained successful; the debug APK is 6,344,957
  bytes with SHA-256
  `721f21e0e92c22c8254f6b75be257fa384c4cb77841be6e75d4433fd77e14b20`.
- This closes the repository signing-wiring gap, not the operational signing
  gate. A product-owner-controlled upload key, protected backup/custody record,
  production-bound signed AAB, Play App Signing, physical DM-01/DM-02 evidence
  and Google Play upload authorization remain outstanding. GitHub publication
  was still paused at this mechanism-only check; the candidate publication
  recorded below supersedes that transient tooling state.

## Release-candidate remote verification — 11 August 2026

- The accumulated governed implementation was committed to
  `codex/confidence-conversation` and published as draft pull request
  [#3](https://github.com/srbhandary1/GESC/pull/3). Repository-local IDE state,
  runtime output, build artifacts and secrets remain excluded.
- Clean GitHub runners exposed two environment assumptions that local ignored
  files had masked. CI now installs the Android command-line SDK through an
  exact-commit-pinned action, and production session-default coverage runs in
  an isolated process. The PostgreSQL HTTP suite additionally fixes its HTTPS
  cookie contract before middleware resolution so host environment variables
  cannot weaken the assertions.
- The final local `make verify` passed Pint, PHPStan, 175 SQLite tests / 1,919
  assertions, 309 PostgreSQL tests / 4,496 assertions, dependency audits,
  locale/OpenAPI/release contracts, ESLint, TypeScript and all 282 JavaScript
  tests. A hostile-environment reproduction separately passed the affected 25
  PostgreSQL authentication/PKCE tests / 800 assertions.
- GitHub Actions run
  [31487637244](https://github.com/srbhandary1/GESC/actions/runs/31487637244)
  passed Verify, Android, Operations and the fail-closed Release Gate. This
  closes the previously open remote-CI gate for this candidate revision.
- Remote repository verification is not production authorization. Independent
  security, privacy, tenancy, accessibility and language reviews; production
  infrastructure/configuration evidence; product-owner upload-key custody;
  physical-device DM-01/DM-02 evidence; a production-bound AAB; Play App
  Signing/upload authority; and LPL-01 human content reconciliation remain
  open.

## Static-IP port-8080 secure entry — 11 August 2026

- The persistent soft-launch service remains enabled and healthy on the Ubuntu
  host. Caddy listens on `0.0.0.0:8080`, the LAN address remains
  `192.168.1.3`, and an independent internet-side TCP check confirmed that
  `117.254.108.143:8080` is reachable through the router forward.
- When an approved HTTPS origin exists, the port-8080 listener now returns HTTP
  `307` to the same path and query on that origin. This preserves the requested
  static-IP entry while preventing usernames, passwords and Secure session
  cookies from being transported over public plain HTTP. It does not expose or
  weaken localhost-only recovery controls.
- The existing LPL-01 reconciliation aid already uses the authenticated
  operator API with fresh same-password confirmation. Its stale
  `localhost-only` / `fresh-TOTP` labels now describe the controlled HTTPS
  soft-launch and password-confirmed contract accurately; no API,
  authorization, candidate handling or browser-storage behavior changed.
- The managed service was restarted against the changed runtime. Loopback and
  LAN requests both returned the expected redirect, the HTTPS readiness
  endpoint returned `ready`, and soft-launch readiness reported 17 passes, one
  intentional disabled-live-AI warning and zero failures.
- `make verify` passed Pint, PHPStan, 175 SQLite tests / 1,919 assertions, 309
  PostgreSQL tests / 4,496 assertions, audits, contracts, ESLint, TypeScript
  and 282 JavaScript tests. `make build` passed with only the existing Vite
  chunk-size advisory.
- This is invite-only remote commissioning access, not production/public
  release authorization. The HTTPS tunnel hostname is ephemeral and remains
  authoritative from the protected runtime state; a stable named HTTPS tunnel
  and the outstanding human/release gates remain separate.

## Remote password sign-in process ownership correction — 11 August 2026

- A live remote sign-in loop exposed that the managed API PID was stopped while
  port `8000` remained owned by an older manually started `artisan serve`
  parent/child pair. Managed restarts logged `Address in use`, yet readiness
  was falsely satisfied by that stale process with its obsolete tunnel-origin
  environment.
- The soft-launch runner now launches the repository-pinned Laravel PHP server
  router directly from `apps/api/public` and records the actual long-lived
  server PID. Stop/restart therefore owns the process that holds port `8000`
  and cannot silently retain an old stateful-domain/session origin.
- No credential, request body, username or password was inspected. Diagnosis
  used only process identity, bind-error evidence, health responses and
  non-secret origin/session configuration.
- The exact stale parent/child pair was stopped after the managed unit was
  stopped. The corrected unit then started one directly owned API listener and
  reports API, worker, scheduler, both Caddy listeners and tunnel as running.
  A credential-free live protocol probe returned `password_only` / HTTP 200,
  issued the XSRF and `__Host-global_english_session` cookies, and reached the
  password controller with an expected generic HTTP 422 for a synthetic
  invalid account rather than a stateful/CSRF failure.
- The post-correction `make verify` passed Pint, PHPStan, 175 SQLite tests /
  1,919 assertions, 309 PostgreSQL tests / 4,497 assertions, audits, contracts,
  ESLint, TypeScript and 282 JavaScript tests.

The original rich-text body is unavailable. A separately labeled newly authored
Level 1 `can` blueprint now provides an objective, Talk scenario, weighted
Check items and Apply rubric. It remains unpublished pending English academic
review, Kannada creation/review and governed content attachment.

The user approved the authored English draft on 14 August 2026. Kannada
guidance is now drafted separately and remains pending independent Kannada
review; no publication or runtime attachment has occurred.

The user subsequently approved the Kannada guidance. Both language reviews are
now recorded as approved for private draft use; governed content attachment and
publication are still outstanding.

LPL-02 foundation contract is now accepted under ADR-0024 and
`docs/specs/LPL-02_TYPED_LESSON_PRACTICE_ATTACHMENT.md`. Runtime implementation
has not yet begun; LPL-01 remains unpublished until the new schema and API are
implemented and verified.
