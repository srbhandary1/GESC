# DR-0039 — Post-daily-loop experience: pronunciation map, animated tutor, voice pacing, transliteration, language activation

## Status

**Proposed.** Awaiting Accountable Product Owner decision on the five items
below. Deciding this request now is permitted; **implementing it is not** until
checkpoints A, B and C of [DR-0038](DR-0038_PHASE1_DAILY_LOOP_CLIENT_PLATFORM_AND_PROVIDER_REQUEST.md)
are accepted and verified, per the strict sequence the product owner adopted in
the DR-0038 reconciled-brief addendum.

Raised from the product-owner "Master Orchestration Specification (Phase 1 –
Multi-Language Ready)" of 30 August 2026. That specification also re-stated the
Flutter rewrite, the "Miss Nova" rename, an attendance engine with a percentage
gauge and a 150–155 monthly marks bucket, hard-coded 19 levels, a Notice Board,
vendor names as architecture, a legacy-site scraper, and compute-profile
orchestration. The product owner confirmed on 30 August 2026 (structured
selection: "Stale template text — keep the D-0219 decisions, don't reopen")
that those reappearances are stale template text. **D-0219 stands unchanged;
this request does not reopen it.**

## Request

Five capabilities from the specification are genuinely new — not shipped, not
previously decided, and not in conflict with the canonical baseline:

1. an ELSA-style **syllable pronunciation colour map** beside the correction
   card (GREEN / YELLOW / RED per syllable);
2. an **animated tutor** whose mouth and gestures are driven by viseme
   timestamps and gesture triggers in the turn payload;
3. a learner **speech-speed control** (0.5×–1.25×) for spoken tutor output;
4. **Kanglish transliteration** input (Latin-script Kannada typing rendered in
   Kannada script), architecturally mapped for `te`, `ta`, `ml` later;
5. **learner-facing activation of Telugu, Tamil and Malayalam** as base
   languages.

## Current product (what already exists toward each item)

- **Coaching strictness already exists.** Conversation sessions carry coaching
  modes `gentle` / `tutor` / `fluency`; the specification's "Speech Strictness"
  toggle is a UI surface over an existing control, not a new engine.
- **Speech synthesis is already SSML-shaped.** Guidance TTS renders through
  Azure neural voices with a fixed prosody rate of 0.92
  (`GuidanceTtsSsml.php`); a pacing control is a bounded parameter on an
  existing pipeline.
- **The multi-language foundation exists.** Six locale catalogues (`kn-IN`,
  `en-IN`, `te-IN`, `ta-IN`, `ml-IN`, `hi-IN`) with 1,097-key parity; guidance
  TTS voice mapping for all of them (`GuidanceTtsPolicy`); the Indic recorded
  STT adapter auto-detects the spoken language and translates to English, so
  te/ta/ml speech already yields an English turn without per-language routing.
- **The correction payload already carries a language tracker.**
  `priority_correction.explanation_locale` plus `explanation_l1` /
  `explanation_kn`; the specification's proposed JSON adds only
  `phonetic_analysis`, `viseme_timestamps` and `avatar_gesture_trigger` to what
  ships today.
- **The provider needed for items 1 and 2 is already live.** The Azure Speech
  service family used for soft-launch STT/TTS also offers pronunciation
  assessment (per-syllable/phoneme accuracy scoring) and viseme events on
  synthesis. Neither requires ElevenLabs, which stays deferred under D-0219.

## Recommended interpretation, per item

### 1. Advisory pronunciation colour map — recommend accept with guardrails

Add a project-owned pronunciation-assessment port (no vendor types in domain
code, per ADR-0010 discipline) with an Azure adapter and a deterministic fake.
Per consented spoken turn, return an advisory per-syllable accuracy band
rendered GREEN / YELLOW / RED beside the existing correction card.

Guardrails that make this acceptable under the master plan:

- **Advisory only, permanently.** §9.2 defers *authoritative* automated
  pronunciation scoring; this map must never write marks, mastery, streaks or
  unlocks, and the DR-0037 rule that pronunciation scores are not authoritative
  marks continues to apply.
- **No shaming presentation** (§3.2): the map shows placement, not a grade; no
  cumulative "pronunciation score" is displayed in Phase 1.
- **Accent fairness before default-on.** The master plan forbids labelling a
  learner's dialect and requires subgroup evaluation across representative
  Karnataka speech patterns. Ship behind a per-learner opt-in until an
  evaluation across representative speakers shows the bands are not
  systematically punishing Indian-accented English that is fully intelligible.
- **Accessibility**: colour is never the only signal; bands also carry text.
- **Cost discipline**: pronunciation assessment is a new billable usage type.
  It requires a spend approval, a `ConversationProviderUsagePolicy` entry and a
  matching `ai_cost_ledger` CHECK migration **before** live traffic — the 29
  August guidance-TTS incident (HTTP 500 on every turn from a missing CHECK
  entry) is the failure mode to avoid repeating.

### 2. Animated tutor with visemes and gestures — recommend accept, last in order

Drive Swathi's mouth shapes from Azure TTS viseme events and a small
enumerated gesture set (`ENCOURAGING_NOD` and similar) chosen deterministically
from the correction outcome — never free-form model output. Rive or Lottie is a
client implementation choice, not an architecture decision.

Sequence this after the DR-0038 C latency pass: both rework the TTS delivery
path, and streaming audio should land first so visemes ride the same stream
rather than a second contract. The claim-grounding rules adopted in DR-0038
already say lip-sync may not be claimed until built; that stays true until this
ships.

### 3. Speech-speed control — recommend accept, first in order

A per-learner preference (bounded 0.5×–1.25×, default the current 0.92)
applied to the SSML prosody rate for model and guidance speech. Persisted with
ordinary profile preferences, validated server-side, no provider or spend
change. This is the cheapest item and can ship in the first post-C checkpoint.

### 4. Kanglish transliteration input — recommend accept as a bounded input aid

Latin-script input rendered as Kannada script suggestions at the point of
typed input (the "Type instead" path and future text drills). Keep it behind a
project-owned transliteration port; a client-side library is acceptable if it
adds no learner-data egress, otherwise the provider goes through the normal
adapter, spend and DPA path. Advisory input aid only: transliterated text is
learner-confirmed before it becomes an utterance. Architecture must key by
locale so `te`/`ta`/`ml` reuse it, but only Kannada ships in this item.

### 5. Telugu / Tamil / Malayalam learner activation — recommend accept as a gate checklist, not a build

The architecture is ready; what is missing is governance, and §9.2 explicitly
defers ml/ta/te curriculum, speech and explanation support "unless separately
approved". Recommend recording a per-language activation gate rather than any
new code:

1. named independent review of that language's UI catalogue;
2. named academic review of curriculum guidance text in that language;
3. guidance-TTS voice check by a named speaker of the language;
4. AI evaluation cases for L1 explanations in that language (the existing
   suite gates Kannada; each activated language needs equivalents);
5. APO acceptance activating the language in `INTERFACE_LOCALES`.

Until a language passes its gate, it stays exactly where it is today: built,
reviewable, not learner-visible. Note the Kannada UI catalogue itself is still
`pending-independent-review`; it is listed first in its own gate.

## Suggested implementation order (after DR-0038 A/B/C are verified)

1. Speech-speed control (item 3) — smallest, no new provider surface.
2. Pronunciation colour map (item 1) — new port, spend approval, opt-in.
3. Kanglish transliteration (item 4).
4. Animated tutor (item 2) — after the latency pass reshapes TTS delivery.
5. Language activation (item 5) — governance-driven, runs whenever a
   language's reviews complete; independent of 1–4.

## Out of scope

- Reopening anything decided under D-0219: Flutter, the teacher's name,
  attendance percentage or monthly marks buckets, hard-coded 19 levels, Notice
  Board, legacy-site scraping, compute-profile orchestration.
- ElevenLabs (stays deferred pending the costed comparison already recorded).
- Any authoritative pronunciation score, grade or mark.
- Implementing any item before DR-0038 A/B/C are accepted and verified.
- Production, Google Play, or curriculum publication authorization.

## Decision asked of the Accountable Product Owner

1. Accept items **1–5** individually (each is independently acceptable), with
   the guardrails stated above.
2. Confirm the pronunciation map is **advisory and opt-in** until the accent
   fairness evaluation passes.
3. Confirm the implementation order, or state a different one.
4. Confirm item 5 is a governance gate (reviews then activation), not a
   development project.

## Verification if accepted

```text
make verify
make e2e     # items 1-4 change learner journeys
make ai-eval # items 1 and 2 change provider usage or prompts
```

Item 1 additionally requires, before any live call: a spend-approval evidence
file, a `ConversationProviderUsagePolicy` allowlist case, an `ai_cost_ledger`
CHECK migration with a test, and the accent-fairness evaluation plan. Item 5
requires the per-language review evidence recorded in `APPROVALS.md`.

## Follow-up

- Record accepted items in `docs/DECISIONS.md` with an ADR if any master-plan
  section is amended (item 1 likely amends the §7.3 Correction screen row; item
  5 touches §1.1 language baseline).
- Update `docs/STATUS.md` and `docs/QUALITY_REVIEW.md` at the end of each
  accepted checkpoint.
