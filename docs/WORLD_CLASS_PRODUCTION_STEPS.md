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

- [ ] **2.1** **Utterance length:** cap hold-to-talk at ~25 seconds **or** chunk/stream to Sarvam (REST ~30s). Current max is 120s. If you skip this, long holds fail with no useful error.
- [ ] **2.2** **Cost ledger CHECK:** allow `provider_key = indic-http-stt` (and the model-version prefix you will actually write). Same class of bug as `azure-speech-guidance` (HTTP 500, turn rolled back).
- [ ] **2.3** **Usage policy:** `ConversationProviderUsagePolicy` must allow Indic STT stage/key in soft-launch (and later production).
- [ ] **2.4** **Failover:** if Sarvam times out / 5xx, retry once on Azure Fast Transcription. If you skip this, one Sarvam outage kills Speak.
- [ ] **2.5** **Secrets:** `VOICE_INDIC_SECRET_FILE` mode `0600` outside git; never commit the key. Host allowlist `api.sarvam.ai` only.
- [ ] **2.6** **Consent copy:** voice notice names Sarvam as transcription processor (en-IN + kn-IN). Update `config/voice.php` notice revision.
- [ ] **2.7** Tests: HTTP fake for Sarvam translate; ledger constraint test; failover test; 25s cap or chunk test. PostgreSQL integration on CI or Ubuntu.

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

**Exit for 2A:** merge + deploy to **soft-launch**. Speak still on Azure until Wave 6.  
**Exit for 2B:** not required for first public URL; required before renaming the env to `production`.

---

## Wave 3 — Accounts, vendors, spend (parallel with Wave 2)

- [ ] **3.1** Azure OpenAI: confirm South India deployment still serves; key in `~/ge-conversation-model/secrets/azure-openai.secret`; live kill switch understood (`CONVERSATION_AZURE_OPENAI_LIVE_REQUESTS_ENABLED` or current equivalent).
- [ ] **3.2** Azure Speech: keep for TTS + STT failover. Confirm Central India resource, key file, TTS voices `en-IN-NeerjaNeural` + `kn-IN-SapnaNeural`.
- [ ] **3.3** Sarvam: create project, billing alert, DPA/no-training, API key file `~/ge-speech/secrets/sarvam.secret` (or similar, mode 0600). Note monthly INR cap: `________________`.
- [ ] **3.4** Razorpay/Cashfree: account, GSTIN, webhook secret, test vs live keys. Webhook URL will be `https://<host>/api/v1/...` (exact path after adapter exists).
- [ ] **3.5** Google / Facebook (optional): production client IDs, redirect URIs on the **stable** origin. Do not enable until Wave 5 origin is frozen.
- [ ] **3.6** Play Console: app `com.globalenglish.app`, signing key custody, Data safety draft (mic, audio, Sarvam, Azure).
- [ ] **3.7** Spend caps written: Azure chat ₹/day `____`, Azure speech ₹/day `____`, Sarvam ₹/day `____`. Who gets the SMS when hit: `________________`.

**Exit:** every secret exists on the Ubuntu host at mode 0600. None in git. Caps written.

---

## Wave 4 — Backup, observe, rollback

- [ ] **4.1** `make soft-launch-backup`. Store dump off-box (encrypted). Test restore once if not done recently (`docs/ops/BACKUP_RESTORE_DRILL.md`).
- [ ] **4.2** Confirm queue worker + scheduler are running (`systemctl --user status global-english-soft-launch.service`).
- [ ] **4.3** Health: `make soft-launch-health` and `make soft-launch-ready`.
- [ ] **4.4** Write rollback: previous APK path, previous git SHA, “set `VOICE_PROVIDER_DRIVER=azure`” as STT rollback.
- [ ] **4.5** Alerts: at least “API down”, “queue stuck”, “provider 5xx rate”. Even a Telegram/email to you is better than nothing. See `docs/ops/INCIDENT_SUPPORT_RUNBOOK.md`. **Never paste transcripts or keys into chat.**

**Exit:** you can restore yesterday’s DB and roll STT back with one env change.

---

## Wave 5 — Stable public host (stop the tunnel)

- [ ] **5.1** Cloudflare DNS: `A` record for `ai.globalspokenenglish.com` (or chosen host) → the Ubuntu public IPv4. No chat-paste of Cloudflare tokens.
- [ ] **5.2** Router: 443/80 to the box as the runbook requires.
- [ ] **5.3** `make soft-launch-configure-stable HOST=ai.globalspokenenglish.com` (or the real host).
- [ ] **5.4** Caddy TLS + HTTPS redirect + HSTS. Stop Cloudflare Quick Tunnel (`make soft-launch-tunnel-stop`).
- [ ] **5.5** `APP_URL` and Sanctum/session origin = that HTTPS origin. Trusted proxies if behind Cloudflare.
- [ ] **5.6** CORS / cookie `Secure` / `SameSite` work on that origin. Sign in on a phone browser (not only laptop).
- [ ] **5.7** Rebuild Android:

  ```text
  export VITE_MOBILE_API_ORIGIN='https://ai.globalspokenenglish.com'
  make android-closed-beta
  ```

  Update `network_security_config.xml` if you still need any cleartext (prefer none).
- [ ] **5.8** Sideload the new APK. Confirm it does **not** open the old tunnel host. Uninstall old APKs.
- [ ] **5.9** Publish a one-line privacy policy + account-deletion page on that origin (Play needs a public URL). Wire in-app deletion if not already.

**Exit:** Jio phone, not on your Wi-Fi, opens the HTTPS site, signs in, and the new APK talks to the same host.

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

1. Tick Wave 0.  
2. Backup + deploy uncommitted entitlements (Wave 1).  
3. Start Wave 2.1–2.6 (clip cap, ledger, failover, Sarvam secret, consent).  
4. Do **not** flip `VOICE_PROVIDER_DRIVER=indic` until 2A is deployed.  
5. Do **not** set `APP_ENV=production`.
