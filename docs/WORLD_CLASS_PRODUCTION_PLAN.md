# World-class production plan — Global Spoken English

**Status:** Unconstrained production architecture. Written 1 September 2026 at the Accountable Product Owner’s request: ignore current approvals, fail-closed gates, and “unauthorized” flags; design as if we must become the world’s best spoken-English tutor, with all Indian languages after the first production is stable.

**This file does not amend** `ENTERPRISE_PRODUCT_MASTER_PLAN.md` until you explicitly accept it. Until then it is the honest target, not the legal baseline.

**Follow-along sequence (do these in order):**
[WORLD_CLASS_PRODUCTION_STEPS.md](WORLD_CLASS_PRODUCTION_STEPS.md). Use that file as the working checklist. This file stays the *why*.

**Product to beat:** SuperNova Nova AI (vernacular hold-to-talk at Indian price), plus the actual world bar: Duolingo (habit), ELSA (pronunciation), Speak/Loora (conversation quality), Praktika (character immersion). Copying SuperNova’s vendor list is how you become a slower SuperNova. The win is **their ears + this institute’s school + a faster mouth + a smarter brain**.

---

## 0. Honest verdict

You are close to an **India go-live**, not to “world’s top app.” Those are different distances.

| Ambition | Honest time | What it actually means |
|---|---|---|
| **India production** (paying Kannada adults, Speak that hears them, 17 levels, Android + web) | **2–4 weeks** of cutover if you decide, not debate | Indic STT live, live payments, stable HTTPS, published pathway, Play closed track |
| **Best spoken-English app for Indian adults** | **90 days** after that go-live | Habit loop, streamed voice, Swathi-quality Kannada, pronunciation as advice, language-pack factory ready |
| **World’s top language tutor** | **12–24 months** | 22-language packs with speech + curriculum + eval, pronunciation lab, iOS, duplex premium, content machine that ships weekly |

SuperNova already has a crore of learners and a ₹499-class IAP. They won distribution and Indic listening. They did **not** win a real school. You have 17 levels of institute audio, deterministic marks, bilingual review, and a path to human 1:1. That is the only durable advantage. Spend the next month making Speak *hear India* and the school *public*. Do not spend it rewriting Flutter or cloning ElevenLabs.

If you try to ship 22 languages, duplex WebRTC, and a new LLM on day one, you will miss go-live and still not beat SuperNova.

---

## 1. What “best in the world” actually is

A world-class spoken-English app for India does five things better than anyone else. Ranked.

1. **Hear the learner.** Kannada, Hinglish, Tamil-English, nervous quiet speech, cheap mics, fans, traffic. If STT is English-only Azure, SuperNova already beat you.
2. **Answer in under a second of felt time.** Hold-to-talk is correct for cheap Android and bad networks. The crime is waiting for the full WAV → STT → LLM → TTS → download before the mouth moves. Stream the first audio.
3. **Teach, don’t chat.** SuperNova is a conversational gym. You must be a **course**: today’s mission, due review, unlock, evidence, a teacher named Swathi, 67 real lesson recordings. Marks stay human-rules. AI never writes them.
4. **Mother-tongue coaching that is not machine-translated mush.** First production is Kannada as the gold language. Other Indian languages are a **factory** after Kannada is independently good, not 22 Google-translate skins.
5. **A price that can pay for the voices.** ₹350 Modules / ₹500 Premium only works if TTS is cheap. ElevenLabs as default mouth will eat the plan. Use it later as a luxury voice, not the mass path.

Everything else (notice board, Flutter, attendance %, Miss Nova, scraping the old website) is how also-rans waste a quarter.

---

## 2. Target production stack

Keep the Laravel modular monolith and the React PWA + Capacitor Android. Break the **provider wiring**, not the product.

### 2.1 Voice and intelligence (go-live)

| Job | Production choice | Why | Fallback |
|---|---|---|---|
| **STT (live now)** | **Sarvam Saaras v3**, REST, `mode=translate`, `language_code=unknown` | This is SuperNova’s actual weapon. English-as-is, Indic → English in one call. Code-mix is the product, not an edge case. Adapter already exists. | Azure Fast Transcription `en-IN` only if Sarvam 5xx / timeout |
| **Detected L1** | Persist Sarvam’s detected language on the turn (not as a mark) | Needed so Kannada TTS is used when they spoke Kannada, Tamil TTS later, etc. | Default `kn-IN` for Direct v1 |
| **Conversation LLM** | **Azure OpenAI `gpt-4.1-mini` (or current South India deployment) as Turn Model** | Already structured to `assistant_reply` + `priority_correction`. India-regional. Fast. Cheap enough for ₹500 unlimited-ish talk. | Second Azure deployment in the same resource |
| **Hard-correction LLM** | **OpenAI GPT-4.1 / GPT-4o** (or Claude Sonnet) on a **router**, not on every turn | Use only when confidence is low, Role-play Perform, or the Turn Model’s JSON fails schema. SuperNova put GPT-4o on every utterance; that is how you lose money. | Turn Model retry |
| **Kannada explanation LLM** | Same Turn Model first; **Sarvam 30B** as an L1 specialist if Kannada eval fails | Do not replace the English teacher with Sarvam-105B on day one. Swap L1 only if humans prefer it. | Azure explanation_l1 as today |
| **English TTS** | **Azure `en-IN-NeerjaNeural` streaming** (or equivalent neural) | Good enough Indian English. Already paid. Ledger known. | Text-first on failure (already built) |
| **Kannada TTS** | **Azure `kn-IN-SapnaNeural` at go-live**; bake-off **Sarvam Bulbul v3** in week 3 | Kannada quality is the product. Bulbul may win. ElevenLabs Flash only if a listener panel says it is worth 5–10× the rupees. | Azure Sapna |
| **Pronunciation (not go-live)** | Advisory scorer after 30 days (see P3). Never writes marks. | ELSA’s moat. SuperNova is weak here. This is how you become #1, not a clone. | Off |

Do **not** put Sarvam on chat, Azure off, and ElevenLabs on every character at the same moment. That is three unproven cutovers. Hear first. Mouth second. Brain last.

### 2.2 What to keep (do not break)

- Username/password + optional Google/Facebook for already-enrolled users.
- Sanctum web, Passport family on Android, no tokens in `localStorage`.
- Deterministic mastery, delayed review, meaningful-speaking streak.
- AI advisory-only. No AI marks, unlocks, or attendance.
- Hold-to-talk as the default. Full-duplex is a **premium later**, not go-live.
- Owned lesson MP3s. Never scrape `globalspokenenglish.com`.
- Teacher name **Swathi**. Not Nova.
- Direct ₹0 demo (48h, Level 1–2, 300s live-AI) / ₹350 Modules / ₹500 Premium.
- Institutional orgs unmetered.

### 2.3 What to break (do this)

| Break | Replace with |
|---|---|
| Azure-only live STT | Sarvam translate STT as default; Azure as cold standby |
| `CONVERSATION_PROVIDER_DRIVER=disabled` in production | Live Turn Model in production with spend caps, kill switch, and schema repair |
| Dummy checkout as the public path | Razorpay (or Cashfree) + real GSTIN; keep dummy only for staff |
| `curriculum_publication_gate` blocking Levels 2–17 | Publish the reviewed Direct pathway for paying learners |
| Progress tab still showing the Learn path | Real Progress: can-do, streak, due review, recent improvement |
| Cloudflare Quick Tunnel as the product host | `https://ai.globalspokenenglish.com` (or one stable HTTPS) |
| Kannada UI `pending-independent-review` blocking production chrome | Ship Kannada chrome with a visible “preview” only if a reviewer is not booked; do not fall back to English for kn-IN Direct |
| 120s hold encoded as one WAV against Sarvam REST ~30s | Chunk or use Sarvam realtime WebSocket; otherwise the long turn silently dies |
| Single LLM for every token | Router: Turn Model always; Heavy Model on failure/low confidence; L1 specialist only if eval demands it |
| Play debug APK as “the app” | Signed AAB, Play closed testing, then production track |
| Courtesy Premium not applied on the live DB | Grant the 1-month Premium on go-live so existing Direct learners are not stuck on demo |

---

## 3. LLM design (multiple models, one contract)

The product mistake is “pick one LLM.” The SuperNova mistake is “GPT-4o for everything.” The world-class pattern is a **strict output contract** and a **router**.

### 3.1 One contract, many brains

Every model, including Sarvam and Claude, must emit the same project JSON:

- `assistant_reply_en` — what Swathi says in English (spoken)
- `priority_correction` — optional; `correction_en`, `explanation_l1`, `explanation_locale`
- `detected_intent` — talk / mother-tongue-ask / off-topic / unsafe
- `safe_to_speak` — boolean

If JSON fails: repair once with the same model, then Heavy Model, then a canned English + Kannada fallback. Never show a raw provider dump.

AI still **must not** write mastery, unlocks, or entitlements.

### 3.2 Router

```text
audio → Sarvam STT (translate) + lang_id
     → Turn Model (gpt-4.1-mini / current Azure deployment)
         → if schema invalid or STT confidence low or Role-play Perform
              → Heavy Model (GPT-4.1 or Claude Sonnet)
         → if explanation_locale is kn-IN and Kannada eval is failing
              → L1 Model (Sarvam 30B) for explanation_l1 only
     → TTS English (Azure stream)
     → TTS L1 (Azure Sapna or Bulbul) in parallel when a correction exists
```

### 3.3 Models to have live (accounts, not SDKs in domain)

| Role | Vendor | When it is on |
|---|---|---|
| Turn Model | Azure OpenAI (keep South India) | Go-live |
| Heavy Model | OpenAI GPT-4.1 or Anthropic Claude Sonnet | Go-live behind router, 10–20% of turns max |
| L1 Kannada specialist | Sarvam 30B | Only after a 50-item human panel loses to Azure |
| Future L1 Tamil/Telugu/Hindi | Same L1 slot, different system prompt + eval set | After Kannada factory is proven |
| On-device / offline AI | None in v1 | Offline remains deterministic lessons only |

Do not add Gemini, Groq, Llama, and OpenRouter to production on week one. Two chat vendors plus Sarvam STT is already operational complexity. A third chat vendor is allowed only as Heavy Model.

### 3.4 Eval that actually protects “world class”

- 50 locked Kannada adult clips (nervous, noisy, code-mix).
- 15 hash-locked correction cases (already have `friend.l1.locale.v1` — keep them).
- A new **listen set**: did Sarvam hear the English intent?
- Weekly human score: 20 turns, two reviewers, “would I send this to my cousin?”
- Kill a model in 24 hours if it invents facts, shames the learner, or writes a mark.

---

## 4. Production steps

Dates assume you freeze scope **today** and treat this as the go-live programme. Parallelise 4.1–4.4.

### P0 — Freeze and host (days 0–3)

1. Freeze features that are not Speak, Learn, Pay, Publish, Host.
2. Point DNS: `ai.globalspokenenglish.com` → the Ubuntu box. TLS, HSTS, no tunnel.
3. Rebuild Android with that HTTPS origin. Throw away tunnel-pinned APKs.
4. Production env: live conversation, live voice upload, Indic STT, live payments driver, real GSTIN. Kill switch remains one flag.
5. Secrets stay mode-0600 **outside git**: Azure OpenAI, Azure Speech (standby STT + TTS), Sarvam, Razorpay, Play signing.
6. Run courtesy Premium grant on the live Direct org so current students are not demo-capped on day one.
7. Publish privacy policy + delete-account URL on that same host (Play will demand it).

**Exit:** a phone on a friend’s Jio can open `https://ai.globalspokenenglish.com`, sign in, and the APK talks to the same origin.

### P1 — Ears live: Indic STT (days 0–7, starts immediately)

This is the one SuperNova lesson that matters. Do it before the press / Play listing.

1. Sarvam project + DPA: no training on your audio, India processing if they offer it, deletion on their side matching your 60-minute source deletion.
2. Wire production:
   - `VOICE_UPLOAD_ENABLED=true`
   - `VOICE_PROVIDER_DRIVER=indic`
   - `VOICE_INDIC_TRANSCRIBE_URL=https://api.sarvam.ai/speech-to-text`
   - `VOICE_INDIC_ALLOWED_HOST=api.sarvam.ai`
   - `VOICE_INDIC_MODEL=saaras:v3`
   - `VOICE_INDIC_MODE=translate`
   - `VOICE_INDIC_LANGUAGE_CODE=unknown`
3. **Fix the 30s trap:** current holds may be up to 120s. Production must either:
   - cap a Speak utterance at ~25s with a visible meter (better pedagogy anyway), **or**
   - chunk WAV to ≤30s, or use Saaras realtime WebSocket for the hold.
4. Allow `indic-http-stt` on `ai_cost_ledger` **before** the first live turn (missing CHECK = HTTP 500, entire Speak dead).
5. Keep Azure STT as automatic failover for 14 days.
6. Store `provider_language` on the turn (content-free + locale code, not the audio).
7. Shadow 200 real Direct utterances: Azure vs Sarvam. If Sarvam WER on English-intent is worse than Azure on *English-only* clips, still keep Sarvam for mixed clips; measure both.

**Exit:** a Kannada-dominant adult can hold, mix Kannada and English, and Swathi answers the English intent. Failover to Azure is tested by killing Sarvam in staging.

### P2 — Mouth and brain live (days 3–14)

1. Production Turn Model on. Same structured contract as soft-launch.
2. Stream TTS: first sentence of `assistant_reply_en` speaks while the rest generates. This is the latency leap SuperNova buyers feel. Do it on Azure first (you already pay for it).
3. Speak L1 (Kannada) in parallel when a correction exists. Do not block English audio on Kannada TTS.
4. Add Heavy Model behind the router. Cap spend (start with a hard daily INR ceiling you can raise).
5. Replace dummy payments with Razorpay/Cashfree. Webhook → entitlement grant, idempotent. GST snapshot as already designed.
6. Publish Direct Levels 1–17 for entitled learners. Stop hiding the school behind `curriculum_publication_gate`.
7. Progress tab: streak, due review, can-do. Stop showing the level path there.
8. Home: today’s speak task + due review outranking trivia.

**Exit:** pay ₹500 dummy-no-more, unlock Role-play, hold-to-talk p50 **felt** reply &lt; 1.0s on Wi-Fi, &lt; 1.8s on typical 4G. (Provider time will still dominate; streaming is how you cheat perception.)

### P3 — India #1 loop (days 15–90)

Do this **after** P2 is boringly stable. This is how you pass SuperNova on *product*, not on a blog.

1. **TTS bake-off (week 3):** Azure Sapna vs Sarvam Bulbul vs (optional) ElevenLabs Flash on 20 Kannada scripts. Pick by listener + ₹/minute. Default stays the cheaper one that is “good enough.” ElevenLabs as a Premium voice skin only if the math works.
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
| STT | Sarvam translate already hears 11–22 | Same STT; **eval clips** in that L1 |
| L1 explanation | Human-reviewed few-shot + eval set | New 50-item eval, new reviewer |
| L1 TTS | Sapna / Bulbul kn | New voice; listener panel |
| Curriculum | Institute English + Kannada guidance | Translate **guidance only**; English curriculum stays English |
| Speech eval | kn-IN noise/code-mix set | New set; no reuse of Kannada scores |
| Store listing | kn + en | New screenshots and Data safety |

**Rollout order (revenue + speech quality, not census):**

1. Kannada (now)
2. Telugu, Tamil, Hindi (largest adjacent demand)
3. Malayalam, Marathi, Bengali
4. Gujarati, Punjabi, Odia, Assamese, then the rest of Schedule 8 as TTS/STT quality allows

Never enable `ml-IN` / `ta-IN` / `te-IN` / `hi-IN` as “UI preview = learning language.” That lie is how you get one-star reviews. UI can preview; **Speak L1 is off until that language’s eval pack is green.**

Sarvam translate-mode means you can **hear** those languages earlier than you can **teach** in them. That is allowed: they speak Tamil, you reply in English + Tamil explanation only after the Tamil pack exists. Until then: English reply + Kannada-or-English explanation, and the STT still understands Tamil-in-the-mix.

---

## 5. Client and loop (what SuperNova and Speak actually got right)

### 5.1 Keep hold-to-talk

Full-duplex on a ₹8,000 Android in a bus is a demo. Turn-based hold is how India practices. Improve it:

- Visible listening / processing / speaking (you started this).
- Hard 15–25s utterance cap with a countdown (better STT, better pedagogy).
- Cancel aborts the in-flight turn (you have this).
- First audio from streamed TTS, not after SSE + full WAV.

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
| Cost ceiling per learner-day | Instrument STT minutes + LLM tokens + TTS characters. If Premium COGS &gt; ~₹80–120/month at typical use, shorten demo, cap daily Speak minutes on Modules, or raise Premium. |
| Never unlimited GPT-4o + ElevenLabs on ₹500 | That is SuperNova’s marketing, not their unit economics. |

---

## 7. Go-live checklist (production, not a philosophy)

Print this. Do in order. Skip nothing.

1. Stable HTTPS origin live.
2. Sarvam STT default, Azure STT failover, 30s/chunk problem solved, ledger CHECK includes `indic-http-stt`.
3. Azure OpenAI Turn Model live in production with kill switch.
4. Azure TTS English + Kannada live; text fallback on failure.
5. Real GSTIN + Razorpay (or Cashfree) webhooks granting entitlements.
6. Direct pathway published; demo/paywall enforced as designed.
7. Courtesy Premium granted on live DB.
8. Progress UI is Progress.
9. Kannada UI ships for Direct (review if you can; do not English-fallback the whole chrome).
10. Privacy policy, delete account, consent copy updated for Sarvam as processor.
11. Play signed AAB, Data safety (microphone, audio, third-party STT/LLM/TTS named), closed testers.
12. 20 real adults on Jio/Airtel, cheap Androids, noisy rooms. If they cannot finish one Speak turn, you are not live.

---

## 8. What I would not do, even with a free hand

- **Pure Sarvam** for STT + LLM + TTS on day one. Unproven brain, extra TTS risk, no eval lock.
- **Azure-only STT** into a Kannada market. You will lose every comparison video to SuperNova.
- **ElevenLabs as default TTS.** Luxury later.
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

1. **This month:** hear India (Sarvam), keep the Azure teacher, take money, publish the school, put it on a real domain and Play.
2. **This quarter:** stream the mouth, clone Swathi, pronunciation advice, habit notifications, 30 more scenes, language factory ready.
3. **This year:** Tamil/Telugu/Hindi packs with the same quality bar as Kannada, iOS, duplex as Premium, then the rest of India.

If you only do one technical change before go-live: **Indic STT live, with the 30-second clip problem fixed, and Azure as failover.** Everything else in this file is how to be the world’s best *after* that ships.

---

## 9.1 First engineering slice if this plan is accepted

Follow [WORLD_CLASS_PRODUCTION_STEPS.md](WORLD_CLASS_PRODUCTION_STEPS.md) from Wave 0. Do not skip Wave 2A (clip length, ledger, failover) or set `APP_ENV=production` before Wave 12.
