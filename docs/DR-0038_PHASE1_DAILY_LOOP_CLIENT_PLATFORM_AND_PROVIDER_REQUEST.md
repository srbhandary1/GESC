# DR-0038 — Phase 1 revised spec: daily loop, client platform, providers, level naming

## Status

**A, B and C accepted** (30 August 2026, Asia/Kolkata) under
[APP-0069](APPROVALS.md#app-0069), recorded as D-0219: Daily Mission Home with a
meaningful-speaking-day streak and deterministic delayed review, Progress as a
distinct mastery and review surface, and a Speak latency pass with no new
provider. Master plan §7.2 is amended by
[ADR-0028](adr/0028-meaningful-speaking-streak-and-delayed-review.md); the plan
is version 3.9.

**D, F and G confirmed** by the same approver: the learner client stays React
PWA plus Capacitor 8 with Flutter and iOS deferred; levels stay generically
modelled and Levels 2–17 keep their authored content; no attendance percentage
enters canonical records, Notice Board stays deferred, and the teacher remains
Swathi.

**E is not accepted.** The Sarvam driver flip stays governed by D-0217 and needs
keys plus a reviewed secret file. ElevenLabs stays deferred pending a costed
comparison against Azure `kn-IN-SapnaNeural`.

**The compute-profile orchestration protocol is not accepted** and remains out
of scope, as recorded below.

### Correction to this request as originally authored

This document stated below that accepting A and B amends master plan §7.1. That
is wrong and is corrected in APP-0069 and ADR-0028:

- §7.1 is navigation and is **unchanged**; the five destinations stand.
- §7.2 Home is the section amended by A.
- §7.3 already requires Progress to show objectives, speaking activity, review
  due, recurring errors and recent improvement, so **B is a conformance fix, not
  an amendment**.

Raised from a product-owner "Master Orchestration Specification: Global Spoken
English App (Phase 1 – Revised)" dated 30 August 2026. That specification is
recorded here as a product brief. It is not self-executing: seven of its
provisions conflict with the canonical baseline and each needed an explicit
accept or reject before implementation.

## Request

The product owner asked for an app that is better than SuperNova AI Spoken
English, and better than the other language apps currently available, on
**usage, features and interface**. The submitted specification proposes:

1. a **Daily Mission** module combining attendance check-in, an attendance
   percentage gauge, a streak, a mandatory audio lesson, a five-question quiz
   and a three-to-four minute AI speaking task, completing in under 15 minutes;
2. an **AI Teacher portal** named "Miss Nova" with a binary English/Kannada
   selector and a dual-payload mother-tongue interruption correction;
3. a **My Levels** console covering 19 checkpoints (`Pre-Level 1`,
   `Pre-Level 2`, `Level 1`–`Level 17`), all unlocked, with eight micro rooms
   per level, and full content only for the first three;
4. a minimal **Notice Board**;
5. a rebuild on a **unified Flutter codebase** targeting Android, iOS and web;
6. **Sarvam AI** STT, **OpenAI GPT-4o**, and **ElevenLabs Multilingual v2** TTS;
7. **SQLite/Floor** local persistence for attendance, quiz marks and progress;
8. a `LegacyAudioSyncService` pulling lesson audio from
   `https://globalspokenenglish.com`;
9. a Claude-architect / GPT-codegen orchestration protocol with compute
   profiles replacing the current checkpoint discipline.

## Current product (what already exists)

The specification appears to describe `apps/web/src/demo/phase1/DemoPhase1Shell.tsx`
— the in-memory synthetic demo — rather than the live product. Attendance
calendars, the monthly attendance percentage, the five-question quiz and
`Pre-Level 1` / `Pre-Level 2` exist **only** in that demo and in
`phase1Fixtures.ts`. They have no API, no persistence and no tenancy.

The live learner product already provides:

- **Five-destination nav** `Home · Learn · Speak · Progress · 1:1`
  (D-0216 / ADR-0026).
- **17 levels**, generically modelled with concept keys (`can`, `should`,
  `do_does` … `used_to`) in `apps/api/config/level_sessions.php`, each with the
  four-session path Introduction → Practice → Conversation → Interaction
  (ADR-0025). All 17 are `available` on Direct with 67/67 governed teaching
  audio hashes attached.
- **Levels 2–17 authored lesson text** (`AuthoredFoundationLessons.php`,
  APP-0065), named English review (APP-0066) and named Kannada review
  (APP-0067). These are private Direct drafts; publication remains blocked by
  `curriculum_publication_gate`.
- **Dual-payload mother-tongue correction already shipped.** Each turn returns
  `assistant_reply_en` plus a separate `priority_correction` carrying
  `correction_en`, `explanation_l1`, `explanation_locale` and `explanation_kn`.
  It is advisory-only and cannot write marks. Guidance TTS speaks the Kannada
  with `kn-IN-SapnaNeural`. The `friend.l1.locale.v1` evaluation suite holds 15
  hash-locked cases and fails when false corrections appear.
- **Hold-to-talk Speak room** with 12 role-play scenes (D-0215).
- **Offline**: public shell service worker plus authenticated mission bundles
  in IndexedDB with idempotent sync.
- **Sarvam-style Indic STT already ported** as `IndicHttpSpeechToTextProvider`
  behind `RecordedSpeechToTextProvider` (D-0217 / ADR-0027), off until an
  explicit driver switch with a secret file outside git.
- Multi-tenancy, three-purpose voice consent, 60-minute source-audio deletion,
  transcript ciphertext, privacy erasure, operator console, content governance,
  Passport PKCE with Android secure credential storage, and an immutable
  `ai_cost_ledger`.

Two genuine weaknesses in the live product match what the specification is
reaching for:

- Authenticated Home returns at most **one** `next_mission` with a **single**
  MCQ. There is no streak, no delayed review and no "today" surface.
- **Progress renders the same `LevelPathPanel` component as Learn.** Two of the
  five destinations are currently identical.

## Conflicts requiring an explicit decision

| Specification provision | Canonical baseline it reverses |
|---|---|
| Unified Flutter codebase | Master plan §2: "Do not build a separate Kotlin or Flutter product in Phase 1"; §9.2 defers Flutter and iOS |
| Named providers as architecture | ADR-0010 and master plan §14: providers stay behind project-owned ports; no vendor names in domain code |
| 19 levels, `Pre-Level 1` / `Pre-Level 2` | Master plan §2: "Do not hard-code a nineteen-level count in application logic" |
| Full content only for first three levels | APP-0065 / APP-0066 / APP-0067 already authored and reviewed Levels 2–17 |
| Attendance percentage as a headline metric | Master plan §3.3 north star excludes page opens and MCQ-only activity; §§9.2, 16 keep attendance out of canonical learning records |
| Teacher named "Miss Nova" | D-0218 pinned Swathi on 29 August 2026 across prompts, the Azure output contract and six locale catalogues |
| Compute-profile orchestration replacing checkpoints | Master plan §§0.2, 25; `AGENTS.md`; the ADR / decision-request / APO-evidence path |

## Recommended interpretation

Keep the specification as a **product brief**. Decline it as an **architecture**.
Everything it asks for on usage, features and interface is reachable on the
existing stack in weeks; the rewrite it proposes costs months and its declared
benefit (iOS) is deferred by §9.2 in any case.

### A. Daily Mission home — recommend accept

Make the daily loop a first-class surface on Home, targeted at under 15 minutes:

1. today's level session or review, chosen deterministically;
2. a spoken turn with the teacher;
3. a short check;
4. a **streak of meaningful speaking days**, not of app opens.

A day counts toward the streak only when it contains learner speech and a
response or task outcome, matching the §3.3 definition. Opening the app,
playing audio, or answering only MCQs does not count. This is the honest
version of the requested attendance gauge and it strengthens rather than
contradicts the north-star metric.

Add **delayed review** scheduling in the same checkpoint. It is already named
in §3.3 ("one delayed review"), it is the strongest retention mechanism
available, and no competitor in this category ships it.

### B. Progress as a real dashboard — recommend accept

Progress must stop rendering `LevelPathPanel`. It becomes mastery by objective,
speaking minutes and turns, first-attempt versus retry improvement, delayed-review
performance, and recurring-error reduction — the §3.4 learning metrics, all of
which are already recorded. This is the cheapest visible interface win available.

### C. Speak latency pass — recommend accept

Perceived latency, not feature count, is what makes a voice app feel better or
worse than SuperNova. The current turn is hold, release, upload WAV, Azure STT,
Azure OpenAI, Azure TTS, base64 WAV response. Reducing that round trip — streamed
TTS, earlier partial feedback, optimistic UI during upload — will do more for the
comparison than Modules 1–3 combined. No new provider is required.

### D. Client platform — recommend reject Flutter

Staying on the React PWA plus Capacitor 8 preserves the five-destination shell,
hold-to-talk, offline mission bundles and IndexedDB sync, Passport PKCE with the
Android native secure credential store (ADR-0012), the authenticated blob-URL
media path, the Playwright suites, and 1,092 localization keys across six
locales. A Flutter rewrite discards all of it and returns iOS, which §9.2 defers.

If iOS is genuinely required, the cheaper route is Capacitor iOS on the same
React shell, raised as its own checkpoint.

### E. Speech and model providers — recommend partial accept

- **Sarvam STT**: already available. `VOICE_PROVIDER_DRIVER=indic` plus a secret
  file outside git under D-0217. This is a config and credential checkpoint, not
  a rewrite. Recommend accept when keys exist.
- **GPT-4o**: the deployment is env-driven
  (`CONVERSATION_AZURE_OPENAI_DEPLOYMENT`). Naming a model in a product spec has
  no domain effect and should not pin the port.
- **ElevenLabs Multilingual v2**: recommend **defer**. It requires a new adapter,
  a new spend approval, a `ConversationProviderUsagePolicy` allowlist entry and
  an `ai_cost_ledger` CHECK migration. On 29 August 2026 a missing CHECK entry
  for `azure-speech-guidance` returned HTTP 500 on every live turn until
  migration `2026_08_29_000250` was applied; the same failure mode applies here.
  Guidance TTS is currently ledgered at ₹0.00038 per character against a ₹500
  cap (APP-0064). ElevenLabs is materially more expensive per character, and §5
  of the specification proposes unlimited premium conversation at ₹199–299 per
  month. **TTS unit cost decides whether that plan is profitable.** Recommend a
  costed comparison against `kn-IN-SapnaNeural` before any adapter work.

### F. Level naming and depth — recommend reject as specified

Levels stay generically modelled. If the commercial curriculum genuinely sells
19 named checkpoints including two pre-levels, that is **content and labelling**,
expressed through blueprints and locale catalogues — never a hard-coded count in
application logic (master plan §2).

Reject the proposal that Levels 2–17 show "Full content coming soon". That is a
regression of sixteen levels of authored, English-reviewed and Kannada-reviewed
lesson text. The real remaining work is the publication gate, not authoring.

### G. Attendance, Notice Board, and teacher name — recommend reject

- **Attendance percentage**: a self-serve tap-to-check-in gauge measures app
  opens and invites gaming. §§9.2 and 16 deliberately keep attendance separate
  from learning engagement. Replace with the meaningful-speaking-day streak in A.
  Trainer-recorded attendance stays an optional institution module that does not
  write canonical learning records.
- **Notice Board**: remains deferred unless a signed pilot requires it (§2).
- **"Miss Nova"**: recommend keeping **Swathi**. This would be the third teacher
  rename in one week, touching prompts, the Azure output contract and 1,092 keys
  across six catalogues. "Nova" is also close enough to SuperNova to weaken the
  trademark separation recorded in D-0215 and `QUALITY_REVIEW.md`.

### H. What "better than SuperNova" rests on

The defensible advantages are already held and are not in the specification:

| Advantage | Why a pure-AI competitor cannot copy it |
|---|---|
| Owned institute curriculum audio (67 governed MP3s, 17 levels) | Cannot be synthesized; it is a real teacher's recorded course |
| Deterministic marks and auditable evidence | AI never writes mastery; institutes can trust the record |
| Governed bilingual Kannada review | Not machine translation; source-linked revisions and named review |
| Real human 1:1 through the tuition queue | An actual institute with actual trainers, not a marketplace |
| Offline deterministic path | Matters directly for low-cost Android and unreliable data |

Note that provision 8 of the specification puts the first of these at risk. The
teaching audio was re-ingested on 29 August 2026 **from local object storage
only, with no legacy-site access**, under the media rights path (ADR-0016). A new
`LegacyAudioSyncService` scraping `globalspokenenglish.com` would bypass rights
attestation and is not recommended.

## Out of scope unless separately accepted

- Production or public Google Play release.
- iOS, App Store, or any second learner client.
- Paid learner subscriptions, Play Billing, RevenueCat. Entitlements remain
  Phase 2 / `ADMIN-ENT-01`.
- Curriculum publication for Levels 2–17.
- Independent Kannada UI catalogue review.
- Any change to marks, mastery or unlocks based on AI speech scores.
- Always-on microphone, barge-in, or full duplex.
- Replacing the ADR / decision-request / APO-evidence path with compute-profile
  orchestration. Live Azure spend, learner audio and DPDP-relevant handling
  currently depend on it.

## Decision asked of the Accountable Product Owner

1. Accept **A** (Daily Mission home with meaningful-speaking streak and delayed
   review), **B** (Progress dashboard), and **C** (Speak latency pass) as the
   next three checkpoints. Each is independently acceptable.
2. Confirm **D**: the learner client stays React PWA plus Capacitor 8, and
   Flutter stays deferred. If iOS is required, say so and it will be raised as a
   Capacitor iOS checkpoint.
3. Confirm **E**: Sarvam via the existing Indic driver when keys exist;
   ElevenLabs deferred pending a costed comparison against Azure `kn-IN`.
4. Confirm **F**: levels stay generic; if 19 named checkpoints are the commercial
   curriculum, authorize them as content and labelling only. Confirm Levels 2–17
   keep their authored content rather than reverting to placeholders.
5. Confirm **G**: no attendance percentage in canonical records, Notice Board
   stays deferred, and the teacher remains Swathi.

Accepting A and B amends master plan §7.1 (Home and Progress surfaces). Nothing
in this request amends §3.3, §9.2 or the AI-authority rule in §0.3.

## Verification if accepted

```text
make verify
make e2e     # A, B and C each change a critical learner journey
make ai-eval # only if E changes prompts, models or providers
```

New coverage required per checkpoint: streak and delayed-review scheduling must
be deterministic and unit-tested; Progress metrics need organization-scoped and
cross-tenant tests; any provider change needs a `ConversationProviderUsagePolicy`
case and a matching `ai_cost_ledger` CHECK migration test before live traffic.

## Follow-up

- Record accepted items in `docs/DECISIONS.md` as D-0219 onward, with an ADR for
  any master-plan amendment.
- Update `docs/STATUS.md` and `docs/QUALITY_REVIEW.md` at the end of each
  accepted checkpoint.
- Keep the original specification archived as the product brief this request
  interprets.
