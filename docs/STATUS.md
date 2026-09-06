# Repository status

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

## Current checkpoint

| Field | Value |
|---|---|
| Checkpoint | UX-01: Futuristic Learning Studio — complete (D-0283) |
| Status | D-0283 closes UX-01B visual review by Accountable Product Owner instruction and records UX-01C–G complete. Studio landing, sign-in, learner frame and route splitting are served. UX-02–10 remain proposed. Android TalkBack remains a release gate. Wave 5 host remains blocked. `APP_ENV` remains `soft-launch`. |
| Canonical baseline | `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.14 |
| Updated date | 6 September 2026 |
| Git branch | `codex/confidence-conversation` |
| Product code | Five-destination nav (ADR-0026); teacher name Swathi (D-0218); in-app Android password (ADR-0033); Google/Facebook extra sign-in (ADR-0034); Direct subscriptions (ADR-0035); Premium courtesy month (ADR-0036); paid live-AI caps and talk packs (ADR-0037); Indic STT port with 25s cap and Azure failover (ADR-0027 Wave 2A) |
| Next required action | UX-02 (first-use) is the next UI programme checkpoint when authorized. APO: payment gateway (Wave 8), GST, hosting server, DNS A record for `ai.globalspokenenglish.com`. Do **not** set `APP_ENV=production`. Do **not** stop the Quick Tunnel until Wave 5.3. |
| Production/public release | Not authorized |
| Soft launch | Live Azure OpenAI and mixed-script guidance TTS. Indic STT staff verification is complete with the driver still set to `VOICE_PROVIDER_DRIVER=indic`. Non-English UI catalogues `ui-*-2026-09-06.1` are pending independent review and fall back to English outside explicit development preview. Learner locale picker remains kn-IN + en-IN only. The previously published closed-beta APK targets an obsolete tunnel origin and is not current. |

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
- Azure Speech: `$HOME/ge-speech-benchmark/secrets/azure-speech.secret` mode 0600; voices Neerja + Sapna; cap ₹500 (APP-0064) until 2026-09-14.
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

Detailed evidence is recorded in [QUALITY_REVIEW.md](QUALITY_REVIEW.md),
[the PR-008 verification scope](PR-008_CONVERSATION_FAKE_PROVIDER_VERIFICATION_SCOPE.md),
[the PR-007 verification scope](PR-007_OFFLINE_BUNDLE_VERIFICATION_SCOPE.md),
[the PR-006 verification scope](PR-006_DETERMINISTIC_DAILY_SPEAKING_VERIFICATION_SCOPE.md),
[the PR-005 verification scope](PR-005_CONTENT_PUBLICATION_VERIFICATION_SCOPE.md)
and [the PR-004 accessibility scope](PR-004_ACCESSIBILITY_VERIFICATION_SCOPE.md).

PR-006 verification results:

- `make verify` passes with 39 SQLite tests/264 assertions, 163 PostgreSQL
  tests/1,848 assertions, 31 localization tests, 9 design-system tests and 51
  web tests. Dependency audits, locale validation, OpenAPI/generated-client
  drift, formatting, PHPStan, lint and recursive TypeScript checks pass.
- `make openapi` passes with OpenAPI 3.1.1 document version 0.4.0 and matching
  generated clients.
- `make e2e` passes all seven Chromium scenarios, including the explicitly
  contract-stubbed learner golden path and microphone-denial path.
- `make build` passes Laravel production optimization/cleanup and a strict
  production Vite build of 67 modules.
- Fresh migration, repeat seed, empty PR-006 rollback, forward reapply and
  repeated seed pass on the explicitly verified `global_english_test`
  PostgreSQL 14.19 database. Final state has migration `000024` once, 21
  permissions and zero publications, learner profiles or learner attempts.
- Twenty-five in-scope Markdown files pass structure and local-link checks.
  External URLs were not live-checked.
- `git diff --check` and the PR-006 security/scope audit pass.

## Known limitations

- PR-006 has no approved production mission, Kannada content, transcript or
  model-audio rights evidence. The canonical seed remains unpublished.
- Browser E2E may exercise a contract-stubbed synthetic mission. That is not a
  true full-stack published-media journey or accountable content approval.
- PR-006 deliberately accepts exactly one manifest mission. Multiple missions
  contributing to an objective require a later persisted result snapshot or
  global objective-evidence order before this boundary can expand.
- Raw learner audio is local-only and unavailable for transcription, trainer
  review or spoken mastery. Its non-authoritative capture receipt is not
  evidence of intelligible speech.
- DR-0005 is resolved by accepted ADR-0007 under APP-0011, but no recording
  upload, persistent consent state, retention/deletion implementation or
  real-provider processing is authorized.
- PR-007 offline behavior is accepted at its exact implementation commit.
  PR-008 adds only project-owned deterministic fake-provider paths; every real
  AI/speech provider and provider SDK remains excluded.
- ADR-0004's development roles are consolidated in one human and therefore
  non-independent. Independent tenancy, English-academic and Kannada-language
  review remains required before production.
- The human reviewer appointments still require active organization
  memberships, permissions and scoped runtime authority-appointment records
  before any product-data approval is valid.
- No curriculum publication has human runtime approval. The one seeded mission
  is a synthetic unpublished draft.
- Public content/publication HTTP operations are not part of PR-005; the
  implemented write paths are domain commands.
- Automatic scheduled activation and schedule cancellation are not included.
- Media-bearing cloning fails closed until reviewed object-copy, retention and
  derivative-rights behavior exists.
- Media asset import and initial pending-rights creation do not yet have a
  public or dedicated domain import command. Program-scoped confirmation,
  rejection and withdrawal are implemented; production ingest remains a
  reviewed later workflow.
- Evidence operations use PostgreSQL table-level share locks and require
  representative production-volume concurrency benchmarking.
- The APP-0004 self-appointment exception remains disabled until deployment
  binds its exact evidence to `S R Bhandary`'s stable application-user ULID.
  It does not make the consolidated reviews independent.
- Kannada, Malayalam, Tamil, Telugu and Hindi wording has not been approved by
  independent accountable reviewers. The current drafts and visual rendering
  evidence are not linguistic approval.
- The 200% and 400% automated checks enlarge root text and test reflow; they
  are stress proxies, not evidence of actual browser-zoom testing.
- Manual screen-reader, real browser-zoom and representative Android TalkBack
  testing remain outstanding. Automated axe results do not replace them.
- Docker Engine 29.6.2 and Compose 5.3.1 are installed, but this Codex login
  session cannot access the Docker socket until group membership is refreshed.
  PostgreSQL verification used the available loopback version 14 service.
- The fresh native verification toolchain used PHP 8.4.23, Node.js 24.18.1,
  pnpm 11.18.0, Composer 2.10.2 and GNU Make 4.4.1.
- The GitHub Actions workflow has not run on a remote runner.
- PR-006 is accepted at its exact implementation commit under APP-0007; every
  recorded limitation remains in force.

## PR-010 implemented boundary

- Context allowlist, versioned prompts (`prompt_versions`), structured output
  validation, curated safety fallback, advisory correction digests and
  learner error-pattern counters are implemented behind the existing
  conversation turn pipeline.
- Gentle / Tutor / Fluency coaching modes are accepted for Friend sessions.
- Priority correction is returned as a separate API field and rendered as a
  separate correction card; it is never merged into the Friend reply text.
- Deterministic fake model adapters remain the only local/testing path.
  Production remains fail-closed for conversation providers.
- Authoritative learning records (`learner_attempts`, `mastery_states`, and
  related tables) are not written by the AI orchestration path.
- OpenAPI and generated contracts include `priority_correction` and
  `safety_state`.

## PR-011 implemented boundary

- The active boundary is
  [the PR-011 evaluation and memory verification scope](PR-011_EVALUATION_MEMORY_VERIFICATION_SCOPE.md).
- Learner-visible AI memory facts are purpose-bound plaintext (`conversation_context`
  / `learner_explicit`), listed completely when active, and soft-deleted with
  immutable `memory_events` audit digests. Clearing memory does not delete
  learning or account records.
- `/api/v1/me/ai-memory` supports GET/POST/PATCH/DELETE with membership ownership
  and idempotent writes. Active facts are allowlisted into conversation context
  with hard caps (5 × 200 characters).
- The Practice UI exposes list, create, edit and delete for learner-visible
  facts.
- Locked deterministic AI evaluation suite lives under
  `apps/api/resources/ai-evaluation/v1/` and runs via `php artisan ai:evaluate`
  / `make ai-eval` (speech half runs only when `SPEECH_BENCHMARK_RUN_ID` is set).
- Human-review export/import packages bind the exact suite key and hash,
  accept `pending`/`accept`/`reject` dispositions, fail closed on mismatch or
  unknown cases, and keep prompt/cost rollups aggregate-only and
  synthetic-fixture based.
- Real LLM providers, PR-012, production and Play remain unauthorized.

## Next checkpoint gate

PR-011 is accepted under [APP-0020](APPROVALS.md#app-0020) at
`220628f4d4c69284af0b1f2534e8902f3b272088` with its recorded limitations
([PR-011_APO_ACCEPTANCE_PACKAGE.md](PR-011_APO_ACCEPTANCE_PACKAGE.md)).

[APP-0021](APPROVALS.md#app-0021) authorized beginning the Confidence Conversation
enterprise slice. [APP-0025](APPROVALS.md#app-0025) records Accountable Product
Owner acceptance of that slice at
`5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b` with every recorded limitation
([CONFIDENCE_CONVERSATION_APO_ACCEPTANCE_PACKAGE.md](CONFIDENCE_CONVERSATION_APO_ACCEPTANCE_PACKAGE.md)).
Nested A4/A5 gates remain as accepted under
[APP-0022](APPROVALS.md#app-0022) / [ADR-0010](adr/0010-conversation-model-provider-policy.md)
and [APP-0024](APPROVALS.md#app-0024) / [ADR-0011](adr/0011-confidence-conversation-guidance-tts-policy.md).
Live multi-locale Azure TTS spend and ordinary Azure Speech DI expansion
remain unauthorized. Live Azure OpenAI calls remain operator-gated
(outside-repo evidence + ₹500 cap); production DI stays `disabled`.
Demo-local Azure TTS / OpenRouter (D-0092 / D-0094) are presentation-only
and do not count as enterprise verification evidence.

[APP-0031](APPROVALS.md#app-0031) accepted PR-014 Capacitor Android
closed-beta foundations at `44b0e672e004cf3846cddabc456c21153fa4607e` with
recorded limitations
([PR-014_APO_ACCEPTANCE_PACKAGE.md](PR-014_APO_ACCEPTANCE_PACKAGE.md)).
Production package identity is `com.globalenglish.app` at
`apps/web/android-app`; the demo APK (`com.globalenglish.demo`, D-0078) stays
presentation-only.
[APP-0033](APPROVALS.md#app-0033) accepted PR-015 bounded operations hardening
and closed-beta evidence closure at `5eca7515291ea571a7f697dcdc2da6197535f839`
with recorded limitations
([PR-015_APO_ACCEPTANCE_PACKAGE.md](PR-015_APO_ACCEPTANCE_PACKAGE.md)).
Active plan step is **F0** pre-F0 evidence-closure under
[APP-0034](APPROVALS.md#app-0034)
([F0_PHASE1_DOD_GAP_ASSESSMENT.md](F0_PHASE1_DOD_GAP_ASSESSMENT.md)).
F0 Definition of Done sign-off and Google Play public release remain
unauthorized.
[APP-0031](APPROVALS.md#app-0031) accepted PR-014 Capacitor Android
closed-beta foundations at `44b0e672e004cf3846cddabc456c21153fa4607e` with
recorded limitations
([PR-014_APO_ACCEPTANCE_PACKAGE.md](PR-014_APO_ACCEPTANCE_PACKAGE.md)).
Production package identity is `com.globalenglish.app` at
`apps/web/android-app`; the demo APK (`com.globalenglish.demo`, D-0078) stays
presentation-only.
[APP-0029](APPROVALS.md#app-0029) accepted PR-013 bounded operator
content-workflow + audit at `9220ab27baa6dc29d82caf8e9e97e94f7df7219d` with
recorded limitations
([PR-013_APO_ACCEPTANCE_PACKAGE.md](PR-013_APO_ACCEPTANCE_PACKAGE.md)).
Sampled speaking review, privacy ops, learner PII CSV export, full content
studio, F0 Phase 1 DoD, Google Play public release and real learner production
data remain unauthorized until distinct APPs.
Live multi-locale Azure TTS spend and ordinary Azure Speech DI expansion
remain unauthorized. Live Azure OpenAI calls remain operator-gated
(outside-repo evidence + ₹500 cap); production DI stays `disabled`.
Demo-local Azure TTS / OpenRouter (D-0092 / D-0094) and the demo APK
(`com.globalenglish.demo`, D-0078) are presentation-only and do not count as
enterprise verification evidence.

Production additionally requires qualified legal review and independent
security, privacy, tenancy, accessibility, language/content and release
review.
