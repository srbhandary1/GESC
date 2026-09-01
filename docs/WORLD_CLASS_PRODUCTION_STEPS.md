# World-class production — steps to follow

**Companion to** [WORLD_CLASS_PRODUCTION_PLAN.md](WORLD_CLASS_PRODUCTION_PLAN.md).  
**How to use:** tick boxes in order. Do not start a later wave until the previous wave’s **Exit** is true. One owner per step; if two people are needed, the step says so.

**Hard rule:** do **not** set `APP_ENV=production` on day one. Today that flag **turns Speak, Azure chat, TTS, and payments off**. First public go-live is a **stable-host soft-launch**. True `production` is Wave 12, after the fail-closed bindings are rewritten.

---

## Wave 0 — Decide and freeze (day 0)

Recorded 1 September 2026 from APO answers in the active session.

- [x] **0.1** Accept this sequence (Sarvam STT + Azure Turn Model + Azure TTS; no Flutter; no ElevenLabs default).
- [x] **0.2** Freeze new features except the waves below. No Notice Board, Flutter, Miss Nova, old-site import, or 22-language Speak.
- [x] **0.3** Go-live host: `https://ai.globalspokenenglish.com`
- [x] **0.4** Payment: **keep dummy checkout** through the first public week; choose Razorpay/Cashfree in Wave 8 later.
- [x] **0.5** Sarvam key is on this workstation (not in git). File: `$HOME/ge-indic-stt/secrets/sarvam.secret` (mode `0600`, outside the repository). Do **not** set `VOICE_PROVIDER_DRIVER=indic` until Wave 6. **Rotate this key in the Sarvam dashboard** — it was pasted in chat.
- [x] **0.6** Do **not** import `globalspokenenglish.com` passwords.
- [x] **0.7** UAT: **staff-only until testers exist.** Play public waits until ~20 adults can be recruited (Wave 9). Closed-group staff testing can still proceed.

**Exit:** hostname, payment vendor, and “no `APP_ENV=production` yet” are written down. **Wave 0 complete.** Next is Wave 1.

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

These are **missing in the current app**. Flipping env vars without them will 500 Speak or silently disable it.

### 2A — Must have for Indic STT go-live

Completed 1 September 2026 on this host. Speak remains on Azure. Indic is not enabled.

- [x] **2.1** **Utterance length:** hold-to-talk auto-stops at 25 seconds. Server `voice.maximum_duration_ms` is 25_000. Learn tap-to-record stays 120 seconds (`learning.capture.maximum_duration_ms`).
- [x] **2.2** **Cost ledger CHECK:** migration `2026_09_01_000300_allow_indic_stt_cost_ledger` allows `indic-http-stt` (`indic-http-stt-v1:`) and `azure-speech-soft-launch-stt`.
- [x] **2.3** **Usage policy:** `ConversationProviderUsagePolicy` allows those STT keys under soft-launch `azure-openai-dev`.
- [x] **2.4** **Failover:** `IndicWithAzureFailoverSpeechToTextProvider` retries once on Azure Fast Transcription after Indic HTTP/connection/transport failure. Not for malformed/unauthorized/input errors.
- [x] **2.5** **Secrets:** documented in `apps/api/.env.example` comments (`$HOME/ge-indic-stt/secrets/sarvam.secret`, `api.sarvam.ai`). Driver not flipped.
- [x] **2.6** **Consent copy:** `config/voice.php` en-IN + kn-IN name Sarvam then Azure failover. Revision `voice-processing-notice-2026-09-01`.
- [x] **2.7** Tests: Indic HTTP fake; ledger constraint test; failover tests; 25s inspector and hold auto-stop. PostgreSQL CHECK: docker `php:8.4-cli-bookworm` + `pdo_pgsql` applied `2026_09_01_000300_allow_indic_stt_cost_ledger` and `IndicSttCostLedgerConstraintTest` 1/1.

### 2B — Must have before `APP_ENV=production` (Wave 12, not now)

Do **not** do Wave 12 until all of these are coded. Today:

| Binding | What production does today |
|---|---|
| Conversation | `ConversationEnvironmentGate` refuses live Azure |
| Chat / TTS / STT DI | `AppServiceProvider` only live in non-production; Indic only local/testing/soft-launch |
| Payments | `PaymentProvider` is always `DisabledPaymentProvider` in production |

- [ ] **2.8** Allow live Azure Turn Model + guidance TTS + Indic STT in production **with** spend cap + kill switch (rewrite gate + DI).
- [ ] **2.9** Razorpay (or chosen) adapter behind `PaymentProvider`. Production must not stay dummy. Dummy only for staff/`local`.
- [ ] **2.10** Heavy Model router (optional for first public week; required before you claim “all LLMs”). Same JSON contract. Daily INR ceiling.

**Exit for 2A:** code merged on this branch; CHECK migrated on the soft-launch DB. Speak still on Azure until Wave 6.  
**Exit for 2B:** not required for first public URL; required before renaming the env to `production`.

---

## Wave 3 — Accounts, vendors, spend (parallel with Wave 2)

Host inventory recorded 1 September 2026 on this Ubuntu workstation. No secret values, transcripts, or learner data were copied into git or chat. Indic STT remains off. `APP_ENV` remains `soft-launch`.

- [x] **3.1** Azure OpenAI South India is the live Turn Model. Host `ge-conversation-southindia.openai.azure.com` returned HTTP 200 over TLS at 2026-09-01T16:04:14Z (no API key sent). Secret file `$HOME/ge-conversation-model/secrets/azure-openai.secret` is a regular file, owner uid 1000, mode `0600`, outside the repository. Soft-launch env: `CONVERSATION_PROVIDER_DRIVER=azure-openai-dev`, deployment `gpt-4-1-mini-southindia`, API version `2024-10-21`, **kill switch** `CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED` (currently `true`). Set that to `false` and restart the API/worker to stop chat spend. Authorized hard ceiling **₹500** (APP-0022).
- [x] **3.2** Azure Speech Central India is TTS and recorded-STT (live now, Indic failover later). Host `ge-speech-centralindia.cognitiveservices.azure.com` returned HTTP 200 over TLS at the same instant (no key sent). Secret `$HOME/ge-speech-benchmark/secrets/azure-speech.secret` mode `0600`, outside git. Voices remain `en-IN-NeerjaNeural` and `kn-IN-SapnaNeural` (`GuidanceTtsPolicy`). Kill switches: `CONVERSATION_GUIDANCE_TTS_LIVE_REQUESTS_ENABLED=false` stops TTS; `VOICE_UPLOAD_ENABLED=false` or `VOICE_PROVIDER_DRIVER=disabled` stops learner STT. Authorized hard ceiling **₹500** (APP-0064), shared with learner STT (APP-0068). APP-0064 spend-approval `valid_until` is **2026-09-14** — renew before then.
- [x] **3.3** Sarvam key on disk: `$HOME/ge-indic-stt/secrets/sarvam.secret` (mode `0600`, outside git). That Wave 0 path is canonical; `~/ge-speech/secrets/sarvam.secret` was **not** created (do not duplicate the key). `VOICE_INDIC_SECRET_FILE` is unset; `VOICE_PROVIDER_DRIVER=azure`. **Still APO dashboard work:** rotate the key (it was pasted in chat); billing alert; DPA/no-training; monthly INR cap (not invented here).
- [x] **3.4** Payments stay **dummy** through the first public week (Wave 0.4 / Wave 8). `PAYMENT_DRIVER` is unset → `DummyPaymentProvider`. No Razorpay/Cashfree keys. Real GSTIN remains the operator tax-page placeholder. Webhook path does not exist until Wave 2.9 / Wave 8.
- [x] **3.5** Google/Facebook client IDs are **unset**. Do not add production redirect URIs until Wave 5 freezes `https://ai.globalspokenenglish.com`.
- [x] **3.6** Android application id is `com.globalenglish.app`. Play Console, signing-key custody, and Data safety draft are **operator dashboard work** (Wave 10). Not opened in this session.
- [x] **3.7** Authorized hard ceilings written (not new daily SMS alerts): Azure chat **₹500** (APP-0022); Azure speech **₹500** (APP-0064, includes STT). Sarvam daily/monthly cap: **not set**. Named SMS recipient: **not set** — use Azure Cost Management email to the subscription owner until you name a phone.

**Exit (host):** Azure OpenAI, Azure Speech, and Sarvam secret files exist at mode `0600` outside git. Dummy checkout unchanged. **Not exit-complete for dashboard leftovers:** Sarvam rotate + DPA + monthly cap; named spend SMS; Play Console; Razorpay (Wave 8).

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

## Wave 6 — Indic STT live (Speak ears)

Only after Waves 1, 2A, 3, 4, 5.

- [ ] **6.1** Staging/soft-launch env (example; adjust paths):

  ```text
  VOICE_UPLOAD_ENABLED=true
  VOICE_PROVIDER_DRIVER=indic
  VOICE_ALLOWED_ORGANIZATION_IDS=<Direct org id>
  VOICE_INDIC_TRANSCRIBE_URL=https://api.sarvam.ai/speech-to-text
  VOICE_INDIC_ALLOWED_HOST=api.sarvam.ai
  VOICE_INDIC_SECRET_FILE=/home/.../sarvam.secret
  VOICE_INDIC_AUTH_HEADER=api-subscription-key
  VOICE_INDIC_MODEL=saaras:v3
  VOICE_INDIC_MODE=translate
  VOICE_INDIC_LANGUAGE_CODE=unknown
  VOICE_INDIC_OUTPUT_LANGUAGE=en-IN
  ```

- [ ] **6.2** Restart API + worker. Confirm DI is Indic (a safe log line: provider key only, no transcript).
- [ ] **6.3** Staff hold-to-talk: English only, then Kannada+English mix, then noisy room. Three successes each.
- [ ] **6.4** Confirm 25s (or chunk) UX: long hold is cut or split; user sees why.
- [ ] **6.5** Kill Sarvam (wrong key or firewall) once: Speak must failover to Azure or show a clean error, **not** HTTP 500.
- [ ] **6.6** Confirm live-AI 300s demo meter still increments and then denies.
- [ ] **6.7** Confirm Role-play Perform still works on Premium (courtesy-granted testers).
- [ ] **6.8** Azure OpenAI + Azure TTS still on. Do **not** disable Azure this wave.
- [ ] **6.9** Watch `ai_cost_ledger` for `indic-http-stt` rows. If insert fails, roll back to `VOICE_PROVIDER_DRIVER=azure` immediately.

**Exit:** mixed-language hold-to-talk works on the public host; failover tested; ledger writing; Azure chat/TTS still speaking.

---

## Wave 7 — Product holes that block “this is the app”

- [ ] **7.1** Publish Direct Levels 1–17 for entitled learners (lift `curriculum_publication_gate` with a real publish action, not a comment). Confirm Learn plays teaching audio.
- [ ] **7.2** Progress tab shows can-do / streak / due review, **not** the Learn level path.
- [ ] **7.3** Home: due review outranks a random lesson; streak visible.
- [ ] **7.4** Kannada UI: Direct default `kn-IN`. If independent review is still pending, ship it anyway with honest “draft” only if legally acceptable; do **not** show English chrome to a Kannada-only adult as the only path.
- [ ] **7.5** Type-instead still works when they refuse mic.
- [ ] **7.6** Operator: enroll, grant, revoke, GST, privacy export/erasure still work on the stable host.

**Exit:** one new adult can: sign in → Home → one Learn session → one Speak turn → see Progress.

---

## Wave 8 — Real money

Skip only if you explicitly stay invite-only with courtesy Premium and dummy checkout. Public listing without this is fraud.

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

- [ ] **9.1** 20 adults, two cheap Androids, noisy room, Jio and Airtel.
- [ ] **9.2** Journeys: sign-in, Learn audio, hold-to-talk, Role-play (Premium), pay or courtesy, sign-out, reconnect (kill app, come back).
- [ ] **9.3** Android: in-app password, refresh still works, uninstall clears session, no Chrome Custom Tab for password.
- [ ] **9.4** Web PWA: same origin, cookie session, mic permission.
- [ ] **9.5** Record **content-free** results: pass/fail per journey, latency felt, device model. No transcripts in the sheet.
- [ ] **9.6** If fewer than 18/20 finish one Speak turn: **not live**. Fix STT/TTS/UX before Play public.

**Exit:** 18/20 complete Speak; pay path proven or courtesy-only invite list still closed.

---

## Wave 10 — Play closed test

- [ ] **10.1** Signed **AAB** (`make android-release-bundle` or current release target), Play App Signing, API 36.
- [ ] **10.2** Store listing: Swathi, Kannada-first, no fluency guarantees, no SuperNova trademarks.
- [ ] **10.3** Data safety: microphone, audio uploaded, processed by Sarvam + Azure; account deletion URL; privacy policy URL.
- [ ] **10.4** Closed testing: 12 opted-in testers × 14 days if the Play account requires it.
- [ ] **10.5** Every closed-test APK uses the **stable** HTTPS origin from Wave 5.

**Exit:** closed track is live; testers install from Play, not a WhatsApp APK.

---

## Wave 11 — First public week (ops)

- [ ] **11.1** Daily: Speak success rate, STT failover count, payment failures, 5xx, spend vs caps.
- [ ] **11.2** Kill switches rehearsed: voice upload off, conversation live-requests off, payments disabled.
- [ ] **11.3** Support: how a learner resets password; how you grant Premium; how you revoke. No DB as the only tool.
- [ ] **11.4** Do **not** start Tamil/Hindi Speak L1, ElevenLabs, or GPT-4o-on-every-turn this week.

**Exit:** seven days without a Sev-1 (auth down, data corruption, Speak 500 for all).

---

## Wave 12 — True `APP_ENV=production` (only after 2B)

- [ ] **12.1** Wave 2.8–2.10 merged and tested on a staging clone.
- [ ] **12.2** PostgreSQL CHECKs allow production + live provider keys you will use.
- [ ] **12.3** Cut env to `production` in a maintenance window. Confirm Speak, pay, Learn still work.
- [ ] **12.4** If anything fail-closes, roll back env to `soft-launch` the same hour.

**Exit:** production env serves the same journeys as the public soft-launch.

---

## Wave 13 — After it is boring (India #1)

Do not start because you are excited. Start because Wave 11 stayed green.

- [ ] **13.1** Streamed TTS (first sentence while the rest generates).
- [ ] **13.2** Heavy Model router + daily cap.
- [ ] **13.3** TTS bake-off: Azure Sapna vs Sarvam Bulbul (± ElevenLabs as luxury only).
- [ ] **13.4** Licensed Swathi voice clone for AI talk (lessons stay institute MP3s).
- [ ] **13.5** Advisory pronunciation on Practice only.
- [ ] **13.6** Notifications: streak, due review, demo ending.
- [ ] **13.7** More Role-play scenes (same engine).
- [ ] **13.8** Language-pack factory documented; first extra language only when Kannada eval is green (see plan §4 P4).
- [ ] **13.9** Play production track / public listing when Wave 10 closed test completes.
- [ ] **13.10** Capacitor iOS only after Android is taking money.

---

## Do not do (until the wave that names it)

| Temptation | When it is allowed |
|---|---|
| `APP_ENV=production` | Wave 12 |
| Disable Azure OpenAI or Azure TTS | Never at go-live; TTS bake-off is Wave 13 |
| Disable Azure STT with no failover | Never |
| ElevenLabs default | After bake-off, Premium-only if COGS works |
| GPT-4o every turn | Never; router only |
| Flutter rewrite | Not in this programme |
| 22-language UI as “Speak in Tamil” | Wave 13.8 + eval pack |
| Old website password import | Never |
| Teacher name Nova | Never |
| Full-duplex WebRTC | After money, as Premium experiment |

---

## Suggested calendar (if you start tomorrow)

| When | Waves |
|---|---|
| Day 0 | 0 |
| Days 1–3 | 1, 3, 4 in parallel; start 2A |
| Days 2–7 | Finish 2A, 5 (DNS + APK) |
| Days 5–10 | 6 Indic STT |
| Days 7–14 | 7 product holes, 8 payments if adapter ready |
| Days 10–21 | 9 UAT, 10 Play closed |
| Week 4+ | 11, then 12, then 13 |

---

## First action tomorrow morning

1. Wave 0–4 are done. Wave 5 **legal pages** are in the tree; **DNS and router are not**.  
2. APO: in Cloudflare, grey-cloud `ai` A record to this box’s public IPv4. On the router, stop WAN 80 serving the login page; forward 80→`192.168.1.4:8080` and 443→`192.168.1.4:8443`.  
3. Then engineering: `make soft-launch-stable-prereq` must pass, then 5.3–5.8.  
4. Rotate the Sarvam key; copy the encrypted backup off this NVMe.  
5. Do **not** set `APP_ENV=production`. Do not stop the Quick Tunnel until 5.3.
