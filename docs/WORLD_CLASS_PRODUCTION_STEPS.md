# World-class production — steps to follow

**Companion to** [WORLD_CLASS_PRODUCTION_PLAN.md](WORLD_CLASS_PRODUCTION_PLAN.md).  
**How to use:** tick boxes in order. Do not start a later wave until the previous wave’s **Exit** is true. One owner per step; if two people are needed, the step says so.

**Hard rule:** no public or paid learner release, Play production/public listing,
or `APP_ENV=production` until all required Wave 2B/DR-0048 gates pass and the
Accountable Product Owner explicitly approves release. An environment label
cannot bypass the gate. Staff-only controlled evaluation may remain on
`soft-launch` under its existing consent, privacy and spend authority; it is not
production.

---

## Wave 0 — Decide and freeze (day 0)

Recorded 1 September 2026 from APO answers in the active session.

- [x] **0.1** Record the starting sequence (Sarvam STT + Azure Turn Model + Azure TTS; no Flutter). This baseline does not preselect or reject ElevenLabs; 0.8 and the controlled bake-off now govern the final speech route.
- [x] **0.2** Freeze new features except the waves below. No Notice Board, Flutter, Miss Nova, old-site import, or 22-language Speak.
- [x] **0.3** Go-live host: `https://ai.globalspokenenglish.com`
- [x] **0.4** Payment: keep dummy checkout only for the controlled staff/closed-beta lane; choose and prove Razorpay/Cashfree in Wave 8 before a paid learner release.
- [x] **0.5** Sarvam key is on this workstation (not in git). File: `$HOME/ge-indic-stt/secrets/sarvam.secret` (mode `0600`, outside the repository). Do **not** set `VOICE_PROVIDER_DRIVER=indic` until Wave 6. **Rotate this key in the Sarvam dashboard** — it was pasted in chat.
- [x] **0.6** Do **not** import `globalspokenenglish.com` passwords.
- [x] **0.7** UAT: **staff-only until testers exist.** Play public waits until ~20 adults can be recruited (Wave 9). Closed-group staff testing can still proceed.
- [x] **0.8** Record the 9 September 2026 planning direction: complete every
  [DR-0048](DR-0048_MULTIPROVIDER_SPEECH_OBSERVABILITY_AND_LATENCY_REQUEST.md)
  recommendation before production, one bounded checkpoint at a time. This
  plans Azure/Sarvam/ElevenLabs integration, metering, admin comparison,
  latency work, a controlled bake-off and Kannada Help; it does not itself
  authorize implementation, credentials, spend, learner-audio processing or
  release.

**Exit:** hostname, payment boundary and the complete pre-production speech
gate are written down. **Wave 0 complete.** Next is Wave 1.

---

## Wave 1 — Land the uncommitted product (before any cutover)

Completed 1 September 2026 on this host. Commit `2c9dc14`. System PHP has no `pdo_pgsql`; migrations and artisan used a throwaway `php:8.4-cli-bookworm` container with `pdo_pgsql` on host networking.

- [x] **1.1** Inventory: branch `codex/confidence-conversation`; 154 files in the Wave 1 commit (federated/password auth, Direct entitlements, OpenAPI, locales, ADRs 0033–0036, world-class docs).
- [x] **1.2** Commit `2c9dc14` — no `.env`, no Sarvam secret, no learner data.
- [x] **1.3** Backup `~/ge-soft-launch/backups/soft-launch-20260901T151339Z.sql.gz`. Soft-launch restarted on this working tree (API/worker/scheduler/Caddy; `health live: ok`; Vite `dist` rebuilt). Tunnel still stopped. Android APK rebuild is Wave 5.
- [x] **1.4** Migrations applied: `2026_09_01_000280_create_federated_identities`, `2026_09_01_000290_create_direct_entitlements`.
- [x] **1.5** Catalogue present: `demo` (Two-day demo), `monthly_modules` (Modules, ₹350), `monthly_modules_roleplay` (Premium, ₹500).
- [x] **1.6** GSTIN stays dummy placeholder (18%) until Wave 8.
- [x] **1.7** Courtesy Premium: dry-run 5 granted / 0 skipped; live grant 5 granted / 0 skipped; replay 0 granted / 5 skipped. Counts only — no usernames recorded here.

**Exit:** live DB has entitlement schema, Premium catalogue, and 5 Direct learners on courtesy Premium. Soft-launch API is running the committed tree. **Wave 1 complete.** Next is Wave 2A (clip length, ledger, failover — do not flip Indic STT yet).

---

## Wave 2 — Code that must exist before Indic STT or public Speak

Wave 2A is historical and complete; Wave 2B records the remaining production
requirements. Flipping environment variables without the required bindings and
evidence can fail-close Speak or silently disable it.

### 2A — Must have for Indic STT go-live

Completed 1 September 2026 on this host. Speak remains on Azure. Indic is not enabled.

- [x] **2.1** **Utterance length:** hold-to-talk auto-stops at 25 seconds. Server `voice.maximum_duration_ms` is 25_000. Learn tap-to-record stays 120 seconds (`learning.capture.maximum_duration_ms`).
- [x] **2.2** **Cost ledger CHECK:** migration `2026_09_01_000300_allow_indic_stt_cost_ledger` allows `indic-http-stt` (`indic-http-stt-v1:`) and `azure-speech-soft-launch-stt`.
- [x] **2.3** **Usage policy:** `ConversationProviderUsagePolicy` allows those STT keys under soft-launch `azure-openai-dev`.
- [x] **2.4** **Failover:** `IndicWithAzureFailoverSpeechToTextProvider` retries once on Azure Fast Transcription after Indic HTTP/connection/transport failure. Not for malformed/unauthorized/input errors.
- [x] **2.5** **Secrets:** documented in `apps/api/.env.example` comments (`$HOME/ge-indic-stt/secrets/sarvam.secret`, `api.sarvam.ai`). Driver not flipped.
- [x] **2.6** **Consent copy:** `config/voice.php` en-IN + kn-IN name Sarvam then Azure failover. Revision `voice-processing-notice-2026-09-01`.
- [x] **2.7** Tests: Indic HTTP fake; ledger constraint test; failover tests; 25s inspector and hold auto-stop. PostgreSQL CHECK: docker `php:8.4-cli-bookworm` + `pdo_pgsql` applied `2026_09_01_000300_allow_indic_stt_cost_ledger` and `IndicSttCostLedgerConstraintTest` 1/1.

### 2B — Must have before any public/paid release or `APP_ENV=production`

Do **not** expose public/paid learner traffic or do Wave 12 until every required
item below is implemented and evidenced. Today:

| Binding | What production does today |
|---|---|
| Conversation | `ConversationEnvironmentGate` refuses live Azure |
| Chat / TTS / STT DI | `AppServiceProvider` only live in non-production; Indic only local/testing/soft-launch |
| Payments | `PaymentProvider` is always `DisabledPaymentProvider` in production |

- [ ] **2.8** Allow only the evidence-selected Turn Model and STT/TTS primary/
  fallback routes in production, each with its approved spend cap, configuration
  pin and kill switch (rewrite the environment gate + DI without hard-coding a
  vendor into the domain).
- [ ] **2.9** Razorpay (or chosen) adapter behind `PaymentProvider`. Production must not stay dummy. Dummy only for staff/`local`.
- [ ] **2.10** Heavy Model router (optional for first public week; required before you claim “all LLMs”). Same JSON contract. Daily INR ceiling.
- [x] **2.11 SPEECH-GOV-01:** Accept the bounded ADR/master-plan amendments for
  provider routing, the separate first-audible gate, realtime transport and
  Kannada Help. Freeze the STT/model contracts, Help access/entitlement rules,
  retention/aggregate policy and separate authorization points for credentials,
  capped spend, evaluation data, processing and release. Closed under D-0306 /
  APP-0077.
- [ ] **2.12 SPEECH-OBS-01:** Add content-free, retention-compliant usage events,
  exact provider/model/API/voice/region/configuration identities, explicit
  billable units, versioned rate cards, tax/discount/credit/FX evidence,
  dispatch-versus-cache state, client/server latency stages and invoice
  reconciliation. Provider-call records are append-only only while retained;
  enforce ADR-0007's 30-day aggregate/delete rule and one learner entitlement
  charge per logical turn. D-0307 completes the code path and synthetic proof.
  Acceptance stays open until real source-backed rates and one real capped
  statement per evaluated candidate are reconciled through the audited
  platform-only path. No comparative economics may be claimed meanwhile.
- [ ] **2.13 SPEECH-PROVIDER-01:** Integrate Azure, Sarvam and ElevenLabs only
  behind project-owned STT/TTS ports. Review each exact product's DPA,
  subprocessors, location, no-training/human access, retention and deletion;
  pin server-owned routing; allow one primary plus bounded reviewed fallback
  (two total dispatch attempts per stage); add timeouts, circuit breakers,
  quotas, spend caps and independent kill switches. Unselected candidates stay
  disabled or benchmark-only; ordinary learner audio is never three-way
  shadowed. D-0308 added fail-closed challenger ports and a two-dispatch
  router. This box stays open: Wave 3.8 is development-closed (D-0312) but
  HTTP enablement and live-deployment key rotation are still required. No new
  vendor may receive content.
- [ ] **2.14 SPEECH-ADMIN-01:** Deliver the permission-gated Speech Economics API
  and accessible portal view. Platform administrators alone see commercial
  rate cards/invoices by default; organization operators require an explicit
  permission and see only active-organization usage/allocated cost. Prove
  cross-tenant denial for list/filter/export/job/cache paths, audited safe CSV,
  small-cell suppression, RFC 9457, OpenAPI/generated clients and API p95
  ≤800ms. D-0313 delivered the API, Operations panel, audited CSV, small-cell
  suppression and OpenAPI 0.44.0. No job/cache path exists to leak. Operator
  API p95 ≤800ms was not load-measured, so acceptance remains open under
  D-0316 even though the implementation slice is present.
- [ ] **2.15 SPEECH-LAT-01:** Persist the full turn waterfall and distinguish
  acknowledgement, first substantive response signal and first audible audio.
  Measure/optimize model first-token and completion time, validate a bounded
  reply before speech, stream main TTS instead of waiting for a full WAV, move
  optional guidance off the critical path, and prove cancellation,
  backpressure, private no-store delivery and mobile playback. Do not call a
  fast spinner an immediate answer. D-0314 returns the validated reply
  without waiting for guidance TTS, streams stored English WAV from a
  private `no-store` path, and records ack / first signal / first audible
  separately. D-0316 adds a default-off bounded Azure model SSE path that can
  record first content separately from completion while withholding output
  until the complete JSON reply validates. No live model sample was taken;
  TTS generation still completes a full WAV before the private stream opens;
  server-side turn cancellation/backpressure and load p95 remain unproven.
- [ ] **2.16 SPEECH-RT-01:** Under an accepted transport ADR, benchmark Sarvam
  Saaras realtime and ElevenLabs Scribe v2 Realtime against recorded STT while
  retaining explicit learner submission, consent-before-preconnect, endpointing,
  reconnect/deduplication and the bounded recorded recovery route. Do not add
  always-on capture or full duplex. D-0315 added fail-closed Sarvam/ElevenLabs
  realtime ports, a consent-gated first-party ticket that names recorded
  upload as recovery, and `speech:realtime-benchmark` against deterministic
  fakes. Live vendor sockets were not opened; HTTP enablement remains denied,
  so the required candidate comparison remains open under D-0316.

- [ ] **2.17 SPEECH-BAKEOFF-01:** Freeze before dispatch a stratified corpus of at
  least 200 STT utterances plus 20–50 Kannada/English/code-mixed TTS scripts,
  exact configurations, rubric/weights/subgroup floors, spend cap and expiry.
  Use only synthetic, licensed or separately consented material; blind bilingual
  review; Karnataka regional/noise/device/Wi-Fi/4G/cold-warm coverage; 50
  concurrent and approved 2× peak load; and rank cost only after quality,
  privacy, safety and tenancy pass.
- [ ] **2.18 KN-HELP-01:** After accepted product authority, implement explicit
  Kannada Help under the backend free-speech entitlement: preserve native/
  code-mixed text only within consent/retention, return a short reviewed Kannada
  reply plus optional English bridge, and use the selected Kannada speech route
  plus reviewed fallback. English Practice may give one Kannada bridge then
  returns to English. No AI mark, mastery, progression, review or unlock; no
  production preview without exact-revision bilingual approval.
- [ ] **2.19 SPEECH-PROD-01:** Select and pin one primary plus at least one
  reviewed fallback per STT/TTS stage from evidence. Prove the master-plan
  first-response-signal median ≤4s/p95 ≤8s target and the separately accepted
  first-audible median ≤4s/p95 ≤8s gate (launch objective ≤1.8s/≤4s), known
  failures visible within 2s, availability/capacity, migrations, deletion,
  canary and rollback. Run `make openapi`, `make verify`, `make e2e`,
  `make ai-eval`, `make build`, PostgreSQL tenancy, load/soak, Android lifecycle
  and accessibility gates; record exact evidence and obtain explicit release
  approval.

**Exit for 2A:** code merged on this branch; CHECK migrated on the soft-launch DB. Speak still on Azure until Wave 6.  
**Exit for 2B:** every required item 2.8–2.19 is evidenced on a staging clone.
This exit is required before any public or paid learner release, Play production/
public listing, or `APP_ENV=production`. Staff-only controlled evaluation is the
only pre-exit lane.

---

## Wave 3 — Accounts, vendors, spend (parallel with Wave 2)

Host inventory recorded 1 September 2026 on this Ubuntu workstation. No secret values, transcripts, or learner data were copied into git or chat. Indic STT remains off. `APP_ENV` remains `soft-launch`.

- [x] **3.1** Azure OpenAI South India is the live Turn Model. Host `ge-conversation-southindia.openai.azure.com` returned HTTP 200 over TLS at 2026-09-01T16:04:14Z (no API key sent). Secret file `$HOME/ge-conversation-model/secrets/azure-openai.secret` is a regular file, owner uid 1000, mode `0600`, outside the repository. Soft-launch env: `CONVERSATION_PROVIDER_DRIVER=azure-openai-dev`, deployment `gpt-4-1-mini-southindia`, API version `2024-10-21`, **kill switch** `CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED` (currently `true`). Set that to `false` and restart the API/worker to stop chat spend. Authorized hard ceiling **₹500** (APP-0022).
- [x] **3.2** Azure Speech Central India is TTS and recorded-STT (live now, Indic failover later). Host `ge-speech-centralindia.cognitiveservices.azure.com` returned HTTP 200 over TLS at the same instant (no key sent). Secret `$HOME/ge-speech-benchmark/secrets/azure-speech.secret` mode `0600`, outside git. Voices remain `en-IN-NeerjaNeural` and `kn-IN-SapnaNeural` (`GuidanceTtsPolicy`). Kill switches: `CONVERSATION_GUIDANCE_TTS_LIVE_REQUESTS_ENABLED=false` stops TTS; `VOICE_UPLOAD_ENABLED=false` or `VOICE_PROVIDER_DRIVER=disabled` stops learner STT. Authorized hard ceiling **₹500** (APP-0064), shared with learner STT (APP-0068). APP-0064 spend-approval `valid_until` is **2026-09-14** — renew before then.
- [x] **3.3** Sarvam key on disk: `$HOME/ge-indic-stt/secrets/sarvam.secret` (mode `0600`, outside git). That Wave 0 path is canonical; `~/ge-speech/secrets/sarvam.secret` was **not** created (do not duplicate the key). `VOICE_INDIC_SECRET_FILE` is unset; `VOICE_PROVIDER_DRIVER=azure`. **Still APO dashboard work:** rotate the key (it was pasted in chat); keep the email billing alert; supply an executed DPA/no-training hash. Monthly INR cap **₹500** is now asserted (APP-0079).
- [x] **3.4** Payments stay **dummy** only in the controlled staff/closed-beta lane (Wave 0.4 / Wave 8). `PAYMENT_DRIVER` is unset → `DummyPaymentProvider`. No Razorpay/Cashfree keys. Real GSTIN remains the operator tax-page placeholder. Webhook path does not exist until Wave 2.9 / Wave 8.
- [x] **3.5** Google/Facebook client IDs are **unset**. Do not add production redirect URIs until Wave 5 freezes `https://ai.globalspokenenglish.com`.
- [x] **3.6** Android application id is `com.globalenglish.app`. Play Console, signing-key custody, and Data safety draft are **operator dashboard work** (Wave 10). Not opened in this session.
- [x] **3.7** Authorized hard ceilings written (not new daily SMS alerts): Azure chat **₹500** (APP-0022); Azure speech **₹500** (APP-0064, includes STT). Sarvam monthly cap **₹500** asserted 9 September 2026 (APP-0079). Named SMS recipient: **not set** — keep Azure/Sarvam email alerts until you name a phone.
- [x] **3.8** Development-phase privacy/account packet recorded (D-0312 / APP-0081).
  Exact products, India host pin, Zero Retention assertion, ₹500/₹500 caps,
  email alerts, APP-0064 Azure Speech inheritance, and APP-0080 public-document
  residual risk are on file. The Accountable Product Owner deferred ElevenLabs
  key rotation and the deletion test until **before live deployment**, not
  during development. This does **not** authorize dispatch, secret binding,
  vendor HTTP, or production. `speech-wave38:preflight` may return development
  `ready` with those two items listed under
  `deferred_until_live_deployment`. Rotate the disclosed key, record the
  deletion-test receipt, and obtain a separate HTTP-enablement approval before
  any ElevenLabs content call. See
  [WAVE38_EVIDENCE_HANDOFF.md](WAVE38_EVIDENCE_HANDOFF.md).

**Exit (host):** Azure OpenAI, Azure Speech, and Sarvam secret files exist at mode `0600` outside git. Dummy checkout unchanged. Wave 3.8 is development-closed. **Not exit-complete for dashboard leftovers:** Sarvam key rotation; ElevenLabs live-deployment rotation and deletion test; named spend SMS; Play Console; Razorpay (Wave 8).

---

## Wave 4 — Backup, observe, rollback

Completed 1 September 2026 on this host. Live database was not overwritten. Indic was **not** flipped as a Wave 6 cutover in this wave; the gitignored env already had `VOICE_PROVIDER_DRIVER=indic` (ahead of Wave 6 staff tests). `APP_ENV` remains `soft-launch`.

- [x] **4.1** `make soft-launch-backup` wrote `~/ge-soft-launch/backups/soft-launch-20260901T162027Z.sql.gz` (mode `0600`, 462785 bytes, SHA-256 `8bfc83d57c6deb9e4cc4a825ec4cb647649469fca96072c56738d417ba3e84ad`). AES-256-CBC ciphertext `~/ge-offsite-backups/soft-launch-20260901T162027Z.sql.gz.enc` (mode `0600`, SHA-256 `6437e45efd1533be6f78a79cd4b8d98becbb0e79b7f4a78b6c96223ae5a0a364`). Passphrase file mode `0600` outside git. Gzip restore drill into a temporary database: passed, RTO 2s, `live_database_overwritten=false` (`artifacts/ops/soft-launch-restore-drill-20260901T162031Z.json`). REL-01 custom-format drill also passed, RTO 2s (`artifacts/ops/backup-restore-20260901T162039Z.json`). Single NVMe — copy the `.enc` file to USB/another machine when you can.
- [x] **4.2** `global-english-soft-launch.service` enabled and active. Worker and scheduler pids running (linger yes).
- [x] **4.3** `make soft-launch-health` live ok / ready ready. `make soft-launch-ready` 24 pass / 0 warn / 0 fail.
- [x] **4.4** Rollback card: [ops/SOFT_LAUNCH_ROLLBACK.md](ops/SOFT_LAUNCH_ROLLBACK.md). Previous APK `~/ge-soft-launch/artifacts/closed-group/global-spoken-english-in-app-login-20260901.apk` SHA-256 `aa94cacc3f4e244a9a258aee4f62e345f92bac6654ff3c22140678282213cca0`. Git SHA `114305dd9658edf515fcea90cecb19f0bfdbdd44`. STT rollback: `VOICE_PROVIDER_DRIVER=azure` then restart.
- [x] **4.5** User timer `global-english-soft-launch-observe.timer` every minute. Signals: API down (two live fails), queue stuck (worker/scheduler/Redis/backlog), provider 5xx counts from `laravel.log`. JSONL `~/ge-soft-launch/alerts/observe.jsonl`. No transcripts. Named SMS/Telegram not invented (`mail` not installed).

**Exit:** yesterday’s gzip dump can be restored into a temp DB; STT rolls back with one env change. **Wave 4 complete.** Next is Wave 5 (stable host). Do not treat the Quick Tunnel as the public origin.

---

## Wave 5 — Stable public host (stop the tunnel)

Started 1 September 2026. **Not exit-complete.** `make soft-launch-configure-stable` and tunnel-stop were **not** run: they would take down the working Quick Tunnel before DNS and WAN 80/443 exist.

- [ ] **5.1** Cloudflare DNS: `ai.globalspokenenglish.com` has **no A record** (checked 2026-09-01T16:37Z via 1.1.1.1). Domain NS is Cloudflare. No Cloudflare API token is on this host. Create a **DNS-only** `ai` A record to this workstation’s public IPv4. Do not paste tokens in chat.
- [ ] **5.2** Router: WAN **80** on the public IPv4 currently returns a **router/login HTML page**, not Caddy. WAN **443** refuses. WAN 8080/8443 time out. This host’s LAN address is **`192.168.1.4`**. Map WAN 80 → `192.168.1.4:8080` and WAN 443 → `192.168.1.4:8443` (reserve that DHCP lease).
- [ ] **5.3** After 5.1–5.2 pass `make soft-launch-stable-prereq`, then `make soft-launch-configure-stable HOST=ai.globalspokenenglish.com` and restart. Not run.
- [ ] **5.4** Caddy TLS/HSTS is generated only after 5.3. Quick Tunnel still running on purpose.
- [ ] **5.5** `APP_URL` still the rotating tunnel origin. Sanctum Secure-cookie stays false until the stable host is configured.
- [ ] **5.6** Phone-on-Jio sign-in not possible until HTTPS on `ai.globalspokenenglish.com` exists. No adb device attached.
- [ ] **5.7** Android rebuild blocked until 5.1–5.6. Do not pin a new APK to the tunnel.
- [ ] **5.8** Sideload blocked: `adb devices` empty.
- [x] **5.9** Public privacy + account-deletion pages: `/privacy.html` and `/account-deletion.html` (also `/privacy`, `/account-deletion` after Caddy reload). In-app footer links. Operator erasure API already exists; this page tells learners to ask their institute admin. Copied into current `dist` so loopback already serves them (HTTP 200).

**Exit:** not met. Next: APO creates the DNS A record and router maps, then engineering runs 5.3–5.8.

---

## Wave 6 — Indic STT staff verification (Speak ears)

Completed 5 September 2026 on the invite-only soft-launch origin. Wave 5 stable
hosting remains open, so this evidence does not authorize a public release.

- [x] **6.1** Staging/soft-launch env configured on this host (`VOICE_PROVIDER_DRIVER=indic`, Sarvam secret mode `0600`, allowlisted org). Verified by `make wave6-indic-preflight`.
- [x] **6.2** Restart API + worker; DI binds `IndicWithAzureFailoverSpeechToTextProvider`. Verified by `make wave6-indic-preflight` (provider class + `IndicRecordedSpeechConfiguration` preflight).
- [x] **6.3** Staff hold-to-talk passed English 3/3, Kannada+English 3/3 and noisy-room 3/3. Runbook: [ops/WAVE6_INDIC_STT_STAFF_TESTS.md](ops/WAVE6_INDIC_STT_STAFF_TESTS.md).
- [x] **6.4** The 25-second cap behavior was observed.
- [x] **6.5** Sarvam failure produced the reviewed Azure failover or clean recovery path, not an HTTP 500.
- [x] **6.6** The live-AI 300-second demo meter was verified.
- [x] **6.7** Role-play Perform was verified for the Premium staff path.
- [x] **6.8** Azure OpenAI chat and Azure guidance TTS were verified still active.
- [x] **6.9** Content-free `voice_provider_runs` evidence recorded 50 successful
  Indic STT runs and 13 successful Azure fallbacks. Recorded STT still uses
  `voice_provider_runs`, not `ai_cost_ledger`.

**Exit:** complete for controlled staff verification under D-0276. Evidence:
[Wave 6 result](evidence/wave6/wave6-indic-stt-staff-test-result.json) and
`make wave6-indic-preflight` 24 pass / 0 warn / 0 fail. Stable hosting,
DR-0048 and production authorization remain open.

---

## Wave 7 — Product holes that block “this is the app”

Recorded 1 September 2026 on this host. Dummy checkout unchanged. Stable host still missing.

- [x] **7.1** Publish Direct Levels 1–17: `lesson-practice:publish-direct-pathway --approval=WAVE-7.1`. 17 latest blueprints `published` and `publication.eligible=true`; prior drafts retained. Replay skipped 17. Evidence `docs/evidence/lpl-01/wave71-direct-pathway-publish-result.json`. Learn already served attached media; this is the publish action, not a comment.
- [x] **7.2** Progress tab is `ProgressDashboard` (can-do / streak / due review), not the Learn level path.
- [x] **7.3** Home ranks due review above the next mission (`LearnerJourney`); streak is visible.
- [x] **7.4** Direct default locale is `kn-IN`. Soft-launch web is translation-preview (`VITE_UI_TRANSLATION_PREVIEW=true`) with an honest pending-review notice. Independent review is still pending; no production or signed release build may use this unreviewed preview.
- [x] **7.5** Type-instead remains on Speak (`conversation.confidence.type_instead`) and Learn continue-without-recording.
- [ ] **7.6** Operator enroll / grant / revoke / GST / privacy export-erasure exist on the **current** origin. Not re-verified on `ai.globalspokenenglish.com` (Wave 5 still blocked).

**Exit:** code and live publish for 7.1 are done. The adult journey on the **stable** host is still Wave 5.

---

## Wave 8 — Real money

Skip only while the product remains a controlled invite-only staff/closed-beta
lane with courtesy Premium and an honest non-purchasable UI. A paid or publicly
advertised production offer requires this wave.

- [ ] **8.1** Ship Razorpay (or chosen) adapter (Wave 2.9) to this host.
- [ ] **8.2** Real GSTIN saved on operator tax page. Checkout snapshot uses it.
- [ ] **8.3** Test-mode payment: demo user upgrades to Premium; entitlement active; Role-play unlocks; GST line visible.
- [ ] **8.4** Replay webhook with same id: no double grant.
- [ ] **8.5** Failed payment: no entitlement. User sees a non-technical error.
- [ ] **8.6** Switch live keys. One real ₹1 or smallest charge to a staff card, then refund. Confirm ledger/receipt without storing PAN.
- [ ] **8.7** Modules ₹350 vs Premium ₹500: Role-play locked on Modules, open on Premium.
- [ ] **8.8** Demo 48h + 300s still enforced for new sign-ups.

**Exit:** a stranger can pay and get Premium without you touching the database.

---

## Wave 9 — Human UAT (not a screenshot)

- [ ] **9.0** Start only after the complete required Wave 2B speech route is
  implemented and evidenced for controlled testing, Wave 5 stable HTTPS is
  live and Wave 8 is complete for any payment journey.
- [ ] **9.1** At least 20 consented adults, representative Karnataka speech
  patterns, two cheap Androids, noisy/quiet rooms, Wi-Fi, Jio and Airtel.
- [ ] **9.2** Journeys: sign-in, Learn audio, English Practice, a Kannada question
  receiving one bridge then returning to English, explicit Kannada Help,
  hold-to-talk/realtime downgrade, Role-play (Premium), pay or courtesy,
  sign-out and reconnect (kill app, come back).
- [ ] **9.3** Android: in-app password, refresh still works, uninstall clears session, no Chrome Custom Tab for password.
- [ ] **9.4** Web PWA: same origin, cookie session, mic permission.
- [ ] **9.5** Record only **content-free** pass/fail, selected route, device/network,
  measured response-signal/first-audio latency and participant rating. No audio,
  transcript, reply text or learner identifier in the results sheet.
- [ ] **9.6** If fewer than 18/20 finish one Speak turn, Kannada Help violates its
  mode boundary, or speech quality/latency/subgroup gates fail: **not live**.
  Fix and rerun before Play public/production.
- [ ] **9.7** Human TalkBack, screen-reader, system-font, bilingual wording and
  cancellation/consent-withdrawal checks pass on the release candidate.

**Exit:** 18/20 complete Speak; Kannada Help and the selected provider fallback
behave as approved; accessibility and content-free latency evidence pass; pay
path is proven or the lane remains explicitly non-purchasable and closed.

---

## Wave 10 — Play closed test

- [ ] **10.1** Signed **AAB** (`make android-release-bundle` or current release target), Play App Signing, API 36.
- [ ] **10.2** Store listing: Swathi, Kannada-first, no fluency guarantees, no SuperNova trademarks.
- [ ] **10.3** Data safety, consent and privacy policy name the microphone, audio/
  transcript/model/TTS processing and every enabled Azure, Sarvam or ElevenLabs
  product that can receive content; include account-deletion and privacy URLs.
- [ ] **10.4** Closed testing: 12 opted-in testers × 14 days if the Play account requires it.
- [ ] **10.5** Every closed-test APK uses the **stable** HTTPS origin from Wave 5.

**Exit:** closed track is live under the approved testing boundary; testers
install from Play, not a WhatsApp APK. This is not Play production/public-listing
authority.

---

## Wave 11 — First public week (ops)

- [ ] **11.1** Daily: Speech Economics usage and invoice variance by stage/provider,
  dispatch/cache/retry/fallback counts, success rate, p50/p95 first response
  signal and first audible audio, sample size, payment failures, 5xx and spend
  versus caps.
- [ ] **11.2** Kill switches rehearsed independently for every selected STT,
  model and TTS route, plus voice upload, payments and fallback; deletion and
  reconciliation workers continue after new dispatch is disabled.
- [ ] **11.3** Support: how a learner resets password; how you grant Premium; how you revoke. No DB as the only tool.
- [ ] **11.4** Do not make an unbenchmarked provider/model/voice route change,
  start a new L1 speech pack, or put a heavy model on every turn. ElevenLabs may
  run only if SPEECH-PROD-01 selected that exact configuration; otherwise it
  remains disabled or benchmark-only.

**Exit:** seven days without a Sev-1 (auth down, data corruption, Speak 500 for all).

---

## Wave 12 — True `APP_ENV=production` (only after 2B)

- [ ] **12.1** Every required Wave 2.8–2.19 item is merged and evidenced on a
  production-like staging clone; SPEECH-PROD-01, Waves 5/8/9/10 and the seven-
  day operational gate are complete.
- [ ] **12.2** PostgreSQL constraints, server-owned route/configuration hashes,
  consent revisions, rate cards, retention/deletion workers, spend caps and
  kill switches allow only the exact evidence-selected production providers.
- [ ] **12.3** Rehearse migration, backup/restore, canary, provider/quota failure,
  deletion and one-command rollback; then obtain explicit Accountable Product
  Owner release approval before the maintenance-window cut to `production`.
- [ ] **12.4** Confirm Speak English Practice, Kannada Help, selected fallback,
  pay, Learn, Speech Economics and deletion on the canary. If any gate fails,
  roll back within the approved window without disabling deletion work.

**Exit:** the production environment serves the exact approved journeys and
provider configurations with recorded release evidence; no environment rename
is treated as proof by itself.

---

## Wave 13 — After it is boring (India #1)

Do not start because you are excited. Start because Wave 11 stayed green.

- [ ] **13.1** Continue latency optimization only after the pre-production
  validated-reply/streamed-TTS path is stable. Speaking from incomplete model
  generation remains a separate ADR/evaluation checkpoint; never stream raw
  unvalidated tokens.
- [ ] **13.2** Heavy Model router + daily cap.
- [ ] **13.3** Re-run the frozen Azure/Sarvam/ElevenLabs provider suite when
  model/configuration, contract, price or measured quality/latency drifts; do
  not silently replace the evidence-selected voice or route.
- [ ] **13.4** Licensed Swathi voice clone for AI talk only after a separate
  rights, consent, privacy, impersonation-safety and provider-retention decision
  (lessons stay institute MP3s). It is not part of DR-0048.
- [ ] **13.5** Advisory pronunciation on Practice only.
- [ ] **13.6** Notifications: streak, due review, demo ending.
- [ ] **13.7** More Role-play scenes (same engine).
- [ ] **13.8** Language-pack factory documented; first extra language only when Kannada eval is green (see plan §4 P4).
- [ ] **13.9** Play production track/public listing only after Waves 10–12,
  current Play review requirements and a separate explicit release approval.
- [ ] **13.10** Capacitor iOS only after Android is taking money.

---

## Do not do (until the wave that names it)

| Temptation | When it is allowed |
|---|---|
| Public/paid learner release, Play production listing or `APP_ENV=production` | Only after complete Wave 2B/DR-0048 evidence and explicit release approval; `APP_ENV=production` is performed in Wave 12 |
| Change or disable an Azure/Sarvam/ElevenLabs production route | Only through SPEECH-BAKEOFF-01/SPEECH-PROD-01 evidence with a reviewed fallback and rollback |
| Any STT/TTS route with no reviewed speech-provider fallback | Never |
| ElevenLabs default | Only if the exact Scribe/v3 configuration wins the quality/privacy/latency/effective-cost gate; otherwise disabled or benchmark-only |
| Three-way processing of ordinary learner audio | Never; controlled comparison uses synthetic, licensed or separately consented material |
| Stream incomplete model tokens to speech | Only after a separate accepted incremental-envelope ADR and safety evaluation; validated-reply TTS streaming is required in 2.15 |
| GPT-4o every turn | Never; router only |
| Flutter rewrite | Not in this programme |
| 22-language UI as “Speak in Tamil” | Wave 13.8 + eval pack |
| Old website password import | Never |
| Teacher name Nova | Never |
| Full-duplex WebRTC | Only after production stability, as a Premium experiment under a separate privacy/safety/transport ADR and evaluation |

---

## Dependency calendar (re-baselined 9 September 2026)

Do not promise dates until legal/provider evidence, independent bilingual review
and test participants are available. Sequence by exits:

| Order | Work |
|---|---|
| Evidence already complete | Waves 1, 2A, 4 and 6 staff verification; Wave 7.1 publication |
| Next, in parallel where authority permits | Wave 5 stable HTTPS, Wave 8 payments, vendor account/privacy work in Wave 3, and 2.11 SPEECH-GOV-01 |
| Strict speech sequence | 2.12 OBS → 2.13 PROVIDER → 2.14 ADMIN → 2.15 LAT → 2.16 RT → 2.17 BAKEOFF → 2.18 KN-HELP → 2.19 PROD |
| After the complete 2B gate | Wave 9 adult UAT and Wave 10 Play closed test |
| After explicit release approval | Wave 11 controlled first public week, then Wave 12 environment cutover |
| After stable operations | Wave 13 revalidation and separately governed enhancements |

---

## First action tomorrow morning

1. Close the earliest open speech acceptance gate, SPEECH-OBS-01, then
   PROVIDER, ADMIN, LAT and RT in order. D-0316 corrected premature ADMIN/LAT/RT
   ticks; their implementation slices remain, but required measured/live
   evidence does not. SPEECH-BAKEOFF-01 is not yet authorized.
2. In parallel, finish the Wave 5 stable HTTPS prerequisites and Wave 8 payment
   decision without stopping the working tunnel prematurely. Renew APP-0064
   before **2026-09-14**.
3. Before live deployment or any HTTP-enablement approval: rotate the disclosed
   ElevenLabs key, record a deletion-test receipt, then authorize HTTP
   separately. Do not flip live Speak off Indic + Azure until SPEECH-BAKEOFF-01.
4. Wave 6 staff Indic verification is already complete under D-0276; do not
   repeat it as a substitute for the new three-provider production gate.
5. Do not expose a public/paid learner release, Play production listing or set
   `APP_ENV=production` before 2.19 and explicit release approval.
