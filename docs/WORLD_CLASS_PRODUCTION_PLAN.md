# World-class production plan — Global Spoken English

**Status:** Target production architecture with gated delivery. Originally
written 1 September 2026 as an unconstrained end-state design; the 9 September
pre-production amendment below controls sequencing. This plan does not override
the master plan, accepted ADRs, fail-closed controls or checkpoint authority.

**Pre-production amendment — 9 September 2026:** The Accountable Product Owner
directed that every recommendation in
[DR-0048](DR-0048_MULTIPROVIDER_SPEECH_OBSERVABILITY_AND_LATENCY_REQUEST.md)
be planned and completed before production: provider-neutral Azure/Sarvam/
ElevenLabs speech adapters, complete usage and versioned cost evidence, the
scoped Speech Economics operator view, streaming/parallel latency work, a
controlled three-provider bake-off, evidence-based routing and Kannada Help.
This records sequencing; it does not authorize credentials, provider spend,
ordinary learner-audio shadowing or production.

**This file does not amend** `ENTERPRISE_PRODUCT_MASTER_PLAN.md` until you explicitly accept it. Until then it is the honest target, not the legal baseline.

In particular, planning Kannada Help here does not silently change English as
the learning language. Its implementation requires an accepted ADR and an
explicit master-plan amendment defining the bounded Kannada-reply behavior.

**Follow-along sequence (do these in order):**
[WORLD_CLASS_PRODUCTION_STEPS.md](WORLD_CLASS_PRODUCTION_STEPS.md). Use that file as the working checklist. This file stays the *why*.

**Product to beat:** SuperNova Nova AI (vernacular hold-to-talk at Indian price), plus the actual world bar: Duolingo (habit), ELSA (pronunciation), Speak/Loora (conversation quality), Praktika (character immersion). Copying SuperNova’s vendor list is how you become a slower SuperNova. The win is **their ears + this institute’s school + a faster mouth + a smarter brain**.

---

## 0. Honest verdict

You are close to an **India go-live**, not to “world’s top app.” Those are different distances.

| Ambition | Honest time | What it actually means |
|---|---|---|
| **India production** (paying Kannada adults, Speak that hears and answers them appropriately, 17 levels, Android + web) | **After the DR-0048 gates pass; do not retain the former 2–4 week promise** | Stable HTTPS/payments/pathway plus measured speech economics, streaming first audio, three-provider bake-off, Kannada Help, consent/privacy evidence and Play closed-track proof |
| **Best spoken-English app for Indian adults** | **90 days** after that go-live | Habit loop, continuous provider revalidation, Swathi-quality Kannada, pronunciation as advice, language-pack factory ready |
| **World’s top language tutor** | **12–24 months** | 22-language packs with speech + curriculum + eval, pronunciation lab, iOS, duplex premium, content machine that ships weekly |

SuperNova already has a crore of learners and a ₹499-class IAP. They won distribution and Indic listening. They did **not** win a real school. You have 17 levels of institute audio, deterministic marks, bilingual review, and a path to human 1:1. That is the durable advantage. Complete the measured speech path and production gates before making the school public. Do not spend that time rewriting Flutter or moving product authority into a speech vendor.

If you try to ship 22 languages, duplex WebRTC, and a new LLM on day one, you will miss go-live and still not beat SuperNova.

---

## 1. What “best in the world” actually is

A world-class spoken-English app for India does five things better than anyone else. Ranked.

1. **Hear the learner.** Kannada, Hinglish, Tamil-English, nervous quiet speech, cheap mics, fans, traffic. If STT is English-only Azure, SuperNova already beat you.
2. **Answer in under a second of felt time.** Hold-to-talk is correct for cheap Android and bad networks. The crime is waiting for the full WAV → STT → LLM → TTS → download before the mouth moves. Stream the first audio.
3. **Teach, don’t chat.** SuperNova is a conversational gym. You must be a **course**: today’s mission, due review, unlock, evidence, a teacher named Swathi, 67 real lesson recordings. Marks stay human-rules. AI never writes them.
4. **Mother-tongue coaching that is not machine-translated mush.** First production is Kannada as the gold language. Other Indian languages are a **factory** after Kannada is independently good, not 22 Google-translate skins.
5. **Know what each successful turn costs.** Measure STT minutes, model tokens,
   TTS characters/audio duration, retries, failures and invoice-reconciled INR.
   Choose quality-first routing using cost per successful usable turn; do not
   assume either that ElevenLabs is unaffordable or that a cheaper list price is
   economical.

Everything else (notice board, Flutter, attendance %, Miss Nova, scraping the old website) is how also-rans waste a quarter.

---

## 2. Target production stack

Keep the Laravel modular monolith and the React PWA + Capacitor Android. Break the **provider wiring**, not the product.

### 2.1 Voice and intelligence (production target)

Production uses one server-derived primary and one reviewed fallback per stage.
The table is the starting benchmark hypothesis, not a vendor promise or a
client-selectable router.

| Job | Baseline / primary hypothesis | Required challenger | Fallback |
|---|---|---|---|
| **English-practice and code-mixed STT** | Sarvam Saaras realtime, using the reviewed profile for that mode | ElevenLabs Scribe v2 Realtime | Azure Speech, with the locale/profile explicitly configured |
| **Kannada Help STT** | Sarvam `transcribe` or `codemix`, preserving original Kannada/code-mixed text | ElevenLabs Scribe v2 Realtime | Azure Speech `kn-IN` |
| **Detected input language** | Persist provider language and confidence as advisory metadata; preserve the original transcript | Compare provider calibration in the bake-off | Server-derived session/UI default only as an explicit recovery state |
| **Conversation LLM** | Current project-owned Azure OpenAI Turn Model | Separately approved latency/quality challenger only if the measured model stage misses its gate | Curated schema-valid safe fallback |
| **English TTS** | Azure `en-IN-NeerjaNeural` streaming baseline | ElevenLabs v3 Conversational and Sarvam Bulbul v3 | The second reviewed speech provider selected by the bake-off; text/replay is a separate UX recovery path |
| **Conversational Kannada TTS** | ElevenLabs v3 Conversational quality hypothesis | Sarvam Bulbul v3 streaming | Azure `kn-IN-SapnaNeural` |
| **Slow instructional Kannada TTS** | Sarvam Bulbul v3 or Azure, chosen by beginner listener evidence | ElevenLabs v3 Conversational | The other reviewed Sarvam/Azure speech route; visible text/replay is a separate UX recovery path |
| **Pronunciation (not go-live)** | Advisory scorer after production stability and a separate evidence checkpoint | Provider-neutral benchmark | Off |

ElevenLabs Multilingual v2 and Flash v2.5 do not currently list Kannada. The
Kannada candidate is `eleven_v3_conversational`. Do not enable a provider merely
because it is integrated: complete the controlled bake-off, privacy gates,
usage/cost ledger and operator dashboard first.

Do **not** switch ears, mouth and brain at once. Add adapters behind the current
ports, retain the Azure baseline, route one ordinary learner operation to one
provider, and promote candidates only from measured evidence.

### 2.2 What to keep (do not break)

- Username/password + optional Google/Facebook for already-enrolled users.
- Sanctum web, Passport family on Android, no tokens in `localStorage`.
- Deterministic mastery, delayed review, meaningful-speaking streak.
- AI advisory-only. No AI marks, unlocks, or attendance.
- Hold-to-talk as the default. Full-duplex is a **premium later**, not go-live.
- Owned lesson MP3s. Never scrape `globalspokenenglish.com`.
- Teacher name **Swathi**. Not Nova.
- Direct ₹0 demo (48h, Level 1–2, 300s live-AI) / ₹350 Modules / ₹500 Premium.
- Institutional learners may be unmetered for learner entitlement, but every
  provider operation is still metered for usage, reliability and cost.

### 2.3 What to break (do this)

| Break | Replace with |
|---|---|
| One fixed live STT path | Server-derived profiles and measured routing across Sarvam, ElevenLabs and Azure, with exactly one primary call plus bounded fallback per ordinary learner operation |
| `CONVERSATION_PROVIDER_DRIVER=disabled` in production | Live Turn Model in production with spend caps, kill switch, and schema repair |
| Dummy checkout as the public path | Razorpay (or Cashfree) + real GSTIN; keep dummy only for staff |
| `curriculum_publication_gate` blocking Levels 2–17 | Publish the reviewed Direct pathway for paying learners |
| Progress tab still showing the Learn path | Real Progress: can-do, streak, due review, recent improvement |
| Cloudflare Quick Tunnel as the product host | `https://ai.globalspokenenglish.com` (or one stable HTTPS) |
| Kannada UI `pending-independent-review` blocking production chrome | Complete exact-revision independent bilingual review before production; preview catalogues remain limited to clearly marked controlled review builds |
| Completed-WAV upload followed by completed REST STT | Keep explicit learner turn submission, but benchmark realtime streaming/endpointing under the required ADR and retain the current bounded recorded fallback |
| Complete JSON → complete WAV → optional Kannada WAV before any playback | Validate a short first reply, stream its first audio immediately, and produce optional guidance in parallel/background |
| Provider-dependent unit fields and estimates with no admin comparison | Versioned rate cards, explicit billable units/currency, invoice reconciliation and an organization-scoped Speech Economics dashboard |
| Translate-only STT and English-only reply contract | Mode-specific recognition plus a locale-aware reply contract for Kannada Help; keep English Practice English-first |
| Single LLM for every token | Router: Turn Model always; Heavy Model on failure/low confidence; L1 specialist only if eval demands it |
| Play debug APK as “the app” | Signed AAB, Play closed testing, then production track |
| Courtesy Premium not applied on the live DB | Grant the 1-month Premium on go-live so existing Direct learners are not stuck on demo |

---

## 3. LLM design (multiple models, one contract)

The product mistake is “pick one LLM.” The SuperNova mistake is “GPT-4o for everything.” The world-class pattern is a **strict output contract** and a **router**.

### 3.1 One contract per boundary, many providers

Every speech or model adapter emits its project-owned, versioned boundary
contract; provider SDK types never become domain contracts.

The STT result contract supplies `transcript_original`, detected locale and
confidence as provider-neutral orchestration inputs. An English translation may
be additive, but it must not replace native/code-mixed learner evidence or be
used for grammar correction. Original text exists only within the approved
consent and ADR-0007 retention boundary and never enters economics telemetry.

Every conversation model emits the same validated output contract. English
Practice remains English-first; Kannada Help extends reply behavior without
changing marks or progression:

- `assistant_reply: { text, locale }` is the single canonical validated spoken
  reply;
- reply locale is derived from the validated server-side session mode and
  intent, never from detected input language alone;
- during migration, legacy `assistant_reply_en` may exist only when the
  canonical locale is `en-IN`, must equal `assistant_reply.text` exactly, and
  is then retired rather than becoming a second authored source of truth;
- `english_bridge_en` is optional in explicit Kannada Help;
- `priority_correction` remains optional and contains `correction_en`,
  `explanation_l1` and `explanation_locale` only when practice policy calls for
  correction;
- `detected_intent` is talk / mother-tongue-ask / off-topic / unsafe; and
- `safe_to_speak` is boolean.

If JSON fails: repair once with the same model, then use a separately approved
Heavy Model if configured, then a curated mode-specific safe fallback. English
Practice falls back in English; Kannada Help uses its independently reviewed
Kannada fallback and optional English bridge. Never show a raw provider dump.

AI still **must not** write mastery, unlocks, or entitlements.

### 3.2 Router

```text
explicit learner turn
     → server-selected STT profile and provider
         → original transcript + detected locale + confidence
         → bounded fallback only on approved failures
     → project Turn Model and schema/safety validation
         → English reply for English Practice
         → short Kannada reply + optional English bridge for Kannada Help
         → approved Heavy/L1 challenger only when policy and eval justify it
     → server-selected TTS provider for the validated reply locale
         → first audio streamed immediately
         → optional correction/guidance produced in parallel or background
     → usage, first-result/finish/end-to-audio latency and cost evidence
```

### 3.3 Models to have live (accounts, not SDKs in domain)

| Role | Vendor | When it is on |
|---|---|---|
| Turn Model | Azure OpenAI (keep South India) | Go-live |
| Heavy Model | Separately approved provider/model behind the same project contract | Only for bounded schema repair, low-confidence policy or evaluated Role-play need |
| L1 Kannada specialist | Separately approved current model behind the same project contract | Only if the locked Kannada panel beats the Turn Model and its privacy/latency gates pass |
| Future L1 Tamil/Telugu/Hindi | Same L1 slot, different system prompt + eval set | After Kannada factory is proven |
| On-device / offline AI | None in v1 | Offline remains deterministic lessons only |

Do not multiply chat vendors during the speech bake-off. Azure, Sarvam and
ElevenLabs in DR-0048 refer to speech candidates; a new conversation-model
vendor is a separate benchmark and approval.

### 3.4 Eval that actually protects “world class”

- A hash-locked synthetic/licensed set plus a separately consented,
  representative Kannada adult corpus covering nervous, noisy and code-mixed
  speech. Ordinary learner audio is not three-way shadow material.
- 15 hash-locked correction cases (already have `friend.l1.locale.v1` — keep them).
- A new **listen set**: did each STT candidate preserve the original words,
  English intent, names, numbers and negation?
- Blind bilingual TTS review of Azure, Sarvam and ElevenLabs with sample sizes,
  voice/model versions and beginner-pace results recorded.
- Provider and end-to-end first-result/finish/first-audio p50/p95 on target
  Android, Wi-Fi and typical 4G.
- Versioned unit cost, cost per successful usable turn, retry/fallback cost and
  invoice reconciliation.
- Weekly human score: 20 turns, two reviewers, “would I send this to my cousin?”
- Kill a model in 24 hours if it invents facts, shames the learner, or writes a mark.

---

## 4. Production steps

Dates assume you freeze scope **today** and treat this as the go-live programme. Parallelise 4.1–4.4.

### P0 — Freeze and host (days 0–3)

1. Freeze features that are not Speak, Learn, Pay, Publish, Host.
2. Point DNS: `ai.globalspokenenglish.com` → the Ubuntu box. TLS, HSTS, no tunnel.
3. Rebuild Android with that HTTPS origin. Throw away tunnel-pinned APKs.
4. Keep staging/soft-launch until every DR-0048 production gate passes. Do not
   use an environment label to bypass speech, privacy or UAT evidence.
5. Secrets stay mode-0600 **outside git**: Azure OpenAI, Azure Speech, Sarvam,
   ElevenLabs when separately approved, the payment provider and Play signing.
6. Run courtesy Premium grant on the live Direct org so current students are not demo-capped on day one.
7. Publish privacy policy + delete-account URL on that same host. Consent and
   store disclosures must name every provider that can actually receive learner
   audio or text.

**Exit:** a phone on a friend’s Jio can open `https://ai.globalspokenenglish.com`, sign in, and the APK talks to the same origin.

### P1 — Ears live: governed multi-provider STT

Complete this before public production or a Play production listing.

1. Review the exact Sarvam, ElevenLabs and Azure products that may receive
   learner audio: contracting entity, DPA, subprocessors, processing location,
   retention/no-training configuration, deletion behavior, credentials, spend
   caps and emergency disablement. ElevenLabs requires the approved enterprise
   residency/Zero Retention configuration before it receives learner audio.
2. Keep the current bounded Sarvam recorded path and Azure fallback while the
   realtime candidates are developed behind project-owned ports.
3. Add server-owned recognition profiles rather than one global mode:
   - `english_practice.native_or_codemix`;
   - `kannada_help.native_kn`; and
   - `kannada_help.kn_en_codemix`.
4. Preserve `transcript_original`, provider/detected locale and confidence. A
   translated English intent may be derived as a separate orchestration field
   after preservation; it must not erase Kannada/code-mixed evidence or become
   grammar/correction evidence.
5. Benchmark Sarvam Saaras realtime, ElevenLabs Scribe v2 Realtime and Azure
   explicitly configured for the required locale. Introducing WebSocket audio
   transport requires the master-plan §14.2 ADR and must retain explicit turn
   submission; it is not always-on capture or full duplex.
6. Keep a visible 15–25 second turn budget and bounded recorded fallback even
   when streaming is available.
7. Add exact provider/model keys to usage-policy and PostgreSQL constraints
   before any call. Record successful, failed, retried and fallback usage.
8. Run three-way comparison only on synthetic/licensed or separately consented
   evaluation material. Route ordinary learner operations to one primary plus
   bounded fallback; never shadow them automatically to all providers.

**Exit:** every STT candidate has content-free usage/cost/latency evidence;
Kannada/code-mix quality and endpointing pass on target devices/networks; Azure
fallback is tested by controlled failure; original-language preservation is
proved; consent and deletion evidence match the enabled route.

### P2 — Mouth and brain live (days 3–14)

1. Complete the DR-0048 usage schema: explicit billable-unit kinds, versioned
   rate cards, native currency and INR evidence, first-result/finish timing,
   fallback accounting and invoice reconciliation.
2. Add the permission-gated, tenant-scoped Speech Economics operator API and
   screen before choosing a production provider.
3. Optimize the measured model bottleneck: record first-token and completion
   time, bound the reply, minimize allowlisted context/prompt overhead, reuse
   connections where supported and benchmark the current deployment against a
   separately approved challenger only if it misses the gate.
4. In the first safe streaming implementation, complete and validate the
   bounded model reply, then immediately stream its TTS audio. Do not wait for a
   complete WAV or embed it in the final turn JSON. Speaking while a model is
   still generating requires a separate, independently validated incremental
   envelope and ADR; never play raw unvalidated tokens.
5. Generate optional Kannada correction/guidance audio in parallel or after
   main playback begins. It must not block an already-ready main reply.
6. Integrate Azure, Sarvam and ElevenLabs STT/TTS candidates through the
   project ports, usage policy, kill switches and exact database constraints.
7. After the required ADR/master-plan amendment, add Kannada Help: native or
   code-mixed transcript preserved, short Kannada answer, optional English
   bridge, selected Kannada TTS, reviewed speech fallback, text/replay recovery
   and no marks/mastery. Full Kannada replies exist only in explicit Help mode
   under the same backend free-speech entitlement; English Practice may give
   one short Kannada bridge and then returns to English. Any demo allowance must
   be explicitly capped rather than inferred from detected language.
8. Keep the production Turn Model behind the same structured contract and add
   a Heavy Model only if a separate benchmark proves the need. Cap all spend.
9. Replace dummy payments with Razorpay/Cashfree. Webhook → entitlement grant,
   idempotent. GST snapshot as already designed.
10. Publish Direct Levels 1–17 for entitled learners. Stop hiding the school
    behind `curriculum_publication_gate`.
11. Progress tab: streak, due review, can-do. Stop showing the level path there.
12. Home: today’s speak task + due review outranking trivia.

**Exit:** the main validated response begins without waiting for guidance;
provider/model economics are visible and reconcilable; Kannada Help works under
its accepted authority; the master-plan end-of-turn-to-first-response-signal
target demonstrates median ≤4s/p95 ≤8s; and the separate DR-0048 launch gate
demonstrates turn-end-to-first-audible median ≤4s/p95 ≤8s, with a recommended
objective of median ≤1.8s/p95 ≤4s on target Wi-Fi and typical 4G. The stricter
audible gate must be accepted in SPEECH-GOV-01 before implementation.

### P2.5 — Speech provider selection gate (before production)

1. Run the controlled Azure/Sarvam/ElevenLabs STT and TTS bake-off in DR-0048.
2. Compare Kannada/code-mix accuracy, names/numbers/negation, transcript edit
   rate, blind bilingual TTS quality, beginner pace, p50/p95 latency, failure/
   fallback rate and invoice-reconciled cost per successful usable turn.
3. Select one primary and at least one reviewed fallback per stage. Pin exact
   provider/model/region/profile versions server-side; the client never selects
   a vendor.
4. Rehearse provider failure, quota exhaustion, timeout, kill switches and
   text/recorded fallbacks. Prove every attempt is counted once.
5. Update consent, privacy policy and Play Data safety to name every provider
   that can receive learner audio or text. Re-consent where the approved notice
   changes.

**Exit:** SPEECH-PROD-01 in DR-0048 passes. No provider is promoted from list
price, vendor latency or a single clean recording.

### P3 — India #1 loop (days 15–90)

Do this **after** P2.5 has selected and proved the production speech routes and
the released path is boringly stable. This is how you improve the product
without reopening the production gate casually.

1. **Provider revalidation:** the initial Azure/Sarvam/ElevenLabs bake-off is a
   pre-production gate. After production, repeat it when a model, price,
   contract or measured quality/latency materially changes; do not silently
   change the selected Swathi voice.
2. **Swathi voice:** clone a licensed studio take of the real teacher for English model lines. Institute audio stays the lesson recordings; AI talk can sound like the same person. This beats a generic Nova.
3. **Pronunciation lab (advisory):** phoneme-level feedback on Practice sentences only. Never a CEFR score. Never a mark. This is ELSA’s gap in your category.
4. **Notifications:** streak at risk, due review, 48h demo ending. SuperNova and Duolingo are notification companies.
5. **Speak situations:** 12 scenes is a start. Add 30 workplace / interview / travel scenes with the same engine. Quantity of *good* scenes beats a Flutter rewrite.
6. **Language-pack factory (design now, run later):** see §5. Do not turn on Tamil STT curriculum until Kannada eval is green.
7. Play production track. Closed test 12 testers × 14 days if the account requires it. Then public.
8. iOS only as Capacitor iOS on the same React shell — after Android is making money.

**Exit:** 7-day repeat use, Kannada explanation win-rate ≥ 90% on the human panel, Premium conversion from demo ≥ a number you set (start by measuring; 8–15% is a serious app).

### P4 — All Indian languages (only after P3 Kannada is stable)

You asked for every Indian language in upgrades. That is correct **as a second product**, not as go-live.

A language is not a locale JSON. A language is:

| Layer | Kannada (v1) | Each new language |
|---|---|---|
| UI catalogue | Independent review | New catalogue + reviewer |
| STT | Selected Kannada profiles after the three-provider bake-off | Provider/profile candidate plus a new consented **eval set** in that L1 |
| L1 explanation | Human-reviewed few-shot + eval set | New 50-item eval, new reviewer |
| L1 TTS | Production-selected Eleven v3 Conversational, Bulbul v3 or Sapna route | New supported voice/model; listener panel and latency/cost evidence |
| Curriculum | Institute English + Kannada guidance | Translate **guidance only**; English curriculum stays English |
| Speech eval | kn-IN noise/code-mix set | New set; no reuse of Kannada scores |
| Store listing | kn + en | New screenshots and Data safety |

**Rollout order (revenue + speech quality, not census):**

1. Kannada (now)
2. Telugu, Tamil, Hindi (largest adjacent demand)
3. Malayalam, Marathi, Bengali
4. Gujarati, Punjabi, Odia, Assamese, then the rest of Schedule 8 as TTS/STT quality allows

Never enable `ml-IN` / `ta-IN` / `te-IN` / `hi-IN` as “UI preview = learning language.” That lie is how you get one-star reviews. UI can preview; **Speak L1 is off until that language’s eval pack is green.**

A multilingual STT candidate may **hear** another language before the product is
approved to teach or answer in it. That does not enable the locale. Preserve
the original transcript, keep the English-learning contract, and enable L1
guidance/replies only after that language pack, consent copy and evaluation set
pass their own gate.

---

## 5. Client and loop (what SuperNova and Speak actually got right)

### 5.1 Keep hold-to-talk

Full-duplex on a ₹8,000 Android in a bus is a demo. Turn-based hold is how India practices. Improve it:

- Visible listening / processing / speaking (you started this).
- Hard 15–25s utterance cap with a countdown (better STT, better pedagogy).
- Cancel aborts the in-flight turn (you have this).
- First audio from streamed TTS, not after SSE + full WAV.
- Keep turn-based explicit submission. Realtime STT may stream while the learner
  holds/taps, but it must not become always-on capture or unapproved full duplex.
- Measure immediate acknowledgement separately from substantive first signal
  and first audible audio; only the last measure proves conversational speed.

### 5.2 Do not Flutter-rewrite

A rewrite throws away Passport, offline outbox, localisation, operator console, and six months. SuperNova can afford a single Flutter bet because that **is** their product. You already have the school on this stack. Capacitor iOS later.

### 5.3 Offline

Keep deterministic Learn offline. Do not promise offline Swathi. World-class apps are honest when the network is dead.

---

## 6. Commercial and go-live numbers

Keep GST-exclusive ₹350 / ₹500. Demo 48h + 300s is the right funnel.

| Move | Why |
|---|---|
| Courtesy 1-month Premium for current Direct learners | They are your first reviews. Do not put them on a 5-minute demo the week you go public. |
| No Play Billing in v1 | Razorpay web checkout in the same WebView. Add Play Billing only if Play forces it. |
| Cost ceiling per learner-day | Instrument STT billed seconds, LLM tokens, TTS characters/generated seconds, retries and fallbacks against immutable rate cards. Reconcile estimates to invoices before changing a plan or route. |
| Compare effective economics | Rank only quality/privacy-passing candidates, using INR per successful usable turn and learner speaking minute. Separate gross cost, tax, discounts and credits so a free tier does not distort marginal cost. |
| Entitlement accounting | One logical submitted learner turn consumes its approved live-AI entitlement once. Provider retry, fallback and benchmark calls are ledgered but never charge the learner a second time. |

---

## 7. Go-live checklist (production, not a philosophy)

Print this. Do in order. Skip nothing.

1. Stable HTTPS origin live.
2. DR-0048 and its required realtime/Kannada Help/master-plan ADRs are accepted
   at the exact scope being implemented; credentials, capped spend, evaluation
   material and production release each have their required authority.
3. Azure, Sarvam and ElevenLabs candidates exist only behind project-owned
   ports. Exact provider/model/API/voice/region/configuration identities and
   configuration hashes are frozen; unselected candidates are disabled.
4. Server-owned routing selects one primary and at least one reviewed speech
   fallback per stage. Timeouts, two-total-dispatch-attempt policy, retry
   categories, circuit breakers, quotas and per-stage kill switches pass;
   text/replay and bounded recorded capture remain separate UX recovery paths.
5. `voice_provider_runs` and `ai_cost_ledger` feed a content-free unified read
   model with explicit units, first/finish/end-to-audio timings, versioned rate
   cards, currency/FX evidence and invoice reconciliation.
6. The permission-gated Speech Economics admin view passes organization and
   platform scopes, cross-tenant/list/filter/export/cache tests, safe small-
   sample handling, accessibility and the operator p95 ≤800ms target.
7. The measured response waterfall is fixed: model latency bounded, validated
   main audio streamed, optional Kannada guidance off the critical path,
   cancellation/backpressure and mobile compressed playback verified.
8. The controlled three-provider benchmark passes on the frozen synthetic/
   licensed and separately consented Kannada adult corpus. No ordinary learner
   audio is three-way shadowed.
9. Kannada Help has accepted product authority, preserves original language,
   returns a short Kannada reply plus optional English bridge, and cannot write
   marks, mastery, progression, review or unlocks.
10. The master-plan end-of-turn-to-first-response-signal target demonstrates
    median ≤4s/p95 ≤8s. The separately accepted DR-0048 first-audible gate also
    demonstrates median ≤4s/p95 ≤8s, with a recommended launch objective of
    median ≤1.8s/p95 ≤4s on target Android, Wi-Fi and typical 4G. Known
    dependency failure becomes visible within 2s; voice availability/capacity
    and 2× forecast-peak load gates pass.
11. Every enabled provider passes DPA/subprocessor/processing-location,
    retention/no-training, human-access, incident, deletion and emergency-
    disable review. Secrets are least-privilege, outside Git/browser/database,
    rotated and revocable. Deletion work remains enabled during dispatch kills.
12. Consent, privacy inventory/DPIA/threat model, vendor register, privacy
    policy and Play Data safety name every provider that can receive content.
    Retention of call-level usage versus approved anonymous aggregates is
    explicitly resolved under ADR-0007.
13. Real GSTIN + Razorpay (or Cashfree) idempotent webhooks grant entitlements.
    Retry/fallback/benchmark speech spend never consumes learner entitlement a
    second time.
14. Direct pathway published; demo/paywall, courtesy Premium, Home and Progress
    behave as designed.
15. Kannada UI and speech wording have accountable bilingual review; critical
    accessibility paths pass WCAG 2.2 AA and TalkBack/system-font checks.
16. Play signed AAB uses the stable HTTPS origin. Store and Data safety name the
    microphone, audio processing and every enabled third-party STT/LLM/TTS.
17. At least 20 consented adults test the real journey on Jio/Airtel/Wi-Fi,
    cheap Androids and noisy rooms. If fewer than 18 finish one Speak turn, the
    product is not live.
18. Staging migration/rollback, backup/restore, provider failure, quota,
    retention/deletion and one-command rollback rehearsals pass. `make openapi`,
    `make verify`, `make e2e`, `make ai-eval`, `make build`, PostgreSQL tenancy,
    load/soak, Android lifecycle and accessibility gates are green.
19. `docs/STATUS.md`, `docs/DECISIONS.md`, `docs/QUALITY_REVIEW.md`, privacy
    inventory, consent revisions and provider runbooks contain the exact final
    evidence. Production still requires explicit accountable release approval.

---

## 8. What I would not do, even with a free hand

- **Pure Sarvam** for STT + LLM + TTS on day one. Unproven brain, extra TTS risk, no eval lock.
- **Azure-only STT** into a Kannada market. You will lose every comparison video to SuperNova.
- **Preselect or reject ElevenLabs from reputation or list price.** Integrate the
  Kannada-capable Scribe v2 Realtime/v3 Conversational candidates, then promote
  them only if the controlled quality, latency, privacy and effective-cost
  evidence wins.
- **Send ordinary learner audio to all three providers.** One routed primary
  plus bounded fallback; three-way evaluation uses separately authorized data.
- **Treat a sub-100ms spinner as an immediate answer.** Measure turn-end to
  first substantive signal and first audible audio separately.
- **GPT-4o on every turn.** Router or you die on COGS.
- **Flutter / iOS / duplex / 19 hard-coded levels / Miss Nova / attendance % / Notice Board** before money.
- **Import old-website passwords.** Illegal and architecturally wrong. Invite or fresh usernames.
- **Authoritative pronunciation scores.** Advisory only, or you will be sued by pedagogy.

---

## 9. How you actually become #1

SuperNova is a microphone with a personality. Duolingo is a habit. ELSA is a phonetic mirror. Speak is a fluent partner.

Your category-winning sentence:

> Open the app, Swathi hears your Kannada-English as it is, you speak useful English in a real institute lesson, you see tomorrow’s one task, and a human trainer still exists if you need 1:1.

The production programme is therefore:

1. **Before production:** complete DR-0048: governed adapters for all three
   speech providers, exact usage/rate-card/invoice evidence, Speech Economics
   admin, model-latency work, streamed validated first audio, controlled
   bake-off, evidence-selected primary/fallback routing and Kannada Help under
   its accepted product authority. Also complete stable hosting, payments,
   publication, consent/privacy and human UAT.
2. **This quarter after a stable release:** pronunciation advice, habit
   notifications, more scenes and the language factory. A licensed Swathi voice
   clone remains a separate rights/privacy decision, not part of DR-0048.
3. **This year:** Tamil/Telugu/Hindi packs with the same quality bar as Kannada, iOS, duplex as Premium, then the rest of India.

Do not reduce the pre-production speech work to a provider flip. The minimum
acceptable change is the complete measured route: observability first,
streaming/parallel latency repair, controlled provider comparison, Kannada Help
authority and an evidence-selected primary plus fallback.

---

## 9.1 First engineering slice if this plan is accepted

Follow [WORLD_CLASS_PRODUCTION_STEPS.md](WORLD_CLASS_PRODUCTION_STEPS.md) from
Wave 0. Do not skip Wave 2A or the complete DR-0048 pre-production gate. No
public-production release and no `APP_ENV=production` before that evidence is
recorded and explicitly approved.
