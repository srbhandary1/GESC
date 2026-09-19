# Quality review

## APP-0104 native home identity and utility-bar correction — 19 September 2026

### Scope reviewed

- Native home-header identity, control alignment and guest theme access.
- Authenticated live-AI/account adjacency, Kannada greeting governance and
  developer attribution.

### Review result

| Check | Result | Evidence |
|---|---|---|
| App identity | Pass (automated) | Existing localized `app.name` is no longer suppressed by native narrow-screen CSS |
| Utility controls | Pass (automated) | Native toolbar is end-aligned; guest Theme and Language selects retain accessible labels and three theme choices |
| AI allowance | Pass | Existing API-authoritative chip remains immediately before Account for authenticated Android and web sessions; no guest value is invented |
| Kannada greeting | Pass with human gate | `demo.phase1.home.greeting` is `ಶುಭ ದಿನ`; canonical hash validates; independent review remains open |
| Developer attribution | Pass (automated) | Footer contains the supplied legal company name and a `mailto:sales@northstar-es.in` link |
| Focused tests | Pass | App/theme 24/24; localization 36/36; workspace typecheck green |

### Verdict

The bounded source correction passes focused automated review. Full repository,
Android package, VPS deployment and physical-device evidence are recorded after
the exact committed candidate is built and promoted. Kannada linguistic review
and all existing UX-REM-01/content residuals remain open.

## KANNADA-LEARNER-UI-FIX-02 learner chrome catalogue correction — 18 September 2026

### Scope reviewed

- English-identical Kannada values in Plans, Progress, delayed review,
  model-audio controls and entitlement messaging.
- Catalogue revision/hash governance and regression coverage.
- Repository verification, end-to-end and production-build gates.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Catalogue scope | Pass with human gate | 51 English-identical learner-interface values replaced; intentional English content, authoritative plan names and `GST` retained |
| Semantics | Pass | Interface copy only; no learning, review, entitlement, payment, consent, API or persistence behavior changed |
| Regression coverage | Pass | Scoped test rejects Kannada values identical to English source except explicit `subscription.gst`; localization 36/36; LearnerJourney 14/14 |
| Governance | Pass | `ui-kn-IN-2026-09-18.2`; canonical SHA-256 matches; `pending-independent-review` retained |
| `make verify` | Pass | SQLite 330/330; PostgreSQL 360/360; web 467/467; five expected independent-review warnings |
| `make e2e` | Pass | API 4/4 (103 assertions); Playwright 30/30 including Kannada first use |
| `make build` | Pass | Laravel/Vite production build; existing large-chunk advisory only |
| Android build | Pass | Commit `1bdea67`; Gradle tests/assemble `BUILD SUCCESSFUL` (103 tasks); SHA-256 `28bda09e…dcfff4c`, 83,050,428 bytes |
| Stable-host deployment | Pass | Immutable release `/srv/global-spoken-english/releases/1bdea6793424`; public live/ready pass; readiness 27 pass / 1 existing publication warning / 0 fail; published APK hash matches |
| RMX3085 installation | Pass | Preserve-data `adb install -r` succeeded on the connected API-33 handset |
| RMX3085 visual inspection | Pass with human gate | Authenticated Kannada Progress and Plans rendered corrected interface copy; authoritative API plan names and intentional English-learning content remain English; independent linguistic approval remains open; temporary captures deleted |

### Verdict

Repository implementation, packaging, stable-host deployment, preserve-data
installation and the bounded Plans/Progress visual inspection pass. The
Kannada copy remains an implementation draft pending independent human
linguistic and accessibility review under ADR-0005.

## KANNADA-ONBOARDING-FIX-01 first-use catalogue correction — 18 September 2026

### Scope reviewed

- Physical Android reproduction of mixed Kannada/English first-use behavior.
- `kn-IN` first-use UI values, catalogue governance and regression coverage.
- Correct stable-origin Android packaging, paid-minutes navigation and
  diagnostic-capture deletion boundary.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Root cause | Pass | Every English-identical `learner.onboarding.*` value in `kn-IN` was identified; locale resolution itself was correct |
| First-use interface draft | Pass with human gate | No onboarding value is now identical to the English source; English learning model remains intentionally English; `pending-independent-review` retained |
| Consent semantics | Pass | Separate recording/transcription and automated-conversation choices remain optional, unselected, withdrawable and accurately described |
| Governance | Pass | `ui-kn-IN-2026-09-18.1`; canonical hash validates; six-locale key/placeholder parity unchanged |
| Focused tests | Pass | Localization 35/35; onboarding and paid-minutes chip 9/9 |
| `make verify` | Pass | SQLite 330/330; PostgreSQL 360/360; web 467/467; five expected independent-review warnings |
| `make e2e` | Pass | API 4/4 (103 assertions); Playwright 30/30 including Kannada first use |
| `make build` | Pass | Laravel/Vite production build; existing large-chunk advisory only |
| Android API origin | Pass after correction | Bare `https://ai.globalspokenenglish.com` accepted; the erroneous `/api/v1`-prefixed build failed closed and is superseded |
| Paid-minutes regression | Pass on RMX3085 | Retained 120-minute entitlement visible; chip opened Plans; `Level undefined` absent |
| Deployed candidate | Pass | Commit `4f4f4fc`; release `/srv/global-spoken-english/releases/4f4f4fcbf04f`; public/local APK SHA-256 `dddd00cc…c388b`, 83,049,774 bytes; preserve-data install succeeded |
| Diagnostic images | Pass | All session captures and UI dumps were deleted from workstation `/tmp` and handset `/sdcard`; none entered Git, the VPS release or production data |

### Verdict

The implementation defect is corrected and all automated gates pass. Kannada
copy remains an implementation draft pending the independent human linguistic
review required by ADR-0005. Production approved-only release must not treat
this agent-authored draft as approved.

## PAYMENT-RAZORPAY-01 server-authoritative Direct checkout — 18 September 2026

### Scope reviewed

- Razorpay Orders API adapter, hosted Checkout handoff, callback confirmation,
  raw-body webhooks and provider reconciliation.
- Immutable checkout authority, ambiguous-response replay safety, exact-once
  entitlement grant, talk-pack eligibility and organization/learner isolation.
- API/OpenAPI/client contract, migration, external configuration and sandbox
  deployment boundary.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Browser trust boundary | Pass | Callback signature is verified; provider order/payment are reread; browser callback alone cannot grant |
| Amount and product authority | Pass | INR, amount, receipt, order/payment linkage and frozen plan snapshot must match |
| Idempotency | Pass | Local pending record precedes provider call; ambiguous response replay performs one capture attempt and creates one checkout |
| Exact-once grant | Pass | Checkout row is locked; callback/webhook replay produces one entitlement |
| Webhook privacy/integrity | Pass | Exact raw-body HMAC before parse; event dedupe; digest and IDs only; malformed/oversized bodies rejected |
| Payment retry semantics | Pass | `payment.failed` does not terminally fail an order that may receive a later valid attempt |
| Tenant/learner isolation | Pass | Cross-learner reconcile and confirm denied without mutation/provider dispatch |
| Talk packs | Pass | Active non-demo paid entitlement required before order and again under completion lock |
| Secret handling | Pass | Secret/webhook values are env-only; only public key reaches client; none entered Git |
| Focused API | Pass | 20 tests, 131 assertions |
| Focused web | Pass | SubscriptionPanel 9/9; workspace typecheck green |
| API contract | Pass | OpenAPI lint/policy/generation drift green |
| `make verify` | Pass | SQLite 330/330; PostgreSQL 360/360; web 467/467; static/security/contract gates green (two moderate pnpm advisories below fail threshold) |
| `make e2e` | Pass | API 4/4 (103 assertions); Playwright 30/30 |
| `make build` | Pass | Laravel/Vite production build; existing large-chunk advisory only |
| Provider sandbox smoke | Pass with residual | RMX3085/API 33: paid order `order_TdZJ0LserJ8wHl`, captured payment `pay_TdZNX2YPPJEk9X`, confirm HTTP 200, checkout `01M2TP4FF5XX30W9SK9ERP94E7` completed and exactly one active entitlement granted; Dashboard webhook remains unregistered (`payment_webhook_events=0`) |
| Android sign-in after immutable release switch | Pass after deployment repair | Initial 500 correlated to absent Passport PEM files; identical prior-release keys restored with matching SHA-256, then mobile password sign-in returned 200; readiness now fails closed when keys are absent |
| Hardened follow-up deployment | Pass | Commit `3fedf08` active at `/srv/global-spoken-english/releases/3fedf08f6b80`; public live/ready pass; readiness 27 pass / 1 content-publication warning / 0 fail |
| Follow-up Android artifact | Superseded packaging defect | SHA-256 `5ff51e5527e4fc3b1b81bab54e7dd351222044294985e76c69c4e944b08657fb` embedded an invalid `/api/v1`-prefixed origin and failed authentication closed; it was replaced, not accepted |
| Paid-minutes callback | Pass on device | Corrected bare-origin build retained the 120-minute entitlement and opened Plans without `Level undefined` |
| Provider-checkout system inset | Open defect | On realme RMX3085/API 33 three-button navigation, provider-owned fixed-bottom Continue/Yes-Cancel controls extend under system navigation; keyboard focus completed the test but is not acceptable broad-release evidence |
| Live capture | Blocked | No `rzp_live_` credential; canary/refund/reconciliation evidence absent |

### Verdict

Implementation, repository gates, stable-host sandbox deployment and one
server-verified test payment pass. This checkpoint cannot be described as live
payment: the supplied key is a Razorpay test key, and production DI refuses
test mode by design. Live activation still needs the APP-0100 conditions,
secret rotation, Dashboard webhook evidence and the open RMX3085 checkout-inset
defect repaired and retested. Existing UX-REM-01 physical accessibility gates
remain independent.

## APP-0103 compact title-bar and governed Direct-content recovery — 19 September 2026

### Scope reviewed

- Compact Android authenticated title-bar controls and Account/Sign-out menu.
- Six-catalogue key parity and governance revisions.
- Installation-independent, read-only Direct commissioning audit resolution.
- Backup-first recovery inventory for the missing VPS Levels 1–17.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Account menu | Pass | Dedicated component tests cover open, Account action, Sign out, blur and Escape; App integration keeps Sign out unavailable until the menu is opened |
| Compact controls | Pass (browser) | Language/theme retain native selects and accessible names; native header controls are 48×48 and horizontal at narrow widths |
| 320px / 200% regression | Pass | `level-path-offline.spec.ts` exact affected journey passes with no document overflow |
| Physical title bar | Pass on RMX3085/API 33 | Final screenshot inspected; one app-owned header, compact horizontal controls, visible green/tint contrast; UI hierarchy contains no `android:id/action_bar_container` |
| Both Android wrappers | Pass | Production and demo manifests use `AppTheme.NoActionBar`; both activities enforce it before `BridgeActivity` decor creation; both Gradle compile/unit-test gates pass |
| Catalogue contract | Pass | Six catalogues include `session.account_settings`; canonical governance hashes validate; five expected non-English independent-review warnings remain |
| Tenant/program resolution | Pass | `DirectPathwayCommissioningAuditTest` proves result IDs equal the current seeded stable-key records rather than fixed ULIDs |
| Production rollback point | Pass | Encrypted VPS backup `soft-launch-20260919T035337Z.sql.gz.enc`; SHA-256 `1b96eb42e147e5b1bdb4d944db487955c2e21d52179b4afc7006c7344481986d` |
| Restore-set reconciliation | Pass | 17 latest published blueprints, 68 attachments, 67 unique audio assets; concepts and current published IDs reconcile to the LPL-05 wave evidence |
| Accepted media bytes | Blocked safely | None of the 67 exact MP3 objects is present in the local lesson-media store or recovered archives; importer stopped before any production content write |
| `make verify` | Pass | SQLite 330/330; PostgreSQL 360/360; web 468/468; static, localization, contract and security gates green |
| `make e2e` | Pass | API 4/4 (103 assertions); Playwright 30/30 |
| `make build` | Pass | Production Laravel/Vite build completed; existing large-chunk advisory only |
| Deployment/package | Pass | Active immutable release `f6e0881`; public live/ready; readiness 27/1 warning/0 fail; installed/public APK SHA-256 `3e5e4958…e03a97c`, 83,050,611 bytes |

### Verdict

The title-bar behavior and portable read-only commissioning audit pass their
repository gates. The 17-level production restoration is **not complete** and
no production content row was written: importing metadata without the 67
hash-matching private objects would violate ADR-0004 and produce broken lesson
delivery. Recovery may resume only after those exact objects are restored from
the authorized legacy source or another verified backup. UX-REM-01's existing
physical-device residuals remain open and are not reclassified by this work.

## UX-REM-01 accessibility, reflow and adaptive layout — 19 September 2026

### Scope reviewed

- Five-destination enlarged-text reflow, authenticated header/paid-minutes
  reflow and short Android landscape behavior.
- Variant C high-contrast navigation legibility without global brand changes.
- Onboarding and quiz safe-area/IME/bottom-navigation offsets.
- Exact automated gates plus bounded attempts on the RMX3085 and API 36 AVD.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Scope isolation | Pass | CSS and test changes only; no history, storage, scoring, API, OpenAPI, provider or authoritative-record behavior changed |
| 320/360/412 at 200% | Pass (browser) | English + Kannada authenticated harness; no document/control overflow; all five navigation targets ≥48×48 |
| Header and paid-minutes | Pass (browser) | Narrow toolbar becomes one column; paid `82 live-AI minutes available` control remains bounded |
| Landscape | Pass (browser) | 800×412 native simulation; header is non-sticky and five-destination grid remains usable |
| High contrast / reduced motion | Pass (browser) | Forced-colour labels/glyphs opaque; active border retained; reduced-motion path exercised |
| Onboarding insets | Pass (browser) | 320px/200% English and Kannada first screens consume all native safe-area variables and 120px IME inset |
| Quiz action | Pass (browser) | Closed action above bottom nav; open action above 220px IME; no doubled IME padding |
| Focused journeys | Pass | 11/11 affected Playwright journeys |
| `make verify` | Pass | SQLite 321/321; PostgreSQL 360/360; web 466/466; all static/contract gates green |
| `make e2e` | Pass | API 4/4 (103 assertions); Playwright 30/30 |
| `make build` | Pass | Production Vite/Laravel build; existing chunk warning only |
| Android package | Pass | `BUILD SUCCESSFUL in 7s`; installed/on-device SHA `e132cc26…9941db9`, 82,981,429 bytes |
| Initial RMX3085 shell attempt | Blocked, superseded in part below | OEM denied shell `WRITE_SETTINGS` and `WRITE_SECURE_SETTINGS`; the later settings-UI run reached density 540 but the device still offers no 2.0 font setting |
| Initial authenticated physical journey | Not run, superseded in part below | Initial installed state was public guest Home; the later unlocked run covered authenticated Home/Progress/Plans/Speak but not first use or quiz |
| API 36 AVD | Blocked | No KVM; software AVD reached API 36 ADB but never `sys.boot_completed` or focused an app |
| Initial TalkBack / split-screen attempt | Not run, superseded in part below | Later split-screen passed; TalkBack critical journey remains not run |
| Exact 320 CSS px | Partial physical pass | OEM Large display size produced density 540 on 1080px (exact 320 CSS px); portrait visually bounded, short landscape failed usable reflow |
| Split-screen | Pass on RMX3085/API 33 | App task entered split-screen-primary alongside Settings, preserved the signed-in task and remained navigable |
| Enlarged text | Fail on RMX3085 maximum 1.6 | English `Progress` isolated its final letter; Kannada destination labels wrapped awkwardly; exact 2.0 remains unavailable |
| IME-open fallback | Fail | Speak → Type instead focused the field and Android reported IME open, but the field and Send action were not visible above the keyboard |
| OEM high contrast | Fail | Bottom-navigation labels faded into the white background |
| Plans return/focus | Fail | Continue free left Plans expanded; system Back closed it but focused Home instead of the opener |
| TalkBack / physical quiz CTA | Not run | Current learner has completed first use and has no published levels; no result is inferred |
| Evidence-update `make verify` | Pass | Composer audit/Pint/PHPStan; SQLite 330/330 (3,147 assertions); PostgreSQL 360/360 (5,224 assertions); locale/OpenAPI/release/soft-launch, lint and typecheck; web 467/467 |

### Verdict

The UX-REM-01 implementation and automated acceptance are complete. One
physical row (split-screen task preservation) now passes, but the checkpoint
remains **in progress** because enlarged text, short landscape, IME visibility,
high contrast, Plans focus return, TalkBack and physical quiz evidence remain
open. Bounded defects `UXREM01-DEV-01` through `UXREM01-DEV-06` are recorded;
no fixes were made. No later checkpoint may start without passing evidence or
an explicit residual decision.

Detailed evidence:
[Android Mobile UX Remediation Evidence](ANDROID_MOBILE_UX_REMEDIATION_EVIDENCE.md#ux-rem-01--accessibility-reflow-and-adaptive-layout).

## UX-REM-00 Android candidate and device evidence path — 18 September 2026

### Scope reviewed

- Establish the Mac USB ADB → Linux reverse-tunnel path without changing app
  data or device configuration.
- Build one exact production-package debug candidate from current HEAD against
  the stable HTTPS origin, install it with data preservation and prove the
  installed bytes match.
- Capture an unlocked non-personal baseline without promoting any later device
  or accessibility row to PASS.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Sequential scope | Pass | D-0319 records one-checkpoint-at-a-time execution and retains every stated human/release gate |
| ADB bridge | Pass | `J7GUXWEENBNZTW85` visible through `ADB_SERVER_SOCKET=tcp:127.0.0.1:5038` |
| Device identity | Pass | realme RMX3085 / Android 13 / API 33 / WebView 151.0.7922.199 / three-button navigation |
| Candidate build | Pass | Exact host rerun of `make android-closed-beta`: `BUILD SUCCESSFUL in 7s`; 103 actionable Gradle tasks |
| Candidate identity | Pass | Git `b1660dc`; APK `fffbcec64978b98eaad9658670245fa03a27acf39b64e2612dbedfd663665012`; 82,981,433 bytes; `com.globalenglish.app` 1.0 (1); target SDK 36 |
| Installation integrity | Pass | Candidate and pulled installed `base.apk` SHA-256 and byte count match exactly |
| Baseline privacy | Pass | Unlocked public guest Home only; no learner identity, credential, authored learner text, transcript or audio visible; artefacts remain in `/tmp` |
| Device settings | Pass | No font, locale, navigation, contrast, permission or developer setting changed; no restoration needed |
| Accessibility/device verdicts | Not assessed | Baseline capture is not evidence for UX-REM-01 or any open §10 matrix cell |

### Residuals

- The public baseline exposes only the guest surface. Authenticated critical
  journeys require the approved test learner to sign in during their relevant
  checkpoints.
- The native UI Automator hierarchy identifies the WebView but does not expand
  its DOM semantics; TalkBack/browser evidence remains required.
- This is a debug-signed test APK, not a signed Play release artefact.

Detailed evidence:
[Android Mobile UX Remediation Evidence](ANDROID_MOBILE_UX_REMEDIATION_EVIDENCE.md#ux-rem-00--candidate-and-device-evidence-path).

## PROVIDER-LIVE-WIRING-01 Azure, Sarvam and ElevenLabs — 18 September 2026

### Scope reviewed

- Replace only the ElevenLabs TTS/recorded-STT scaffolds with project-owned
  REST transports and preserve the ADR-0038 route/dispatch ceiling.
- Keep India residency, zero retention, spend, credential, deletion, consent
  and fallback controls fail-closed.
- Recheck the deployed credential without sending learner or synthetic speech,
  then run all repository/provider gates.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Project-owned adapters | Pass | ElevenLabs TTS and recorded STT expose only existing `TextToSpeechProvider` / `RecordedSpeechToTextProvider` contracts; no vendor SDK type leaves the adapters |
| Data residency | Pass in source; blocked operationally | Exact `api.in.residency.elevenlabs.io` allowlist; alternate host, user info, port, redirect, query-controlled endpoint and repository secret paths are rejected |
| Zero retention | Pass in source | Both provider URLs append fixed `enable_logging=false`; clients cannot override it |
| Credential/evidence gate | Pass | External readable owner/mode-0600 secret and evidence only; learner-content approval, future vendor/spend validity, ₹500 cap, rotation receipt and matching content-free deletion receipt are mandatory |
| Response safety | Pass | HTTPS verification, no redirects, connect/request timeouts, bounded response bodies, content-type/JSON/PCM validation and safe status mapping |
| TTS product compatibility | Pass | Provider PCM is wrapped as owned 24 kHz mono PCM WAV for the existing private artifact/player path; input remains capped at 160 characters |
| Recorded STT semantics | Pass | English `en-IN` WAV only; transcript is bounded UTF-8; confidence is derived only from returned word log probabilities and is 0 when unavailable |
| Dispatch topology | Pass | Azure guidance remains primary, ElevenLabs is at most one provider fallback, and text-first remains the non-provider recovery; Sarvam→Azure recorded STT is unchanged |
| Unimplemented candidates | Pass | ElevenLabs/Sarvam realtime and Sarvam Bulbul classes remain explicitly `implementation_unavailable` and cannot fall through to a null return |
| Focused tests | Pass | 17 tests / 41 assertions for adapters, authority, maximum dispatch and provider-usage policy |
| `make verify` | Pass | Pint and PHPStan green; SQLite 321/321, PostgreSQL 360/360, web 466/466; OpenAPI/release/localization/lint/typecheck green |
| `make e2e` | Pass | API 4/4 (103 assertions); Playwright 29/29 |
| `make ai-eval` | Pass on host rerun | 15/15 deterministic, zero estimated provider cost; initial sandbox attempt could not reach loopback PostgreSQL and is not counted as a pass |
| `make build` | Pass | Laravel cache cycle and Vite production build completed; existing chunk-size warning only |
| Existing ElevenLabs key | Blocked | Metadata-only India models request returned HTTP 400 `invalid_api_key`; no audio/text payload was sent |
| Azure OpenAI | Blocked on human MFA | Management-plane read requires device-code MFA; existing synthetic provider call remains HTTP 403 |
| Pre-deploy recovery | Pass | Encrypted database backup ciphertext SHA-256 `1faf9c0b…249d8c`; previous immutable release `56f2caa…` retained |
| Immutable deployment | Pass | Commit `5376a20` staged at `/srv/global-spoken-english/releases/5376a20d4f5f`; archive SHA-256 `da4d0ab3…3f2e2`; autoload/syntax/route smoke passed before atomic switch |
| Post-deploy health | Pass | Supervised service active; public `https://ai.globalspokenenglish.com/api/v1/health/{live,ready}` returned `ok` / `ready` |
| Deployed dispatch posture | Pass, fail-closed | Safe TTS denial codes: `http_enablement_not_authorized`, `kill_switch`, `dispatch_flag_off`, `evidence_missing`, `secret_unbound` |

### Residual activation steps

- Human must complete Azure MFA before the OpenAI resource/network policy can
  be inspected or changed.
- ElevenLabs administrator must issue a new key in the separate India isolated
  workspace and choose/provide a voice ID. The disclosed/invalid key must be
  revoked; only then may rotation/deletion receipts be recorded.
- Run content-free account/model checks, synthetic TTS and synthetic-WAV STT,
  verify zero-retention/deletion behavior, then turn on the global router and
  only the passing candidate. Until then, VPS flags stay off and kill switches
  stay on.
- Independent privacy/security/tenancy review remains a production gate. No
  Play or payment authority is implied.

## STABLE-HOST-CUTOVER-01 VPS DNS/TLS and Android cutover — 18 September 2026

### Scope reviewed

- Prove the stable A record, public 80/443 transport and restricted raw ports.
- Preserve rollback, switch the authoritative origin, issue TLS and stop the
  rotating tunnel.
- Rebuild and externally verify the production-package debug APK.

### Review result

| Check | Result | Evidence |
|---|---|---|
| DNS | Pass | `ai.globalspokenenglish.com` A = `172.235.18.171` |
| Pre-cutover recovery | Pass | Encrypted DB ciphertext SHA-256 `7ea092af…bdb05ef8`; UFW and mode-0600 runtime env snapshots retained |
| Public-port isolation | Pass | Root systemd sockets listen on 80/443; UFW allows only OpenSSH/80/443; external raw 8080 timed out |
| HTTP canonical redirect | Pass | HTTP 308 to `https://ai.globalspokenenglish.com/` |
| TLS | Pass | Let's Encrypt `YE2`; SAN `ai.globalspokenenglish.com`; valid 2026-09-18 through 2026-12-17 |
| Runtime origin | Pass | `APP_URL=https://ai.globalspokenenglish.com`; stable hostname, Secure cookies and same-origin Sanctum pass readiness |
| Public API | Pass | live=`ok`, ready=`ready`; browser and mobile password mode=`password_only` with the public PKCE client |
| Public policy pages | Pass | `/privacy.html` and `/account-deletion.html` return success over stable HTTPS |
| Provider regression | Pass | Post-cutover synthetic Azure guidance TTS → Sarvam STT round trip returned both expected provider keys and a transcript |
| Deletion health | Pass | 0 active, due, retrying, dead-lettered or stale source deletions |
| VPS readiness | Pass with one content warning | 26 pass / 1 warn / 0 fail; missing active publication head/enrollment pin |
| Android artifact | Pass for debug testing | Gradle unit tests/assemble passed; external download matches SHA-256 `37793a48…3a2f15`, 82,981,384 bytes, package `com.globalenglish.app`, target SDK 36 |

### Known limitations

- Debug signing is not Play signing; no AAB was produced or uploaded.
- Azure OpenAI still returns HTTP 403, ElevenLabs remains unimplemented,
  payment remains disabled and Content Studio still lacks the active
  publication/enrollment pin.
- The root password disclosed earlier must still be rotated or password/root
  SSH disabled after tested key-based break-glass access exists.

## PROVIDER-ACTIVATION-01 bounded live-provider activation — 18 September 2026

### Scope reviewed

- Back up authoritative state and runtime configuration before dispatch.
- Enable only existing Azure OpenAI, Azure Speech and Sarvam provider paths;
  preserve consent, retention, deletion, tenancy and fail-closed controls.
- Execute synthetic provider checks without learner audio or learner text.
- Rebuild and externally verify the Android debug artifact after the temporary
  HTTPS origin changed.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Pre-change database recovery | Pass | Encrypted dump `soft-launch-20260918T101853Z.sql.gz.enc`; verified ciphertext SHA-256 `08a1b5ec…f633c863` |
| Runtime configuration rollback | Pass | Owner-only snapshot `~/ge-provider-activation-rollbacks/20260918T102300Z/` |
| Consent/tenancy boundary | Preserved | ADR-0007 purposes and `VOICE_ALLOWED_ORGANIZATION_IDS=01KYS97G7MX374P303XJHDXT72`; no bypass added |
| Azure guidance TTS | Pass | Synthetic text only; `azure-speech-guidance`, 722 ms, private WAV result |
| Sarvam recorded STT | Pass | Azure-generated synthetic WAV only; `indic-http-stt`, 417 ms, transcript present |
| Azure recorded-STT failover | Pass | Azure-generated synthetic WAV only; `azure-speech-soft-launch-stt`, 355 ms, transcript present |
| Azure OpenAI | Blocked externally | Adapter/configuration preflight passes and switch is true; synthetic call returned HTTP 403 / `provider_authorization_failed`; Azure CLI requires interactive MFA before resource network policy can be changed |
| ElevenLabs | Not implemented / not activated | Provider methods stop at `SpeechDispatchAuthority`; router is hard-disabled and no live HTTP response path exists |
| Source-audio deletion outbox | Pass | `voice:deletion-status`: healthy; 0 active, due, retrying, dead-lettered or stale entries |
| Runtime health | Pass | systemd supervisor recovered after one automatic retry; live and ready endpoints returned `ok` after Quick Tunnel restart |
| VPS readiness | Pass with one launch warning | 23 pass / 1 warn / 0 fail; no active Content Studio publication head/enrollment pin |
| Android artifact | Pass for debug testing | `make android-closed-beta` passed; public bytes match SHA-256 `b7bd4c4b…8371fc2`, 82,981,426 bytes, `com.globalenglish.app`, target SDK 36 |
| Repository verification | Pass | `make verify`: 316 SQLite / 360 PostgreSQL / 466 web; `make e2e`: API 4/4 + Playwright 29/29; `make ai-eval`: 15/15 deterministic; `make build`: pass |

### Known limitations

- The runtime remains the invite-only `APP_ENV=soft-launch` stack. Ordinary
  `production` DI is intentionally fail-closed for these providers in the
  deployed revision, so a production-environment promotion was not claimed.
- Azure OpenAI needs an Azure owner to complete interactive MFA and correct or
  confirm resource authorization for the VPS before conversation works.
- ElevenLabs needs actual adapter/router implementation, key rotation and the
  accepted ADR-0038 evidence path; it cannot be enabled by configuration.
- Stable DNS/TLS, root credential rotation, off-box backup/PITR, remaining
  independent and device/human reviews, production signing/Play and live
  payment remain open.
- Content Studio still needs an active publication head and the target learner
  enrollment pinned to it before production curriculum delivery is claimed.

## VPS-TEST-SECRETS-01 testing credential sync — 18 September 2026

### Scope reviewed

- Transfer only the four Global English provider credential families and their
  evidence/configuration from the workstation to the invite-only VPS.
- Preserve every VPS-generated platform secret and keep provider/payment
  dispatch disabled.
- Restart the supervised runtime, run non-dispatch preflights, rebuild the
  debug closed-beta APK for the rotated Quick Tunnel and verify externally.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Pre-change recovery | Pass | Protected pre-sync archive at `~/ge-secret-rollbacks/20260918T090500Z/pre-secret-sync.tar` |
| Credential scope | Pass | Azure OpenAI, Azure Speech, Sarvam Indic STT and ElevenLabs only; four expected secret files are non-empty and mode `0600` |
| Platform-secret preservation | Pass | VPS app/database/storage/Passport/digest/backup secrets were not imported or overwritten |
| Exclusions | Pass | No personal SSH/Azure session, unrelated project, historic diagnostic-token, stale test-user, Android-signing or payment credential copied |
| Runtime stop rules | Pass | AI/Speech/media live switches false; voice upload false; payment disabled |
| Soft-launch readiness | Pass with expected warnings | 21 pass / 2 warn / 0 fail; only disabled live Azure switch and absent publication head remain |
| Wave 3.8 no-call preflight | Pass | `ready`, `checkpoint_closed=true`, `dispatch_authorized=false`; key rotation/deletion test retained as live-deployment deferrals |
| Wave 6 no-call preflight | Fail-closed as intended | 20 pass / 4 fail because voice upload/live authorization and live adapter binding remain disabled; no provider call made |
| Supervised runtime | Pass | systemd user service active; live and ready endpoints externally healthy after restart |
| Rebuilt Android test artifact | Pass | Full `make android-closed-beta` exited 0; package retains target SDK 36 and embeds the rotated tunnel; public SHA-256 `56a0dc74…e47c` |

### Security, privacy and tenancy review

- Transfer used SSH host-key verification. The temporary archive was mode
  `0600`, hash-verified before extraction and deleted from both endpoints.
- Secret values and hashes are not written to Git, logs or this evidence.
  Provider files and copied operational evidence are owner-only on the VPS.
- No learner data, audio or transcript moved, and no provider request was
  made. Tenant scoping and authoritative records were unchanged.
- Copied credentials must be rotated before production. Credential presence is
  not spend, privacy, security, deletion, production or Play authorization.

### Residuals

- Stable DNS is still absent, so every Quick Tunnel restart requires another
  Android debug rebuild. The present endpoint is temporary.
- Wave 6 stays deliberately blocked until an exact later authorization enables
  voice upload/live dispatch and its full preflight is green.
- Root-password rotation/disablement, off-box backup/PITR, active publication,
  governed users, independent reviews, production signing, Play and payment
  remain open.

## VPS-SOFT-LAUNCH-01 new VPS deployment — 18 September 2026

### Scope reviewed

- Deploy exact repository revision
  `56f2caa74eed978ba4b20ef20dd14e93e21f6b2c` to the owner-supplied Ubuntu VPS.
- Provision the repository-pinned runtime, private data services, migrations,
  foundation seed, public Android PKCE client, supervised application processes,
  temporary HTTPS entry and tested restore point.
- Preserve the existing invite-only, no-live-payment and no-live-provider
  posture. No application behavior, API/OpenAPI, schema or Android source
  changes are in scope.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Exact release | Pass | Immutable release and `.deployment-revision` contain `56f2caa74eed978ba4b20ef20dd14e93e21f6b2c`; archive SHA-256 `779604d0…cf27` |
| Least-privilege runtime | Pass with SSH residual | Application runs as `globalenglish`; UFW default-deny permits only SSH; fail2ban active; root/password SSH remains owner-controlled and must be rotated/disabled before production |
| Private dependencies | Pass | PostgreSQL, Redis, SeaweedFS, Mailpit and OTP stub healthy; stateful host bindings are loopback-only |
| Persistence | Pass | `global-english-soft-launch.service` enabled/active with user linger; API, worker, scheduler, Caddy and tunnel supervised |
| External app | Pass | HTTPS live=`ok`, ready=`ready`, password mode=`password_only`; corrected VPS-bound APK public-route hash `034fa83d…d9e9`, package `com.globalenglish.app`, target SDK 36 |
| Payment/provider stop rules | Pass | Payment disabled; voice upload/driver disabled; all Azure/OpenAI/Speech/media live-request switches false |
| Backup/restore | Pass with production residual | Encrypted backup SHA-256 `fadb111c…8ee7`; isolated restore passed in 4 seconds without live overwrite. Same-host custody is not off-box or PITR |
| Repository gates | Pass | `make verify`; `make e2e` API 4/4 + Playwright 29/29; `make build` |
| Runtime readiness | Pass with intentional warnings | `make soft-launch-ready`: 17 pass / 7 warn / 0 fail; warnings are absent provider credentials/authorization and no active publication head |

### Security, privacy and tenancy review

- Secrets were generated on the VPS and remain mode `0600` outside Git. No
  password, API key, database credential, learner content, audio or transcript
  is present in repository evidence.
- The Android OAuth client is public PKCE with the exact package callback and no
  secret. No human account or credential was invented during deployment.
- No live AI, Speech, media-transcription or payment request was made. Existing
  organization scoping and deterministic records were not changed.
- The root password supplied through chat is treated as disclosed. Rotation or
  password-auth disablement remains a production blocker; it was not silently
  changed because the owner explicitly retained rotation for go-live.

### Verification notes and residuals

- The first local `make verify` invocation failed because the sandbox could not
  resolve Packagist; the same exact gate passed with authorized network access.
  The first `make e2e` invocation could not bind Playwright's local preview
  server; the same exact gate passed with authorized local-port access. Neither
  failed attempt is reported as a pass.
- The initially copied APK was structurally valid but still embedded the
  retired static API origin. It was rejected, rebuilt as the debug closed-beta
  target with the active HTTPS tunnel and public PKCE client, and replaced at
  both public download paths. The first Gradle step could not write the
  sandboxed user cache; the same unit/assemble step then passed with approved
  cache access. No Play or production signing is claimed.
- A protected off-box backup copy was not taken because no approved external
  custody destination was authorized. Stable DNS/TLS, PITR/alerts, independent
  specialist reviews, governed operator identities, remaining device evidence,
  production signing, Play and payment authority remain open.
- Complete evidence and rollback instructions are in
  [VPS_SOFT_LAUNCH_DEPLOYMENT_2026-09-18.md](VPS_SOFT_LAUNCH_DEPLOYMENT_2026-09-18.md).

## DEVICE-GATE-FIX-02 deterministic Android gate repair — 16 September 2026

### Scope reviewed

- Preserve the localized explanation when Android Back cancels an active
  recording, without changing capture, upload or typed-fallback behavior.
- Repair both wrappers' invalid DOM route helper so connected smoke journeys
  can reach their asserted destinations.
- Fix only deterministic test/lifecycle failures reproduced at current HEAD:
  first-Tab skip-link timing, configured-host Wave 3.8 test isolation and late
  guidance callbacks after component unmount.
- No device-matrix cell, feature, API/OpenAPI, schema, storage, entitlement,
  payment, production or Play authorization change.

### Review result

The targeted pre-fix RMX3085 session at source HEAD `8f79fc3` verified the
contextual rationale, denied-once and denied-permanently results, retained
**Type instead**, and actual recording after permission was granted through
Android App Info. It did not exercise choosing **Allow** in the contextual
system prompt, so UX10-DEV-01 remains narrowly open. Back during active capture
cancelled without upload, but the explanatory status disappeared; that exact
source defect is the repair below. No learner audio or transcript is retained
as evidence.

| Check | Result | Evidence |
|---|---|---|
| Recording Back status | Pass in component evidence | `VoicePracticePanel` resets its parent loop without changing the child key; the new integration regression starts capture, dispatches the Android Back event, verifies cancellation, retained `learner.recording.back_cancelled`, ready hold control and no upload |
| Native route helper | Pass in source/compile evidence | Production and demo suites use a valid JavaScript IIFE and assert the resulting hash before continuing; both Android-test source sets compile |
| Async lifecycle | Pass | Guidance callbacks are guarded after unmount; the focused Conversation/Voice suites pass 24/24 and the full web suite passes 466/466 without unhandled rejection |
| Skip-link race | Pass | The E2E waits for the rendered skip link before pressing Tab; Playwright 29/29 passes |
| Wave 3.8 test isolation | Pass | The unconfigured test explicitly sets an empty `speech_routing.wave38.status_file`; focused test passes 4/4 and full SQLite suite passes 316/316 |
| Scope boundary | Pass | No authoritative record, API, schema, storage, entitlement, review interval, payment, production or release change |

### Commands and results

| Command | Result |
|---|---|
| Focused Vitest (`ConversationJourney`, `VoicePracticePanel`, then `RecordingTurn`) | Passed: 24/24 and 37/37 |
| Focused `Wave38EvidencePreflightTest` | Passed: 4 tests, 31 assertions |
| Production wrapper Gradle compile/instrumentation compile/unit test | `BUILD SUCCESSFUL`; 62 tasks executed |
| Demo wrapper Gradle compile/instrumentation compile/unit test | `BUILD SUCCESSFUL`; 60 tasks executed |
| Pinned `make verify` | Passed: Composer/Pint/PHPStan; SQLite 316/316 (3,087 assertions); PostgreSQL 360/360 (5,225 assertions); lint/typecheck/contracts; web 101 files / 466 tests. Two moderate pnpm advisories remain below the high-severity gate; five locale-review warnings remain. The first attempt exposed the configured-host Wave 3.8 precondition; the second exposed two late guidance callbacks; both were repaired and the final command exited 0. |
| Pinned `make e2e` | Passed: reconciliation API 4/4 (103 assertions), Playwright 29/29 |
| Pinned `make build` | Passed; production web bundle built and unlicensed tutor-avatar models remained excluded |

### Residuals

- The pre-fix RMX3085 walk proved safe cancellation but did not retain the
  explanatory status. The source regression is fixed; a rebuilt APK must prove
  the announcement on-device before that UX-10A exit criterion can pass.
- Connected production/demo instrumentation was not rerun because no device is
  attached to this Linux host. A documented seeded signed-in profile remains a
  precondition for the production suite.
- Gesture predictive Back, TalkBack, font/320px, split-screen/offline and
  remaining §10 columns remain open exactly as recorded. No matrix cell changed.
- Production, Play release and live payment remain unauthorized.

## APP-0064 renewal and Wave 3.8 reclose — 16 September 2026

### Scope reviewed

- Renew inherited APP-0064 Azure Speech vendor and spend evidence after
  `valid_until` **2026-09-14T13:15:00Z** blocked guidance TTS and Wave 3.8
  preflight (`azure_speech_vendor_evidence_expired`).
- Re-run fail-closed `speech-wave38:preflight` and bind
  `SPEECH_WAVE38_STATUS_FILE` in soft-launch/API env. No provider HTTP, no
  challenger dispatch, no code change.

### Review result

| Check | Result | Evidence |
|---|---|---|
| APP-0064 vendor `valid_until` | Pass | **2026-12-14T13:15:00Z**; `reviewed_at` **2026-09-16T13:15:00Z** |
| APP-0064 spend `valid_until` | Pass | Same dates; `cap_inr` **500** |
| Wave 3.8 preflight | Pass | `status: ready`, `checkpoint_closed: true`, `dispatch_authorized: false` |
| Live-deployment deferrals | Recorded | `elevenlabs_key_not_rotated`, `elevenlabs_deletion_test_missing` (APP-0081) |
| Soft-launch health | Pass | `make soft-launch-health` live ok / ready ok after restart |

Preflight JSON (16 September 2026):

```json
{"status":"ready","checkpoint_closed":true,"dispatch_authorized":false,"safe_error_codes":[],"deferred_until_live_deployment":["elevenlabs_key_not_rotated","elevenlabs_deletion_test_missing"],"recorded_assertions":{"india_residency_asserted":true,"zero_retention_asserted":true,"elevenlabs_cap_inr":500,"sarvam_cap_inr":500}}
```

### Known limitations

- ElevenLabs rotation and deletion test remain deferred until live deployment.
- Sarvam/ElevenLabs challenger HTTP enablement still requires separate approval.
- Production, Play and live payment remain unauthorized.

## §10.2 current-phone rerun — 16 September 2026

### Scope reviewed

- Rerun the existing RMX3085 current-phone column at documentation HEAD
  `d62a25a` with the installed APK hash `0409e528…`. Record per-row PASS/FAIL
  against plan §10.1. Do not treat this as a new device. Do not copy API 33
  into API 24/35/36 or the low-cost column. Open a bounded defect for each
  FAIL, but make no fix. No Play/production/payment claim.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Device identity | Same phone | Serial `J7GUXWEENBNZTW85`, realme RMX3085 (`RMX3085L1`), Android 13/API 33, WebView 151.0.7922.199, three-button `navigation_mode=0`, identity-capture locale `en-GB` and font scale `1.0` |
| APK | Hash replaced | On-device `base.apk` SHA-256 `0409e52835b41271fca5e4bdb82da7d2c2598926cfdb11cd0acc6bc62ed493b3` (28,307,558 bytes). Stale `779b5b90…` does not match |
| Fresh install / no launch mic | PASS | Same 13:16 install; cold start `MainActivity`; `RECORD_AUDIO` denied; no launch prompt |
| Grant/deny/permanent deny | FAIL | Rationale + system dialog + Don't allow + Type instead. Grant not completed. Permanent deny not offered |
| Gesture predictive Back | NOT RUN | Could not leave three-button mode |
| Three-button Back | PASS | Question → practice → extra introduction hop → Learn → Home → launcher |
| Quiz resume after app switch | PASS | 30s Home; Recents restored question 0/3 with first option still checked |
| Process recreation | PASS | Force-stop Recents → Home; radios unchecked; no ADR-0006 durable draft |
| Rotation and split-screen | FAIL | Landscape no overflowX; split-screen not entered |
| IME | PASS | `--ge-native-ime-inset-bottom` 331.76px; primary action above IME |
| TalkBack critical journey | FAIL | Named controls on main destinations; full 10.1 journey not completed |
| 200% English | FAIL | OEM max `font_scale=1.6`; Progress/chip clipping; 320 CSS width not applied |
| 200% Kannada | FAIL | Same 1.6 cap; Kannada nav labels truncate |
| Offline recovery | FAIL | Local quiz continue/submit while airplane on; no pending/idempotency UI |
| Light/dark/high-contrast | PASS | Night and high-contrast kept accepted light Variant C; no unapproved dark theme |
| Other columns | Unchanged residual | APP-0093 51 other-column cells plus 7 unmarked current-phone rows = 58/64 unmarked |

No physical result was supplied for Back during recording or 48×48 target
measurement. Existing component/browser evidence is retained without inferring
a current-phone PASS.

### Bounded defect list

| ID | FAIL | Residual |
|---|---|---|
| UX10-DEV-01 | Contextual permission | Grant and permanent denial were not completed; ordinary denial and Type instead were evidenced |
| UX10-DEV-02 | Rotation/split-screen | Rotation remained usable with crowded header chips; split-screen was not entered |
| UX10-DEV-03 | TalkBack | Named controls were observed, but the complete critical journey and gesture focus behavior were not completed |
| UX10-DEV-04 | English enlarged text | OEM capped at 1.6; Progress and the live-AI chip clipped; 2.0/320px was not exercised |
| UX10-DEV-05 | Kannada enlarged text | OEM capped at 1.6; navigation labels clipped; 2.0/320px was not exercised |
| UX10-DEV-06 | Offline recovery | Local feedback appeared, but pending/reconciled state, idempotency-key reuse and one authoritative submission were not evidenced |

All six are open and bounded to later evidence or separately authorized fixes.
No fix was made in this session. TalkBack, font scale, night mode and high
contrast were restored after the walk. Production, Play and live payment
remain unauthorized.

The earlier single tester Pass in the section below is historical and is not
the current-phone result.

## AUDIT-CLOSE-01 plan §15 closure — 16 September 2026

### Scope reviewed

- Perform the Android mobile UX audit closure pass defined in
  [`ANDROID_MOBILE_UX_AUDIT_EXECUTION_PLAN.md` §15](ANDROID_MOBILE_UX_AUDIT_EXECUTION_PLAN.md#15-definition-of-audit-closure).
- Record APP-0093/D-0300: remaining 51 non-N/A §10 cells accepted as residual;
  UX-EXP-01/02/03 Deferred; production, Play and live payment remain unauthorized.
- No application, schema, API/OpenAPI, provider, payment or device-matrix cell
  was changed in this pass.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Authorization | Pass | Exact APO reply `I accept.` after remaining §10 cells were offered as residual so §15 could close. Experiments follow Prompt 9's Deferred default. [APP-0093](APPROVALS.md#app-0093) |
| Repository gates at HEAD `179b534` | Pass | Pinned `make verify`, `make e2e` and `make build` all exit 0. Exact summaries in §13.3 of the audit plan |
| MUX-001–016 | Pass with residuals | Each finding has a resolving commit or an APP-0093 residual. Walk table in the audit plan §15.1 |
| §13.2 defects 1–8 | Pass with residuals | Defects 1–5 and 7 (authorized parts) resolved by later commits; defects 4/7 remainders, 6 and 8 are residuals. Walk table in §15.1 |
| §8 checkbox evidence | Pass | All 145 checkbox records carry an explicit link; every local linked file exists. Open/failed human and device rows remain unchecked and point to their residual evidence. |
| Experiments | Deferred | No analytics emission, no independent privacy review, no live traffic. Not used as correctness evidence |
| Production / Play / live payment | Still blocked | No authorization was granted or implied |

### Commands and results

Pinned toolchain: Node.js 24.18.1, pnpm 11.18.0, PHP 8.4.23, JDK 21 under
`tmp/toolchain`. Re-run at candidate HEAD
`179b534801a6457bf493e6da2f24f1d8f6ec1c37` after the committed UX-10A
device-evidence update.

| Command | Result |
|---|---|
| `make verify PHP="$PWD/tmp/toolchain/php/php" COMPOSER_CMD="$PWD/tmp/toolchain/php/php $PWD/tmp/toolchain/composer/composer.phar"` | Passed (exit 0). Composer audit clean; Pint passed; PHPStan 0 errors; SQLite 316/316 (3,087 assertions); PostgreSQL 360/360 (5,226 assertions); frozen install; `pnpm audit --audit-level=high` reported 2 moderate advisories below the failure threshold; locale check passed with 5 pending-independent-review warnings; OpenAPI/contracts, release and soft-launch contracts passed; lint `--max-warnings=0`; typecheck; web Vitest 101 files / 465 tests. The initial sandbox attempt stopped because Packagist DNS was unavailable; the exact rerun with network access passed. |
| `make e2e PHP="$PWD/tmp/toolchain/php/php"` | Passed (exit 0). Reconciliation API 4/4 (103 assertions); Playwright 29/29 passed (22.8s). The initial sandbox attempt could not start Playwright's local web server; the exact host rerun passed. |
| `make build PHP="$PWD/tmp/toolchain/php/php"` | Passed (exit 0). Production web bundle built in 189ms; tutor-avatar licence enforcement excluded models because no completed public licence grant exists. |

### Conclusion and retained gates

This section records the historical APP-0093/D-0300 closure. The superseding
§10.2 rerun above reopens UX-10A's device/accessibility evidence gate; its six
FAIL rows were not newly accepted as resolved. This remains neither production,
Play nor live-payment authorization. Open human gates remain:

- independent privacy/security/tenancy review before any funnel-event emission;
- academic review of review intervals (ADR-0028);
- independent review of the five non-English UI catalogues;
- representative casual/student/business comprehension studies (Home Continue,
  first-use, quiz clutter, paywall/Continue-free);
- API 24, API 35, API 36 and physical low-cost-phone §10 cells (accepted
  residual; do not copy RMX3085 API 33 results);
- current-phone FAIL/NOT RUN rows from the §10.2 rerun (permission grant/
  permanent-deny, gesture Back, rotation/split-screen, TalkBack journey, 200%
  English/Kannada, offline checkpoint evidence);
- connected instrumentation on a seeded signed-in profile;
- dashboard fail-close if a stored review row has `completed_at < due_at`
  (§13.2 defect 6).

## §10.2 current-phone device session — 16 September 2026

### Scope reviewed

- Record the Accountable Product Owner's **Pass** result for audit-plan §10
  current-phone rows after attaching realme RMX3085 (API 33).
- Do not mark API 24, API 35, API 36 or physical low-cost-phone cells.
- Do not start Prompt 9, experiments, or a new implementation checkpoint.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Device identity | Pass | `adb devices -l` showed `J7GUXWEENBNZTW85` / `model:RMX3085`; `ro.build.version.sdk=33`; WebView 151.0.7922.199 |
| Fresh install microphone | Pass (agent) | After uninstall/install, cold start focus stayed on `MainActivity`; `RECORD_AUDIO` remained `granted=false`; no permission dialog; guest Home visible |
| Remaining current-phone rows | Superseded | The single tester **Pass** below is historical. Current results are in [§10.2 current-phone rerun](#102-current-phone-rerun--16-september-2026) |
| Other matrix columns | Open | 51 of 64 non-N/A cells remain unmarked. API 33 evidence is not copied to API 35/36 |
| Connected instrumentation | Failed as recorded | Default `connectedDebugAndroidTest` failed on Capacitor cordova-plugin Kotlin duplicate classes. `:app:connectedDebugAndroidTest` was 6/6 failed looking for `.level-path__item` on unsigned guest Home |
| Demo wrapper | Not run | `com.globalenglish.demo` was not installed |

### Commands and results

| Command | Result |
|---|---|
| `VITE_MOBILE_API_ORIGIN=http://127.0.0.1:8080 make android-closed-beta` | Passed; APK SHA-256 `779b5b904a3b8f0a29d2661269993b74fc33a056e1395781005d0b31268e18b0` |
| `adb install` of that APK | Passed |
| `:app:connectedDebugAndroidTest` | Failed 6/6 on guest Home (expected until a seeded signed-in profile exists) |

### Conclusion and retained gates

The current-phone column was treated as exercised on a single tester Pass.
That assertion is superseded by the per-row rerun at the top of this file.

## GATE-FIX-01 repository gate repair — 16 September 2026

### Scope reviewed

- APP-0092/D-0299 authorize only the stale Variant-C cache-buster assertion,
  five recorded ESLint findings, retained header contrast failures and the
  soft-launch contract-test drift proven pre-existing at `ce305bd`.
- Existing voice-recorder behavior/tests, light Studio/operator/Mira header
  contrast, and the generated soft-launch environment contract/history.
- No feature, device, experiment, closure, API/OpenAPI, schema, provider,
  payment, production or release configuration is in scope.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Authorization and boundary | Pass | APP-0092/D-0299 limit the checkpoint to repository gate repair. No application capability, contract, schema, migration or live configuration changed |
| Variant-C assertion | Pass | `jezwebLearnerChrome.test.ts` requires the current source to contain a `?v=jezweb-cN` value and no longer couples the test to one release number; current product source remains `jezweb-c8` |
| Recorder lint and behavior | Pass | `browserSpeech.ts` uses an immutable start timer. `VoicePracticePanel` uses stable callbacks and the supplied parent phase; lock transitions change the `RecordingTurn` key and invoke its pre-existing unmount cancellation rather than writing state in an effect. The second state-reset effect was removed. Unchanged focused tests pass 37/37 and full web tests pass 465/465 |
| Header contrast | Pass | Pending-review text and its localized child are white only within green light-theme non-Mira Studio/operator headers. The learner paid-minutes chip uses `#0a1a14` on its existing light surface. The global muted token, `#006b3f` green and Mira learner white header are unchanged; final axe journeys pass 29/29 |
| Soft-launch history | Pass | `git show 0952364` proves the generator intentionally changed `SANCTUM_STATEFUL_DOMAINS` to `ai.globalspokenenglish.com,117.254.108.143:8080,127.0.0.1:9080,...` for staff-LAN sign-in while the older test string remained. Only `scripts/test-soft-launch-environment.sh` changed; generator, ports, domains and `APP_URL` are untouched, so no DR was needed |
| Privacy, security and tenancy | Pass | No persisted content, credential, learner data, tenant query or authorization path changed. Recorder cleanup remains fail-safe on unmount and no analytics emission was added |

### Commands and results

| Command | Result |
|---|---|
| focused `pnpm run lint` | Passed with zero warnings |
| focused unchanged chrome/voice/recording Vitest | Passed: 3 files, 37 tests |
| `bash scripts/test-soft-launch-environment.sh` | Passed: `Soft-launch environment contract passed.` |
| pinned `make verify PHP="$PWD/tmp/toolchain/php/php" COMPOSER_CMD="$PWD/tmp/toolchain/php/php $PWD/tmp/toolchain/composer/composer.phar"` | Passed. Final summary: `Test Files 101 passed (101)`; `Tests 465 passed (465)`; earlier stages passed Composer advisory audit, Pint, PHPStan, SQLite 316/316 (3,087 assertions), PostgreSQL 360/360 (5,223 assertions), frozen install, high-severity dependency audit, locale, OpenAPI/generated contracts, release and soft-launch contracts, lint and typecheck. Two moderate pnpm advisories remain below the configured high-severity failure threshold |
| pinned `make e2e PHP="$PWD/tmp/toolchain/php/php"` | Passed. Final summaries: reconciliation API `tests: 4, passed: 4, assertions: 103`; Playwright `29 passed (23.0s)`. The first diagnostic rerun exposed three operator locale-note descendants still inheriting muted text; the header-only descendant selector corrected them before this final full run |
| pinned `make build PHP="$PWD/tmp/toolchain/php/php"` | Passed. Final summaries: `✓ built in 184ms`; `Tutor avatar bundle: excluded models because no completed public license grant exists.` |

### Conclusion and retained gates

GATE-FIX-01 is complete: the exact repository verification, browser and build
gates pass at the candidate state. No product or live-host behavior changed.
The audit itself is not closed: the §10 physical-device matrix, human
accessibility/comprehension checks, independent catalogue reviews, experiments
and the separately authorized closure decision remain open.

## AUDIT-FIX-01 post-implementation audit corrections — 16 September 2026

### Scope reviewed

- APP-0091/D-0298 authorize only §13.2 defects 2, 3, 4 (partial), 5 and
  7 (partial). Prompt 8b, closure, experiments, defect 6, APIs/OpenAPI,
  schemas, review intervals/semantics, quiz drafts and plans structure are
  outside this checkpoint.
- First-use resume privacy and authority, remaining Home/sign-in navigation,
  pending-checkout recovery, and both production/demo wrapper startup and
  asynchronous Back failure paths.
- The existing §13.2 repository baselines are comparison evidence, not passing
  results: five lint findings, one stale Variant-C unit assertion and seven
  browser contrast failures.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Authorization and boundary | Pass | APP-0091/D-0298 bind the change to the five enumerated defect slices. No Prompt 8b/device execution, closure, experiment, API/OpenAPI, schema, migration, payment/provider, review, interval, quiz-draft or plans-structure change was made |
| Resume learner isolation | Pass | First-use resume uses the exported SHA-256 hash of the existing organization/user/membership offline binding as its storage-key suffix and embedded binding check. The raw identifiers are never written to the key or record; a learner-A record is not read for learner B |
| Resume expiry/version | Pass | Version 2 records carry issued/expiry instants, accept no interval over 24 hours and remove invalid/expired records. Storage access and hashing failures are caught so first use remains available without persistence |
| Adult and authoritative onboarding | Pass | `adultConfirmed` and `onboardingSaved` are absent from the record contract. The checkbox initializes false on every mount. If the server still reports onboarding incomplete, a restored cursor after the goal boundary returns to adult confirmation, so storage cannot skip the authoritative goal command |
| Completion/logout cleanup | Pass | Completion and both authenticated logout paths remove the bound record. Per-binding storage mutations are serialized so a preceding best-effort write cannot recreate the record after its clear operation |
| Navigation history | Pass | No raw Home/sign-in hash assignment remains. The sign-in helper pushes `#sign-in` with a prior Home target and Android Back delegates to browser history; focused history coverage proves the prior entry |
| Pending checkout recovery | Pass | A provider `pending` result retains the pending message, disables duplicate purchase submission and exposes **Check access**. The existing authoritative entitlement refresh either reconciles or truthfully becomes ambiguous; no success is inferred |
| Native startup and dependency | Pass | Both `MainActivity` implementations guard `bridge` and its WebView before installing clients/shell behavior. Both apps explicitly depend on AndroidX Activity 1.11.0, the version already pinned by the Capacitor 8.5 wrapper variables |
| Native Back timeout | Pass in isolated unit/compile evidence; device gate retained | Both shells post a 1,500 ms timeout. Whichever of timeout or JavaScript callback wins releases the generation guard; timeout cancels preview and falls through the supported dispatcher, while a late callback is ignored. Package-correct JVM tests prove release, late-finish rejection and subsequent dispatch. Connected predictive behavior remains open |
| Template/path cleanup | Pass for authorized partial scope | Both stale `com/getcapacitor/myapp/ExampleUnitTest.java` files are deleted. The web source-policy test resolves `@capacitor/android` from the installed package rather than a pnpm-store layout. Instrumented-journey realism remains outside this partial fix |
| Privacy, tenancy and contracts | Pass | The resume allowlist contains only step, goal, placement, binding hash and timestamps. It contains no credential, learner text, audio, transcript, consent or adult-confirmation state. No tenant query, API/OpenAPI, schema, migration or second storage mechanism changed |

### Commands and results

| Command | Result |
|---|---|
| focused Vitest for first use, learner history, pending checkout and Android source policy | Passed: 4 files, 22 tests |
| pinned `pnpm run typecheck` | Passed all five participating workspaces |
| pinned `pnpm run lint` | Not green: exactly the §13.2 baseline of 5 problems (3 errors, 2 warnings) in unchanged `VoicePracticePanel.tsx`, `browserSpeech.ts` and `RecordingTurn.tsx`; AUDIT-FIX-01 delta is zero |
| pinned `pnpm run test` plus JSON web report | Not green: web 464/465 tests passed; the sole failure remains the §13.2 `jezwebLearnerChrome.test.ts` expectation for `?v=jezweb-c4` against current `c8`. All new/changed tests pass |
| pinned `make e2e` after updating the existing v1-key journey | Not green: reconciliation API 4/4 (103 assertions) and Chromium 22/29 passed, including English and Kannada first use. The seven failures are exactly the §13.2 contrast baseline on the Kannada pending-review notice or paid-minutes chip; delta is zero |
| production wrapper `./gradlew --rerun-tasks :app:compileDebugJavaWithJavac :app:compileDebugAndroidTestJavaWithJavac :app:testDebugUnitTest` with pinned JDK 21 | Passed: BUILD SUCCESSFUL, 62 tasks; 6 unit tests, 0 failures |
| demo wrapper same Gradle command with pinned JDK 21 | Passed: BUILD SUCCESSFUL, 60 tasks; 6 unit tests, 0 failures |

### Conclusion and retained gates

AUDIT-FIX-01's authorized implementation and evidence scope is complete.
Defects 2, 3 and 5 are resolved; the authorized null-guard/timeout portion of
defect 4 and stale-test/package-resolution portion of defect 7 are resolved.
The predictive back-to-launcher animation/device behavior remainder of defect
4, instrumented-journey realism remainder of defect 7, all §10 device cells,
the five lint findings, stale Variant-C assertion and seven contrast failures
remain open. Prompt 8b device work and the closure pass were not started.

## UX-10A Android Back, lifecycle and accessibility hardening — 16 September 2026

### Scope reviewed

- APP-0090/D-0297 authorize only UX-10A findings MUX-005, MUX-009,
  MUX-015 and MUX-016. No experiment, onboarding redesign, quiz-content
  change, API, schema, payment, provider, production promotion or dark-theme
  work is included.
- Both target-SDK-36 Capacitor wrappers, learner destination/level/part/question
  history, recording interruption, focus/announcement behavior, WebView
  insets, Variant C bottom navigation and the existing UX-04A draft/offline
  checkpoint journey.
- `adb devices -l` returned no device or emulator. Native suites were compiled
  but not executed, and no §10 device result is inferred from source, browser,
  compilation or APK-build evidence.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Authorization and boundary | Pass | APP-0090/D-0297 bind the slice to UX-10A. No experiment was started and no API/OpenAPI, authoritative record, schema, migration, payment, provider or production configuration changed |
| Capacitor Back-source inspection | Pass | The installed `@capacitor/android` 8.5.0 `BridgeActivity.java` extends `AppCompatActivity` and contains lifecycle/bridge setup only: no AndroidX or platform Back callback, WebView `canGoBack`/`goBack`, legacy `onBackPressed` override or `KEYCODE_BACK` policy. This was recorded before selecting the app-owned integration point |
| Web history hierarchy | Pass | Content-free state and exact hashes represent primary destination, level, four-session part, assessment kind/phase and question ordinal. Focused unit/browser tests prove question 2 → question 1 → session → level list, with the prior target available to predictive-progress presentation |
| Android Back integration | Pass with connected-device gate | Both wrappers install lifecycle-owned AndroidX `OnBackPressedCallback`; started/progressed/cancelled callbacks update a route-accurate prior-target label, commit asks the web layer first, and an unhandled event temporarily disables the callback before supported dispatcher fallback. Neither wrapper overrides legacy Back APIs or disables predictive Back in the manifest |
| Recording safety | Pass with device gate | A cancelable web Back request is consumed while permission, recording, paused or processing is active; the capture/request is cancelled and a localized status explains that Back cancelled recording. Focused component tests pass. Physical gesture/three-button timing remains open |
| Focus and announcements | Pass with TalkBack gate | History stores only a stable originating-control ID and restores it after popstate rendering. Level, part and question changes update a polite live region; focused unit and Chromium journeys verify state and focus. TalkBack reading/order remains unclaimed |
| Reload and offline preservation | Pass with lifecycle gate | The existing option-only, tenant/member/level/part/attempt-bound draft remains authoritative for selection restoration. The extended offline Playwright journey now proves that reload resumes the exact nested question URL, keeps the selection and preserves one idempotent signed checkpoint submission. Low-memory/process-death execution remains open |
| Edge-to-edge, safe areas and IME | Pass in implementation/browser; device open | `EdgeToEdge.enable`, system-bar/display-cutout/IME `WindowInsetsCompat` listeners and density-normalized CSS variables are present in both wrappers. Manifest `adjustResize` is retained. A 320px browser simulation proves the UX-04A sticky action remains above a 220px IME inset; physical WebView/system-bar/keyboard validation remains open |
| Targets and Variant C identity | Pass in browser; device open | The five narrow-screen destinations are equal tabs with at least 48×48 CSS-pixel geometry and labels of at least 12px, with green tint, weight and inset indicator. Browser dark-preference evaluation retains the accepted light `#FAFBFA` canvas and `#006B3F` action; forced-colors evaluation adds a 3px system-color edge. No dark product theme was added |
| Native smoke coverage | Pass compilation; connected execution open | Both placeholder `ExampleInstrumentedTest.java` files are removed. Package-correct Espresso/UiAutomator suites for `com.globalenglish.app` and `com.globalenglish.demo` cover first use, lesson/Back, quiz resume, Speak denial/typed fallback, plans return and question/focus Back. Both application and androidTest source sets compile |
| Privacy, credentials and tenancy | Pass | History contains route ordinals/identifiers and focus IDs only. It stores no learner text, audio, transcript or credential; no new persistence mechanism exists. Existing option-only draft scoping is unchanged and no tenant-owned query was added or modified |
| Device matrix | Open with superseding physical evidence | The later RMX3085 rerun records six current-phone PASS cells, six FAIL rows and one NOT RUN row; 58/64 non-N/A cells remain unmarked. See [§10.2 rerun](#102-current-phone-rerun--16-september-2026). No result is copied into API 24/35/36 or low-cost-phone columns |

### Commands and results

| Command | Result |
|---|---|
| `adb devices -l` | Ran; output contained only `List of devices attached`, so no connected test or matrix cell was claimed |
| focused Vitest for App, learner history, Android Back source policy, Level path and recording | Passed: 5 files, 70 tests |
| full web Vitest | Did not complete: 100/101 files and 459/460 tests passed; sole failure is the pre-existing `jezweb-c4` expectation against current `c8` |
| scoped ESLint for UX-10A web/design-system files | Did not complete: one pre-existing `react-hooks/set-state-in-effect` error at `RecordingTurn.tsx:114`; no additional warning was reported |
| focused Playwright UX-10A journey | Passed: route/Back/focus, 48px targets, 12px labels, active indicator, 320px IME inset, dark preference, forced colors and scoped axe |
| extended existing offline checkpoint Playwright journey | Passed: encrypted option-only reload restoration at the exact question URL and one signed idempotent offline submission |
| pinned `make verify` | Did not complete: Composer validation/audit, Pint, PHPStan, SQLite 316/316 (3,087 assertions), PostgreSQL 360/360 (5,225 assertions), frozen install, dependency audit, locale, OpenAPI/generated contracts and release contract passed; it stopped at the pre-existing `soft-launch-contract-test` fixed-string assertion |
| pinned `make e2e` after the reload-locator correction | Did not complete: reconciliation API 4/4 (103 assertions) and Chromium 22/29 passed, including both Level-path and UX-10A journeys. Seven retained journeys fail only the pre-existing serious contrast defects on the Kannada pending-review notice or paid-minutes chip |
| pinned `make build` | Passed; production web build and licence enforcement completed |
| production and demo `compileDebugJavaWithJavac` plus `compileDebugAndroidTestJavaWithJavac` | Passed for both wrappers with JDK 21 and Android SDK 36 |
| `make android-closed-beta` with the accepted staff-LAN origin plus final direct Gradle compile/test/assemble | Passed: web sync, production unit tests, both wrappers' androidTest compilation and production `assembleDebug`. Final debug APK is 28,307,558 bytes, SHA-256 `1dead3e63353f62c6681bd9722ac835e75b35ac7f15270ced7028d13d4c30a39`; it was not installed or published |

### Conclusion and retained gates

UX-10A's bounded implementation scope is complete, and the repository gates
were later repaired by GATE-FIX-01. Checkpoint device/accessibility closure is
not complete after the superseding RMX3085 rerun: UX10-DEV-01 through
UX10-DEV-06 remain open, gesture predictive Back was not run, physical
48×48 target measurement and Back-during-recording behavior were not reported,
and 58/64 non-N/A matrix cells remain unmarked. No experiment or dark theme
was introduced; the sideloaded debug APK is not a production or Play release.

## UX-08A contextual plans and recoverable checkout presentation — 15 September 2026

## Scope reviewed

- APP-0089/D-0296 authorize only UX-08A finding MUX-011. UX-10A, Android,
  live payment, Play Billing, real capture, provider credentials, migrations
  and production enablement remain outside this change.
- Entitlement-locked Level path presentation, the existing account/plans
  surface, Direct catalogue/checkout institutional exclusion, all six UI
  catalogues and focused unit/tenancy/browser evidence.
- The existing ADR-0035 dummy/disabled provider seam is unchanged. No live
  provider was called and no OpenAPI contract changed.

## Review result

| Check | Result | Evidence |
|---|---|---|
| Authorization and boundary | Pass | APP-0089/D-0296 bound the work to UX-08A. No Android, UX-10A, payment provider, real capture, schema, migration or production configuration changed |
| Contextual level locks | Pass | Every `upgrade_required` level renders its own **View plans for Level N** action. The callback carries the locked level, highest earlier `passed` checkpoint only, and a deterministic return target; component and browser tests cover multiple locks and a return to `#learn` |
| Progress-safe close | Pass | Opening account/plans leaves `LevelPathPanel` mounted. Close and Continue free collapse plans and restore focus to the originating locked-level action; the 320 CSS-pixel Chromium journey proves the route and focus return without a learning mutation |
| Entitlement-first hierarchy | Pass | Current entitlement/status/expiry/minutes precede the contextual accomplishment, membership choices and optional packs. Exactly two non-demo/non-pack API offers are required before checkout buttons render; an incomplete catalogue fails closed |
| Benefit and price truth | Pass | Offer names, level cap, Role-play inclusion, live-AI cap, ex-GST, GST and payable totals come from the catalogue response. Each membership has one expandable price definition and one exact total. Talk-pack tax/expiry-period detail is also expandable |
| Premium choice | Pass | The API `recommended` value controls a border/badge distinction only. There is no radio, selected state, default plan or automatic checkout; the test asserts the Premium action is not pressed/preselected |
| Talk-pack eligibility | Pass | The entire Talk-time section is absent unless the authoritative entitlement is active and non-demo. Eligible paid membership reveals API-catalogued packs; tests cover both sides |
| Checkout state truth | Pass | Loading, unavailable, request-pending, unknown/ambiguous, explicit failure, reconciled and active-entitlement messages are distinct. `completed` is followed by entitlement/catalogue refresh and becomes reconciled only when the chosen membership or pack delta is reflected; refresh uncertainty remains ambiguous |
| Institutional isolation | Pass | Institutional entitlement returns `applies=false`; the client stops before requesting a Direct catalogue and renders no checkout. The service now returns an empty catalogue and rejects a cross-organization Direct plan ID before any checkout row is written. DirectEntitlementTest and SubscriptionPanel tenancy tests pass |
| Privacy and credentials | Pass | The flow stores no checkout, learner, credential or payment content in browser storage. Existing cookie/PKCE authentication abstractions and no-store API responses are unchanged |
| Human comprehension | Open | Automated structure and exact-price checks do not prove that casual, student and business learners can accurately explain both tiers and the payable amount; representative moderated testing remains required |
| Catalogue review | Open | English is source revision `ui-en-IN-2026-09-15.10`; the five non-English catalogues contain structurally complete implementation-draft keys but remain pending independent review |

## Commands and results

| Command | Result |
|---|---|
| `pnpm --filter @global-english/web exec vitest run src/App.test.tsx src/subscriptions/SubscriptionPanel.test.tsx src/learner/LevelPathPanel.test.tsx` | Passed: 3 files, 45 tests |
| `php apps/api/vendor/bin/phpunit --configuration=apps/api/phpunit.xml apps/api/tests/Feature/DirectEntitlementTest.php` | Passed: 11 tests, 85 assertions |
| scoped ESLint for SubscriptionPanel, LearnerSubscriptionApi, LevelPathPanel, App and the UX-08A browser spec | Passed with zero warnings |
| `pnpm --filter @global-english/web typecheck` | Passed |
| `pnpm --filter @global-english/localization validate` | Passed all 6 catalogues; five expected pending-independent-review warnings |
| `pnpm --filter @global-english/web build` | Passed; production web bundle and tutor-avatar licence enforcement completed |
| focused `playwright test e2e/level-path-offline.spec.ts --grep "opens contextual plans"` | Passed: 1/1 after correcting the test's initially non-exact currency locator; covers context, two cards, exact tax values, hidden packs, 320px panel reflow, scoped axe and focus/route recovery |
| `make verify` with pinned repository toolchains | Did not complete: Pint and PHPStan passed; SQLite 316/316 (3,087 assertions), PostgreSQL 360/360 (5,224 assertions), locale/OpenAPI/generated-contract/release checks passed; the command stopped at the pre-existing out-of-scope `soft-launch-contract-test` fixed-string assertion |
| `make e2e` with pinned repository toolchains | Did not complete: API precheck 4/4 passed and Chromium 20/28 passed, including both Level-path journeys and UX-08A. Eight shared-shell journeys failed only the retained serious contrast defects on the Kannada pending-review notice or paid-minutes chip |

## Conclusion and retained gates

UX-08A's bounded implementation scope is complete. The checkpoint remains open
on the repository-wide soft-launch/contrast failures, representative plan and
price comprehension, and independent review of five non-English catalogue
drafts. UX-EXP-02 is protocol-ready for plan-selection intent and the existing
dummy/disabled behaviors only; it was not activated, no analytics were emitted
and live payment remains unauthorized. UX-10A was not started.

## UX-04A: One-question quiz and resumable drafts — 15 September 2026

### Scope reviewed

- APP-0088/D-0295 authorize only UX-04A findings MUX-003, MUX-004, MUX-005
  and MUX-014. Plans/paywall work, Android Back/history, APIs, scoring and
  attempt limits are outside this change.
- Quick checks and checkpoints in `LevelPathPanel`, their existing API result
  rationale, the existing ADR-0006 offline store/queue, all six interface
  catalogues and the synthetic demo path.
- Component tests plus the existing offline Level-path browser journey; no
  existing test file was deleted or replaced.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Authorization and boundary | Pass | APP-0088 authorizes UX-04A only. No UX-08A, UX-10A history, Android, API/OpenAPI, schema, scoring, attempt-bound, lease or offline-queue contract change was introduced |
| One-question interaction | Pass | The shared quick-check/checkpoint renderer exposes exactly one server item, `Question X of Y`, Previous where safe and one sticky Continue/Submit action; component tests prove both question transitions and selection restoration |
| Target and selected state | Pass with physical gate | Answer cards have a 48 CSS-pixel minimum and checked semantics; selection adds border/inset treatment, a check icon and visible **Selected** text. Browser geometry, axe and 320-pixel checks pass; physical assistive-technology checks remain below |
| Draft minimization and binding | Pass | The allowlisted 24-hour draft contains only blueprint/item IDs, numeric option IDs, attempt ID, position and timestamps; encrypted persistence uses the existing personal-device store and exact organization/member binding. The no-opt-in path is memory-only |
| Interruption recovery | Pass with device gate | Unit tests recreate the store/component, switch parts and restore the exact answer/attempt; Chromium reload restores the encrypted selection. Physical Android background, process-death and low-memory recreation remain required |
| Idempotent/offline authority | Pass | The same stable attempt ID is retained after uncertain submission and becomes the checkpoint idempotency key. The unchanged signed queue receives the complete answer set once; draft deletion occurs only after server acceptance or durable queue handoff |
| Useful feedback and retry | Pass | Item-level icon + text + color, selected answer and existing rationale render without an animation wait. Focused retry contains only incorrect questions while its authoritative resubmission retains the full answer map under a new attempt ID |
| Motion and sensory behavior | Pass | Feedback/selection motion is 180 ms and disabled by `prefers-reduced-motion`. No sound or haptic was added because none is separately accepted |
| Demo and localization | Pass with language-review gate | Synthetic demo remains covered. All six catalogues contain the new keys at `ui-*-2026-09-15.8`; five non-English catalogues remain pending independent review |

### Commands and results

- Focused `LevelPathPanel` Vitest passed 14/14. Web typecheck and scoped ESLint
  passed. Locale validation passed with only the five expected independent-
  review warnings. The production web build passed.
- Full web Vitest passed 447/448 tests across 98/99 files. Its sole failure is
  the pre-existing `jezwebLearnerChrome.test.ts` `?v=jezweb-c4` expectation
  against the current `?v=jezweb-c8` product value; all UX-04A tests passed.
- Pinned-toolchain `make verify` passed Composer validation/audit, Pint,
  PHPStan, SQLite PHPUnit (316/316; 3,083 assertions), PostgreSQL PHPUnit
  (360/360; 5,224 assertions), frozen install, high-severity dependency audit,
  locale validation, OpenAPI/generated contracts and release-contract. It
  then stopped at the separately owned pre-existing `soft-launch-contract-test`
  fixed-string assertion, so the command is not reported green.
- Pinned-toolchain `make e2e` passed reconciliation integration 4/4 (103
  assertions) and 19/27 Chromium journeys. The UX-04A offline Level-path
  journey passed, including reload restoration, privacy inspection, 48-pixel
  targets, non-color selection, axe, reduced motion, 320-pixel reflow,
  queue/sync idempotency and rationale. The eight failures are retained
  serious shared-chrome contrast violations; the command is not reported
  green.

### Human accessibility and device gates still open

1. Physical Android TalkBack: progress, question/answer names, checked state,
   result status/rationale, focus movement after Continue/Previous/result/retry
   and offline pending/sync announcements.
2. Real Android 200% font: no clipped prompt, option, progress or rationale;
   every 48-pixel target and sticky action remains operable in English and
   Kannada preview.
3. A 320 CSS-pixel Android WebView with the real IME and safe areas: no overlap
   or horizontal scrolling after entering from an input-bearing view, rotating
   or resuming; the sticky action and bottom navigation remain reachable.
4. Hardware keyboard and Switch Access: logical order, visible focus, radio
   selection, Previous/Continue/retry operation and no focus trap.
5. Android lifecycle/low-memory recreation on a consented personal device:
   background, task switch, process kill and WebView recreation restore the
   exact bound unexpired draft; the non-opt-in path leaves no durable draft.

### Conclusion and retained gates

UX-04A's bounded implementation scope is complete. The checkpoint is not
closed: repository-wide `make verify`/`make e2e`, the five physical
accessibility/lifecycle checks and independent review of the five non-English
catalogues remain open. Experiment UX-EXP-03 also remains blocked until its
measurement/emission prerequisites are separately implemented. UX-08A and
UX-10A were not started.

## UX-03A: One-action Home and retention semantics — 15 September 2026

### Scope reviewed

- APP-0087/D-0294 authorize only UX-03A findings MUX-010, MUX-012 and
  MUX-013. Guest Home, onboarding, quiz rendering, Android code, APIs,
  schemas, review intervals and UX-04A are outside this change.
- Authenticated `#home`, ADR-0028 due-review selection, Level-path and Progress
  review blockers, mission-result actions/progress, the live-AI usage chip and
  all six interface catalogues.
- Focused component/integration tests and the existing English/Kannada-preview
  critical learner browser journeys.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Authorization and boundary | Pass | APP-0087 authorizes UX-03A only. No UX-04A, guest Home, onboarding, quiz, Android, API, schema, migration, interval or provider change was introduced for this slice |
| One-action authenticated Home | Pass | `App` no longer renders `TutorHomeHero` above authenticated `LearnerJourney`; the mission card contains one primary Continue and no duplicate Speak/Learn action. App, component and Kannada Home browser tests cover the result |
| Deterministic mission choice | Pass | The existing `due_review ?? next_mission` selection and due-review-first start branch are unchanged. The due-review test proves the review identifier starts before the next mission identifier |
| Secondary streak | Pass | Streak remains a small status aside, is absent from mission selection/locking, and retains neutral zero/count wording; it cannot gate or purchase anything |
| Review terminology | Pass with language-review gate | `learner.progress.delayed_review_missed` and its CSS modifier are removed. All six catalogues use **reviews ready or overdue**, reviewed on-time and reviewed-late vocabulary at `ui-*-2026-09-15.7`; the five non-English revisions remain pending independent review |
| Blocking-review route | Pass | Progress already calls the blocking review directly. Both Level-path blocker placements now provide one-tap **Go to review** links to `#home`; the component test exercises both placements and the route |
| Proximal result | Pass | Mission result shows compact mastered-capability progress such as **1 of 1 capabilities ready**, one primary **Continue from Home**, and secondary Retry. No XP, heart or reward-currency affordance was added |
| Usage placement | Pass | Demo seconds return no global-chrome chip and are shown only on Speak or plan/account surfaces. Paid minutes remain available in global chrome but the label is availability information, not a ticking **left** countdown |
| Representative-learner comprehension | Open | Automation establishes destination and dominance but cannot prove that casual learners, students and business professionals can explain what Continue opens; moderated comprehension remains required |

### Commands and results

- Focused Vitest passed 60/60 tests across `App`, `LearnerJourney`,
  `LevelPathPanel`, `ProgressDashboard`, `LiveAiMinutesChip` and
  `SubscriptionPanel`. The strengthened Level-path blocker test also passed
  11/11 in its isolated rerun.
- Web typecheck, production web build and locale validation passed. Locale
  validation reported only the five expected pending-independent-review
  warnings. Scoped UX-03A tests and files introduce no lint finding; the full
  lint command retains three unrelated UX-02A working-tree errors and two
  warnings in recording/speech files.
- Full web Vitest passed 444/445 tests across 98/99 files. Its sole failure is
  the existing `jezwebLearnerChrome.test.ts` expectation for `?v=jezweb-c4`
  while the product constant is `?v=jezweb-c8`; all UX-03A tests passed.
- Pinned-toolchain `make verify` passed Composer validation/audit, Pint,
  PHPStan, SQLite PHPUnit (316/316; 3,083 assertions), PostgreSQL PHPUnit
  (360/360; 5,225 assertions), frozen install, the high-severity dependency
  audit (two moderate findings), locale validation, OpenAPI/generated
  contracts and release-contract. It then stopped at the separately owned
  pre-existing `soft-launch-contract-test` assertion, so the command is not
  reported green.
- Pinned-toolchain `make e2e` passed the reconciliation integration suite
  (4/4; 103 assertions) and 18/27 Chromium journeys. All seven
  `learner-journey.spec.ts` scenarios passed, including Kannada authenticated
  Home and the online mission/result journey. The nine failures are existing
  serious color-contrast violations for the Kannada pending-review note in
  shared header chrome; the command is not reported green.

### Conclusion and retained gates

UX-03A's bounded implementation scope is complete. The checkpoint is not
closed: the exact `make verify` and `make e2e` exit criteria remain red on the
separately owned soft-launch and shared-header contrast defects, and the
representative-learner Continue-comprehension test is human work. Independent
review of the five changed non-English catalogue revisions also remains open.
UX-04A was not started.

## UX-02B: Progressive first use and first success — 15 September 2026

### Scope reviewed

- APP-0086/D-0293 authorize only UX-02B/MUX-006; UX-03A, Home redesign,
  quiz rendering, Android wrapper behavior and authoritative learning rules are
  outside this change.
- The shared `OnboardingStepper` in both controlled production mode and its
  existing synthetic-demo mode.
- The complete §6.2 sequence from value statement through acknowledgement and
  Home, including interruption resume after the authoritative onboarding save.
- Existing adult/goal command shape, existing purpose-specific consent APIs,
  the UX-02A contextual recording bridge and the private typed alternative.
- Localization governance, storage allowlist and automated English/Kannada
  preview browser coverage.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Authorization and scope | Pass | APP-0086 authorizes UX-02B only; no UX-03A/Home, quiz, API, schema, migration or Android source change belongs to this checkpoint |
| Canonical stage order | Pass | Production `LearnerJourney` renders the ten setup screens in §6.2 order, then opens Home as the eleventh outcome; component and Chromium tests traverse the complete path |
| Shared stepper/demo | Pass | `OnboardingStepper` supports the production controlled flow while its original internal one-question mode remains covered by `DemoPhase1Shell` and stepper tests |
| Authoritative payload | Pass | The one server onboarding write remains exactly `{ adult_confirmed, goal_key }`; placement and UI/accessibility choices do not change the API or database schema |
| Placement safety | Pass | The only routes are **Start from the beginning** and defer the optional placement check; no accent, location or first-language inference exists |
| Purpose-specific consent | Pass | Recording upload/transcription and automated conversation-provider processing are separate checkboxes, both initially clear, each granted through its existing purpose endpoint; refusal continues to microphone-free/non-AI learning |
| Audio-output isolation | Pass | The output stage invokes only an injected/AudioContext playback function; its test proves the recorder is untouched |
| Contextual microphone | Pass | The optional mic stage uses `RecordingTurn`; its test proves the recorder remains untouched until the learner selects Start and then accepts the UX-02A rationale |
| Honest first action | Pass | The spoken or typed turn is explicitly local, is discarded on leaving the step, and makes no mastery/progress claim; the acknowledgement says the learning goal—not proficiency—was saved |
| Safe resume | Pass | A strict versioned record restores the last safe step and allowlists only step, adult flag, goal enum, placement enum and authoritative-save marker; tests show locale, consent and learner text are absent |
| Storage/privacy | Pass | No credential, learner-authored text, recording, transcript or consent choice is written to localStorage, IndexedDB or Capacitor Preferences; resume is cleared on completion and logout |
| Error and navigation behavior | Pass with human gate | Existing onboarding validation/offline/timeout/server retry/edit semantics remain; audio, consent, mic and practice stages have specific recovery copy; progress and safe Back are visible and Continue is CSS-sticky with safe-area padding. Physical IME occlusion remains a human check |
| Tone | Pass | Copy is adult, direct and non-mascot across casual, student and business goals |
| Localization | Automated pass; human review open | All six catalogues validate at source revision `ui-en-IN-2026-09-15.6`; the five non-English implementation drafts remain pending independent review, so Kannada E2E is preview evidence only |
| Representative-adult usability | Open | Automation proves the ordered actions but cannot prove that a new adult completes them without guessing; moderated first-use comprehension remains required |

### Commands and results

- Focused Vitest passed 5 files / 48 tests: production first use, shared
  stepper, synthetic demo, LearnerJourney and App integration.
- The full web Vitest suite passed 444/445 tests across 98/99 passing files.
  Its sole failure is the already-recorded `jezwebLearnerChrome.test.ts`
  expectation for `?v=jezweb-c4` while the product constant is already
  `?v=jezweb-c8`; every UX-02B test passed.
- Web typecheck, scoped ESLint, `git diff --check` and locale validation passed.
  Locale validation reports only the five expected pending-independent-review
  warnings.
- Focused Chromium passed 3/3: complete English first use, complete Kannada
  development-preview first use with a reload/restored placement step, and
  frozen same-command retry followed by edit/new-command recovery.
- Literal `make verify` stopped at the expected toolchain guard because ambient
  Node is 22.22.1 and the repository requires 24.18.1. The pinned-toolchain
  rerun passed Composer validation/audit, Pint, PHPStan, SQLite PHPUnit
  (316/316; 3,083 assertions), PostgreSQL PHPUnit (360/360; 5,225 assertions),
  frozen install, the high-severity dependency audit (two moderate findings),
  locale validation, OpenAPI/generated contracts and release-contract. It then
  stopped at the pre-existing `soft-launch-contract-test` assertion; no
  UX-02B test failed.
- Literal `make e2e` passed reconciliation PHPUnit (4 tests / 103 assertions),
  then could not start the browser server under ambient Node 22.22.1. The
  pinned-toolchain rerun built the web application and passed 18/27 Chromium
  journeys. All three UX-02B first-use/recovery journeys passed. The nine
  failures are the already-recorded serious color-contrast defects in the
  shared sign-in/header, live-AI chip, post-onboarding Home and operator
  surfaces; the online no-AI journey reaches Home before its contrast failure.

### Human accessibility and usability gates still open

- **TalkBack:** traverse every first-use step on a representative low-cost and
  current Android device; verify heading/progress announcements, checkbox and
  radio names/states, focus after Back/Continue, permission rationale/denial,
  typed fallback and acknowledgement-to-Home transition.
- **Real 200% font scale:** test English and Kannada preview on Android—not only
  a CSS/root-font simulation—with no clipped copy, lost controls, overlap,
  unexpected focus movement or hidden error/status announcements.
- **320 CSS pixels plus keyboard/IME:** test portrait reflow through every
  screen, open the IME for the typed turn, and verify no horizontal document
  overflow and that Back, errors and the sticky Continue action remain visible
  and operable above the keyboard and safe area.
- **Representative-adult comprehension:** observe casual learners, students and
  business professionals completing the flow without prompting; confirm they
  understand placement deferral, the two consent purposes and that speaking is
  optional.
- **Kannada language review:** an independent qualified reviewer must approve
  meaning, adult tone, glyph rendering and wrapping before non-preview use.

### Conclusion and retained gates

UX-02B implementation scope is complete, but the checkpoint is not marked
closed. Its representative-adult usability, TalkBack, real 200% font,
320px/IME and independent-catalogue gates are still human work, while the
required repository-wide `make verify`/`make e2e` gates remain red on separately
owned pre-existing failures. UX-03A was outside that slice and was subsequently
implemented under APP-0087/D-0294.

## UX-02A: Contextual microphone permission and onboarding recovery — 15 September 2026

### Scope reviewed

- APP-0085 and D-0292 supersede only D-0284's launch-time `RECORD_AUDIO`
  behavior; the bundled `https://localhost` origin remains unchanged.
- App-owned Capacitor permission plugins and fail-closed WebChrome clients in
  production `com.globalenglish.app` and demo `com.globalenglish.demo`.
- Shared web recording entry, localized rationale, three native permission
  outcomes and a non-blocking **Type instead** path.
- LearnerJourney onboarding validation/offline/timeout/server classification,
  frozen same-command retry and explicit edit/new-command recovery.
- No UX-02B flow, API, schema, review interval, credential-storage, consent,
  retention, deletion, provider-processing or production-deployment change.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Authorization before runtime change | Pass | APP-0085 records the Accountable Product Owner and Technical Decision Owner authorization; D-0292 supersedes only D-0284's launch-time permission clause |
| No launch-time prompt | Pass | Both manifests retain `RECORD_AUDIO`; both `MainActivity.onCreate` methods contain no permission request and register the contextual plugin instead; the replaced source-contract test covers both wrappers |
| Deliberate contextual request | Pass | `RecordingTurn` exposes the localized rationale only after the learner's speaking action; only its explicit continue action reaches `BrowserAudioRecorder`, which calls `requestAfterSpeakingAction` before `getUserMedia` |
| Permission outcome distinctions | Pass | Both native callbacks use the runtime grant plus `shouldShowRequestPermissionRationale`: granted, denied-once (`true`) and denied-permanently (`false`); Java and web tests cover all three |
| Bundled-origin WebView grant | Pass | Each app-owned WebChrome client grants only the single `RESOURCE_AUDIO_CAPTURE` resource, only for HTTPS localhost/default-or-443, and only while `RECORD_AUDIO` is already granted; all other origin/resource/permission paths deny |
| Non-recording continuity | Pass | One-time and permanent denial UI always exposes localized **Type instead**; the existing non-spoken path retains `spoken_state=unassessed` and no mastery claim was changed |
| Onboarding failure taxonomy | Pass | Validation, offline, 15-second timeout and server failures have distinct message keys and copy in all six catalogues |
| Same submission retry | Pass | Component and Chromium tests prove retry reuses the exact frozen payload and idempotency key while inputs remain locked |
| Edit choices | Pass | Component and Chromium tests prove edit discards the frozen payload, unlocks inputs and allocates a different command identity before resubmission |
| Storage/privacy | Pass | UX-02A code adds no localStorage, IndexedDB or Capacitor Preferences access and stores no credential or learner text; recording consent/retention boundaries remain ADR-0007 authority |
| API/schema/migration | Pass | No API field, OpenAPI contract, database column or migration changed |
| Scope boundary | Pass | UX-02B, progressive onboarding, Home layout, quiz rendering and later checkpoints were not started |
| Physical Android fresh-install smoke | Open | `adb devices -l` returned no attached device; automated source/native tests prove the launch contract, but an installed fresh-launch/system-dialog smoke and TalkBack remain device gates |

### Commands and results

- Focused UX-02A Vitest: 4 files / 45 tests passed.
- Locale validation: all 6 catalogues passed; the 5 non-English
  `ui-*-2026-09-15.4` catalogues retain expected pending-independent-review
  warnings. Web typecheck passed.
- Focused Chromium: 3/3 passed — first-use onboarding in `en-IN`, first-use
  onboarding in `kn-IN`, and same-command retry/new-command edit recovery.
- Production and demo `testDebugUnitTest`: each passed 6/6 tests, covering the
  plugin's three outcomes plus allowed/denied origin and resource branches.
- `make android-closed-beta`, using repository JDK 21 and the existing
  soft-launch API origin, passed web build, production Capacitor sync,
  `testDebugUnitTest` and `assembleDebug` (103 tasks). The local debug APK is
  28,287,126 bytes with SHA-256
  `4fba09d802a0c7e862a32257e99bda5d784428375968281b2cd03c33cf66164f`.
  It was not copied to the public download path or deployed.
- Full web Vitest passed 441/442 tests (97/98 files). The sole failure is the
  pre-existing `jezwebLearnerChrome.test.ts` expectation for `?v=jezweb-c4`
  while product code is already `?v=jezweb-c8`.
- Pinned-toolchain `make verify` passed Composer validation/audit, Pint,
  PHPStan, SQLite PHPUnit (316 tests / 3,083 assertions), PostgreSQL PHPUnit
  (360 tests / 5,224 assertions), frozen pnpm install, high-severity audit,
  locale validation, OpenAPI/generated contracts and release-contract. It
  then stopped at the pre-existing `soft-launch-contract-test` fixed-string
  assertion. No UX-02A test failed.
- `make e2e` passed reconciliation PHPUnit (4 tests / 103 assertions) and
  Chromium 17/27. All 3 added UX-02A journeys passed; the 10 failures are the
  existing serious color-contrast violations across sign-in, conversation,
  learner and operator themes.
- `make build` passed. `git diff --check` passed. Focused ESLint found only the
  previously recorded `react-hooks/set-state-in-effect` error at
  `RecordingTurn.tsx:109`; no new lint finding was introduced.

### Conclusion and retained gates

UX-02A implementation scope is complete. It is not marked closed because its
literal `make verify` exit criterion, the repository-wide E2E accessibility
gate and the physical fresh-install Android smoke are not green. Those
pre-existing/out-of-environment gates must be resolved and rerun before
UX-02A closure. UX-02B remains unstarted and requires its own bounded work.

## UX-07A: Accepted review semantics implementation — 15 September 2026

### Scope reviewed

- One persisted-timezone classifier for D-0289's delayed-review attempt
  window, consumed by the learner Progress read model.
- D-0290-aligned write naming and proofs that an eligible incorrect attempt
  clears one due occurrence, does not become mastery evidence and schedules
  the existing deterministic remediation review.
- Atomic learner/operator metric rename through OpenAPI 0.47.0, generated
  contracts, web consumers, demo/E2E fixtures and every locale catalogue.
- Existing organization predicates on the touched Progress read, operator
  metrics read and occurrence writer, with PostgreSQL cross-tenant proofs.
- No onboarding, Home layout, quiz rendering, Android, review interval,
  database schema or event-emission change.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Persisted timezone | Pass | `DelayedReviewAttemptWindow` converts both instants using the occurrence row's `timezone`; server/sync time is not accepted by the classifier |
| Exact attempt boundaries | Pass | Data-provider tests cover due−1 second, exact due, local 23:59:59, next-day 00:00:00 and 00:00:01 |
| UTC/Kolkata rollover | Pass | A UTC next-date attempt at 23:59:59 Asia/Kolkata remains on time; the following UTC instant at local 00:00:00 is late |
| Attempt clears regardless of correctness | Pass | PostgreSQL lifecycle test submits an incorrect attempt exactly at due time and observes the single due occurrence cleared once |
| Mastery safety | Pass | The incorrect attempt is not mastery evidence; the earlier correct attempt remains authoritative |
| Deterministic remediation | Pass | Incorrect attempt creates the existing `immediate_retry` schedule at the attempt instant; intervals were not changed |
| Idempotent replay | Pass | Same submission command/result replay creates one learning command, does not duplicate occurrences and does not clear twice |
| Cross-tenant reads | Pass | Learner Progress and operator attempted-count reads return one organization-owned occurrence each, excluding the other tenant |
| Cross-tenant write | Pass | Organization A's eligible attempt clears only A's occurrence; the matching organization B occurrence remains open |
| API/client/UI semantics | Pass | Old surfaced review-completion metrics are absent; OpenAPI/generated clients and learner/operator labels use reviewed/attempted |
| Migration/ADR/master plan | Pass | Legacy internal storage names remain; no migration. D-0289/D-0290 clarify rather than amend ADR-0028, so no ADR or master-plan amendment |
| Additional authority | Pass | No DR-0049 was needed; the user's Accountable Product Owner and Technical Decision Owner instruction authorized this checkpoint |

### Exact boundary matrix

All examples use the persisted occurrence timezone `Asia/Kolkata`.

| Boundary | `due_at` UTC | Attempt UTC | Local result |
|---|---|---|---|
| Due minus 1 second | 2026-09-15 03:30:00 | 2026-09-15 03:29:59 | Not eligible |
| Due exactly | 2026-09-15 03:30:00 | 2026-09-15 03:30:00 | Reviewed on time |
| 23:59:59 due date | 2026-09-15 03:30:00 | 2026-09-15 18:29:59 | Reviewed on time |
| 00:00:00 next date | 2026-09-15 03:30:00 | 2026-09-15 18:30:00 | Reviewed late |
| 00:00:01 next date | 2026-09-15 03:30:00 | 2026-09-15 18:30:01 | Reviewed late |
| UTC/Kolkata date rollover | 2026-09-15 23:30:00 (05:00 local on 16 Sep) | 2026-09-16 18:29:59 (23:59:59 local on 16 Sep) | Reviewed on time |
| UTC/Kolkata rollover +1 second | 2026-09-15 23:30:00 | 2026-09-16 18:30:00 (00:00:00 local on 17 Sep) | Reviewed late |

### Commands and results

- Focused SQLite learner/API and operator query:
  `phpunit LearnerProgressDashboardTest.php OperatorLearningMetricsQueryTest.php`
  — 11 tests / 28 assertions passed.
- Focused PostgreSQL tenancy:
  `phpunit.postgres.xml ProgressDashboardTenancyTest.php` — 2 tests / 19
  assertions passed.
- Focused PostgreSQL replay, incorrect-attempt and remediation lifecycle:
  filtered `CurriculumPublicationLifecycleTest` — 1 test / 96 assertions
  passed.
- Focused web Progress/operator Vitest — 2 files / 17 tests passed.
- OpenAPI/generated-contract check passed. Locale validation passed with the
  five already-recorded pending-independent-review locale warnings. Workspace
  typecheck and API PHPStan passed. `git diff --check` passed.
- Literal `make verify` stopped at the toolchain guard: ambient Node 22.22.1,
  required Node 24.18.1. The pinned-toolchain rerun passed Composer validation
  and audit, Pint, PHPStan, SQLite PHPUnit (316 tests / 3,083 assertions),
  PostgreSQL PHPUnit (360 tests / 5,225 assertions), frozen pnpm install,
  high-severity audit, locale validation, OpenAPI/generated-contract and
  release-contract checks. It then failed the pre-existing
  `soft-launch-contract-test`, whose fixed-string assertion expects an older
  `run-soft-launch.sh` command shape.
- Supplemental full web Vitest passed 432/433 tests (97/98 files); the sole
  failure is the pre-existing `jezwebLearnerChrome.test.ts` expectation for
  APK cache-buster `?v=jezweb-c4` while product code already uses `?v=jezweb-c8`.
  Focused UX-07A web tests remain green. Lint retains three existing errors and
  two warnings in `VoicePracticePanel.tsx`, `browserSpeech.ts` and
  `RecordingTurn.tsx`.
- Literal `make e2e` passed the API reconciliation tests (4 tests / 103
  assertions) before the ambient Node mismatch prevented the web server.
  Pinned-toolchain Playwright ran after installing its locked Chromium and
  passed 13/24; all 11 failures are pre-existing serious color-contrast
  violations in sign-in/operator, learner Home and the live-AI chip. No
  UX-07A contract, runtime or semantic failure was reported.

### Conclusion and retained gates

UX-07A implementation scope and focused correctness evidence are complete.
The checkpoint is not marked closed because its explicit `make verify` and
Progress-journey `make e2e` exit criterion is not green. The unrelated
soft-launch assertion, stale APK unit expectation, lint debt and contrast
violations are retained for their owning checkpoints; repairing them here
would violate UX-07A's scope boundaries.

## UX-AUDIT-00: Metric taxonomy and review decisions — 15 September 2026

### Scope reviewed

- APP-0084 records the Accountable Product Owner and Technical Decision Owner's
  acceptance of D-0289/MUX-001 and D-0290/MUX-002.
- Git-history reconciliation of the then-unsupported legacy D-0294–D-0297,
  D-0300 and D-0302 references in the non-authoritative UI/UX companion.
- Exact mapping of the supplied onboarding claim and source-quality status.
- Privacy-safe UX funnel event names, dimensions, prohibitions, retention,
  access, organization scope and deletion behavior.
- Read-only inspection of current delayed-review submission, mastery,
  remediation and Progress read-model behavior.
- No application code, schema, API, generated client, localization or event
  emission.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Required first commit | Pass | `132a847 docs: add Android mobile UX audit execution plan`; audit plan was the only file in that commit and was committed unchanged |
| MUX-001 authority/boundaries | Pass | APP-0084; D-0289; decision packet §3 includes due−1s, exact due, local 23:59:59, next-day 00:00:00 and UTC/Kolkata rollover |
| MUX-002 existing behavior | Pass | `DailyMissionAttemptService` now calls correctness-independent `recordEligibleAttempt`; false creates `not_mastered`/does not promote an existing state and `scheduleReview` sets immediate remediation due time |
| MUX-002 future delta | Pass | Decision packet §4 inventories Progress read-model, API/OpenAPI/generated client, localization/UI and required tests for UX-07A |
| Ledger history | Pass | `git log --all -S` found UI companion references in `855c212` and `7c1f074`; a scan of every historical `docs/DECISIONS.md` revision found none of D-0297/D-0300/D-0302 |
| Authoritative checkpoint state | Pass | At UX-AUDIT-00 execution, UI/UX companion front matter, roadmap and progress log agreed with DECISIONS/STATUS: UX-00/UX-01 complete; UX-02–UX-10 proposed. Later checkpoints require and retain their own APP/D records |
| Onboarding claim mapping | Pass with limitation | Only candidate is `LearnerJourney`'s `OnboardingForm` on authenticated `#home` when `onboarding.completed=false`; external source unknown, so 35% is unverified |
| Funnel reproducibility | Pass | `UX_FUNNEL_EVENT_CONTRACT.md` defines entry, submit, success, cohorts, windows and deduplication |
| Privacy-safe contract | Design pass; independent review pending | Event envelope and property allowlists, forbidden content, organization scoping, access, retention and deletion are explicit; no emission exists |
| Review UI vocabulary | Open at UX-AUDIT-00; subsequently closed | This documentation checkpoint inventoried the conflicting fields; UX-07A later migrated surfaced API/client/UI terms under D-0291 |
| ADR/master-plan impact | Pass | No amendment required: D-0289/D-0290 clarify an undefined read-model/calendar and UX vocabulary without changing ADR-0028's deterministic learning authority |
| DR need | Pass | No DR-0049: APP-0084 supplied all product/technical authority needed for this documentation checkpoint |

### Verification and limitations

- Documentation references and formatting were inspected after edits.
- Plain `make verify` stopped at the toolchain gate because the ambient shell
  exposed Node 22.22.1 instead of required Node 24.18.1.
- The authoritative pinned-toolchain/network-enabled rerun of `make verify`
  **failed** at `soft-launch-contract-test` after these successful stages:
  Composer validation and locked audit (no advisories), Pint, PHPStan, SQLite
  PHPUnit (309/309, 3,074 assertions), PostgreSQL PHPUnit (359/359, 5,206
  assertions), frozen pnpm install, high-severity pnpm audit (zero high;
  two moderate reported), locale validation, OpenAPI/generated-contract check
  and release-contract check.
- The pre-existing soft-launch failure is in
  `scripts/test-soft-launch-environment.sh`: it expects the substring
  `ai.globalspokenenglish.com,127.0.0.1:9080`, while current
  `persist_public_origin` inserts the staff static-IP origin between those
  stateful domains. The separate observe-fixture script passed.
- Pinned supplemental stages that `make verify` did not reach: workspace
  typecheck passed; lint failed with three errors and two warnings in existing
  `VoicePracticePanel.tsx`, `browserSpeech.ts` and `RecordingTurn.tsx`; web
  Vitest passed 431/432 tests (97/98 files) and failed only because
  `jezwebLearnerChrome.test.ts` expects APK cache-buster `?v=jezweb-c4` while
  current product code uses `?v=jezweb-c8`.
- These failures predate and are unrelated to the documentation-only diff.
  They were not repaired because UX-AUDIT-00 forbids application/test changes.
- The supplied onboarding/paywall figures have no named external dataset,
  export, cohort, date window or calculation and are not accepted baselines.
- No independent privacy/security/tenancy reviewer role was exercised in
  APP-0084; the contract must pass that named review before implementation or
  emission.
- At UX-AUDIT-00 completion, UX-07A and application changes remained
  unauthorized/unstarted. UX-07A was subsequently authorized and implemented
  under D-0291; telemetry storage, third-party analytics, production and Google
  Play remain unauthorized/unstarted.

## D-0288: Variant C jezweb-ux Android chrome — 13 September 2026

### Scope reviewed

- Learner frame gains `ge-application-frame--jezweb` and
  `data-ge-ux="variant-c-jezweb"`. CSS layer `jezwebLearnerChrome.css`
  loads after Variant A chrome.
- Native Android status/navigation/splash bars use light canvas
  `#FAFBFA` and brand green `#006B3F`.
- Homepage APK href cache-buster `?v=jezweb-c`.
- No LessonPractice, audio curriculum, ConversationOrchestrator, or TTS
  edits.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Jezweb learner class | Pass | `App.tsx` learner/guest Mira frames include `--jezweb` |
| Bottom tabs / no FAB | Pass | Narrow jezweb nav is a 5-column icon tab bar |
| Continue/chips flow | Pass | Variant A Learn structure retained; jezweb only restyles chrome |
| Native bars | Pass | `colors.xml` / `styles.xml` / `studio_launch.xml` |
| APK download href | Pass | `androidDownload.ts` `?v=jezweb-c` |
| Lesson/audio paths | Pass | No LessonPractice / orchestrator / TTS edits |
| LAN cleartext | Pass | `117.254.108.143` already in network security config |

### Verification and limitations

- Focused web Vitest (this agent): 6 files / 29 tests passed
  (`jezwebLearnerChrome`, `miraLearnerChrome`, `miraGescGreenPin`,
  `androidNativeStudioThemeContract`, `MarketingLanding`, `App`).
- ESLint on changed TS/TSX passed (`--max-warnings=0`).
- Real-Studioz `make android-closed-beta` BUILD SUCCESSFUL (103 Gradle
  tasks) with `VITE_MOBILE_API_ORIGIN=http://117.254.108.143:8080`.
  `aapt2 dump badging`: `com.globalenglish.app` 1.0 (1), targetSdk 36,
  `RECORD_AUDIO`. APK contains `variant-c-jezweb` and
  `VITE_MOBILE_API_ORIGIN=http://117.254.108.143:8080`.
- `make soft-launch-refresh-web` staged the APK; public
  `http://117.254.108.143:8080/downloads/global-spoken-english-android.apk?v=jezweb-c`
  returns 200 / 55,616,920 bytes. Homepage marketing chunk href is
  `?v=jezweb-c`.
- MagicPath was over quota and Superdesign CLI was unauthenticated, so the
  named `03-variant-c-jezweb-ux` canvas file was not imported pixel-for-pixel.
  The implemented chrome follows jezweb design-review (professional light
  product UI, semantic tokens, equal tabs) on the locked learner flow.
- `make verify` / `make e2e` were not run in this pass.
- Play / production remain unauthorized.

## D-0287: Variant A learner chrome — 13 September 2026

### Scope reviewed

- Demo My Levels (`DemoPhase1Shell`) and authenticated Learn
  (`LevelPathPanel`) restyled to Variant A: continue hero, level chips,
  room chips. Learner frame tokens, pill nav, locale note, skip-link
  visibility.
- No LessonPractice, audio curriculum, ConversationOrchestrator, or TTS
  edits. Marketing landing routes unchanged.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Variant A Learn/My Levels structure | Pass | Continue hero + chip rows in `DemoPhase1Shell` and `LevelPathPanel` |
| Scaffolding copy removed | Pass | Phase 1 eyebrow/lead not rendered; room summaries not shown on Learn |
| Language fallback | Pass | `shell-locale-note` under locale control; `shell-preview` banner removed from `App.tsx` |
| Skip-link | Pass | Hidden with clip-path until `:focus` / `:focus-visible` |
| Tokens | Pass | Learner `--ge-*` aligned to `#006B3F` / `#fafbfa` / `#1a1a1a` family |
| Nav / routes | Pass | ADR-0026 five destinations retained; demo Phase 1 modules still linked |
| Lesson/audio paths | Pass | No LessonPractice / orchestrator / TTS edits |

### Verification and limitations

- Focused web Vitest (this agent, Node 22.14 / engine warning vs pinned
  24.18.1): 4 files / 41 tests passed (`DemoPhase1Shell`,
  `LevelPathPanel`, `miraLearnerChrome`, `App`).
- `tsc -b` for `@global-english/web` passed. ESLint on changed TS/TSX
  passed (`--max-warnings=0`).
- Local Vite demo (`VITE_LOCAL_DEMO=true`, port 4174) screenshots:
  `#levels` continue hero + chips; `#learn` Variant A path; skip-link
  not painted; yellow fallback banner absent.
- `make verify` / `make e2e` were not run: PHP/Composer and pinned Node
  24.18.1 are not available here; Playwright browsers were not installed.
- Soft-launch is **not** deployed by this change.
- F0 / Play / production remain unauthorized.

## D-0286: Guest `#home` Mira chrome — 13 September 2026

### Scope reviewed

- Default unsigned `#home` switched from `MarketingLanding` to
  `GuestMiraHome` (Mira hero + next-lesson card + Sign in).
- `#sign-in` dedicated page preserved. Marketing landing kept at
  `#about` / `#marketing`.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Guest default home | Pass | `showGuestMiraHome` for anonymous/checking/unavailable `#home` |
| Sign-in page | Pass | `#sign-in` still `showDedicatedSignIn` |
| Marketing retained | Pass | `#about` and `#marketing` still render `MarketingLanding` |
| Lesson/audio paths | Pass | No LessonPractice / orchestrator / TTS / ConversationJourney edits |

### Verification and limitations

- Focused web Vitest (this agent, Node 22.14): 4 files / 23 tests passed
  (`GuestMiraHome`, `App`, `miraLearnerChrome`, `miraGescGreenPin`).
  Web `tsc -b` passed. ESLint on changed TS/CSS passed.
- Local Vite (`VITE_LOCAL_DEMO=false`, `VITE_UI_TRANSLATION_PREVIEW=true`)
  guest session `unavailable`: `#home` rendered GuestMiraHome (`Today` /
  Mira / Speak now; no `.marketing-landing`). `#sign-in` stayed the
  dedicated form. `#about` and `#marketing` still rendered MarketingLanding
  (`Let’s speak English fluently`).
- `make verify` / `make e2e` were not run: PHP/Composer and pinned Node
  24.18.1 are not available here. Repository Playwright browsers were not
  installed; local screenshots used system Chrome via Playwright.
- Soft-launch is **not** deployed by this change. Operator rebuild:
  `VITE_UI_TRANSLATION_PREVIEW=true pnpm --filter @global-english/web build`
  into `apps/web/dist`, or `make soft-launch-refresh-web`.
- F0 / Play / production remain unauthorized.

## D-0285: Mira / Wonder learner chrome on soft-launch — 13 September 2026

### Scope reviewed

- Learner chrome/layout/styling from PR #6 (`cursor/learner-ux-elevate-5f47`)
  ported onto `codex/confidence-conversation`.
- Home, onboarding, primary nav FAB, Speak room presentational chrome.
- Explicitly out of scope: LessonPractice, LevelSessionAudioStreamer,
  ConversationOrchestrator, listen-and-learn / Levels audio, TTS adapters,
  Attendance / AI Teacher / Daily Activity / My Levels / Notice Board
  product wiring (demo modules kept), marketing/Plesk.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Chrome isolation | Pass | New `miraLearnerChrome.css` after `miraGescGreenPin.css`; UI files under `apps/web/src/ui/` |
| Five-destination nav | Pass | `PrimaryNavigation` still Home · Learn · Speak · Progress · 1:1; Speak is the FAB |
| Soft-launch home route | Pass | Authenticated `#home` remains TutorHomeHero + LearnerJourney; demo `#home` remains DemoPhase1Shell |
| Lesson/audio paths | Pass | No edits to LessonPractice, LevelSessionAudioStreamer, ConversationOrchestrator, ConversationJourney.tsx, SpeakRoom.tsx, or TTS adapters |
| Brand | Pass | `#006B3F`, original `mira.jpg` portrait, not a Supernova clone |
| Locales | Pass | `ui-*-2026-09-13.1` pending independent review |

### Verification and limitations

- Focused web Vitest (this agent, Node 22.14): 8 files / 42 tests passed
  (`miraLearnerChrome`, `miraGescGreenPin`, AvatarSlot, OnboardingStepper,
  TapToPlay, DemoPhase1Shell, LearnerJourney, App). Design-system: 12
  passed. Localization: 35 passed. Web `tsc -b` passed. ESLint on changed
  TS files passed with `--max-warnings=0`.
- Full web Vitest also ran 352 tests: 348 passed; 4 failed. Three of those
  are the pre-existing Node 22 / jsdom Android private-media fetch failures
  recorded in D-0284 (`WebLearnerJourneyApi` / `WebLevelSessionsApi`). The
  fourth was a chrome-test path typo and is fixed on this branch.
- `make verify` / `make e2e` were not run: PHP/Composer and pinned Node
  24.18.1 are not available here. `node scripts/validate-locales.mjs`
  cannot import `.ts` on Node 22; catalogue hashes were checked via the
  localization Vitest suite.
- Soft-launch is **not** deployed by this change. Operator rebuild:
  `VITE_UI_TRANSLATION_PREVIEW=true pnpm --filter @global-english/web build`
  into `apps/web/dist`, or `make soft-launch-refresh-web`.
- F0 / Play / production remain unauthorized.

## D-0284: Mira GESC-green theme pin and Android mic — 13 September 2026

### Scope reviewed

- Theme/CSS/tokens/chrome only: pin Superdesign Futuristic Learning Studio
  to Mira GESC-green without rewriting lesson or audio paths.
- Capacitor Android `RECORD_AUDIO` runtime request for Speak/recording.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Theme isolation | Pass | `miraGescGreenPin.css` imported after `studioExperience.css`; Studio layout now uses `--ge-studio-*-rgb` tokens |
| Brand colours | Pass | `#006B3F` / `#0B1210` / `#3DDC97` in pin, `theme-color`, and interface theme |
| Lesson/audio paths | Pass | No edits to LessonPractice, LevelSessionAudioStreamer, ConversationOrchestrator, ConversationJourney, BrowserAudioRecorder, or TTS adapters |
| Android mic | Pass | Demo and production `MainActivity` request `RECORD_AUDIO`; manifests keep the permission and add optional microphone hardware |
| Secure context | Pass | Demo Capacitor `server.androidScheme` is `https` / `localhost` |
| Marketing / live host | Pass | No `apps/marketing` or production cutover files changed |

### Verification and limitations

- Focused web unit tests passed: `miraGescGreenPin`, `interfaceTheme`,
  `InterfaceThemeControl`, `capacitorAndroidConfig`,
  `androidMicrophonePermission` (9 tests). `App.test.tsx` plus those files:
  28 passed. Design-system package: 12 passed.
- Full web Vitest: 342 passed, 3 failed in untouched
  `WebLearnerJourneyApi` / `WebLevelSessionsApi` Android private-media
  fetch tests. Those files are not in this diff; the failures look like the
  Node 22.14 / jsdom environment (toolchain pin is Node 24.18.1) rather than
  the theme pin.
- `make verify` / `make e2e` were not run: PHP/Composer and the pinned
  Node 24.18.1 toolchain are not installed in this agent workspace.
- Physical-device Speak/mic confirmation and `make demo-android` APK rebuild
  remain operator steps on real-studioz. HTTP `:8080` cannot record in a
  browser; sideload the bundled APK.
- F0 / Play / production remain unauthorized.

## D-0283: UX-01 Studio programme complete — 6 September 2026

### Scope reviewed

- UX-01B visual-review closeout plus UX-01C–G: complete Studio landing,
  sign-in handoff, authenticated learner frame, lazy route families, bilingual
  evidence and the UI-only product holes that blocked those surfaces.

### Review result

| Check | Result | Evidence |
|---|---|---|
| UX-01B review | Closed by instruction | Accountable Product Owner asked to remove UI/UX blockers and complete the implementation; no further screenshot ceremony |
| Landing hierarchy (UX-01C) | Pass | Hero, four-step preview with Type instead, five outcomes, Swathi/AI boundary, catalogue Demo/Modules/Premium (₹0 / ₹350 / ₹500 GST exclusive), trust/readiness, closing CTA |
| Sign-in handoff (UX-01D) | Pass | Dedicated `#sign-in` uses Studio tokens; username/password remain Sanctum; no credential persistence; duplicate sign-in hint removed from that page |
| Learner frame (UX-01E) | Pass | Guest and learner use Studio frame; operator stays distinct; Home due-review uses `interval_days` rather than `next_mission` duration |
| Route split (UX-01F) | Pass | Initial `index-DSDJ_Aqr.js` 78.58 kB gzip; guest+marketing 235.77 kB gzip; operator 52.57 kB gzip is not on the learner path |
| Role-play presentation | Pass | Observe and Rehearse are shown; learner Continues. API still starts at observe. D-0215 optional-example rule is not amended |
| Voice confirm / model audio / Progress | Pass | Accept/edit/discard always shown; 0.75× slow + resume; Progress retry and catalogue labels |
| Accessibility / reflow | Pass | Existing 21 Chromium journeys include axe, keyboard, 320–1280, 200%/400% text, 44-pixel targets |
| Visual evidence | Pass, Kannada preview | [ux01g](evidence/ux01g/) landing, sign-in and Home at 320/1280 in en-IN and kn-IN; landing copies also in [ux01c](evidence/ux01c/) |
| Scope control | Pass | No payment provider, schema, APK, Wave 5 or `APP_ENV=production`. Unused Academy CSS deleted |

### Verification and limitations

- Exact pinned `make verify`: pass. PHP 8.4.23, Composer 2.10.2, Node 24.18.1,
  pnpm 11.18.0. Backend 249/249 (2,652 assertions), PostgreSQL 341/341 (4,775
  assertions), web 343 tests, localization 35, design system 12. One existing
  moderate pnpm advisory remains non-blocking. Locales validate with five
  pending-independent-review warnings as expected.
- `make e2e`: 4/4 integration (103 assertions) and 21/21 Chromium passed after
  overflow `minmax(min(…rem, 100%), 1fr)` and Role-play `exact` heading locators.
- `make build` passed; Vite still warns that the lazy marketing chunk is 676 kB
  minified (157.19 kB gzip). Combined guest download stays under 250 kB gzip.
- PHPUnit SQLite/Postgres now set `AUTH_RECOVERY_DIGEST_KEY` to the documented
  local/testing dummy so an empty inherited `APP_ENV` cannot blank it.
- Kannada/Hindi/Malayalam/Tamil/Telugu `ui-*-2026-09-06.1` catalogues remain
  pending independent review. Screenshots are development-preview, not
  translation approval. hi/ml/ta/te currently copy English source.
- Cursor IDE browser could not reach `127.0.0.1`; landing and sign-in were
  exercised with Playwright against the preview origin instead.
- `futuraExperience.css` remains imported for shared shell chrome.
  Illustration filenames may remain `tutor-maya.png` (D-0218).
- Remaining honestly incomplete: UX-02 first-use (placement, accessibility
  setup, audio orientation), UX-08 live payments, UX-10 APK/TalkBack, Wave 5
  stable HTTPS origin.

## Soft-launch sign-in restored — 3 September 2026

### Scope reviewed

- Restore password sign-in after the Laravel API could not bind `:8000`.

### Review result

| Check | Result | Evidence |
|---|---|---|
| API health | Pass | `GET /api/v1/health/live` 200 through Caddy `:9080` and the live tunnel |
| Password mode | Pass | `GET /api/v1/auth/password/mode` 200 `password_only` from the tunnel origin |
| Sign-in form | Pass | `#sign-in` renders username/password with no pre-submit error |
| Wrong password | Pass (expected fail) | Dummy `tester.one` shows `auth.password.error` after the API responds |
| Env contract | Pass | `scripts/test-soft-launch-environment.sh` |

### Known limitations

- Tunnel hostname rotated to `https://viii-throw-literature-caroline.trycloudflare.com`. The previous Quick Tunnel origin is dead.
- Another local process still holds `:8000`; this stack uses `GE_SOFT_LAUNCH_API_PORT=8012`.
- Closed-beta APK still targets an older tunnel origin.

## Academy homepage template — 3 September 2026

### Scope reviewed

- Rebuild guest `#home` to the Global Academy page structure (slider, Login/Join Now, intro band, why tiles, how-it-works, current Direct prices) using existing product copy only.

### Review result

| Check | Result | Evidence |
|---|---|---|
| 5-image slider | Pass | Live `banner-3.jpg` 1600×746; 5 slides + arrows + dashes |
| Login + Join Now | Pass | Header `session.label` + `marketing.hero.cta_sign_in`; intro/plan/join CTAs |
| New-site copy / prices | Pass | Catalogues unchanged; cards ₹0 / ₹350 / ₹500; no fluency-guarantee |
| App tests | Pass | `src/App.test.tsx` 19/19 |

### Known limitations

- Sign-in is served from the Laravel API on `GE_SOFT_LAUNCH_API_PORT` (currently `8012` when `:8000` is occupied).
- Plan names on the public cards are English SKU names (Two-day demo / Modules / Premium), matching the API.
- Android APK not rebuilt.

## Academy public template — 3 September 2026

### Scope reviewed

- Replace the guest website chrome with the Global Academy template from `globalspokenenglish.com` (teal bar, coral Sign in, full-bleed slider, mint stats). Product copy, prices, and auth stay on the current platform.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Guest academy class + light default | Pass | `ge-application-frame--academy`; storage key `ge-interface-theme-preference-v2`; App/theme tests 19+4 |
| Live landing chrome | Pass | Tunnel screenshot: `#17ad90` header, coral Sign in, 1920px slider, gold stats on mint gradient |
| Session checking shows marketing | Pass | `showMarketingLanding` no longer waits for `anonymous` |
| Old commercial copy not restored | Pass | Current marketing catalogues; no ₹320/₹3000 or fluency-guarantee claims |
| API sign-in on this host | Pass (ops) | Soft-launch API rebound to `127.0.0.1:8012`; tunnel `password/mode` 200 `password_only`; systemd service active |
| Android APK template parity | Not claimed | APK not rebuilt |

### Known limitations

- Appearance picker is hidden for guests so the public site stays academy-light. Signed-in learners still have light/dark.
- Waves on the slider are those baked into the academy banner JPEGs, not a second CSS overlay.
- Tunnel hostname rotated; the published APK still targets the previous origin.

## Closed-beta APK on tunnel origin — 2 September 2026

### Scope reviewed

- Rebuild production-path Android APK (`com.globalenglish.app`) against the live Cloudflare tunnel so staff can install from the website download link without Tailscale.

### Review result

| Check | Result | Evidence |
|---|---|---|
| JDK 21 assemble | Pass | Temurin 21.0.12+8; `make android-closed-beta`; Gradle BUILD SUCCESSFUL, 103 tasks |
| Package identity | Pass | `aapt2 dump badging`: `com.globalenglish.app`, versionName 1.0, targetSdk 36, `INTERNET` / `RECORD_AUDIO` |
| Baked API origin | Pass | APK JS contains `https://serious-writer-associate-interest.trycloudflare.com` and client `019fdd3f-4bba-720b-9e4e-a771ecccdd7f`; does not contain `117.254.108.143` |
| Download link | Pass | Tunnel `content-length: 28654905`; SHA-256 `0f946ec6b2caea217f0824232ad93637d8f15a0678efa42d292fa6f40d382aae` |
| Native UI markers | Pass | APK embeds `marketing-hero__banner` and `ge-application-frame--native-android` |
| Physical install | Not claimed | No `adb` device on this host |

### Known limitations

- Debug keystore only. Not Play App Signing.
- The APK is pinned to the current Quick Tunnel hostname. A tunnel restart requires a rebuild.
- Website dist was rebuilt without `VITE_MOBILE_*` so the PWA stays Sanctum cookie auth; only the APK binary is baked for native Passport.
- Production/Play remain unauthorized.

## D-0282: UX-01B futuristic first screen implemented — 6 September 2026

### Scope reviewed

- Only UX-01B visual tokens actually used by the guest shell and the public
  first screen; later landing sections and authenticated frames remain later
  checkpoints.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Legacy replacement | Pass | Guest App class changed from Academy to Studio; legacy logo and all five carousel images/controls/timers are absent from the rendered first screen |
| Action hierarchy | Pass | One primary sign-in link and one secondary four-session demonstration link appear in the hero; later-page CTAs are outside UX-01B |
| Product truth | Pass | Canonical four-stage sequence is visible and Swathi is explicitly described as AI whose advice does not change deterministic records |
| Original visual system | Pass | Code-native wordmark, voice core, grid and orbits; no remote font, stock hero art, copied mascot, autoplay media or WebGL dependency |
| Accessibility | Pass | Axe across all six interface locales, keyboard/skip-link, visible focus, ≥44 CSS-pixel controls, reduced motion, self-hosted script fonts and 200%/400% text stress pass |
| Responsive evidence | Pass automated | English and Kannada-preview at 320/1280 have `scrollWidth === clientWidth`; screenshots recorded under `docs/evidence/ux01b/` |
| Accountable visual approval | Pending | Product Owner must accept or request revisions to the four review captures before UX-01C |
| Scope control | Pass | No schema/API/auth/payment/provider/navigation/learner-record/APK/production change; later guest sections remain UX-01C |

### Verification and limitations

- Exact pinned `make verify`: pass. Backend 249/249 (2,652 assertions),
  PostgreSQL 341/341 (4,775 assertions), web 339 tests, localization 35 and
  design system 12. One existing moderate pnpm advisory remains non-blocking.
- `make e2e`: 4/4 integration (103 assertions) and 21/21 Chromium passed.
- `make build` passed; the ordinary approved-only release and subsequent
  source-fingerprint refresh produced entry `index-A6DfU909.js`. The existing
  approximately 313.79 kB gzip initial bundle warning remains assigned to
  UX-01F code splitting.
- Kannada screenshots are development-preview evidence, not renewed independent
  translation approval. The remainder of the old public landing page is
  intentionally deferred to UX-01C and may look visually transitional below
  the new first fold.

## D-0281: UX-01A served-build freshness complete — 6 September 2026

### Scope reviewed

- Only UX-01A: deterministic served-web release identity, conditional
  soft-launch rebuilds and public-shell cache versioning/invalidation.
- D-0280's user-reported keyboard pass and UX-00 closure.

### Review result

| Check | Result | Evidence |
|---|---|---|
| UX-00 human exit | Pass | Accountable Product Owner reported `Keyboard: passed` at the required 320 CSS-pixel smoke; Android TalkBack remains a release gate |
| Deterministic release identity | Pass | SHA-256 covers web source/public/config, root locks/workspace configuration, shared contracts/design-system sources and locale catalogues; stability and mutation tests pass |
| Stale-build prevention | Pass | Soft launch compares `.soft-launch-web-build` to current inputs and rebuilds on mismatch; `make soft-launch-refresh-web` is available and an unchanged repeat preserved the entry digest |
| Browser cache transition | Pass | Vite embeds the 64-character release in `/service-worker.js?release=…`; activation deletes only caches with the `global-english-public-shell-` prefix |
| Protected local data | Pass | The worker cache cleanup does not open or delete IndexedDB, and authenticated/API paths remain excluded from public-shell caching |
| Controlled mutation proof | Pass | Source mutation changed release `a14a9311…d631`→`a9d06362…4557`, entry `index-DRG7SBfg.js`→`index-DARKM3lG.js`, and index SHA-256 `f63664aa…9546`→`bc06434c…466` |
| Scope control | Pass | No schema, API, auth, payment, AI-provider, learner-record, navigation, APK or production change; UX-01B remains separate |

### Verification and limitations

- Focused Vitest: 2 files, 3 tests passed; web lint and typecheck passed;
  soft-launch environment contract and `git diff --check` passed.
- Exact pinned `make verify` passed: 249 standard backend tests (2,652
  assertions), 341 PostgreSQL tests (4,774 assertions), 71 web test files (339
  tests), 35 localization tests and 12 design-system tests. `pnpm audit
  --audit-level=high` retains one non-blocking moderate advisory.
- `make e2e` passed 4/4 reconciliation integration tests (103 assertions) and
  21/21 Chromium journeys after replacing the retired hard-coded `v7` cache
  assertion with the source-derived release identity. `make build` passed.
- The first browser invocation did not run because its configured temporary
  browser cache was empty. A second invocation reused an old approved-only
  preview server and correctly exposed a test-environment mismatch. After the
  pinned browser path and intended Kannada-preview server were used, the only
  application-contract failure was the obsolete `v7` assertion described
  above; the final complete run passed.
- The existing Academy presentation remains until UX-01B; UX-01A fixes why
  changed presentation could remain stale, but is intentionally not itself a
  visual redesign.

## D-0279: Futuristic Learning Studio plan accepted — 6 September 2026

### Scope reviewed

- Documentation-only selection and sequencing of the UX-01 visual direction.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Product authority | Pass | Accountable Product Owner explicitly requested a futuristic replacement and step-by-step implementation; D-0279 records the direction without changing the canonical navigation or learning rules |
| Visual definition | Pass | [UI/UX Master Plan §7.1](UI_UX_MASTER_PLAN.md#71-accepted-visual-character--futuristic-learning-studio) distinguishes a calm adult AI-learning studio from legacy Academy chrome, cyberpunk novelty and copied competitor trade dress |
| Incremental delivery | Pass | [UX-01A→G](UI_UX_MASTER_PLAN.md#112-ux-01--futuristic-learning-studio-implementation-sequence) provides one bounded sub-checkpoint at a time with explicit exit gates |
| Stale-template cause | Addressed in plan | UX-01A must make served build/cache freshness observable before visual implementation begins |
| Scope control | Pass | No code, schema, API, navigation, provider, payment, APK or production change; UX-00 keyboard closure remains first |

### Verification and limitations

- New D-0279 cross-document targets and whitespace were checked locally.
  A repository-wide local-link scan still reports the pre-existing missing
  `docs/DR-0039_PRONUNCIATION_AVATAR_PACING_TRANSLITERATION_LANGUAGES_REQUEST.md`
  target from `docs/STATUS.md`; this planning change neither created nor masks
  it. Application tests were not rerun because this decision changes no runtime
  file.
- The visual direction is approved; the actual UX-01B prototype still requires
  accountable screenshot review before the rest of the landing page is built.

## D-0278: UX-00 automated implementation complete — 6 September 2026

### Scope reviewed

- D-0277's exact UX-00 slice: local fonts, contrast, carousel behavior,
  narrow/enlarged-text reflow, operator semantics, entitlement truth,
  Playwright fixtures and non-root output paths.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Fonts and privacy | Pass | No runtime Google Font request; locally bundled full Latin/Kannada/Malayalam/Tamil/Telugu/Devanagari resources load in Chromium |
| Accessibility | Pass (automated) | Axe, keyboard, named landmarks/headings, carousel pause/resume and 44 CSS-pixel targets pass; 320/360/412 reflow and 200%/400% enlarged-text proxies have no document overflow |
| Entitlement failure states | Pass | Loading, ready, unavailable, absent and exhausted are distinct; recoverable refresh failure retains the last paid entitlement; institutional membership is not Direct-paywalled |
| Browser contract | Pass | `make e2e`: 4 integration tests / 103 assertions and 21/21 Chromium journeys, including learner, conversation, Role-play and operator paths |
| Full verification | Pass | Exact PHP 8.4.23, Composer 2.10.2, Node 24.18.1, pnpm 11.18.0 and PostgreSQL 18.4: `make verify`; 249 standard backend tests / 2,652 assertions, 341 PostgreSQL tests / 4,775 assertions, 69 web files / 336 tests, 35 localization tests and 12 design-system tests |
| Dependency gates | Pass with recorded debt | Composer audit has no advisory; pnpm high-severity gate passes with one moderate advisory |
| Production build | Pass with recorded debt | `make build`; 1.319 MB minified / 313.83 kB gzip main bundle retains the known large-chunk warning assigned to UX-01 |
| Responsive captures | Pass | [English 320](evidence/ux00/marketing-en-IN-320.png), [English 1280](evidence/ux00/marketing-en-IN-1280.png), [Kannada 320](evidence/ux00/marketing-kn-IN-320.png), [Kannada 1280](evidence/ux00/marketing-kn-IN-1280.png); Kannada uses explicit development preview |

### Security, privacy and tenancy

- No credential, learner audio, transcript or personal learner data was added
  to repository evidence. Runtime third-party font requests were removed.
- Entitlement caching is scoped to the current API/session resource and never
  carries an entitlement into another learner scope. Server-side deterministic
  authorization remains authoritative.
- API and generated client contracts remain synchronized. No production,
  payment, APK publication, schema expansion or later UX checkpoint is
  authorized by this work.

### Remaining gates and limitations

- Human keyboard smoke at 320 CSS pixels remains before UX-00 can be marked
  closed. Representative Android TalkBack remains a release gate.
- Every non-English catalogue changed after its prior exact-revision approval.
  The new `ui-*-2026-09-05.1` revisions are pending independent review;
  approved-only builds correctly fall back to English. Kannada screenshots are
  development-preview evidence, not production approval.
- The main-bundle warning and one moderate pnpm advisory remain recorded debt;
  neither defeats the configured high-severity or build gate.

## D-0276: Wave 6 Indic STT staff tests completed — 5 September 2026

### Scope reviewed

- Automated Wave 6 preflight plus human staff verification for Indic hold-to-talk, recovery, Role-play and guidance speech on soft-launch.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Preflight script | Pass | 24 pass, 0 warn, 0 fail on 5 September; `scripts/wave6-indic-stt-preflight.sh` |
| Staff runbook | Pass | [WAVE6_INDIC_STT_STAFF_TESTS.md](ops/WAVE6_INDIC_STT_STAFF_TESTS.md) |
| Indic adapter PHPUnit | Pass (sqlite) | 7/7 on host with `DB_CONNECTION=sqlite` |
| Staff hold-to-talk matrix | Pass | English 3/3; Kannada/English 3/3; noisy room 3/3; 25-second cap and clean failover observed |
| Role-play and Azure continuity | Pass | Premium Role-play Perform completed; Azure chat and mixed Kannada/English guidance TTS completed |
| Failure recovery | Pass | Empty/invalid local capture and recoverable STT failure retain the primary record control and a fresh retry command |
| Provider usage table | Pass | Tester counts: Indic STT succeeded 50; Azure STT failover succeeded 13; counts/booleans only in Git evidence |

### Known limitations

- At D-0276 completion, `make verify` was blocked by newly published
  `league/commonmark` advisories; D-0278 records the dependency update and a
  green exact-toolchain `make verify`.
- At D-0276 completion, the Playwright suite had accessibility/fixture
  failures; D-0278 records their repair and a 21/21 run.
- Production, Play and `APP_ENV=production` remain unauthorized.

## D-0275: Phase-1 UI catalogues approved — 2 September 2026

### Scope reviewed

- Independent review and approval of Malayalam, Tamil, Telugu and Hindi interface catalogues under APP-0076, completing Phase-1 UI locale governance after APP-0075 Kannada approval.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Governance approval | Pass | `ml-IN`, `ta-IN`, `te-IN`, `hi-IN` `reviewStatus=approved`, revision `ui-*-2026-09-02.2` |
| Locale validation | Pass | `@global-english/localization` vitest 31/31; zero pending-review warnings |
| Resolver / web policy tests | Pass | Updated approved-only and synthetic pending-governance fallback tests |
| Soft-launch locale picker | Unchanged | D-0206 keeps kn-IN + en-IN only in learner UI |
| Production/Play | Not authorized | Dummy checkout unchanged |

### Known limitations

- Named reviewer is the Accountable Product Owner, not separately commissioned linguist firms per locale.
- Full `make verify` / `make e2e` were not re-run on this host after the approval slice.

## D-0274: Kannada UI catalogue approved — 2 September 2026

### Scope reviewed

- Independent review and approval of the `kn-IN` interface catalogue under APP-0075.
- Production `approved-only` resolution for default Kannada chrome and removal of the language-fallback banner.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Governance approval | Pass | `kn-IN` `reviewStatus=approved`, `independentReview=true`, revision `ui-kn-IN-2026-09-02.2` |
| Locale validation | Pass | `@global-english/localization` vitest 31/31 |
| Web policy tests | Pass | `interfaceLocalization.test.ts`, `App.test.tsx`, `ApplicationErrorBoundary.test.tsx`, `InterfaceThemeControl.test.tsx` |
| Soft-launch build policy | Pass | `scripts/run-soft-launch.sh` now builds ordinary `approved-only` `dist/` |
| Hindi/Malayalam/Tamil/Telugu UI | Not claimed | Still `pending-independent-review`; English fallback remains |

### Known limitations

- Unapproved locale fallback notice in `en-IN` is now generic; derivative catalogue copies of the old Kannada-specific notice were not rewritten in this slice.
- Full `make verify` / `make e2e` were not re-run on this host after the approval slice.

## Web sign-in failure visibility — 2 September 2026

### Scope reviewed

- Dedicated `#sign-in` after typing a password: live tunnel origin POSTed sign-ins, then looked unchanged.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Failure visibility | Pass (this slice) | Error is in the form above Sign in; password is kept on failure |
| Anonymous after 200 | Pass (this slice) | `current()` that is not `authenticated` sets the same alert |
| Username HTML pattern | Pass (this slice) | `/v` flag accepts `[A-Za-z][A-Za-z0-9._\\-]{3,63}`; previous unescaped `-` was ignored by Chromium |
| Web tests | Pass (this slice) | `WebPasswordSignInPanel.test.tsx` |

### Known limitations

- Live `northstar` sign-in on the tunnel origin now reaches Home (`#home`) with `/api/v1/me/entitlement` HTTP 200 after applying `2026_09_01_000310_create_live_ai_talk_packs`.
- Soft-launch `dist/` was rebuilt with `VITE_UI_TRANSLATION_PREVIEW=true` for this slice.
- Full `make verify` / `make e2e` were not re-run after this slice.

## D-0273: Paid live-AI caps and talk packs — 1 September 2026

### Scope reviewed

- Monthly live-AI pools on Modules/Premium and prepaid talk packs.
- Demo and institutional behaviour left in place.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Decision | Accepted | DR-0047 Option A / ADR-0037 / APP-0074 / master plan 3.14 |
| OpenAPI / contracts | Pass | Version **0.40.0**; `pnpm run openapi:check` |
| PHP feature tests | Pass | `DirectEntitlementTest` + `CourtesyPremiumGrantTest` 13/13, 89 assertions (SQLite) |
| PHPStan / Pint | Pass | Entitlement files 0 PHPStan errors; Pint dirty files clean |
| Web tests | Pass | `SubscriptionPanel.test.tsx` (talk-pack gate); ConversationJourney paid-exhaustion copy; WebConversationJourneyApi problem-key; Operator entitlement panel |
| Locale catalogues | Pass | `@global-english/localization` vitest 31/31 including `validateCanonicalCatalogues`. Host Node is 22.22.1 so `pnpm run locale:check` (Node 24 `.ts` loader) was not executed. |
| PostgreSQL integration | Not run | Host PHP has no `pdo_pgsql` |
| Privacy inventory | `learner-privacy-v8` | `live_ai_talk_packs` count-only |
| Production/Play | Not authorized | Dummy checkout unchanged |

### Known limitations

- Dummy payment driver still completes in-process.
- Pack seconds are not refunded when a learner upgrades mid-month.
- Operator plan editor does not special-case talk-pack duration beyond stored `duration_value`.
- PostgreSQL `billing_period` CHECK (`talk_pack`) was not re-run on this host.

## Wave 7.1: Direct pathway publish — 1 September 2026

### Scope reviewed

- Lift `curriculum_publication_gate` with a real publish, not a comment.
- Whether graph `CurriculumPublisher` applies to the LPL program.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Wave 2A | Already complete | Commit `38130ab`; 25s cap, ledger, failover |
| LPL graph publication | Not used | `curriculum_workspaces` empty; no graph heads |
| `publishBlueprint` | Pass | Integration `LevelSessionDeliveryTest` 3/3 |
| Authored blueprint eligible | Pass | Unit `LessonPracticeBlueprintValidatorTest` 3/3 |
| Live publish | Pass | 17 processed, 17 published; replay skipped 17 |
| Prior drafts retained | Pass | 65 earlier draft rows remain |
| `APP_ENV=production` | Not set | Remains `soft-launch` |
| Dummy checkout | Unchanged | Wave 8 |
| Stable host | Not done | Wave 5 |

### Known limitations

- Entitled learners see Levels 3–17; demo remains 1–2.
- Teaching audio was already attached; publish changes eligibility/state.
- Wave 6 staff Indic tests remain open.
- Operator 7.6 not re-checked on `ai.globalspokenenglish.com`.

## Wave 5: stable host + legal pages — 1 September 2026

### Scope reviewed

- Whether `ai.globalspokenenglish.com` and WAN 80/443 can be cut over.
- Public privacy and account-deletion URLs for Play.

### Review result

| Check | Result | Evidence |
|---|---|---|
| DNS A for `ai.globalspokenenglish.com` | Fail | `dig @1.1.1.1` empty |
| Cloudflare API token on host | Absent | No token files; not invented |
| WAN 80 → Caddy | Fail | Public IPv4 port 80 returned a router login HTML page |
| WAN 443 | Fail | Connection refused |
| WAN 8080/8443 | Fail | Timeout |
| LAN IPv4 | `192.168.1.4` | DHCP on `wlo1` |
| `configure-stable` / tunnel-stop | Not run | Would take down the working tunnel |
| Privacy page | Pass | `GET /privacy.html` HTTP 200 on loopback; names Sarvam + Azure |
| Account-deletion page | Pass | `GET /account-deletion.html` HTTP 200; no invented inbox |
| In-app links | Pass | Vitest `App.test.tsx` 16 passed |
| Soft-launch contract | Pass | `scripts/test-soft-launch-environment.sh` |
| Localization | Pass | `packages/localization` 31 tests; revision `ui-*-2026-09-01.4` |
| Android rebuild / sideload | Not run | No stable origin; `adb devices` empty |
| `APP_ENV=production` | Not set | Remains `soft-launch` |

### Known limitations

- `/privacy` and `/account-deletion` redirects need a Caddy reload (next stack restart). `.html` URLs work now.
- Play listing still needs the pages on the stable HTTPS origin after 5.3.
- `make verify` still blocked on this host (PHP 8.5.4 vs 8.4.23; no system `pdo_pgsql`; Node v22 vs 24.18.1).

## Wave 4: backup, observe, rollback — 1 September 2026

### Scope reviewed

- Fresh soft-launch Postgres gzip dump, AES wrap, same-host ciphertext copy.
- Restore into a temporary database only (never drop live).
- Worker/scheduler, health/ready, rollback card, content-free observe timer.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Gzip dump mode 0600 | Pass | `soft-launch-20260901T162027Z.sql.gz` 462785 bytes; SHA-256 `8bfc83d57c6deb9e4cc4a825ec4cb647649469fca96072c56738d417ba3e84ad` |
| Encrypted offsite copy | Pass | `~/ge-offsite-backups/soft-launch-20260901T162027Z.sql.gz.enc` SHA-256 `6437e45efd1533be6f78a79cd4b8d98becbb0e79b7f4a78b6c96223ae5a0a364`; passphrase mode 0600 outside git |
| Decrypt \| gzip -t | Pass | `soft-launch-encrypt-backup.sh` during backup |
| Temp-DB gzip restore | Pass | RTO 2s; `live_database_overwritten=false`; `artifacts/ops/soft-launch-restore-drill-20260901T162031Z.json` |
| REL-01 custom-format drill | Pass | RTO 2s; `artifacts/ops/backup-restore-20260901T162039Z.json` |
| User unit | Pass | `global-english-soft-launch.service` enabled/active; linger yes |
| Worker/scheduler | Pass | pids running; readiness checks added |
| Health | Pass | live `ok`; ready `ready` |
| Readiness | Pass | 24 pass / 0 warn / 0 fail |
| Observe fixtures | Pass | `scripts/test-soft-launch-observe.sh` plus existing env contract |
| Observe timer | Pass | `global-english-soft-launch-observe.timer` enabled; oneshot exit 0; signals empty |
| Previous APK | Pass | SHA-256 `aa94cacc3f4e244a9a258aee4f62e345f92bac6654ff3c22140678282213cca0` |
| USB/second-machine copy | Open | Single NVMe |
| Named SMS/Telegram | Not set | `mail` not installed; optional MAILTO/COMMAND documented |
| `APP_ENV=production` | Not set | Remains `soft-launch` |

### Known limitations

- Encrypted copy is same disk until copied off-box.
- Gitignored env already has `VOICE_PROVIDER_DRIVER=indic`; Wave 6 staff tests, failover kill, and ledger watch are still required.
- Quick Tunnel remains the HTTPS entry (Wave 5).
- `make verify` still blocked on this host (PHP 8.5.4 vs 8.4.23; no system `pdo_pgsql`; Node v22 vs 24.18.1).

## Wave 3: vendor host inventory — 1 September 2026

### Scope reviewed

- Confirm Azure OpenAI / Azure Speech / Sarvam secret files on this host.
- Confirm regional Azure hosts still answer TLS.
- Record kill switches, authorized spend ceilings, and what stays dummy until later waves.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Azure OpenAI secret | Pass | `$HOME/ge-conversation-model/secrets/azure-openai.secret` mode `0600`, outside repo, not a symlink |
| Azure OpenAI host | Pass | `https://ge-conversation-southindia.openai.azure.com/` HTTP 200 over TLS; no credential sent (2026-09-01T16:04:14Z) |
| Chat kill switch | Pass | `CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED` in soft-launch `.env` (gitignored); documented in `.env.example` |
| Azure Speech secret | Pass | `$HOME/ge-speech-benchmark/secrets/azure-speech.secret` mode `0600`, outside repo |
| Azure Speech host | Pass | `https://ge-speech-centralindia.cognitiveservices.azure.com/` HTTP 200 over TLS; no credential sent |
| TTS voices | Pass | `GuidanceTtsPolicy`: `en-IN-NeerjaNeural`, `kn-IN-SapnaNeural` |
| Sarvam secret | Pass | `$HOME/ge-indic-stt/secrets/sarvam.secret` mode `0600`. Indic driver unset |
| `.env` not in git | Pass | `apps/api/.gitignore` ignores `.env` |
| Dummy payments | Pass | `PAYMENT_DRIVER` unset → `DummyPaymentProvider` |
| Google/Facebook production IDs | Not set | Client IDs unset (Wave 5) |
| Authenticated Azure/Sarvam spend in this session | Not run | No API key was sent |
| Sarvam rotate / DPA / monthly cap | Open | APO dashboard |
| Named spend SMS | Not set | Not invented |
| Play Console | Not opened | Wave 10; package `com.globalenglish.app` |

### Known limitations

- APP-0064 speech spend-approval `valid_until` renewed to 2026-12-14 (16 September 2026).
- HTTP 200 on the Azure resource URL is not a token/deployment smoke test.
- `make verify` still blocked on this host (PHP 8.5.4 vs 8.4.23; no system `pdo_pgsql`; Node v22 vs 24.18.1).

## Wave 2A: Indic STT prerequisites — 1 September 2026

### Scope reviewed

- 25-second Speak hold-to-talk and recorded-STT cap; Learn capture stays 120 seconds.
- Cost-ledger CHECK and conversation usage policy for Indic STT and Azure STT failover.
- One Azure Fast Transcription retry after transient Indic HTTP/connection/transport failure.
- Consent notices name Sarvam then Azure failover. Indic driver remains off.

### Review result

| Check | Result | Evidence |
|---|---|---|
| 25s WAV inspector | Pass | Feature `PcmWavInspectorTest` — SQLite artisan test filter, 33 tests / 61 assertions with the Wave 2A PHP suite |
| Indic HTTP fake (translate) | Pass | Feature `IndicSpeechAdapterTest` plus `indic-http-stt-v1:` model version |
| Failover | Pass | Feature `IndicWithAzureFailoverSpeechToTextProviderTest` |
| Usage policy | Pass | Unit `ConversationProviderUsagePolicyTest` |
| Binding wraps Indic with Azure failover | Pass | Unit `ConversationProviderBindingTest::test_testing_binds_indic_recorded_stt_when_authorized` |
| Azure STT preflight allows indic driver | Pass | Feature `SoftLaunchRecordedSpeechConfigurationTest` |
| Hold-to-talk 25s auto-stop; Learn tap uncapped at 25s | Pass | Vitest `RecordingTurn.test.tsx` 20 passed; `VoicePracticePanel.test.tsx` 4 passed |
| Localization | Pass | `pnpm --filter @global-english/localization run test` — 3 files / 31 passed. Catalogue revision `ui-*-2026-09-01.3`, 1163 keys |
| Web lint / typecheck | Pass | eslint on touched files; `tsc -b` |
| PHPStan | Pass | 0 errors on Wave 2A PHP |
| PostgreSQL ledger CHECK | Pass | Docker `php:8.4-cli-bookworm` + `pdo_pgsql`: migrate `000300` on soft-launch DB; `IndicSttCostLedgerConstraintTest` 1 test / 4 assertions |
| Indic live on soft-launch | Not done | `VOICE_PROVIDER_DRIVER` unchanged |
| Closed-beta APK | Not rebuilt | Wave 5 |

### Known limitations

- Recorded STT still writes `voice_provider_runs`, not `ai_cost_ledger`. The CHECK is in place so Wave 6 ledger inserts cannot 500.
- Kannada UI catalogue remains `pending-independent-review`.
- `make verify` still blocked on this host (PHP 8.5.4 vs pinned 8.4.23; no `pdo_pgsql` in system PHP; Node v22 vs 24.18.1).

## D-0267: Premium flagship and courtesy month — 1 September 2026

### Scope reviewed

- DR-0046 Option A / ADR-0036 / APP-0073 / master plan 3.13.
- Premium as the recommended Direct paid SKU; Modules remains ₹350 without Role-play.
- Complimentary one-month Premium for current Direct learner memberships.
- No import of the previous website’s students, passwords or progress.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Premium catalogue + recommended flag | Pass | Feature `CourtesyPremiumGrantTest::test_catalogue_flags_premium_as_recommended` |
| Courtesy grant, replay skip, CSV username filter, dry-run | Pass | Feature `CourtesyPremiumGrantTest` 4 passed with `DirectEntitlementTest` 5 passed — 9 tests / 56 assertions |
| OpenAPI / contracts | Pass | Version **0.39.0**; `pnpm run openapi:check` (lint + policy + generated types match) |
| Web typecheck | Pass | `pnpm --filter @global-english/web run typecheck` |
| Focused web tests | Pass | Vitest 3 files / 14 passed (`SubscriptionPanel`, `OperatorEntitlementAdministration`, `OperatorConsole`) |
| Localization tests | Pass | `pnpm --filter @global-english/localization run test` — 3 files / 31 passed. Catalogue revision `ui-*-2026-09-01.2`, 1162 keys |
| ESLint (touched web files) | Pass | `eslint` on subscription panel, operator entitlement/console — 0 warnings |
| Pint / PHPStan (new PHP) | Pass | Pint `--test` on dirty PHP; PHPStan 0 errors on Entitlements, EntitlementAdministration, courtesy command, operator controller |
| PostgreSQL integration | Not run | Host PHP 8.5.4 has no `pdo_pgsql`. `EntitlementAdministrationApiTest` courtesy cases are written for CI |
| Closed-beta APK | Not rebuilt | No native change in this checkpoint |
| Browser UI | Not run | No local Vite/API process was running in this session |
| Old-website import | Not done | No scrape or copy of `www.globalspokenenglish.com` |

### Known limitations

- Courtesy grants apply only to Direct learner memberships already in this database.
- Email logins from the previous website cannot become usernames here (ADR-0021).
- Dummy payment driver and dummy GSTIN are unchanged. Production and Play remain unauthorized.
- `make verify` still blocked on this host (PHP 8.5.4 vs pinned 8.4.23; no `pdo_pgsql`; Node v22.22.1 vs 24.18.1).
- Kannada UI catalogue remains `pending-independent-review`.

## D-0266: Direct subscriptions, GST and dummy in-app checkout — 1 September 2026

### Scope reviewed

- DR-0045 Option C / ADR-0035 / ADMIN-ENT-01 / APP-0072 / master plan 3.12.
- Direct auto-demo, GST-exclusive dummy checkout, live-AI meter, operator GST/plan/grants.
- Institutional organizations remain unmetered.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Auto-demo, 300s meter, GST checkout, Role-play lock, institutional bypass | Pass | Feature `DirectEntitlementTest` 5 passed, 25 assertions: 401 unauthenticated; Direct auto-demo Level 2 / Role-play / 300s with idempotent charge then deny; ₹350 checkout 6300 GST paise, all levels, Role-play locked; ₹500 allows Role-play; institutional `applies=false` |
| OpenAPI / contracts | Pass | Version **0.38.0**; `pnpm run openapi:check` (lint + policy + generated types match) |
| Web typecheck | Pass | `pnpm --filter @global-english/web run typecheck` |
| Focused web tests | Pass | Vitest 5 files / 34 passed (`App.test.tsx`, `LevelPathPanel.test.tsx`, `SubscriptionPanel.test.tsx`, `OperatorEntitlementAdministration.test.tsx`, `OperatorConsole.test.tsx`) |
| Localization tests | Pass | `pnpm --filter @global-english/localization run test` — 3 files / 31 passed. Catalogue revision `ui-*-2026-09-01.1`, 1158 keys |
| ESLint (touched web files) | Pass | `eslint` on App, SpeakRoom, LevelPath, subscription panel, operator entitlement/console — 0 warnings |
| Pint / PHPStan (new PHP) | Pass | Pint `--test` on entitlement PHP; PHPStan 0 errors on Entitlements, Billing, EntitlementAdministration, related controllers/requests, `ProblemDetailsFactory`, `LevelSessionDeliveryService` |
| Locale CLI | Not run | `pnpm run locale:check` fails on this host: Node v22.22.1 cannot load the TypeScript validation entry (pinned 24.18.1). Vitest validation above is the local stand-in |
| PostgreSQL integration | Not run | Host PHP 8.5.4 has no `pdo_pgsql`. `EntitlementAdministrationApiTest` is written for CI |
| Closed-beta APK | Not rebuilt | No native change in this checkpoint |
| Browser UI | Not run | No local Vite/API process was running in this session |

### Known limitations

- Dummy payment driver completes in-process. Production and `PAYMENT_DRIVER=disabled` fail closed. No live Razorpay/Stripe/Play Billing.
- Dummy GSTIN `29AAAAA0000A1Z5` is invalid for filing until replaced on the operator Subscriptions page.
- Sign-in throttle clear is best-effort against the hashed username limiter key.
- `make verify` still blocked on this host (PHP 8.5.4 vs pinned 8.4.23; no `pdo_pgsql`; Node v22.22.1 vs 24.18.1).
- Production/Play remain unauthorized.
- Kannada UI catalogue remains `pending-independent-review`.

## D-0264: Google and Facebook extra sign-in — 1 September 2026

### Scope reviewed

- DR-0044 Option B / ADR-0034 / master plan 3.11.
- Federated identities, web Sanctum session, Android Passport family.
- Username/password enrollment and `/oauth/token` password grant unchanged.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Social cannot create accounts | Pass | Feature `FederatedSignInTest` 6 passed, 85 assertions: unlinked Google callback redirects home with no new user, session, or `federated_identities` row |
| Same web session / Android family | Pass | Same suite: web federated sign-in creates one `authentication_sessions` row and `/api/v1/me` matches; Android ticket POST issues Bearer family, no session cookie, `/me` works |
| `/oauth/token` password grant rejected | Pass | Same suite asserts `grant_type=password` is 400 `invalid_request` |
| Linking is idempotent | Pass | Password session + `intent=link` for the same Facebook subject stays one row |
| OpenAPI / contracts | Pass | Version **0.37.0**; `pnpm run openapi:check` (lint + policy + generated types match) |
| Web typecheck | Pass | `pnpm --filter @global-english/web run typecheck` |
| Focused web tests | Pass | Vitest 11 files / 72 passed (App, adapters, password panel, federated buttons, native contract, defaultAuthentication, OTP/MFA/account security) |
| ESLint (touched web files) | Pass | `eslint` on adapters, panels, `App.tsx` — 0 warnings |
| Pint / PHPStan (new PHP) | Pass | Pint `--test` on federated PHP; PHPStan 0 errors on Federated namespace, controller, model, `AppServiceProvider`, `MobileTokenFamilyService` |
| Locale keys | Pass (key parity) | All 6 UI catalogues have the same 1134 keys including `auth.social.*`. `pnpm run locale:check` was not run: this host has Node v22.22.1, not pinned 24.18.1 |
| PostgreSQL integration | Not run | Host PHP 8.5.4 has no `pdo_pgsql` |
| Closed-beta APK | Not rebuilt | `GlobalEnglishSystemBrowserPlugin` now allows `/oauth/social/google/authorize` and `/oauth/social/facebook/authorize` in addition to `/oauth/authorize`. `make android-closed-beta` was not run (requires `VITE_MOBILE_API_ORIGIN`). The D-0263 HTTP LAN APK cannot open those Custom Tabs paths |

### Known limitations

- Live Google/Facebook need operator-registered OAuth clients in env (never commit secrets). Missing client configuration fails closed.
- First-time users still need invitation + username/password, then link. Social never creates or invites an account.
- Android federated authorization requires HTTPS matching `BuildConfig.MOBILE_API_ORIGIN`. Staff LAN HTTP `:8080` Custom Tabs remain fail-closed.
- Password confirmation is not satisfied by social sign-in.
- Android link/sign-in ticket exchange issues a new `federated_sign_in` family and does not revoke the previous family.
- Feature `phpunit.xml` now forces `APP_URL=https://localhost` and `SANCTUM_STATEFUL_DOMAINS` including `localhost`, so Feature cookie tests are not poisoned by staff LAN `.env` domains.
- Production/Play remain unauthorized.
- Kannada UI catalogue remains `pending-independent-review`.
- `make verify` still blocked on this host (PHP 8.5.4 vs pinned 8.4.23; no `pdo_pgsql`; Node v22.22.1 vs 24.18.1).

## D-0263: In-app Android username/password login — 1 September 2026

### Scope reviewed

- DR-0043 Option B / ADR-0033 / master plan 3.10.
- First-party mobile password routes and Passport family issuance.
- Android in-app form on `#sign-in`. `/oauth/token` password grant remains rejected.

### Review result

| Check | Result | Evidence |
|---|---|---|
| `/oauth/token` password grant rejected | Pass | SQLite `MobileOAuthPolicyTest` 9 passed, 38 assertions; Feature `MobilePasswordSignInTest` also asserts `grant_type=password` is 400 |
| Web Sanctum still stateful | Pass | Feature `MobilePasswordSignInTest` (7 passed, 74 assertions): web sign-in without a stateful browser is 401 |
| Family create for `password_sign_in` | Pass | Feature happy path issues access + refresh and `/me` works after rotation. Integration `MobileTokenFamilyTest` was not run on this host (no `pdo_pgsql`). |
| No credentials in web storage | Pass | Web vitest 8 files / 53 passed (`App.test`, Android adapter, bind deep-link, password panel, defaultAuthentication, native contract, `androidPkceEnvironment`, capacitor config) |
| OpenAPI / contracts | Pass | Version 0.36.0; `pnpm run openapi:check` (lint + policy + generated types match) |
| Web typecheck | Pass | `pnpm --filter @global-english/web run typecheck` |
| Pint / PHPStan (new PHP) | Pass | Pint `--test` on mobile password files; PHPStan 0 errors on grant, issuer, factory, controller |
| PostgreSQL integration | Not run | Host PHP 8.5.4 has no `pdo_pgsql`; `phpunit.postgres.xml` filtered suite: 13 errors, `could not find driver` |
| Closed-beta APK | Pass | `make android-closed-beta` BUILD SUCCESSFUL, 103 Gradle tasks; `com.globalenglish.app` 1.0 (1), targetSdk 36 |

### Closed-beta APK record (1 September 2026)

- Build-tree: `apps/web/android-app/app/build/outputs/apk/debug/app-debug.apk`
- Protected copy: `~/ge-soft-launch/artifacts/closed-group/global-spoken-english-in-app-login-20260901.apk`
- Size: 14,932,042 bytes
- SHA-256: `aa94cacc3f4e244a9a258aee4f62e345f92bac6654ff3c22140678282213cca0`
- Baked origin: `http://117.254.108.143:8080` (staff LAN; Cloudflare tunnel was stopped at build time)
- Public client id: `019fdd3f-4bba-720b-9e4e-a771ecccdd7f`
- Package embeds `auth/mobile/password/sign-ins`; does not embed `com.globalenglish.demo`

### Known limitations

- The password is typed in the Capacitor WebView (honest ADR-0033 trade-off).
- Old APKs still open Chrome until testers install a build from this checkpoint.
- This APK is pinned to staff LAN HTTP on port 8080 because the Quick Tunnel was stopped. It is not the HTTPS-tunnel closed-group origin. Rebuild against HTTPS when a tunnel hostname is available.
- PostgreSQL integration tests for this checkpoint were not executed on this host.
- `make verify` was not run (host PHP 8.5.4 vs pinned 8.4.23; no `pdo_pgsql`).
- Production/Play remain unauthorized.
- Kannada UI catalogue remains `pending-independent-review`.

## DR-0038 C: Speak hold-to-talk latency — 30 August 2026

### Scope reviewed

- Client hold-to-talk loop: stage timings, §7.4 states, live waveform,
  abortable upload/turn, PCM-first WAV, play-before-events, CSRF cache.
- No new provider, no `ai_cost_ledger` change, no consent-flow change
  (ADR-0007 / ADR-0011).

### Review result

| Check | Result | Evidence |
|---|---|---|
| Stage timings without learner data | Pass | `speakLoopTimings.test.ts`; `console.debug('[speak-loop]', durationsMs)` |
| Distinct listening/processing/preparing/speaking | Pass | `data-speak-loop`, tutor moods, localized loop copy |
| Cancel aborts in-flight fetch | Pass | `VoicePracticePanel` + `RecordingTurn` + `playBase64Audio` abort tests |
| Type-instead and low-confidence retry intact | Pass | Existing VoicePracticePanel and ConversationJourney tests still pass |
| Lint / typecheck / unit tests | Pass | `pnpm run lint`; `pnpm run typecheck`; `pnpm run test` (295 web + 11 design-system + 31 localization) |
| Locale parity | Pass | `pnpm run locale:check`; catalogues `ui-*-2026-08-30.4` |
| PHP / ledger / consent | Unchanged | No PHP files in this checkpoint |

### Client latency on this machine (21 samples)

Synthetic client pipeline only (80ms MediaRecorder stop wait + 40ms events RTT + WAV encode/decode). Azure STT/OpenAI/TTS were **not** in the path.

| | p50 | p95 |
|---|---|---|
| Current (serial backup-recorder wait then events then decode) | 125ms | 127ms |
| Achieved (PCM WAV without backup wait; decode overlaps events) | 43ms | 44ms |

Provider time is unchanged. Full release-to-first-audio with live Azure was not measured on this host.

### Known limitations

- End-to-end Azure p50/p95 is still dominated by STT + model + TTS; this pass does not shrink those stages.
- CSRF cookie is reused after the first Sanctum fetch in a `WebConversationJourneyApi` instance; a 419 would need a later retry path.
- Dev timing overlay is `import.meta.env.DEV` only and is `aria-hidden`.
- Kannada UI catalogue remains `pending-independent-review`.
- Production/Play remain unauthorized.

## DR-0038 B: Progress read-model API — 30 August 2026

### Scope reviewed

- `GET /api/v1/me/progress-dashboard` read model over existing
  `mastery_states`, `meaningful_speaking_days`, `conversation_turns`,
  `learner_attempts`, `delayed_review_occurrences`, and
  `learner_error_patterns`.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Organization-scoped queries | Pass | All reads filter `organization_id` + active enrollment |
| No raw transcript exposure | Pass | Speaking activity uses turn counts and duration metadata only |
| Deterministic marks only | Pass | Objectives sourced from `mastery_states`; no AI fields |
| OpenAPI / contracts | Pass | Version 0.35.0; `pnpm run openapi:check` |
| Feature auth + shape | Pass | `LearnerProgressDashboardTest` (SQLite) |
| Cross-tenant isolation | Pass (CI) | `ProgressDashboardTenancyTest` (pgsql-guarded; not runnable on this host) |
| PHPStan / Pint | Pass | 0 errors |

### Known limitations

- Progress UI landed locally at `44e3339` (`#progress` uses `ProgressDashboard`).
- Integration test requires PostgreSQL (`phpunit.postgres.xml`); skipped locally without `pdo_pgsql`.
- Recurring-error `trend` is a heuristic from pattern timestamps, not a time series.

## DR-0038 A: daily loop — 30 August 2026

### Scope reviewed

- Meaningful-speaking-day streak and deterministic delayed-review scheduling on
  `LearnerHomeService`, Home API fields, flagship authenticated Home UI, and
  ADR-0028 PostgreSQL constraint coverage.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Meaningful-speaking-day predicate and projector | Pass | Unit/feature tests; org-scoped `insertOrIgnore`; no AI writes |
| Streak reader and delayed-review scheduler | Pass | Home API returns `streak` and `due_review`; due review outranks mission in UI tests |
| Cross-tenant isolation | Pass | PostgreSQL integration test (CI `verify`) |
| Review occurrence requires mastery evidence | Pass | PostgreSQL integration test (CI `verify`) |
| Idempotent mastery replay | Pass | Existing PR-006 lifecycle coverage |
| OpenAPI / generated contracts | Pass | Contract validation in `make verify` |
| Localization key parity | Pass | `pnpm run locale:check` in `make verify` |
| Flagship Home UI | Pass | `LearnerJourney.test.tsx`; e2e learner journeys (21/21 Playwright) |
| Full gate | Pass | GitHub Actions run [33317082476](https://github.com/srbhandary1/GESC/actions/runs/33317082476): `verify`, `android`, `operations`, `release-gate` at `9818fe2` |

### Known limitations

- Default review intervals `[1, 3, 7, 14]` in `config/review.php` remain
  provisional pending named academic review (ADR-0028).
- DR-0038 C Speak latency is implemented locally (D-0221); live Azure
  p50/p95 was not measured on this host.
- Kannada UI catalogue remains `pending-independent-review`.
- Production/Play and curriculum publication remain unauthorized.

## Guidance TTS cost ledger + Swathi identity — 29 August 2026

### Scope reviewed

- Live Speak/Learn turns failed after successful STT because guidance TTS cost-ledger rows were rejected by PostgreSQL CHECK.
- Product owner required the on-screen teacher name Swathi instead of Maya.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Cost-ledger CHECK | Pass | `GuidanceTtsCostLedgerConstraintTest` 1 passed; live migrate `000250`; constraint includes `azure-speech-guidance` |
| Friend prompts pin Swathi | Pass | `ConversationOrchestrationTest` 2 passed; live migrate `000260`; tutor body starts “You are Swathi” |
| Azure output contract | Pass | `AzureOpenAiConversationAdapterTest` 6 passed |
| Android events URL | Pass | `WebConversationJourneyApi` vitest; prefixes baked API origin |
| Localization hashes | Pass | `node scripts/validate-locales.mjs`; localization vitest 31 passed; catalogues `ui-*-2026-08-29.6`; Kannada still pending-independent-review |
| Conversation unit tests | Pass | ConversationJourney + LevelPathPanel + events-URL tests: 18 passed |
| Conversation e2e | Pass | Playwright `conversation-journey.spec.ts` 3 passed |
| Closed-beta install | Pass | `make android-closed-beta` BUILD SUCCESSFUL (103 Gradle tasks); `adb install -r` Success on RMX3085; origin `https://demands-cashiers-bryan-quit.trycloudflare.com` |
| Production/Play | Unchanged | Not authorized |

### Known limitations

- Lesson-aware model history (Swathi remembering the exact lesson question in the turn payload) remains a later checkpoint.
- Role-play scene characters (Priya, Arun, receptionist, and others) stay scene names, not Swathi.
- Illustration asset filenames remain `tutor-maya.png` unless a later checkpoint renames files.
- Kannada UI catalogue remains `pending-independent-review`.
- Device confirmation of a completed hold-to-talk turn (HTTP 201, spoken Swathi reply) is still needed after this install.

## Android voice answers + lesson question after audio — 29 August 2026

### Scope reviewed

- Android hold-to-talk still failed after lesson audio because Capacitor dropped Blob/ArrayBuffer recordings.
- After audio, Maya did not ask a lesson question; the learner had to speak first into a generic Friend session.

### Review result

| Check | Result | Evidence |
|---|---|---|
| File-part voice upload | Pass | `formDataWithFileParts` + Android adapter `fetch(url, { body: FormData })` + conversation API tests; vitest 37 passed; `tsc -b` |
| Lesson opening question | Pass | `LevelPathPanel` uses a fallback-turn question (`Can I use your pen?`); `ConversationJourney` opening-line test |
| Closed-beta install | Pass | `make android-closed-beta` BUILD SUCCESSFUL; `adb install -r` Success on RMX3085 against `https://demands-cashiers-bryan-quit.trycloudflare.com` |

### Known limitations

- Azure OpenAI from this Ubuntu host is reachable: dummy-key probe returned 401 (not VNet 403); a 2-token live smoke on `ge-conversation-southindia` returned HTTP 200. The remaining learner-path risk is in-app STT/upload and the lesson question loop, not this host’s IP allowlist.
- Lesson-aware model history (Maya remembering the exact question in the turn payload) remains a later checkpoint; the opening line is shown and spoken client-side from the blueprint.

## Android lesson-audio playback — 29 August 2026

### Scope reviewed

- Learn level-session audio on Android used a raw media element against a private `/api/v1` path and could not attach a Passport bearer.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Authenticated lesson-audio download | Pass | `WebLevelSessionsApi` path allowlist + Android private-media fetch |
| Player uses blob URL when required | Pass | `LevelPathPanel` + `ModelAudioPlayer` + `WebLevelSessionsApi` vitest 11 passed; `tsc -b` |
| Closed-beta install | Pass | `make android-closed-beta` BUILD SUCCESSFUL; `adb install -r` Success on RMX3085 |
| Web cookie path unchanged | Pass | `downloadAudio` is wired only when `privateMediaRequiresFetch` |
| Production/Play | Unchanged | Not authorized |

### Known limitations

- Whole-body blob buffering remains the bounded implementation (same as model audio). HTTP range delivery is still VIDEO-01.
- Device confirmation of audible playback on RMX3085 is still needed after this install.

## Five-destination nav + Indic STT port (D-0216 / D-0217) — 29 August 2026

### Scope reviewed

- DR-0037 B five-item learner nav with 1:1 last and institute contact.
- Indic HTTP recorded-STT adapter behind the existing voice port; Azure remains live.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Nav destinations | Pass | `PRIMARY_DESTINATIONS` Home/Learn/Speak/Progress/tuition; `#practice` aliases Speak; design-system 10 passed |
| 1:1 contact | Pass | `InstituteContact` fail-closed; empty env hides links; OpenAPI `institute_contact`; tuition panel test |
| Indic STT framework | Pass | `IndicSpeechAdapterTest` + binding + InstituteContact PHPUnit 13 passed (`DB_CONNECTION=sqlite`) |
| Localization hashes | Pass | `node scripts/validate-locales.mjs`; localization vitest 31 passed; `ui-*-2026-08-29.5` |
| Web unit | Pass | `apps/web` vitest 263 passed; `tsc -b` |
| Conversation e2e | Pass | Playwright `conversation-journey.spec.ts` 3 passed |
| OpenAPI | Pass | `openapi:check`; contract `0.33.0` |
| PHPStan / Pint | Pass | Indic STT and institute-contact files clean |
| Production/Play | Unchanged | Not authorized; live Indic STT not enabled |

### Known limitations

- Direct institute phone/WhatsApp/email are not committed; set `TUITION_CONTACT_*` when named.
- Indic STT is not live. REST clip-length limits of a future vendor remain a later checkpoint.
- Role-play stays `synthetic_unreviewed`.

## Maya identity + disclosure copy — 29 August 2026

### Scope reviewed

- Pin Friend Talk prompts and Azure output contract to Maya.
- Remove duplicate disclosure heading; rename learner copy from generic AI Friend to Maya.
- Voice consent/upload copy remains present (ADR-0007).

### Review result

| Check | Result | Evidence |
|---|---|---|
| Localization hashes | Pass | `node scripts/validate-locales.mjs`; localization vitest 31 passed; `ui-*-2026-08-29.3`; Kannada still pending-independent-review |
| Conversation unit tests | Pass | ConversationJourney + LevelPathPanel 11 passed |
| Azure adapter contract | Pass | `AzureOpenAiConversationAdapterTest` 6 passed; OUTPUT_CONTRACT includes Maya |
| Conversation orchestration | Pass | `ConversationOrchestrationTest` 2 passed against `global_english_test` after bootstrap isolation |
| `make ai-eval` | Pass | Fake-provider eval 15 passed, 0 failed, suite_hash `5bf9a508d1aeaf3a329212e60cf957cc28404fabcf0d34f4f65ebb6e99223270` |
| Production/Play | Unchanged | Not authorized |

### Known limitations

- Existing Talk-stage replies already stored as Swathi are unchanged; learners must start a new conversation.
- Role-play scene characters (Priya, Arun, receptionist, and others) stay scene names, not Maya.
- Kannada UI catalogue remains `pending-independent-review`.
- `make e2e` / `make verify` were not re-run for the full suite. PostgreSQL integration tests must use `phpunit.postgres.xml` (now with `tests/bootstrap.postgres.php`).
- Operator usernames `trainer` and `reviewer` were recreated on the soft-launch database on 2 September 2026 via `scripts/soft-launch-provision-operator-test-accounts.php`; tunnel sign-in verified for both accounts.

## Learner tutor UI + in-session teacher — 29 August 2026

### Scope reviewed

- Character-led animated learner shell (web + Capacitor Android).
- Listen-then-live-teacher in the same level session.
- Role-play scene cards; bilingual correction card styling.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Web unit/typecheck | Pass (this slice) | `@global-english/web` vitest 259 passed before the extra LevelPathPanel case; LevelPathPanel 4 passed after; `tsc -b` passed |
| Localization hashes | Pass (tests) | `packages/localization` vitest 31 passed; catalogues `ui-*-2026-08-29.1` |
| Design system | Pass | 9 tests; ApplicationFrame optional `className`; speak control class |
| Production/Play | Unchanged | Not authorized |
| Independent Kannada UI review | Unchanged | Pending |

### Known limitations

- Practice complete still requires the deterministic MCQ in the API.
- Role-play remains `synthetic_unreviewed`.
- Maya art is original generated illustration, not a licensed photo.
- `make verify` / `make e2e` were not re-run for this slice.
- Full-screen Android rebuild was not executed here; the WebView loads the same React bundle.

## APP-0068 — Neural listen voice + playable recording — 14 August 2026


### Scope reviewed

- Replace device `speechSynthesis` for Conversation/Role-play model lines with session guidance TTS.
- Capture PCM WAV so local review is audible.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Natural listen voice | Pass (this slice) | `GuidanceSpeechService` + Azure NeerjaNeural WAV; SSML `prosody rate="0.92"`; browser TTS is not the product path |
| Local recording playback | Pass (this slice) | PCM ScriptProcessor capture encoded as 16 kHz WAV for review |
| Tenancy | Pass (tests) | Cross-tenant session speech rejected; unauthenticated speech POST 401 |
| Production STT/TTS gates | Unchanged | Fake/testing remains text-first without audio; live Azure stays soft-launch |

### Known limitations

- Human consented mic→transcript in the browser is still not a completed named-learner smoke.
- Full `make verify` / `make e2e` were not re-run after this slice.
- Neural voice is still Azure neural TTS, not a human recording (ADR-0011).

## APP-0068 — Soft-launch voice-first learner STT — 14 August 2026

### Scope reviewed

- Voice-first Conversation/Role-play with type only on request.
- Bounded Direct soft-launch STT on the APP-0064 Speech resource.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Type is on request | Pass (this slice) | Default `speak` input mode; Type instead; transcript correction typing hidden until requested |
| Soft-launch STT DI | Pass (this slice) | `SoftLaunchRecordedAzureSpeechToTextProvider`; live preflight empty for Direct allowlist |
| Live Azure STT | Pass (provider roundtrip) | Guidance TTS WAV transcribed; non-empty English; `market` present; 9936 bps |
| Production STT | Unchanged, off | Gate rejects `production`; `.env.example` stays disabled |
| Independent privacy firm | Not claimed | APO acting as consolidated reviewer; DR-0028 Phase B smoke not run |

### Known limitations

- Spend shares APP-0064 ₹500. No separate STT spend file.
- Role-play remains `synthetic_unreviewed`.
- Browser-microphone consent/upload by a named learner was not captured; the live check used a guidance-TTS fixture through the product STT adapter.
- Full `make verify` / `make e2e` were not re-run after this slice.

## APP-0067 — Named Kannada review of authored Levels 2–17 — 14 August 2026

### Scope reviewed

- Record `Level 2-17 Kannada approved.` against the APP-0065 authored pack.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Kannada lesson review | Pass (named owner review of agent-authored pack) | Exact statement; APP-0067; Direct revision 4; `docs/evidence/lpl-01/levels-2-17-kannada-review-result.json` |
| Kannada UI catalogue | Not claimed | kn-IN still `pending-independent-review` |
| Publication | Not claimed | Blocker: `curriculum_publication_gate` |
| Role-play | Unchanged | Still `synthetic_unreviewed` |

### Known limitations

- Product owner reviewing agent-authored Kannada, not a commissioned linguist firm.

## Honest listen-then-type copy + Kannada UI preview — 14 August 2026

### Scope reviewed

- Stop serving English chrome when Kannada is selected on invite-only soft-launch.
- Stop advertising microphone / live speaking as available while replies are typed.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Soft-launch UI policy | Pass (this slice) | `VITE_UI_TRANSLATION_PREVIEW=true` dist stamp; D-0030 preview notice; production remains `approved-only` |
| Kannada catalogue independently approved | Not claimed | kn-IN still `pending-independent-review` |
| Conversation/Role-play honesty | Pass (this slice) | Catalogue copy + voice panel `unavailable` status; STT gate unchanged |
| Learner STT / `VOICE_UPLOAD_ENABLED` | Later enabled for invite-only Direct under APP-0068 | D-0178 / DR-0028; superseded in-scope by D-0212 |

### Known limitations

- Draft Kannada chrome is a review preview, not an independent translation approval.
- Guidance TTS can still speak; learner microphone transcription is not on.
- Role-play remains `synthetic_unreviewed`.
- Full `make verify` / `make e2e` were not re-run after this honesty slice.

## APP-0066 — Named English academic review of authored Levels 2–17 — 14 August 2026

### Scope reviewed

- Record the Accountable Product Owner statement `Levels 2–17 English approved`
  against the APP-0065 authored pack.

### Review result

| Check | Result | Evidence |
|---|---|---|
| English academic review | Pass (named owner review of agent-authored pack) | Exact statement; APP-0066; blueprint `english_review_reference` |
| Kannada independent review | Not claimed | `kannada_guidance.independent_review` remains false |
| Publication | Not claimed | Blockers: `independent_kannada_review`, `curriculum_publication_gate` |
| Role-play | Unchanged | Still `synthetic_unreviewed` |

### Known limitations

- This is the product owner reviewing agent-authored English, not a separately commissioned linguist firm.
- Kannada carry-forward decision rows exist only so four-part audio can attach to the new hash.
- Live Direct drafts are revision 3; evidence `docs/evidence/lpl-01/levels-2-17-english-review-result.json`.

## APP-0065 — Authored levels, Role-play scripts, 1:1 request — 14 August 2026

### Scope reviewed

- Replace APP-0063 provisional Levels 2–17 lesson text with authored English + Kannada copy.
- Give Role-play Observe/Rehearse/Perform authored scripts without changing `synthetic_unreviewed`.
- Replace “hire a tutor” marketing with an org-scoped 1:1 tuition request.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Levels 2–17 not templates | Pass (this slice, in code and live Direct drafts) | `AuthoredFoundationLessons`; validator covers all 16 concepts; live replace evidence `docs/evidence/lpl-01/levels-2-17-authored-replace-result.json` (16 levels, revision 2) |
| Independent bilingual review | English named under APP-0066; Kannada not claimed | `kannada_guidance.independent_review` is false; publication still gated |
| Role-play scripts | Pass (this slice) | `RoleplayPedagogy` + `roleplayCatalog.ts`; UI still `data-review-status="synthetic_unreviewed"` |
| 1:1 is a request | Pass (this slice) | `/me/tuition-requests` + operator queue; marketing copy updated |
| Payments / calendar / hire | Out of scope | Explicitly excluded under DR-0036 |

### Known limitations

- Independent named review of the authored pack is still required before publication.
- Named English review of Levels 2–17 is recorded under APP-0066; Kannada remains outstanding.
- Role-play remains synthetic until specialist curriculum review.
- Help, certificates, staff invite UI, privacy operator UI, and Content Studio pagination remain later checkpoints.
- Full `make verify` / `make e2e` were not re-run after this slice. Host `php` in PATH has no `pdo_pgsql`; Postgres PHPUnit used Docker image `ge-php84-pgsql:local`.

## Release-gate repair — 14 August 2026

### Scope reviewed

- TypeScript, ESLint, and unit failures that kept the web gate red.
- Ordered four-session path (UI + API), bound AI Tutor handoff, Practice tab semantics, sign-in pattern/IDs/focus, missing operator confirm hint, Role-play learner turn, Home/Progress placeholders, Kannada fallback copy, learner guide OTP docs.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Session order | Pass (this slice) | `LevelPathPanel` locks later tabs; `LevelSessionDeliveryService` rejects `session_locked` |
| Bound tutor handoff | Pass (this slice) | `ConversationJourney` `initialSession`; demo/API return a full session |
| Practice tablist | Pass (this slice) | `role="tab"` / `aria-selected` / `tabpanel` |
| Sign-in username pattern | Pass (this slice) | JS `/^[A-Za-z][A-Za-z0-9._-]{3,63}$/` plus escaped HTML pattern and `minLength={4}` |
| Operator confirm hint | Pass (this slice) | `operator.password.confirm_hint` in all six catalogues |
| Kannada approved | Not claimed | Still `pending-independent-review`; production notice updated |

### Known limitations

- Help, downloads, recording management, privacy/export, account deletion, certificate, trainer, and booking experiences remain later checkpoints.
- Staff invite-to-first-login, privacy export/erasure UI, organization switching UI, Content Studio pagination, retry idempotency keys, and beginner-admin ULID/hash forms remain later operator work.
- Role-play remains `synthetic_unreviewed` (scripts are now authored).
- 1:1 tutor hiring/marketplace remains out of scope; waitlist requests are implemented under APP-0065.
- Levels 2–17 lesson text is authored under APP-0065; independent bilingual review remains outstanding.
- Full `make e2e` / all-controls demo were not re-run after this slice. Bootstrap guest/sign-in expectations were updated; remaining E2E files may still need a green pass on the host.
- Level-session API lock tests were added in `LevelSessionDeliveryTest` but were not executed here: host PHP has no `pdo_pgsql` driver.

## Learner marketing four-session path + pedagogy — 14 August 2026

### Scope reviewed

- Four-session path and pedagogy vs chat-only tutoring on the marketing home.
- Marketing difference tiles (hybrid, 1:1 tuition, combined path).
- Header Sign in / dedicated `#sign-in` page; logos split web vs Android.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Home has no login form | Pass | `MarketingLanding` has no `signInSlot`; App.test |
| Path and pedagogy on home | Pass | `marketing.path.*` / `marketing.pedagogy.*`; App.test |
| Level session HTTP idempotency | Pass | `createClientId` in `LevelPathPanel`; English-first lesson copy |
| Licensed photos only | Pass | Unsplash files + `PHOTO_ATTRIBUTION.md` |
| Web vs Android logo | Pass | Capacitor platform selects `logo-web` / `logo-android` |

### Known limitations

- 1:1 tutor hiring/marketplace is not implemented; waitlist requests are under APP-0065.
- Conversation “not saved unless you choose” is policy copy on the home page; this slice does not add a new memory opt-in control.
- Kannada catalogue remains pending independent language review.

## Learner UI locale + chrome polish — 14 August 2026

### Scope reviewed

- Interface locale picker restricted to kn-IN / en-IN.
- Marketing, practice switch, and level path localization.
- Sign-in embedded in marketing join; typography scale reduced.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Only kn + en in learner picker | Pass | `INTERFACE_LOCALES`; App.test |
| Marketing no longer hardcoded English | Pass | `MarketingLanding.tsx` + catalogue keys |
| Bottom school-project sign-in removed on home | Pass | SessionSummary only via marketing `signInSlot` |
| Catalogue parity maintained | Pass | 978 keys across all `*-IN.json`; governance hashes updated |

### Known limitations

- kn-IN remains `pending-independent-review` (preview policy shows Kannada; approved-only falls back to en-IN).
- Concept labels / lesson content strings from the API may still be English.
- Full visual QA on every authenticated surface not completed in this pass.

## APP-0064 — Soft-launch guidance TTS + Role-play catalog

### Scope reviewed

- Soft-launch Azure Speech TTS DI for GuidanceTtsPolicy (`model_en`, `guidance_l1`)
  under APP-0064 ₹500 evidence, separate from PR-009 speech-benchmark.
- Text-first fallback on TTS failure; optional WAV base64 on turn speech payload.
- Synthetic Role-play catalog expansion (clinic + 11 common scenarios) + UI picker.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Live multi-locale TTS gated by APP + kill-switch | Pass | `SoftLaunchGuidanceTtsConfiguration`; `.env.example` default false |
| PR-009 benchmark gate unchanged | Pass | Benchmark `AzureSpeechConfiguration` / fixture voices untouched |
| Text remains primary | Pass | Provider falls back to `TextFirstTextToSpeechProvider` |
| Role-play catalog ≥10 additional scenarios | Pass | `conversation.roleplay_catalog` size 12; UI select |
| Production/Play/STT not enabled | Pass | Soft-launch only binding; STT remains confirmed-text |

### Known limitations

- Guidance TTS spend evidence and secrets live outside the repository.
- Role-play scenarios remain `synthetic_unreviewed` / `development_only`.
- Ordinary learner STT and production promote remain unauthorized.

## PR-000 — Governance baseline

### Scope reviewed

- canonical-plan placement and authority;
- agent operating instructions;
- repository onboarding documentation;
- ADR, issue and pull-request governance;
- checkpoint status and decision traceability;
- formatting and repository hygiene conventions.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Canonical plan is at repository root | Pass | `ENTERPRISE_PRODUCT_MASTER_PLAN.md` |
| Agent instructions are concise and consistent with the plan | Pass | `AGENTS.md` |
| Source-of-truth hierarchy is explicit | Pass | `README.md` and `AGENTS.md` |
| Current checkpoint is recorded | Pass | `docs/STATUS.md` |
| Material decisions have a durable ledger | Pass | `docs/DECISIONS.md` |
| ADR structure covers required impacts | Pass | `docs/adr/0000-template.md` |
| Issue and pull-request templates enforce bounded scope | Pass | `.github/ISSUE_TEMPLATE/` and `.github/PULL_REQUEST_TEMPLATE.md` |
| Repository-local Markdown links and anchors resolve | Pass | Local link and anchor validation |
| Markdown headings and YAML front matter are structurally valid | Pass | Local structure and YAML parsing |
| No product code was introduced | Pass | Repository file inspection |
| Root verification commands are available | Not applicable | Scheduled for PR-001 |

### Security, privacy and tenancy review

- No secrets, real learner data or licensed media were added.
- Governance templates require explicit security, privacy, tenancy and
  authoritative-record impact analysis.
- Deferred decisions are recorded for authentication, resource ownership,
  offline data and recording retention.

### Known limitations

- The repository is initialized on `pr-000-governance-baseline`, but it has no
  commit, remote configuration or CI execution yet.
- Markdown was validated locally for expected structure and repository-local
  file targets; external references were not live-verified.
- Application verification commands do not exist before PR-001.

### Exit decision

PR-000 was accepted on 29 July 2026 through the product-owner instruction to
proceed with project implementation. PR-001 is authorized to begin.

---

## PR-001 — Monorepo and local platform bootstrap

### Scope reviewed

- Laravel 13 and React 19.2 framework shells;
- runtime and dependency reproducibility;
- local database, cache, object-storage, mail and OTP services;
- stable root commands and CI parity;
- backend, frontend and browser smoke tests;
- checkpoint boundaries, secret hygiene and local-network exposure.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Laravel API shell exists without business or identity models | Pass | `apps/api/`, route inspection and framework health test |
| React TypeScript/Vite shell exists without learner features | Pass | `apps/web/` and render tests |
| Runtime versions and dependencies are reproducibly declared | Pass | version files, manifests and lockfiles |
| Local service images are immutable and host ports are loopback-only | Pass, static | `compose.yaml` inspection |
| Mail and OTP development paths are non-production fakes | Pass, static | Mailpit and WireMock configuration |
| Root bootstrap, verification and build commands are executable | Pass | local command execution |
| Production dependencies have no reported security advisories | Pass | Composer and pnpm audits |
| Locked application dependency licences were inventoried | Pass with release follow-up | Composer and pnpm licence reports; final notices tracked by DR-0009 |
| PHP formatting and level-8 static analysis pass | Pass | Pint and Larastan/PHPStan |
| Backend and frontend deterministic tests pass | Pass | PHPUnit and Vitest, one test each |
| React shell browser smoke test passes | Pass | Playwright Chromium, one test |
| Production API optimization and web bundling pass | Pass | `make build` |
| CI uses the declared toolchain and root commands | Pass, static | `.github/workflows/ci.yml` |
| Compose services start and become healthy | Not run | Docker/Compose unavailable on the host |
| No secrets, generated environment files or build output are tracked | Pass | ignore and Git hygiene checks |
| No later-checkpoint feature was introduced | Pass | repository inspection |

### Commands and results

| Command | Result |
|---|---|
| `make bootstrap` | Pass using Node.js 24.18.1, pnpm 11.18.0, Composer 2.10.2 and local PHP 8.4.17; emitted the expected PHP 8.4.23 patch warning |
| `make verify` | Pass: Composer validation/audit, Pint, PHPStan, PHPUnit, frozen pnpm install, pnpm production audit, ESLint, TypeScript and Vitest |
| `make build` | Pass: Laravel production optimization and Vite production build |
| `make e2e` | Pass outside the restricted macOS sandbox: one Chromium test |
| Local Markdown validator | Pass: 12 files, balanced fences, heading progression, final newlines and all repository-local links/anchors |
| YAML/JSON syntax checks | Pass: Compose, issue-template configuration/front matter and WireMock mapping |
| Compose static policy check | Pass: expected service set, digest-pinned images and loopback host bindings |
| `git diff --check` and ignore checks | Pass: no whitespace errors; generated environments, dependencies, test/build output and SQLite state are ignored |
| Composer/pnpm licence inventory | Pass: locked application dependencies reported licences; final distribution and notice policy remains DR-0009 |

### Security, privacy and tenancy review

- Compose host ports bind to `127.0.0.1`, and containers use
  `no-new-privileges`.
- Root and API `.env.example` values are explicitly local-development
  credentials; generated `.env` files and application keys remain ignored.
- Mailpit and WireMock prevent local mail/OTP work from reaching real
  recipients.
- No learner data, licensed media, authentication credential, provider secret
  or production configuration was added.
- No tenant-owned resource exists in PR-001. Organization scoping,
  authorization and cross-tenant tests remain mandatory from PR-003.

### Known limitations

- Docker and Docker Compose are unavailable on the current host. Compose YAML
  can be checked statically, but service startup, health and persistence have
  not been demonstrated.
- The local PHP executable is 8.4.17, while repository and CI declarations pin
  8.4.23. The PHP checks passed on the compatible 8.4 series, but an exact-patch
  clean-environment run remains outstanding.
- CI configuration was inspected locally but has not executed on a GitHub
  runner.
- Final source-distribution terms and third-party notice production are deferred
  to DR-0009.
- The first sandboxed Playwright attempt was blocked by macOS Mach-port
  permissions. The same test passed outside that sandbox.
- PR-002 must add dependency-aware readiness; PR-001 provides only Laravel's
  framework liveness shell.

### Exit decision

PR-001 implementation is complete and ready for review. Acceptance remains
pending the unresolved environment checks above or an explicit reviewer
decision accepting them. PR-002 is not yet authorized.

PR-001 was subsequently accepted on 29 July 2026 when the product owner
instructed Codex to continue after these limitations were presented. The
instruction authorized PR-002 without representing the unavailable checks as
having passed.

---

## PR-002 — Contracts, health and observability

### Scope reviewed

- OpenAPI 3.1.1 source and generated TypeScript contract package;
- contract linting, policy enforcement and deterministic drift detection;
- correlation IDs, problem details and privacy-safe structured logging;
- process liveness and PostgreSQL/Redis dependency readiness;
- API exception and React render error boundaries;
- root-command and CI integration;
- checkpoint boundaries, secret hygiene and future tenancy constraints.

### Review result

| Check | Result | Evidence |
|---|---|---|
| OpenAPI 3.1.1 contract is structurally valid | Pass | Redocly strict lint of `docs/api/openapi.yaml` |
| Client, types and OpenAPI 3.1 JSON schemas are generated and exported | Pass | `packages/contracts/`, contract typecheck and web consumer test |
| Generated output is deterministic and drift is rejected | Pass | Temporary generated-file sentinel caused the expected non-zero drift result; restored output passed |
| The health-only missing-4xx exception cannot silently apply to feature operations | Pass | `scripts/validate-openapi-policy.mjs` in `make openapi` |
| Liveness does not resolve dependency probes | Pass | Backend feature test |
| Readiness checks all configured required dependencies | Pass | PostgreSQL and Redis probe wiring |
| An unavailable required dependency returns a safe `503` | Pass | Injected throwing probe test; diagnostic secret absent from body and logs |
| API errors use the stable problem-details contract in debug and non-JSON requests | Pass | 404, 405, 422 and 500 feature tests |
| Correlation UUIDs propagate from validated input or are generated | Pass | Response-header/body and sequential-request tests |
| Request logging uses fixed events and an allowlist | Pass | Log-spy tests and a live liveness request emitted one JSON event with correlation context |
| Operational responses include a no-store directive | Pass | Contract plus liveness, readiness and problem tests |
| React has an accessible generic render error boundary | Pass | Focus, alert, reload, root-callback, non-disclosure and normal-render tests |
| Web uses the generated contract package | Pass | Typed liveness wrapper and mocked-fetch tests |
| CI executes contract validation and drift checks | Pass, static | `make verify` integration and CI workflow |
| No PR-003 or later feature was introduced | Pass | Route, model, migration and repository inspection |

### Commands and results

| Command | Result |
|---|---|
| `make openapi` | Pass: strict OpenAPI lint, health-only 4xx policy and generated-client reproducibility |
| Deliberate generated-client drift run | Expected failure: the temporary sentinel was detected; removal restored a passing check |
| `make verify` with isolated Composer 2.10.2 | Pass on local PHP 8.4.17 with the expected 8.4.23 warning: Composer validation/audit, Pint, PHPStan, 12 backend tests/88 assertions, frozen pnpm install, full pnpm audit, OpenAPI checks, ESLint, contracts/web TypeScript and 8 frontend tests |
| `make build` | Pass: Laravel configuration/events/routes/views cached and fully cleared; Vite built 20 modules |
| `make e2e` | Pass after installing the pinned Playwright Chromium: one Chromium shell/error-boundary smoke test |
| Focused unavailable-dependency backend test | Pass: required-probe exception produced a generic `503` without the injected secret |
| Live liveness request on the Laravel development server | Pass: response/header correlation matched and the JSON event omitted the secret query value |
| Live readiness request with PostgreSQL forced to an unavailable loopback port | Pass: generic `503`, `Retry-After`, no-store and matching correlation; structured warnings omitted connection diagnostics |
| Full pnpm audit at high severity | Pass: no known vulnerabilities after the `js-yaml` 4.3.0 override |
| Local Markdown, YAML/JSON and repository hygiene validation | Pass; repository-local links, contract YAML, package JSON, whitespace and generated-artifact ignore rules checked |

The first repository-wide verification attempt stopped before code checks
because the host default was Composer 2.9.3. The exact Composer 2.10.2 PHAR was
downloaded from the official distribution, its published SHA-256 checksum
passed, and the same verification command then completed. The first final
Playwright attempt similarly stopped because the pinned browser was absent; the
pinned Chromium build was installed and the rerun passed.

### Security, privacy and tenancy review

- Incoming correlation values are accepted only when they are UUIDs; otherwise
  the server creates a UUID. Correlation state is placed in Laravel context for
  structured logs.
- API response and log tests use secret-like learner/transcript and database
  diagnostic fixtures and prove those values are not returned or logged.
- Public readiness responses do not name failed dependencies. Internal logs
  contain only the stable dependency label, failure type and exception class.
- Request events omit raw paths, query values, bodies, headers, IP addresses and
  user-agent strings.
- React root and boundary handlers report only allowlisted failure categories
  and render no raw error text or stack.
- No learner data, credentials, provider secret, authentication flow,
  tenant-owned resource, migration or cross-tenant behavior was added.

### Configuration and dependency review

- API logging defaults to JSON on stderr with stack traces disabled.
- Redis readiness uses a dedicated connection with 0.5-second connect/read
  limits and no retries.
- Production-build verification clears configuration first, then clears all
  optimization artefacts with the non-network `array` cache store.
- Redocly 2.41.2 and Hey API 0.99.0 are exact development pins. Their selected
  plugins generate a self-contained client, TypeScript types and JSON schemas.
- `js-yaml` is pinned and overridden to 4.3.0 to avoid the high-severity
  quadratic-processing advisory in the generator's transitive parser.
- The existing API Pint configuration is now invoked from `apps/api`; applying
  its existing rule mechanically added `declare(strict_types=1)` to the
  remaining pre-existing API PHP files.
- No migration was added.

### Known limitations

- Docker/Compose is unavailable, so healthy PostgreSQL/Redis readiness and
  container failure latency were not measured. A forced loopback connection
  refusal and injectable failures prove `503` response semantics; PostgreSQL
  still relies on the selected driver/platform connection timeout.
- The local PHP executable is 8.4.17, not the declared 8.4.23 patch. CI declares
  8.4.23, but the remote workflow has not run.
- Object storage, mail/OTP and external providers are not readiness dependencies
  before serving paths rely on them.
- The React error boundary cannot catch event-handler, arbitrary asynchronous,
  boundary-self or pre-mount static-import failures.
- Metrics, distributed tracing and an external log backend remain deployment
  choices for later operational checkpoints.
- Final distribution terms and third-party notices remain DR-0009.

### Exit decision

PR-002 was accepted on 30 July 2026 under
[APP-0001](APPROVALS.md#app-0001). The same bounded evidence accepted
ADR-0001, ADR-0002 and ADR-0003 and authorized PR-003 without representing the
recorded unavailable environment checks as passed.

---

## PR-003 — Tenancy and identity foundation

### Scope reviewed

- platform-global identity and organization records;
- organization-owned memberships, roles, invitations, cohorts and
  enrollments;
- Direct and institution invite-plus-OTP onboarding;
- organization-context derivation, tenant scopes, policies and database
  constraints;
- same-origin web sessions, CSRF, OTP policy and session revocation;
- Android public-client PKCE, installation-bound refresh rotation and family
  revocation;
- platform-operation authorization, audit/outbox evidence and idempotency;
- OpenAPI/generated clients, frontend adapters and checkpoint boundaries.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Accepted PR-003 decisions and authorization have durable human evidence | Pass | APP-0001 and ADR-0001 through ADR-0003 |
| Direct phone-only onboarding assigns the ordinary Direct tenant transactionally | Pass | Direct provisioning PostgreSQL tests |
| Invite-plus-OTP creates institution membership without unintended Direct membership | Pass | Web and mobile PostgreSQL onboarding journeys plus rollback/non-enumeration tests |
| Every introduced organization-owned model fails closed without context | Pass | Organization isolation matrix and web/mobile request-context middleware tests |
| Cross-tenant read, list, create, update, delete, scoped binding, attachment and mass assignment are denied | Pass | Policy/model matrix and PostgreSQL composite constraints |
| Non-applicable route-handler, job and object-storage categories are explicit | Pass | `docs/PR-003_TENANCY_VERIFICATION_SCOPE.md` |
| Platform operations require signed capability, safe reason, target and append-only audit evidence | Pass | Platform-operation PostgreSQL tests |
| Web sessions enforce CSRF, inactivity, absolute lifetime, logout and arbitrary/all-session revocation | Pass | Session feature/PostgreSQL tests and live same-origin login/logout smoke |
| OTPs are hashed, short-lived, single-use, superseding, attempt-limited, abuse-controlled and safely logged | Pass | OTP policy and integration tests |
| Android OAuth uses system-browser S256 PKCE without a public-client secret | Pass | Passport and Android-adapter tests |
| Refresh is single-use, installation-bound and concurrently safe with family revocation on reuse | Pass | Real-PostgreSQL forked race plus mismatch and expiry-boundary tests |
| No browser or ordinary-preferences credential persistence exists | Pass | Adapter boundary and storage-spy tests |
| Public APIs and generated clients match | Pass | Strict OpenAPI lint, explicit RFC 9457/OAuth media policy and generated-client drift check |
| No later-checkpoint learner, content, AI, speech, offline or commercial feature was introduced | Pass | Route, schema and repository inspection |

### Commands and results

| Command | Result |
|---|---|
| `make verify` with the isolated Composer 2.10.2 and pinned Node/pnpm toolchain | Pass on local PHP 8.4.17 with the expected 8.4.23 patch warning: strict Composer validation with the approved exact-pin advisory excluded, Composer/pnpm audits, Pint, PHPStan level 8, 36 SQLite tests/254 assertions, 93 PostgreSQL tests/1,217 assertions, frozen pnpm install, OpenAPI drift/policy checks, ESLint, TypeScript and 16 frontend tests |
| `make openapi` | Pass: strict Redocly validation, explicit RFC 9457 domain/OAuth JSON policy and reproducible generated TypeScript contracts |
| `make e2e` | Pass: one Chromium secure identity-shell journey |
| `make build` | Pass: Laravel production optimization/cache cleanup and Vite production build of 38 modules |
| Targeted PostgreSQL acceptance audit | Pass: 25 invite/session/Passport/concurrent/token-family tests with 761 assertions plus 3 revocation-failure tests with 15 assertions |
| Forked concurrent refresh test | Pass: one simultaneous request returned `200`, one returned OAuth `invalid_grant`, the token family was revoked and one reuse audit was appended |
| Mobile binding and boundary tests | Pass: wrong installation and exact 30-day inactivity, 90-day absolute and refresh-record expiry boundaries revoke the family |
| Live same-origin Vite/API/PostgreSQL smoke | Pass: CSRF `204`, OTP request `202`, verification `200`, authenticated `/me` `200`, logout `204`, revoked `/me` `401` with problem details |
| `php artisan route:list` for Sanctum, OAuth and `/api/v1` | Pass: cookie-CSRF, PKCE/token and first-party authentication middleware are attached to the expected routes |
| `php artisan migrate:fresh --seed --force` against the isolated PostgreSQL test database | Pass: six migrations and the idempotent Direct foundation seeder completed |
| `make check-local-auth-env` | Pass: local environment uses encrypted sessions and a dedicated identity digest key |
| PostgreSQL initializer idempotency check | Pass: `001-create-test-database.sql` completed twice with `ON_ERROR_STOP` |
| Compose/CI static policy check | Pass: five services are digest-pinned and loopback-only; PostgreSQL initializer mount and exact CI runtime/extensions are present |
| Local Markdown, YAML, link, anchor and Git hygiene checks | Pass: balanced structure, valid local targets, canonical inputs unchanged and no whitespace errors |

### Security, privacy and tenancy review

- Global English Direct is enforced as an ordinary organization tenant.
- Membership-derived context is fail-closed. Caller fields and arbitrary
  organization headers cannot establish or replace authority.
- The `organization.context` middleware resolves an active selected membership
  or exactly one active membership for both web and mobile principals, scopes
  downstream tenant models and clears context after every response.
- Composite PostgreSQL foreign keys bind tenant relationships, invite
  acceptance, audit actors and selected mobile memberships to the same
  organization and user.
- Platform-global operations require a project-issued short-lived capability,
  an allowlisted non-sensitive reason and append-only audit evidence.
- Web uses encrypted Secure, HttpOnly, SameSite cookies and CSRF protection.
  Android access tokens stay in memory; persistent credential operations exist
  only on the reviewed-native-secure-store interface.
- Identity, OTP, session, installation and refresh values are purpose-digested
  or encrypted. Raw phone numbers, codes, tokens and provider payloads are not
  stored in audit/outbox records or application logs.
- The local fixed OTP is available only in `APP_ENV=local`; production uses no
  fixed code and the fake delivery adapter fails closed outside local/testing.
- Both OAuth endpoints accept only active first-party public mobile clients
  with the expected provider and grants; HTTP Basic, PHP-auth and body client
  secrets are rejected before Passport.
- Requests without a web session resolve the Passport guard only when a bearer
  token is present, so a local install without OAuth keys fails closed with
  `401` rather than exposing a server error.
- Independent security, privacy and tenancy specialist review remains required
  before production.

### Migrations and configuration reviewed

- Six ordered migrations create identity/tenancy, tenant relationships,
  audit/outbox, web authentication, Passport and mobile token-family records.
- PostgreSQL check, uniqueness, composite foreign-key and append-only audit
  constraints supplement application scopes and policies.
- `AUTH_IDENTITY_DIGEST_KEY` is independent from `APP_KEY`, validated
  fail-closed and documented as requiring a versioned migration for rotation.
- Session/token/OTP lifetimes match ADR-0002 and remain centralized
  configuration.
- Compose initializes the isolated PostgreSQL integration-test role/database
  on a fresh volume; CI runs the PostgreSQL suite.

### Known limitations

- Docker/Compose and a fresh PostgreSQL 18.4 container are unavailable on this
  host; the local PostgreSQL verification service is version 14.
- Local PHP is 8.4.17 rather than the declared 8.4.23 patch, and the GitHub
  Actions workflow has not run remotely.
- Production OTP delivery, privileged-MFA verification, Android native secure
  storage and operational alert integrations require later reviewed
  implementations.
- Physical session-store cleanup is best effort after authoritative database
  revocation commits. Middleware remains fail-closed, but production alerting
  for cleanup failures is not integrated in PR-003.
- Recovery, phone change/reassignment, admission-ID setup and account closure
  remain gated future workflows under ADR-0002.
- No tenant-owned queued handler or object-storage record exists yet, so those
  isolation categories become executable at the checkpoint that introduces
  them.
- Independent specialist review remains outstanding.

### Exit decision

PR-003 implementation and its local verification gate were complete and ready
for Accountable Product Owner review. It was subsequently accepted on 30 July
2026 under [APP-0002](APPROVALS.md#app-0002). The accepted limitations remain
limitations rather than passing results. APP-0002 authorizes only PR-004.

---

## PR-004 — Localization and accessible design system

### Scope reviewed

- governed `kn-IN` and `en-IN` UI catalogues and deterministic fallback;
- Unicode normalization, source linkage and translation-review evidence;
- mixed-language semantics, Kannada font delivery and accessible tokens;
- responsive Home/Learn/Practice/Progress shell and focus behavior;
- inert audio/recording presentation contracts;
- production-versus-preview language policy and safe fallback observability;
- unit, locale, keyboard, axe, reflow, target-size and font-resource tests;
- preservation of PR-003 API, authentication and tenancy boundaries.

### Review result

| Check | Result | Evidence |
|---|---|---|
| PR-003 acceptance and PR-004 authorization are durable | Pass | APP-0002 and D-0029 |
| UI catalogues have exact locale/key parity, deterministic hashes and current source linkage | Pass | Locale validator and governance revisions `ui-en-IN-2026-07-30.2` / `ui-kn-IN-2026-07-30.2` |
| Pending Kannada is not represented as approved | Pass | `pending-independent-review`, attributed draft, null reviewer/approval and `independentReview=false` |
| Ordinary production builds reject pending Kannada and visibly fall back to English | Pass | Policy unit tests and local strict-build browser inspection |
| Development/review preview is explicit and visibly labelled | Pass | `VITE_UI_TRANSLATION_PREVIEW` policy tests and shell preview notice |
| Fallback observability excludes text, parameters and learner data | Pass | Resolver and web reporting tests |
| Blank messages, malformed placeholders, stale hashes and dishonest review claims fail validation | Pass | Localization validator tests |
| Kannada text uses NFC without removing ZWJ/ZWNJ | Pass | Normalization tests |
| Mixed-language and brand text expose explicit language metadata | Pass | Design-system and shell tests |
| Shell landmarks, navigation, skip focus and route-title focus are keyboard operable | Pass | Component tests and Chromium journey |
| Skip focus does not destroy the active route fragment | Pass | Component and Chromium reload tests from `#learn` |
| Both locale variants have no automated axe violations | Pass | Chromium axe scans for Kannada preview and English |
| Shell reflows at 320/360/412/768/1280 CSS pixels | Pass | Chromium no-horizontal-overflow matrix |
| Enlarged text reflows at 200% and 400% stress proxies | Pass | Chromium root-text-size matrix; not claimed as actual browser zoom |
| Primary controls meet the project 44 CSS-pixel target | Pass | Chromium bounding-box assertions |
| Kannada-specific bundled WOFF2 face loads for Kannada sample text | Pass | FontFaceSet and resource-entry assertions |
| Reduced-motion behavior is honored | Pass | Chromium media-emulation assertion |
| Locale selection is ephemeral and no browser credential store is used | Pass | unit storage spies and Chromium localStorage/IndexedDB assertions |
| Audio/recording contracts perform no media, provider, network or persistence work | Pass | component tests and forbidden-API inspection |
| OpenAPI and generated clients remain unchanged | Pass | `make openapi` |
| No PR-005 or later product behavior was introduced | Pass | route, schema and repository inspection |

The first enlarged-text browser pass exposed horizontal overflow at the 400%
stress proxy. Shrinkable grid/flex tracks and explicit wrapping corrected the
defect; the complete matrix passed after the fix. An adversarial integration
review then identified and closed strict-production preview, fallback
visibility, static metadata, skip-route, validator, font-test, lint-coverage
and document-language gaps before the final gate.

### Commands and results

| Command | Result |
|---|---|
| `make verify` with pinned Node.js 24.18.1, pnpm 11.18.0 and Composer 2.10.2 | Pass on local PHP 8.4.17 with the expected 8.4.23 patch warning: Composer validation/audit, pnpm frozen install/audit, Pint, PHPStan, 36 SQLite tests/254 assertions, 93 PostgreSQL tests/1,217 assertions, locale validation, OpenAPI drift/policy, centralized ESLint, recursive TypeScript and 56 frontend/package unit tests |
| `pnpm run locale:check` | Pass for two catalogues with one expected `pending-independent-review` governance warning |
| `make openapi` | Pass: strict Redocly validation, repository API policy and reproducible generated-client drift check |
| `make e2e` | Pass: five Chromium scenarios covering both-language axe scans, keyboard/focus/route behavior, ephemeral locale and storage boundaries, width/reflow/targets, Kannada font loading and reduced motion |
| `make build` | Pass: Laravel production optimization/cache cleanup and strict Vite production build of 54 modules |
| Local strict-build browser inspection | Pass at 320 CSS pixels: visible English fallback, `lang="en-IN"`, `data-requested-locale="kn-IN"` and `scrollWidth === clientWidth` |
| Local Kannada-preview browser inspection | Pass at 1280 and 320 CSS pixels: Kannada glyphs rendered through the bundled font stack with no 320-pixel horizontal overflow |
| Local Markdown/YAML/link and Git hygiene validation | Pass: 19 in-scope Markdown files, two YAML files, local targets/anchors, canonical-input preservation and diff whitespace |

### Security, privacy and tenancy review

- Authentication, membership-derived organization context, cross-tenant
  policies and backend schema are unchanged from accepted PR-003.
- The locale selector is ephemeral and writes no browser persistence.
- Structured fallback events contain allowlisted catalogue context only; raw
  UI text, interpolation values, learner data and provider details are absent.
- Tenant-provided organization names are isolated with `<bdi lang="">` rather
  than assigned an inferred language.
- Pending or stale Kannada cannot enter an ordinary production build through
  the default resolver policy. The explicit preview build remains visibly
  marked and is not production-authorized.
- Audio/recording components are inert and introduce no microphone permission,
  audio retention, transcript, provider, network or storage path.
- No real learner data, curriculum content, captured audio, secret or
  unlicensed media was added.

### Migrations and configuration reviewed

- No backend migration, route, OpenAPI operation or generated contract changed.
- `@fontsource-variable/noto-sans-kannada` 5.3.0 and
  `@axe-core/playwright` 4.12.1 are exact dependencies in the pnpm lockfile.
- `VITE_UI_TRANSLATION_PREVIEW=true` is an explicit non-production review-build
  switch; ordinary production builds remain strict without it.
- CI installs Chromium, runs `make verify`, executes the accessibility browser
  journey and then builds the strict production bundle.
- Root lint now covers web, design-system and localization sources.

### Known limitations

- Kannada wording remains an implementation draft pending independent,
  accountable human language review.
- Automated 200%/400% root-text enlargement is a reflow stress proxy, not
  actual browser-zoom evidence.
- Manual screen-reader, actual browser-zoom and Android TalkBack review remain
  outstanding; axe alone is not an accessibility claim.
- The accessible media contracts have no real playback/capture behavior to
  evaluate yet.
- Docker/Compose, fresh PostgreSQL 18.4 and remote GitHub Actions execution
  remain unavailable locally. PostgreSQL tests used the local version 14
  service, and local PHP is 8.4.17 rather than the declared 8.4.23 patch.
- Curriculum-content language approval and publication are deliberately
  outside UI-catalogue governance and remain blocked on DR-0003.

### Exit decision

PR-004 implementation and its local verification gate are complete and ready
for Accountable Product Owner review. PR-004 is not accepted by this review.
PR-005 must not begin until explicit checkpoint acceptance is recorded and
DR-0003 is resolved through an accepted ADR with the required human evidence.

PR-004 was subsequently accepted on 30 July 2026 under
[APP-0003](APPROVALS.md#app-0003). The acceptance preserves every recorded
limitation and authorizes only PR-005 decision-resolution work.

---

## PR-005 — Decision phase for versioned content and publication

### Scope reviewed

- contextual PR-004 acceptance and the exact boundary of PR-005 authorization;
- current curriculum, content, publication, sharing and enrollment schema;
- ADR-0003 organization ownership and context enforcement;
- proposed content ownership and cross-organization availability;
- academic, language and publisher authority and separation;
- immutable revisions, publication, rollback and idempotency;
- enrollment-to-curriculum-publication pinning and migration compatibility;
- approval-evidence and checkpoint gates.

### Review result

| Check | Result | Evidence |
|---|---|---|
| PR-004 acceptance is bounded and durable | Pass | APP-0003 and D-0034 |
| APP-0003 is not misrepresented as DR-0003 approval | Pass | APP-0003 conditions and proposed ADR-0004 status |
| Current repository has no curriculum/publication implementation | Pass | route, OpenAPI, model, migration, policy, seeder and test inspection |
| Accepted ownership preserves the ordinary Direct-tenant boundary | Pass | All authored content is organization-owned; cross-organization use requires an explicit immutable-publication grant |
| Academic, translation and publisher authority are distinct | Pass | Permission plus scoped authority appointments, creator/reviewer separation and revocation semantics |
| Publication closure and media-rights gates are deterministic | Pass | Derived closed manifest, visible/hidden authoritative review, explicit rights predicate and successor-publication rule |
| Published content and rollback history are immutable | Pass | Append-only revision, review, publication and activation-event rules |
| Sharing, withdrawal and cloning have explicit semantics | Pass | ADR-0004 owner/recipient grants, continuity, suspension and lineage rules |
| Existing enrollment shape has a safe accepted migration | Pass | `pending_curriculum`, pre-activation withdrawal, all-status preflight and atomic immutable pin/activation |
| UI catalogue review is not reused as curriculum approval | Pass | ADR-0004 localization boundary |
| Adversarial governance review has unresolved high/medium findings | No | Final read-only re-review after ownership, authority, rights, manifest and enrollment corrections |
| Required human decision authority is complete | Pass | APP-0004 accepts ADR-0004 at `d9d7cba4b2ccc0d9f2e8656e5fd19bab9ce9dc99`, records every appointment and reviewer acceptance, and authorizes only PR-005 implementation |
| PR-005 schema or product behavior was introduced | No | Governance and proposed decision only |

### Repository integration findings

- Existing `enrollments` contain membership, optional cohort and status only.
  Institution invite acceptance creates them as `active`, so a direct non-null
  publication foreign key would break existing rows and accepted onboarding.
- The proposed additive path introduces `pending_curriculum`, migrates
  pre-content unpinned active rows, preserves unpinned pre-activation
  withdrawal, fails closed on inconsistent unpinned completion and makes
  curriculum pin plus activation one transaction. Only a learner invite with a
  cohort creates that pending enrollment.
- `BelongsToOrganization` and `OrganizationAuthorization` correctly fail
  closed for another organization's record. Shared-content access therefore
  needs a dedicated, allowlisted grant/access repository; tenant scopes must
  not be disabled in a controller.
- Existing audit and outbox records are suitable foundations. Publication
  needs separate command-idempotency state, aggregate locking and an expanded
  safe-identifier outbox allowlist.
- The permission catalogue currently contains eight keys and has exact-count
  tests. ADR-0004 proposes explicit owner, recipient, rights, suspension and
  pinning permissions that require deliberate seeder and test changes after
  authorization.
- Role permissions alone cannot appoint reviewers or rights confirmers. The
  proposal adds scoped, time-bounded, revocable authority appointments linked
  to durable human evidence and snapshotted by each approval.

### Commands and results

| Command | Result |
|---|---|
| Repository route/schema/model/OpenAPI inspection with `rg` and file reads | Pass: no PR-005 product implementation exists |
| Local Markdown/YAML/link validator | Pass: 23 repository Markdown files; YAML front matter, heading progression, fences, UTF-8, final newlines, local targets and anchors |
| `git diff --check` | Pass |
| `make verify` with Node.js 24.18.1, pnpm 11.18.0 and Composer 2.10.2 | Pass on local PHP 8.4.17 with the expected 8.4.23 warning: dependency validation/audits, Pint, PHPStan, 36 SQLite tests/254 assertions, 93 PostgreSQL tests/1,217 assertions, locale validation, OpenAPI/generated-client drift, ESLint, recursive TypeScript and 56 frontend/package tests |

The first full-gate attempt stopped at the toolchain check because the host
`pnpm` was 11.9.0. The declared Corepack pnpm was then placed first on `PATH`.
The next attempts reached PostgreSQL but found the local service stopped and
then its test role/database absent. PostgreSQL 14 was started temporarily, the
committed idempotent local test initializer was applied twice, and the complete
gate passed. The service was stopped after verification. These environment
failures are not represented as test failures or passing checks.

`make e2e` and `make build` were not rerun because this decision phase changes
no user journey, application code, API contract, packaging or deployment
behavior.

### Security, privacy and tenancy review

- Proposed content stays owned by a non-null organization, including material
  authored for Global English Direct.
- Recipient access requires both an explicit owner grant and
  membership-derived recipient authority. It does not introduce a null tenant,
  public-content bypass or caller-supplied organization context.
- Publisher authority cannot substitute for academic or Kannada approval.
  Codex, seed processes and synthetic test identities are not human reviewers.
- The closed-manifest and rights predicates reject omitted learner-visible
  or hidden pedagogically authoritative revisions, localization
  misclassification and unknown, stale, expired or insufficient media
  evidence. Successor rights evidence requires a new publication identity.
- Audit/outbox rules exclude content bodies, translation text, learner data,
  raw media, credentials and provider payloads.
- No real learner data, content body, media asset, secret, migration or product
  behavior was added in this decision phase.

### Known limitations

- Every ADR-0004 development role is consolidated in `S R Bhandary` and is
  therefore non-independent review.
- `MA` is recorded exactly as the qualification/accountable basis supplied for
  each reviewer role; no specialization is inferred.
- Independent tenancy, English-academic and Kannada-language specialist review
  remains required before production.
- Runtime exercise of the reviewer and rights-confirmer roles still requires
  an active organization membership, matching permission and scoped authority
  appointment.
- No PR-005 implementation or acceptance test has started.
- Existing PR-004 environment, accessibility and Kannada-review limitations
  remain unchanged.

### Exit decision

The PR-005 decision phase is accepted under APP-0004. ADR-0004 is accepted and
DR-0003 is resolved. PR-005 schema, seed, API and product implementation is
authorized within the accepted policy. This decision does not accept PR-005,
authorize PR-006 or waive independent pre-production review.

---

## PR-005 — Versioned content and publication foundation

### Scope reviewed

- organization ownership, exact tenant foreign keys and immutable owner
  identity;
- source, Kannada, graph and media-rights revision lineage;
- appointment authority, role separation and the bounded APP-0004
  consolidated-role exception;
- canonical publication closure, scheduling, activation, retirement, rollback
  and successor rights;
- explicit sharing, withdrawal, recipient isolation and clone lineage;
- enrollment migration, atomic publication pinning and retained-pin access;
- owner and named-platform emergency suspension, remediation and resumption;
- idempotency, locking, audit/outbox evidence and concurrent first-write
  behavior;
- safe synthetic seed integrity, OpenAPI enrollment-state compatibility and
  generated client drift;
- the PR-005 checkpoint boundary and exclusion of later learner, speech, AI,
  offline and Android behavior.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Every curriculum aggregate has a non-null immutable owner organization | Pass | composite tenant foreign keys, owner-change triggers and cross-tenant integration tests |
| Source, Kannada and rights history is append-only with exact lineage | Pass | immediate-parent triggers and direct mutation/nonconsecutive-parent tests |
| Same-hash Kannada evidence cannot be substituted across source identities | Pass | variant, review and publication-component composite-key rejection tests |
| Review and rights evidence uses the exact accepted authority kind/event | Pass | PostgreSQL authority triggers and wrong-kind/nonaccepted-event tests |
| Consolidated APP-0004 appointments remain bounded and non-independent | Pass with production gate | deny-by-default runtime user binding, exact evidence/qualification checks and APP-0004 limitations |
| Publication is a deterministic immutable closed manifest | Pass | manifest/component hash tests and graph/content sealing |
| Immediate and future activation histories are explicit | Pass | atomic scheduled/activated immediate events plus scheduled activation tests |
| Scheduled-only snapshots cannot be rollback targets | Pass | fail-closed rollback test with no command/event/head/audit/outbox residue |
| Rollback uses the stored manifest and exact still-valid pinned evidence | Pass | retirement restoration and later exact-review-withdrawal tests |
| Program-scoped successor rights require a new publication | Pass | old/new component evidence remains distinct and the earlier manifest is unchanged |
| Share use is explicit and recipient-isolated | Pass | preview/enrollment/clone allow/deny, withdrawal and recipient mutation/re-share tests |
| Enrollment activation creates one immutable owned/shared pin | Pass | migration, pinner, direct-update rejection and rollback-retention tests |
| Suspension stops all delivery but permits remediation without implicit resume | Pass | owner/platform, retired-pin and suspended-replacement tests |
| Emergency evidence identifies the exact actor session, remediation and prior platform audit | Pass | authentication-session foreign key, PostgreSQL evidence trigger, forged-session/audit rejection and service/event assertions |
| Signed platform authorization cannot drive a second command or duplicate replay audit | Pass | unique append-only authorization nonce plus same-command replay and cross-command reuse tests |
| Concurrent authority revocation cannot pass an in-transaction permission recheck | Pass | locked authority joins and a second PostgreSQL connection timing out on permission removal |
| Shared and pinned access use one locked authoritative snapshot | Pass | resolver-owned transactions and query assertions for authorization, grant/pin, publication, head, activation and evidence locks |
| Competing first publish/schedule has one PostgreSQL winner | Pass | forked-worker concurrency tests |
| Seed is synthetic, unpublished, atomic and replay-safe | Pass | repeat seed and conflicting/pre-existing state rejection tests |
| Later checkpoint behavior was introduced | No | no learner mission execution, speech/AI provider, offline or Android product implementation |

### Commands and results

| Command | Result |
|---|---|
| Focused PR-005 PostgreSQL suites during remediation | Pass; final serialized authority/access/platform/schema/lifecycle suite ran 44 tests with 286 assertions |
| Full PostgreSQL integration suite | Pass: 154 tests, 1,671 assertions |
| Fresh PostgreSQL migrate, seed, repeated seed | Pass |
| Four-step empty-data rollback, forward migrate and seed | Pass; populated-enrollment rollback denial is separately covered |
| `make verify` with Node.js 24.18.1, pnpm 11.18.0 and Composer 2.10.2 | Pass on local PHP 8.4.17 with the expected 8.4.23 warning: dependency validation/audits, Pint, PHPStan, 36 SQLite tests/254 assertions, 154 PostgreSQL tests/1,671 assertions, locale validation, OpenAPI/generated-client drift, ESLint, recursive TypeScript and 56 frontend/package tests |
| `make e2e` | Pass: five Chromium scenarios after rerunning outside the macOS process sandbox |
| `make build` | Pass: Laravel production optimization/cleanup and strict Vite production build, 54 modules |
| Local Markdown structure/link validator | Pass: 24 in-scope Markdown files; UTF-8, final newlines, balanced fences, heading progression and repository-local links/anchors |
| `git diff --check` and canonical-input diff | Pass; no whitespace errors and no change to `AGENTS.md` or `ENTERPRISE_PRODUCT_MASTER_PLAN.md` |

The first full-gate attempt stopped at the pinned Composer check because the
host Composer was 2.9.3. Composer 2.10.2 was downloaded to an isolated
`/private/tmp` path and the unchanged gate then passed. The first browser run
could not start Chromium because the macOS sandbox denied its Mach rendezvous
port; the approved unsandboxed rerun passed all five scenarios. Neither
environment stop is represented as a product-test failure or a passing
attempt. `make ai-eval` was not run because it intentionally fails before
PR-011 and PR-005 changes no prompt, model, provider or AI orchestration.

### Security, privacy and tenancy review

- Owner-side commands recheck membership-derived permissions inside their
  authoritative transactions and lock the authority rows used by the decision.
  Concurrent suspension or permission removal therefore waits for the
  protected command. Caller-supplied organization IDs never establish context.
- Shared access uses a dedicated recipient-context resolver. Grant use does not
  expose owner drafts, authority records, memberships or unrelated grants, and
  recipients cannot edit, publish or re-share owner records. Shared and pinned
  access decisions own their transactions, capture one trusted instant and
  lock the mutable state they evaluate.
- Exact source identities, revision lineage, appointment kinds/events and
  organization ownership have database enforcement, not service-only checks.
- Raw caller idempotency keys are aggregate-scoped and replay is bound to the
  exact actor and request. Actor mismatch fails closed.
- Existing delivery keeps its immutable publication pin. Rights expiry or
  withdrawal and explicit suspension stop delivery; later review invalidation
  blocks future activation, rollback and resumption without silently rewriting
  a live release.
- Platform emergency authority is disabled by default and is signed, named,
  target-bound, short-lived and fresh-MFA-bound. The resulting event links the
  exact platform audit record created before organization context. A unique
  audit nonce prevents one authorization from driving another command, while
  exact idempotent replay does not manufacture another audit event.
- Audit/outbox payloads contain identifiers and stable keys only. No curriculum
  bodies, Kannada text, media bytes, learner data, credentials, transcripts or
  provider payloads were introduced.
- Seed data is synthetic and unpublished. No real learner data or unlicensed
  media was added.

### Migrations and configuration reviewed

- Migrations `000020`–`000023` add curriculum structure, immutable governance
  evidence, publication/sharing history and enrollment pins.
- `000023` upgrades pre-content unpinned active rows to
  `pending_curriculum`, fails closed for invalid completed rows and refuses
  rollback once any enrollment exists.
- Publication events require exact emergency evidence for suspend/resume rows;
  PostgreSQL binds the session to the accountable actor and platform emergency
  events additionally bind the exact target/capability/action/reason/program/
  correlation audit row.
- Migration `000022` extends append-only platform audit history with a unique
  signed-authorization nonce; pre-existing audit rows remain nullable for
  migration compatibility.
- `CONTENT_GOVERNANCE_APP_0004_ACTOR_USER_ID` binds the accepted human evidence
  to a stable runtime user and is empty/deny-by-default.
- `PLATFORM_CONTENT_EMERGENCY_ACTOR_USER_IDS` is an empty/deny-by-default list
  of stable platform user ULIDs.
- OpenAPI 0.3.0 and generated clients add only the
  `pending_curriculum` enrollment status; there is no PR-005 public
  content/publication endpoint.

### Known limitations

- `S R Bhandary` holds every APP-0004 development role. Those approvals are
  explicitly non-independent; independent tenancy, English-academic and
  Kannada-language review remains mandatory before production.
- `MA` is recorded exactly for the English Academic Reviewer and Kannada
  Reviewer; no specialization is inferred.
- No production curriculum has runtime human review evidence. The synthetic
  seed is deliberately unpublished.
- Media asset import and initial pending-rights creation have no public or
  dedicated domain import command.
- Media-bearing clone requests fail closed until reviewed object-copy,
  retention and derivative-rights behavior exists.
- Scheduled activation workers and schedule cancellation are not implemented;
  the foundation exposes explicit schedule/activate commands.
- Evidence transitions use PostgreSQL table-level share locks and need
  representative production-volume benchmarking.
- Public content/publication HTTP operations remain a later bounded issue.
- Docker and the declared PostgreSQL 18.4 image were unavailable locally;
  database verification used PostgreSQL 14. Local PHP is 8.4.17 rather than
  the declared 8.4.23 patch.
- Remote CI, production runbooks and independent specialist reviews remain
  outstanding.
- No learner mission execution, attempt/mastery record, playable/captured
  audio, offline behavior, AI/provider integration or Android product UI is in
  PR-005.

### Exit decision

PR-005 implementation and local verification are complete within APP-0004 and
are ready for Accountable Product Owner review. This quality review does not
accept PR-005, authorize PR-006 or authorize production. PR-006 remains blocked
until the human explicitly accepts PR-005 at its exact implementation commit
and authorizes only PR-006.

### Subsequent checkpoint acceptance

[APP-0005](APPROVALS.md#app-0005) records `S R Bhandary`'s explicit
Accountable Product Owner acceptance of PR-005 at
`daeee1df7a7e4d31427001aedece76d8121413d6`, with every limitation above, and
authorizes only PR-006 — Deterministic daily-speaking slice.

That approval does not authorize PR-007, AI or speech-provider orchestration,
production deployment or any later checkpoint.

---

## PR-006 — Deterministic daily-speaking slice

### Scope reviewed

- minimal adult learner onboarding and allowlisted goal selection;
- active authenticated learner identity, organization context and exact
  enrollment publication pin;
- one deterministic no-AI mission contract;
- browser model-audio/transcript presentation and volatile local
  recording/playback/retry;
- submitted formative attempts, append-only responses and exact
  first/latest/best evidence;
- deterministic knowledge mastery, spoken-mastery denial and review
  scheduling;
- transaction, idempotency and concurrent-attempt behavior;
- tenant, learner, enrollment, curriculum-owner, publication and mission
  relationship protection;
- OpenAPI/generated-client compatibility and safe problem responses;
- browser contract golden path, accessibility and raw-audio
  storage/network-cleanup assertions;
- honest production-seed and test-fixture boundaries; and
- exclusion of PR-007 offline/outbox, AI/speech-provider and PR-009 recording
  upload/retention behavior.

Detailed rules and the final evidence map are maintained in
[the PR-006 verification scope](PR-006_DETERMINISTIC_DAILY_SPEAKING_VERIFICATION_SCOPE.md).

### Accepted implementation rules

| Rule | Required outcome | Current evidence |
|---|---|---|
| Learner ownership | every learner record is organization-owned and tied to the authenticated learner membership | Pass: PostgreSQL composite relationships, policy checks, same-tenant-other-learner denial and authenticated HTTP boundary |
| Enrollment authority | only an active enrollment's exact immutable publication pin can select a mission; learners cannot self-pin | Pass: pinned-access resolver and pending/completed/withdrawn, poisoned-pin and mission-mismatch tests |
| Evidence scope | summaries cover submitted attempts for the exact learner membership, enrollment, publication and mission | Pass: governed lifecycle and historical replay tests |
| First evidence | lowest server-assigned attempt number | Pass: ordered retry evidence |
| Latest evidence | highest server-assigned attempt number | Pass: ordered retry and score-regression evidence |
| Best evidence | highest deterministic score; earliest attempt number wins a tie | Pass: correct/incorrect retries and equal-score tie evidence |
| Canonical evidence | submitted attempts and responses are append-only; summary pointers remain derived | Pass: PostgreSQL mutation triggers and post-submit response rejection |
| Deterministic authority | caller input cannot set attempt number, score, mastery or review outcome | Pass: strict HTTP requests, versioned policy and direct-database forgery rejection |
| Mastery separation | a deterministic check may advance knowledge mastery; spoken mastery requires eligible spoken evidence | Pass: every no-capture and capture-receipt result remains `spoken_state=unassessed` |
| Capture receipt | local-capture receipt is non-authoritative and cannot satisfy spoken mastery | Pass: bounded receipt validation, deterministic results and progress assertions |
| Raw-audio privacy | audio bytes stay in volatile browser memory and never enter API, storage, cache, log, audit or outbox data | Pass: browser storage/network/cleanup assertions, strict payload rejection and safe-event source audit |
| Checkpoint boundary | no PR-007 offline/outbox, provider orchestration, transcription, generated voice or recording-upload workflow | Pass: source/dependency/route/storage scope audit |
| Content authority | canonical synthetic seed remains unpublished; contract fixtures create no human approval or media-rights claim | Pass: repeated seed and zero-publication assertions |
| Browser evidence | a stubbed browser journey is labeled as contract evidence, not a true full-stack published-media path | Pass: two explicitly contract-stubbed Chromium learner scenarios |

### Commands and results

| Command or check | Result |
|---|---|
| Focused PR-006 PostgreSQL suites | Pass: lifecycle/exact-one 2 tests/78 assertions; concurrency 1/24; HTTP 2/65; request validation 3/10; verified audio 4/8 |
| Fresh PostgreSQL migration and seed | Pass on the explicitly verified `global_english_test` database |
| Empty-data rollback, forward migration and repeat seed | Pass; migration `000024` reapplied once, seed remained idempotent, 21 permissions and zero publications/profiles/attempts |
| `make verify` | Pass: SQLite 39/264, PostgreSQL 163/1,848, localization 23, design system 8 and web 50; audits, formatting, PHPStan, lint, type and contract checks passed |
| `make e2e` | Pass: 7 Chromium scenarios |
| `make openapi` | Pass: OpenAPI 3.1.1 version 0.4.0 and generated clients match |
| `make build` | Pass: Laravel production cache exercise and Vite build of 63 modules |
| Local Markdown structure/link validator | Pass: 25 in-scope files, zero structural/local-link failures |
| `git diff --check` and checkpoint-scope audit | Pass; unrelated `deliverables/` and `docs/archive/` remain outside the checkpoint |

The passing PR-005 commands remain the starting baseline and are not counted
as PR-006 evidence. `make ai-eval` is not applicable because PR-006 is
forbidden from changing prompts, models, providers or AI orchestration.

### Security, privacy and tenancy review

- Organization context must be reconstructed from the accepted authenticated
  principal and active membership. Request bodies cannot supply authoritative
  organization, learner, enrollment owner, curriculum owner or publication
  scope.
- Learner commands must prove exact user-membership ownership and active
  enrollment state. Same-tenant access to another learner is denied just as
  cross-tenant access is.
- Start, response and submission paths must resolve the exact enrollment pin
  and mission. `pending_curriculum`, completed and withdrawn enrollments fail
  closed. Ordinary share withdrawal preserves an existing pin, while owner
  inactivity, current rights failure and emergency suspension deny delivery
  and new learning writes.
- Attempt-number allocation, response append, submission, summary refresh,
  mastery and review scheduling require a transactional lock boundary.
  Actor/fingerprint-bound idempotency returns exact replay and rejects changed
  requests or another actor.
- The server derives score, knowledge mastery and review timing from governed
  versioned rules. Client-supplied score, mastery, attempt number or spoken
  authority is rejected.
- Raw learner audio remains entirely in volatile browser memory. Retry,
  cancellation, route exit and unmount release media tracks and object URLs.
  No raw audio or transcript belongs in logs, metrics, requests, PostgreSQL,
  object storage, IndexedDB, service-worker cache, audit or outbox data.
- A bounded local-capture receipt is retained only as non-authoritative input
  metadata. It contains no audio, transcript, filename, blob URL, device label
  or provider data and cannot award spoken mastery.
- Cross-tenant and same-tenant-other-learner failures use safe errors that do
  not disclose record existence.

### Content, media and test-fixture review

- APP-0005 authorizes PR-006 implementation but approves no future curriculum
  publication, Kannada wording, transcript, model audio or media-rights
  evidence.
- The canonical `FoundationSeeder` must remain an unpublished
  Codex-attributed draft. It cannot manufacture human review, rights
  confirmation or a production publication.
- A browser contract stub or synthetic media fixture may demonstrate client
  states and accessibility. It is not accountable human review and is not
  evidence that a real published media asset is deliverable.
- Backend PostgreSQL integration tests remain authoritative for tenant
  ownership, publication pins, immutable evidence, idempotency, concurrency,
  mastery and review rules unless a true full-stack journey is implemented
  and separately identified.

### Migrations, contracts and configuration reviewed

- `2026_07_30_000024_create_deterministic_learning_tables.php` adds
  `learner_profiles`, `learner_goals`, `learner_attempts`, `item_responses`,
  `attempt_submissions`, `mastery_states`, `review_schedules` and
  `learning_commands`, plus the composite enrollment-pin identity required by
  their foreign keys.
- PostgreSQL partial uniqueness, checks, composite foreign keys and triggers
  enforce one open attempt, immutable snapshots, sequential responses,
  deterministic submission/mastery/review projections and append-only
  evidence. The migration refuses rollback after learner profiles or attempts
  exist; authoritative adoption therefore requires a reviewed forward fix or
  backup restoration rather than silent evidence loss.
- OpenAPI 3.1.1 document version 0.4.0 adds `getLearnerHome`,
  `completeLearnerOnboarding`, `getLearnerMission`,
  `startLearnerMissionAttempt`, `recordLearnerAttemptResponse`,
  `submitLearnerMissionAttempt`, `getLearnerProgress` and
  `streamLearnerModelAudio`. Generated TypeScript clients, types and JSON
  schemas are committed under `packages/contracts/src/generated/`.
- The current-identity response now includes the authoritative
  `membership_type`; the React journey renders only for a selected learner
  membership rather than inferring learner authority from a mutable role key.
- `config/learning.php` owns the allowlisted goals, versioned
  `daily-speaking-v1` rule selection, local-capture MIME/size/duration bounds
  and private model-audio MIME/10 MB bounds. `LEARNING_MODEL_AUDIO_DISK`
  separates governed delivery from a caller-supplied storage location.
- Interface catalogue revisions are `ui-en-IN-2026-07-30.4` and
  `ui-kn-IN-2026-07-30.4`. English is the approved source; the Codex-authored
  Kannada interface draft remains `pending-independent-review` and falls back
  to English under the production `approved-only` policy.

### Known limitations

- No production learner mission, Kannada guidance, transcript or model-audio
  asset has accountable runtime publication evidence.
- The canonical synthetic mission remains unpublished.
- Browser E2E is contract-stubbed and therefore does not prove a real
  API-to-PostgreSQL-to-governed-media path.
- PR-006 deliberately rejects publications with zero or multiple missions.
  Multi-mission objective progress requires a later persisted result snapshot
  or global objective-evidence order before this implementation can expand.
- Raw learner audio is not uploaded, retained, transcribed, analyzed or
  available for trainer review.
- A local-capture receipt cannot award spoken mastery.
- At PR-006 exit, DR-0005 remained open for recording consent states,
  retention, deletion and provider-processing boundaries. It was subsequently
  resolved by ADR-0007 under APP-0011 without authorizing PR-009.
- PR-007 offline mission bundles, service-worker caching, IndexedDB outbox and
  reconnect synchronization remain excluded.
- AI conversation, speech-to-text, generated voice, pronunciation signals and
  other provider orchestration remain excluded.
- Independent production reviews and environment limitations remain in force.
- Docker/Compose and PostgreSQL 18.4 were unavailable; local PostgreSQL 14.19
  was used. Local PHP 8.4.17 differs from pinned 8.4.23, and GitHub Actions
  did not run on a remote runner.

### Exit decision

PR-006 is implemented and locally verified. This quality review itself does
not approve a curriculum publication or authorize production.

### Subsequent checkpoint acceptance

[APP-0007](APPROVALS.md#app-0007) records `S R Bhandary`'s explicit
Accountable Product Owner acceptance of PR-006 at
`598ad387e65b94707564dce1e199c97deb411d82`, with its recorded limitations.
Only PR-007 decision-resolution work is authorized. Product implementation
remains blocked until ADR-0006 is accepted and DR-0004 is resolved.

---

## ADR-0005 — Phase 1 multilingual UI expansion

### Scope reviewed

- complete UI catalogue previews for Malayalam (`ml-IN`), Tamil (`ta-IN`),
  Telugu (`te-IN`) and Hindi (`hi-IN`);
- preservation of Kannada as the default requested interface locale and
  English as the governed source, learning language and deterministic
  fallback;
- exact catalogue parity, source linkage, hashes and honest draft governance;
- strict production fallback and visible development/review preview status;
- locale selection, BCP 47 language metadata and privacy-safe fallback events;
- self-hosted Kannada, Malayalam, Tamil, Telugu and Devanagari variable fonts;
- six-locale unit, accessibility, reflow, font and browser coverage; and
- exclusion of curriculum, speech recognition, model audio, AI explanations,
  API contracts, persistence and authoritative records.

The accepted boundary and release gates are maintained in
[the Phase 1 multilingual UI expansion scope](PHASE1_MULTILINGUAL_UI_EXPANSION_SCOPE.md).

### Review result

| Check | Result | Evidence |
|---|---|---|
| Six complete interface catalogues have exact key and placeholder parity | Pass | `locale:check` and localization validation tests; 129 keys per catalogue |
| English remains the source and fallback | Pass | resolver policy and strict production fallback tests |
| All five translations are honestly marked as implementation drafts | Pass | governance metadata has `pending-independent-review`, no reviewer and no approval time |
| Drafts render only under preview policy with a visible notice | Pass | resolver and web application tests for all four added locales |
| Locale selection remains ephemeral and credential-free | Pass | web unit and Chromium storage checks |
| Language metadata covers every supported BCP 47 locale | Pass | application and mixed-language component tests |
| Script fonts are self-hosted and loaded | Pass | pinned package lock and Chromium font-resource checks |
| Six locale states remain axe-clean and responsive | Pass | Chromium axe, width, enlarged-text and target-size checks |
| Curriculum and provider boundaries remain unchanged | Pass | source, contract and scope inspection; no OpenAPI or backend behavior change |

### Catalogue evidence

| Locale | Revision | Catalogue SHA-256 | Release status |
|---|---|---|---|
| `en-IN` | `ui-en-IN-2026-07-30.7` | `894b0f5e95ecb9347f1eeb55a8dfed1f80991996ebb8a1d3f843030cd1b8d5e9` | Source |
| `kn-IN` | `ui-kn-IN-2026-07-30.7` | `9dac4931d05f0b46903d40592179f13005753dde857caa4279e8e7942492e19a` | Pending independent review |
| `ml-IN` | `ui-ml-IN-2026-07-30.3` | `c9a73cd2e78a53ca11df9d4f7e982143e3ffa9ddd7e54a0f64352d4ad98a66ac` | Pending independent review |
| `ta-IN` | `ui-ta-IN-2026-07-30.3` | `38322af7777cac4aea5bb726a50846a25f424387f53304b1c5d3bc077881114a` | Pending independent review |
| `te-IN` | `ui-te-IN-2026-07-30.3` | `9b678d26e3c9c7084750be702ace49a46c3c213f882bfe1d7de42eca8a939dc8` | Pending independent review |
| `hi-IN` | `ui-hi-IN-2026-07-30.3` | `8f5818d1f9781c4522b4a2a2a48e5b89eac21616dc5d38c4cdcc7cdfe2430a65` | Pending independent review |

### Commands and results

| Command or check | Result |
|---|---|
| `make verify` | Wrapper blocked: GNU Make is not installed; the exact constituent commands passed with SQLite 39 tests/264 assertions, PostgreSQL 164/1,883, localization 31, design system 9 and web 54 |
| `pnpm run e2e` (`make e2e` constituent) | Pass: 8 Chromium scenarios |
| Laravel optimize/cleanup and `pnpm --filter @global-english/web build` (`make build` constituents) | Pass: strict production build of 70 modules |
| `pnpm run locale:check` | Pass: 6 catalogues with the 5 expected pending-review governance warnings |
| Changed Markdown local-link validation | Pass: 11 files, zero missing local targets |
| `git diff --check` | Pass |

`make ai-eval` is not applicable because prompts, models, providers and AI
orchestration are explicitly unchanged.

### Security, privacy and tenancy review

- No authentication, authorization, organization-scoping or tenant-owned
  record changed.
- Locale selection remains in React memory. It is not written to
  `localStorage`, IndexedDB, cookies, ordinary Capacitor preferences or the
  server.
- Fallback events contain only allowlisted catalogue metadata; they contain no
  translated message, parameter, learner data, credential or provider
  payload.
- Catalogue and font assets are static repository-governed build artifacts.
  No database migration, seed, production content, learner data or media was
  added.

### Known limitations and release gates

- Kannada, Malayalam, Tamil, Telugu and Hindi wording is an
  implementation draft authored by Codex, not a human-approved translation.
- Each exact revision and hash requires an accountable translator and a
  distinct independent reviewer competent in that language before production.
- Ordinary production remains `approved-only` and visibly falls back to
  English for every unapproved, stale, rejected or incomplete translation.
- Manual terminology, wrapping, browser zoom, screen-reader pronunciation and
  representative Android TalkBack review remains outstanding for every
  translated locale.
- UI availability does not imply curriculum, speech-recognition, model-audio,
  text-to-speech or AI-explanation support in that language.
- PR-006 is accepted separately under APP-0007. ADR-0006 and APP-0008 now
  authorize the separately reviewed PR-007 implementation; that does not
  approve any translated catalogue for production.

### Exit decision

The bounded Phase 1 multilingual UI implementation authorized by APP-0006 is
implemented and locally verified. This review does not approve any translated
catalogue for production or expand curriculum, speech, audio or AI behavior.
PR-006 is accepted separately under APP-0007; PR-007 authority is recorded
separately in APP-0008.

---

## PR-007 — Offline bundle and idempotent outbox

### Scope reviewed

- APP-0007 acceptance of PR-006 at its exact implementation commit;
- accepted ADR-0006 and APP-0008 PR-007-only implementation authority;
- signed mission-bundle and atomic reconnect-completion contracts;
- personal-device opt-in, protected browser storage, public shell caching,
  offline UI states and logout boundaries;
- exact identity, membership, tenant, enrollment, publication and policy
  binding; and
- PostgreSQL authority, idempotency, rollback and ordinary PR-006 evidence
  behavior.

### Review result

| Check | Result | Evidence |
|---|---|---|
| PR-006 acceptance is bound to the exact `598ad387e65b94707564dce1e199c97deb411d82` revision | Pass | APP-0007 |
| ADR-0006 is accepted at the exact approved proposal hash and DR-0004 is resolved | Pass | ADR-0006 and APP-0008 |
| Bundles are signed, purpose-specific, exact-account/publication bound, no-store and expire at or before 24 hours | Pass | service implementation and PostgreSQL expiry/tamper/identity tests |
| Offline completion is one atomic server-authoritative PR-006 command with exact replay and changed-replay conflict | Pass | PostgreSQL lifecycle tests and one outer command assertion |
| Invalid option or late failure leaves no partial attempt, response, mastery, review or command | Pass | PostgreSQL rollback assertions |
| Persistent storage requires explicit personal-device confirmation and is absent before opt-in | Pass | web unit and Chromium storage tests |
| Protected records use opaque partitions, non-exportable AES-GCM keys, authenticated metadata and verified model-audio bytes | Pass | browser implementation and Chromium ciphertext/key/hash checks |
| Credentials, raw learner audio, names and organization display names are excluded | Pass | request/storage allowlists, validation tests and browser payload inspection |
| Public caching excludes API, Sanctum and OAuth paths | Pass | service-worker policy and Chromium cache inspection |
| One browser lock coordinates reconnect while server idempotency remains final | Pass | client implementation, immutable command unit test and server replay tests |
| Logout blocks on pending work and otherwise purges keys before web or Android server revocation | Pass | authentication adapter ordering and blocked-logout tests |
| OpenAPI and generated clients describe both offline endpoints without drift | Pass | Redocly, policy validation and generated-client check |
| PR-008, AI/provider behavior, recording upload and production remain absent | Pass | dependency, route and scope inspection |

### Commands and results

| Command or check | Result |
|---|---|
| `make verify` | Wrapper unavailable because GNU Make is not installed; every constituent command passed |
| Composer validate/audit | Pass; no locked dependency advisory |
| Pint and PHPStan | Pass; zero static-analysis errors |
| SQLite suite | Pass: 39 tests, 264 assertions |
| PostgreSQL suite | Pass: 164 tests, 1,883 assertions |
| pnpm frozen install/audit | Pass; no known vulnerability |
| Locale/OpenAPI/generated-client/lint/typecheck | Pass; five intentional translation-review warnings only |
| Workspace unit tests | Pass: localization 31, design system 9, web 54 |
| `pnpm run e2e` | Pass: 8 Chromium journeys |
| Laravel production optimize/cleanup and Vite build | Pass: 70 transformed modules |

`make ai-eval` is not applicable because prompts, models, providers and AI
orchestration are unchanged.

### Security, privacy and tenancy review

- The lease cannot authenticate an API request; reconnect still requires the
  current first-party principal, active exact membership, tenant context,
  active enrollment and unchanged publication authority.
- The browser never decides attempt number, mastery, review timing or
  authoritative completion. Local correct/incorrect guidance is visibly
  pending until the server returns the ordinary result.
- Same-tenant other-learner access, signature tampering, lease expiry and
  caller-supplied authority fail closed. Existing organization scoping remains
  inside the project-owned learning services.
- The APP-0008 development roles are consolidated in `S R Bhandary`; this is
  explicitly not independent production review.

### Known limitations and release gates

- There is no learner-facing logout/account-management screen. The adapter
  boundary blocks logout when pending protected commands exist, but the later
  UI must offer sync, cancel or explicit permanent discard before retrying.
- Browsers without `indexedDB.databases()` fail closed for offline resume;
  they may enable the current-session download but cannot silently reopen a
  partition after restart.
- The browser cannot guarantee timed cleanup while it is not running. Cleanup
  is activation/contact driven as accepted in ADR-0006.
- Browser locks are advisory. Server transactions and idempotency remain the
  final duplicate/concurrency authority.
- The E2E journey uses contract-stubbed synthetic curriculum/media; production
  content, language, accessibility-device and release review remain open.
- The workstation has Docker Engine and Compose, but this Codex login has not
  refreshed docker-group membership. Full PostgreSQL verification used the
  available loopback PostgreSQL 14 service.
- GNU Make is absent, so documented wrapper targets were reproduced from the
  Makefile instead of invoked directly.
- GitHub Actions and representative Android device tests have not run.
- PR-007 is accepted separately under APP-0009 at
  `e0e2f1b90e0eb4ecf680f5243b57ec0731c32648`. PR-008 and production remain
  unauthorized.

### Exit decision

PR-007 is implemented and repository-verified within APP-0008, and the
Accountable Product Owner accepts the exact implementation commit
`e0e2f1b90e0eb4ecf680f5243b57ec0731c32648` with its recorded limitations
under APP-0009. That acceptance does not authorize PR-008, production or any
later implementation checkpoint.

---

## PR-008 — Conversation domain with deterministic fake providers

### Scope reviewed

- APP-0010 authority and the accepted PR-007 baseline;
- organization-scoped personas, scenarios, learner sessions and turns;
- project-owned STT, conversation-model and TTS ports;
- local/testing-only deterministic fake adapters and production fail-closed
  bindings;
- deterministic state transitions, bounded retry, idempotency, safe SSE
  status history and zero-cost usage records;
- request-memory-only typed prototype content and the no-audio boundary;
- authenticated learner Practice UI, disclosure and six-locale draft
  catalogue changes; and
- OpenAPI, generated clients, PostgreSQL protection and regression coverage.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Persona and scenario definitions are versioned, synthetic and organization scoped | Pass | migration, foundation seed and PostgreSQL seed/integrity tests |
| Sessions and turns bind the authenticated learner's exact active membership and organization | Pass | `LearnerAuthority`, composite foreign keys and same-/cross-tenant tests |
| Provider SDK types do not enter the domain | Pass | project-owned provider interfaces, DTOs and adapter bindings |
| Only an explicit fake driver in `local`/`testing` can resolve fake adapters | Pass | environment gate, disabled adapters and feature tests |
| Successful and retry/failure state transitions are deterministic | Pass | state-machine and fail-once/exhausted PostgreSQL tests |
| Exact command replay is stable and changed replay conflicts | Pass | session/turn idempotency tests and row-count assertions |
| Persistent records exclude utterance, transcript, reply and audio content | Pass | schema allowlists plus database, audit and outbox content scans |
| SSE contains only ordered safe state metadata | Pass | media-type, no-store, ordering and ownership tests |
| Usage records contain bounded latency/attempt metadata and zero fake cost | Pass | cost-ledger constraints and integration assertions |
| Conversation cannot modify attempts, mastery, progression or review schedules | Pass | before/after learning-table assertions |
| Web UI discloses fake AI, request-memory-only text and text-only fake TTS | Pass | React tests and Chromium critical journey |
| No browser audio/upload/provider-network path exists | Pass | source inspection and intercepted Chromium request assertions |
| API and generated client contracts match | Pass | strict Redocly, policy and generated-drift checks |
| PR-009, real providers and production remain absent | Pass | dependency, route, configuration and scope inspection |

### Commands and results

| Command or check | Result |
|---|---|
| Composer validate/audit | Pass; no locked dependency advisory |
| pnpm frozen install/audit | Pass; lockfile current and no known vulnerability |
| Pint and PHPStan | Pass; zero formatting or static-analysis errors |
| SQLite-safe backend suite | Pass: 42 tests, 277 assertions |
| PostgreSQL integration suite | Pass: 169 tests, 1,981 assertions |
| `pnpm run locale:check` | Pass: six catalogues and five expected pending-review warnings |
| `pnpm run openapi:check` | Pass: OpenAPI 3.1.1 version 0.6.0 and generated-client parity |
| ESLint and recursive TypeScript | Pass |
| Workspace unit tests | Pass: localization 31, design system 9 and web 57 |
| `pnpm run e2e` | Pass: 9 Chromium journeys, including the fake-provider conversation and axe scan |
| Laravel optimize/cleanup and web production build | Pass: 74 Vite modules transformed |
| `make verify` / `make build` | Wrapper blocked because GNU Make is absent; every constituent command above passed |

`make ai-eval` remains not applicable: PR-008 uses fixed deterministic fake
responses and introduces no real model, prompt selection or provider
orchestration.

### Security, privacy and tenancy review

- Every mutable conversation row carries `organization_id` and exact learner
  membership ownership. Composite constraints and application authorization
  fail closed for same-organization other learners and other tenants.
- Bounded typed text is processed only in request memory. The database stores
  SHA-256 digests and safe metadata, while audit/outbox tests prove the
  prototype utterance and fake reply do not escape.
- Fake TTS returns metadata only. No recording permission, audio bytes,
  multipart/base64 input, object-storage key, playback URL or retained audio
  is introduced.
- Provider identity, model version, state, attempt, latency and cost are
  server-owned. The fake ledger cost is constrained to zero.
- Production and non-local environments bind disabled adapters. There is no
  provider credential or external provider dependency.
- The conversation slice is advisory and has no write path to deterministic
  learning authority.

### Known limitations and release gates

- The fake STT echoes bounded typed text; it does not recognize speech. Fake
  TTS creates no audio, and the fake model returns one fixed synthetic reply.
- The event route returns a bounded history and closes. It is not a live
  multi-process broker or reconnectable production stream.
- Conversation text retention, real provider processing, consent, secure
  upload, transcript recovery and deletion were unresolved at PR-008
  implementation time. ADR-0007 now supplies the accepted policy under
  APP-0011, but PR-009 implementation remains separately unauthorized.
- The seeded persona/scenario and all five translated UI catalogues are
  synthetic implementation drafts, not production-approved content.
- The Chromium journey uses contract stubs for the web boundary; backend
  tenancy, persistence, replay and state behavior are separately proven
  against PostgreSQL.
- GNU Make is absent, so wrapper commands were reproduced from the Makefile.
  GitHub Actions, real devices, screen readers and independent production
  security/privacy/tenancy/accessibility/language/release reviews have not run.
- No public-facing server was started. Playwright used a temporary preview
  listener on `127.0.0.1:4173` and stopped it after each run.

### Exit decision

PR-008 is implemented and repository-verified within APP-0010 and is ready
for exact-commit Accountable Product Owner review. It is not accepted yet.
PR-009, real provider calls, audio upload/persistence and production remain
unauthorized.

---

## DR-0005 — Recording policy decision and acceptance

### Scope reviewed

- master-plan recording, privacy, provider, tenancy and PR-009 constraints;
- accepted ADR-0001, ADR-0002, ADR-0003 and ADR-0006 boundaries;
- accepted consent purposes and per-turn learner submission;
- upload quarantine and media-validation limits;
- raw-audio, transcript, generated-media, evidence and backup retention;
- withdrawal, deletion, provider deletion and reconciliation;
- provider eligibility, request minimization and no-training rules;
- learner transcript recovery and deterministic learning authority; and
- non-production, legal, independent-review and release gates.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Exact proposal integrity | Pass | approved SHA-256 matches the committed ADR-0007 proposal at `98c46584b298cbf18623b140dbe5dc3dbc7737e6` |
| Approval authority and evidence | Pass | APP-0011 records the exact statement and all four required development roles |
| ADR acceptance | Pass | accepted front matter links APP-0011 and preserves the approved proposal hash |
| DR-0005 resolution | Pass | resolved-decision table links ADR-0007 and APP-0011 |
| PR-009 remains unauthorized | Pass | ADR status, fail-closed rollout and repository status |
| Raw audio is temporary, private and excluded from ordinary logs/backups | Pass | data-classification, validation and retention sections |
| Consent, withdrawal and deletion are purpose-specific and measurable | Pass | consent ledger, per-turn authorization and deletion objectives |
| Provider data is minimized and secondary use is prohibited | Pass | provider-processing boundary |
| Organization and exact-membership ownership remain mandatory | Pass | tenancy and authorization section |
| Automated transcript/speech output cannot alter learning authority | Pass | transcript-recovery and authoritative-record sections |
| Production has explicit legal and independent-review gates | Pass | context and fail-closed rollout |
| No source, API, schema, configuration or environment change is authorized | Pass | documentation-only diff inspection |

### Commands and results

| Command or check | Result |
|---|---|
| Node/js-yaml front matter, heading, fence, final-newline, local-link and anchor validation | Pass: five changed Markdown files |
| Accepted-status and APP-0011 evidence assertions | Pass |
| `git diff --check` | Pass |
| Scoped status/diff inspection | Pass: only the accepted ADR and four governance records belong to this checkpoint |
| SHA-256 proposal hashing | Pass; exact hash reported with the proposal commit |

Application test, E2E, AI-evaluation and build commands are not applicable
because this checkpoint changes documentation only and authorizes no runtime
behavior.

### Security, privacy and tenancy review

The accepted policy treats learner voice and transcript content as sensitive,
purpose-limited data. It requires fail-closed adult eligibility, consent,
private encrypted media, short retention, deletion reconciliation,
content-free telemetry, project-owned provider adapters, exact tenant
ownership and same-/cross-tenant tests before any later implementation can be
accepted.

This is a development policy draft, not legal advice or independent
production review. Qualified Indian privacy counsel must confirm applicable
commencement, notice, retention, erasure, processor and transfer obligations
before production.

### Known limitations and implementation gate

- `S R Bhandary` accepted the exact proposal artifact while exercising four
  consolidated development roles; this is not independent production review.
- No real provider has been selected, reviewed, contracted or benchmarked.
- No notice or translation has independent legal or language approval.
- The accepted policy intentionally leaves PR-009 implementation, provider
  configuration, audio processing and production unauthorized.
- PR-008 exact-commit acceptance remains a separate pending checkpoint gate.

### Exit decision

ADR-0007 is accepted under APP-0011 and DR-0005 is resolved. The approval
records no PR-009 implementation authority or production authority.

---

## PR-009 — Provider-neutral secure speech and transcript recovery

### Scope reviewed

- APP-0012 authorization and accepted ADR-0007;
- separate upload/transcription and provider-processing consent purposes;
- local volatile capture, explicit submit and browser PCM/WAV conversion;
- server-side media signature/container/codec/duration/size verification;
- application-encrypted transient private storage and scheduled deletion;
- project-owned recorded-STT port with disabled and deterministic-test
  adapters only;
- transcript confidence, learner correction/discard, history-off behavior and
  deletion evidence;
- organization/exact-membership ownership, replay protection and
  deterministic-learning isolation;
- version 0.7.0 OpenAPI/generated clients and the accessible Practice UI; and
- six-locale governance with production English fallback.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Voice collection is disabled by default and production fails closed | Pass | `voice.php`, feature gate, disabled adapter and environment binding |
| Upload/provider purposes are separately granted from a versioned notice | Pass | append-only consent service/schema and UI/unit coverage |
| Client cannot choose tenant, learner, provider, model, confidence, retention or state | Pass | authenticated route inputs, server-owned configuration and services |
| Actual media is strictly bounded and inspected rather than trusting filename/extension | Pass for the local PCM/WAV slice | RIFF/WAVE inspector and malformed/codec/channel/rate tests |
| Private object keys contain no learner/tenant identifiers and stored bytes are application encrypted | Pass for local/testing | random object-key generation, `Crypt` encryption and content scan |
| Successful source is removed after transcript candidate commit | Pass | storage fake and lifecycle assertions |
| Provider outage/malformed output retries twice and fails recoverably | Pass | deterministic adversarial adapters and provider-run assertions |
| Due source/transcript content is purged with visible deletion evidence | Pass | scheduled `voice:purge-expired` service and expiry test |
| Transcript can be corrected/discarded without learning authority writes | Pass | lifecycle and before/after learning-table assertions |
| Recording access is exact-learner and cross-tenant safe | Pass | composite foreign keys and cross-tenant service test |
| Telemetry/database evidence excludes audio and transcript bodies | Pass for tested provider-neutral paths | ciphertext/digest and safe provider-run scans |
| API/generated clients, localization and accessible UI match | Pass | OpenAPI policy/drift, locale validation, React and axe Chromium tests |
| One live reviewed STT and TTS adapter has benchmark evidence | Blocked | ADR-0008 is accepted, but no live adapter, operational vendor evidence, approved credential or benchmark result exists |
| Production media storage/scanning/provider deletion is reviewed | Blocked | production and specialist reviews remain unauthorized |

### Commands and results

| Command or check | Result |
|---|---|
| `make verify` with pinned Linux-native toolchain | Pass |
| Composer validate/audit and pnpm frozen install/audit | Pass; no known locked vulnerability |
| Pint and PHPStan | Pass; zero formatting/static-analysis errors |
| SQLite backend suite | Pass: 49 tests, 288 assertions |
| PostgreSQL integration suite on `127.0.0.1:5432` | Pass: 173 tests, 2,016 assertions |
| Workspace tests | Pass: localization 31, design system 9, web 59 |
| Locale governance | Pass with five expected pending-independent-review warnings |
| OpenAPI/generated-client drift, ESLint and recursive TypeScript | Pass |
| `make e2e` equivalent (`pnpm run e2e`) | Pass: 10 Chromium journeys |
| `make build` | Pass: Laravel optimize/cleanup and 76-module Vite build |
| `make ai-eval` | Not run; ADR-0008 selects the benchmark adapter, but its implementation and operational evidence gates are not complete |

### Security, privacy and tenancy review

- Voice records, provider runs and deletion evidence are tenant owned and bind
  the exact learner membership. Consent additionally binds the actor user and
  its organization/membership composite identity.
- Candidate transcript and source audio are encrypted; database/provider-run
  records contain only digests and bounded technical metadata. The UI never
  puts voice bytes or credentials into browser persistent storage.
- Upload, provider invocation, review and deletion are rate limited or
  idempotent as applicable. Withdrawal deletes affected active content.
- Provider output remains advisory and learner-correctable. It cannot update
  marks, mastery, progression, review schedules or any deterministic learning
  record.
- All development services observed during verification publish only to
  `127.0.0.1`; Playwright's preview server was temporary and stopped.

### Known limitations and exit decision

The provider-neutral development slice is implemented and verified, but
PR-009 is not complete or ready for Accountable Product Owner acceptance.
ADR-0008 now selects the exact STT and TTS implementations and locked synthetic
benchmark. The current implementation has no live provider, TTS audio,
operational vendor evidence, separate spend approval, approved scanner,
isolated streaming quarantine worker, reviewed production object store,
provider deletion reconciliation, restore/tombstone drill or independent
production review. PR-010 and production remain unauthorized.

---

## DR-0011 — Accepted Azure Speech development provider

### Scope reviewed

- accepted ADR-0007 provider, consent, retention and deletion requirements;
- current official Azure Speech regions, data handling, REST operations,
  language/voice support, DPA, Product Terms and subprocessor process;
- current official Google Cloud and AWS data-use/region alternatives;
- open-weight Whisper, Kokoro and Piper licence/runtime considerations;
- a synthetic-only PR-009 quality, latency, cost and safe-failure benchmark;
  and
- credentials, callbacks, region, training, human-review and rollout controls.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Exact development STT/TTS stack approved | Pass | accepted ADR-0008 selects Azure Fast Transcription REST `2025-10-15`, `en-IN`, and real-time `en-IN-NeerjaNeural` TTS under APP-0013 |
| India-region service support | Documented, not account-verified | official Azure region documentation lists `centralindia` for core STT/TTS and states Speech data stays in the resource region |
| Provider content retention | Documented, not contract-verified | official Azure documentation states no retention for fast STT and real-time TTS with logging/customization off |
| No training/model improvement | Documented, not account-verified | official Azure Speech documentation plus mandatory configuration/evidence gate |
| Exact legal entity, DPA and subprocessors | Blocked pending Product Owner account evidence | public DPA/subprocessor process cannot identify the future subscription's contracting entity or acceptance |
| Credential | Missing by design | no secret or Azure account data was requested, created or written |
| Callback/replay boundary | Pass by proposed design | synchronous REST only; callback, webhook, redirects and URL input prohibited |
| Synthetic benchmark definition | Approved and implemented; not run live | 48 strings, two voices, three deterministic conditions, 288 STT clips, 96 TTS outputs and conjunctive thresholds |
| Real learner/accent validation | Explicitly not satisfied | synthetic round trip is circular and production remains blocked |
| Provider enabled or called | No | ordinary binding remains disabled and fail-closed preflight blocks missing operational evidence |

### Commands and results

| Command or check | Result |
|---|---|
| Current official provider/contract documentation review | Pass for proposal drafting; account-specific evidence remains blocked |
| Local Markdown final-newline and link-target validation | Pass for all five affected documentation files |
| `git diff --check` and trailing-whitespace scan | Pass |
| `make verify` with the pinned Linux-native toolchain | Pass |
| Composer and pnpm locked audits | Pass; no known vulnerability |
| Pint and PHPStan | Pass; zero errors |
| SQLite suite | Pass: 49 tests, 288 assertions |
| PostgreSQL suite | Pass: 173 tests, 2,016 assertions |
| Localization, design-system and web tests | Pass: 31, 9 and 59 tests |
| OpenAPI/generated-client, lint and type checking | Pass |
| Provider calls and `make ai-eval` | No provider call; command now exits 2 at preflight because credentials/evidence/spend approval are absent |

The first verification attempt used the host toolchain and stopped at its
Node.js mismatch. The next pinned-toolchain attempt reached the Composer audit
and stopped because the sandbox could not resolve Packagist. The complete
pinned-toolchain gate then ran with approved advisory-registry access and
passed. Neither stopped attempt is represented as a test failure or a provider
call.

`make e2e` and `make build` were not rerun because this decision package
changes no application source, API, user journey, packaging or deployment
behavior. The previously recorded PR-009 results remain unchanged.

### Decision result and implementation gate

ADR-0008 and its benchmark plan are accepted under APP-0013 at their exact
recorded SHA-256 values. DR-0011 is resolved, including explicit acceptance of
managed STT model rotation and the circular synthetic benchmark.

Only the remaining PR-009 adapter and locked benchmark implementation is
authorized. A dedicated Central India Azure subscription, exact
contract/DPA/subprocessor evidence, non-production credential and separate
provider-spend approval are still required before the first live synthetic
request. No benchmark pass, PR-009 acceptance, learner-data use, PR-010 or
production is approved.

---

## PR-009 — Azure Speech adapter and locked benchmark implementation

### Scope reviewed

- APP-0013's exact authorization boundary and accepted ADR-0008;
- Azure Fast Transcription REST `2025-10-15` and real-time TTS adapter code;
- endpoint, credential-reference, response-size, media and retry controls;
- exact corpus, deterministic transforms, WER/hallucination measurements,
  thresholds, operation/attempt/spend caps and aggregate safe report;
- fault-injected adapter and complete-run tests using Laravel HTTP fakes only;
- ordinary application binding, learner-data and deterministic-authority
  isolation; and
- root verification, localhost-only Chromium journeys, build and fail-closed
  benchmark preflight.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Ordinary application/provider path remains disabled | Pass | `AppServiceProvider` has no Azure binding; concrete adapters are constructed only by the benchmark runner |
| Exact Azure endpoint and configuration fail closed | Pass | HTTPS exact-host allowlist, `centralindia`, fixed `en-IN`, API, profanity, voice and format validation |
| Credential and evidence handling follows ADR-0008 | Pass for implemented workstation boundary | external, owner-readable mode-`0600`, non-repository credential, vendor-evidence and spend-approval files must match the configured hashes, IDs, region, disabled data-use settings, scope, cap and validity dates |
| Request/response content stays out of safe evidence | Pass for tests | generic exceptions, bounded bodies, hashed allowlisted provider metadata and aggregate-only report |
| Corpus and transforms are locked | Pass | exact 48-entry NFC manifest hash plus deterministic 24 kHz-to-16 kHz clean/noise/low-pass tests |
| Logical operation and spend boundaries are locked | Pass | 544 logical operations, 1,088 maximum attempts and `₹1,000` maximum approved cap validated before dispatch |
| Retry policy is bounded and category aware | Pass | only connection/408/429/5xx categories are retryable; policy, authentication, authorization, redirect, size and malformed failures are terminal |
| Complete runner behavior is deterministic | Pass for HTTP-fake evidence only | 544 logical operations, one injected transient retry, 545 fake HTTP attempts, 368 STT results and 176 TTS results |
| Required fault categories fail closed | Pass | 401, 403, 408, 413, 429, 500, 503, redirect, connection failure, malformed JSON, malformed WAV and oversized response |
| Learner/tenant/learning authority remains untouched | Pass | runner accepts only committed synthetic corpus and performs no database or application-binding write |
| Live benchmark evidence | Blocked | exact subscription/DPA/subprocessor evidence, credential reference and separate spend approval are absent |

### Commands and results

| Command or check | Result |
|---|---|
| Approved proposal hash verification | Pass: `b1c778753ab9b0b6b7b8202f8d106a8ee302485455189afce76baac361841b51` |
| Approved benchmark-plan hash verification | Pass: `817f430aabd93eb06d9ecd1b980994862e1ddfe19f3285fe184ba508204581ab` |
| Targeted Azure/benchmark/PcmWav tests | Pass: 24 tests, 88 assertions |
| `make verify` with pinned Linux-native toolchain | Pass |
| Composer and pnpm locked audits | Pass; no known vulnerability |
| Pint and PHPStan | Pass; zero errors |
| SQLite suite | Pass: 66 tests, 365 assertions |
| PostgreSQL suite | Pass: 173 tests, 2,016 assertions |
| Localization, design-system and web tests | Pass: 31, 9 and 59 tests |
| OpenAPI/generated-client, lint and type checking | Pass; no public API change or generated-client drift |
| First sandboxed `make e2e` attempt | Did not start tests: sandbox denied the loopback bind with `EPERM` |
| Approved loopback-only `make e2e` retry | Pass: 10 Chromium journeys; temporary `127.0.0.1:4173` process stopped |
| `make build` | Pass: Laravel optimize/cleanup and 76-module Vite build |
| `make ai-eval` | Expected blocked exit 2 before HTTP; missing operational evidence, secret reference, runner commit, pricing/scan evidence and spend approval |
| Live Azure calls | Zero |

The first full `make verify` attempt stopped at three PHPStan findings before
tests. The endpoint-array shape, redundant header check and Artisan
pending-command narrowing were corrected without suppressions; the complete
gate then passed. This stopped attempt is not represented as a test failure.

### Security, privacy and tenancy review

- Authentication headers are built only after complete preflight and never
  enter provider results, safe reports, exception messages or logs.
- Redirects are disabled; endpoint paths are fixed; filenames and request
  bodies contain no tenant, learner, organization or interaction identifier.
- Synthetic text/audio remains in process memory. The only writable artifact
  is a mode-`0600` aggregate report containing hashes, metrics and safe evidence
  identifiers.
- Azure adapters parse into project-owned results and expose no SDK or HTTP
  type to the conversation/voice domain.
- The existing learner upload/transcript path remains separately tenant
  scoped, consent gated and deterministic. This benchmark code has no learner
  query, tenant selector or learning-record write.

### Known limitations and exit decision

Repository implementation and mocked integration verification are complete.
They are not provider evidence and do not report benchmark success. No Azure
credential, exact subscription contract/DPA/subprocessor snapshot, price
snapshot, content-scan evidence, listener evidence or separately approved
spend exists in repository configuration, so `make ai-eval` correctly blocks
before HTTP.

The benchmark retains the explicitly accepted circular Azure-TTS-to-Azure-STT
bias and provider-managed STT rotation limitation. It cannot validate learner
speech, Karnataka accents, accessibility, pedagogy or production fitness.
PR-009 is not accepted; learner data, PR-010 and production remain
unauthorized.

The subsequent no-network handoff review added
`PR-009_SPEECH_BENCHMARK_EVIDENCE_HANDOFF.md` so operational reviewers have
the exact external JSON shapes, file permissions, environment mappings and
sequencing enforced by the implementation. Its values are explicit
placeholders; it creates no approval, credential, live request or benchmark
evidence.

That review also found the circular listener-evidence dependency now recorded
as DR-0012. The runner correctly makes no request without listener evidence,
but valid listener evidence must be based on the actual 96 Azure TTS outputs.
Resolving this requires a reviewed encrypted two-phase fixture bundle or a
reviewed bounded in-process playback/attestation mechanism. This is a
privacy/security workflow decision, not permission to weaken preflight.

Accepted ADR-0009 resolves the design through an encrypted
prepare/review/complete state machine, a token-protected loopback-only
browser-memory review utility, 24-hour expiry and deletion evidence. The
proposal preserves the 544-operation cap by reusing the 96 prepared outputs.
APP-0014 authorizes only this bounded DR-0012 implementation. It does not
authorize credentials, provider spend, live requests, benchmark success,
PR-009 acceptance, learner data, PR-010 or production.

---

## PR-009 — DR-0012 encrypted fixture and listener-review workflow

### Scope reviewed

- APP-0014's exact authorization boundary and accepted ADR-0009;
- authenticated per-fixture encryption and encrypted atomic resume state;
- exact 24-hour lifetime, external owner/mode/path/symlink checks and purge
  scheduling;
- prepare idempotency, partial-run refusal, completion replay prevention and
  resumed operation/attempt/spend accounting;
- two anonymous listener-disposition slots and exact external aggregate
  matching;
- the ephemeral loopback HTTP server, fragment token, authorization header,
  proxy/public-host refusal, cache/CSP headers and browser-memory playback;
- terminal deletion receipts, retry-visible failed deletion and tamper/expiry
  handling; and
- database, tenant, learner, public API, web/mobile bundle and provider-call
  isolation.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Locked operation plan survives phase split | Pass with HTTP fakes | prepare persists 96 logical TTS operations; complete restores them and performs 448 more for exactly 544 total |
| Duplicate spend/replay is prevented | Pass | prepared rerun performs zero calls; partial preparation cannot restart; completion atomically enters non-replayable state |
| Fixture confidentiality and integrity | Pass | separate authenticated application ciphertext, random opaque filenames, mode-`0600`, exact ciphertext/plaintext hashes and no persisted `RIFF` bytes |
| External path boundary | Pass | absolute owner-controlled mode-`0700` local root required outside repository, Laravel storage/public and web roots; symlinks/wrong modes fail closed |
| Listener privacy | Pass | only required booleans in two anonymous slots; no human identifier, contact detail, IP, browser value or free text |
| Loopback review boundary | Pass | hard-coded `127.0.0.1:0`, 256-bit fragment token, bearer authorization, proxy/public-host rejection, `no-store`, restrictive CSP and revoked object URLs |
| Expiry, corruption and deletion | Pass | exact 24-hour expiry, corrupt ciphertext rejection, hourly/manual purge, visible failed-delete receipts and immutable successful receipt |
| Completion binding | Pass | exact configuration/evidence hashes, corpus, plan, ADR hash, runner revision/commit, transform, dependency locks, fixture plan and counters revalidated before HTTP |
| Tenancy and deterministic authority | Pass | test listener observed zero database queries; workflow has no organization, learner, curriculum, marks, mastery, progression or review-schedule path |
| Public application surface | Pass | no route/OpenAPI/generated-client/web/mobile change; review utility exists only behind the benchmark command |
| Live provider evidence | Blocked | no credential, provider-spend approval, live-request authorization, listener evidence or Azure result exists |

### Commands and results

| Command or check | Result |
|---|---|
| ADR-0009 proposal hash verification | Pass: `a0848d462f9363cc2fb9a2babd2f50fd8ae7ad4ed99ee2cd3abaaeb828413e0f` |
| Targeted Azure/benchmark tests | Pass: 21 tests, 413 assertions; Laravel HTTP fakes only |
| Targeted Pint and PHPStan level 8 | Pass; zero errors |
| `make verify` with pinned Linux-native toolchain | Pass |
| Composer and pnpm locked audits | Pass; no known vulnerability |
| SQLite suite | Pass: 70 tests, 701 assertions |
| PostgreSQL suite | Pass: 173 tests, 2,016 assertions |
| Localization, design-system and web tests | Pass: 31, 9 and 59 tests |
| OpenAPI/generated-client, lint and recursive type checking | Pass |
| `make build` | Pass: Laravel optimize/cleanup and 76-module Vite build |
| `make e2e` | Pass: 10 Chromium journeys; temporary `127.0.0.1:4173` server stopped |
| `make ai-eval` | Expected blocked exit 2 before HTTP because no opaque run ID or live authorization exists |
| Live Azure calls | Zero |

The first full verification attempt used the workstation-default Node.js and
stopped at the declared-toolchain check. The first pinned attempt stopped when
the sandbox could not resolve the Composer advisory service. The pinned retry
with approved advisory-registry access passed. Neither stopped attempt is
represented as a product test failure or provider call.

### Security, privacy and tenancy review

- Plaintext fixture audio exists only in bounded provider/process memory and
  one authenticated loopback response/browser blob at a time.
- The encrypted state contains opaque fixture mapping and counters; no
  transcript, SSML, credential, evidence document, learner/tenant value or
  human reviewer identifier is written.
- Successful or ordinary failed completion deletes the bundle. A crash leaves
  encrypted content for the scheduled/manual purge, and a deletion failure
  remains visible and blocks acceptance.
- Revoked or changed provider, evidence, pricing, credential-version,
  configuration, code or dependency bindings block completion before a new
  provider request; the terminal complete path purges the bundle.
- There is no migration, database record, public endpoint, ordinary provider
  binding or change to deterministic learning authority.

### Known limitations and exit decision

The DR-0012 implementation is complete and repository-verified, but no
preparation or completion phase was run against Azure. The browser utility has
automated contract/security coverage and hard-coded loopback binding; the two
accountable listener sessions, actual audio-device behavior and independent
accessibility/security/privacy review remain human gates.

PR-009 is not accepted. Exact subscription/contract/DPA/subprocessor evidence,
a dedicated credential, reviewed price/content-scan evidence, separate
provider-spend approval, explicit live-request authorization, two accountable
listener reviews and the locked live result remain outstanding. Learner data,
PR-010 and production remain unauthorized.

---

## Demo Phase 1 dashboard shell (D-0081)

### Scope reviewed

- demo-only Phase 1 module navigation and synthetic fixtures for Attendance,
  AI Teacher, Daily Activity, My Levels and Notice Board;
- ordinary product shell unchanged when `demoMode` is false;
- no cloud AI/speech calls; scripted AI Teacher replies only;
- locale parity for new demo.phase1.* keys.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Five modules reachable from dashboard | Pass | DemoPhase1Shell unit + demo e2e |
| Levels list Pre-1/Pre-2/1–17 with rooms | Pass | fixtures + UI |
| Daily quiz 5×1 mark | Pass | in-memory scoring |
| Non-demo shell unchanged | Pass | `hidePrimaryNavigation` only when demoMode |
| No API/provider calls | Pass | demo e2e zero `/api/` |

---

## Professional client setup path (Phase A + Phase B scaffold)

### Scope reviewed

- Phase A client runbook: HTTPS tunnel preference, APK share, disclosure script
  and walkthrough ([CLIENT_DEMO_RUNBOOK.md](CLIENT_DEMO_RUNBOOK.md));
- Phase B outside-repo evidence workspace and env path map
  ([PROFESSIONAL_CLIENT_SETUP.md](PROFESSIONAL_CLIENT_SETUP.md));
- fail-closed `speech:benchmark --phase=preflight` with incomplete evidence;
- live-prepare script refusal without APO authorization file / secret.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Presentation path documented | Pass | HTTPS-first runbook + disclosure + 8-minute walkthrough |
| Secrets stay outside Git | Pass | `$HOME/ge-speech-benchmark` mode 0700/0600; empty secret file |
| Ordinary app remains fail-closed | Pass | docs require `VOICE_PROVIDER_DRIVER=disabled` and live flags false in app `.env` |
| Preflight blocks without evidence | Pass | `scripts/run-speech-benchmark-preflight.sh` → `"status":"blocked"` exit 2 |
| Live prepare blocked without APO text | Pass | `scripts/run-speech-benchmark-live-prepare.sh` exit 2; missing `LIVE_AUTHORIZATION.txt` |
| No Azure network call made | Pass | preflight/live-prepare gates only; no prepare/complete executed |

### Known limitation

Live Azure prepare/complete/`make ai-eval` remain unauthorized until the
operator supplies centralindia endpoint, secret, real evidence JSON and the
exact APO authorization sentence.

---

## APP-0015 / D-0082 — Blanket demo approval and ₹1,000 spend cap

### Scope reviewed

- APO exact statement recorded as [APP-0015](APPROVALS.md#app-0015);
- outside-repo `LIVE_AUTHORIZATION.txt` and `spend-approval.json` (`cap_inr` 1000);
- decision ledger D-0082; STATUS professional-client row updated.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Spend authorization text present | Pass | `$HOME/ge-speech-benchmark/LIVE_AUTHORIZATION.txt` |
| Spend cap file written | Pass | `evidence/spend-approval.json` approved, cap 1000 |
| Production not claimed | Pass | APP-0015 conditions; D-0082 limitations |
| No live Azure call made | Pass | secret file still empty; vendor evidence still templates only |

### Known limitation

Credential, vendor/DPA evidence and filled `speech-benchmark.env` remain
operator-supplied. This approval does not fabricate those artifacts.

---

## Local synthetic presentation demo

### Scope reviewed

- development/distribution-only activation and production refusal;
- in-memory learner identity, lesson, deterministic result/progress and Friend
  adapters;
- explicit synthetic-data and no-real-provider disclosure;
- on-device browser Web Speech as fallback; local Vite demo may use Azure
  neural TTS via `/demo/tts` when credentials are configured (D-0092);
- professional demo-scoped presentation surface;
- microphone-denial fallback and typed practice;
- localization parity/governance for demo speech/UI copy; and
- network, persistence, accessibility and existing-test regression.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Normal runtime remains unchanged | Pass | demo adapters are selected only when the explicit flag and Vite `DEV`/demo mode are true |
| Production cannot activate the demo | Pass | runtime-policy unit test denies an explicit request when `development=false` and `distribution=false` |
| Useful lesson is visible immediately | Pass | demo defaults to Home with a populated introduction mission, model text, browser TTS model speech, recording fallback, check and result |
| Conversation is clearly synthetic | Pass | deterministic Friend disclosure, rotating replies, browser TTS/STT presentation helpers, five status events and zero cost |
| No authoritative data is created | Pass | adapters are browser-memory objects; no database, API mutation, publication, enrollment or mastery authority path exists |
| No ordinary application API call occurs | Pass | Chromium journey observes zero `/api/` requests; Azure TTS (when configured) uses only local `POST /demo/tts` middleware, never ordinary Laravel routes |
| Browser persistence remains bounded | Pass | Chromium journey observes zero `localStorage` entries; demo state resets on reload |
| Accessibility | Pass | complete demo lesson/result/Friend journey has zero automated axe violations |
| Language governance | Pass with expected warnings | six catalogues have exact parity/hashes; Kannada, Malayalam, Tamil, Telugu and Hindi notices remain pending independent review |

### Commands and results

| Command or check | Result |
|---|---|
| `make verify` with the pinned Linux-native toolchain | Pass: Composer/pnpm audits, formatting, PHPStan, 70 SQLite tests/701 assertions, 173 PostgreSQL tests/2,016 assertions, locale/OpenAPI/generated-client checks, lint, TypeScript, 31 localization tests, 9 design-system tests and 72 web tests |
| `make demo-e2e` | Pass: one synthetic journey on temporary `127.0.0.1:4174` including browser TTS playback controls; server stopped |
| `make demo-dist-e2e` | Pass: dist-demo rebuild and Chromium journey on `127.0.0.1:4175` |
| `make demo-android` | Pass: debug APK rebuilt with the updated demo bundle |
| API/provider requests during browser journey | Zero |

### Security, privacy and limitations

- The demo has no real identity, phone number, credential, learner content,
  provider configuration, transcript persistence or tenant data.
- Microphone capture, when a presenter chooses it, stays under the existing
  local browser-memory practice controls and is not uploaded by the demo.
- Model speech and Friend replies use the browser Web Speech APIs when
  available. This is device speech for presentation only, not Azure/cloud
  STT/TTS, not licensed media and not PR-009 acceptance evidence.
- The demo is not evidence that the backend, database, OTP, offline sync,
  secure upload or Azure provider paths are operating. Their existing
  verification and authorization gates remain unchanged.
- Human language, accessibility/device and production reviews remain required.
  PR-009 acceptance, learner data, PR-010 and production remain unauthorized.

## Synthetic demo distribution — web bundle and Android demo APK

### Scope reviewed

- the explicit `demo` build mode boundary against the ordinary production
  build (D-0077);
- demo-runtime enrichment: Home opens on the recommended mission, a demo-only
  Progress destination, rotating deterministic Friend replies and a curated
  set of gentle pattern tips;
- storage-free behaviour of the distribution (no service worker, no
  `localStorage`, no API/provider requests);
- the Capacitor 8 Android wrapper (D-0078) and its permission surface; and
- dependency, lint and regression impact on the existing verified suites.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Ordinary production build cannot activate the demo | Pass | runtime-policy unit test denies `development=false, distribution=false`; configuration test proves production mode builds to `dist/` while only the explicit demo mode builds `dist-demo/` |
| Demo distribution activates only through the dedicated script | Pass | `build:demo` alone sets `VITE_LOCAL_DEMO=true` with `--mode demo`; no `.env` file or default mode carries the flag |
| Built artifact runs the full demo journey | Pass | Chromium journey against `vite preview` of `dist-demo`: Home mission, lesson with microphone-denial fallback, check, result, progress objectives and two Friend turns |
| No storage or network leaves the device | Pass | journey observes zero `/api/` requests, zero `localStorage` entries and zero service-worker registrations |
| Friend replies stay deterministic and advisory | Pass | unit tests pin the four-reply rotation and the four curated pattern tips; replies remain presentation text with no learning-record path |
| Accessibility | Pass | zero automated axe violations across the extended journey |
| Android APK identity and permissions | Pass | `aapt2 dump badging`: `com.globalenglish.demo`, `Global English Demo`, minSdk 24, targetSdk 36, `INTERNET`/`RECORD_AUDIO`/`MODIFY_AUDIO_SETTINGS` only, demo assets bundled |
| Dependency audit | Pass | `uuid@<11.1.1` override patches the moderate advisory in the dev-only Capacitor CLI path; `pnpm audit` reports no known vulnerabilities |
| Existing suites unchanged | Pass | full `make verify`, `make e2e` and `make build` re-run green after the change |

### Commands and results

| Command or check | Result |
|---|---|
| `make verify` with the pinned Linux-native toolchain | Pass: Composer/pnpm audits, formatting, PHPStan, 70 SQLite tests/701 assertions, 173 PostgreSQL tests/2,016 assertions, locale/OpenAPI/generated-client checks, lint, TypeScript, 31 localization tests, 9 design-system tests and 68 web tests |
| `make e2e` | Pass: 10 existing Chromium journeys |
| `make build` | Pass: Laravel production optimize/cleanup and strict production Vite build |
| `make demo-e2e` | Pass: extended synthetic journey on the development server |
| `make demo-dist-e2e` | Pass: the same journey against the built `dist-demo` artifact (three consecutive runs during stabilization) |
| `make demo-android` | Pass: Capacitor sync plus Gradle `assembleDebug` producing `app-debug.apk` (~4.9 MB) with JDK 21, AGP 8.13.0, Gradle 8.14.3 |

### Security, privacy and limitations

- The demo distribution and APK contain no credential, identity, provider
  configuration, tenant data or learner content; recordings stay in volatile
  browser memory for local playback/retry.
- `com.globalenglish.demo` is a placeholder demo identity; the client-owned
  production package identity, Passport PKCE authentication, native secure
  storage and Play release engineering remain PR-014 scope.
- The APK is debug-signed presentation packaging. It has not been executed
  on a device or emulator in this session (no `/dev/kvm` access); an
  on-device pass on representative hardware remains outstanding evidence.
- Gentle pattern tips are scripted demo content and must not be represented
  as grammar analysis, correction authority or AI capability.
- The demo distribution is not curriculum approval, benchmark evidence,
  checkpoint acceptance or production authorization; every existing PR-009
  and later gate is unchanged.


---

## Live Azure complete (APP-0015 / 2026-08-05)

### Scope reviewed

- APO instruction to mark all listener dispositions good;
- two development listener slots recorded 96/96 acceptances;
- `make ai-eval` complete phase executed against centralindia Speech;
- safe aggregate report written outside Git.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Listener all-pass recorded | Pass | aggregate 96/96/0 critical |
| Live complete executed | Pass (ran) | safe-report.json; ~₹13.22 estimated |
| Quality gate | Fail | clean/snr WER above locked thresholds; numbers category ~0.37–0.42 |
| Completeness gate | Fail | snr20/snr10 clip_count 94 (duration mismatch failures: 4) |
| Cost / listener / performance / reliability | Pass | report gates |
| PR-009 acceptance / production | Not authorized | limitations in report |

### Known limitation

Development listeners under APO instruction are not independent language review.
Failing WER does not authorize relaxing locked thresholds without a new decision.


---

## APP-0016 / D-0084 / D-0085 — Production-path authority and PR-009 remediation

### Scope reviewed

- APP-0016 blanket production-path authorization recorded with limitations;
- WER scoring normalization for spoken/digit/currency equivalence (D-0085);
- STT duration over-claim-only check;
- live prepare/review/complete re-run under ₹1,000 cap.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Number-form WER unit cases | Pass | SpeechBenchmarkPolicyTest number equivalence cases |
| Targeted PHPUnit speech suite | Pass | 22 tests |
| Live complete remediation | Pass | `make ai-eval` status passed; run `e1dc9d3c8daff9e33c20bdd00d2dd4d0` |
| All conjunctive gates | Pass | safe-report gates all true; clean WER ~2.6% |
| Cost under cap | Pass | ~₹13.21 estimated; cap ₹1,000 |
| Production/Play claimed | No | STATUS limitations; APP-0016 conditions |

### Known limitation

Formal APO PR-009 checkpoint acceptance and PR-010 authorization remain separate
human steps. Independent specialist reviews remain mandatory before production.


---

## APP-0017 — PR-009 acceptance and PR-010 authorization

### Scope reviewed

- APO exact statement accepting PR-009 and authorizing PR-010;
- prior live synthetic benchmark pass `e1dc9d3c8daff9e33c20bdd00d2dd4d0`;
- STATUS/DECISIONS updated to open only PR-010.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Exact statement recorded | Pass | [APP-0017](APPROVALS.md#app-0017) |
| PR-009 closed with limitations | Pass | APP-0017 conditions; D-0086 |
| Only PR-010 authorized | Pass | STATUS next checkpoint; no PR-011/production |
| Production/Play claimed | No | APP-0017 limitations |

### Known limitation

PR-009 remediation and related demo-path files may still be uncommitted at
record authoring; the evidence repository revision is the commit that contains
this APP-0017 record.


---

## PR-010 — AI orchestration, correction and Tutor beta

### Scope reviewed

- context allowlist, versioned prompts, structured validators, curated fallback;
- Gentle/Tutor/Fluency modes;
- separate priority correction API field and UI card with Kannada explanation;
- advisory correction digests and error-pattern counters;
- learning-record isolation tests;
- OpenAPI/contract regeneration and localization catalogue updates.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Output validator unit tests | Pass | ConversationOutputValidatorTest |
| Request validation accepts tutor mode | Pass | ConversationRequestValidationTest |
| PostgreSQL conversation + orchestration suite | Pass | 6 tests / 70 assertions via Docker PHP 8.4 + pdo_pgsql |
| SQLite unit/feature conversation filter | Pass | 5 tests / 17 assertions |
| OpenAPI lint + contract drift check | Pass | `pnpm run openapi:check` |
| PHPStan on PR-010 conversation paths | Pass | 0 errors |
| Web conversation/demo tests | Pass | 76 web tests in targeted vitest run |
| Locale governance | Pass | `pnpm run locale:check` (5 pending-review warnings) |
| Learning records unchanged by AI path | Pass | orchestration + fake-provider isolation assertions |
| Correction separate from Friend reply | Pass | API field + UI card test |
| Real model provider / production / Play | Not authorized | APP-0017; STATUS |

### Security, privacy and tenancy

- Persistent correction records store digests, not reply/correction text.
- Allowlisted model context excludes personal identifiers and learning authority.
- Organization scoping of correction/error-pattern rows follows conversation turn ownership.
- Fake adapters remain local/testing-only; production conversation providers stay fail-closed.

### Known limitations

- Host PHP lacks `pdo_pgsql`; PostgreSQL integration evidence used a Docker
  `php:8.4-cli` container against the local Compose Postgres service.
- Full `make verify` / remote CI was not completed in this session.
- No real LLM provider is wired; Tutor beta uses deterministic fake outputs.
- Kannada and preview-locale UI copy remain pending independent review.


---

## APP-0018 — PR-010 acceptance

### Scope reviewed

- APO statement accepting PR-010 with recorded limitations;
- exact implementation commit `13eb5ac56f04402a2571346e9fd8e24f1a05ea80`;
- STATUS/DECISIONS updated without authorizing PR-011.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Exact statement recorded | Pass | [APP-0018](APPROVALS.md#app-0018) |
| Exact commit cited | Pass | `13eb5ac56f04402a2571346e9fd8e24f1a05ea80` |
| PR-010 closed with limitations | Pass | APP-0018 conditions; D-0088 |
| PR-011 authorized | No | APP-0018 limitations; STATUS next checkpoint |
| Production/Play claimed | No | APP-0018 limitations |

### Known limitation

This acceptance does not waive independent specialist reviews or full
CI/`make verify` follow-up.


---

## APP-0019 — PR-011 implementation authorization

### Scope reviewed

- APO statement `APO accept PR-011.`;
- interpretation as authorization to begin unimplemented PR-011 only;
- STATUS/DECISIONS updated without accepting completed PR-011 work.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Exact statement recorded | Pass | [APP-0019](APPROVALS.md#app-0019) |
| Interpreted as begin-implementation only | Pass | APP-0019 context; no PR-011 artifact exists |
| Only PR-011 authorized | Pass | STATUS next checkpoint; D-0089 |
| PR-012 / production / Play claimed | No | APP-0019 limitations |

### Known limitation

PR-011 deliverables remain unimplemented until a bounded checkpoint
implementation is completed and separately accepted.


---

## PR-011 — Evaluation harness and memory controls

### Scope reviewed

- learner-visible AI memory list/create/edit/delete + audit;
- allowlisted memory in conversation orchestration;
- locked deterministic AI evaluation suite, human-review export/import with
  fail-closed suite binding, prompt comparison and cost rollup;
- OpenAPI 0.8.0 memory routes and Practice UI panel;
- [PR-011 verification scope](PR-011_EVALUATION_MEMORY_VERIFICATION_SCOPE.md).

### Review result

| Check | Result | Evidence |
|---|---|---|
| Memory isolation / learning freeze | Pass | LearnerMemoryTest (PostgreSQL via Docker PHP 8.4.24 + pdo_pgsql) |
| AI evaluation suite pass | Pass | AiEvaluationRunnerTest |
| Human-review import accept/pending/reject + fail-closed mismatch | Pass | AiEvaluationRunnerTest import cases |
| Suite hash stability | Pass | AiEvaluationSuiteTest (host `php artisan test`) |
| HTTP auth + orchestration + memory/eval filter | Pass | 13 PostgreSQL tests / 204 assertions via Docker PHP 8.4.24 (`AiEvaluationRunnerTest\|LearnerMemoryTest\|ConversationOrchestrationTest\|LearnerHttpBoundaryTest`) |
| OpenAPI / contracts | Pass | `pnpm run openapi:check` on host Node v22.22.1 / pnpm 11.18.0 |
| PHPStan PR-011 paths | Pass | 0 errors (`app/Conversation/Evaluation`, `Memory`, `LearnerMemoryController`) |
| Web memory panel list/create/edit/delete | Pass | AiMemoryPanel vitest 1 passed |
| Locale governance | Pass | `node scripts/validate-locales.mjs` in `node:24.18.1-bookworm-slim` — 6 catalogues, 5 pending-review warnings |
| `php artisan ai:evaluate` | Pass | Docker PHP 8.4.24 against `global_english_test` after migrate/seed: status `completed`, 4 passed / 0 failed, suite_hash `9d1f8d6069af09abbc9c20dd5048af6c640a4c77c927f0f43eed3364c13dc5e6`, report_digest `407d67cc39091c5703a291c042da3ba7bb14b1f1f67f236b70a305ab883cd318`, estimated_cost_microunits 0; speech half skipped (no `SPEECH_BENCHMARK_RUN_ID`) |
| Full host `make verify` / remote CI | Not claimed | Host PHP 8.5.4 lacks `pdo_pgsql`; Node 22 cannot load locale `.ts` imports without pinned Node 24 |
| Real LLM / PR-012 / production / Play | Not authorized | APP-0019; STATUS |

### Re-verification — 14 August 2026

| Check | Result | Evidence |
|---|---|---|
| LearnerMemoryTest + AiEvaluationRunnerTest | Pass | postgres phpunit: 11 tests / 65 assertions |
| AiMemoryPanel vitest | Pass | 2 passed |
| `artisan ai:evaluate` (fake) | Pass | `passed_count=15`, `failed_count=0`, `estimated_cost_microunits=0`, suite_hash `5bf9a508d1aeaf3a329212e60cf957cc28404fabcf0d34f4f65ebb6e99223270` |
| STATUS closed-checkpoint note | Pass | `docs/STATUS.md` restored PR-011 / APP-0020 closed entry without changing current APP-0061 checkpoint |
| LearnerHttpBoundaryTest full class | Not claimed | 1 passed / 2 failed on unrelated conversation turn auth (401); memory routes remain covered by LearnerMemoryTest |

### Subsequent checkpoint acceptance

[APP-0020](APPROVALS.md#app-0020) records Accountable Product Owner acceptance
of PR-011 at exact commit `220628f4d4c69284af0b1f2534e8902f3b272088` with
every recorded limitation in this section and
[PR-011_APO_ACCEPTANCE_PACKAGE.md](PR-011_APO_ACCEPTANCE_PACKAGE.md). PR-012
implementation remains unauthorized until a separate APP.

### Security, privacy and tenancy

- Memory list returns all active facts for the owning membership; no hidden active facts.
- Practice UI edit uses the existing PATCH contract; no hidden active facts.
- Audit events store digests, not fact plaintext after delete/update trails.
- Eval fixtures are synthetic; reports are aggregate digests with zero fake cost.
- Import packages must match the exact suite key and hash; unknown cases,
  missing files and malformed dispositions fail closed.
- Organization + membership FKs and same-tenant denial mirror conversation patterns.

### Known limitations

- Host PHP 8.5.4 lacks `pdo_pgsql`; PostgreSQL evidence used Docker PHP 8.4.24
  against local Compose Postgres.
- Host Node v22.22.1 cannot load locale `.ts` imports; locale governance was
  verified in `node:24.18.1-bookworm-slim`.
- Full host `make verify` / remote CI were not claimed green in this session.
- Preview-locale memory strings may reuse English pending independent review.
- Eval cost rollup remains zero on deterministic fakes and is not live spend.
- Speech half of `make ai-eval` remains PR-009-gated and was not run.
- PR-011 is accepted under APP-0020 at
  `220628f4d4c69284af0b1f2534e8902f3b272088`; PR-012 remains unauthorized.

## APP-0021 — Confidence Conversation enterprise slice authorization

### Evidence reviewed

- Exact APO statement authorizing only
  [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) Steps A1–A7;
- Prior PR-011 acceptance under APP-0020;
- Learning-flow design
  [STUDENT_AI_CONVERSATION_LEARNING_FLOW.md](STUDENT_AI_CONVERSATION_LEARNING_FLOW.md);
- Explicit non-authorization of PR-012, production, Play, and nested A4/A5
  spend until separate gates;
- Explicit statement that demo D-0092/D-0094 paths are not enterprise evidence.

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Exact APO authorize sentence recorded | Pass | [APP-0021](APPROVALS.md#app-0021) |
| Begin-implementation only (not A7 accept) | Pass | APP-0021 conditions |
| PR-012 / production / Play still unauthorized | Pass | APP-0021; STATUS next gate |
| Demo paths non-evidence | Pass | D-0095; APP-0021; plan §0 |
| Nested A4/A5 still gated | Pass | APP-0021 authorized scope |

### Outcome

A0 is complete. Implementation may proceed through A1 (spec + verification
scope) under APP-0021 without treating demo adapters as production evidence.

## Confidence Conversation — Step A1 (spec + verification scope)

### Evidence reviewed

- [CONFIDENCE_CONVERSATION_SPEC.md](CONFIDENCE_CONVERSATION_SPEC.md)
- [CONFIDENCE_CONVERSATION_VERIFICATION_SCOPE.md](CONFIDENCE_CONVERSATION_VERIFICATION_SCOPE.md)
- Existing PR-010 `PriorityCorrection` still uses `explanation_kn` only —
  A2 must generalize to `explanation_l1` for all UI locales
- OpenAPI draft deltas listed in verification scope (not yet generated)

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Spec committed under APP-0021 | Pass | SPEC file |
| Verification scope committed | Pass | VERIFICATION_SCOPE file |
| Demo excluded as evidence | Pass | verification §10 |
| Nested A4/A5 still gated | Pass | SPEC §8; APP-0021 |
| Plan Active step → A2 | Pass | ENTERPRISE_STUDENT_PATH_PLAN |

### Outcome

A1 complete. Next: A2 structured turn contract on fakes (no real LLM).
## Confidence Conversation — Step A2 (structured turn contract)

### Evidence reviewed

- `PriorityCorrection` now emits `needed`, `explanation_l1`, `explanation_locale`;
  deprecated `explanation_kn` dual-write only for `kn-IN`
- Session start requires `ui_locale`; persisted on `conversation_sessions`
- Fake provider fixtures for all six UI locales; shame-fixture → curated fallback
- OpenAPI 0.9.0 + regenerated contracts
- Migration `2026_08_07_000040_confidence_conversation_explanation_l1.php`

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| CC-03 empty L1 rejected when needed | Pass | ConversationOutputValidatorTest |
| CC-04 six UI locale fixtures | Pass | ConversationOutputValidatorTest::test_fake_provider_covers_all_ui_locales |
| Shame-language fail-closed | Pass | Validator + shame-fixture fake |
| OpenAPI / contracts | Pass | `pnpm run openapi:check` |
| PHPStan changed paths | Pass | 0 errors |
| PostgreSQL integration filter | Pass | Docker PHP 8.4.24: 19 tests / 281 assertions |
| Host unit/feature conversation | Pass | 6 tests / 55 assertions |
| Web conversation + demo fixtures | Pass | ConversationJourney / Web API / localDemo vitest |
| Real LLM / A4–A5 / PR-012 / production | Not authorized | APP-0021; D-0096 |

### Known limitations

- Prompt catalogue text still mentions “Kannada explanation” (eval suite hash
  unchanged); A6 may revise prompts with locked suite update.
- Host PHP lacks `pdo_pgsql`; PostgreSQL evidence used Docker PHP 8.4.24.
- Full host `make verify` / remote CI not claimed in this step.
- A3 UI stages (warm-up / listen / reflect) not yet implemented.

### Outcome

A2 complete. Next: A3 product UI vertical slice on fakes.

## Confidence Conversation — Step A3 (product UI vertical slice)

### Evidence reviewed

- Practice `ConversationJourney` stages: Warm-up → Listen → Talk → Reflect
- Confidence controls: retry, type instead, skip, hint; silence nudge after 8s
- Correction card uses `explanation_l1` / `explanation_locale`
- UI catalogues revision `ui-*-2026-08-07.9` with confidence copy
- Product Listen path discloses text-only fake TTS (A5 still gated)

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Stage journey unit coverage | Pass | ConversationJourney vitest 3 passed |
| Confidence controls present | Pass | Say that again / Type / Skip / Hint |
| Locale governance | Pass | validate-locales: 6 catalogues, 5 pending-review warnings |
| Demo excluded as enterprise evidence | Pass | D-0097; demoMode TTS optional only |
| Real LLM / A4–A5 / PR-012 / production | Not authorized | APP-0021 |

### Known limitations

- Listen model lines are synthetic client constants (not published curriculum).
- Product-path model/reply audio remains text-only until A5.
- Reflect summary is advisory client copy; does not write mastery.
- Full `make e2e` / host `make verify` not claimed in this step.

### Outcome

A3 complete. Next: A4 conversation-model provider ADR (no live DI without nested APP).

## Confidence Conversation — Step A4 (ADR proposed)

### Evidence reviewed

- Proposed [ADR-0010](adr/0010-conversation-model-provider-policy.md)
- Authorization draft [A4_CONVERSATION_MODEL_AUTHORIZATION_REQUEST.md](A4_CONVERSATION_MODEL_AUTHORIZATION_REQUEST.md)
- Explicit separation from demo OpenRouter (D-0094) and Speech ADR-0008
- Fail-closed production default retained

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| ADR drafted for provider choice + gates | Pass | ADR-0010 proposed |
| Nested APP required before adapter code | Pass | A4 auth request |
| Live spend unauthorized | Pass | ADR + A4 request non-goals |
| Demo path non-evidence | Pass | D-0094; ADR alternatives |

### Outcome

A4 ADR proposed. Adapter implementation blocked until ADR acceptance and nested
APO authorize statement are recorded.

## Confidence Conversation — Step A4 (accepted + adapter)

### Evidence reviewed

- Exact ADR acceptance: `Accept ADR-0010 conversation-model provider policy as proposed.`
- APO live-spend intent: production closeness with live spends
- [APP-0022](APPROVALS.md#app-0022) (₹500 INR hard development ceiling)
- Azure OpenAI adapter behind `ConversationModelProvider` with HTTP-fake tests
- Outside-repo workspace scripts under `scripts/setup-conversation-model-workspace.sh`
- Production / non-allowlisted env remain fail-closed

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| ADR-0010 accepted | Pass | APP-0022 |
| Adapter uses project-owned port only | Pass | `AzureOpenAiConversationModelProvider` |
| HTTP fakes map to `explanation_l1` | Pass | `AzureOpenAiConversationAdapterTest` |
| Production cannot authorize live | Pass | `environment_not_allowlisted` |
| Spend capped; unlimited unauthorized | Pass | APP-0022; config `maximum_spend_inr=500` |
| Demo OpenRouter not DI | Pass | D-0094; driver allowlist |
| Production promote / Play | Not authorized | APP-0022 non-goals |
| Operator live smoke | Pending evidence | Outside-repo workspace |

### Outcome

A4 complete for code + governance. Next: A5 multi-locale guidance TTS policy
(nested ADR/APP; no TTS spend under APP-0022).

## Confidence Conversation — Step A5 (in progress)

### Evidence reviewed

- Proposed [ADR-0011](adr/0011-confidence-conversation-guidance-tts-policy.md)
- [APP-0023](APPROVALS.md#app-0023) begin-implementation (fakes / text-first)
- [A5_GUIDANCE_TTS_AUTHORIZATION_REQUEST.md](A5_GUIDANCE_TTS_AUTHORIZATION_REQUEST.md)
- `GuidanceTtsPolicy` + web `guidanceTtsPolicy` voice map
- Product Listen/reply text-first with optional on-device English browser speech
- Locale catalogues bumped to `ui-*-2026-08-07.10`
- APP-0022 spend ceiling amended to ₹500

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| ADR-0011 drafted | Pass | proposed |
| APO begin A5 (fakes/text-first) | Pass | APP-0023 |
| Human lesson recordings forbidden | Pass | GuidanceTtsPolicy |
| Text-first fallback required | Pass | policy + UI copy |
| PR-009 en-IN benchmark unchanged | Pass | ADR-0011 decision table |
| Live multi-locale TTS spend | Not authorized | APP-0023 / ADR-0011 |
| ADR-0011 accepted | Pending | needs exact Accept statement |

### Outcome

A5 scaffolding in progress. Close A5 only after exact ADR-0011 acceptance.

## Confidence Conversation — Step A5 (accepted)

### Evidence reviewed

- Exact ADR acceptance: `Accept ADR-0011 Confidence Conversation guidance TTS policy as proposed.`
- Exact APO authorize: fakes and text-first fallback; live multi-locale TTS spend unauthorized
- [APP-0024](APPROVALS.md#app-0024)
- Prior scaffolding under [APP-0023](APPROVALS.md#app-0023)
- `GuidanceTtsPolicy` + fake TTS contract + product text-first UI

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| ADR-0011 accepted | Pass | APP-0024 |
| AI-only / no human lesson recordings | Pass | GuidanceTtsPolicy; ADR-0011 |
| Text-first fallback | Pass | UI + policy |
| Fakes only for multi-locale guidance | Pass | APP-0024 |
| PR-009 en-IN benchmark unchanged | Pass | ADR-0011 |
| Live multi-locale TTS spend | Not authorized | APP-0024 non-goals |
| Production / Play | Not authorized | APP-0024 |

### Outcome

A5 complete. Next: A6 locale eval fixtures + `make ai-eval` gates for L1
explanations.

## Confidence Conversation — Step A6 (accepted)

### Evidence reviewed

- Suite `friend.l1.locale.v1` / version `1`
- Locked `suite_hash` `5bf9a508d1aeaf3a329212e60cf957cc28404fabcf0d34f4f65ebb6e99223270`
- 15 cases: gentle × 6 UI locales, tutor/fluency/unsafe, shame × 6 locales
- Metrics: false_correction_count, missing_l1_count, shame_language_hits,
  schema_invalid_count, cost_ceiling_ok
- Human-review export includes `ui_locale` + `explanation_locale`
- Docker PHP 8.4.24 `AiEvaluationRunnerTest` 6/6; `ai:evaluate` completed
  15/0, report_digest `355b4d0424fab059209d97fdd863e738a703e9a1bd6437069981fdadc42b84ca`

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| CC-E1 locked suite hash; fake-only | Pass | suite_hash; APP_ENV/driver gate |
| CC-E2 false-correction / missing-L1 fail suite | Pass | metrics + case gates |
| CC-E3 shame-language fail closed | Pass | 6 shame cases → fallback |
| CC-E4 cost zero on fakes | Pass | estimated_cost_microunits 0 |
| CC-E5 make ai-eval fails on regression | Pass | status failed if any gate fails |
| All Phase 1 UI locales covered | Pass | ui_locales === ConversationUiLocales::all() |
| Live multi-locale TTS spend | Not authorized | APP-0024 |
| Live model eval | APP-0022 only | spend/evidence gates |

### Outcome

A6 complete. Next: A7 APO exact-commit acceptance of the Confidence Conversation
slice.

## Confidence Conversation — Step A7 (accepted)

### Evidence reviewed

- Exact APO statement: `APO accept Confidence Conversation slice at 5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b with its recorded limitations`
- [APP-0025](APPROVALS.md#app-0025) / D-0103
- [CONFIDENCE_CONVERSATION_APO_ACCEPTANCE_PACKAGE.md](CONFIDENCE_CONVERSATION_APO_ACCEPTANCE_PACKAGE.md)
- A1–A6 QUALITY_REVIEW sections; APP-0021 / APP-0022 / APP-0024; ADR-0010 / ADR-0011

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Acceptance package | Pass | CONFIDENCE_CONVERSATION_APO_ACCEPTANCE_PACKAGE.md |
| Limitations explicit | Pass | package § Recorded limitations |
| Exact commit SHA | Pass | `5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b` |
| APO accept statement | Pass | APP-0025 |
| PR-012 / production / Play | Not authorized | APP-0025 non-goals |
| Live multi-locale TTS spend | Not authorized | APP-0024 / APP-0025 |

### Outcome

A7 complete. Confidence Conversation enterprise slice accepted. Next: separate
APP for PR-012 Role-play (plan B0) only — do not start PR-012 code without it.

## PR-012 — Step B0 (authorization request ready)

### Evidence reviewed

- [PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md](PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md)
- [PR-012_ROLEPLAY_VERIFICATION_SCOPE.md](PR-012_ROLEPLAY_VERIFICATION_SCOPE.md)
- Preconditions: APP-0020 (PR-011) + APP-0025 (Confidence Conversation)
- Scope: synthetic Observe→Rehearse→Perform→Reflect; advisory objectives;
  fake providers default; no live TTS spend expansion

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Auth request refreshed post–APP-0025 | Pass | PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md |
| Verification scope RP-01–RP-08 | Pass | PR-012_ROLEPLAY_VERIFICATION_SCOPE.md |
| APO begin-implementation statement | Pending | exact authorize text in auth request |
| PR-012 code | Not authorized | awaiting APP |

### Outcome

B0 package ready. Record a new APP only after the exact APO authorize
statement; then advance Active step to B1 and begin synthetic implementation.

## PR-012 — Step B0 (accepted) / B1 (in progress)

### Evidence reviewed

- Exact APO statement authorizing PR-012 bounded synthetic role-play
- [APP-0026](APPROVALS.md#app-0026) / D-0104
- [PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md](PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md)
- [PR-012_ROLEPLAY_VERIFICATION_SCOPE.md](PR-012_ROLEPLAY_VERIFICATION_SCOPE.md)

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| APO begin-implementation | Pass | APP-0026 |
| Synthetic-only scope | Pass | APP-0026 authorized scope |
| Approved curriculum / live TTS expansion | Not authorized | APP-0026 non-goals |
| B1 implementation | In progress | plan Active step B1 |

### Outcome

B0 closed. Implement B1 synthetic Observe→Rehearse→Perform→Reflect slice;
exact-commit acceptance remains a later APO gate.

### B1 progress note (implementation landed; verification pending)

Landed under APP-0026 authorized synthetic scope:

- Migration `2026_08_07_000050_pr012_synthetic_roleplay.php` widens friend/roleplay
  CHECKs and adds `scenario_objectives`, append-only
  `conversation_session_stage_events`, and advisory `roleplay_feedback_evidence`.
- `RoleplayStages` + `RoleplayStageService` linear Observe→Rehearse→Perform→Reflect;
  Reflect writes digests only (no mastery).
- `ConversationSessionService` resolves friend/roleplay prototype pairs; roleplay
  starts at Observe with objectives/feedback payload fields.
- HTTP `POST /conversation-sessions/{session}/stages`, OpenAPI 0.10.0 + regenerated
  clients, FoundationSeeder clinic-reception synthetic prototype (2 objectives).
- Practice UI `RoleplayJourney` entry ("Role-play (synthetic)") beside Confidence.

Focused verification passed for this slice:

- PHPUnit unit/feature (sqlite): `RoleplayStagesTest`,
  `ConversationRequestValidationTest` stage cases — pass
- PHPUnit integration (postgres via php:8.4-cli + pdo_pgsql):
  `RoleplaySessionTest`, `FoundationSeederIntegrityTest` — pass
- Vitest (Node 24): `RoleplayJourney.test.tsx` — pass
- `pnpm run openapi:check` and `pnpm run locale:check` — pass

B1 remains open for full `make verify` / APO exact-commit acceptance; production
and Play stay unauthorized.

### B1 verification re-run + B2 + acceptance package

#### Evidence reviewed

- Host PHPUnit: `RoleplayStagesTest` + stage validation — 5 passed
- Docker PHP 8.4.24 + pdo_pgsql: `RoleplaySessionTest` +
  `FoundationSeederIntegrityTest` — 6 passed / 42 assertions
- Node 24 Docker: locale check pass; OpenAPI generate `--check` pass;
  Vitest RoleplayJourney + ConversationJourney — 4 passed
- Chromium Role-play e2e: spec added; webServer `tsc -b` blocked by
  pre-existing unrelated typecheck errors — **not claimed**
- B2 content path: D-0105 synthetic retained for APP-0026
- [PR-012_APO_ACCEPTANCE_PACKAGE.md](PR-012_APO_ACCEPTANCE_PACKAGE.md)

#### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| RP-01 stage transitions | Pass | RoleplaySessionTest |
| RP-02 persona/scenario snapshot | Pass | session start assertions |
| RP-03 no mastery writes | Pass | learningCounts unchanged |
| RP-04 advisory feedback evidence | Pass | roleplay_feedback_evidence digests |
| RP-05 cross-tenant denial | Pass | RoleplaySessionTest |
| RP-06 OpenAPI/clients | Pass | 0.10.0 + generate --check |
| RP-07 UI journey | Pass (unit) | RoleplayJourney vitest; Chromium not claimed |
| RP-08 make verify | Not claimed | host/CI limitations |
| B2 synthetic content path | Pass | D-0105 |
| Exact commit / APO accept | Pass | [APP-0027](APPROVALS.md#app-0027) at `0fb6f2953580ed6154e4d895e128c46167c6f5d4` |

#### Outcome

B1–B2 synthetic scope ready for Exact-commit APO acceptance after an authorized
commit. PR-013 remains unauthorized.

## PR-012 — Accepted (APP-0027)

### Evidence reviewed

- Exact APO statement accepting `0fb6f2953580ed6154e4d895e128c46167c6f5d4`
- [APP-0027](APPROVALS.md#app-0027) / D-0106
- [PR-012_APO_ACCEPTANCE_PACKAGE.md](PR-012_APO_ACCEPTANCE_PACKAGE.md)

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Exact-commit APO accept | Pass | APP-0027 |
| Recorded limitations retained | Pass | acceptance package |
| Approved curriculum / live TTS expansion | Not authorized | APP-0027 non-goals |
| PR-013 / production / Play | Not authorized | APP-0027 |

### Outcome

PR-012 synthetic Role-play accepted. Next: separate APP for PR-013 (C0) only.

## PR-013 — Step C0 (authorization request ready)

### Evidence reviewed

- [PR-013_OPERATOR_AUTHORIZATION_REQUEST.md](PR-013_OPERATOR_AUTHORIZATION_REQUEST.md)
- [PR-013_OPERATOR_VERIFICATION_SCOPE.md](PR-013_OPERATOR_VERIFICATION_SCOPE.md)
- Preconditions: APP-0027 (PR-012); existing curriculum commands + audit write path
- First APP scope: content review→publish + audit list; no DB access for operators
- Deferred: sampled speaking review (ADR-0007), privacy ops, CSV PII export,
  full studio, production/Play

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Auth request drafted post–APP-0027 | Pass | PR-013_OPERATOR_AUTHORIZATION_REQUEST.md |
| Verification scope OC-01–OC-08 | Pass | PR-013_OPERATOR_VERIFICATION_SCOPE.md |
| ADR-0007 sampled review deferred | Pass | auth request non-goals |
| APO begin-implementation statement | Pass | APP-0028 |
| PR-013 code | Authorized bounded slice | APP-0028 |

### Outcome

C0 closed by APP-0028. Proceed to C1 implementation.

## PR-013 — Step C1 (content-workflow + audit in progress)

### Evidence reviewed

- APP-0028 bounded operator content-workflow + audit authorization
- Seeded Direct org role packs: `trainer`, `organization_admin`, `content_reviewer`
- New permission `audit.read` for org-scoped audit digests
- Operator HTTP APIs wrapping ContentReviewCommands / CurriculumPublisher /
  CurriculumRollbackService plus draft/publication/audit list queries
- Ordinary-product `OperatorConsole` (not Vite `/demo/*`)
- OpenAPI bumped to **0.11.0**

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| OC-01 review→publish without DB access | Pass (focused) | OperatorContentWorkflowTest HTTP review→publish→rollback |
| OC-02 privileged actions write audit events | Pass (focused) | content_review.recorded + publication.activated present |
| OC-03 org-scoped audit list (digests only) | Pass (focused) | GET /operator/audit-events; no payload/transcript fields |
| OC-04 cross-tenant denial | Pass (focused) | foreign revision/version IDs → 404 |
| OC-05 missing permission fail-closed | Pass (focused) | membership without grants → 404 |
| OC-06 OpenAPI/client drift | Pass (focused) | OpenAPI 0.11.0; `pnpm run openapi:check` |
| OC-07 operator UI unit smoke | Pass (focused) | OperatorConsole.test.tsx; Chromium e2e not claimed |
| OC-08 make verify | Not claimed | focused Docker PHPUnit / Node 24 only |
| ADR-0007 no audio/transcript operator access | Pass | audit digests + console notice; no voice routes |

### Focused verification recorded this slice

| Area | Result |
|---|---|
| PHPUnit feature (host) | `OperatorRequestValidationTest` — 2 passed |
| PHPUnit integration (Docker PHP 8.4.24 + pdo_pgsql) | `OperatorContentWorkflowTest` + `FoundationSeederIntegrityTest` — 5 passed / 104 assertions |
| Vitest (Node 24 Docker) | `OperatorConsole.test.tsx` — 1 passed |
| Locale validation (Node 24) | Pass — 6 catalogues, 5 pending-review warnings |
| OpenAPI contract generate `--check` (Node 24) | Pass — contracts match OpenAPI 0.11.0 |
| Host `make verify` / Chromium e2e / remote CI | **Not claimed** |

### Outcome

C1 bounded operator content-workflow + audit slice implemented under APP-0028
with focused verification green. Sampled speaking review, privacy ops,
production and Play remain unauthorized. APO exact-commit acceptance package
drafted for Exact commit `9220ab27baa6dc29d82caf8e9e97e94f7df7219d`
([PR-013_APO_ACCEPTANCE_PACKAGE.md](PR-013_APO_ACCEPTANCE_PACKAGE.md)).

## PR-013 — Acceptance package drafted

### Evidence reviewed

- [PR-013_APO_ACCEPTANCE_PACKAGE.md](PR-013_APO_ACCEPTANCE_PACKAGE.md)
- Exact commit `9220ab27baa6dc29d82caf8e9e97e94f7df7219d`
- C1 QUALITY_REVIEW focused evidence; OC-01–OC-07 pass; OC-08 not claimed

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Acceptance package drafted | Pass | PR-013_APO_ACCEPTANCE_PACKAGE.md |
| Limitations explicit | Pass | package § Recorded limitations |
| Exact commit SHA | Pass | `9220ab27baa6dc29d82caf8e9e97e94f7df7219d` |
| APO accept statement | Pass | [APP-0029](APPROVALS.md#app-0029) |
| Sampled review / privacy ops / PR-014+ | Not authorized | APP-0029 non-goals |

### Outcome

PR-013 bounded operator content-workflow + audit **accepted** under APP-0029 at
`9220ab27baa6dc29d82caf8e9e97e94f7df7219d`. Next: separate APP for PR-014
and/or remaining PR-013 items only.

## PR-014 — Step D0 (authorization request ready)

### Evidence reviewed

- [PR-014_ANDROID_AUTHORIZATION_REQUEST.md](PR-014_ANDROID_AUTHORIZATION_REQUEST.md)
- [PR-014_ANDROID_VERIFICATION_SCOPE.md](PR-014_ANDROID_VERIFICATION_SCOPE.md)
- Preconditions: APP-0029; Passport PKCE server; Android adapter interface;
  demo Capacitor APK remains D-0078 presentation-only
- First APP scope: production-path closed-beta foundations (separate package,
  system-browser PKCE, reviewed native secure storage, device matrix,
  reproducible signed closed-beta artifact)
- Deferred: Play public release, PR-015, remaining PR-013 sampled review /
  privacy ops, production OTP cutover

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Auth request drafted post–APP-0029 | Pass | PR-014_ANDROID_AUTHORIZATION_REQUEST.md |
| Verification scope AN-01–AN-10 | Pass | PR-014_ANDROID_VERIFICATION_SCOPE.md |
| Demo APK kept non-production | Pass | D-0078; auth request non-goals |
| APO begin-implementation statement | Pass | [APP-0030](APPROVALS.md#app-0030) |
| PR-014 code | Authorized (begin) | APP-0030; Active = D1 |

### Outcome

D0 complete under APP-0030. Active step D1 implements closed-beta foundations.

## PR-014 — Step D1 (closed-beta foundations in progress)

### Evidence reviewed

- Separate production Capacitor tree `apps/web/android-app`, package
  `com.globalenglish.app` (demo `apps/web/android` / `com.globalenglish.demo`
  unchanged)
- Dual `capacitor.config.ts` via `CAPACITOR_APP=production`
- `AndroidPkceAuthenticationAdapter` wired from `defaultAuthentication` on
  native Android when `VITE_MOBILE_API_ORIGIN` is set
- Reviewed secure store: project-owned EncryptedSharedPreferences plugin
  ([ADR-0012](adr/0012-android-native-secure-credential-store.md) / D-0110)
- System browser Custom Tabs plugin + deep-link
  `com.globalenglish.app:/oauth/callback`
- [PR-014_ANDROID_DEVICE_MATRIX.md](PR-014_ANDROID_DEVICE_MATRIX.md)
- [PR-014_ANDROID_CLOSED_BETA_BUILD.md](PR-014_ANDROID_CLOSED_BETA_BUILD.md)
- `make android-closed-beta` (demo target retained)

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| AN-01 separate package ID | Pass (static) | android-app applicationId / ADR docs |
| AN-02 ordinary product `dist` | Pass (static) | CAPACITOR_APP production webDir `dist` |
| AN-03 PKCE + deep link wiring | Pass (unit) | adapter + bindAndroidPkceDeepLink tests |
| AN-04 reviewed secure store | Pass (unit + ADR) | KeystoreSecureCredentialStore; ADR-0012 |
| AN-05 logout clears + revoke path | Pass (existing adapter tests) | AndroidPkceAuthenticationAdapter.test |
| AN-06 no WebView/Preferences creds | Partial | unit proves secure-plugin-only writes; on-device matrix pending |
| AN-07 device matrix doc | Pass (doc) | PR-014_ANDROID_DEVICE_MATRIX.md; physical runs pending |
| AN-08 permission/lifecycle notes | Pass (doc) | matrix journeys; on-device pending |
| AN-09 reproducible signed artifact steps | Pass (doc) | closed-beta build doc; debug keystore limitation |
| AN-10 make verify / Android build evidence | Pending | focused vitest only; full verify / Gradle APK not claimed |
| Play public release | Not authorized | APP-0030 |

### Focused tests run (this D1 slice)

```text
vitest run src/auth/AndroidPkceAuthenticationAdapter.test.ts
  src/auth/KeystoreSecureCredentialStore.test.ts
  src/auth/defaultAuthentication.test.ts
  src/auth/createInstallationUlid.test.ts
  src/auth/bindAndroidPkceDeepLink.test.ts
  src/auth/androidPkceEnvironment.test.ts
  src/auth/capacitorAndroidConfig.test.ts
→ 7 files / 15 tests passed
```

`make verify`, `make android-closed-beta` Gradle assemble, physical device matrix
and Play upload were **not** run/claimed in this session.

### Outcome

D1 foundations landed for APO review after focused tests and documented
remaining on-device gaps. Play public release and PR-015 remain unauthorized.

## PR-014 — Step D1 acceptance package (awaiting APO)

### Evidence reviewed

- Exact implementation commit `44b0e672e004cf3846cddabc456c21153fa4607e`
- [PR-014_APO_ACCEPTANCE_PACKAGE.md](PR-014_APO_ACCEPTANCE_PACKAGE.md)
- QUALITY_REVIEW D0/D1 gate tables (AN-06 partial; AN-10 / physical matrix /
  Gradle assemble / `make verify` not claimed)

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Acceptance package drafted | Pass | PR-014_APO_ACCEPTANCE_PACKAGE.md |
| Exact commit SHA filled | Pass | `44b0e672e004cf3846cddabc456c21153fa4607e` |
| Limitations recorded (Play / matrix / verify) | Pass | acceptance package |
| APO accept statement | Pass | [APP-0031](APPROVALS.md#app-0031) |
| Play public release / PR-015 | Not authorized | APP-0031 non-goals |

### Outcome

PR-014 closed-beta foundations **accepted** under APP-0031 / D-0111 at
`44b0e672e004cf3846cddabc456c21153fa4607e`. Next: separate APP for E0
(PR-015) and/or on-device matrix / remaining PR-013 only.

## PR-015 — Step E0 (authorization request ready)

### Evidence reviewed

- [PR-015_PRODUCTION_HARDENING_AUTHORIZATION_REQUEST.md](PR-015_PRODUCTION_HARDENING_AUTHORIZATION_REQUEST.md)
- [PR-015_PRODUCTION_HARDENING_VERIFICATION_SCOPE.md](PR-015_PRODUCTION_HARDENING_VERIFICATION_SCOPE.md)
- Preconditions: APP-0031 (PR-014 foundations + unclaimed matrix/`make verify`);
  APP-0029 bounded operator; prior learner slices
- First APP scope: close APP-0031 Android evidence gaps; load/soak harness;
  backup/restore drill; monitoring/runbooks; staged deploy rehearsal; a11y
  evidence package; aggregate pilot analytics skeleton
- Deferred: Play public release; F0 §27 DoD; remaining PR-013 sampled review /
  privacy ops; specialist review waiver; real learner production data

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Auth request drafted post–APP-0031 | Pass | PR-015_PRODUCTION_HARDENING_AUTHORIZATION_REQUEST.md |
| Verification scope PH-01–PH-12 | Pass | PR-015_PRODUCTION_HARDENING_VERIFICATION_SCOPE.md |
| Bounded vs full PR-015 / F0 | Pass | auth request non-goals |
| APO begin-implementation statement | Pass | [APP-0032](APPROVALS.md#app-0032) |
| PR-015 code | Authorized (begin) | APP-0032; Active = E1 |

### Outcome

E0 complete under APP-0032. Active step E1 implements bounded operations
hardening and closed-beta evidence closure.

## PR-015 — Step E1 (implementation in progress)

### Evidence reviewed

- APP-0032 / D-0112 begin-implementation
- Verification scope PH-01–PH-12
- Implementation starting on checkpoint branch

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Begin-implementation recorded | Pass | APP-0032 |
| PH-01–PH-12 delivery | In progress | E1 |
| Play / F0 / remaining PR-013 | Not authorized | APP-0032 non-goals |

### Outcome

E1 implementation authorized and underway.

## PR-015 — Step E1 (bounded ops hardening delivery)

### Evidence reviewed

- Aggregate pilot analytics API/UI (`GET /operator/pilot-analytics`, OpenAPI
  **0.12.0**)
- Ops runbooks: backup/restore, incident/support, alerts skeleton, staged
  deploy dry-run
- Makefile targets: `load-soak`, `backup-restore-drill`, `staged-deploy-dry-run`
- Accessibility evidence package draft
- Device matrix authority updated for APP-0032 evidence closure

### Focused tests run

| Area | Result |
|---|---|
| PHPUnit integration (Docker PHP 8.4.24 + pdo_pgsql) | `OperatorContentWorkflowTest` — **2 passed / 94 assertions** |
| Vitest (Docker Node 24.18.1) | `OperatorConsole.test.tsx` — **1 passed** |
| Locale validation (Docker Node 24) | Pass — 6 catalogues, 5 pending-review warnings |
| OpenAPI contracts generate | Pass — regenerated for 0.12.0 |
| Backup/restore drill | Run when claimed in commit notes |
| Load/soak / staged-deploy dry-run / physical matrix / `make verify` | **Not claimed** unless separately recorded |

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| PH-01–PH-04 device matrix / assemble | Partial | docs + matrix template; physical/Gradle not claimed |
| PH-05 `make verify` | Not claimed | host/env limitations |
| PH-06 load/soak harness | Pass (harness) | `make load-soak` / scripts/run-load-soak.sh — needs live API to execute |
| PH-07 backup/restore | Pass (script+doc) | docs/ops + make backup-restore-drill |
| PH-08 monitoring/runbooks | Pass (docs) | ALERTS + INCIDENT runbooks |
| PH-09 staged deploy dry-run | Pass (script+doc) | make staged-deploy-dry-run |
| PH-10 a11y package | Pass (doc) | PR-015_ACCESSIBILITY_EVIDENCE_PACKAGE.md; manual rows open |
| PH-11 pilot analytics | Pass (code+tests) | OpenAPI 0.12.0 + OperatorConsole |
| PH-12 fail-closed regression | Pass (focused) | OperatorContentWorkflowTest |
| Play / F0 / remaining PR-013 | Not authorized | APP-0032 |

### Outcome

E1 delivery landed for APO exact-commit review with recorded limitations
(physical matrix, Gradle assemble, full `make verify`, live load-soak against
API, Play/F0).

## PR-015 — Step E1 acceptance package (awaiting APO)

### Evidence reviewed

- Exact implementation commit `5eca7515291ea571a7f697dcdc2da6197535f839`
- [PR-015_APO_ACCEPTANCE_PACKAGE.md](PR-015_APO_ACCEPTANCE_PACKAGE.md)
- QUALITY_REVIEW E1 gate table

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Acceptance package drafted | Pass | PR-015_APO_ACCEPTANCE_PACKAGE.md |
| Exact commit SHA filled | Pass | `5eca7515291ea571a7f697dcdc2da6197535f839` |
| Limitations recorded | Pass | acceptance package |
| APO accept statement | Pass | [APP-0033](APPROVALS.md#app-0033) |
| Play / F0 / remaining PR-013 | Not authorized | APP-0033 non-goals |

### Outcome

PR-015 bounded operations hardening and closed-beta evidence closure
**accepted** under APP-0033 / D-0113 at
`5eca7515291ea571a7f697dcdc2da6197535f839`. Next: separate APP / release board
for F0 only.

## F0 — Phase 1 Definition of Done (gap assessment ready)

### Evidence reviewed

- [F0_PHASE1_DOD_GAP_ASSESSMENT.md](F0_PHASE1_DOD_GAP_ASSESSMENT.md)
- Master plan §27 checklist mapped to current APP posture
- APP-0033 limitations (physical matrix / verify / live drills unclaimed)
- Remaining blockers: DR-0007 reviewed content; PR-013 privacy/support;
  specialist reviews; production-like OTP/pilot environment

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| §27 gap assessment drafted | Pass | F0_PHASE1_DOD_GAP_ASSESSMENT.md |
| F0 DoD ready to sign | Fail / blocked | Multiple §27 rows Partial/Blocked |
| Pre-F0 evidence APP statement | Pending | Exact line in gap assessment |
| Android sideload smoke vs F0 | Separated | Closed-beta build doc; not F0 evidence alone |
| Play public release | Not authorized | APP-0033 |

### Outcome

F0 consideration package ready. Do **not** claim Phase 1 DoD. Recommended
APO path: authorize pre-F0 evidence-closure APP, or defer F0.

## F0 — Pre-F0 evidence-closure (APP-0034 begin)

### Evidence reviewed

- Exact APO begin statement recorded as [APP-0034](APPROVALS.md#app-0034) /
  D-0114
- [F0_PHASE1_DOD_GAP_ASSESSMENT.md](F0_PHASE1_DOD_GAP_ASSESSMENT.md)
- Local closed-beta Android `assembleDebug` (2026-08-07):
  - JDK: Temurin 21 at `~/.local/jdk/jdk-21.0.12+8`
  - SDK: `$ANDROID_HOME` API 36
  - Dependency fix: `androidx.security:security-crypto:1.1.0-alpha06`
    (required for `MasterKey` used by secure-credentials plugin)
  - Output: `apps/web/android-app/app/build/outputs/apk/debug/app-debug.apk`
    (~5.8 MB); package `com.globalenglish.app`; minSdk 24; targetSdk 36
  - Web assets baked with `VITE_MOBILE_API_ORIGIN=http://192.168.1.3:8000`
  - Physical device matrix / on-device credential negatives: **not claimed**
  - No device attached via `adb` at assemble time

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Begin-implementation recorded | Pass | APP-0034 |
| F0 DoD sign-off | Not authorized | APP-0034 non-goals |
| Play public release | Not authorized | APP-0034 |
| Local Gradle `assembleDebug` | Pass (local) | APK path above; not Exact-commit claim yet |
| Physical device matrix / drills | Open | APP-0034 remaining scope |

### Outcome

APP-0034 authorized. Local debug APK assemble recorded; F0 Exact-commit DoD
accept and Play public release remain unauthorized.

## F0 — APP-0034 evidence closure executed

### Evidence reviewed

- [F0_APP0034_EVIDENCE_PACKAGE.md](F0_APP0034_EVIDENCE_PACKAGE.md)
- Android `assembleDebug` Pass at `21f577cb2b1ac032124f65d3774b46e45e665196`
- PH-06 load-soak Pass (`artifacts/ops/load-soak-20260807T161805Z.json`, 200/200)
- PH-07 backup/restore Pass (`artifacts/ops/backup-restore-20260807T161800Z.json`, RTO 2s)
- §27 matrix filled; PR-013 privacy/support APP-deferred with draft authorize request
- Staging Friend DI runbook [STAGING_CONFIDENCE_CONVERSATION.md](STAGING_CONFIDENCE_CONVERSATION.md)
- F0 consideration package (not accept):
  [F0_PHASE1_DOD_CONSIDERATION_PACKAGE.md](F0_PHASE1_DOD_CONSIDERATION_PACKAGE.md)
- Closed-beta pilot runbook [CLOSED_BETA_PILOT_RUNBOOK.md](CLOSED_BETA_PILOT_RUNBOOK.md)
- Physical DM-01/DM-02: **not claimed**

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| APP-0034 evidence package | Pass (recorded) | F0_APP0034_EVIDENCE_PACKAGE |
| Local assemble + PH-06 + PH-07 | Pass | artifacts/ops + QUALITY |
| Physical device matrix | Open | PR-014_ANDROID_DEVICE_MATRIX |
| F0 DoD Exact accept | Not authorized | Consideration package only |
| Play public release | Not authorized | APP-0034 non-goals |
| Staging Friend path documented | Pass | STAGING_CONFIDENCE_CONVERSATION |

### Outcome

APP-0034 evidence-closure deliverables recorded. Exact-commit F0 DoD and Play
remain unauthorized. Next APO gates: privacy/support APP, DR-0007, device
matrix, specialist reviews.




## PR-013 — Remaining privacy and support ops (APP-0035)

### Scope reviewed

- Org-scoped support diagnostics digests
- Bounded privacy export (identifier summary only)
- Account-erasure workflow with ADR-0007 status machine and voice deletion receipts
- Permissions, OpenAPI/contracts, operator console diagnostics panel, cross-tenant tests

### Review result

| Check | Result | Evidence |
|---|---|---|
| APP-0035 begin recorded | Pass | APPROVALS.md APP-0035 |
| Diagnostics omit content bodies | Pass | SupportDiagnosticsQuery + OpenAPI description |
| Export omits audio/transcripts | Pass | PrivacyExportService content_note |
| Erasure creates deletion receipts | Pass | PrivacyErasureService |
| Cross-tenant isolation | Pass | OperatorPrivacySupportTest |
| Sampled speaking review | Not authorized | APP-0035 non-goals |
| F0 DoD / Play | Not authorized | APP-0035 non-goals |

### Security, privacy and tenancy review

- Operator responses remain digests/identifier summaries only.
- Independent specialist reviews remain required before production.
- No waiver of ADR-0007 audio/transcript access prohibition.

### Known limitations

- Postgres Integration suite / host `make verify` not claimed in this slice.
- Physical Android matrix and reviewed pathway content remain open F0 blockers.

## PR-013 — Privacy/support Exact accept (APP-0036)

| Check | Result | Evidence |
|---|---|---|
| Exact commit accepted | Pass | APP-0036 at 1357eb161d3754da3b8d6b888bd1e3b0a8698ff9 |
| F0 DoD | Not authorized | APP-0036 non-goals |
| Play public release | Not authorized | APP-0036 |
| Sampled speaking review | Not authorized | APP-0036 |


## F0 — Pre-DoD readiness refresh (post APP-0036/0037)

### Scope reviewed

- Updated F0 consideration package with closed privacy/support and DR-0007 limitation
- Specialist review packet for external reviewers
- Android `clean assembleDebug` re-verify at `22815bdfea835acfdd8eb4968743dd2b7ec52a7d`
- Emulator substitute probe (failed: missing AVD system image / no KVM)

### Review result

| Check | Result | Evidence |
|---|---|---|
| assembleDebug re-verify | Pass | ~5.8 MB APK; SHA-256 in device matrix |
| Physical DM-01/DM-02 | Open | No device attached |
| Emulator substitute | Failed | AVD system path missing |
| Specialist reviews | Not claimed | F0_SPECIALIST_REVIEW_PACKET.md |
| F0 DoD Exact accept | Not authorized | Consideration package Option A/B only |

### Known limitations

- Docker/`make verify` not re-run in this refresh (daemon not started).
- F0 Exact accept still requires an Exact APO statement.

## ADR-0013 — Lesson-video learner delivery

> Superseded in part by the LV-01 conformance review below. The original
> delivery surfaces remain valid, but the original importer and its generated
> authority/publication evidence do not.

### Scope reviewed

- Private `lesson_video` stream contract separate from model audio
- Pin-gated catalog API and LearnerJourney video panel
- Local commissioner/import from gitignored legacy inventory feedstock
- OpenAPI 0.13.0 + generated contracts; locale keys for level-video UI

### Review result

| Check | Result | Evidence |
|---|---|---|
| ADR recorded | Pass | `docs/adr/0013-lesson-video-learner-delivery.md` |
| Config bounds video separately from audio | Pass | `apps/api/config/learning.php` |
| Integration delivery + tenancy isolation | Pass | `LevelVideoLearnerDeliveryTest` (31 assertions) |
| Web catalog/player tests | Pass | `LessonVideoPlayer`, `LevelVideosPanel`, `WebLearnerJourneyApi`, `LearnerJourney` vitest |
| OpenAPI + contracts | Pass | `pnpm run openapi:check` |
| Locale catalogue parity/hashes | Pass | `pnpm run locale:check` (pending-independent-review warnings only) |
| No media bytes committed | Pass | feedstock remains under gitignored `/artifacts/` |
| F0 DoD / Play / academic approval | Not authorized | Owner-asserted local commissioning only |

### Known limitations

- Offline bundles do not include lesson video.
- Missing legacy source levels (4, 5, 12, 15, 16) remain absent from catalogs.
- Full `make verify` not claimed in this slice; targeted PHPUnit + web + openapi + locale checks were run.
- Android uses WebView `<video>` only.

## Lesson-video staging hardening (D-0119)

### Scope reviewed

- Private `lesson_videos` S3 disk + `LEARNING_LESSON_VIDEO_DISK` env example
- Staging import runbook, rights/evidence checklist, source-gap table, smoke criteria
- Explicit non-authorization of F0 / Play / academic approval

### Review result

| Check | Result | Evidence |
|---|---|---|
| Staging hardening doc | Pass | [LESSON_VIDEO_STAGING_HARDENING.md](LESSON_VIDEO_STAGING_HARDENING.md) |
| Dedicated private disk config | Pass | `filesystems.php` `lesson_videos`; `.env.example` |
| Missing levels documented (not fabricated) | Pass | Hardening doc source-gap table |
| Production/F0/Play claimed | Fail (correct) | Explicit non-claims in doc + D-0119 |

### Known limitations

- Independent content review and F0 Exact accept remain unauthorized.

### Local staging-shaped smoke (follow-on)

| Check | Result | Evidence |
|---|---|---|
| Import 12 feedstock MP4s to `lesson_videos` disk | Quarantined | Superseded importer manufactured synthetic authority |
| S1 catalog / S2 stream / S3 wrong enrollment | Quarantined | Depended on the unauthorized publication/pin above |
| S4 LearnerJourney UI + session stream contract | Mixed | UI/session tests remain useful; the historical publication artifact is non-evidence |
| S4 interactive browser / S5 Android WebView | Not claimed | Operator mobile OTP / APK follow-up |
| Production host import | Not claimed | Local SeaweedFS only |

## LV-01 — Lesson-video import authority correction

### Scope reviewed

- Legacy inventory validation and private object copy
- Aggregate transaction and caller-bound idempotency
- Tenant/permission boundaries
- Draft graph/content/translation creation
- Initial pending media-rights evidence
- Independent governance handoff through existing ADR-0004 commands
- Failure cleanup and prior smoke-evidence classification

### Review result

| Check | Result | Evidence |
|---|---|---|
| Import creates users/memberships | Pass (none created) | `LegacyLevelVideoCommissionerTest` |
| Import creates appointments/reviews/approved rights | Pass (none created) | `LegacyLevelVideoCommissionerTest` |
| Import publishes or pins | Pass (blocked) | publisher rejection assertion + zero publication/enrollment rows |
| Source files and stored objects match bytes/SHA-256 | Pass | preflight validation + storage read-back verification |
| Database writes are atomic | Pass | one transaction after external object writes |
| Failed partial upload is cleaned | Pass | mocked second-write failure test |
| Exact retry replays; changed request conflicts | Pass | caller/organization-scoped idempotency assertions |
| Cross-tenant retry-key isolation | Pass | same key creates distinct tenant-owned drafts and command records |
| Legitimate governed delivery remains possible | Pass | distinct test author, English reviewer, Kannada reviewer, rights confirmer and publisher; tenant-isolated catalog/stream test |
| Human academic/language/rights approval | Not claimed | test identities and decisions are fixtures only |

### Commands run so far

| Command | Result |
|---|---|
| Targeted Pint on LV-01 PHP/routes/tests | Pass |
| Targeted PHPStan level 8 (`--debug`, `/tmp` cache) | Pass, 0 errors |
| SQLite integration filter for importer + delivery | Pass, 5 tests / 69 assertions |
| Ordinary API unit/feature suite (`php artisan test`) | Pass, 98 tests / 824 assertions |
| PostgreSQL integration filter | Blocked: this PHP runtime has no `pdo_pgsql` driver |
| Full integration directory forced onto SQLite | Not an accepted gate: 107 passed; PostgreSQL-only constraints/concurrency plus protected storage produced 65 failures / 22 errors / 1 skipped |
| `make verify` with pinned toolchain | Failed at Composer audit: six `league/commonmark` advisories; tracked by PIPE-01 |
| `make e2e` with pinned Node toolchain | Failed before browser startup: four existing `LevelVideosPanel.tsx` possibly-undefined errors; tracked by VIDEO-01 |

### Known limitations

- The draft retains the disclosed synthetic daily-mission scaffold from
  D-0116, including placeholder model-audio bytes. Real reviewers must reject
  or replace unsuitable content/media; no production approval is claimed.
- The repository has no complete operator HTTP/CLI workflow for appointment,
  review, rights confirmation, publication and pin handoff.
- PostgreSQL-specific constraints/concurrency could not run in this environment
  because the available PHP runtimes lack `pdo_pgsql`; they remain a release
  gate even though the bounded LV-01 checkpoint is closed.
- Repository-wide `make verify` and `make e2e` remain red for the explicitly
  assigned PIPE-01 and VIDEO-01 gaps above; LV-01 did not opportunistically
  change those later checkpoints.

## PRIV-DEL-01 — Durable voice deletion reconciliation

### Scope reviewed

- Learner deletion and consent-withdrawal deletion
- Scheduled source/transcript retention expiry
- Operator privacy erasure and receipt reconciliation
- Transaction boundary, leased outbox retry and stale/re-entrant worker safety
- Tenant isolation and content-free operational evidence

### Review result

| Check | Result | Evidence |
|---|---|---|
| Intent exists before storage I/O | Pass | transaction-level assertions around `VoiceDeletionService` |
| Failed delete retains object key | Pass | false-return failure/retry integration test |
| Failed delete is non-terminal and retryable | Pass | unpublished outbox, safe error, capped backoff, successful second attempt |
| Active worker cannot be double-claimed | Pass | re-entrant worker observes zero due work while lease is active |
| Stale attempt cannot finalize a newer claim | Pass | attempt generation is required by final update |
| Privacy and retention share implementation | Pass | exact receipt-link migration and operator erasure retry test |
| Replay creates duplicate work | Pass (none created) | deletion/privacy idempotency count assertions |
| Cross-tenant access/deletion | Pass (denied) | learner deletion and operator privacy HTTP tests |
| Outbox/log evidence contains content/key | Pass (none present) | empty payload plus raw-audio/object-key assertions |
| API/client contract changed | No | existing deletion/privacy status enums already cover partial/pending states |

### Commands run

| Command | Result |
|---|---|
| Targeted Pint | Pass |
| Targeted PHPStan level 8 (`--debug`) | Pass, 0 errors |
| Focused SQLite integration filter | Pass, 8 tests / 134 assertions |
| Focused PostgreSQL integration filter | Pass, 8 tests / 134 assertions |
| Ordinary API unit/feature suite | Pass, 98 tests / 824 assertions |
| Full PostgreSQL integration suite | In-scope tests pass; repository suite remains red: 186 passed / 2,332 assertions, 3 foundation-count failures and 8 pre-existing seed/roleplay/auth errors outside PRIV-DEL-01 |
| `make verify` | Stops at six `league/commonmark` advisories; assigned to PIPE-01 |
| `make e2e` | Preview web server exits before Playwright; existing `LevelVideosPanel` TypeScript failures remain assigned to VIDEO-01 |

### Security, privacy and tenancy review

- Source object keys remain only on `voice_recordings` until confirmed delete;
  they are absent from outbox payloads, responses, audit events and safe errors.
- Every receipt, outbox event and privacy link is organization scoped; composite
  foreign keys prevent cross-tenant privacy/deletion linkage.
- Storage I/O does not run inside the service's authoritative transaction.
- No raw audio, transcript, exception text or provider payload is logged.

### Known limitations

- Provider-side deletion and provider reconciliation remain unavailable while
  live voice providers are disabled.
- Production private-store review and backup restore/tombstone drills remain
  production gates under ADR-0007.
- Broader privacy export/erasure inventory completeness is assigned to
  PRIV-DSR-01; this checkpoint covers voice source objects only.
- Repository-wide dependency, foundation-seed and lesson-video frontend gates
  remain red under later PIPE-01 / VIDEO-01 work.

## AND-AUTH-01 — Android authenticated request lifecycle

### Scope reviewed

- Shared production-Android API, event-stream and private-media request path
- Native secure refresh restoration and rotating-token failure behavior
- One-refresh/one-replay 401 handling and concurrent request races
- Exact, single-consumption PKCE callback delivery across cold WebView startup
- Shell invalidation, logout races and fail-closed mobile configuration
- Private model-audio and lesson-video bearer delivery

### Review result

| Check | Result | Evidence |
|---|---|---|
| Every production Android API client uses shared bearer transport | Pass | default learner, conversation and operator clients use `defaultAuthenticationRuntime.apiFetch`; mobile revoke uses the same adapter |
| API origin/path boundary and cookie omission | Pass | cross-origin rejection, caller-authorization replacement and `credentials=omit` assertions |
| Cold native refresh and rotation | Pass | adapter cold-start and transient/terminal exchange tests |
| Concurrent 401 behavior | Pass | one coalesced refresh, exact request-body replay, one UI invalidation after rejected replay |
| Logout versus late refresh | Pass | credential-generation race test leaves native refresh empty |
| Callback single consumption | Pass | atomic native `take`, serialized exchange, listener-before-launch and duplicate-state tests |
| Persistent credential allowlist | Pass | native source contract plus `EncryptedSharedPreferences`/Keystore implementation |
| Private audio/video authentication | Pass | authenticated blob loader and object-URL revocation tests |
| PostgreSQL server token-family contract | Pass | 12 tests / 567 assertions |
| Android compile, unit task and APK assembly | Pass | Gradle `testDebugUnitTest` and `assembleDebug` |
| Physical DM-01/DM-02 lifecycle/storage evidence | Open | `adb devices -l` returned no attached device |

### Commands run

| Command | Result |
|---|---|
| Changed-file ESLint (`--max-warnings=0`) | Pass |
| `pnpm run typecheck` with pinned Node 24.18.1 | Pass across contracts, design system, localization and web |
| `pnpm --dir apps/web test` with pinned Node 24.18.1 | Pass, 35 files / 132 tests |
| `pnpm run locale:check` with pinned Node 24.18.1 | Pass, 6 catalogues; 5 pending-independent-review warnings |
| PostgreSQL `MobileTokenFamilyTest\|PassportPkceFlowTest\|ConcurrentMobileRefreshTest` | Pass, 12 tests / 567 assertions |
| Broader PostgreSQL auth filter including `AuthenticationFlowTest` | Not green: 26/28 passed; two pre-existing duplicate `organization_admin` role-fixture errors |
| Mobile Vite build with emulator API/public-client configuration | Pass, 104 modules; existing >500 kB chunk warning |
| `CAPACITOR_APP=production pnpm exec cap sync android` | Pass |
| `./gradlew testDebugUnitTest` (JDK 21) | Pass, 53 actionable tasks; dependent plugin test tasks correctly report no source |
| `./gradlew --offline assembleDebug` (JDK 21) | Pass, 93 actionable tasks |
| `aapt2 dump badging .../app-debug.apk` | Pass: `com.globalenglish.app`, minSdk 24, targetSdk 36, expected network/microphone permissions |
| APK digest | `3095c1a23e5705d3a3ed64a310dcc988e1ac5c3b1be8bf0b565319296441618b` (6,230,453 bytes) |
| `make e2e` with pinned Node toolchain | Pass, 11 Chromium journeys |
| `make build` with pinned PHP/Node toolchains | Pass; API optimize/cleanup and web production build |
| `make verify` with pinned toolchains | Stops at Composer audit: six `league/commonmark` advisories; downstream recipe steps did not run |
| `pnpm audit --audit-level=high` | Fails: high advisories in `js-yaml` and `nanoid` |
| Full web lint invocation | Not green: generated Android build JavaScript and existing unrelated React/demo findings; changed AND-AUTH-01 files pass targeted lint |

### Security, privacy and tenancy review

- Access tokens never enter native or browser persistence; refresh tokens,
  installation ID and pending PKCE state remain behind the reviewed native
  allowlist.
- Bearers are attached only after API origin/path validation. Android requests
  cannot silently fall back to WebView cookies or Sanctum CSRF behavior.
- Terminal rotation/replay failure clears credentials and the learner shell;
  transient outages retain the rotating refresh token without logging it.
- Callback URLs, tokens, raw audio/video and learner identity content are not
  logged by this lifecycle.
- The change adds no authoritative records, foundation schema or tenant-owned
  resource. Existing server authorization and organization scope remain the
  authority for every request.

### Known limitations

- Physical DM-01/DM-02 cold-start, background/process-death, negative-storage,
  private-media and logout checks remain open; no device was attached.
- Android private audio/video currently buffer the full response into a blob.
  Range delivery and large-video memory bounds remain VIDEO-01.
- Composer and pnpm advisory failures, full lint cleanup and the unrelated
  PostgreSQL auth role-fixture errors remain PIPE-01 work; `make verify` is not
  claimed green.
- The generated web chunk remains above Vite's 500 kB advisory threshold.
- F0 Definition of Done, physical Android acceptance and Play release remain
  unauthorized.

## DR-0013 — Option A privileged MFA decision-gate resolution

### Scope reviewed

- Exact human approval statement and approved proposal digest
- Conformance with the master-plan privileged-MFA baseline and ADR-0002
- Factor, recovery, protected-action and web/Passport boundaries
- Durable approval, decision-request and active-checkpoint records
- Separation between policy approval and later MFA-01 implementation evidence

### Review result

| Check | Result | Evidence |
|---|---|---|
| Exact requested statement supplied | Pass | `Approve DR-0013 Option A as written.` |
| Approved artifact is durably identified | Pass | APP-0038 records pre-resolution SHA-256 `78697cffea182a9aafde9eea84742883ffb4e71f616a0789b5f0f9021601013f` |
| Independent factor selected | Pass (policy) | RFC 6238 authenticator-app TOTP with the exact DR-0013 controls |
| SMS-only privileged recovery rejected | Pass (policy) | Ten show-once, single-use ≥128-bit recovery codes; SMS/primary OTP cannot reset MFA |
| Web/Passport boundary selected | Pass (policy) | Privileged/operator endpoints are web-session-only; Passport bearer requests are denied |
| Protected-action set selected | Pass (policy) | Exact authoritative mutations and security escape paths are listed in DR-0013 rule 6 |
| Master plan or ADR silently changed | Pass (none) | Option A fills ADR-0002's gated privileged-MFA extension point; no master-plan amendment |
| MFA schema/API/client behavior claimed | Not claimed | This task records approval only; implementation remains the next bounded MFA-01 task |
| Independent production review | Open | Consolidated development approval does not replace independent security/privacy review |

### Security, privacy and tenancy review

- TOTP secrets are approved only as show-once enrollment material encrypted at
  rest; recovery codes are show-once and keyed-digested. Neither belongs in
  logs, audit payloads or later API responses.
- Time-step, challenge and recovery-code consumption must be transactional and
  replay-safe. Factor replacement/revocation must globally revoke web sessions
  and Android token families.
- Organization permission and tenant checks remain independent of MFA. Fresh
  MFA is an additional gate and never supplies membership, capability or
  organization context.
- Passport denial is an explicit Phase 1 privileged boundary; ordinary learner
  Android flows are unchanged by this approval.

### Known limitations

- No migration, factor storage, TOTP implementation, endpoint, generated
  contract, web UI, middleware coverage expansion or automated security test
  was added by this decision-recording task.
- TOTP is not phishing resistant; Option A explicitly accepts that Phase 1
  trade-off. WebAuthn or cross-platform privileged access needs a separate
  future decision.
- Production remains blocked on independent security and privacy review and on
  successful verification of the eventual MFA-01 implementation.

### Commands run

| Command | Result |
|---|---|
| `git diff --check` | Pass |
| Read-only Node local Markdown-link check over the six affected governance files | Pass, 6 files |
| Read-only Node fence/final-newline structure check over the same files | Pass, 6 files |
| APP-0038 / D-0123 uniqueness and DR-0013 open-table absence checks | Pass |
| `make verify` | Not run for this approval-only documentation task; no product behavior changed, and existing repository-wide dependency failures remain assigned to PIPE-01 |

## MFA-01 — Privileged TOTP MFA vertical slice

### Scope reviewed

- RFC 6238 generation/verification parameters and cross-session replay control
- Pending enrollment, one-time provisioning and recovery-code issuance
- Five-minute challenges, five-attempt cap and user/session/IP rate limits
- TOTP privileged-action scope versus recovery-only replacement scope
- Factor replacement, recovery regeneration and revocation with global web and
  Android credential invalidation
- Web-only operator boundary and fresh-MFA enforcement on every mutable
  operator content/publication/privacy route
- OpenAPI 0.14.0, generated TypeScript client, cookie/CSRF adapter and operator
  console lifecycle
- Content-free audit, response/log secret exclusion and database actor binding

### Review result

| Check | Result | Evidence |
|---|---|---|
| TOTP profile | Pass | 160-bit random Base32 secret, SHA-1, six digits, 30-second period, ±1 step; RFC vector test |
| TOTP replay | Pass | locked `last_used_time_step`; enrollment code reuse is rejected on a later challenge |
| Enrollment secrecy | Pass | encrypted active secret; setup URI appears only in the start response/transient UI, and the scheduled bounded purge clears expired/inactive ciphertext |
| Challenge lifecycle | Pass | session/factor/user composite binding, five-minute expiry, five failed attempts, per-challenge and user/session/IP throttles |
| Recovery strength/use | Pass | ten unique 128-bit codes, keyed factor-domain digest, locked one-time consumption, no later read endpoint |
| Recovery authority | Pass | recovery creates only `factor_replacement`; operator and recovery-management mutations reject it |
| Credential revocation | Pass | replacement, regeneration and revocation invalidate recovery material and revoke web sessions, token families and Passport token rows |
| Protected operator boundary | Pass | all operator routes require web transport; six mutable routes additionally require fresh TOTP; logout/session revocation remain escape paths |
| Database invariants | Pass | PostgreSQL partial uniqueness, composite user binding, factor lifecycle and valid privileged assertion-shape constraints |
| Audit/privacy | Pass | lifecycle/failure categories use opaque IDs; raw TOTP secrets, entered assertions and recovery codes are absent from records, audit and captured logs |
| API/client drift | Pass | OpenAPI 0.14.0 lint/policy and generated-client check pass |
| Browser credential persistence | Pass | MFA adapter uses cookie + CSRF transport; UI tests observe no localStorage/IndexedDB secret writes |
| Tenant authorization | Pass | MFA supplies no tenant authority; existing membership/permission/context checks still gate operator commands and their PostgreSQL tests pass |
| Independent production review | Open | APP-0038 approved the design; it did not accept this implementation or replace independent security/privacy review |

### Commands run

| Command | Result |
|---|---|
| Targeted Pint over MFA and affected auth/operator files | Pass |
| Scoped PHPStan level 8 over MFA and affected authentication files | Pass, 0 errors |
| Full ordinary SQLite API suite | Pass, 102 tests / 868 assertions |
| MFA policy + lifecycle SQLite filter | Pass, 12 tests / 212 assertions |
| MFA lifecycle PostgreSQL | Pass, 4 tests / 201 assertions |
| Affected curriculum/operator/MFA PostgreSQL filter | Pass, 47 tests / 693 assertions |
| `pnpm run openapi:check` | Pass; OpenAPI valid, policy valid, generated contracts current |
| `pnpm run locale:check` | Pass, 6 catalogues; 5 pending-independent-review warnings |
| Changed MFA/App/operator ESLint invocation | Pass, zero warnings |
| Laravel route inspection | Pass, 7 MFA routes and 13 web-only operator routes; all 6 operator mutations carry fresh-MFA middleware |
| Laravel schedule inspection with in-memory cache/session stores | Pass; `auth:mfa-expire-pending` is registered every minute |
| `pnpm run typecheck` | Pass across contracts, design system, localization and web |
| Full web Vitest suite | Pass, 36 files / 135 tests |
| `make e2e` with pinned Node toolchain | Pass, 11 Chromium journeys |
| `make build` with pinned PHP/Node toolchains | Pass; Laravel optimize/cleanup and 105-module web build; existing chunk-size warning |
| Full PHPStan | Not green: 22 existing errors in conversation/voice/operator code outside MFA-01; MFA scope is clean |
| Full web lint | Not green: generated Android build JavaScript plus existing conversation/demo findings; MFA-owned files are clean |
| `make verify` | Stops at Composer audit: six `league/commonmark` advisories; downstream recipe steps do not run |
| `pnpm audit --audit-level=high` | Fails on existing high advisories in `js-yaml` and `nanoid`; assigned to PIPE-01 |
| `git diff --check` and local Markdown-link check | Pass; 6 MFA-01 governance files checked |

### Security, privacy and tenancy review

- Factor/challenge/recovery records are platform-global and user-owned, with
  composite foreign keys preventing a session, challenge or recovery code from
  being bound across users. No organization bypass is introduced.
- TOTP and recovery consumption, assertion writing and relevant factor changes
  occur inside locked transactions. MFA operations lock the user row first to
  serialize cross-session lifecycle races.
- Raw setup/recovery material is show-once and `no-store`; browser adapters do
  not persist it. Request code fields are removed before downstream reporting,
  and safe problem responses do not echo assertions.
- Recovery cannot authorize authoritative operator work. Permission, tenant
  context and immutable content/publication rules remain separate required
  gates after MFA succeeds.
- Global revocation commits authoritative session/token-family state before
  storage-session cleanup; Android refresh and access tokens are both revoked.

### Known limitations

- TOTP is not phishing resistant. WebAuthn or cross-platform privileged access
  requires a separate decision.
- There is no in-app support-assisted reset. Loss of both the active factor and
  all recovery codes fails closed; SMS sign-in alone cannot recover it.
- `APP_KEY`-derived recovery digests do not support historical-key lookup, so
  key rotation needs a reviewed credential migration/revocation procedure.
- Non-English MFA UI strings are implementation previews pending independent
  human language review.
- Repository-wide dependency, PHPStan and lint failures remain PIPE-01; this
  checkpoint does not claim `make verify` is green or authorize production.

## PIPE-01 — Verification pipeline restoration

### Scope reviewed

- Composer and pnpm production/development dependency advisories
- Repository-wide PHP formatting and PHPStan level-8 findings
- Authored web/package ESLint, recursive TypeScript and locale governance
- PostgreSQL verification fixtures and seeded foundation-role assumptions
- Deterministic transcript-number parsing and frontend lifecycle regressions
- OpenAPI policy/generated-client drift, package tests, browser journeys and
  production build

### Review result

| Check | Result | Evidence |
|---|---|---|
| Composer advisory closure | Pass | `league/commonmark` 2.8.3 → 2.9.0; locked audit reports no advisories |
| pnpm advisory closure | Pass | `js-yaml` 4.3.0 → 4.3.1 and `nanoid` 3.3.16 → 3.3.18; audit reports no known vulnerabilities |
| PHP formatting | Pass | full Pint test mode |
| PHP static analysis | Pass | full PHPStan level 8, zero errors |
| JavaScript lint scope | Pass | all authored web/package/localization validation source; copied native wrapper/build output excluded |
| Type and locale contracts | Pass | recursive TypeScript; 6 locale catalogues with only 5 declared pending-independent-review warnings |
| API contract drift | Pass | OpenAPI lint/policy and deterministic generated TypeScript client check |
| API behavior | Pass | 102 SQLite tests / 869 assertions and 201 PostgreSQL tests / 2,615 assertions |
| Package/web behavior | Pass | localization 31, design system 9 and web 139 tests; 179 total |
| Browser journeys | Pass | 11 Chromium journeys, including conversation, voice-consent, online/offline learner and accessibility paths |
| Production build | Pass | Laravel optimize/cleanup and 105-module web build; existing chunk-size warning remains visible |
| API/schema change | None | no OpenAPI behavior, migration, foundation schema or production configuration added by PIPE-01 |
| Architecture decision | None required | dependency patching, verification-scope correction and behavior-preserving static fixes conform to existing decisions |

### Commands run

| Command | Result |
|---|---|
| Composer validate and locked audit | Pass; strict manifest validation and zero advisories |
| `pnpm audit --json` and `pnpm audit --audit-level=high` | Pass; zero vulnerabilities at every reported severity |
| Targeted affected PHP tests | Pass, 16 tests / 669 assertions |
| Targeted affected web tests | Pass, including the class-receiver AI-memory regression |
| Final pinned-toolchain `make verify` | Pass with PHP 8.4.23, Composer 2.10.2, Node.js 24.18.1 and pnpm 11.18.0; all audit, format, static, API, PostgreSQL, locale, OpenAPI, lint, type and package-test stages green |
| Focused conversation Playwright file | Pass, 3/3 Chromium journeys |
| Final `make e2e` | Pass, 11/11 Chromium journeys |
| `make build` | Pass; Laravel production optimization/cleanup and web production build |
| `make ai-eval` | Not run; prompts, models, provider selection and orchestration did not change |

The first full browser run passed 9/11 and exposed an unbound optional
`listAiMemory` class method introduced while removing an effect-state lint
violation. The receiver-preserving call and a concrete
`WebConversationJourneyApi` regression test were added; the focused 3/3 and
final 11/11 reruns then passed. The stopped run is not represented as success.

### Security, privacy and tenancy review

- The patched Composer and pnpm dependency graphs report no known advisories;
  exact versions remain committed in the lockfiles.
- Demo request-body validation now rejects disallowed control code points while
  preserving tab, carriage-return and newline inputs. It does not log request
  content or add a provider path.
- Static fixes do not relax organization scoping, authorization or immutable
  content/evidence rules. PostgreSQL tenancy and operator suites pass in the
  final gate.
- The PostgreSQL test suite uses a forced writable `/tmp` storage path only in
  `phpunit.postgres.xml`; production storage configuration is unchanged.
- No raw learner audio, transcript, credential, secret or personal learner
  data was added to logs, fixtures or committed artifacts.

### Known limitations

- Vite still reports a production JavaScript chunk above 500 kB. PIPE-01 did
  not claim or implement measured code splitting.
- The non-gating peer report identifies an optional Rolldown WASM dependency
  path with incompatible `@emnapi` peer ranges. The locked native production
  path builds and tests; forcing an alpha peer override was not justified.
- Kannada, Malayalam, Tamil, Telugu and Hindi catalogues remain implementation
  previews pending independent human review; the five expected warnings stay
  visible.
- PIPE-01 is not production approval and does not close physical Android,
  specialist-content, performance, backup/restore or release gates.
- VIDEO-01 subsequently closes the recorded video range, query/storage and
  browser lifecycle gap below; PRIV-DSR-01 is now the next ordered checkpoint.

## VIDEO-01 — Bounded lesson-video delivery and client lifecycle

### Scope reviewed

- Exact-pin/current-rights authorization for catalog and media requests
- Single byte-range parsing, strong ETag/If-Range behavior and 416 problems
- Sequential local and S3 integrity verification without a full-file spool
- Publication evidence, catalog and direct-stream query amplification
- Browser-native ranges and Android authenticated bounded-Blob delivery
- Video/object-URL cleanup, loading/error/retry states and accessible naming
- OpenAPI 0.15.0 and deterministic generated TypeScript contracts

### Review result

| Check | Result | Evidence |
|---|---|---|
| Full representation | Pass | 200 includes `Accept-Ranges: bytes`, governed SHA-256 strong ETag, exact Content-Length, private no-store and verified MP4 bytes |
| Single ranges | Pass | Closed, open-ended and suffix ranges return exact 206 bytes, Content-Length and Content-Range; a matching strong If-Range is honored |
| Range fallback/error | Pass | Stale/weak If-Range, malformed and multiple ranges receive 200; valid out-of-object ranges receive RFC problem JSON, 416 and `bytes */N` |
| Integrity/no spool | Pass | Local disk hashes sequentially and seeks the same handle; fixed 64 KiB verification/response chunks replace `tmpfile` + `fpassthru` |
| S3 consistency | Pass | SDK-mock test observes exactly two GetObject commands: full bounded verification, then exact Range with the first ETag in If-Match |
| Query amplification | Pass | A complete authenticated catalog/stream request is capped at 30 queries; content components 1, media components 1, rights 2, content revisions 1 and media assets 1 for the two-video publication |
| Exact target resolution | Pass | Direct streaming resolves only the requested lesson/asset definitions after full immutable-publication and current-rights revalidation |
| Tenancy/rights | Pass | Cross-organization catalog and ranged stream return 404; current rights, active owner/program and exact learner pin remain required |
| Tamper rejection | Pass | Changed stored bytes fail the published length/SHA-256 check before any response bytes are served, including ranged requests |
| Browser lifecycle | Pass | Native video keeps cookie/range semantics; media error/source switch/unmount pause and clear the element, revoke owned URLs, announce error and permit retry |
| Android bound | Pass | Authenticated fetch requires catalog size ≤20 MB, exact Content-Length and MP4 type, rejects overrun/truncation, then owns a revocable Blob URL |
| Accessibility | Pass | Unique heading/caption IDs, labelled controls, polite loading status, alert errors and named retry buttons have component coverage; 11 Chromium journeys remain green |
| API/client drift | Pass | OpenAPI 0.15.0 describes Range/If-Range and 200/206/416 headers/bodies; generated clients match |
| Migration/configuration | None | No database migration, foundation-schema change, environment variable or production disk setting was added |
| Architecture decision | No ADR required | D-0124 records an ADR-0013/master-plan-conforming implementation constraint; authentication/revocation remains app-proxied |

### Commands run

| Command | Result |
|---|---|
| Byte-range and local/S3 object-store tests | Pass, 18 tests / 63 assertions |
| Focused PostgreSQL publication/rights/tenant/video filter | Pass, 24 tests / 367 assertions |
| Full PHPStan level 8 | Pass, zero errors |
| Full authored-source ESLint | Pass, zero warnings |
| Web TypeScript | Pass |
| OpenAPI lint/policy/generated-client check | Pass; document version 0.15.0 |
| Full web Vitest rerun | Pass, 37 files / 142 tests |
| Final pinned-toolchain `make verify` | Pass; 120 SQLite tests / 932 assertions, 201 PostgreSQL tests / 2,682 assertions and 182 package/web tests; audits, format, static, locale, contract, lint and type gates green |
| `make e2e` | Pass, 11/11 Chromium journeys |
| `make build` | Pass; Laravel optimize/cleanup and 105-module production web build; existing chunk-size warning remains visible |
| `make ai-eval` | Not run; no prompt, model, provider or AI orchestration behavior changed |

### Security, privacy and tenancy review

- Every byte response still requires the authenticated learner's active
  organization membership, exact enrollment publication pin, active owner and
  unsuspended program plus current learner-delivery rights. Range headers never
  supply organization or content authority.
- Cross-tenant and guessed asset/lesson/enrollment combinations remain generic
  404 responses. Storage keys never enter the API payload, ETag contains only
  the already-governed content digest, and no signed/public URL was introduced.
- S3's second read is bound to the object verified by the first read with
  `If-Match`, failing closed on concurrent replacement. Generic non-seekable
  stores fail closed rather than reopening an unverified object.
- No raw media bytes, storage keys, learner identifiers, transcripts,
  credentials or secrets were added to logs. Tests use synthetic byte strings
  and an SDK mock, not learner data or unlicensed media.
- Publication evidence batching retains exact component-count/key checks,
  current rights expiry/withdrawal/supersession rules and authority revocation;
  affected publication, sharing and emergency PostgreSQL tests pass.

### Known limitations

- Only one range is served. Multipart Range requests are intentionally ignored
  and receive the complete representation; `multipart/byteranges` is not
  implemented.
- Integrity remains fail-closed and intentionally costs one full sequential
  storage read per authorized response (bounded by 20 MB). S3 then reads the
  requested range, so worst-case storage transfer is object size plus range
  size. No trusted checksum cache or CDN path was approved.
- Android bearer playback still needs a complete in-memory Blob after a
  bounded incremental read. A native authenticated media data source and
  physical-device memory/network measurements remain open.
- S3 command shape and consistency are covered with the official SDK mock, not
  a live production provider/load test. Representative concurrency, latency
  and egress measurement remain release work.
- The existing >500 kB Vite JavaScript chunk warning remains. VIDEO-01 did not
  opportunistically implement unrelated code splitting.
- Independent security, privacy, tenancy, accessibility and physical Android
  review remain production gates. VIDEO-01 is not Play or production approval.
- PRIV-DSR-01 is next: inventory and close privacy export/erasure coverage
  across the full learner-linked data model.

## ADMIN-DISC-01 — reference admin functional inventory and parity plan

### Scope reviewed

- Authenticated reference navigation and read-only list/add/edit/detail page
  metadata: headings, fields, table columns, action types and route families
- Local operator API/UI, tenancy, identity, curriculum, publication, media,
  learning-evidence, privacy, audit and MFA foundations
- Master-plan core/deferred boundaries and accepted ADR-0001 through ADR-0004,
  ADR-0007 and ADR-0012 constraints
- Proposed capability mapping, experience improvements, checkpoint order and
  material policy decision

### Review result

| Check | Result | Evidence |
|---|---|---|
| Feasibility | Pass | Every observed reference feature family maps to an existing or bounded new product domain in `ADMIN_REFERENCE_PARITY_PLAN.md` |
| Existing coverage | Partial by design | Current console exposes content status, audit, analytics, diagnostics, privacy operations and MFA; full people, authoring, messaging, groups, assignments, CMS, entitlements and settings workflows are not claimed |
| Architecture fit | Pass with decision gate | Laravel modular-monolith, React web, project-owned adapters and existing domain boundaries remain suitable; DR-0014 is required before deferred scope or authoritative schema changes |
| Tenancy/security | Pass at plan level | Membership-derived context, scoped RBAC, web-only TOTP, no super-admin bypass, cross-tenant tests and non-hard-delete lifecycle rules are mandatory checkpoint exits |
| Privacy | Pass at plan level | No reference records or media enter the repository; messages, learner work, recordings and reports require purpose/retention/access rules before implementation |
| Product authority | Resolved subsequently | This planning review correctly stopped while DR-0014 was open; Option A was later accepted under APP-0039 / ADR-0014 / D-0125 as recorded in the decision review below |
| Migration/configuration | None | No migration, API, generated client, dependency, environment variable or production configuration changed |
| Product behavior | None | ADMIN-DISC-01 is discovery/planning only |

### Validation performed

- Read-only authenticated inventory of the reference portal's navigation and
  non-mutating page contracts; no create/update/publish/send/delete/upload
  action was called.
- Repository route, operator UI/API and foundation-schema inventory.
- Local Markdown-link and whitespace validation for the planning records.

### Known limitations

- The inventory proves visible functional surfaces, not undocumented business
  rules, background jobs or production data semantics in the reference system.
- No reference-source code, API contract or database was available; later
  checkpoints must specify behavior independently and test it here.
- At this planning review, DR-0014 was still open. It was subsequently
  resolved by Option A; the existing remediation checkpoints and the later
  communication, entitlement/provider and legacy-data decisions remain
  genuine gates.
- This review is not feature implementation, independent specialist review,
  reference-data migration or production approval.

## DR-0014 — Option A secure reference-admin parity decision

### Scope reviewed

- Exact human approval statement and pre-resolution proposal digest
- Exact approved parity-specification digest and checkpoint order
- APP-0039 approval evidence, ADR-0014 and D-0125 consistency
- Master-plan 3.2 amendments at §§8.3, 9.2, 16.5 and 31
- Security, privacy, tenancy, authoritative-record and later-gate boundaries
- Separation between scope approval and any later ADMIN implementation or
  production acceptance

### Review result

| Check | Result | Evidence |
|---|---|---|
| Exact requested statement supplied | Pass | `Approve DR-0014 Option A as written.` |
| Approved DR artifact durably identified | Pass | APP-0039 records reconstructed pre-resolution SHA-256 `7a88bc31444b4e99107c5b4def16c42d0efc18a07bcc587a235737934dbd4d10` |
| Approved parity specification durably identified | Pass | APP-0039 records reconstructed pre-annotation SHA-256 `caa24f8dc581c1ed755be8c90af53bc45a6ff8d678eaccbd3b0c6278a941294b` |
| Required authority recorded | Pass | APP-0039 records Accountable Product Owner, Technical Decision Owner, Security Reviewer, Privacy Reviewer and Tenancy Reviewer development roles |
| ADR and master plan changed together | Pass | ADR-0014 explicitly amends the master plan; canonical version 3.2 contains all four authorized section changes |
| Approved feature specification | Pass | `ADMIN_REFERENCE_PARITY_PLAN.md` is approved for functional intent and preserves its ADMIN-GOV-01 through ADMIN-ACCEPT-01 order |
| Existing remediation order preserved | Pass | PRIV-DSR-01, REL-01 and MAINT-01 remain ahead of ADMIN-GOV-01 |
| Unsafe literal parity excluded | Pass (policy) | Administrator-chosen passwords, ordinary hard deletion, super-admin bypass, mutable released evidence and ungoverned personal/media data are not approved target behavior |
| Deterministic authority preserved | Pass (policy) | AI cannot set marks, human-review results, mastery, progression, attendance or entitlements |
| Migration/configuration/API/product behavior | None | No feature schema, migration, API, generated client, dependency, environment variable, configuration or runtime behavior changed in this decision task |
| Independent production review | Open | Consolidated development roles do not replace independent security, privacy or tenancy review, qualified legal review where applicable, or production authorization |

### Security, privacy and tenancy review

- Every later tenant-owned resource remains membership-context derived,
  permission checked, organization constrained and subject to same-tenant and
  cross-tenant tests. Fresh web TOTP is additional authorization evidence and
  never supplies tenant, membership or role authority.
- Messages, attachments, submissions, support notes, recordings and personal
  reports remain private content. Purpose, audience, access, retention,
  deletion and moderation rules must be fixed before their affected writes;
  raw content cannot enter logs, analytics, audit or outbox payloads.
- Published content, submitted evidence, entitlement history, human-review
  corrections and privileged audit remain immutable or append-only. AI remains
  advisory and cannot create the listed authoritative outcomes.
- The approval imports no reference records, source, credentials, personal
  data or media and grants no production-data migration or media-licensing
  authority.

### Verification performed

| Command | Result |
|---|---|
| Plain host `make verify` | Failed at the toolchain guard before verification: host Node.js 22.22.1; required 24.18.1 |
| Pinned Node.js 24.18.1 / PHP 8.4.23 `make verify` | Pass: Composer validation/audit, Pint, PHPStan, 120 SQLite tests / 932 assertions, 201 PostgreSQL tests / 2,682 assertions, frozen pnpm install/audit, locale governance, OpenAPI/generated-client drift, ESLint, recursive TypeScript and 182 package/web tests |
| Read-only DR-0014 governance checker | Pass: 9 affected files; reconstructed proposal/specification hashes, ADR front matter, headings, fences, local links, IDs, open-table state and master-plan markers |
| `git diff --check` | Pass |

`make e2e`, `make ai-eval` and `make build` were not run because this bounded
decision task changed no critical user journey, provider/orchestration path or
packaging/deployment behavior.

### Known limitations and retained gates

- ADMIN-GOV-01 has not frozen capability, permission, ownership, retention or
  route matrices and no ADMIN feature implementation is accepted. It remains
  scheduled only after PRIV-DSR-01, REL-01 and MAINT-01.
- Communication purpose/retention/moderation, payment-provider/spend,
  production-data migration/identity matching/media licensing and every
  checkpoint exit remain separately gated.
- Open peer-to-peer social chat, in-product conferencing/class recording,
  consumer checkout/coupons/accounting and broad attendance/LMS semantics
  remain deferred.
- Approval is not independent specialist review, reference-data import,
  deployment, production release or advance acceptance of unbuilt work.

## PRIV-DSR-01 — full learner-linked export and deterministic erasure

### Scope reviewed

- Every current foundation-schema table with a direct learner membership,
  global user/authentication or schema-level membership/user actor link
- External voice source objects, device-only encrypted offline records,
  immutable/append-only authority and derived personalization projections
- Existing operator privacy authorization, fresh-MFA, idempotency, audit,
  transaction and ADR-0007 deletion/reconciliation behavior
- Multi-organization identity boundaries and other-tenant identifier exposure
- OpenAPI 0.16.0, generated TypeScript contracts, operator recovery guidance
  and historical PR-013 acceptance wording

### Review result

| Check | Result | Evidence |
|---|---|---|
| Complete class inventory | Pass | `LearnerPrivacyInventory` emits a fixed `learner-privacy-v1` group for every mapped class, including zero-count groups, without per-table row caps |
| Sensitive-value exclusions | Pass | Credential material, cryptographic digests, audio, transcripts, storage keys, provider secret payloads, session payload/IP/user-agent and outbox bodies are not returned |
| Deterministic erasure outcome | Pass | A pre-mutation count/action/basis matrix is persisted on the erasure receipt and replayed unchanged |
| Mutable personal data | Pass | Role links and error projections are deleted; open enrollments are withdrawn; every memory fact and prior stored export body is redacted |
| Authoritative retention | Pass | Append-only onboarding, immutable learning/conversation/consent/governance evidence, deterministic projections, audit and privacy/deletion receipts remain with an explicit basis |
| Credential closure | Pass | Server sessions are deleted; web sessions, mobile families and OAuth credentials are globally revoked; the target mobile tenant selection is cleared |
| Multi-organization identity | Pass | Only the target membership ends; the global user, identity, MFA and another organization membership remain. Other-tenant mobile selection IDs and audit rows are excluded from the export |
| Voice failure/recovery | Pass | Every recording links to the shared durable deletion receipt; storage work starts after commit, failed source deletion remains retryable, and privacy status reconciles to completion |
| Idempotency/tenancy | Pass | Exact export/erasure replays return the original receipt, conflicting reuse returns 409, foreign targets return generic 404 and regression data covers a shared global user |
| API/client drift | Pass | OpenAPI 0.16.0 and generated TypeScript types/schemas/SDK represent versioned record groups and persisted per-class erasure outcomes |
| Migration/configuration | Pass | Migration `2026_08_09_000090_add_privacy_erasure_outcomes.php` adds nullable JSONB `outcome_json`; no environment variable, provider or production-storage configuration was added |
| Architecture decision | No ADR required | D-0126 records an ADR-0002/0003/0004/0006/0007-conforming implementation constraint; no accepted architecture or master-plan boundary changed |

### Commands run

| Command | Result |
|---|---|
| Focused default PHPUnit filter | Returned `No tests found` because `phpunit.xml` intentionally contains Unit/Feature only; this run is not counted as privacy integration evidence |
| Focused PostgreSQL `OperatorPrivacySupportTest` during implementation | Initially exposed PostgreSQL append-only protection when profile/goal deletion was attempted; implementation was corrected to retain that authority |
| Final focused PostgreSQL `OperatorPrivacySupportTest` | Pass, 3 tests / 119 assertions, including shared-identity tenant leakage and deletion retry/reconciliation |
| Targeted Pint and full PHPStan level 8 | Pass; Pint formatted the inventory, PHPStan reports zero errors |
| Final pinned-toolchain `make verify` | Pass; Composer/pnpm audits, Pint, PHPStan, 120 SQLite tests / 932 assertions, 201 PostgreSQL tests / 2,727 assertions, locale governance, OpenAPI/client drift, ESLint, recursive TypeScript and 182 package/web tests are green |
| `make e2e` | Pass, 11/11 Chromium journeys; the invoked TypeScript/Vite web build also succeeds with the existing chunk-size warning |
| `git diff --check` | Pass |
| `make ai-eval` | Not run; no prompt, model, provider or AI orchestration behavior changed |
| Packaging/deployment `make build` | Not run; no packaging or deployment behavior changed |

### Security, privacy and tenancy review

- The current organization and learner membership are resolved from the
  authenticated operator context; caller-supplied tenant or global-user IDs
  never become authority. Foreign targets remain indistinguishable from
  missing records.
- Global authentication metadata is linked through the selected membership's
  user only. Mobile families disclose whether they selected this target, never
  another tenant's organization/membership IDs. Audit rows are restricted to
  the selected organization plus the user's global authentication lifecycle.
- Erasure database writes and voice intents commit atomically. Storage I/O is
  after commit, content-free outbox work retains the key only until confirmed
  deletion, and no optimistic terminal state is written on failure.
- The global identity and other memberships remain because ADR-0003 permits
  multi-organization identity. Credentials are revoked globally as a safe
  account-security boundary, so the user must reauthenticate for an unaffected
  membership.
- No raw audio, transcript, credential, digest, object key, provider payload or
  learner export body was added to logs, audits or outbox payloads. Tests use
  synthetic records and bytes only.

### Known limitations and retained gates

- The synchronous export has no silent cap. Production capacity/latency and an
  approved encrypted case-delivery mechanism require measurement before use at
  large record volumes.
- Jurisdiction-specific legal holds, qualified retention/access advice,
  identity closure/phone reassignment and final disposition when no membership
  remains are outside this organization-membership operation.
- A disconnected/lost client cannot be remotely inspected or wiped. Global
  credential revocation prevents new authority but does not prove destruction
  of an encrypted device cache.
- Backup tombstone propagation and restore evidence remain REL-01 work.
- Consolidated development review is not independent legal, privacy, security
  or tenancy review and is not production authorization.

## DR-0015 — Option A development-continuation boundary

### Scope reviewed

- Exact human approval statement and pre-resolution DR-0015 proposal digest
- ADR-0001 authority, source-of-truth and independent-review evidence rules
- Master-plan qualified-legal-validation and production boundaries
- Separation between uninterrupted non-production execution and any claim that
  missing legal or specialist review occurred or was removed

### Review result

| Check | Result | Evidence |
|---|---|---|
| Exact requested statement supplied | Pass | `Approve DR-0015 Option A as written.` |
| Approved artifact durably identified | Pass | APP-0040 records pre-resolution SHA-256 `a40c05bc4bd590e83102720b4eb18b1e0f0a7eb12c84c89883faf0cbffc6355d` |
| Development continuation | Accepted | REL-01, MAINT-01 and later approved non-production checkpoints may proceed without pausing for or repeatedly requesting the retained reviews |
| Existing governance | Preserved | ADR-0001 and master-plan legal/independent pre-production gates are not amended |
| Evidence integrity | Preserved | Missing legal or independent review cannot be described as completed, passed, waived or equivalent to consolidated development review |
| Production/public release | Not authorized | No deployment, production acceptance, Play public release or real-learner production-data authority is inferred |
| Architecture/master plan | No change | Option A expressly requires no ADR or master-plan amendment |
| Migration/configuration/API/product behavior | None | No schema, migration, endpoint, generated client, dependency, environment variable, configuration or runtime behavior changed |

### Verification performed

- Confirmed APP-0040, D-0127 and DR-0015 resolution identifiers and links are
  mutually consistent.
- Confirmed DR-0015 is absent from the open-decision table and present in the
  resolved-decision table.
- Confirmed the accepted proposal digest was captured before resolution
  annotations.
- `git diff --check` passes.

`make verify`, `make e2e`, `make ai-eval` and `make build` were not rerun for
this documentation-only governance decision. The immediately preceding final
PRIV-DSR-01 gates remain recorded separately; this task changed no executable,
contract, provider, packaging or deployment behavior.

### Retained limitations

- Option A prevents review scheduling from blocking ordinary development; it
  does not make the reviews optional for production.
- Option B and any removal of the internal safeguards require a separate exact
  superseding ADR/master-plan amendment.
- External legal, contractual, regulator, platform and store requirements
  cannot be satisfied by this repository approval record alone.

## REL-01 — fail-closed release, outbox and recovery gates

### Scope reviewed

- GitHub Actions enforcement for PostgreSQL/full verification, critical web
  E2E, production build, Android unit/APK build and operations drills
- Voice source-deletion leasing, capped backoff, terminal failure state,
  scheduled exit signal and operator recovery
- Readiness load evidence and PostgreSQL dump/restore cleanup, comparison and
  threshold behavior
- Safe evidence boundaries for tenant, learner, recording, object-store and
  privacy data

### Delivered behavior

| Check | Result | Evidence |
|---|---|---|
| Critical CI jobs | Pass (repository contract) | `verify`, `android` and `operations` are required independently |
| Aggregate release result | Pass | `release-gate` runs with `always()` and accepts only three exact `success` results |
| Workflow drift prevention | Pass | `pnpm run release:check` validates job commands/dependencies plus full-SHA action and PostgreSQL digest pins |
| Android gate | Pass | Production Capacitor sync followed by `testDebugUnitTest assembleDebug`; 102 Gradle tasks, build successful |
| Retry/dead-letter boundary | Pass | Five attempts by default; terminal failure remains unpublished and retains key/non-terminal receipt |
| Dead-letter schema state | Pass | Additive migration plus PostgreSQL check forbids a dead letter without a reason or a dead-lettered/published row |
| Safe operational health | Pass | Active/due/retrying/dead/stale counts and oldest age only; unhealthy command/schedule exit is non-zero |
| Recovery | Pass | Only an exact unpublished voice-deletion dead letter can reset to due work; success then reconciles receipt/privacy state |
| Readiness drill | Pass | 100/100 HTTP 200, concurrency 10, 0 failures, p95 202 ms under explicit 1,000 ms drill threshold |
| Load failure path | Pass (expected non-zero) | Three requests to an unreachable loopback port wrote `passed=false`, `fail=3` and returned failure |
| Logical backup/restore | Pass | PostgreSQL 18.4 test DB; archive list/restore, column schema, migration ledger and deletion/tombstone aggregate state match; RTO 1 s |
| Drill evidence integrity | Pass | JSON includes threshold/result, commit and working-tree cleanliness; raw rows and credentials are absent |

### Automated verification

- Focused PHPStan: new/changed voice monitor, deletion service, privacy
  inventory and console routes — pass, zero errors.
- Focused PostgreSQL `VoiceRecordingLifecycleTest`: 7 tests / 94 assertions.
- Focused PostgreSQL `OperatorPrivacySupportTest`: 3 tests / 119 assertions.
- Final `make verify`: pass — Composer/pnpm audits; Pint; PHPStan level 8;
  120 SQLite tests / 932 assertions; 203 PostgreSQL tests / 2,759
  assertions; locale governance; OpenAPI/generated-client drift;
  `release:check`; ESLint; recursive TypeScript; 182 package/web tests.
- `make e2e`: pass — 11/11 Chromium journeys.
- `make build`: pass — Laravel production optimize/cleanup and web bundle.
- `make android-closed-beta` with synthetic `.invalid` CI configuration:
  pass — web build, production Capacitor sync, native unit task and debug APK.
- `make backup-restore-drill` against `global_english_test`: pass; gitignored
  evidence `backup-restore-20260809T043426Z.json`.
- `make load-soak` against the real loopback Laravel readiness route: pass;
  gitignored evidence `load-soak-20260809T043439Z.json`.
- `bash -n` for both drill scripts and `git diff --check`: pass.

The first full verification run exposed a pre-existing Level Videos test race:
it waited only for the API invocation and asserted before the empty response
removed the loading panel. The assertion now waits for the settled empty state;
the focused test and complete verification rerun pass. No product behavior was
changed for that correction.

### Migration and configuration

- `2026_08_09_000100_add_outbox_dead_letter_state.php` adds
  `dead_lettered_at`, `dead_letter_reason_code`, a delivery-state index and a
  PostgreSQL consistency check.
- New safe defaults: `VOICE_DELETION_MAX_ATTEMPTS=5` and
  `VOICE_DELETION_OUTBOX_STALE_SECONDS=3600`.
- No HTTP API or generated client contract changed.

### Security, privacy and tenancy review

- Existing outbox organization ownership and scope checks remain intact. The
  worker still resolves the deletion request and recording inside the same
  organization before object I/O.
- A terminal dependency failure cannot clear `source_object_key`, set deletion
  completion, publish the event or reconcile a privacy request as complete.
- Health and requeue output contains no organization, learner, membership,
  recording, deletion-request, object-key, payload, audio or transcript value.
  Stable error codes remain database-only operational metadata.
- The restore comparison holds column-schema/migration text and aggregate counts only
  in process, prints only equality booleans and uses synthetic/test data. The
  temporary dump is mode 0600 and removed under an exit trap.
- Requeue is CLI-only and requires the exact dead-letter event ULID; no web or
  Android authorization surface was added.

### Known limitations and retained gates

- The workflow contract passed locally, but GitHub-hosted CI has not run on a
  commit containing these working-tree changes. Branch protection must require
  the stable `release-gate` check.
- The readiness drill is not the authenticated normal-API SLO, a representative
  2× pilot-peak load test, a long soak or production capacity evidence.
- The logical restore is not encrypted production backup, offsite retention,
  continuous WAL archiving or point-in-time recovery/RPO evidence.
- Physical DM-01/DM-02 device results, signed AAB, production alert routing,
  independent reviews and explicit production/Play release approval remain
  open. D-0127 permits continued development but does not satisfy them.

## MAINT-01 — bounded duplication, query/index and status maintenance

### Scope reviewed

- Header-only idempotency validation across the 22 replay-safe request classes
- Repeated synthetic permission, TOTP-factor and privileged-session fixtures
- Exact PostgreSQL plans for the three support-diagnostics queries
- Isolated Laravel test-storage bootstrapping and stale status material

### Delivered behavior

| Check | Result | Evidence |
|---|---|---|
| Request contract | Pass, unchanged | One documented base request adds the required UUID/ULID rule after payload/route data and always overwrites it from `Idempotency-Key` |
| Body non-authority | Pass | A body `idempotency_key` cannot replace a missing or invalid header |
| Test fixtures | Pass | Common organization-owned permission grants, synthetic TOTP factors and privileged sessions replace repeated helpers |
| Isolated storage | Pass | The common test base creates missing directories with requested mode 0700 before Laravel resolves compiled views; an incomplete PostgreSQL root passes without manual seeding |
| Attempt diagnostics | Pass | `(organization_id, status, updated_at, id)` selected with no `Sort` |
| Voice-failure diagnostics | Pass | backward scan of `(organization_id, status, updated_at, id)` selected with no `Sort` |
| Deletion diagnostics | Pass | backward scan of `(organization_id, requested_at, id, status)` selected with no `Sort` |
| Documentation | Pass | Current state is separated from historical quality, decision and approval evidence |
| Architecture/API | No change | No ADR, endpoint, response, OpenAPI/generated-client, provider, prompt or authoritative-record behavior changed |

### Automated verification

- Pre-refactor request baseline: 9 tests / 32 assertions passed.
- Post-refactor request regression: 12 tests / 39 assertions passed.
- Focused PostgreSQL fixture/query-plan regression: 53 tests / 732 assertions
  passed.
- Dedicated PostgreSQL JSON plan contract: 3 tests / 18 assertions passed.
- PostgreSQL migration rollback/reapply: passed; the plan contract then passed
  again with 3 tests / 18 assertions.
- PostgreSQL suite rerun from the incomplete isolated storage root: 206 tests /
  2,777 assertions passed.
- Final `make verify`: pass — Composer/pnpm audits; Pint; PHPStan level 8;
  123 SQLite tests / 939 assertions; 206 PostgreSQL tests / 2,777 assertions;
  locale governance; OpenAPI/generated-client drift; release-contract
  validation; ESLint; recursive TypeScript; and 182 package/web tests.

The first isolated-storage gate attempts failed before product assertions
because their temporary roots lacked `framework/views`. The test base now
creates every missing required directory with requested mode 0700 before
framework bootstrap; the incomplete-root PostgreSQL rerun and final complete
gate pass.

### Migration and configuration

- `2026_08_09_000110_add_support_diagnostics_indexes.php` adds only the three
  named tenant-leading indexes and drops only those indexes on rollback. Its
  PostgreSQL rollback and forward paths both passed.
- The PostgreSQL PHPUnit storage override remains test-only; no runtime
  configuration or environment contract was added.
- No OpenAPI or generated-client update was required because the HTTP contract
  is unchanged.

### Security, privacy and tenancy review

- Header-only replay-key authority remains fail closed; payload data cannot
  establish a replay key.
- Every new index leads with `organization_id`; plan checks use a synthetic
  tenant identifier and inspect metadata only.
- Shared authorization fixtures remain under the test namespace, create only
  synthetic organization-owned grants and never weaken runtime middleware.
- Missing temporary-storage directories request mode 0700; existing
  caller-supplied root permissions are not rewritten. Tests contain no real
  learner, audio, transcript, credential or provider data.

### Known limitations and retained gates

- Empty-schema forced-index plans prove eligibility and ordering, not
  production selectivity, cache behavior, write overhead or latency.
- Broader curriculum value-reader duplication and large historical test files
  were deliberately left outside this bounded checkpoint.
- GitHub-hosted CI, representative production-volume plans, physical devices,
  independent reviews and production/public release remain separate gates.
- The existing Vite chunk-size warning and optional Rolldown peer mismatch
  remain measured maintenance limitations.

## ADMIN-GOV-01 — operator governance freeze

### Scope reviewed

- Approved ADMIN capability families and checkpoint order under ADR-0014
- Existing platform capabilities, seeded permissions, role packs and
  organization authorization behavior
- Existing operator route/middleware/API inventory and privileged MFA policy
- Accepted ownership, authentication, content, offline, voice, video,
  retention, audit and deterministic-authority decisions
- Retained policy gates for private communications, support notes,
  provider/payment work, platform configuration, data import and release

### Delivered governance

| Check | Result | Evidence |
|---|---|---|
| Capability freeze | Pass | Every ADMIN checkpoint has an explicit authoritative boundary and fail-closed state in `ADMIN_GOVERNANCE_MATRIX.md` |
| Platform authority | Pass, unchanged | The 5 existing named capabilities remain the complete allowlist; no ADMIN role receives one and no general super-admin path exists |
| Permission freeze | Pass, unchanged | All 25 existing seeded keys are inventoried; future keys are documentation-only reservations that later slices must seed and test themselves |
| Ownership freeze | Pass | Platform-global identity/catalogue records and organization-owned domain families are separated; public editorial remains owner-organization content exposed through an immutable publication |
| Lifecycle/retention freeze | Pass | Existing ADR periods are preserved; unresolved private-data classes remain blocked instead of inheriting indefinite retention |
| Route freeze | Pass | Read, command, bulk, export, public, platform and blocked profiles are fixed; ordinary operator HTTP `DELETE` is prohibited |
| Executable route guard | Pass | Every registered operator route is asserted first-party, web-only, organization-context scoped and consistently named; all mutations require fresh MFA |
| Architecture/API/runtime | No change | No feature schema, migration, seed, endpoint, OpenAPI/generated-client contract, configuration, provider, prompt or runtime behavior was added |

### Automated verification

- Pre-change `PrivilegedMfaPolicyTest`: 8 tests / 11 assertions passed.
- Focused PostgreSQL operator content/privacy/seeder baseline: 8 tests / 243
  assertions passed.
- Final `AdminGovernanceRoutePolicyTest`: 3 tests / 85 assertions passed.
- The first full gate stopped at PHPStan `foreach.nonIterable` because Laravel's
  facade exposes `RouteCollectionInterface`; the test now calls the
  interface's documented `getRoutes()` array. The focused test and PHPStan
  then passed.
- Final `make verify`: pass — Composer/pnpm audits; Pint; PHPStan level 8;
  126 SQLite tests / 1,024 assertions; 206 PostgreSQL tests / 2,777 assertions;
  locale governance; OpenAPI/generated-client drift; release-contract
  validation; ESLint; recursive TypeScript; and 182 package/web tests.
- `git diff --check`, PHP syntax validation and a read-only route count of 13:
  passed.

`make e2e`, `make build` and `make ai-eval` were not run because this
governance-only checkpoint changes no critical user journey,
packaging/deployment surface, prompt, model, provider or orchestration path.

### Migration and configuration

- None. No database migration, seed, environment variable, dependency,
  filesystem disk or runtime configuration was added.
- OpenAPI and generated clients remain unchanged because no HTTP contract
  changed.

### Security, privacy and tenancy review

- Common route policy now has executable regression coverage. Domain
  permissions, current membership, target organization and authority
  predicates remain independently decisive.
- Reserved permission or route names grant no authority and create no
  discoverable surface. Each later slice must add same-tenant and cross-tenant
  tests when it activates its own names.
- Support notes, notices, messages and moderation content remain without write
  schema or route until purpose/access/retention/deletion policy is accepted.
  Raw private content remains prohibited from logs, analytics, audit and
  outbox payloads.
- Platform/global work cannot be inferred from an organization role. A new
  platform operation requires a separately named capability, target scope,
  reason, MFA and audit contract.

### Known limitations and retained gates

- The matrix reserves future design space; it is not implementation or
  acceptance evidence for ADMIN-SHELL-01 or any later checkpoint.
- Exact support-note, communication, moderation, facilitated-session,
  provider/payment, platform-configuration and legacy-import policies remain
  unresolved where the matrix marks them blocked.
- GitHub-hosted CI, independent reviews, production-volume evidence,
  deployment and production/public release remain separate gates.
- At ADMIN-GOV-01 close, ADMIN-SHELL-01 was the only authorized next checkpoint
  and could not create the domain schemas reserved for later work.

## ADMIN-SHELL-01 — permission-aware read-only operator workspace

### Scope reviewed

- The ADMIN-SHELL-01 route, permission and retention rows frozen by
  ADMIN-GOV-01 and the bounded implementation specification
- Existing content, pilot-analytics, support-diagnostic and safe audit queries
- First-party web authentication, membership-derived organization context and
  role-pack versus atomic-permission boundaries
- Responsive React shell behavior, independent panel recovery, search, audit
  detail, localization and browser-storage behavior
- OpenAPI 3.1.1, generated TypeScript contracts and common operator-route
  policy coverage

### Delivered behavior

| Check | Result | Evidence |
|---|---|---|
| Permission-derived manifest | Pass | `GET /operator/overview` returns only safe organization metadata and navigation/panel keys independently derived from existing content, audit and support read permissions; permission keys are absent |
| Safe federated search | Pass | `GET /operator/search` searches fixed descriptor fields only, applies SQL organization scope per source, limits pages to 25 and binds encrypted continuation to purpose, tenant, normalized query and authorized source set |
| Audit continuation | Pass | Existing safe action/field allowlists remain unchanged; descending `(occurred_at, id)` keyset continuation is encrypted and organization bound |
| Hidden denial and validation | Pass | A caller without any accepted read authority receives the ordinary 404; malformed, cross-query and cross-organization cursors return safe 422 validation failures |
| Client authorization boundary | Pass | The client uses non-learner Sanctum membership only as an affordance and never infers authority from role names; the server overview remains decisive |
| Independent task panels | Pass | Pilot, review, publication, diagnostics and audit resources load/retry independently, so one failure preserves successful work |
| Search and audit UX | Pass | Debounced/explicit ephemeral search, cursor “load more,” keyboard-operable audit rows and an adjacent safe-field detail pane are implemented without browser persistence |
| Responsive accessibility | Pass | Semantic task navigation, labelled states, live announcements, minimum target sizes, visible focus, axe-clean desktop/mobile checks and no horizontal overflow at 320 CSS pixels |
| API/client/localization drift | Pass | OpenAPI 0.17.0, five generated contract files, exact six-catalogue key parity, source-linked revision `2026-08-09.1` and canonical hashes validate together |
| Architecture/authoritative records | No change | Overview/search are non-authoritative live projections; no audit/outbox write, search index, cache record, schema, migration, permission seed or platform capability was added |

### Verification performed

| Command or suite | Result |
|---|---|
| PHP syntax checks, targeted Pint and PHPStan level 8 | Pass after replacing dynamic SQL interpolation with a literal allowlist and correcting the collection-key contract; no suppression or baseline was added |
| Focused PostgreSQL `OperatorShellTest` | Pass, 3 tests / 74 assertions, including same-tenant paging, foreign-row exclusion, permission omission, hidden denial and malformed/cross-boundary cursors |
| Focused `OperatorConsole`, API-client and application tests | Pass, 19 tests |
| Full web test suite | Pass, 38 files / 148 tests |
| Locale validator | Pass for all 6 catalogues with the 5 intentional pending-independent-review warnings |
| OpenAPI lint, policy and generated-client drift | Pass at version 0.17.0 |
| Focused operator Chromium journey | Pass, 1/1 with partial failure/retry, keyboard navigation, search/audit continuation, desktop/mobile axe and 320-pixel reflow |
| Final `make verify` | Pass: Composer/pnpm validation and audits; Pint; PHPStan; 126 SQLite tests / 1,036 assertions; 209 PostgreSQL tests / 2,851 assertions; locale, OpenAPI/client, release-contract, ESLint and recursive TypeScript gates; 188 package/web tests |
| Final `make e2e` | Pass, 12/12 Chromium journeys |
| Final `make build` | Pass; Laravel cache preparation/cleanup and production Vite build succeeded with the pre-existing chunk-size warning |
| Final `git diff --check` | Pass |
| `make ai-eval` | Not run; no prompt, model, provider or orchestration behavior changed |

The first full gate correctly stopped on five PHPStan `argument.type` errors in
the new search query. The implementation was corrected to accept the actual
integer-keyed collection shape and to map every searchable column to a
literal SQL fragment. The next full run correctly stopped on one localization
test's old source revision. That explicit fixture was updated, its package
suite passed, and the subsequent complete gate passed.

### Migration and configuration

- None. No database migration, feature schema, environment variable,
  dependency, filesystem disk, provider binding, permission seed, role grant
  or platform capability was added.
- Two read-only routes and the existing audit response/cursor contract are the
  complete server surface change. OpenAPI and generated clients changed in the
  same checkpoint.

### Security, privacy and tenancy review

- Overview, search and audit remain behind `auth.first-party` →
  `auth.web-only` → `organization.context`. The route invariant suite covers
  both new paths, while each query independently checks its exact atomic read
  authority.
- Search source tables are organization constrained in SQL. Encrypted cursors
  cannot be replayed across tenants, normalized queries, purposes or changed
  authorized source sets. Audit uses stable keyset rather than offset
  continuation.
- Returned fields are limited to governed content labels/keys, operational
  status digests and the existing safe audit descriptors. Learner identity,
  answers, raw audio, transcripts, conversation bodies, storage keys, audit
  payloads and provider payloads are absent.
- Search terms, results and cursors remain request/component memory only. No
  database/search-index write, browser storage, analytics property, audit
  event, outbox payload or application-log field was added.
- `reports.export` remains unseeded and no export UI or route was inferred.
  Existing authoritative content, learning, privacy, voice and audit records
  are unchanged.

### Localization and accessibility review

- New strings have exact key parity in `en-IN`, `kn-IN`, `ml-IN`, `ta-IN`,
  `te-IN` and `hi-IN`. The five non-English catalogues remain honestly
  attributed implementation drafts pending independent review; production
  still resolves visibly to approved English.
- The browser path demonstrates task-nav keyboard activation, search and audit
  controls, independent error recovery, axe-clean desktop/mobile states and no
  page overflow at 320 CSS pixels. This automated evidence is not independent
  human accessibility acceptance.

### Known limitations and retained gates

- The live federated query has bounded pages but no production-volume latency
  or concurrent-update measurement. It is intentionally not a durable search
  index or saved-view implementation.
- The shell exposes only existing content, aggregate, support-digest and audit
  reads. Organization, learner, authoring, media, group, activity, review,
  communication, CMS, entitlement and configuration parity remains unbuilt.
- The existing Vite production chunk exceeds the advisory 500 kB threshold;
  the warning is unchanged and this checkpoint is not performance acceptance.
- GitHub-hosted CI, independent security/privacy/tenancy/accessibility/language
  review, production-volume evidence, deployment and production/public release
  remain separate gates.
- At ADMIN-SHELL-01 close, ADMIN-ORG-01 was the only next checkpoint under
  accepted DR-0016 Option A; its subsequent completion is recorded below.

## ADMIN-ORG-01 decision gate — resolved before implementation (9 August 2026)

### Outcome

Read-only backend, frontend and adversarial inventories found material
foundation ambiguity before ADMIN-ORG-01 implementation. DR-0016 Option A is
accepted under APP-0041 / D-0132 and now fixes read-only organization registry
metadata, permission-ceiling role delegation, staff suspension without
authority revival, selected-membership credential revocation, show-once
invitation secrets and explicit live/backup disposal.

At the decision-gate stage no schema, migration, permission definition or
grant, route, OpenAPI contract, generated client, localization key, UI control
or runtime behavior was added. `ADMIN-ORG-01_SPEC.md` is now the active
implementation contract; no later ADMIN checkpoint may begin before its exit
evidence is complete.

### Evidence and limitations

- Existing organization, membership, role, invite, cohort, appointment and
  authentication-session code/tests were inspected against the master plan,
  accepted ADRs, parity plan and governance matrix.
- The decision request and status/governance links pass `git diff --check`.
- No product test suite, migration drill, E2E journey or build was run because
  this is a documentation-only decision gate with no runtime change.
- The 365-day live-history rule, 30-day backup ceiling, settings deferral and
  lifecycle/delegation choices are accepted development authority. They are
  not implemented evidence or production-compliance approval by themselves.

## ADMIN-ORG-01 — bounded organization and staff administration

### Scope reviewed

- DR-0016 Option A / D-0132 and the complete bounded ADMIN-ORG-01
  specification
- Organization, branch, cohort, staff membership, invitation, custom role,
  derived trainer, authority-appointment and selected-credential lifecycles
- First-party web authentication, fresh privileged MFA, atomic permission
  delegation, tenant context, idempotency, optimistic concurrency and
  final-manager preservation
- Responsive People UI, generated client, OpenAPI 0.18.0, localization,
  privacy/retention maintenance and restore behavior
- Same-tenant, cross-tenant, strict body/route binding, replay, lifecycle,
  credential-selection and process-level PostgreSQL race evidence

### Delivered behavior

| Check | Result | Evidence |
|---|---|---|
| Organization boundary | Pass | The current organization registry is read-only and excludes system keys; branches/cohorts are tenant-owned, versioned and use explicit update/archive commands |
| Staff-only People scope | Pass | Membership and trainer projections exclude learners, global identity hints and other-organization relationships; display name is an existing read-only projection |
| Atomic authority | Pass | Role names grant nothing; reads and commands use exact permissions, active membership-derived context and post-lock reauthorization |
| Delegation and final manager | Pass | Custom-role grants are capped to the actor's held catalogue permissions; self-role changes and system-role mutation are rejected; concurrent mutual revocation leaves exactly one manager |
| Invitation lifecycle | Pass | Operator invitations carry only staff type and bounded expiry, return a raw code once, bind replay without recovering the secret, advance to version 2 on accept/revoke and schedule terminal digest clearing |
| Membership lifecycle | Pass | Suspension atomically removes current roles/cohort links, terminates current appointments and revokes only credentials still selected for that membership; reactivation restores none of them |
| Credential boundary | Pass | Web selection is authoritative in the authentication-session row; membership-session detail exposes counts/times only; revocation rechecks selection and leaves unselected/other-membership credentials unchanged |
| Trainer/appointment boundary | Pass | Trainer profiles remain derived; no biography/contact/qualification table was added; private existing appointment evidence is visible only to the appointee or exact authority manager |
| Replay and history | Pass | Organization-wide actor/request-bound receipts return exact safe results without duplicate writes; content-minimized lifecycle events and safe audit are recorded transactionally |
| Retention and restore | Pass | Hourly maintenance expires invites, clears terminal digests, retries revoked session-storage cleanup and purges eligible 365-day history while preserving referenced opaque anchors; restore documentation retains the 30-day backup ceiling and current tombstone overlay requirement |
| Accessible People UI | Pass | Manifest-gated navigation, independent panel loading/retry, labelled forms, confirmation/focus restoration, conflict reload, one-time invitation acknowledgement, axe checks and 320-pixel reflow are covered |
| Contract drift | Pass | OpenAPI 0.18.0, five generated TypeScript contract files, route policy, all six catalogues and source-linked localization governance validate together |

### Verification performed

| Command or suite | Result |
|---|---|
| Focused PostgreSQL ADMIN-ORG concurrency | Pass, 5 tests / 40 assertions; both processes were observed in the PostgreSQL lock queue for final-manager, stale-version, invitation and both selected-credential races |
| Focused invitation and ADMIN-ORG API suites | Pass, 12 tests / 256 assertions |
| Earlier complete focused ADMIN-ORG backend slice | Pass, 33 tests / 655 assertions before the final concurrency cases were added |
| Full web suite | Pass, 41 files / 170 tests |
| Focused operator People Chromium journey | Pass, 2/2 |
| Final `make verify` | Pass: Composer/pnpm validation and audits; Pint; PHPStan level 8; 128 SQLite tests / 1,303 assertions; 240 PostgreSQL tests / 3,202 assertions; locale, OpenAPI/client, release-contract, ESLint and recursive TypeScript gates; 210 package/web tests |
| Final `make e2e` | Pass, 13/13 Chromium journeys |
| Final `make build` | Pass; Laravel cache preparation/cleanup and production Vite build succeeded with the existing chunk-size warning |
| Migration direction evidence | Pass in the PostgreSQL suite, including ADMIN-ORG migration down/up with terminal invitation digest restoration |
| `POSTGRES_DB=global_english_test make backup-restore-drill` | Pass; exact application schema, migration ledger and deletion/tombstone aggregate match; measured RTO 2 seconds |
| Final `git diff --check` | Pass |
| `make ai-eval` | Not run; no prompt, model, provider or orchestration behavior changed |

The full gate first exposed an invalid PHPUnit command-chain type and then a
stale organization-administrator role-pack expectation. Both underlying tests
were corrected and their focused suites passed. Full E2E then exposed a
same-document skip-link focus race; reloading the hash destination made the
test assert the actual navigation boundary and all 13 journeys passed. The new
process-level invitation race exposed that acceptance had not advanced the
new optimistic version; acceptance now advances it exactly once, and both the
concurrency and existing invitation suites pass. The final full gate then
reported one unnecessary null-safe access in the race helper; the inferred
non-null database contract was used directly, with no suppression or baseline.

The first isolated production-build attempt lacked the temporary Laravel
framework directories selected for that command. After creating only those
ephemeral `/tmp` directories, the same build passed. This was environment
preparation, not a product-code correction.

### Migration and configuration

- `2026_08_09_000120_create_admin_org_foundation.php` creates branches,
  administration command/event history and durable authentication-session
  cleanup work; it versions/lifecycles cohorts, roles, memberships and
  invitations; and it adds authoritative selected-membership binding to web
  sessions with tenant composite foreign keys and PostgreSQL checks.
- Foundation seeding adds exactly six used ADMIN-ORG permissions and grants
  the complete bounded pack to the Direct organization-administrator system
  role. `organization.settings.manage` remains absent and unseeded.
- Code-owned operator policy fixes 365-day administration history, 72-hour
  default invitation lifetime, allowed 24/72/168-hour lifetimes and terminal
  digest disposal within 60 minutes. No environment variable, dependency,
  provider binding, secret or platform capability was added.
- `operator:maintain-organization-administration` runs hourly without overlap;
  revoked web-session storage cleanup remains durable and retryable.

### Security, privacy and tenancy review

- All organization-owned queries and relationship foreign keys are tenant
  scoped. API tests cover foreign route IDs, body ownership injection and a
  shared global user whose other-organization membership/credentials remain
  unchanged.
- Mutable routes retain `auth.first-party` → `auth.web-only` →
  `organization.context` → fresh TOTP, strict request allowlists,
  `Idempotency-Key`, expected version/fingerprint, transaction and safe audit.
  Passport, unauthenticated, inactive, missing-permission and stale-MFA paths
  produce no authoritative write.
- Raw invitation codes are response-only and never persisted, replayed,
  logged, audited, sent to analytics/outbox or copied automatically. Digests
  are removed after terminal use; event/receipt bodies exclude names, request
  values, credential identifiers and private appointment evidence.
- Credential revocation is membership-selection scoped, not global account or
  recovery authority. Global users, identities, MFA and other tenant
  memberships remain outside this checkpoint.
- The UI keeps ADMIN-ORG values, filters, cursors and initial invitation codes
  in component memory; it adds no localStorage, IndexedDB, Cache Storage,
  ordinary Capacitor preference, URL/history or analytics persistence.

### Localization and accessibility review

- New People strings have exact key parity in `en-IN`, `kn-IN`, `ml-IN`,
  `ta-IN`, `te-IN` and `hi-IN`. The five non-English catalogues remain honest
  implementation previews pending independent review, with visible approved
  English production fallback.
- Browser evidence covers keyboard navigation, confirmation cancellation and
  focus restoration, fresh-MFA and conflict recovery, one-time invitation
  handling, independent panel retry, axe-clean desktop/mobile states and no
  horizontal overflow at 320 CSS pixels. This is automated evidence, not an
  independent human accessibility review.

### Known limitations and retained gates

- No writable organization setting, learner/global identity correction,
  password/PIN/MFA reset, provider invitation delivery, trainer biography,
  export, platform capability or production deployment was added.
- ADMIN-ORG history and cleanup are code-owned repository controls. Production
  backup encryption, offsite/PITR design, 30-day physical age-out, current
  external tombstone overlay, legal hold and restore custody remain unproved.
- Production-volume directory/query/maintenance behavior and concurrent load
  remain unmeasured; the local logical drill is not production RPO/RTO or
  capacity evidence.
- GitHub-hosted CI, independent security/privacy/tenancy/accessibility/language
  review, qualified legal validation, deployment and production/public release
  remain separate gates.
- The existing Vite chunk-size warning is unchanged. ADMIN-LEARNER-01 is the
  only next checkpoint, and support-note writes remain blocked until their
  separate policy is accepted.

## ADMIN-LEARNER-01 decision gate — resolved before implementation (9 August 2026)

### Outcome

The authority and implementation inventory found material ambiguity before
runtime work. The current learner profile stores immutable adult-confirmation
and onboarding evidence with an append-only goal; enrollments have immutable
curriculum pins but no version or one-current-enrollment invariant; learner
invitations have no accepted safe-role rule; and membership suspension has no
accepted selected-credential/offline-lease behavior. These choices affect
privacy, tenancy and authoritative records, so implementation was paused under
the repository working agreement until the decision was accepted.

[DR-0017](DR-0017_ADMIN_LEARNER_PROFILE_ENROLLMENT_LIFECYCLE_REQUEST.md)
Option A is accepted under APP-0042 / D-0134 and translated into
[ADMIN-LEARNER-01_SPEC.md](ADMIN-LEARNER-01_SPEC.md). It explicitly excludes
global identity/account recovery and all support-note schema, permissions,
routes, exports and UI.

### Evidence and limitations

- The master plan §§8.2, 15, 16 and 18; ADR-0002, ADR-0003, ADR-0004,
  ADR-0006, ADR-0007 and ADR-0014; privacy inventory; ADMIN parity/governance
  records; and existing learner profile, goal, enrollment, invitation, role,
  cohort/trainer and selected-credential implementation/tests were inspected.
- No schema, migration, permission seed/grant, route, OpenAPI/generated-client
  contract, localization catalogue, UI control or runtime behavior was added.
- No product, migration, E2E, build or provider suite was run because this is
  a documentation-only decision gate. Documentation whitespace/link-state
  checks are the proportionate verification recorded for this stage.
- The approval authorizes bounded non-production implementation; it does not
  accept unbuilt results. Production/public release and all existing
  independent-review gates remain separate.

## ADMIN-LEARNER-01 — bounded learner administration (9 August 2026)

### Outcome

Repository implementation is complete under DR-0017 Option A / APP-0042 /
D-0134 and the bounded specification. The delivered vertical slice covers
current-organization learner directory/detail, code-only learner-safe
invitations, append-only goal correction, versioned enrollment/cohort/pin
lifecycle, cohort-derived trainers and reversible learner suspension with
selected-credential revocation. Support notes, global identity/account
authority, learner exports, direct trainer assignment, enrollment reactivation
and production release remain excluded.

### Acceptance matrix

| Boundary | Result | Evidence |
|---|---|---|
| Tenant-safe learner reads | Pass | Current membership-derived organization scope, bounded fields, encrypted tenant-bound cursors and same/cross-tenant list/detail/relationship tests |
| Exact learner invitation authority | Pass | Only an active same-tenant role containing exactly `learning.participate`; optional active cohort; one-time code, safe replay and accept/revoke race |
| Governed profile correction | Pass | Original onboarding goal remains immutable; attributed correction history is append-only; versioned current projection advances once and learner home observes it |
| Enrollment authority | Pass | One pending/active enrollment, expected versions, cohort change, currently verified immutable curriculum pin, withdrawal and immutable completed/withdrawn history |
| Learner lifecycle | Pass | Suspension retains learner-safe roles/evidence, contains unexpected authority, revokes only still-selected credentials and reactivation restores no credential/enrollment/pin |
| Transaction and replay safety | Pass | Organization mutex, post-lock reauthorization, deterministic relationship locks, header-only actor/request-bound idempotency and safe conflict mapping |
| Privacy and retention | Pass | `learner-privacy-v2` inventories current-goal projection/corrections and linked administration events; 365-day live history, one-hour terminal invite digest and 30-day backup boundaries remain fixed |
| Accessible Learners UI | Pass | Manifest gating, independent panel retry, deliberate selection, confirmations/focus, conflict reload, explicit copy, ephemeral state, axe and 320-pixel reflow |
| Contract drift | Pass | OpenAPI 0.19.0, generated TypeScript contracts, route policy and all six governed catalogues validate together |

### Verification performed

| Command or suite | Result |
|---|---|
| Focused learner API/schema suites | Pass on SQLite and PostgreSQL, including privacy inventory and migration behavior |
| Focused learner web/API-client suites | Pass, 23 tests |
| Real PostgreSQL administration concurrency class | Pass, 11 tests / 85 assertions; both processes entered the lock queue for learner invitation, enrollment creation/lifecycle, goal correction and selection/suspension races plus retained ADMIN-ORG cases |
| `pnpm openapi:check` | Pass; OpenAPI valid, policy valid and generated contracts match |
| `pnpm locale:check` | Pass for 6 catalogues with the 5 declared pending-independent-review warnings |
| Final `make verify` | Pass: Composer/pnpm validation and audits; Pint; PHPStan level 8; 128 SQLite tests / 1,390 assertions; 249 PostgreSQL tests / 3,369 assertions; locale, OpenAPI/client and release-contract checks; ESLint; recursive TypeScript; 227 package/web tests |
| Final `make e2e` | Pass, 14/14 Chromium journeys |
| Final `make build` | Pass; Laravel cache preparation/cleanup and production Vite build succeeded with the existing chunk-size warning |
| `POSTGRES_DB=global_english_test make backup-restore-drill` | Pass; exact schema, migration ledger and deletion/learner-goal authority aggregate match; measured local RTO 1 second |
| `make ai-eval` | Not run; no prompt, model, provider or orchestration behavior changed |

The first repository-wide gate exposed one localization test still expecting
the prior source-catalogue revision. The assertion was advanced to the exact
governed `.3` revision, its focused suite passed, and the complete gate then
passed. A first focused learner-browser run exposed an ambiguous status-role
locator during simultaneous panel refresh; the assertion was narrowed to the
exact success message and all three operator journeys, then all 14 complete
journeys, passed. Synthetic concurrency-fixture validation also caught an
overlong test idempotency key and a list-shaped empty graph before the final
valid verified-publication race passed. No failure was suppressed or recorded
as success.

### Migration and configuration

- `2026_08_09_000130_create_admin_learner_foundation.php` creates the
  versioned current-goal projection and append-only correction history,
  versions enrollments, enforces one pending/active enrollment, extends shared
  administration events with tenant-bound learner/profile/enrollment links and
  installs PostgreSQL transition/append-only constraints with a reversible
  SQLite path.
- Foundation seeding adds exactly `learners.read`,
  `learners.profile.correct` and `learners.lifecycle.manage`, and grants the
  bounded learner/enrollment pack only to the Direct organization-administrator
  system role. Support-note and broad export keys remain absent.
- OpenAPI advances to 0.19.0 and generated client/types change with the API.
  No environment variable, dependency, provider binding, secret, background
  schedule or platform capability was added.

### Security, privacy and tenancy review

- Reads and mutations derive tenant authority only from the active membership;
  route/body relationships, foreign ULIDs, shared global users and every new
  tenant-owned foreign key have cross-organization rejection coverage.
- Mutations remain first-party web-only, fresh-TOTP, strict-allowlist,
  version/fingerprint guarded and transactionally audited. Passport bearers,
  missing permission, stale state and changed replay cannot write authority.
- Directory/detail deliberately exclude identity hints, credentials, raw
  answers/marks, audio, transcripts, conversation/memory content and private
  notes. Display name remains a bounded read-only projection.
- Invitation secrets exist only in the issuance response/component memory,
  are never recovered on replay, logged, audited, persisted or automatically
  copied, and terminal digests reuse the one-hour disposal path.
- `learner-privacy-v2` exports original/current/correction history and retains
  its append-only authority. Privacy erasure still withdraws current
  enrollments, advances version and retains immutable pins/evidence.
- The UI keeps filters, cursors, projections and initial codes in component
  memory. The E2E journey confirms no `localStorage` or IndexedDB persistence.

### Localization and accessibility review

- New Learners strings have exact key parity and governed hashes in `en-IN`,
  `kn-IN`, `ml-IN`, `ta-IN`, `te-IN` and `hi-IN`. The five non-English
  catalogues remain implementation previews with approved-English production
  fallback pending independent review.
- Automated browser evidence covers keyboard navigation, independent failure
  recovery, labelled bounded forms, confirmation/focus behavior, one-time-code
  acknowledgement, desktop/mobile axe scans and no horizontal overflow at 320
  CSS pixels. This is not independent human accessibility evidence.

### Known limitations and retained gates

- TOTP remains non-phishing-resistant; an already downloaded offline mission
  may remain readable only within ADR-0006's existing maximum 24-hour lease.
- Production-volume learner search, enrollment history and maintenance
  behavior has not been load tested. The encrypted-cursor and index tests are
  correctness evidence, not a production latency result.
- Production backup encryption/offsite/PITR, 30-day physical age-out, external
  tombstone overlays, legal holds and restore custody remain unproved.
- GitHub-hosted CI, independent security/privacy/tenancy/accessibility/language
  review, qualified legal validation, deployment and production/public release
  remain separate gates. The existing Vite chunk-size warning remains.

## LPL-01 private pilot and runtime-rights review — 10 August 2026

### Delivered conformance

- DR-0024 Option A is recorded under APP-0051 / D-0150 / ADR-0019. The
  implementation adds one disabled-default, local-only, exact-ID/evidence/
  asset-state bootstrap command; it is not reusable production onboarding.
- The command validates the exact Direct organization, active actor and
  membership, system administrator assignment, APP-0004/APP-0047/APP-0051,
  four exact active clean assets and exact absent/replay state under
  transaction locks. It creates one standalone program, one non-system role
  with exactly `curriculum.author` and `media.rights.approve`, one assignment
  and one pending program appointment through the existing authority service.
- The exact organization-admin appointee may read only their own appointment
  detail and accept it through the existing selected-membership, first-party
  web and fresh-TOTP path. Appointment creation/list management authority did
  not broaden.
- Safe outbox payloads now allow the stable `role_id` needed by this one
  content-free bootstrap event. No provider type or content enters domain,
  audit, outbox or command output.
- No schema migration, public API shape, generated client, UI, prompt, model,
  provider binding, publication or learner behavior changed.

### Runtime evidence

- The first command attempt found a legitimate selected upload-bound scan with
  a null optional `media_asset_id` and failed transactionally. The predicate
  was corrected to accept the existing schema's upload-bound selected scan
  while still requiring exact selected scan ID, organization, kind, hash,
  bytes, inspection `passed` and malware `clean`; focused SQLite/PostgreSQL
  coverage and the live exact-state command then passed.
- One successful command created program `01KZNK3BWD8A8NJ5A5JW0ZVZMT`, role
  `01KZNK3BWD8A8NJ5A5JW0ZVZMV` and appointment
  `01KZNK3BWRCRWG1EYK7T1ZAYYE`. The process-only switch is absent from
  persistent environment files.
- Ordinary CSRF/Sanctum OTP, exact organization selection and fresh TOTP
  succeeded. The exact appointee read and accepted the appointment via HTTP.
  A first rights request using identifiers outside DR-0024 returned the
  generic not-found response and created no row; four corrected exact requests
  each returned HTTP `201`.

| Part | Asset | Rights revision | Persisted scope |
|---|---|---|---|
| Introduction | `01KZNFYHCJSDWSPK08ZAC7G3AJ` | `01KZNKNFWGSRDBD57H18YCDPG8` | APP-0047; Android/web; IN; derivative/later delivery true; sharing false; no expiry |
| Practice | `01KZNFYJ1M911SB8YVH9P4EZZ0` | `01KZNKNFXKFRSVXRKPYWHDCF7C` | APP-0047; Android/web; IN; derivative/later delivery true; sharing false; no expiry |
| Conversation | `01KZNFYJVSZRPZCEPTTQ4QTMNT` | `01KZNKNFYDDC31CV2BR155DXVR` | APP-0047; Android/web; IN; derivative/later delivery true; sharing false; no expiry |
| Interaction | `01KZNFYK75V9WF8R5VCWRRZHNN` | `01KZNKNFZ7A5Z9JPSC2AV0QPZQ` | APP-0047; Android/web; IN; derivative/later delivery true; sharing false; no expiry |

- A read-only conformance query found exactly four approved rows with exact
  selected hashes, APP-0047 evidence hash, `accepted_media`,
  `preserve_while_referenced` and the accepted appointment. Appointment events
  are exactly `appointed`, `accepted`; the pilot has zero curriculum versions;
  bootstrap audit/outbox counts are one each.
- Replaying the exact command after appointment acceptance returned the
  original program/role/appointment and completion time with `replayed`. No
  duplicate role, assignment, appointment, audit or outbox evidence was added.

### Verification performed

| Command or suite | Result |
|---|---|
| Pint over the changed PHP files | Pass |
| SQLite `LocalLplPilotAuthorityBootstrapTest` | Pass: 4 tests / 71 assertions with upload-bound selected scans |
| PostgreSQL `LocalLplPilotAuthorityBootstrapTest` | Pass: 4 tests / 71 assertions with upload-bound selected scans |
| SQLite/PostgreSQL organization-admin own appointment read/accept path | Pass: 1 test / 14 assertions on each database |
| PostgreSQL two-worker bootstrap race | Pass: 1 test / 9 assertions; exactly one create and one replay |
| Ordinary local HTTP appointment/rights path | Pass: OTP/context/TOTP/appointment; four exact rights commands returned `201` |
| Exact live bootstrap replay and persisted-state audit | Pass |
| Final `make verify` | Pass: Composer/pnpm validation and audits; Pint; PHPStan; 144 SQLite tests / 1,815 assertions; 280 PostgreSQL tests / 4,017 assertions; locale, OpenAPI/client and release-contract checks; ESLint; recursive TypeScript; 270 package/web tests |
| Final `make e2e` | Pass: 16/16 Chromium journeys, including People and governed Media Library |
| `make ai-eval` | Not run; no prompt, model, provider or orchestration behavior changed |
| `make build` | Not run separately; no packaging/deployment behavior changed, and the E2E web server completed its production Vite build with the retained chunk-size warning |

The first two complete verification attempts reported that Laravel
Collection's `all()` result did not prove the method's promised list shape to
PHPStan. The implementation now normalizes the returned PHP array with
`array_values`; focused PHPStan and the uninterrupted final gate passed. No
error was suppressed or described as success.

### Security, privacy and tenancy review

- Every resource is locked and validated in the exact Direct organization;
  role assignment is unique to one membership and rights remain program,
  appointment, asset and selected-hash bound. Cross-tenant and wrong-state
  cases fail closed in service/API coverage.
- The role does not modify a system role and is independently visible and
  revocable. Rights exercise still requires ordinary first-party web auth,
  selected membership, fresh TOTP, clean selected scan and idempotency.
- Temporary HTTP cookies and the content-free diagnostic/runner were mode
  `0600` and deleted after execution. The existing DR-0023 protected MFA
  handoff remains owner-only; no phone, TOTP secret, recovery code, cookie,
  raw media, transcript or learner data entered Git, command output, audit or
  outbox evidence.
- The four rights rows authorize bounded processing and possible later gated
  delivery only. They do not attach media or approve content, answers,
  English/Kannada review, mastery, publication or release.

### Known limitations and next gate

- This is a local non-production first-authority exception, not a production
  organization-onboarding design. One human retains consolidated product,
  rights and review roles; later English/Kannada and production staffing gates
  remain independent.
- The pilot intentionally has no curriculum version, content source,
  transcript, attachment, publication, enrollment or learner entitlement.
- No transcription/provider call has occurred. DR-0025 Option A is accepted
  under APP-0052 / D-0153 / ADR-0020 and now governs the finite asset/attempt/
  spend ceiling, fail-closed kill switch, no-training/minimum-retention
  provider settings, immutable provenance and human transcript reconciliation.
  D-0154 now records its repository implementation and verification; live
  execution remains pending.
- LPL-02, deployment and production/public release remain unauthorized.

## LPL-01 transcription commissioning decision gate — 10 August 2026

### Outcome

[DR-0025](DR-0025_LPL01_TRANSCRIPTION_COMMISSIONING_REQUEST.md) Option A was
accepted exactly as written under [APP-0052](APPROVALS.md#app-0052), D-0153
and [ADR-0020](adr/0020-governed-curriculum-media-transcription-commissioning.md).
It authorizes the bounded implementation, verification and one exact non-
production Azure Fast Transcription campaign. Acceptance itself created no
migration, credential use, spend, provider call, candidate or metadata.

The accepted contract sends each accepted MP3 byte stream directly rather than
creating a normalized derivative. Current official Azure documentation lists
MP3 as supported Fast Transcription input, supports fixed `en-IN` and Central
India, and states that Fast Transcription does not retain customer-provided
data. The exact API reference and the broader guide publish different maximum
file bounds, so the request adopts the stricter less-than-two-hour/250 MB
reference limit. Every selected asset is below eight minutes/eight megabytes.

### Evidence inspected

- Master-plan provider, privacy, observability and spend boundaries; LPL-01's
  active plan; ADR-0004, ADR-0007, ADR-0008, ADR-0015, ADR-0016 and ADR-0019.
- APP-0047 exact-hash authority, four accepted media/scan records, four current
  program-scoped rights revisions and the DR-0024 runtime evidence.
- The current Azure recorded-STT provider, configuration/HTTP boundary,
  synthetic benchmark runner, learner `voice_recordings` /
  `voice_provider_runs` schema and current Media Library metadata commands.
- Azure's current Fast Transcription guide and REST `2025-10-15` reference,
  region/language tables, speech-to-text privacy/retention documentation,
  dynamic pricing page and May 2026 Microsoft DPA listing.

The current learner adapter is not suitable for silent reuse: it accepts only
validated PCM/WAV, limits a transcript to 500 characters and binds benchmark/
learner semantics. The existing Media Library can hold a human-authored
32,000-scalar/64 KiB transcript, but it has no provider campaign, attempt,
encrypted candidate or human-reconciliation lineage. That foundation
ambiguity is why DR-0025 requires the accepted separate foundation before a
live campaign.

### Accepted finite and privacy boundary

- Exactly four logical assets totaling 18,760,190 bytes and 1,172,513 ms;
  sequential execution; at most two attempts per asset.
- Hard campaign maximums of eight attempts, 37,520,380 uploaded bytes,
  2,345,026 billable input milliseconds and ₹100 estimated Azure service usage.
- Exact Central India endpoint, API `2025-10-15`, fixed `en-IN`, default speech
  model and no URL, batch, custom, diarization, language-ID, translation,
  prompting, LLM Speech, MAI, phrase-list, word-timestamp or fallback path.
- Dedicated environment and command only; exact approval/config/evidence/run
  hashes plus a removable external mode-`0600` permit are checked before every
  attempt. Ordinary application and learner DI remain disabled.
- Raw responses stay in memory; validated candidates are application-
  encrypted, private and expire after seven days. Reconciliation/discard
  deletes candidate content within the one-hour hard bound while retaining
  content-free lineage.
- Only a current-tenant, first-party web, selected-membership, fresh-TOTP
  human with `media.metadata.manage` can confirm/correct a candidate into an
  English metadata revision. This remains authorship, not academic or Kannada
  review, and no LLM drafting is authorized by the campaign.

### Verification performed

- Documentation/code/schema inventory and arithmetic reconciliation passed:
  four source durations total 1,172,513 ms; the two-attempt ceiling is
  2,345,026 ms; four source byte counts total 18,760,190 and the two-attempt
  byte ceiling is 37,520,380.
- Current official provider pages were checked on 10 August 2026 and linked in
  DR-0025. A dated execution-time vendor/configuration/price/FX snapshot
  remains mandatory because provider terms and prices can change.
- The exact accepted pre-resolution DR-0025 proposal has SHA-256
  `cb489ec3f034861d256934aa0fcf5128a997af73ec6da4bd461453ebce02bdf4`.
- APP-0052 records the exact human statement
  `Approve DR-0025 Option A as written.` and ADR-0020 records the resulting
  provider-neutral and authoritative-data boundary.
- No migration, route, OpenAPI/client, configuration, candidate, credential,
  provider request, transcript, metadata revision or learner record was
  created.
- APP-0052 approval-recording `make verify` passed: Composer/pnpm validation
  and audits, Pint, PHPStan, 144 SQLite tests / 1,815 assertions, 280
  PostgreSQL tests / 4,017 assertions, locale/OpenAPI/generated-client/release-
  contract checks and 270 package/web tests. `make e2e`, `make ai-eval` and
  `make build` were not run because this resolution changed documentation only;
  no critical journey, provider orchestration or packaging behavior changed.

### Retained gates

- At acceptance, implementation was required to pass every DR-0025 test;
  D-0154 now records that repository result. Every external execution gate
  still must pass before a live request. Any different provider, region,
  locale, model class, asset set, campaign, retry, duration, byte or spend
  boundary requires a successor decision.
- The future human reconciliation must cover every segment and cannot infer
  that all four parts share one `can` scope. Academic English and Kannada
  review, blueprint validation and publication readiness remain LPL-01 result
  gates. LPL-02 and production/public release remain unauthorized.

## LPL-01 transcription commissioning implementation — 10 August 2026

### Outcome

The bounded DR-0025 Option A repository vertical slice is complete under
APP-0052 / D-0153 / ADR-0020 and recorded by D-0154. It can authorize, reserve,
run, resume and reconcile only the one exact four-asset campaign through a
project-owned provider port. It does not make the campaign authoritative:
provider output remains an encrypted expiring candidate until an authorized
human explicitly confirms, corrects, discards or marks it for attention.

No execution-time vendor or price evidence, provider credential, permit or
live Azure request was supplied during this work. No provider spend,
candidate, transcript, media metadata revision, academic/Kannada approval,
publication, enrollment or learner record was created. LPL-01 remains active.

### Delivered vertical slice

- Six additive organization-owned tables record the exact campaign, four
  assets, reserved/succeeded/failed attempts, append-only content-free events,
  candidate deletion receipts and human reconciliation lineage. Tenant-
  composite keys bind campaign, asset, scan, media, candidate digest and
  metadata evidence; PostgreSQL transitions and history are protected.
- `CurriculumMediaTranscriptionProvider` keeps Azure SDK/HTTP shapes outside
  domain code. The dedicated REST adapter sends only inline `audio/mpeg` to
  Central India API `2025-10-15`, refuses redirects and validates bounded JSON,
  `en-IN`, one channel, NFC text, confidence and non-overlapping segments.
- Authorization requires the exact APP-0052 request/manifest/configuration,
  dedicated environment, both disabled-default switches, current mode-`0600`
  external evidence/secret/permit files, exact endpoint host, reviewed no-
  logging/no-training/no-provider-storage fields, same-day price/FX evidence
  and the hard four-asset/eight-attempt/byte/duration/₹100 caps. It is rerun
  before every reservation and dispatch.
- Each reservation advances authoritative counters transactionally before
  network work. Successful assets are not resent; uncertain/interrupted work
  is recorded content-free and retried at most once. A process-local guard and
  PostgreSQL advisory lock reject overlapping runners before duplicate
  reservation or dispatch.
- Candidates use the private project storage abstraction with application
  encryption and digest verification. They expire after seven days;
  reconciliation, discard, interrupted storage and expiry create deletion
  work with a 15-minute target, one-hour hard deadline, five bounded retries
  and a content-free dead letter. Unreferenced terminal provenance is purged
  after 365 days only after exact deletion receipts; metadata-linked lineage
  remains retained.
- Private no-store candidate read and reconciliation endpoints require normal
  first-party web authentication, selected organization, exact
  `media.metadata.manage` and fresh TOTP. Reconciliation is transactional,
  version/hash guarded and header-idempotent. Confirmed/corrected text enters
  ordinary human-authored media metadata; unresolved `[inaudible ...]`
  markers continue to block academic approval.
- `lpl:transcribe-media` exposes prepare/run/status phases, writes only a
  strict content-free mode-`0600` external report and removes the permit after
  terminal status. The hourly retention command processes expiry, deletion,
  dead-letter backlog, campaign completion and eligible provenance purge.
- OpenAPI 0.23.0 and generated TypeScript contracts include the two private
  reconciliation operations. `make e2e` now runs the accepted direct API
  reconciliation journey before the retained browser journeys. No dedicated
  reconciliation UI was required by the accepted first release.

### Verification performed

| Command or suite | Result |
|---|---|
| Focused commissioning suite on SQLite | Pass: 32 tests / 344 assertions |
| Focused commissioning suite on PostgreSQL | Pass: 16 tests / 305 assertions |
| PostgreSQL rollback-order regression suites | Pass: 18 tests / 128 assertions; later commissioning FKs are removed/reapplied in reverse migration order |
| OpenAPI lint, policy and generated-client drift | Pass; OpenAPI 0.23.0 and generated files match |
| Final `make verify` | Pass: Composer/pnpm audits; Pint; PHPStan; 160 SQLite tests / 1,876 assertions; 297 PostgreSQL tests / 4,344 assertions; locale validation; OpenAPI/client and release-contract checks; ESLint; recursive TypeScript; 270 package/web tests |
| Final `make e2e` | Pass: real reconciliation API journey 4 tests / 105 assertions, then 16/16 Chromium journeys |
| `make ai-eval` with the required testing/fake-provider environment | Pass: 15/15 deterministic cases, zero failures and zero estimated cost; the separately gated speech benchmark correctly skipped without `SPEECH_BENCHMARK_RUN_ID` |
| Final `make build` | Pass: Laravel optimize/cache cleanup and production Vite build; retained measured chunk-size warning only |

The expanded overlap test first exposed that a reentrant runner could classify
an active reservation as interrupted and then collide on the same attempt. The
local/PostgreSQL campaign locks fixed the underlying race. The first complete
gate then found four PHPStan type errors; exact types/validation were corrected
without suppression. The next complete PostgreSQL gate found three older
targeted rollback tests dropping a parent media-metadata table while the later
commissioning FK remained applied; the tests now observe reverse migration
order without weakening the FK. The final uninterrupted gate passed. Initial
AI-evaluation attempts failed closed in the non-testing environment and then
at sandboxed local-database access; the approved fake-provider rerun passed.
A manual invocation of the PostgreSQL-only trigger suite against SQLite was
driver-incompatible; the canonical SQLite suite correctly excludes it. No
failed command is represented as passing.

### Migration and configuration

- `2026_08_10_000170_create_media_transcription_commissioning.php` is the only
  new migration for this slice. Its rollback/reapply coverage preserves all
  prior Media Library, curriculum and rights tables. Internal commissioning
  history cascades only for the guarded 365-day purge; referenced authoritative
  metadata and membership/media foundations remain restrictive.
- `media_transcription.php` and the private filesystem disk fix the exact
  manifest/caps/provider values, external evidence paths, candidate retention
  and deletion policy. New environment values are disabled/empty by default.
  The provider is not bound to ordinary application or learner dependency
  injection.
- The console schedule adds only the hourly candidate/provenance maintenance
  command. No dependency, permission definition, default role grant, platform
  capability, learner route or public storage surface was added.

### Security, privacy and tenancy review

- Every database read/write and API operation is organization-scoped; tenant-
  composite relationships and cross-tenant read/write tests prevent guessed
  campaign, asset, attempt, candidate and metadata identifiers from crossing
  owners.
- Network work occurs only after exact source hash/byte/duration, clean selected
  scan, latest approved derivative rights, appointment authority, evidence,
  credential version and campaign caps are revalidated. Overlap and stale
  version/hash/idempotency cases fail closed before duplicate effects.
- Candidate plaintext and raw provider bodies exist only in bounded memory or
  the encrypted private candidate store. They are absent from database
  provenance, safe reports, audit/outbox payloads and error messages. API
  responses are private/no-store; credentials and evidence remain external
  mode `0600` files and were not committed or printed.
- Marks, mastery, progression, academic approval, publication and learner
  records remain deterministic and untouched. Human metadata authorship is
  not academic English/Kannada approval, and no LLM drafting path exists.

### Known limitations and next gate

- Repository verification is not live commissioning evidence. Before any
  dispatch, the operator must supply independently reviewed current official
  vendor/privacy/security evidence, same-day price/FX evidence, an exact
  external credential/version, reviewed private encrypted candidate storage
  including its backup bound, and a removable mode-`0600` campaign permit.
- No dedicated reconciliation UI exists; the accepted first release uses the
  private first-party API. Independent accessibility review applies if a UI is
  later added.
- No real Azure response shape, latency, quota, deletion-backend failure or
  production-volume behavior has been observed. Operational alert routing and
  dead-letter response remain deployment gates.
- After all external gates pass, the next bounded action is only the exact
  four-asset campaign, followed by human reconciliation/deletion evidence and
  safe-report review. LPL-01 then still requires source-supported English and
  Kannada review and an approved publishable Level 1 blueprint. LPL-02,
  deployment and production/public release remain unauthorized.

## LPL-01 live commissioning preflight stop — 10 August 2026

### Outcome

The authorized one-campaign boundary was taken through every safe,
non-interactive pre-dispatch step. Dispatch correctly remained disabled. No
campaign permit was created, the commissioning database contains zero
campaigns and provider attempts, no Azure transcription request was made, and
reserved/actual provider spend remains zero.

The stop is evidentiary. The prior PR-009 material still matches the Central
India Speech resource and records a valid credential version, agreement, DPA,
subprocessor snapshot and logging/model-improvement controls through 19 August
2026. It identifies its contracting-entity review only through an opaque
evidence ID, however, not the exact contracting entity required by ADR-0020.
Read-only Azure billing-account and key-list requests both returned the
security-default condition requiring interactive reauthentication. Blanket
execution authority cannot convert that missing fact into evidence, so a
permissive placeholder would have violated the accepted fail-closed contract.

### Evidence and state changes

- Before applying the additive commissioning migration, a PostgreSQL
  custom-format backup was written outside the repository: 799,372 bytes,
  SHA-256
  `a1510723c88f350a60d99844ff0ae268d01b482eae4512165d40ef33643370d2`.
- Migration `2026_08_10_000170_create_media_transcription_commissioning` was
  applied as batch 4. A content-free database check then returned zero
  campaigns, provider attempts, reconciliations and candidate deletions.
- The external secret exists at mode `0600`; its content was not printed or
  copied into the repository. An isolated non-backed-up candidate-storage
  root was prepared outside the repository with mode `0700`; no candidate was
  written.
- The current official Azure Central India Fast Transcription retail meter is
  $0.36/hour. The ECB 10 August 2026 rates derive 95.30315880571182 INR/USD.
  Rounding upward gives 9,531 microunits per billable second and a conservative
  full-manifest estimate of ₹22.378788, within the exact ₹100 cap.
- The raw official price/FX snapshots and a content-free mode-`0600` preflight
  audit remain outside the repository. The audit SHA-256 is
  `2c56e5a359636dc4da3e55a3917953019dce3738af40816caaf2eb4fe6d63abb`.
  It records both blocker codes, zero requests and zero spend without raw
  subscription/resource identifiers, secrets, audio or transcript content.
- A mode-`0700` external resume utility, SHA-256
  `281e0cd64ced70652b7d5d1c233666a7f2f63165e31939a1086b5d31cee6276f`,
  passes Bash syntax validation. After normal Azure reauthentication it
  correlates the exact subscription to one billing account, emits the sold-to
  entity only into a mode-`0600` external evidence file, verifies the exact
  resource/region and compares the configured secret against current key
  slots without printing credentials. It emits only content-free blocker
  codes on failure and does not create a campaign permit.

### Provider-boundary correction and verification

Microsoft's current Fast Transcription example permits the mono
`combinedPhrases` item to omit `channel`. The adapter's strict validation was
corrected to treat omission as the single-channel default while continuing to
reject an explicit nonzero channel and every multiple-channel result. The
happy-path fixture now exercises the documented omission and a separate
regression case covers the explicit nonzero failure.

| Command or suite | Result |
|---|---|
| Focused curriculum-media provider boundary | Pass: 17 tests / 50 assertions |
| Final `make verify` | Pass: Pint, PHPStan, audits and contracts; 165 SQLite tests / 1,883 assertions; 297 PostgreSQL tests / 4,345 assertions; 270 package/web tests |
| Final `make e2e` | Pass: real reconciliation API 4 tests / 105 assertions and 16/16 Chromium journeys; retained Vite chunk warning only |
| Final deterministic `make ai-eval` | Pass: 15/15, zero estimated cost; separately gated speech benchmark correctly skipped |

### Retained gates

- Reauthenticate the Azure management plane through the normal human security
  ceremony, verify and record the exact contracting entity and current
  credential version, then regenerate current signed vendor evidence. Only
  after that may the exact mode-`0600` campaign permit be created.
- A successful future provider run would create only four encrypted,
  seven-day candidates. A fresh-TOTP human must listen to every segment and
  explicitly reconcile each candidate; AI cannot impersonate that reviewer.
- English academic review, Kannada translation/review, blueprint approval,
  publication, learner delivery and production/public release remain separate
  human/governance gates.

## LPL-01 local recovery and governed-audio review — 10 August 2026

### Delivered conformance

- DR-0022 remains a disabled local-only, exact-ID, protected-input,
  transactional and one-shot identity binding. DR-0023's validated whole-
  database restore authorized one exact APP-0049 replay; the normal kill
  switch remains absent from persistent configuration.
- An existing verified active Direct learner, trainer or organization
  administrator may now complete ordinary OTP authentication without learner
  conversion or role mutation. Unknown identities retain the existing
  learner-only Direct provisioning behavior; suspended staff remain denied.
- The recovery added no endpoint, schema, permission, role grant, provider or
  support-reset behavior. It restored ordinary sign-in and the accepted media
  lifecycle without bypassing authentication or tenancy.

### Runtime evidence and incident

- The locked incident database was preserved as a valid 781,927-byte custom
  dump with SHA-256
  `2f2b524f1913e40fc3e46be76fde58142c879098395e5e740b9452960f14fab0`.
  Isolated table-count and deterministic full-row-fingerprint comparison found
  only expected migration/seed, identity, authentication/MFA, failed media,
  cleanup, audit, outbox and idempotency deltas. No pre-existing learner,
  enrollment, curriculum, review, publication, asset or rights record changed.
- The exact 548,570-byte baseline backup revalidated at SHA-256
  `b9f3e4ddc75e4be48dfa278c1297ed49cf9f43e34db5092aad38e959c4808053`.
  Only the named local database was restored; migrations 70–160, the
  foundation seed and one exact APP-0049 replay then completed.
- Ordinary OTP, exact membership selection and TOTP completed for the expected
  local synthetic administrator. Factor `01kznfk6xhjm0k4gmrng69zr46` remains
  active. Setup/recovery material remains only in one owner-only `0600`
  temporary handoff; no secret content entered command output or Git.
- Laravel's reloading development-server child filtered non-passthrough
  adapter variables, so restored upload `01KZNFK6ZKECGF2ZCBAVMWX4V4` / scan
  `01KZNFK75WGEKQ854E84Q3K7RM` correctly failed closed with unavailable
  outcomes and no asset. Restarting with `--no-reload` passed the same explicit
  reviewed selectors to the worker; no inspection/scanning policy changed.
- The corrected first-party HTTP run created exactly four active
  `lesson_audio` assets:

| Part | Asset | Scan | Bytes | Duration ms | Persisted result |
|---|---|---|---:|---:|---|
| Introduction | `01KZNFYHCJSDWSPK08ZAC7G3AJ` | `01KZNFYHT1XZ5SB2B0S6Z3C863` | 4,968,408 | 310,526 | `audio/mpeg`; ffprobe passed; ClamAV clean |
| Practice | `01KZNFYJ1M911SB8YVH9P4EZZ0` | `01KZNFYJM0WCFVHN41FWW65Y4F` | 7,357,881 | 459,868 | `audio/mpeg`; ffprobe passed; ClamAV clean |
| Conversation | `01KZNFYJVSZRPZCEPTTQ4QTMNT` | `01KZNFYK2B9E9JX770G810D9CN` | 2,157,215 | 134,826 | `audio/mpeg`; ffprobe passed; ClamAV clean |
| Interaction | `01KZNFYK75V9WF8R5VCWRRZHNN` | `01KZNFYKJACNCGNRFHFFX1HAVC` | 4,276,686 | 267,293 | `audio/mpeg`; ffprobe passed; ClamAV clean |

  Each persisted hash matches APP-0047. Independent asset-scoped queries found
  zero lesson-audio metadata revisions, rights revisions and content usages.
  The incident snapshot's six objects and the active database's six failed-
  attempt plus 25 accepted-upload quarantine objects were deleted through the
  project reconciliation worker only after their due times. All 31 active
  receipts are complete, and a final private-object re-read matched all four
  expected hashes and byte counts.

### Verification

| Command | Result |
|---|---|
| Focused PostgreSQL `DirectLearnerProvisionerTest` plus `AuthenticationFlowTest` | Pass: 23 tests / 295 assertions |
| Pre-restore full `make verify` | Pass: Composer/pnpm validation and audits; Pint; PHPStan; 140 SQLite tests / 1,744 assertions; 278 PostgreSQL tests / 3,993 assertions; locale, OpenAPI/client and release-contract checks; ESLint; recursive TypeScript; 270 package/web tests |
| Pre-restore full `make e2e` | Pass: 16/16 Chromium journeys, including People, Content Studio and governed Media Library |
| Four-file ordinary HTTP ingestion assertions | Pass: exact user/membership/factor; all four hashes/bytes/durations/MIME; four active lifecycle-v1 assets; ffprobe passed; ClamAV clean; zero metadata/rights/usages |
| Incident-snapshot reconciliation | Pass: six deletions; zero retry, dead-letter, unavailable or overdue backlog |
| Current failed-attempt reconciliation | Pass: six deletions; zero retry, dead-letter, unavailable or overdue backlog |
| Accepted-upload reconciliation | Pass: 25 deletions after due time; all 31 active receipts complete; zero retry, dead-letter, unavailable or overdue backlog |
| Final accepted-object/data audit | Pass: four private objects re-read with exact digests; four active assets; zero lesson-audio metadata, rights or usages; one active factor and ten available recovery codes |
| Final full `make verify` | Pass: Composer/pnpm validation and audits; Pint; PHPStan; 140 SQLite tests / 1,744 assertions; 278 PostgreSQL tests / 3,994 assertions; locale, OpenAPI/client and release-contract checks; ESLint; recursive TypeScript; 270 package/web tests |
| `make ai-eval` | Not run; no prompt, model, provider or orchestration behavior changed |

### Security, privacy and tenancy review

- Staff OTP replay is identity-derived and accepts only an already active
  membership of a known Direct membership type. It creates no membership or
  grant on that branch and still lets session privilege derive from current
  roles and permissions.
- Both failed media paths preserved hash, quarantine, inspection and malware
  fail-closed behavior. Successful acceptance used the same first-party HTTP,
  tenant, permission, fresh-TOTP, exact hash and real-adapter controls.
- No direct credential or rights SQL mutation, test authentication, tenant
  bypass, provider call, fabricated review or production/public release
  occurred. The protected MFA handoff remains a local custody obligation; a
  proper support-assisted reset remains unimplemented.

### Known limitations and next gate

- The current administrator has neither `media.rights.approve` nor an accepted
  rights-confirmer appointment. The synthetic preview and duplicate smoke
  imports are not an automatically canonical LPL program.
- DR-0024 must select the pilot owner and initial authority path before any
  rights revision, transcript/provider run or blueprint draft. It creates no
  authority until explicitly accepted.
- Open DR-0024 has SHA-256
  `2b52f1c3c09206a33de7b940e2184c473d3095b89388d30fc9830386f9266538`.
  It is a proposal only and creates no authority before exact approval.
- Transcript reconciliation, English/Kannada review and a publishable
  blueprint remain pending. LPL-02 has not started.

## LPL-00 prerequisite closure and foundation decision gate (10 August 2026)

### Outcome

The retained ADMIN-MEDIA-01 PostgreSQL and complete repository gates have now
passed. LPL-00 is active at its documentation-only foundation gate. The current
curriculum, content/publication, learning attempt, offline, conversation, voice
and progression authorities were inspected before any LPL runtime edit.

[DR-0020](DR-0020_LESSON_PRACTICE_FOUNDATION_REQUEST.md) recommends extending
the existing authorities with a typed practice definition, immutable
publication/practice context, append-only stage evidence, deterministic
weighted multi-item scoring, separate speaking-evidence states and transparent
level unlock decisions. Proposed
[ADR-0015](adr/0015-lesson-practice-foundation.md), the bounded
[specification](LPL-00_SPEC.md) and the
[locked verification scope](LPL-00_VERIFICATION_SCOPE.md) translate that
choice without creating runtime authority. DR-0020 remains open; LPL-00 is not
complete and no LPL migration/API/UI/provider call has begun.

### Prerequisite verification performed

| Command or suite | Result |
|---|---|
| Focused real-PostgreSQL `ConcurrentMediaLibraryTest` | Pass, 5 tests / 53 assertions using disposable PHP 8.4 with `pdo_pgsql` and `pcntl` |
| Final `make verify` | Pass: Composer validation/audit; Pint 587 files; PHPStan 565 files; 130 SQLite tests / 1,665 assertions; 271 PostgreSQL tests / 3,890 assertions; pnpm install/audit; six-catalogue locale validation with five retained pending-review warnings; OpenAPI/client drift; fail-closed release contract; ESLint; recursive TypeScript; package/web tests including 229 web tests |
| Final `make e2e` | Pass, 16/16 Chromium journeys including exact Media Library upload/quarantine/rights/attachment |
| Final `make build` | Pass; Laravel cache lifecycle and Vite production build succeeded with the retained chunk-size warning (main JS approximately 1,015.71 kB / 236.96 kB gzip) |
| `POSTGRES_DB=global_english_test make backup-restore-drill` | Pass; schema/migration/protected aggregate reconciliation and 1-second local RTO; `artifacts/ops/backup-restore-20260810T050100Z.json` |
| `make ai-eval` | Not run for the ADMIN-MEDIA prerequisite; no prompt, model, provider or orchestration behavior changed |

The first cached PHP image stopped at the suite's explicit missing-`pcntl`
guard. A corrected disposable image installed `pcntl` before the focused test.
The first full-container PHPStan run exposed a path mismatch from a Composer
PHAR generated under the host absolute workspace; the final run mounted the
same absolute path. Initial E2E/build/restore attempts met sandbox stream,
temporary Laravel-storage or Compose-network visibility failures before a
product assertion. The exact gates were rerun in the approved suitable
environment and passed. No failed product result was suppressed or described
as success.

### Foundation inventory result

| Boundary | Observed constraint |
|---|---|
| Daily mission definition | Exactly one mission, one matching lesson item, one primary objective and one multiple-choice check |
| Deterministic policy | `daily-speaking-v1` uses the latest response and permits only 0/100, mastered/not-mastered and `spoken_state=unassessed` |
| Offline | Existing v1 signed bundle deliberately includes plaintext `correct_option_id`; it must remain a golden compatibility exception, not the LPL contract |
| Publication | Graph v2 pins levels/lessons/objectives/content/media but has no lesson-practice component or prompt/scoring hash |
| Conversation | Versioned immutable persona/scenario/prompt/session evidence exists, but no lesson/publication context link or skill-focus allowlist |
| Voice | Consented recording/transcript lifecycle exists; it does not create authoritative speaking mastery |
| Progression | Objective mastery/review exists; no five-stage completion or `unlock_decisions` implementation exists |

The recommended policy therefore reuses the existing mission attempt/mastery
authority instead of creating a second assessment subsystem. It preserves
daily-v1 bytes/behavior, supports 3–7 weighted reviewed single-choice items in
`lesson-practice-v1`, withholds answers/rationales from every new
pre-submission/offline/model surface, and makes AI/provider output advisory.

### Security, privacy, tenancy and authority review

- The proposed records derive learner ownership from active organization
  membership and bind exact curriculum-owner publications with tenant-composite
  relationships. Every new route will require same/cross-tenant coverage.
- Practice contexts, stage completions, submissions and unlock decisions are
  immutable or append-only. Transactions, deterministic locks, idempotency and
  PostgreSQL constraints remain mandatory for authoritative writes.
- Raw lesson media, learner audio/transcripts, answer keys, rationales, object
  keys and personal facts are excluded from logs, analytics, audit/outbox and
  Skill Chat context.
- `not_recorded`, `recorded_unassessed`, `awaiting_review` and `reviewed` are
  evidence-lifecycle states, not AI-awarded spoken mastery.
- The product owner's live-AI/repository-media allowance is recorded for
  bounded non-production analysis/drafting/evaluation only. Exact input,
  provider adapter, purpose, spend ceilings, kill switch, rights/privacy and
  human publication review still apply.

### Files and behavior

This gate adds only DR-0020, proposed ADR-0015, the proposed LPL-00
specification, the locked verification scope and checkpoint-ledger
reconciliation. It changes no product schema, migration, permission, role
grant, route, OpenAPI/client, catalogue, provider binding, secret,
configuration or runtime behavior.

### Open gate and next action

LPL-00 remains blocked on the genuine foundation choice required by
`AGENTS.md`. The Accountable Product Owner must resolve DR-0020. If Option A is
accepted exactly, record its APP/decision/ADR evidence, activate the
specification, close LPL-00, and begin only LPL-01 exact governed lesson/media
inventory and Level 1 blueprint work. Production/public release and retained
independent review remain unclaimed.

## LPL-00 approval and checkpoint completion (10 August 2026)

### Outcome

The Accountable Product Owner supplied the exact statement
`Approve DR-0020 Option A as written.` APP-0046 preserves that statement and
the pre-resolution artifact hashes. D-0140 and accepted ADR-0015 now resolve
DR-0020; the LPL-00 specification is active and its verification scope is
locked.

LPL-00 is complete. This is a policy/specification checkpoint only: no LPL
migration, API, UI, prompt, model/provider call, permission, configuration or
runtime behavior was added. LPL-01 exact inventory/content work is the only
next checkpoint.

### Accepted artifacts

| Artifact | Accepted pre-resolution SHA-256 |
|---|---|
| DR-0020 | `698d03917a494c29c58d8d34a02f13a09271c8ac192e1bf73117a04cf0b1cab3` |
| LPL-00 specification | `5bda28ff6be15bee37c32d00fb701ce776108a750db7a4ee8dccddecadd53f6e` |
| LPL-00 verification scope | `a7bf02f70dc5bb602c3f2b4341718bd04d1a218c829c82a3db9998b70d1b9dd4` |
| Proposed ADR-0015 | `009d8af0f3d50747c04f6c6ab75a8267b00db6e407937f28aada2d036ac51a61` |

### Accepted authority boundary

- Governed publication, current mission attempts/mastery/review,
  conversation and voice remain the single canonical authorities.
- New lesson practice uses an immutable typed definition/context and
  append-only stage links rather than a duplicate progress subsystem.
- `lesson-practice-v1` uses 3–7 weighted reviewed items, exact integer scoring
  and an 80-point threshold while daily-v1 rows/bytes keep their meaning.
- New pre-submission learner, model, telemetry and offline surfaces contain no
  plaintext answer/rationale. Daily-v1 offline behavior is a documented golden
  compatibility exception.
- Speaking evidence remains distinct from spoken mastery. AI/STT/TTS cannot
  award marks, mastery, review completion or unlocks.
- `level-unlock-v1` is added only with LPL-04's exact level checkpoint and uses
  deterministic published prerequisites/evidence.
- Live AI/repository-media use remains bounded non-production drafting and
  evaluation with exact provenance, configured spend ceilings, kill switch,
  rights/privacy controls and human English/Kannada publication review.

### Verification and review result

The prerequisite runtime gates are those already recorded in the immediately
preceding LPL-00 decision-gate review: focused PostgreSQL media races, full
`make verify`, 16/16 Chromium E2E, production build and logical
backup/restore all passed. This approval-only change requires and received
documentation state, structure, link, fence, final-newline and whitespace
checks; it does not rerun or claim a new runtime/provider result.

One human exercised consolidated Accountable Product Owner, Technical Decision
Owner, Security Reviewer, Privacy Reviewer and Tenancy Reviewer roles. This is
not independent specialist, academic/Kannada, legal or production review. No
master-plan amendment was required because ADR-0015 refines the existing
generic curriculum, deterministic authority, immutable evidence, offline and
provider-neutral AI rules.

### Next checkpoint

LPL-01 must enumerate exact governed levels, lessons, objectives, media hashes,
transcripts, rights/review state and gaps before analyzing the claimed four
Level 1 audio lessons. It may draft only from eligible exact media, must not
fabricate a `can` topic, and must stop on missing review/rights/content evidence.
LPL-02 runtime implementation remains pending.

---

## LPL-01 exact inventory and content-gate review (10 August 2026)

> Historical inventory-time result. D-0149 and the local recovery/governed-
> audio review above supersede its media-ingestion state; its source and
> content-completeness findings remain applicable.

### Outcome

LPL-01 has completed its exact inventory and completeness-matrix work but has
not passed its checkpoint exit. Initial current-system evidence could not
identify four Level 1 audio lessons or support a Level 1 `can` topic. A later
authenticated read-only legacy-source inspection resolved that source gap:
Level 1 is `Can`, and its Audio node contains four ordered MP3 parts. No
blueprint was fabricated and no runtime work from LPL-02 began.

The local governed snapshot contains one unique Level 1 MP4 duplicated across
two staging-smoke programs. The exact first-import private object was re-read
and matched its governed SHA-256. Its wrapper has no transcript, topic-specific
objective or assessment content, and its current rights permit learner
delivery while disallowing sharing and derivative use. The only imported
audio is a 34-byte synthetic scaffold, not a Level 1 legacy lesson.

The upstream teaching MP3s were read ephemerally to establish exact hashes,
sizes and media shape. They are not current governed assets. The source body
supports ability, possibility/impossibility, permission, polite requests,
question forms, a directions conversation and Workshop practice candidates,
but has not passed current English/Kannada review.

### Evidence inspected

- Current local PostgreSQL curriculum, graph, content revision, localized
  variant, review, publication, media, attachment and rights records, without
  reading learner-personal tables.
- Local migration ledger, which stops before the later ADMIN migrations in
  this database snapshot.
- Gitignored legacy inventory, 12 exact local MP4 files and saved staging-smoke
  results; all 12 SHA-256 values match the retained catalog.
- Current private Level 1 object existence and a full read-stream SHA-256
  comparison through the configured project storage adapter.
- Demonstration-only phase-1 fixture data and repository-wide media/content
  searches, which contain an Audio Lessons room label but no four exact Level
  1 audio objects.
- Authenticated legacy Course, Levels, Lessons and Level 1 edit forms: one
  course; Pre-Levels 1–2 plus Levels 1–17; 102 numbered-level lesson rows; six
  repeated lesson types; and 67 Audio MP3 references.
- Exact ephemeral reads of the four Level 1 teaching MP3s and the source `CAN`
  body/Workshop structure. Assignment media was not downloaded because its
  course-content provenance and privacy class are unknown.
- Master plan 3.2, ADR-0004, ADR-0013, ADR-0015, LPL-00 specification and the
  ordered Lesson Practice Loop plan.

### Acceptance matrix

| Boundary | Result | Evidence |
|---|---|---|
| Exact all-level inventory | Pass | 17 legacy Video rows reconciled: 12 exact MP4s and five missing-source levels; local database programs and duplicate smoke imports separated |
| Four Level 1 audio reconciliation | Pass at source; blocked at inventory time | Introduction, Practice, Conversation and Interaction MP3s identified and hashed upstream; zero corresponding current governed assets at that snapshot; four were later accepted under D-0149 |
| Exact Level 1 objects | Partial at inventory time | Current private MP4 hash matched; all four upstream teaching MP3 hashes/sizes recorded; later governed ingestion is recorded under D-0149 |
| Topic/transcript/objective evidence | Partial/blocked | Upstream body supports `can`; no timestamped transcript, current observable objective or reviewed audio/body reconciliation |
| Processing rights | Blocked at inventory time; owner authorization later resolved | Current MP4 rights disallow derivative use; APP-0047 authorizes the four exact MP3 hashes, while asset-bound rights rows still await DR-0024 after governed ingestion |
| Blueprint deterministic content | Blocked | Source Talk/Apply candidates exist; no reviewed 3–7 items, answers/rationales, structured goals or rubric |
| English/Kannada blueprint review | Blocked | Wrapper reviews exist; the absent blueprint and Kannada variant are not reviewable |
| LPL-02 isolation | Pass | No migration, API, UI, prompt, provider, learner record, publication or scoring change |

### Security, privacy, tenancy and AI review

- Queries remained read-only and restricted to non-personal curriculum/media
  authority. Durable documentation omits private object keys, credentials,
  staff identities and learner records.
- Legacy access was read-only. No enable/disable, edit, delete, upload, publish,
  message, user, chat or report action occurred; temporary cookies and media
  are not retained in the repository.
- Duplicate tenant-owned smoke records were not treated as distinct lessons or
  as cross-tenant authority. No record or object was mutated.
- APP-0044 was not interpreted as a per-asset derivative-use approval. The
  explicit current rights value stopped transcription and AI media analysis.
- No external AI/provider request or spend occurred. No raw audio or transcript
  entered logs, prompts, documentation or analytics.
- Generic wrapper reviews were not misrepresented as academic/Kannada review
  of a transcript, topic, answer key or blueprint.
- Unlabeled legacy Assignment audio was treated as potentially learner-
  originated and excluded from byte access/import pending provenance/privacy
  classification.

### Verification performed

| Check | Result |
|---|---|
| Read-only PostgreSQL inventory and aggregate/detail reconciliation | Pass; 3 programs, 27 lessons, 26 media rows, 2 smoke publications and exact duplicate hashes observed |
| Local file SHA-256 inventory | Pass; all 12 MP4 hashes match `artifacts/legacy-gse-content/inventory.json` |
| Private Level 1 object existence and read-stream hash | Pass; object exists and exact SHA-256 matches |
| Legacy lesson-list reconciliation | Pass; 102 rows = 17 levels × 6 lesson types, with 67 Audio MP3 references |
| Exact Level 1 Audio source read | Pass; four valid MPEG Layer III files, exact hashes and byte sizes recorded |
| `git diff --check` | Pass |
| Seven-document final-newline, balanced-fence and local-link check | Pass |
| Cross-document active-state search | Pass; no stale LPL-01 pending/not-started state in current status, decision, plan or LPL-01 artifacts |

`make verify`, `make e2e`, `make ai-eval` and `make build` were not rerun. This
checkpoint attempt changed documentation only and made no runtime, prompt,
provider, package or deployment change. The preceding complete repository
gates remain historical evidence and are not claimed as fresh LPL-01 runs.

### Gate and required next action

D-0141 records the initial current-system stop condition and D-0142 records the
subsequent source reconciliation. APP-0047 / D-0143 now supplies exact owner
processing authorization for the four selected hashes. Exact-duration
validation then found that Introduction and Practice exceed the current
five-minute `model_audio` Media Library policy. D-0144 / DR-0021 therefore
stops ingestion until the long-form teaching-audio contract is chosen. After
that decision, LPL-01 still requires clean ingestion, an asset-bound rights
record, transcript reconciliation, a supported blueprint and English academic
plus Kannada review. LPL-02 remains unauthorized.

### Exact-media authorization and lesson-audio decision gate

| Boundary | Result | Evidence |
|---|---|---|
| Exact authorization | Pass | APP-0047 preserves the owner's statement and binds only the four source-map hashes |
| Allowed purpose | Pass | Private non-production ingestion, transcription and AI-assisted derivative lesson drafting through project-owned adapters |
| Delivery/release limit | Pass | Later web/Android eligibility in India only; sharing and production/public release prohibited |
| Existing controls | Pass | No-training, provider retention, privacy, exact review and immutable publication remain mandatory |
| Current media-kind fit | Fail closed | Introduction is 310,525.500 ms and Practice is 459,867.563 ms; both exceed `model_audio`'s 300,000 ms ceiling |
| Runtime mutation | Not performed | No media kind, limit, API, generated client, UI, object, rights row, provider or publication changed |
| Decision gate | Open | DR-0021 Option A recommends a distinct bounded `lesson_audio` kind while preserving `model_audio` unchanged |

The four authorized temporary source copies were used only to re-verify exact
hash/media-duration evidence. They were not treated as accepted assets or sent
to an AI/speech provider. No Assignment or learner-originated media was read.

Verification for this decision gate:

| Command or check | Result |
|---|---|
| Exact SHA-256 recheck of four temporary source copies | Pass; all four matched APP-0047/source-map hashes before cleanup |
| Rights-authorization artifact SHA-256 | Pass; `cc50ca8d13c9753bafffb195e952880014ed39bd08476601ccc428cff5f6b201` |
| Open DR-0021 SHA-256 | Pass; `c792ea46d2ace3ed3d517d9518fdf075000228b0b877131504cbbd5cf4ee472a` |
| `git diff --check` over the ten affected LPL/governance documents | Pass |
| Explicit trailing-whitespace scan over tracked and untracked affected documents | Pass |
| Final-newline, balanced-fence and local-link check over the ten documents | Pass |
| Credential/session/source-login scan over the ten documents | Pass |
| Temporary source-copy cleanup | Pass; all four explicit `/tmp/lpl01-authorized-*.mp3` paths absent |
| Runtime, E2E, AI-eval and build gates | Not run; no runtime, prompt/provider, dependency or packaging change was authorized at this open decision gate |

---

## USER-DOC-01 — learner, administrator and sales guides (10 August 2026)

### Outcome

Three audience-specific documents now describe the product without changing
its authority or maturity:

- the learner guide covers access, onboarding, daily missions, results,
  offline use, private media, Confidence Conversation, optional voice,
  learner-controlled memory, privacy, accessibility and troubleshooting;
- the administrator/operator guide covers privileged access, manifest-derived
  navigation, People, Learners, Content Studio, Media Library, audit,
  diagnostics, privacy boundaries, lifecycle checklists and safe retry; and
- the sales guide provides positioning, buyer segments, a status-qualified
  feature catalogue, dynamic-lesson explanation, responsible-AI boundaries,
  deployment prerequisites, pilot discovery, demonstration guidance, claims
  guardrails and roadmap communication.

The README and current status ledger link the three guides. No runtime, API,
schema, permission, provider, configuration, generated contract, localization
catalogue or architecture behavior changed.

### Acceptance matrix

| Boundary | Result | Evidence |
|---|---|---|
| Repository authority | Pass | Claims reconciled with master plan 3.2, current status, accepted ADR-0001–ADR-0014, active ADMIN specifications and Lesson Practice Loop plan |
| Learner coverage | Pass | Sign-in, onboarding, Learn, local speech, deterministic result, offline, video, conversation, voice consent, memory, role-play, privacy/accessibility and troubleshooting are distinguished by current maturity |
| Operator coverage | Pass | Current permission/MFA, organization, learner, content, media, operations, audit and privacy boundaries are described as organization-scoped governed workflows |
| Sales integrity | Pass | Every feature family uses repository-implemented, controlled/conditional, demonstration-only, planned or deferred status; prohibited outcome/release claims are explicit |
| Dynamic future lessons | Pass | Audio transcription/topic analysis, human-reviewed blueprint, Skill Chat, 3–7 item quiz, Apply and Review are described as planned rather than current auto-publication |
| Security/privacy/tenancy | Pass | Guides preserve membership-derived tenancy, atomic permissions, fresh TOTP, show-once secrets, consented voice, content-minimized diagnostics and deterministic learning authority |
| Discoverability | Pass | README and STATUS link all three audience documents; each guide has quick navigation and related-source links |
| Documentation structure | Pass | Local link targets, quick-navigation anchors, unique/separated headings, balanced fences, consistent table shapes, final newlines, tabs and whitespace were checked successfully |

### Verification performed

| Command or check | Result |
|---|---|
| `git diff --check -- README.md docs/STATUS.md docs/DECISIONS.md docs/guides/LEARNER_USER_GUIDE.md docs/guides/ADMINISTRATOR_USER_GUIDE.md docs/SALES_FEATURE_GUIDE.md` | Pass |
| PHP local Markdown-target checker over README, STATUS, DECISIONS, QUALITY_REVIEW and the three guides | Pass; every local file target resolved |
| PHP heading/fence/spacing checker over the three guides | Pass; headings unique and separated, fences balanced |
| PHP table-shape checker over the three guides | Pass |
| PHP final-newline/tab/quick-navigation-anchor checker over the three guides | Pass |
| Ruby versions of the first structure checks | Not run; `ruby` is unavailable on this host, so equivalent PHP checks were run successfully |
| `make verify`, `make e2e`, `make build`, `make ai-eval` | Not run; this bounded task changes documentation only and does not change product, API, packaging, prompts, models, providers or orchestration |

### Security, privacy and tenancy review

- No secret, credential, learner record, raw audio, transcript or unlicensed
  media was added.
- The learner guide presents voice as optional and consent-specific and warns
  against shared-device offline storage and sensitive chat content.
- The administrator guide preserves organization scoping, permission and
  appointment separation, fresh-MFA/idempotency requirements, private media
  and immutable/versioned authority.
- The sales guide prevents repository tests, synthetic demonstrations,
  provider adapters, plans and owner attestations from being represented as
  live production, approved curriculum/media or automatic authority.

### Migrations and configuration

None. OpenAPI and generated clients are unchanged because no API changed.

### Known limitations and retained gates

- The guides describe the repository baseline, not a production deployment or
  customer-specific operating manual.
- Product screenshots, independently reviewed translations, a complete
  production curriculum/media catalogue and customer-specific support/contact
  details are not available for inclusion.
- At USER-DOC-01 completion, the ADMIN-MEDIA PostgreSQL race/full release-gate
  gap and every independent production review remained open. The repository
  gate gap has since passed as recorded in the LPL-00 entry above; independent
  production reviews remain open.
- Planned Lesson Practice Loop capabilities remain non-contractual until their
  ordered checkpoints are implemented and accepted.

### Checkpoint state at review time

The next action at USER-DOC-01 completion was to obtain PostgreSQL-enabled
ADMIN-MEDIA-01 race evidence and complete the retained release gates before
LPL-00. Those gates subsequently passed; the LPL-00 decision gate above is the
current authority.

## ADMIN-MEDIA-01 — governed private media library implementation review (10 August 2026)

> Historical implementation review before the retained repository gates were
> rerun. The LPL-00 prerequisite-closure entry above supersedes its open local
> gate statements; production and independent-review limitations still apply.

### Scope reviewed

- Organization-scoped model-audio and lesson-video media library
- Resumable private quarantine, exact object hashing, inspection and malware-scan evidence
- Explicit quarantine acceptance/rejection, cleanup reconciliation and private preview
- Append-only English/Kannada accessibility metadata and review evidence
- Program-scoped appointed rights confirmation, exact Content Studio attachment,
  immutable replacement lineage and retirement
- OpenAPI/generated contracts, Media Library UI, permission-derived actions and
  tenant-safe route behavior

### Review result

| Check | Result | Evidence |
|---|---|---|
| Schema and tenant constraints | Pass on SQLite; PostgreSQL rerun outstanding | `AdminMediaLibrarySchemaTest` |
| Upload, part, finalization, scan and quarantine lifecycle | Pass | `AdminMediaLibraryApiTest` |
| Governance, rights, attachment, replacement and retirement | Pass | `AdminMediaGovernanceApiTest` |
| Cleanup and deletion-receipt reconciliation | Pass | `MediaLibraryMaintenanceTest` |
| Cross-tenant, web-only, fresh-MFA, strict-body and idempotency boundaries | Pass | focused media API/route tests |
| Private object inspection and preview response behavior | Pass | media object-store and focused API tests |
| PostgreSQL concurrency | Not run to completion | Five race tests stopped at explicit `pdo_pgsql` guard; host exposes only `pdo_sqlite` |
| OpenAPI/generated-client parity | Pass | `pnpm run openapi:check` |
| Web Media Library/API behavior | Pass | 229 tests |
| Production scanner/object-store, independent review or release | Not claimed | checkpoint specification and retained gates |

### Commands run

| Command | Result |
|---|---|
| Focused media PHPUnit suites | Pass, 9 tests / 291 assertions |
| Focused PostgreSQL concurrency suite | Environment-blocked at `pdo_pgsql` guard, 5 tests |
| Focused web media/API suite | Pass, 229 tests |
| `pnpm run openapi:check` | Pass; OpenAPI 0.21.0 and generated contracts match |
| PHP Pint | Pass |
| PHPStan level 8 | Pass, 0 errors |
| ESLint | Pass |
| Recursive TypeScript check | Pass |
| `git diff --check` | Pass |

### Security, privacy and tenancy review

- Organization ownership derives from the active membership context; request
  bodies cannot select a tenant, storage target, scanner, actor or authority.
- Upload bytes and accepted objects remain private, and preview responses expose
  only verified type/length/hash headers. Object keys, media bodies and
  accessibility text are excluded from logs, audit, outbox and shell search.
- Clean scan evidence and explicit human acceptance precede asset creation;
  APP-0044 remains umbrella ownership evidence and never manufactures exact
  rights approval.
- Metadata, review, rights, attachment and replacement evidence is immutable or
  append-only. Ordinary retirement does not rewrite prior publications or pins.
- No accepted-asset physical deletion, public URL, direct-to-storage upload,
  legacy import or provider spend was introduced.

### Known limitations

- The host lacks `pdo_pgsql`, so real PostgreSQL lock/race evidence and
  PostgreSQL migration rerun evidence remain outstanding for this checkpoint.
- Locale validation was blocked by the host's Node 22.22.1; the repository
  pins Node 24.18.1 for that TypeScript-backed command.
- `make verify`, `make e2e`, `make build` and the PostgreSQL backup/restore drill
  were not rerun after the media slice; no success is claimed for them here.
- Production scanner/object-store validation, independent specialist review,
  legal validation, deployment and production/public release remain separate
  gates.

## ADMIN-MEDIA-01 rights attestation and decision gate — opened before implementation (9 August 2026)

> Historical pre-implementation gate record. Superseded for current repository
> state by the ADMIN-MEDIA-01 implementation review above; its non-production
> and approval limitations remain applicable.

### Outcome

The Accountable Product Owner supplied the exact statement `I have overall
rights of all media used here. Proceed with the next steps.` It is preserved in
[APP-0044](APPROVALS.md#app-0044), D-0138 and
[ADMIN_MEDIA_RIGHTS_ATTESTATION.md](ADMIN_MEDIA_RIGHTS_ATTESTATION.md), whose
SHA-256 is
`51e8835828cd5d0d31bfcb074db356c76f73e7e28d387f0a7658fdf5a8b668ca`.

The statement resolves owner-level provenance uncertainty and permits the
next ordered specification work. It does not identify exact bytes or supply
the program, allowed uses, channels, territories, effective period,
permission or accepted appointment required for an approved runtime rights
revision under ADR-0004.

### Existing-foundation findings

- `media_assets`, `media_rights_revisions` and
  `content_revision_media_assets` are tenant constrained and PostgreSQL
  append-only. Accepted assets have exact byte/hash/private-object metadata,
  but no upload-session, quarantine, scan, metadata-revision, replacement,
  retirement or operator lifecycle.
- `MediaRightsConfirmationCommands` already requires an exact asset hash,
  program, rights scope, `media.rights.approve`, accepted in-scope
  `rights_confirmer` appointment, transaction, idempotency, safe audit and
  outbox evidence. A blanket assertion cannot bypass those gates.
- Private verified model-audio and lesson-video delivery exists. The repository
  has no configured malware scanner or general media inspector; Fileinfo and
  caller MIME alone are insufficient for ADMIN upload acceptance.
- Publication manifests pin media and rights revisions but do not yet pin a
  clean-scan record or governed transcript/text-alternative revision.
- Learner-visible transcript and text-alternative fields require
  academic/Kannada revision and review semantics; mutable unreviewed asset
  fields would conflict with ADR-0004.
- The six frozen ADMIN-MEDIA permission definitions and exact Direct role-pack
  grants are not present. There is no `/operator/media` route, OpenAPI
  operation, generated-client surface or Media Library UI.

### Gate decision

[DR-0019](DR-0019_ADMIN_MEDIA_LIBRARY_RIGHTS_LIFECYCLE_REQUEST.md) recommends
Option A: retain APP-0044 as reusable umbrella evidence while implementing an
exact-asset, resumable private quarantine, deterministic inspection, clean
project-owned scanner result, explicit quarantine acceptance, governed
accessibility revisions, appointed per-program rights confirmation, immutable
replacement/attachment and private preview lifecycle.

The request also fixes proposed upload/quarantine retention, fail-closed
scanner behavior, content-free telemetry, object reconciliation and the
boundary between ordinary retirement and rights/safety suspension. Option B's
blanket auto-approval is rejected by the recommendation because it would
weaken accepted exact-hash and appointment authority.

### Current evidence and non-claims

- Authority and affected code/tests were inspected before any runtime edit.
- Governance documents only were changed for this gate.
- No migration, permission seed, route, upload, storage copy, scan result,
  rights revision, API/client contract or UI behavior has been implemented or
  claimed.
- Legacy/reference-site data or media import remains unauthorized. Existing
  gitignored legacy feedstock is neither copied nor newly approved by this
  gate.
- Independent security/privacy/tenancy/accessibility/language review,
  qualified legal review, deployment and production/public release remain
  open.

ADMIN-MEDIA-01 runtime work remains paused until the Accountable Product Owner
resolves DR-0019. No later ADMIN checkpoint may begin opportunistically.
- At ADMIN-LEARNER-01 completion, ADMIN-CONTENT-01 became the next ordered
  checkpoint. Its subsequent open decision gate is recorded below. Support
  notes remain blocked until their separate purpose, visibility, correction,
  legal-hold, retention and erasure policy is accepted.

## ADMIN-CONTENT-01 decision gate — opened before implementation (9 August 2026)

### Outcome

The required authority and implementation inventory found material ambiguity
before runtime work. ADMIN-CONTENT-01 promises level-to-item authoring, but
graph schema v1 has no level concept; current commands cannot create a program,
version, content document or structural graph; a reviewable graph also requires
a core mission and exact graph review not exposed by the operator API; and
there is no accepted draft-workspace, same-owner successor, typed-payload,
media-readiness or schedule-cancellation contract.

[DR-0018](DR-0018_ADMIN_CONTENT_AUTHORING_PUBLICATION_REQUEST.md) records the
recommended versioned owner-workspace policy with explicit graph v2 levels,
core delivery bundles, typed safe content forms, exact review queues and the
complete existing publication lifecycle. At this gate the request was open;
its Option A was not implementation authority until explicitly accepted.

### Evidence inspected

- Master-plan §§5.4, 6, 8.2, 16 and 31; ADR-0004 in full; ADR-0014; the
  approved parity plan, frozen governance matrix and PR-005 verification scope.
- Curriculum structure/governance/publication migrations; graph projector and
  governance commands; English/Kannada append commands; review, immediate
  publish, schedule, activation, retirement and rollback services.
- Operator content authorization, queries, controller, request validation,
  routes, OpenAPI schemas/generated-client surface, React summary panels and
  focused SQLite/PostgreSQL tests.
- Synthetic seed/import/clone paths and learner mission consumption, including
  the exact graph closure and media-dependent daily-mission behavior.

### Security, privacy, tenancy and authority result

- No owner, level, successor, payload or lifecycle behavior was inferred from
  implementation convenience. The decision request preserves active-
  membership tenant context, existing atomic permissions, reviewer separation,
  immutable releases and current rights predicates.
- The recommendation adds no global content owner, super-admin path, public
  preview, raw markup execution, AI authority, reference data/media import or
  learner-personal field.
- Content bodies remain prohibited from logs, analytics and audit/outbox
  payloads. Published and referenced history remains immutable/append-only;
  physical draft disposal stays separately reviewed.

### Change and verification boundary

- Added DR-0018 and reconciled only `STATUS.md`, `DECISIONS.md`, this review,
  the parity plan and governance matrix to the open gate.
- No product schema/migration, permission/default grant, route, OpenAPI or
  generated client, localization catalogue, UI, configuration, dependency,
  fixture or runtime behavior changed.
- `git diff --check` passed for the affected tracked documentation; the
  six-file final-newline, balanced-fence and local-link integrity check passed;
  and the cross-document DR/checkpoint state inventory returned the expected
  open-gate references. These are the proportionate checks for this stage.
  `make verify`, E2E, build, migration, backup/restore and provider suites are
  not represented as rerun by this documentation-only decision gate.

### Gate at that point

ADMIN-CONTENT-01 runtime work was paused until DR-0018 was resolved and the
accepted policy was translated into a bounded active specification. The next
entry records that resolution; production/public release remained
unauthorized.

## ADMIN-CONTENT-01 approval and active specification (9 August 2026)

DR-0018 Option A was accepted exactly as written under
[APP-0043](APPROVALS.md#app-0043) and D-0136. The approval record preserves the
pre-resolution proposal SHA-256
`9774813580bd9522fa9c077bbca0a3cfda9c37d834d82eb2b1ca66b34f8abd72` and the
exact statement `Approve DR-0018 Option A as written.`

[ADMIN-CONTENT-01_SPEC.md](ADMIN-CONTENT-01_SPEC.md) now fixes the additive
owner-workspace/level/successor schema, typed content adapters, private preview,
exact review/readiness queues, guarded publication lifecycle, stable failures,
accessible web boundary and required exit evidence. The specification adds no
permission, grant, platform capability or media-management authority.

This entry records approval and specification publication only. It does not
claim an implemented migration, API, UI, test result, deployment or production
release. Runtime work may now proceed only within the active specification;
every later ADMIN row remains ordered or blocked as previously recorded.

## ADMIN-CONTENT-01 — governed Content Studio completion (9 August 2026)

### Outcome

Repository implementation is complete under DR-0018 Option A / APP-0043 /
D-0136, D-0137 and the bounded specification. The delivered vertical slice
creates organization-owned programs and append-only graph-v2 workspaces,
authors bounded governed text and daily-speaking missions, exposes exact
private preview and review/readiness queues, creates same-owner successors and
operates the guarded immediate/scheduled publication lifecycle. Existing
graph-v1 publications remain immutable. Media management, cross-owner sharing,
public preview, assignment, AI drafting and production release remain excluded.

### Acceptance matrix

| Boundary | Result | Evidence |
|---|---|---|
| Additive workspace schema | Pass | Reversible program descriptor events, explicit levels, draft snapshots, workspace lifecycle/events and actor/request-bound receipts; SQLite and PostgreSQL rollback/reapply preserve graph-v1/publication tables |
| Tenant-safe authoring | Pass | Active membership-derived ownership, existing atomic permissions, strict bodies, tenant-composite relationships, same/cross-tenant API coverage and foreign identifiers mapped to not-found |
| Structured graph/content | Pass | Key-related graph-v2 forms through delivery bundles; typed governed-text and daily-mission English/Kannada adapters; no raw JSON/HTML authoring or unknown-schema mutation |
| Review and readiness | Pass | Exact graph/source/variant heads, code-owned rationales, creator/reviewer and translator/reviewer separation, appointment invalidation and allowlisted blockers/actions |
| Immutable publication lifecycle | Pass | Immediate publish, schedule, due activation, cancellation, retirement and rollback use exact heads, evidence revalidation, stable conflicts and idempotency; publication v1 bytes remain unchanged |
| Concurrency | Pass | Forced PostgreSQL lock-queue overlap covers graph save, English/Kannada heads, structure submission/edit, publish, schedule, rollback, permission revocation and appointment invalidation |
| Private web experience | Pass | Manifest gating, independent resource retry, structured authoring, no-store typed preview, explicit review/lifecycle confirmations, stale reload retaining unsaved memory, axe and 320-pixel reflow |
| Contract drift | Pass | OpenAPI 0.20.0, generated TypeScript contracts, route policy and six governed catalogues validate together |

### Verification performed

| Command or suite | Result |
|---|---|
| Migration rollback/reapply | Pass on SQLite and PostgreSQL, 1 test / 20 assertions on each |
| Focused Content Studio API on SQLite/PostgreSQL | Pass, 1 test / 78 assertions on each; the broader focused content/lifecycle/shell set passed 22 tests / 513 assertions on PostgreSQL |
| Focused Content Studio web/API-client suites | Pass, 27 tests |
| Real PostgreSQL content concurrency suites | Pass, 9 tests / 95 assertions; both workers were observed in the lock queue for every two-writer race |
| Final `make verify` | Pass: Composer/pnpm validation and audits; Pint; PHPStan; 128 SQLite tests / 1,526 assertions; 257 PostgreSQL tests / 3,541 assertions; locale, OpenAPI/client and release-contract checks; ESLint; recursive TypeScript; 247 package/web tests |
| Final `make e2e` | Pass, 15/15 Chromium journeys, including the governed Content Studio journey |
| Final `make build` | Pass; Laravel cache preparation/cleanup and production Vite build succeeded with the retained chunk-size warning |
| `POSTGRES_DB=global_english_test make backup-restore-drill` | Pass; exact schema, migration ledger and protected aggregate reconciliation; measured local RTO 1 second; artifact `artifacts/ops/backup-restore-20260809T173601Z.json` |
| `make ai-eval` | Not run; no prompt, model, provider or orchestration behavior changed |

The complete gate initially identified a redundant PHP type guard, an
anonymous-migration test type, a stale request-validation fixture and an old
catalogue revision expectation. Each underlying fixture/type issue was fixed
and its focused check passed before the uninterrupted complete gate passed.
The concurrency audit then identified that workspace source/variant edits
needed to hold the workspace state lock through the nested append transaction;
the lock boundary and forced-overlap coverage were added before final
verification. No failing result was suppressed or described as success.

### Migration and configuration

- `2026_08_09_000140_create_admin_content_workspace.php` adds only the
  descriptor/workspace/level contract, partial active-sequence indexes and
  PostgreSQL append-only/state constraints. Its guarded rollback refuses to
  discard live workspaces and restores the legacy module schema on SQLite.
- OpenAPI advances to 0.20.0 and generated clients/types change with the API.
  All six UI catalogues advance to the exact governed source revision/hashes.
- No permission definition, role grant, platform capability, environment
  variable, dependency, provider binding, secret or background schedule was
  added.

### Security, privacy and tenancy review

- Reads and commands derive organization only from the active membership.
  Every new tenant-owned relation is tenant-composite where PostgreSQL can
  enforce it; guessed foreign IDs disclose neither descriptors nor content.
- Commands remain first-party web-only, fresh-TOTP protected, header-
  idempotent, strict-allowlist and post-lock reauthorized. Passport bearers,
  missing exact permission, stale state and changed replay cannot mutate.
- Workspace, source, Kannada, review and publication heads serialize through
  deterministic locks. Concurrent winners advance exactly once and losers
  receive stable allowlisted conflicts without partial receipts/evidence.
- Submitted graph revisions, review decisions, publication manifests and
  lifecycle events are immutable/append-only. Published correction uses a
  successor or rollback; no ordinary hard-delete path exists.
- Content and preview bodies are absent from audit/outbox payloads, logs,
  shell search, URLs and browser persistence. Preview is private/no-store and
  renders only inert typed fields.

### Localization and accessibility review

- Content Studio keys have exact parity and governed hashes in `en-IN`,
  `kn-IN`, `ml-IN`, `ta-IN`, `te-IN` and `hi-IN`. The five non-English
  catalogues remain declared implementation previews with visible approved-
  English production fallback pending independent review.
- Automated component/browser evidence covers independent failures, labelled
  structured fields, keyboard operation, confirmations, stale conflict
  recovery, desktop/mobile axe scans and no horizontal overflow at 320 CSS
  pixels. This is automated evidence, not independent human accessibility or
  language review.

### Known limitations and retained gates

- ADMIN-MEDIA-01 is next. Content Studio cannot upload, browse, attach,
  replace, scan, approve rights for or retire media; a daily mission missing
  its exact current model-audio/right evidence remains deliberately blocked.
- Unknown legacy payload schemas remain read-only. Cross-owner clone/share,
  enrollment assignment, cohort targeting, public preview, CMS, AI drafting,
  provider/spend and legacy production import remain outside this checkpoint.
- Production-volume authoring/query behavior is unmeasured. The local logical
  restore drill is not production RPO/RTO, backup encryption, offsite/PITR,
  legal-hold or restore-custody evidence.
- GitHub-hosted CI, independent security/privacy/tenancy/accessibility/language
  review, qualified legal validation, deployment and production/public release
  remain separate gates. The existing Vite chunk-size warning remains.

## APP-0053 Ubuntu soft launch — 10 August 2026

### Scope reviewed

- Soft-launch mandate and LPL-01 critical-path pause;
- conversation HTTP gate allowing `azure-openai-dev` in soft-launch/staging/
  conversation-model-dev;
- Azure spend ceiling raised to ₹10,000 with APP-0053 spend evidence;
- Ubuntu host runtime scripts, Caddy proxy, Cloudflare tunnel and runbook.

### Review result

| Check | Result | Evidence |
|---|---|---|
| APP-0053 / D-0155 / D-0156 recorded | Pass | APPROVALS, DECISIONS, STATUS |
| Soft-launch runbook present | Pass | `docs/SOFT_LAUNCH_UBUNTU_RUNBOOK.md` |
| Production live drivers still unauthorized | Pass | Gate rejects production + azure-openai-dev |
| Local/testing remains fake-required | Pass | `ConversationEnvironmentGateTest` |
| Soft-launch azure authorized | Pass | `ConversationEnvironmentGateTest` |
| Makefile soft-launch targets | Pass | `make help` soft-launch-* entries |

### Limitations

- Soft launch is invite-only on a home/static-IP host; not Play/public production.
- Live Azure still requires filled outside-repo credentials before tutoring works.
- Operator must still publish one pathway and invite learners through the console.

## APP-0053 provider-boundary and restart hardening — 10 August 2026

### Scope and finding

- The live Ubuntu operational review confirmed the real API/web/worker/
  scheduler/Caddy/tunnel stack and two existing active publication heads, but
  found that the runtime did not source the dedicated conversation env named by
  the runbook. The no-call preflight read that file but not the separate kill
  switch location, so following the documented enablement sequence could not
  pass.
- The first setup generated syntactically valid placeholder evidence IDs/hashes.
  Readiness did not distinguish them from reviewed evidence, and ordinary
  provider preflight could accept those sentinel shapes after endpoint/secret
  completion. No live key existed and the switch was false, so no request or
  spend occurred.
- Restart stopped an active Cloudflare tunnel without recreating it and retained
  its stale URL. That made a routine configuration restart unnecessarily remove
  the primary HTTPS entry.

### Implemented controls

- Runtime, readiness and no-call preflight now share a tested outside-repository
  loader. The conversation env owns endpoint/deployment/evidence settings; the
  main soft-launch env owns `APP_ENV`, provider driver and the live-request kill
  switch, even when an earlier setup copied stale provider values into it.
- Setup preserves an existing conversation env. A fresh scaffold is explicitly
  unapproved and uses invalid `REPLACE_*` values. Runtime provider authorization
  also rejects the exact older scaffold IDs/hashes and all endpoint, deployment
  or evidence replacement sentinels before HTTP.
- Readiness reports effective non-secret configuration and turns every Azure
  placeholder/missing item into a failure when the live switch is true. With the
  switch false, the current host reports 13 passes, six explicit warnings and
  zero failures.
- Restart recreates a tunnel only when one was active; explicit stop removes the
  stale URL. The deterministic `make ai-eval` target now pins the local fake
  provider regardless of an ignored developer env file.

### Verification evidence

| Check | Result |
|---|---|
| Shell syntax and APP-0053 env regression | Pass; shared precedence, no-call preflight, secret non-disclosure, idempotent setup and invalid fresh scaffold covered |
| Focused Azure/gate tests | Pass; 10 tests / 35 assertions |
| Final `make verify` | Pass with PHP 8.4.23, isolated Composer 2.10.2, Node 24.18.1 and pnpm 11.18.0: Composer/pnpm audits, Pint, PHPStan, 166 SQLite tests / 1,894 assertions, 297 PostgreSQL tests / 4,345 assertions, locale governance, OpenAPI/client drift, release contract, shell contract, ESLint, TypeScript and 270 package/web tests |
| `make e2e` | Pass; 4 API tests / 105 assertions and 16/16 Chromium journeys |
| `make ai-eval` | Pass; 15/15 deterministic cases, zero failures and zero estimated cost; separately gated speech half skipped |
| `make build` | Pass; Laravel cache prepare/cleanup and production web build, with the retained Vite chunk-size warning |
| Live restart and public smoke | Pass after persistent user-service relaunch; API/worker/scheduler/Caddy/public HTTP/tunnel running, local live/ready OK, public root HTTP 200 and public ready HTTP 200 |
| Fresh soft-launch backup | Pass; gzip-valid mode-`0600` `soft-launch-20260810T160504Z.sql.gz`, 117,967 bytes, SHA-256 `43d0e58d4df13d922db946388922464bad6b1598dd8eb76f4a6ad3ecca796337` |

The first two full-verification invocations stopped in `check-toolchain` before
tests because they referenced a nonexistent Composer path and then the host's
Composer 2.9.5 rather than the required 2.10.2. Final verification used the
exact official 2.10.2 PHAR. The first unoverridden AI-eval invocation failed
closed because the ignored local env had no fake driver; this exposed and led
to the deterministic target correction above. The first restart launched from
the task's transient process group was cleaned up with that group, producing a
temporary tunnel 530; database/cache/object-store containers remained healthy,
and the stack was relaunched under the Ubuntu user service manager before the
passing public smoke.

### Retained gates

- Current outside-repo vendor evidence still contains the old scaffold values;
  endpoint, allowlisted host and deployment remain placeholders; the secret is
  empty; and the live-request switch remains false. Live Confidence
  Conversation and role-play tutoring are therefore unavailable by design.
- The current user service is transient and user lingering is disabled. It
  survives the active desktop session but is not reboot/logout persistence or a
  production service-manager claim.
- No schema, OpenAPI, generated client, tenant resource, authoritative learning
  record or provider output changed. Production/Play release, uncapped spend and
  independent legal/security/privacy/language approval remain unauthorized.

## APP-0053 Conversation Learning Flow closure — 10 August 2026

### Scope and delivered behavior

- Reconciled the accepted Student AI Conversation Learning Flow against the
  live soft-launch container composition, API contract, immutable provider-cost
  evidence, Friend UI, localization governance and Ubuntu runtime.
- Added project-owned confirmed-text STT-stage and text-first TTS-stage
  adapters. The nonproduction Azure composition now calls the model only after
  normalized learner-confirmed text and reports optional client/on-device
  playback without pretending server audio exists.
- Replaced the fake-only application assertion and PostgreSQL cost-ledger
  constraint with one stage-aware policy. Local/testing accepts only exact
  zero-cost deterministic fake evidence. Allowlisted nonproduction Azure mode
  accepts only confirmed-text STT, Azure OpenAI model cost and text-first TTS.
- Added distinct immutable Skill Focus and Review Friend scenarios alongside
  Confidence Chat and the existing Role-play. Skill Focus defaults to Tutor;
  UI completion thresholds are 4/5/2 turns for Confidence/Skill/Review.
- Updated disclosure/privacy/status wording and all six governed catalogue
  drafts. Product mode identifies the partner as AI and advisory, states that
  marks/mastery/progress are unchanged, and distinguishes content-free ledgers
  from consent-governed voice retention. Demo mode remains synthetic.
- OpenAPI 0.24.0 and generated TypeScript contracts include all scenario keys,
  text-first speech rendering and nonnegative live estimated cost.
- Installed a repository-owned, enabled systemd user service and enabled user
  lingering. The prior transient task was stopped through the repository
  shutdown path and its exact surviving cgroup was cleared before replacement.

### Verification evidence

| Check | Result |
|---|---|
| Focused soft-launch provider composition | Pass; confirmed text → Azure-model fixture → client text-first playback, exact three-stage ledger and 25-microunit model cost |
| Skill Focus / Review integration | Pass; distinct tenant-owned immutable scenario snapshots, Tutor/Gentle defaults and definition hashes |
| Locale governance | Pass; six catalogues at revision `.3`, exact hashes/key parity; five declared pending-independent-review warnings |
| OpenAPI/generated client | Pass; OpenAPI 0.24.0 validates and generated TypeScript matches |
| Final `make verify` | Pass with PHP 8.4.23, Composer 2.10.2, Node 24.18.1 and pnpm 11.18.0: audits, Pint, PHPStan, 172 SQLite tests / 1,904 assertions, 299 PostgreSQL tests / 4,364 assertions, locale, OpenAPI/client drift, release/soft-launch contracts, ESLint, TypeScript and 272 package/web tests |
| Final `make e2e` | Pass; 4 API tests / 105 assertions and 16/16 Chromium journeys, including the updated text-first AI disclosure journey |
| `make ai-eval` | Pass; 15/15 deterministic cases, zero failures and zero estimated cost; speech benchmark half skipped because no authorized run ID was supplied |
| `make build` | Pass; Laravel production cache preparation/cleanup and web production build; retained 1,024.01 kB chunk warning |
| Backup/migration/seed | Pass; pre-migration mode-`0600` gzip backup `soft-launch-20260810T170110Z.sql.gz`, 118,038 bytes, SHA-256 `115fa7869be6bb7ff379f750de85d81b7052a4e42403dbffefffcc3f16c44174`; migration `000180` and idempotent foundation seed passed |
| Persistent Ubuntu runtime | Pass; unit active/enabled/non-transient, `Linger=yes`; API/worker/scheduler/Caddy/public HTTP/tunnel running; live/ready healthy; tunnel root and local `:8088` return HTTP 200 |
| Readiness | Pass with retained warnings; 13 pass, six Azure placeholder/disabled warnings, zero failures |
| Public static-IP loopback probe | Open external dependency; `117.254.108.143:8088` refused from this host while the local listener returned 200, so router/ISP NAT forwarding is not evidenced |

The first full verification attempt used the host PHP/Node path and stopped at
the toolchain guard. The second reached PHPStan and exposed a non-exhaustive
test-only provider ordering expression; the next run exposed a stale seeder
count that still expected two rather than four scenarios. Both were corrected
without suppression and their focused tests passed before the final clean
repository-wide gate. The first E2E attempt found stale fake-product copy in
the browser assertion; the updated AI-boundary assertion then passed all 16
journeys. The first systemd install correctly refused to enable over the still-
resident transient definition; the installer now performs an exact graceful
shutdown/cgroup transition and is repeatable.

### Security, privacy, tenancy and authority review

- Conversation sessions, turns and provider ledgers retain organization scope,
  learner authorization, caller idempotency, immutable snapshot hashes and
  cross-tenant tests. No raw turn text is added to persistent conversation
  records or cost evidence.
- AI remains advisory and no marks, mastery, attendance, enrollment or unlock
  authority changed. New scenarios are development-only synthetic content and
  are not represented as independently reviewed curriculum.
- The persistent service contains no secret values; outside-repository mode-
  `0600` files remain the only configuration/credential source.
- ADR-0007/0008 still withhold ordinary live learner-audio Azure processing.
  The existing consent/upload/transcript-correction/deletion lifecycle remains
  fail-closed while that provider activation is disabled. No learner audio,
  Azure request, provider output or spend was created in this checkpoint.

### Retained gates

- Azure model evidence, endpoint, allowlisted host, deployment and secret are
  missing/placeholders and the live kill switch remains false. The temporary
  HTTPS tunnel therefore exposes a healthy product with live tutoring disabled
  rather than silently falling back to fake AI.
- Stable static-IP reachability still requires router/ISP NAT configuration or
  a reviewed stable HTTPS domain. A TryCloudflare hostname changes on restart.
- Five Indic catalogue revisions require independent human language review;
  approved-only production behavior continues to fall back to English.
- Independent security/privacy/tenancy/accessibility/content review,
  production-grade domain/TLS/monitoring/backups/PITR and production/Play
  release remain separate approvals. APP-0053 authorizes only the invite-only
  Ubuntu soft launch.

## Soft-launch port, identity and disclosed-key response — 10 August 2026

- Changed the repository-owned public HTTP default from `8088` to the exact
  router-forwarded port `8080`, rebuilt the production bundle and restarted the
  persistent enabled user service. Caddy now listens on `*:8080`; `:8088` is
  closed; local readiness and the regenerated HTTPS tunnel return HTTP 200.
- Updated the exact visible brand to **Global spoken English Course** across
  all six governed UI catalogues, HTML title, Capacitor configurations, Android
  labels and demo request metadata. Catalogue revision `.4`, hash parity,
  TypeScript and 31 localization tests pass. The five non-English catalogues
  remain pending independent review.
- No request, file write, DNS change or hosting change was made against the
  existing `https://www.globalspokenenglish.com` site.
- Two Azure Speech keys were disclosed in the task. They were not copied into
  the repository, outside-repository configuration, commands or logs and were
  not used for a provider request. Both must be regenerated before further
  work; Microsoft documents one-key-at-a-time regeneration for continuity.
- The existing Speech secret and environment files are mode `0600`, but safe
  no-call preflight remains blocked on environment/live authorization,
  endpoint/credential fields, exact vendor/legal/logging/credential/spend/
  price/content-scan evidence, runner revision, reviewed pricing/FX, safe
  report path and acknowledged ephemeral storage. This is an evidence/config
  result, not an invalid-resource conclusion.
- Host-local public-IP `117.254.108.143:8080` timed out while
  `127.0.0.1:8080` returned 200. Because the same-LAN host may lack NAT
  loopback, a cellular/VPS probe is required. Ubuntu firewall state could not
  be inspected without administrator authentication; the listener itself is
  bound to all interfaces.
- Final pinned-toolchain `make verify` passed Composer/pnpm audits, Pint,
  PHPStan, 172 SQLite tests / 1,904 assertions, 299 PostgreSQL tests / 4,362
  assertions, locale/OpenAPI/generated-client/release/soft-launch contracts,
  ESLint, TypeScript and 272 package/web tests. Final `make e2e` passed 4 API
  tests / 105 assertions and all 16 Chromium journeys. `make build` passed with
  the retained measured bundle-size warning. AI evaluation was not rerun for
  the port/name-only change; the immediately preceding 15/15 deterministic
  APP-0053 result remains applicable.

## APP-0054 Speech credential and preflight correction — 10 August 2026

- Recorded the product owner's bounded temporary-use instruction without
  copying either disclosed key into Git, a command, a log or an application
  environment. One existing active credential remains only in the
  outside-repository mode-`0600` Speech secret; the recovery key is not stored
  alongside it.
- Inspected only protected-file metadata: the Speech secret and benchmark env
  are both mode `0600`. No secret value was printed or compared in process
  output.
- Found that `run-speech-benchmark-preflight.sh` did not load the protected
  benchmark environment and therefore reported application-default blockers.
  The wrapper now loads the configured env when present while preserving the
  no-Azure-call preflight phase and fail-closed behavior for missing/incomplete
  workspaces.
- Configured no-call preflight now passes with `status: ready`. The retained
  safe report shows the completed 5 August Azure Speech synthetic benchmark
  passed completeness, quality, performance, reliability, cost and listener
  gates at estimated spend ₹13.2052 under the ₹1,000 cap. The report itself
  states that synthetic speech is not learner-accent evidence and passing does
  not authorize learner data or production.
- Azure management-plane inspection remains blocked by tenant security
  defaults. Both a tenant-scoped login and clean account-discovery login
  authenticated the supplied Outlook account but failed against `Default
  Directory` with `AADSTS530035`; the clean login then reported no current
  subscriptions. The pre-existing CLI cache still names enabled `Azure
  subscription 1`, but cached discovery is not ARM authority. No Azure OpenAI
  resource/deployment or credential has therefore been inferred or created.

Verification: `sh -n scripts/run-speech-benchmark-preflight.sh` passed and the
configured wrapper returned the expected no-call `ready` payload. Final pinned
toolchain `make verify` passed Composer/pnpm audits, Pint, PHPStan, 172 SQLite
tests / 1,904 assertions, 299 PostgreSQL tests / 4,364 assertions, locale,
OpenAPI/generated-client/release/soft-launch contracts, ESLint, TypeScript and
272 package/web tests. The first invocation stopped at the toolchain guard on
host Node 22; the final run used the repository's pinned Node 24.18.1, PHP
8.4.23, pnpm 11.18.0 and Composer 2.10.2. Ordinary learner Azure Speech DI,
the Azure OpenAI live kill switch and production remain disabled.

## APP-0053 Azure OpenAI resource provisioning — 10 August 2026

- Restored Azure CLI Owner access through MFA-capable browser authentication;
  both earlier device-code attempts had authenticated the personal Microsoft
  account but failed tenant security defaults with `AADSTS530035`.
- Read-only provider/SKU inspection confirmed the Central India Speech resource
  remains `Succeeded`, but `centralindia` has no OpenAI account SKU. Jio India
  Central appeared in the Cognitive SKU feed but was rejected by subscription
  location policy before any resource group existed.
- Created the tagged `ge-ai-softlaunch` resource group and
  `ge-conversation-southindia` OpenAI S0 resource in South India. Created the
  India-regional `Standard` deployment `gpt-4-1-mini-southindia` at capacity 3,
  pinned model version `2025-04-14`, then changed Azure's default automatic
  upgrade behavior to `NoAutoUpgrade`. Azure reports successful provisioning
  and inference retirement on 14 April 2027.
- Newer South India chat deployments are exposed only through APAC data-zone
  or global SKUs. The selected legacy regional model preserves the ADR-0010
  India boundary for the bounded soft launch while creating an explicit 2027
  migration deadline.
- Wrote Key 1 directly from Azure CLI to the existing outside-repository secret
  without terminal output. Secret, environment, vendor evidence and resource
  metadata are mode `0600`; the second key was not copied. Endpoint,
  allowlisted host, deployment, contract hashes and credential-version fields
  now contain factual values rather than scaffolds.
- Verified the Ubuntu outbound IPv4 equals the declared static IP, then changed
  the Azure OpenAI data-plane ACL to default deny with only that exact IPv4
  allowed. ARM management remains available; no subnet, broad CIDR or trusted-
  service bypass was added.
- Azure's resource capability list does not contain `ContentLogging=false`.
  Official Microsoft documentation says default abuse monitoring can retain
  selected flagged prompts/completions for possible human review. Vendor
  evidence therefore truthfully records logging enabled and live activation
  false. The modified-abuse-monitoring application was opened for the owner;
  submission and Microsoft approval remain external.
- No model inference request, learner text, provider response or model-token
  spend occurred. Readiness improved from 13 pass / six warnings to 17 pass /
  one disabled-live warning / zero failures. A live-enabled no-call
  configuration preflight returns only `vendor_evidence_logging_enabled`.

Security/privacy: the deployed model is advisory and pinned; learner/model
traffic remains fail-closed. Public data-plane access remains enabled for the
Ubuntu origin but is inert while the application kill switch is false. Network
restriction, modified-abuse-monitoring verification, capped smoke evidence and
credential rotation remain required before continuing rollout.

## DR-0026 password-only authentication decision review — 11 August 2026

### Scope reviewed

- product-owner request for username/password authentication for every role;
- conflicts with the master plan, ADR-0002 and DR-0013 Option A;
- current NIST memorized-secret and OWASP authentication/password-storage
  guidance;
- learner enrollment, privileged reauthentication, recovery, migration,
  web-session and Android-PKCE consequences; and
- the active LPL-01 reconciliation dependency.

### Review result

| Check | Result |
|---|---|
| Requested behavior is represented exactly | Pass — DR-0026 Option B uses username/password as the only routine and privileged factor |
| Existing authority is silently weakened | Pass — no runtime/schema/API change was made and current OTP/TOTP enforcement remains active |
| Single-factor privileged risk is disclosed | Pass — credential stuffing, phishing, reuse, spraying and offline-guessing exposure are explicit |
| Password baseline is defined | Pass, proposal only — 15–128 Unicode code points, NFC, blocklist, Argon2id, no composition rule or periodic rotation |
| Recovery is treated as authoritative security behavior | Pass, proposal only — ordinary verified-contact recovery and dual-authorized privileged support recovery require revocation and audit |
| Web/Android credential transport remains architecture-compliant | Pass, proposal only — Sanctum cookie sessions and system-browser Passport PKCE remain unchanged |
| Production or independent review is claimed | Pass — neither is authorized or claimed |

### Security, privacy and tenancy review

- Option A is recommended because it removes OTP friction from routine sign-in
  while retaining an independent factor for high-impact privileged commands.
- Option B is the product-owner-requested profile. Fresh password confirmation
  mitigates unattended-session use but is not MFA and does not protect against
  a stolen password.
- Usernames remain identity-level records; organization authorization remains a
  separate current-membership and command-time policy decision.
- Passwords, reset tokens and recovery evidence must never enter logs, audits,
  browser storage, ordinary Capacitor preferences or administrator-visible
  fields.

### Verification and limitations

- `make verify` passed with the repository toolchain: Composer/pnpm audits,
  Pint, PHPStan, 172 SQLite tests / 1,904 assertions, 299 PostgreSQL tests /
  4,362 assertions, locale validation with the five declared independent-review
  warnings, OpenAPI/generated-client/release/soft-launch contracts, ESLint,
  TypeScript and 272 package/web tests.
- Changed-document final-newline, balanced-fence and local-link validation
  passed; `git diff --check` also passed.
- No migration, OpenAPI change, generated client, UI, authentication behavior
  or test behavior was added.
- DR-0026 was subsequently resolved as Option B under APP-0055 / D-0163 /
  ADR-0021. That approval authorized the master-plan amendment and
  AUTH-PASSWORD-01 specification, not runtime implementation. LPL-01
  reconciliation remains blocked by the current fresh-TOTP requirement in the
  interim.

## APP-0055 / ADR-0021 password-only architecture acceptance — 11 August 2026

### Scope reviewed

- exact product-owner approval and approved pre-resolution DR hash;
- APP-0055, D-0163 and ADR-0021 traceability;
- master-plan authentication references and version amendment;
- existing OTP/TOTP/session/Passport/PostgreSQL implementation inventory; and
- bounded AUTH-PASSWORD-01 schema, API, recovery, cutover, retirement and test
  specification.

### Review result

| Check | Result |
|---|---|
| Approval matches the requested Option B text | Pass — exact statement and pre-resolution SHA-256 `847486461ffbf92610427744539bea5a3fd607b2988d8aea8a9ff54fbcd0576d` recorded |
| ADR explicitly amends the master plan | Pass — ADR-0021 names affected sections and canonical plan advances from 3.2 to 3.3 |
| Password confirmation is mislabeled as MFA | Pass — every new authority calls it one-factor freshness and records the accepted risk |
| Existing protections were bypassed | Pass — documentation only; current OTP/TOTP routes, constraints and runtime remain unchanged |
| Implementation boundary is complete enough for review | Pass — schema, APIs, transport, recovery authority, migration/cutover, retirement, threat tests and exit gates are fixed |
| Production or independent review is claimed | Pass — neither is authorized or claimed |

### Security, privacy and tenancy review

- The accepted profile deliberately permits one password to authorize
  privileged commands. Argon2id, long-password policy, blocklisting,
  throttling, generic responses, five-minute confirmation and revocation reduce
  but do not eliminate credential theft risk.
- Usernames and credentials remain platform-global. Organization authority
  remains membership/permission/appointment scoped and is re-evaluated for
  every protected command.
- Privileged recovery requires two distinct platform-capability holders, a
  24-hour delay and no operator ability to see or set the target password.
  Production provisioning of that authority remains a separate operational
  gate.
- Existing mobile identities are not silently repurposed as recovery contacts;
  implementation must update purpose, retention, export and erasure records.

### Verification and limitations

- `make verify` passed with the repository toolchain: Composer/pnpm audits,
  Pint, PHPStan, 172 SQLite tests / 1,904 assertions, 299 PostgreSQL tests /
  4,362 assertions, locale validation with the five declared independent-review
  warnings, OpenAPI/generated-client/release/soft-launch contracts, ESLint,
  TypeScript and 272 package/web tests.
- Eight-file final-newline, balanced-fence and local-link validation passed;
  `git diff --check` also passed.
- No migration, configuration, OpenAPI/generated client, UI, password,
  username, recovery, session or authorization behavior changed.
- APP-0055 does not directly authorize runtime work. AUTH-PASSWORD-01 requires
  the exact separate implementation approval recorded in its section 14.

## AUTH-PASSWORD-01 implementation review — 11 August 2026

### Scope reviewed

- additive PostgreSQL/SQLite schema, model protection and rollback/cutover
  triggers;
- username/password policy, Argon2id hashing, throttling, recovery, session and
  global revocation services;
- mode-aware replacement of the formerly MFA-protected route set and retained
  tenant/permission/appointment checks;
- Sanctum web sign-in/Account Security and Android system-browser Passport PKCE;
- OpenAPI/generated clients, six locale catalogues, privacy inventory v3,
  threat model and operations/runbook changes; and
- synthetic rollback/cutover, browser, build, restore and Android evidence.

### Review result

| Check | Result |
|---|---|
| Approved specification implemented without broadening scope | Pass — APP-0056/D-0164 boundaries retained; no social/federated login, shared/default credential, tenant reset authority or new OAuth grant |
| Password confirmation mislabeled as MFA | Pass — runtime copy and new governance call it one-factor freshness and retain APP-0055's accepted privileged risk |
| Credentials or recovery secrets exposed | Pass in automated/repository review — request-memory only, Argon2id/keyed digests, no browser/ordinary Capacitor storage, generic failures and content-free evidence; production proxy/monitor inspection remains open |
| Existing protected command set weakened | Pass — route-policy coverage uses the mode-aware `auth.password-confirmed` alias and PostgreSQL evidence accepts only valid current-mode session evidence; authorization remains independent |
| Cross-tenant/global recovery boundary preserved | Pass — organization roles cannot reset a global credential; privileged recovery requires two distinct configured active platform actors and a fresh 24-hour delay |
| Migration and retirement fail closed | Pass — manifest completeness, approved exclusion owner, idempotent marker, global session/token revocation and OTP/TOTP secret invalidation are tested; post-cutover automatic rollback is prohibited |
| Production/public release claimed | Pass — explicitly not authorized |

### Verification evidence

- Composer validation/audit: passed; no advisories. pnpm high-severity audit:
  passed; no known vulnerabilities.
- Pint: passed. PHPStan level 8: passed with zero errors.
- Exact root `make verify`: passed end-to-end. SQLite: 172 tests / 1,904
  assertions. Isolated real PostgreSQL: 306 tests / 4,454 assertions. The final
  focused password plus Android Passport slice
  passed 14 tests / 605 assertions; privacy support passed 3 / 127.
- Locale validation: passed for six catalogues with the declared Kannada,
  Malayalam, Tamil, Telugu and Hindi pending-independent-review warnings.
- OpenAPI lint/policy/generated-client drift, release-gate contract and
  soft-launch environment contract: passed.
- ESLint, workspace TypeScript and workspace package tests: passed;
  localization passed 31 tests, design system 9 and web Vitest 235.
- Browser E2E: 16/16 Chromium journeys passed, including axe, keyboard and
  320-pixel/enlarged-text reflow plus password confirmation.
- Production build: passed, retaining the measured large-chunk warning.
- Non-production PostgreSQL backup/restore: passed, two-second RTO, evidence at
  `artifacts/ops/backup-restore-20260811T071852Z.json`.
- Production-path Android with repository JDK 21: Capacitor sync,
  `testDebugUnitTest` and `assembleDebug` passed (102 actionable tasks).
- `git diff --check` and the reserved-port reference check passed.

### Credential-setup usability follow-up

- The authenticated migration screen now states that it is one-time setup,
  not sign-in, and that successful credential establishment revokes the
  current session before the normal Sign in button appears.
- The browser enforces the same safe structural bounds as the server for a
  4–64-character canonical username and a 15–128-character password. The UI
  explains the allowed username characters and non-common-password rule,
  offers an in-memory show-password toggle, and gives safe corrective guidance
  after a rejected establishment without exposing server internals.
- The new component coverage passes for visible save action, exact input
  constraints, show/hide behavior, successful revocation callback, secret
  clearing and rejection guidance. ESLint and TypeScript pass; the focused
  PostgreSQL password flow passes 6 tests / 52 assertions. Locale governance
  revision `.6` validates all six hash-linked catalogues with the five declared
  pending-independent-review warnings. The live port-5174 module and CSS were
  checked after Vite hot reload.
- The routine authenticated shell now renders a compact session summary with
  **Username and password security** and Sign out actions. Password-management
  forms are conditionally mounted only after the user opens that section, so
  they no longer appear to be the post-login destination and make no background
  password-status/session request while closed. App-level coverage proves the
  operator workspace remains primary, open/close behavior is accessible, and
  Sign out returns to anonymous state. The complete web suite at this stage
  passed 239 tests.

### Ordinary-session security-panel follow-up

- Aggregate live evidence showed one active non-privileged session, zero
  privileged sessions, one rejected password-confirmation event and no
  successful confirmation. Read-only role inspection confirmed that the
  reported signed-in account has only the active learner role and
  `learning.participate`; no authority was changed or inferred.
- The panel now renders privileged confirmation and username change only when
  the current server-reported session is privileged. An ordinary learner keeps
  password change and signed-in-session management and no longer receives an
  impossible confirmation control.
- A failed privileged confirmation now displays safe, operation-specific
  re-entry guidance. The exact visible section label is **Username and password
  security**.
- Focused component coverage passes 6/6 tests for setup, learner visibility,
  privileged success and privileged failure. The complete web suite passes 49
  files / 242 tests; ESLint and web TypeScript pass. Locale governance revision
  `.7` passes for six catalogues with the five declared pending-independent-
  review warnings.
- The initial TypeScript run correctly found one session-revocation call that
  had not supplied the new safe-error category. That call was corrected and
  the exact type check then passed. No API, generated client, migration,
  configuration, credential, role or permission changed.

### Configured platform-actor and excluded-operator recovery audit

- The application privilege classifier previously considered only active
  organization-role permissions. This left explicitly configured platform
  identity-recovery/content-emergency actors on ordinary session lifetimes and
  unable to satisfy the password-confirmed middleware that protects their own
  platform APIs.
- `PlatformOrMembershipPrivilegeClassifier` now recognizes only exact IDs in
  the two existing platform allowlists, then delegates to the unchanged
  permission-derived membership classifier. It does not grant a membership,
  role, organization permission or curriculum authority.
- Pint passes. PHPStan passes on the affected classifier/provider/tests. A unit
  slice passes 1 test / 2 assertions, and the focused real-PostgreSQL
  `PermissionDerivedPrivilegeClassifierTest` passes 5 / 9. An attempted run of
  that integration-class filter under the unit/feature-only SQLite
  configuration correctly reported no tests; no SQLite pass is claimed for
  that command.
- Live aggregate inspection found 16 active users, 14 credential-unready users,
  one credential-unready privileged user, and exactly one such user with a
  verified recovery contact. Both credential-ready users have learner-only
  memberships and only `learning.participate`. No identity or opaque user ID is
  recorded here.
- The current privileged recovery implementation returns false for a target
  without an existing username and credential. Consequently the reviewed
  cutover exclusion is not recoverable through the runtime even though the
  cutover command accepted it. [DR-0027](DR-0027_EXCLUDED_PRIVILEGED_ACCOUNT_RECOVERY_REQUEST.md)
  records the required decision; no direct credential mutation, learner
  promotion, retired-factor restore or recovery case occurred.

An earlier PostgreSQL attempt overlapped with continuation sessions whose
runner handles had been discarded, causing schema-teardown deadlocks. No test
connection remained afterward; isolated reruns passed, followed by the exact
single `make verify` command passing all stages and all 306 PostgreSQL tests.

### Security, privacy and tenancy review

- A stolen password can satisfy privileged re-entry. Session binding, a
  five-minute freshness window, rate limits, global revocation and independent
  authorization reduce impact but do not create an independent factor.
- Username/password authority remains global while privacy export is
  membership-scoped. Inventory v3 exports only safe username/lifecycle fields,
  counts password/recovery secret records and retains restricted privileged
  recovery accountability.
- Ordinary recovery is unavailable to privileged/emergency actors. Production
  has no default recovery delivery, recovery actors are deny-by-default, and
  missing recovery key/blocklist evidence fails closed.

### Known limitations and retained gates

- The local non-production database is now in `password_only`. Two users
  independently established credentials; a complete reviewed manifest
  accounted for the other 14 active users as content-free local pre-production
  exclusions owned across the two configured credential-ready recovery actors.
  No credential was inspected, logged or manufactured.
- The dry run validated 16 active users as two ready plus 14 excluded. A
  readable 898,961-byte custom PostgreSQL dump was taken before execution
  (SHA-256
  `de3c59e76ffaa6132cf08fb4cd8bb728691f87070b05a5b8f4fafde4c4680374`).
  Execution completed and an idempotent replay reported already complete.
- Post-cutover aggregate checks found zero active authentication sessions,
  session payloads, mobile families, OAuth access tokens, MFA factors,
  challenges, recovery codes and OTP challenges. The live local mode endpoint
  returned `password_only`; a synthetic legacy OTP request returned HTTP 410.
  The forced global logout was expected. The first browser sign-in was a
  learner-only account. Both currently credential-ready accounts are
  learner-only; the actual Direct operator is a credential-unready exclusion.
  DR-0027 recovery, operator sign-in and fresh same-password confirmation now
  precede LPL-01 reconciliation.
- Production blocklist source/licence/freshness, recovery delivery, privileged
  actor proofing, proxy/log inspection and timing/load evidence remain open.
- Remote CI has not run on these working-tree changes. The tree also contains
  extensive earlier LPL/ADMIN work not isolated into a clean candidate commit.
- Physical Android device and negative-storage inspection, signed AAB, Google
  Play, independent reviews and production authorization remain open.
- Non-English authentication wording remains pending independent human review;
  approved-English fallback stays authoritative for production.
- LPL-01 human reconciliation, English/Kannada content review and publishable
  Level 1 blueprint remain incomplete; LPL-02+ and later ADMIN checkpoints are
  unaffected by this completion.

## DR-0027 governed excluded-account recovery — 11 August 2026

### Delivered behavior

- APP-0057 / D-0169 is recorded against the exact pre-resolution DR hash. The
  completed password-only cutover remains authoritative; no OTP/TOTP material,
  compatibility sign-in or direct credential mutation was restored.
- An additive migration records exact cutover exclusions append-only and links
  them to the global marker. The disabled reconciliation command accepts only
  an absolute mode-`0600` manifest with the configured digest, exact current
  ready/excluded inventory, two distinct credential-ready configured actors,
  approved reasons and owners.
- A listed active credential-unready target with a verified contact may enter
  the existing two-distinct-actor recovery lifecycle. The manifest owner must
  open the case; approvals require fresh same-password confirmation and the
  second approval starts a full 24-hour delay.
- Delayed completion creates one 256-bit, 15-minute, single-use establishment
  challenge. Only its keyed digest and safe lifecycle fields enter PostgreSQL.
  The raw value remains in a mode-`0600` local protected-delivery file and is
  never returned to browser JavaScript. The target chooses their own global
  username and policy-compliant password.
- Failed-attempt and expiry transitions now commit before returning the generic
  recovery failure; the controller keeps successful credential/challenge/
  revocation writes atomic. The account-security deep link also expands the
  deliberately collapsed **Username and password security** panel.

### Verification evidence

- The isolated SQLite migration passed apply, rollback and reapply after the
  rollback test exposed and corrected the unique-index drop order. Its
  temporary database was removed afterward.
- Focused SQLite/unit coverage passes 3 tests / 15 assertions. Focused real-
  PostgreSQL coverage passes 8 tests / 91 assertions, including an enforced
  two-connection row-lock race with exactly one establishment winner. The
  excluded-account slice proves wrong owner, duplicate configured actor,
  one-actor/pre-delay denial, wrong token accounting, username collision,
  replay, expiry, cross-organization authority preservation and secret-free
  evidence.
- PHPStan passes the affected PHP surface with zero errors. OpenAPI lint,
  policy and generated-client equivalence pass. Two new axe-clean browser
  journeys prove no browser storage/raw-token exposure and 320-pixel reflow.
- A clean repository `make verify` passes Pint, PHPStan, 175 SQLite tests / 1,919
  assertions, 309 PostgreSQL tests / 4,498 assertions, dependency audits,
  locale/OpenAPI/release contracts, ESLint, TypeScript and 282 JavaScript
  tests. `make e2e` passes 4 API reconciliation tests / 105 assertions and all
  18 Playwright journeys. `make build` passes with only the existing Vite
  chunk-size advisory. `make ai-eval` was not required because no AI/provider
  behavior changed.

### Local execution evidence and retained gates

- A fresh pre-migration custom PostgreSQL backup is 899,615 bytes with SHA-256
  `39970a5544d793be9b67f59c3ec7d813386ddbb297521cd3740b3730f74091dc`.
  Catalogue inspection succeeded. This supersedes neither the earlier backup
  nor the existing restore-drill evidence.
- The exact 2,545-byte manifest remained mode `0600` with SHA-256
  `ce29c70aee9c7f02fc52e3f749cc4bbb87999b68d2886ca6cfb434526ae5f3e1`.
  The dry run validated 14 exact exclusions; execution recorded 14 rows, two
  distinct owners, one manifest digest, one credential-unready active
  organization administrator, one content-free event and the PostgreSQL
  append-only trigger.
- Post-checks found zero excluded-establishment recovery cases and zero
  establishment challenges. No target username/password, role, membership or
  session authority was created or changed. The API and port-`5174`
  commissioning page are live; human actor 1/actor 2 approvals and the
  non-bypassable 24-hour delay remain outstanding.
- RDP Firefox subsequently showed only the document title with an empty root.
  The live HTML/API/modules were healthy and a fresh Chromium profile rendered
  the complete sign-in surface, isolating stale local browser shell state. The
  app no longer registers the offline service worker during Vite development,
  removes matching development registrations/public-shell caches, and advances
  the production cache revision. ESLint, web TypeScript and all 242 web tests
  pass; a live port-`5174` fresh-browser check reports visible content, zero
  runtime errors, zero registrations and zero matching caches. Database and
  recovery evidence were unchanged.
- Remote CI on the working tree, independent security/privacy/tenancy/
  accessibility/language reviews, production blocklist/recovery delivery,
  physical Android evidence, production deployment and Google Play authority
  remain open. LPL-01 and every later LPL/ADMIN checkpoint remain separate.

### Human recovery-approval evidence

- The designated manifest owner opened the exact excluded-operator case after
  fresh password confirmation. The second configured actor signed in
  independently, freshly confirmed their own password and appended the second
  approval. Content-free aggregate verification reports one case, two distinct
  approvers, status `waiting_delay`, and zero excluded-establishment
  challenges.
- The authoritative `not_before_at` is 12 August 2026 10:44:26 UTC, which is
  16:14:26 IST. No early-completion attempt or time override was used. Delayed
  completion and the target's 15-minute credential-establishment ceremony
  remain outstanding.

## Android release-signing fail-closed verification — 11 August 2026

Scope: PR-014 AN-09 repository signing mechanism only. No production upload
key, Play account or release authorization was used.

| Check | Result | Evidence |
|---|---|---|
| Ordinary Gradle configuration without signing input | Pass | JDK 21 `./gradlew --no-daemon help` |
| Release task without signing input | Expected rejection | `bundleRelease` exited 1 with the repository missing-signing message before task execution |
| Partial signing input | Expected rejection | `help` exited 1 with the incomplete four-variable configuration message |
| Inside-repository signing file | Expected rejection | `help` exited 1 with the outside-repository keystore requirement |
| Signed AAB mechanism | Pass with disposable test identity | `testDebugUnitTest bundleRelease`; 172 tasks; 4,807,426 bytes; SHA-256 `096097a1871e24d5f46e0410ace3fb5db18a379bccf3b646d20f97fb2c`; `jarsigner -verify` reported verified |
| Debug regression | Pass | `testDebugUnitTest assembleDebug`; 102 tasks; 6,344,957-byte APK; SHA-256 `721f21e0e92c22c8254f6b75be257fa384c4cb77841be6e75d4433fd77e14b20` |
| Static release contract | Pass | `pnpm run release:check` |
| Production upload identity / signed candidate | Open | Product owner must establish protected upload-key custody and authorize the production-bound artifact |
| Physical device / Play | Open | No ADB device attached; DM-01/DM-02, Play App Signing and track upload not claimed |

Security/privacy note: passwords are not accepted through command-line Gradle
properties or repository files. The test key was disposable and outside the
repository; its artifact is ignored build output and is not a candidate for
distribution. No learner data, provider credential or authentication secret
was read or packaged for this verification.

## Release-candidate clean-run verification — 11 August 2026

| Check | Result | Evidence |
|---|---|---|
| Candidate publication | Pass | Draft PR [#3](https://github.com/srbhandary1/GESC/pull/3) on `codex/confidence-conversation` |
| Android SDK bootstrap | Pass | Exact-commit-pinned `android-actions/setup-android`; remote Android job passed in 3m47s |
| Production session defaults | Pass | Isolated subprocess test is independent of ignored/local `.env` state |
| PostgreSQL HTTPS session policy | Pass | Host-conflict reproduction: 25 tests / 800 assertions; full suite: 309 / 4,496 |
| Complete local verification | Pass | `make verify`: Pint, PHPStan, 175 SQLite tests / 1,919 assertions, PostgreSQL, audits, contracts, ESLint, TypeScript and 282 JavaScript tests |
| Remote Verify | Pass | GitHub Actions run [31487637244](https://github.com/srbhandary1/GESC/actions/runs/31487637244), 8m41s |
| Remote Operations | Pass | Same run, 1m29s |
| Remote Release Gate | Pass | Same run; all required upstream jobs succeeded |

The corrections affect CI/test determinism only and do not weaken the
production cookie policy. No credential, personal data, learner content or
provider secret was logged or committed. Remote CI is closed for this
candidate; independent review, production-environment validation, physical
devices, production signing identity, Play authorization and release approval
remain explicit gates.

## Static-IP remote-entry verification — 11 August 2026

| Check | Result | Evidence |
|---|---|---|
| Host listener | Pass | Caddy bound on all interfaces at TCP 8080; API remained loopback-only at TCP 8000 |
| Router/public reachability | Pass | Independent `portchecker.io` TCP check returned `True` for `117.254.108.143:8080` |
| Safe transport transition | Pass | Loopback and `192.168.1.3:8080` returned HTTP 307 to the current HTTPS origin with path/query preserved |
| HTTPS application readiness | Pass | `/api/v1/health/ready` returned `ready` after managed-service restart |
| Operational readiness | Pass with one declared warning | 17 pass / 1 disabled-live-AI warning / 0 fail |
| Static contract | Pass | HTTPS configuration contains only the redirect; invalid/non-HTTPS origin retains diagnostic direct-serving fallback |
| Reconciliation access copy | Pass | Controlled soft-launch and fresh-password-confirmed wording matches the existing API middleware; stale localhost/TOTP labels are rejected by the contract test |
| Repository verification | Pass | `make verify`: 175 SQLite / 1,919 assertions; 309 PostgreSQL / 4,496 assertions; 282 JavaScript tests; audits, analysis and contracts passed |
| Production build | Pass with advisory | `make build`; existing Vite chunk-size advisory only |

Security/privacy result: the static public IP is an entry point, not an
authenticated cleartext origin. Passwords and cookies remain on HTTPS, API and
data services retain their existing bindings, and no secret, transcript,
learner data or provider payload was inspected or logged. Router reachability
does not constitute production authorization or independent review.

## Managed API ownership regression — 11 August 2026

| Check | Result | Evidence |
|---|---|---|
| Failure isolation | Confirmed | Managed API PID dead; verified older Laravel parent/child owned `127.0.0.1:8000`; restart log contained `Address in use` |
| Root cause | Confirmed | Readiness reached the stale process, whose inherited stateful origin predated the active HTTPS tunnel |
| Lifecycle contract | Corrected | Runner invokes the repository-pinned PHP server/router directly and records the actual listener PID; contract rejects `artisan serve` |
| Live process ownership | Pass | Corrected API listener PID is running inside the managed systemd unit; every soft-launch process reports running |
| Stateful/CSRF protocol | Pass | Current HTTPS origin returned password-only mode, issued XSRF plus `__Host-` session cookies and delivered a synthetic invalid request to the password controller as generic HTTP 422 |
| Complete regression | Pass | `make verify`: 175 SQLite / 1,919 assertions; 309 PostgreSQL / 4,497 assertions; 282 JavaScript tests; audits, analysis and contracts passed |
| Sensitive-data handling | Pass | No credential, username, request body, session value or learner content inspected |

This is an operational process-ownership correction. It changes no password,
session lifetime, tenant authority, API contract, schema or production-release
authority.

## Closed-group Ubuntu Web and Android password test pack — 11 August 2026

| Check | Result | Evidence |
|---|---|---|
| Supervised Ubuntu service | Pass | Enabled foreground user unit with lingering; monitors API, worker, scheduler, loopback Caddy, port-8080 redirect and tunnel and restarts on child/repeated-health failure |
| External HTTPS readiness | Pass | Current Quick-Tunnel `/api/v1/health/ready` returned `ready` |
| Locked authentication mode | Pass | Current external `/api/v1/auth/password/mode` returned exactly `password_only` |
| Invited learner credential setup | Pass | Atomic invitation acceptance plus learner-created global username/password; focused API 9 tests / 98 assertions; generic rollback failure covered |
| Public API/client contract | Pass | OpenAPI 0.27.0 and generated TypeScript clients include password invitation enrollment |
| Browser credential handling | Pass | Invited account E2E clears form secrets and proves local/session storage empty; no OTP/TOTP/social control introduced |
| Critical Web journey | Pass | `make e2e`: 4 API tests / 105 assertions and 19 Playwright journeys, including axe, 320-pixel/400%-text reflow and invited setup |
| Production-path APK build | Pass | `make android-closed-beta`; Gradle `testDebugUnitTest assembleDebug`, 102 tasks |
| APK identity and transport | Pass | `com.globalenglish.app`, version 1.0 (1), target SDK 36; exact HTTPS origin present; static HTTP origin and demo package absent |
| APK integrity and signing | Pass for closed sideload | 8,461,979 bytes; SHA-256 `bc67ee2dd0520a3a26abd98d62320b996c5ca74f0c89e9e57480a8ddb47040ed`; v2 Android debug signature verified |
| Controlled distribution | Pass | Mode-0640 artifact under `~/ge-soft-launch/artifacts/closed-group`; no unauthenticated public download route |
| Complete repository verification | Pass | `make verify`: 175 SQLite tests / 1,919 assertions; 311 PostgreSQL tests / 4,512 assertions; 283 JavaScript tests; audits, Pint, PHPStan, locale/OpenAPI/release/runtime contracts, ESLint and TypeScript passed |
| DR-0027 excluded administrator | Correctly retained | `waiting_delay` until 12 August 2026 10:44:26 UTC; no bypass or credential establishment; affects that administrator and invitation issuance only |
| Production / Google Play | Not authorized | Debug sideload artifact only; no production signing, Play upload, public release or VPS claim |

Security, privacy and tenancy result: the new tester-setup operation consumes
one exact tenant-scoped invitation inside the same transaction that creates the
credential and membership, returns only generic failures, and removes raw
invitation/password inputs before request logging. Testers choose their own
passwords. No credentials are written to browser storage or ordinary Android
preferences. No learner audio, transcript, provider secret or real tester
credential was inspected or logged.

Retained limitations: a Quick Tunnel can rotate and then requires APK rebuild;
new invitation-only accounts do not yet have a verified recovery contact;
LPL-01 reviewed publication is incomplete; the live AI kill switch remains
disabled; translation reviews, independent security/privacy/tenancy/
accessibility reviews, physical-device evidence, production signing, VPS
migration and Play/public authorization remain open.

## Closed-group hostname rotation and APK rebuild — 12 August 2026

| Check | Result | Evidence |
|---|---|---|
| Current supervised runtime | Pass | `global-english-soft-launch.service` enabled/active; host-network `make soft-launch-ready` returned 17 pass / 1 disabled-live-AI warning / 0 fail |
| External HTTPS protocol | Pass | Current tunnel root HTTP 200 with valid TLS; readiness returned `ready`; same-origin password-mode request returned exactly `password_only` |
| Active Android PKCE client | Pass | Non-revoked `Android closed-beta` client targets exactly `com.globalenglish.app:/oauth/callback` |
| Production-path rebuild | Pass | Repository-documented Temurin JDK 21; `make android-closed-beta`; Gradle `testDebugUnitTest assembleDebug`, 102 tasks |
| APK identity and transport | Pass | `com.globalenglish.app`, version 1.0 (1), min SDK 24, target SDK 36; current HTTPS origin and exact public client present; prior tunnel origin, static HTTP origin and demo package absent |
| APK integrity and signing | Pass for controlled sideload | 8,461,968 bytes; SHA-256 `eab6128229fa2a9dc03bc1e47ac45e3b3df785e37d2403d4986ffb0071427e6a`; APK Signature Scheme v2 verified with the Android debug certificate |
| Controlled staging | Pass | Byte-identical mode-0640 artifact at `~/ge-soft-launch/artifacts/closed-group/global-spoken-english-closed-group-20260812.apk`; the prior artifact was preserved |
| Attached device evidence | Open | `adb devices -l` returned no attached device; physical DM-01/DM-02, authenticated Web/Android tester journeys and storage-negative inspection are not claimed |
| Live conversation model | Correctly blocked | Read-only Azure capability query still returned no `ContentLogging` capability; the live kill switch remains false and no model request or spend occurred |

The first build attempt was stopped by sandbox write restrictions on the
existing Gradle cache. The authorized retry exposed host JDK 17 rather than the
required JDK 21; rerunning with the repository-documented Temurin 21 path
passed without changing project code. No credential, learner content, provider
secret, authentication token, audio or transcript was inspected or logged.

This closes only the rotated-hostname APK-build gap. Closed-group acceptance
still requires intended testers and physical devices; production additionally
retains the provider privacy gate, independent reviews, reviewed learning
content, production infrastructure/signing and explicit release approval.

## Independent L1 gate and production-closure controls — 12 August 2026

### Delivered behavior

- Added separate deterministic export and strict human-review Make targets.
  The export contains the actual English reply/correction and L1 explanation,
  not only case identifiers and dispositions.
- Canonical row hashes bind reviewer acceptance to the exact prompt/context,
  reply, correction, explanation, locale and safety result. The strict gate
  recomputes the hash from the submitted row and compares it with the current
  generated result, so both packet tampering and stale output fail closed.
- Strict mode requires all 15 cases, `accept`, locale-specific reviewer role,
  reviewer/evidence identity, `independent_review: true` and an ISO-8601
  instant. The evaluation report digest includes the review requirement,
  reviewer/evidence references and payload hashes.
- Pending/rejected/incomplete review emits only
  `ai_evaluation_review_rejected`; no raw generated content or framework stack
  is emitted by the command.
- Added the independent-review runbook, updated F0 wording for the accepted
  password-only posture, opened the bounded Speech decision stop and recorded
  a single ordered production closure matrix. No external approval was
  manufactured.

### Verification

| Check | Result |
|---|---|
| Formatter | Pass; Pint on runner, console route and integration tests |
| Focused review suite | Pass; 9 PostgreSQL tests / 54 assertions, including complete-review acceptance, missing-package rejection and changed-reviewed-output rejection |
| Focused PHPStan | Pass; zero errors |
| Final `make verify` | Pass; Composer/pnpm audits, Pint, PHPStan, 175 SQLite tests / 1,919 assertions, 314 PostgreSQL tests / 4,522 assertions, locale/OpenAPI/generated-client/release/soft-launch contracts, ESLint/TypeScript and 283 JavaScript tests |
| `make e2e` | Pass; 4 API tests / 105 assertions and all 19 Chromium journeys |
| `make ai-eval` | Pass; suite hash `5bf9a508d1aeaf3a329212e60cf957cc28404fabcf0d34f4f65ebb6e99223270`, report digest `800ff3dd6ab2d24f2df29dcefe62c5cc756b15c442f35d750bbd96011a77a183`, 15/15, zero estimated cost; separately gated Speech benchmark skipped without a run ID |
| Review export | Pass; 15 actual-output rows; protected packet 17,364 bytes, SHA-256 `47d5f2f10fe0d0d6d8cdcb8ea57cf39e6dadf9b67c031aa2ea072cc61341a05d` |
| Strict pending-package gate | Pass by expected fail-closed result; exit 2 with safe code `ai_evaluation_review_rejected` |
| Production build | Pass; Laravel optimization/cleanup and 117-module Vite build; retained measured 1,069.17 kB chunk-size advisory |
| Diff hygiene | Pass; `git diff --check` |

The review packet is byte-identically staged mode 0640 at
`~/ge-soft-launch/artifacts/review/friend-l1-review-20260812-47d5f2f1.json`.
It contains synthetic evaluation text, not learner content. It is deliberately
still `pending`; passing the strict gate is not claimed.

### Security, privacy, tenancy and retained gates

No schema, migration, OpenAPI, provider request, model/Speech spend,
authoritative learning record, tenant resource or learner content changed. AI
remains advisory. Reviewer evidence stays outside Git and only safe references
enter the report digest. Ordinary learner Speech and the Azure model kill
switch remain disabled.

The current-host APK gap and review mechanism are closed. Physical DM-01/
DM-02 intended-tester evidence, actual independent L1/specialist findings,
Microsoft modified-abuse-monitoring approval, DR-0028 authorization and
implementation, reviewed LPL-01 content, stable production operations,
production signing/Play evidence and exact-commit F0/release acceptance remain
open; production is not authorized.

## Android first-party PKCE journey correction — 12 August 2026

### Root cause and delivered behavior

- Physical testing on an attached Realme 8 (Android 13) proved that the native
  app stayed anonymous while Chrome displayed the Web shell. Sanitized Chrome
  metadata showed `/oauth/authorize` in the address bar while the document was
  the cached `Home` shell. No credential, cookie, token or learner content was
  read.
- The soft-launch origin had three interacting defects: OAuth/mobile-auth
  paths were not part of the original reverse-proxy contract; the public Host
  and HTTPS scheme were not preserved through the loopback tunnel origin; and
  an older PWA worker could substitute the public shell for an authentication
  navigation. All authentication prefixes are now network-only, the worker is
  version 4 with forced update checks, and the Caddy listener accepts any
  public Host while binding only to loopback.
- Laravel now trusts only the configured proxy addresses and forces generated
  HTTPS URLs whenever the configured application URL is HTTPS. Live external
  health returns the Laravel JSON contract, a unique `/oauth/*` probe returns
  Laravel 404, and a fresh authorization request redirects to the HTTPS mobile
  sign-in page with `no-store` and secure cookies.
- Passport auto-approval is fail-closed to the exact reviewed public Android
  client shape and the sole `mobile:api` scope. Username/password remains in
  the system browser; state/S256 PKCE, exact callback validation, rotating
  refresh families and native secure storage remain unchanged. The separate
  **Continue** consent page is removed, and Android exposes one top-right
  **Sign in** action.

### Verification and artifact

| Check | Result |
|---|---|
| Focused API/Web contracts | Pass; 9 SQLite tests / 38 assertions, 8 focused PostgreSQL tests / 503 assertions, 17 Android/Web tests |
| Complete repository verification | Pass; `make verify`: 177 SQLite / 1,928 assertions, 314 PostgreSQL / 4,473 assertions, 285 JavaScript tests; audits, Pint, PHPStan, localization, OpenAPI, release/soft-launch, lint and type checks |
| Critical browser journey | Pass; `make e2e`: 4 API tests / 105 assertions and 19/19 Chromium journeys |
| Android packaging | Pass; JDK 21 `make android-closed-beta`, 103 Gradle tasks |
| Final APK identity/signature | Pass; `com.globalenglish.app` 1.0 (1), min SDK 24, target SDK 36, APK Signature Scheme v2 |
| Final APK transport/integrity | Pass; only `https://award-bite-monica-seafood.trycloudflare.com`; 8,461,998 bytes; SHA-256 `d3e55fe4790cb4e7fd7cebf428fb6818f3c419df849f877039e550669366f448` |
| Controlled staging/install | Pass; byte-identical mode-0640 revision 4 artifact; `adb install -r` succeeded; package update recorded on the Realme 8 |
| Human password/automatic return | Open; final result requested from the device operator after revision 4 cold launch |

### Security, privacy, tenancy and retained gates

No schema, migration, API payload, provider call, AI spend, tenant authority,
credential or authoritative learning record changed. Auto-approval applies
only after browser authentication and only to the exact first-party public
client/scope/callback contract; all other client shapes retain ordinary
consent or fail policy validation. The five non-English UI catalogues remain
explicit implementation previews pending independent review.

This correction does not make the Quick Tunnel production infrastructure.
DM-01/DM-02 evidence, stable product hosting/domain, production signing and
Play controls, independent security/privacy/tenancy/accessibility review,
independent L1 quality review, reviewed LPL-01 content and explicit release
approval remain open. Production is not authorized.

## Stable static-IP HTTPS edge implementation — 12 August 2026

| Check | Result |
|---|---|
| Exact public origin | Pass in repository contract: `https://ai.globalspokenenglish.com`; scheme/path/port and APP_URL mismatches reject |
| Unprivileged direct TLS design | Pass: Caddy `http_port 8080`, `https_port 8443`; documented WAN 80/443 mapping |
| Origin isolation | Pass: stable edge proxies only to `127.0.0.1:9080`; API and recovery internals remain loopback-bound |
| Session/authentication alignment | Pass: persisted exact APP_URL, Secure cookie and Sanctum host; Android remains Passport PKCE and requires a stable-origin rebuild after activation |
| Transport hardening | Pass in generated configuration: public automatic TLS, exact-host redirect, HSTS, nosniff, frame denial and strict-origin referrer policy; no internal certificate or Quick-Tunnel hostname |
| Runtime behavior | Pass in contract: stable mode refuses Quick Tunnel and systemd supervision no longer expects it |
| Shell/config validation | Pass: Bash syntax, soft-launch environment contract and installed-Caddy config validation |
| Complete repository verification | Pass: 177 SQLite tests / 1,928 assertions; 314 PostgreSQL tests / 4,472 assertions; 285 JavaScript tests; audits, Pint, PHPStan, locale/OpenAPI/generated-client/release/runtime checks, ESLint and TypeScript |
| Production build | Pass: Laravel optimize/cleanup and 117-module Vite build; retained measured 1,068.90 kB chunk-size advisory |
| Public DNS | Incomplete; deferred to 13 August 2026: Cloudflare authoritative DNS is verified, but authorized dashboard credentials are unavailable and the `ai` A record is absent |
| Router/certificate/external health | Pending until WAN 80/443 map to LAN 8080/8443 and Caddy obtains the public certificate |
| Live runtime/APK | Deliberately unchanged; temporary closed-test origin remains available and the existing APK is not misrepresented as stable |

No database migration, API/OpenAPI shape, credential, permission, tenant
resource, learner evidence, provider call or content changed. The design keeps
low ports at the router boundary, does not run the application as root and
does not expose localhost-only recovery through SSH or the public edge.

This closes the repository implementation portion of stable hosting, not its
external evidence. DNS, router reachability, public TLS/health/password-mode,
stable-origin Android packaging, production signing/Play, independent reviews,
reviewed content and explicit production authorization remain open.

The external activation plan is recorded as incomplete and deferred to 13
August 2026. Cloudflare credentials must remain private and may not be pasted
into chat, shell history, repository files or evidence.

## Levels 1–17 blanket-activation assessment — 12 August 2026

| Check | Result |
|---|---|
| Governed level records | Blocked: zero active `curriculum_levels` rows |
| Numbered source coverage | Partial: current smoke imports include Levels 1, 2, 3, 6–11, 13, 14 and 17; Levels 4, 5, 12, 15 and 16 have no current imported video module |
| Publications | Not production evidence: two duplicate active staging-smoke imports, each with 13 modules including one scaffold and one generic mission |
| Complete learning loops | Blocked: transcripts, reviewed objectives, quizzes/rationales, Talk goals, Apply rubrics and delayed review are absent |
| AI scenarios | Blocked for live claim: four `development_only` scenarios; four `synthetic_unreviewed` versions; zero persisted turns |
| Live model and learner voice | Disabled behind existing privacy/provider/consent gates |
| Rights | Bounded smoke rights only; derivative use is disabled for imported video, and APP-0047 covers four exact Level 1 MP3s for non-production processing only |
| Blanket production waiver | Rejected as non-executable; it cannot create content or replace rights, consent, provider, tenant publication or enrollment authority |

[DR-0029](DR-0029_LEVELS_1_17_LIVE_ACTIVATION_REQUEST.md) Option A is accepted
under APP-0058 / D-0184. The review result makes LPL-01 the only active content
checkpoint and preserves the Level 1 → Levels 2–3 → remaining source-complete
wave order. No database, provider, publication, enrollment, permission,
rights, content or learner evidence mutation was performed by approval.
## Unified production login and recovery decision review — 13 August 2026

- Read-only recovery evidence confirmed one unexpired DR-0027 case with two
  approvals and an elapsed delay, zero establishment challenges, and an
  unchanged credential-unready target with exactly one active organization-
  administrator membership.
- A fresh pre-completion PostgreSQL backup was created at mode `0600`, measured
  915,762 bytes, had SHA-256
  `e8db7b98186b1f5f19c4451e9825fcfd90fc5dd21dd5982e793434acfe8d8fec`,
  and passed `pg_restore --list` catalogue validation.
- The recovery actor's browser-only password confirmation succeeded. The
  following status request failed closed because the managed `soft-launch`
  environment is not accepted by the local/testing-only APP-0057 controller.
  No case completion, token delivery, credential, role or permission mutation
  occurred.
- The product owner then rejected the exceptional recovery complexity for
  production. DR-0030 records one shared username/password login, role-based
  authorization, enrollment-based learner tiers and verified-contact recovery
  for every role. Baseline hashing, secure transport, token expiry/replay,
  revocation, tenancy and audit controls remain explicit.
- This is a decision checkpoint only. No runtime, schema, API, OpenAPI/client,
  configuration or provider behavior changed. Exact Option A/B acceptance is
  required before implementation because the choice supersedes accepted
  privileged-recovery authority in ADR-0021 and DR-0027.
## AUTH-SIMPLE-01 unified login and recovery — 13 August 2026

### Delivered behavior

- Recorded the exact DR-0030 Option A approval under APP-0059, D-0186 and
  accepted ADR-0022, then amended the master plan to one platform-global
  username/password sign-in and one verified-contact recovery model for every
  role.
- Replaced active privileged/two-actor recovery with a 256-bit, 15-minute,
  single-use role-neutral reset and reviewed migration-exclusion setup. The
  setup actor cannot see the token or choose the username/password.
- Added only `platform.account_recovery.manage` for global lost-contact
  support. It is never derived from an organization role, requires a current
  active session plus five-minute same-password confirmation, denies self-
  targeting, records keyed external-evidence digests and revokes all target
  Web/Android credentials.
- Added append-only management and former-contact-notification evidence. The
  transition keeps old cases/approvals, marks incomplete cases superseded and
  removes their routes/services/configuration from active runtime.
- Added the public setup/recovery fragment pages with immediate history
  erasure, memory-only tokens, CSRF initialization, service-worker exclusion
  and no-store/referrer/robot headers. Android remains system-browser Passport
  Authorization Code with S256 PKCE and reviewed native secure refresh-token
  storage.
- Restyled the anonymous Web gate using the public website's split-layout
  visual pattern while intentionally retaining username/password and excluding
  phone-number sign-in, Google sign-in, remember-me credential storage and
  third-party scripts.

### Verification

| Check | Result |
|---|---|
| Focused SQLite auth/setup/readiness | Pass — 11 tests / 109 assertions |
| Focused PostgreSQL auth and live-case migration/rollback | Pass — 10 tests / 101 assertions |
| PostgreSQL concurrent setup | Pass — one test / 8 assertions; exactly one winner |
| Formatter and static analysis | Pass — Pint and PHPStan, zero errors |
| Public contract | Pass — OpenAPI 0.28.1 valid; generated TypeScript clients match |
| Complete `make verify` | Pass — 177 SQLite tests / 1,934 assertions; 315 PostgreSQL tests / 4,477 assertions; 285 JavaScript tests; dependency audits, locales, release/runtime contracts, lint and typecheck passed |
| Critical journey `make e2e` | Pass — 4 API tests / 105 assertions and 20/20 Chromium journeys |
| Production build | Pass — Laravel optimize/cleanup and 117-module Vite build; retained 1,069.30 kB chunk-size advisory |
| Backup/restore | Pass — `global_english_test`; migrations, application schema and deletion/tombstone state match; RTO 2 seconds |
| Diff hygiene | Pass — `git diff --check` |

### Security, privacy, tenancy and retained gates

Raw passwords, contacts and setup/reset tokens are absent from API responses,
audit, outbox and browser storage. Replacement contacts use the existing keyed
identity digest plus a bounded masked hint; external proof enters the database
only as a keyed digest. Recovery never creates or changes membership, role,
permission, appointment, enrollment, cohort, level, publication or learning
records, and cross-organization authority snapshots remain identical.

The accepted one-factor administrator-takeover risk remains explicit. The
production readiness probe fails closed until both a reviewed recovery delivery
adapter and former-contact notification adapter are configured. Provider/DPA/
subprocessor, sender/secret custody, delivery observability, independent
security/privacy/tenancy/accessibility review, stable-host activation, LPL-01
resumption and public/Play release remain open.
Manager actions remain API/runbook operations rather than a React support
console. No provider request or production mutation was performed.

## Local AUTH-SIMPLE administrator transition — 13 August 2026

### Evidence and behavior

- Reconfirmed the live recovery inventory using content-free counts only: 16
  active users, two credential-ready accounts, 14 exclusions, one waiting case,
  two approvals, an active credential-unready target and one verified target
  contact.
- Resolved the manager deterministically to that exact exclusion's existing
  manifest owner, who was also the original case requester and an active,
  credential-ready reviewed actor. No identifier was selected by convenience
  and no organization role gained platform recovery authority.
- Created and gzip-verified a 138,142-byte pre-migration snapshot with SHA-256
  `357714361f38ed162d63bbed53513946fba47af3cb7218a80b54b91d33791b31`.
  The exact file was restricted from inherited `0664` to `0600`; the runtime
  backup function now uses `umask 077` before creating future snapshots.
- Applied migration batch 8 successfully. The one incomplete legacy case is
  superseded with the approved reason and its two approval rows remain. The
  new management and notification tables exist and remain empty.
- Opened a temporary loopback-only manager aid bound to the exact target. It
  returned no token and required normal Web authentication plus five-minute
  current-password confirmation. The human authorization was not completed
  during ten minutes, so no management event or setup challenge was created.
  The aid was then deleted and the managed API, worker, scheduler, Caddy edges
  and tunnel restored healthy.
- A subsequent human attempt proved normal password sign-in but failed the
  current-password confirmation. Recovery stayed fail closed at zero events,
  challenges and target credentials. The guard's expected fresh-confirmation
  exception was incorrectly serialized with HTTP 500; it now returns the safe
  HTTP 403 `fresh-password-confirmation-required` problem. The account-setup
  feature test first proves denial and absent evidence, then confirms that the
  same session can complete the one-manager protected-local lifecycle after a
  valid current-password confirmation.
- The exact manager subsequently completed a valid current-password
  confirmation and authorized one setup. Exactly one management event and one
  accepted challenge were created. The target alone chose the username and
  password; the challenge is consumed, no setup remains pending, the delivery
  file is removed and the first normal sign-in succeeded. The operator shell
  loaded through existing authorization without any role, membership,
  permission, appointment, cohort or enrollment mutation.
- The protected-local metadata GET initially returned 401 because the page's
  `no-referrer` policy suppressed Sanctum's same-origin stateful signal. The
  corrected `same-origin` policy cannot send a referrer cross-origin, and any
  public token fragment is erased before the request. A three-journey browser
  regression proves protected metadata loads and both setup/recovery fragments
  remain absent from browser persistence.
- Content-free before/after authority snapshots have the identical SHA-256
  `d7ed3f404a5a986f7199882a38454563af6999eee69a0fd3534fe2fabd557cf6`.
  Reconciliation is disabled, the verified-empty protected delivery directory
  is removed and the managed service is healthy. A later managed-HTTPS sign-in
  and current-password confirmation both succeeded; the active session is
  bound to the target's current credential version.

### Verification and retained gates

| Check | Result |
|---|---|
| Pre-migration backup | Pass — gzip readable, 138,142 bytes, exact SHA-256, final mode `0600` |
| Migration | Pass — `000200` batch 8 |
| Historical evidence | Pass — one superseded case, two preserved approvals |
| New recovery evidence | Pass — exactly one management event and one consumed accepted setup challenge; zero pending |
| Target credential/sign-in | Pass — credential ready; first normal password sign-in succeeded; no value inspected |
| Target fresh confirmation | Pass — managed-HTTPS confirmation succeeded and the active session is current-version-bound |
| Protected delivery cleanup | Pass — token never retrieved; delivery removed and empty directory deleted |
| Tenant authority | Pass — exact pre/post SHA-256 match; no role, membership, permission, cohort or enrollment change |
| Manager authority | Pass — exactly one manifest-designated active credential-ready user; retired two-actor configuration removed |
| Managed runtime restoration | Pass — API, worker, scheduler, both Caddy processes and tunnel running; loopback liveness `ok` |
| Backup creation regression | Pass — shell syntax and soft-launch contract cover `umask 077` |
| Fresh-confirmation error contract | Pass — HTTP 403 with safe corrective problem; zero evidence before confirmation; SQLite and PostgreSQL feature regression |
| Protected-local stateful GET | Pass — same-origin-only referrer, no cross-origin referrer, fragment erased first; focused browser 3/3 |
| Complete `make verify` | Pass — 177 SQLite tests / 1,934 assertions; 315 PostgreSQL tests / 4,477 assertions; 285 JavaScript tests and all static/contract gates |
| Critical journey `make e2e` | Pass — 4 API tests / 105 assertions and 20/20 Chromium journeys |
| Production build | Pass — Laravel optimize/cleanup and 117-module Vite build; retained 1,069.30 kB chunk-size advisory |
| Diff hygiene | Pass — `git diff --check` |

Credential issuance, normal sign-in and fresh confirmation are complete. The
manager's current password and the target's username/password remained human-controlled secrets;
no session or token was synthesized, the raw protected-local token was never
read and no direct credential/database mutation was used. Production delivery/
notifier providers, stable hosting and independent review remain separate
gates.

## LPL-01 human transcript reconciliation — 14 August 2026

### Evidence and behavior

- The authorized Direct operator completed the private HTTPS reconciliation
  journey for exact campaign `01KZP5129TNAQPE542XM15604C`. The page verified
  each downloaded audio object against its fixed SHA-256 in browser memory;
  the human listened to each complete part, checked every provider segment and
  selected `confirmed` for Introduction, Practice, Conversation and
  Interaction.
- Four append-only reconciliation records atomically created four English
  metadata revisions. Their content hashes, in source order, are
  `2e8335f5998dace0a619acbd24945beafbb3b9a2e427bd71dd01f473b3b17e0b`,
  `a185249064c4e153237e1a54f8375fe1e7e4fac2ce45525684a929ea174a6298`,
  `830bba76c97bb19d3b132da8bd7a021cb5160be146592cfffcaaaa810bc48635`
  and
  `5db31dd35f5d9fe1f0d02fc116e2793f718bf6b4cff1dde00c25c93ec8ba2d37`.
  No transcript text, segment text, audio, candidate key or credential entered
  this review evidence.
- Candidate deletion completed on the first attempt for all four assets. The
  last receipt completed at 14 August 2026 05:35:20 UTC; the campaign then
  moved from `provider_complete` to `completed`. Content-free aggregate checks
  show four reconciliations, four completed deletion receipts and zero
  retry/dead-letter backlog.
- The campaign's fixed usage remains four successful provider attempts, zero
  failures/retries, 18,760,190 uploaded bytes, 1,172,513 billable milliseconds
  and 11,189,394 INR microunits (₹11.189394), inside APP-0052's limits.
- The journey exposed a usability defect in the accepted policy: successful
  password sign-in creates no fresh-password proof, while candidate read and
  reconciliation both require a separate proof that expires after five
  minutes. The exact audio set lasts 19 minutes 32.513 seconds, so the separate
  Account Security flow necessarily caused repeated same-factor prompts.
  [DR-0031](DR-0031_LPL01_RECONCILIATION_AUTHENTICATION_SIMPLIFICATION_REQUEST.md)
  records three bounded options and recommends ordinary signed-in,
  permissioned access for these two exact routes. It changes no current
  authentication behavior.

### Verification and retained gates

| Check | Result |
|---|---|
| Reconciliation aggregate | Pass — campaign `completed`; four reconciliations; all outcomes `confirmed` |
| Metadata lineage | Pass — four non-null metadata revision IDs and four exact content hashes |
| Candidate cleanup | Pass — four completed receipts on first attempt; zero retry/dead-letter backlog |
| Provider/cost boundary | Pass — historical 4/4 success, zero retry/failure and exact usage/cost remain within caps |
| Runtime health | Pass — managed API, worker, scheduler, Caddy edges and current HTTPS tunnel healthy during reconciliation |
| Sensitive-content handling | Pass — only content-free identifiers, hashes, counts, states and timestamps were inspected/recorded |
| Repository behavior | Unchanged — no API, schema, session, credential, provider or learner runtime edit |

Human metadata reconciliation is complete, but it is not English academic or
Kannada review. The typed legacy-source snapshot, deterministic ordered
blueprint, English academic approval, Kannada creation/review and publication
readiness remain open; LPL-01 and LPL-02 exit gates are not claimed. DR-0031
required exact option acceptance and a successor ADR at this evidence point;
the subsequent section records its APP-0060 / ADR-0023 resolution.

## One-login role-group operator authorization — 14 August 2026

### Scope reviewed

- APP-0060 contextual approval, resolved DR-0031, ADR-0023 and master-plan
  version 3.5;
- exact routine-versus-administrator-authority route classification;
- Account settings wording and removal of routine fresh-error handling;
- retirement of the completed LPL reconciliation page and Caddy exception;
- OpenAPI 0.29.0/generated clients, locale governance, user/runbook/matrix
  documentation and retained recovery behavior.

### Review result

| Check | Result |
|---|---|
| One signed-in routine workflow | Pass — branch creation, learner administration, Content Studio, Media Library, publication, privacy and reconciliation routes have no `auth.password-confirmed`; command-time auth/session/tenant middleware and domain permissions remain |
| Administrator-authority boundary | Pass — route policy fixes nine exact staff invitation, role-group, role-assignment and staff-lifecycle routes; an unconfirmed role creation is HTTP 403 and creates no role |
| Recovery/credential boundary | Pass — platform setup/contact routes retain `auth:web`, `auth.session`, `auth.password-confirmed`, exact capability and throttling; current password remains required for credential changes |
| Tenant and authoritative controls | Pass — focused backend suite passed 23 tests / 1,342 assertions, including cross-tenant denial, version/hash conflict, idempotent replay, immutable evidence and candidate cleanup |
| Visible simplification | Pass — Account settings says routine work needs no further password check; learner/content/media clients no longer advertise impossible fresh-password detours |
| Temporary aid retirement | Pass — the public file and Caddy handler are absent; browser and soft-launch contracts fail if the commissioning surface returns |
| API/client contract | Pass — OpenAPI 0.29.0 validates and all five generated TypeScript files match |
| Localization | Pass — six catalogues share `.10` key parity and exact hashes; five implementation previews retain honest independent-review warnings |
| Full repository verification | Pass — `make verify`: 177 SQLite tests / 1,935 assertions, 315 PostgreSQL tests / 4,469 assertions, 31 localization + 9 design-system + 242 Web tests, Composer/pnpm audits, Pint, PHPStan, lint, typecheck and all contract gates |
| Critical browser journey | Pass — `make e2e`: 4 reconciliation API tests / 103 assertions and Chromium 21/21, including one-login routine branch work and retired aid fallback |
| Production build | Pass — `make build`; only the existing non-failing large-chunk advisory remains |

### Security, privacy and tenancy review

- The visible/repeated one-factor ceremony is removed; password hashing,
  compromised-password rules, HTTPS Secure/HttpOnly cookies, CSRF, throttling,
  credential/session revocation, tenant scoping, role/permission checks,
  transactions, idempotency and content-free audit remain.
- APP-0060 accepts the increased risk that a stolen active administrator
  session can perform more authorized routine work before expiry/revocation.
  It does not grant cross-tenant or unheld permission authority.
- No raw password, token, transcript, audio, contact or learner content was
  inspected or added to repository evidence. No database or runtime authority
  mutation was required.

### Known limitations and retained gates

- Role/staff authority changes still use the optional Account settings
  confirmation control; a future UI may place that rare confirmation inline
  without changing the ADR boundary.
- No future integrated transcription campaign wizard was built because the
  only authorized LPL-01 campaign is terminal and its candidates are deleted.
  Future campaign UI remains a later bounded Media/Content slice.
- The repository change is not deployed to the temporary or stable host.
  Production/public release and independent security, privacy, tenancy,
  accessibility and language review remain gated.

### Exit decision

ADR-0023 repository implementation is complete under D-0194. LPL-01 remains
active at the typed source snapshot and deterministic Level 1 blueprint step;
no LPL-02 work is authorized.

## LPL-01 local evidence manifest — 14 August 2026

### Review result

The local evidence package records a deterministic, review-only shell for the
ordered Level 1 media parts. It verifies the four exact metadata revision IDs,
content hashes and source order, and keeps publication ineligible. The rich-text
body is intentionally absent because this turn did not access or modify the
legacy admin site and no exact read-only export was available locally.

| Check | Result |
|---|---|
| Four-part order | Pass — Introduction → Practice → Conversation → Interaction |
| Metadata bindings | Pass — all four reconciled revision/content-hash pairs match D-0191 |
| Rights bindings | Pass — all four approved program-scoped rights revisions are recorded |
| Typed rich-text body | Blocked — exact read-only source export not supplied |
| Blueprint content | Blocked — objective, Talk, Check and Apply fields remain absent |
| English/Kannada review | Blocked — no authored source blueprint exists to review |
| Publication | Blocked — manifest and blueprint shell cannot publish |
| Legacy-site safety | Pass — no request or mutation was made; temporary capture state was removed |
| Deterministic validator | Pass — `tmp/toolchain/node/bin/node scripts/validate-lpl01-evidence.mjs` |

No migration, API, runtime, tenant, credential, provider, transcript, learner
record, curriculum attachment or publication changed. LPL-01 remains active and
LPL-02 remains unauthorized.

The newly authored draft is deterministic and unpublished. Its validator confirms
the objective, weighted Check items, Apply evidence boundary and English-before-
Kannada review ordering; it does not claim legacy source fidelity.

ADR-0024 and the LPL-02 specification now define the typed lesson-practice
attachment boundary. No migration, API route, runtime record or publication has
yet been changed under that checkpoint.

## All-levels four-session journey (APP-0061) — 14 August 2026

### Scope reviewed

- ADR-0025 / master plan 3.6 four-session contract;
- operator lesson-practice attachment APIs;
- learner `/me/levels` delivery and Practice UI path;
- OpenAPI 0.30.0 and LevelSessionDeliveryTest evidence.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Option A accepted and ADR recorded | Pass | APP-0061; ADR-0025; D-0201 |
| Four sessions data-driven for all levels | Pass | `config/level_sessions.php`; Levels 2–17 commissioning_pending |
| Attachment requires reviews + active lesson_audio + approved rights | Pass | `LessonPracticeAttachmentService` |
| Check answers withheld until submit; deterministic scoring | Pass | `LevelSessionDeliveryService`; LevelSessionDeliveryTest |
| Cross-tenant isolation of availability | Pass | LevelSessionDeliveryTest other-org case |
| OpenAPI/contracts updated | Pass | openapi 0.30.0; `pnpm run contracts:generate` |
| Web path renders Introduction with Kannada guidance | Pass | LevelPathPanel vitest |

### Known limitations

- Exact live Direct Level 1 four-asset attachment is an operator action, not auto-performed here.
- Levels 2–17 have no commissioned media/blueprints.
- Curriculum publication and production/public release remain unauthorized.
- Bound conversation start still depends on the existing conversation provider gate/fakes.
- Full `make verify` / `make e2e` were not claimed for this stroke.

## APP-0062 — Levels 2–17 teaching-audio Media Library ingest

### Scope reviewed

- Exact inventory SHA-256 private `lesson_audio` ingest under APP-0062 / D-0203;
- ffprobe inspection + ClamAV scan path;
- program-scoped Direct pilot rights confirmation (web/Android, India);
- no credentials or media bytes committed.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Inventory hashes ingested privately | Pass | 63 accepted + 4 skipped Level 1; DB 67/67 match inventory |
| Malware/inspection drivers used | Pass | `MEDIA_INSPECTOR_DRIVER=ffprobe`, `MEDIA_SCANNER_DRIVER=clamav` |
| Program rights confirmed | Pass | 63 APP-0062 rights rows; 67 assets with approved program rights |
| No Git media binaries / no secrets in evidence | Pass | `/tmp` staging only; ingest-result JSON is hash/ops metadata |
| Attachment/publication not claimed | Pass | STATUS next action remains attachment |

### Known limitations

- Levels 2–17 blueprints, content review and four-session attachment remain open.
- Artisan command `media:ingest-app0062-lesson-audio` is a local operator aid bound to approval `APP-0062` only.
- Operator password was supplied ephemerally for the ingest command and must not be committed; rotate if exposed beyond the intended operator channel.

## APP-0061 — Level 1 private draft attachment execution

### Scope reviewed

- Private unpublished Level 1 `can` blueprint create + English/Kannada approve +
  four-part APP-0047 audio attachment on Direct LPL pilot program.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Migrations applied | Pass | `lesson_practice_*` and `level_session_*` tables |
| Blueprint + dual reviews | Pass | blueprint `01KZZY3RFR2DB04JY0GTFRXQZ1`; both reviews approved |
| Four assets attached | Pass | introduction/practice/conversation/interaction media rows |
| Learner Level 1 available | Pass | `LevelSessionDeliveryService::listLevels` → `available` |
| Publication not claimed | Pass | readiness `publication_eligible: false` |

### Known limitations

- Independent production security/privacy review is still outstanding.
- Live Azure conversation remains unauthorized locally; local uses
  `CONVERSATION_PROVIDER_DRIVER=fake` only.

## APP-0063 — Levels 2–17 private auto-commission

### Scope reviewed

- Private Direct auto-commission of Levels 2–17 blueprints, provisional English
  and Kannada approvals, four-part audio attachment, `level_concepts` config.

### Review result

| Check | Result | Evidence |
|---|---|---|
| APO waiver recorded | Pass | APP-0063; DR-0034; D-0204 |
| Levels 2–17 commissioned | Pass | 16 levels; evidence `levels-2-17-private-commission-result.json` |
| Learner catalog | Pass | `listLevels` → 17 `available` |
| Publication not claimed | Pass | `publication_eligible: false`; quality_debt listed in evidence |
| Level 15 Practice reuse | Pass | documented; same asset as Introduction/Practice combined file |

### Known limitations

- Reviews are provisional auto-approvals, not specialist bilingual sign-off.
- Template objectives/checks and Kannada summaries must be replaced before
  curriculum publication.
- Production/Play remain unauthorized.
- Some metadata transcripts are APP-0063 placeholders.

## Speak room hold-to-talk (D-0215) — 29 August 2026

### Scope reviewed

- Hold-to-talk Speak room, one Practice speaking surface, optional example
  stages, ADR-0007 per-turn submit amendment.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Hold control is presentation-only in the design system | Pass | `RecordingControls` `captureMode="hold"`; host owns capture |
| Release is the consented per-turn submit | Pass | D-0215; VoicePracticePanel auto-upload/accept; low-confidence candidate retained |
| One speaking room | Pass | Practice tabs Levels \| Speak; SpeakRoom everyday / situation |
| Blocking Warm-up/Observe gates removed | Pass | Conversation starts at Talk; Role-play auto-advances to Perform |
| SuperNova trademarks not copied | Pass | Original Maya hold/example copy; catalogues `ui-*-2026-08-29.4` |
| 1:1 not added as fifth nav item | Pass | DR-0037 B remains open; tuition request stays on Home |
| Marks/mastery unchanged | Pass | Advisory L1 correction only |

### Known limitations

- Always-on listening, in-app video, and paid subscriptions remain out of scope.
- Role-play stays `synthetic_unreviewed`. Kannada UI catalogue remains
  `pending-independent-review`.
- Production/Play remain unauthorized.

## APP-0022 live Azure enablement on soft-launch stack — 14 August 2026

### Scope reviewed

- Switch active API from `fake` to `azure-openai-dev` under ₹500 APP-0022 spend
  evidence; soft-launch environment; live Confidence Conversation smoke.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Preflight empty | Pass | `AzureOpenAiConversationConfiguration::preflightErrors()` → `[]` |
| Provider binding | Pass | `AzureOpenAiConversationModelProvider` |
| Live Friend turn kn-IN | Pass | HTTP 201; Kannada `explanation_l1` present |
| Live clinic role-play start | Pass | HTTP 201; mode `roleplay` |
| Cap | Pass | spend evidence `APP-0022` / `cap_inr=500` |

### Known limitations

- Ordinary conversation STT/TTS remain text-first (not fake, not live Azure Speech DI).
- Production/Play remain unauthorized; this is soft-launch only.
- Level 2–17 lesson copy remains provisional (APP-0063).

## APP-0061 — Level 1 audio stream + bound free-speech (local)

### Scope reviewed

- Session-scoped lesson audio streaming and bound Confidence Conversation start
  for Level path parts; local fake provider enablement.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Audio route streams MPEG | Pass | `GET .../sessions/{part}/audio` → 200 `audio/mpeg` for all Level 1 parts as `northstar` |
| Browser canplay | Pass | Playwright: conversation audio `readyState=4`, duration ≈135s |
| Bound conversation start | Pass | `POST .../conversation-sessions` → 201; UI handoff to Confidence Conversation |
| Provider fail-closed mapping | Pass | `ConversationProviderException` → 503 on level conversation start |
| OpenAPI + contracts | Pass | `pnpm run openapi:check` |
| LevelSessionDeliveryTest | Pass | postgres phpunit filter, 3 tests |

### Known limitations

- Production must keep conversation provider disabled.
- Levels 2–17 still commissioning_pending for learner delivery.
- Rate-limit redis can make password sign-in look stuck after repeated failures;
  flush local redis if needed during operator testing.

## LPL-04 level checkpoint + sequential unlock — 13 September 2026

### Scope

Bounded level checkpoint (3 attempts, 80% pass), four-session prerequisite,
append-only unlock decisions on checkpoint pass. Checkpoint items derive from
blueprint `check` when no `level_checkpoint` block is authored.

### Verification result

| Check | Result | Notes |
|---|---|---|
| `LevelSessionProgressServiceTest` | Pass | unlock requires checkpoint pass |
| `LevelSessionDeliveryTest` | Pass | `level_checkpoint_attempts` table |
| `LevelSessionLevelsHttpTest` | Pass | failed attempt + checkpoint pass unlocks level 2 |
| `LevelPathPanel.test.tsx` | Pass | progress-locked copy + journey tests |
| `localDemo.test.ts` | Pass | seventeen levels + demo checkpoint API |

Postgres filter `LevelSession|LevelCheckpoint`: 4 tests, 100 assertions.
