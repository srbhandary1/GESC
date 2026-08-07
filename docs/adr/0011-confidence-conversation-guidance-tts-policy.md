---
status: accepted
date: 2026-08-07
deciders:
  - S R Bhandary (Technical Decision Owner, Security Reviewer, Privacy Reviewer, Tenancy Reviewer — development consolidation)
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: false
amends:
  - ADR-0008
approval_evidence:
  - APP-0024
approval_reference: APP-0024
---

# ADR-0011: Confidence Conversation guidance TTS and AI-only lesson audio

## Status

**Accepted** under Confidence Conversation plan Step A5
([ENTERPRISE_STUDENT_PATH_PLAN.md](../ENTERPRISE_STUDENT_PATH_PLAN.md),
[APP-0021](../APPROVALS.md#app-0021), [APP-0024](../APPROVALS.md#app-0024)).

Accepted on 2026-08-07 (Asia/Kolkata) by `S R Bhandary` exercising consolidated
development roles (Technical Decision Owner, Security Reviewer, Privacy
Reviewer, Tenancy Reviewer) with exact statement:

```text
Accept ADR-0011 Confidence Conversation guidance TTS policy as proposed.
```

Accountable Product Owner authorized A5 implementation with fakes and
text-first fallback under APP-0024. This ADR alone still does **not** authorize
live multi-locale Azure TTS spend, ordinary-app Azure Speech DI expansion,
production promote or Play.

This ADR amends ADR-0008 **product policy for Confidence Conversation guidance
TTS** only. The locked PR-009 synthetic benchmark allowlist (`en-IN` /
`en-IN-NeerjaNeural`) remains unchanged.

## Context

ADR-0008 locks Azure Speech development STT/TTS for the PR-009 synthetic
benchmark to `en-IN` / `en-IN-NeerjaNeural` in `centralindia`, with ordinary
application DI fail-closed (D-0074).

Confidence Conversation (APP-0021) requires:

- AI TTS for English model lines (Listen + Friend replies);
- **no human-recorded lesson audio** in the Confidence Conversation content
  model;
- optional spoken L1 explanations in Phase 1 UI locales, with text always
  remaining the primary and fallback surface;
- demo Azure TTS multi-locale map (D-0092) to stay presentation-only.

A4 delivered the Azure OpenAI conversation-model path (ADR-0010 / APP-0022).
A5 amends speech **product policy** without silently rewriting the locked
PR-009 benchmark allowlist.

## Decision

### Amendments relative to ADR-0008

| Capability | ADR-0008 (unchanged for PR-009 benchmark) | Confidence Conversation product policy (this ADR) |
|---|---|---|
| Learning STT | `en-IN` only | `en-IN` only |
| Model English TTS | `en-IN-NeerjaNeural` | `en-IN` neural (`en-IN-NeerjaNeural` primary) |
| L1 guidance TTS | Out of scope / not in benchmark | Optional; allowlisted neural voices by UI locale; **text-first fallback mandatory** |
| Human lesson recordings | N/A | **Prohibited** for Confidence Conversation spoken lines |
| Ordinary app Azure DI | Fail-closed except speech-benchmark command | Remains fail-closed for multi-locale Azure TTS until a nested spend APP; fakes + text-first ship first |
| Demo Vite `/demo/tts` | Presentation exception (D-0092) | Remains non-evidence |

### Locked Phase 1 guidance voice map

| UI / explanation locale | Azure neural voice (when live multi-locale TTS is later authorized) |
|---|---|
| `en-IN` | `en-IN-NeerjaNeural` |
| `hi-IN` | `hi-IN-SwaraNeural` |
| `kn-IN` | `kn-IN-SapnaNeural` |
| `ml-IN` | `ml-IN-SobhanaNeural` |
| `ta-IN` | `ta-IN-PallaviNeural` |
| `te-IN` | `te-IN-ShrutiNeural` |

Purpose codes:

- `model_en` — English Listen lines and Friend `assistant_reply` (always
  `en-IN` voice);
- `guidance_l1` — optional L1 explanation / warm-up / reflect playback in
  session `ui_locale` (text remains sufficient).

### Content model

Curriculum / Confidence Conversation content supplies **text and learning
objectives only** for spoken lines. No CMS or publication path may require a
human-recorded audio asset for Confidence Conversation Listen, Friend reply or
L1 guidance. Deterministic daily-mission `model_audio` (PR-006) remains a
separate learning path and is not reclassified by this ADR.

### Binding and fallback rules

1. Project-owned `TextToSpeechProvider` remains the only domain port.
2. Deterministic fake TTS may accept the allowlisted voices and still return
   text-only / no playback for CI.
3. Product UI must always show the English model text and L1 explanation text;
   playback failure never blocks the session.
4. Live multi-locale Azure TTS in ordinary DI requires a nested APP (spend +
   evidence). Until then, product path may use on-device browser speech for
   English model lines as a non-evidence interim aid, with text primary.
5. Logs must not contain raw synthesis text beyond digests/length metadata
   already used by speech adapters.

## Alternatives considered

### A. Keep ADR-0008 en-IN-only forever for product Confidence Conversation

Rejected: shy learners need L1-capable guidance surfaces; English model TTS
alone is insufficient for warm-up/reflect comfort, even when correction text
is shown.

### B. Promote D-0092 demo Vite TTS into ordinary Laravel DI

Rejected: demo credentials and Vite middleware are presentation-only (D-0092).

### C. Require human studio recordings for quality

Rejected: product intent and APP-0021 forbid human lesson recordings for this
slice.

## Consequences

### Positive

- Clear AI-only spoken-line policy for Confidence Conversation.
- Preserves PR-009 locked benchmark and ADR-0008 region/STT locks.
- Text-first fallback protects shy learners when TTS is unavailable.

### Negative and trade-offs

- Multi-locale neural quality still needs A6 eval and bilingual review.
- Ordinary live multi-locale Azure TTS spend remains a later gate.
- Browser speech interim is not enterprise verification evidence.

## Security, privacy and tenancy impact

- Same Central India Azure Speech posture as ADR-0008 when live TTS is used.
- No new provider; no training on learner content.
- Organization-scoped turns only; no cross-tenant synthesis context.

## Master-plan amendment

None. This ADR amends ADR-0008 **product policy for Confidence Conversation
guidance TTS** only; it does not change Phase 1 Definition of Done.

## Approval evidence

Recorded under [APP-0024](../APPROVALS.md#app-0024):

- exact ADR acceptance statement;
- APO authorize for fakes / text-first only;
- explicit non-authorization of live multi-locale TTS spend, production and Play.

## Verification

- Unit tests for voice map + purpose selection + unsupported locale fail-closed.
- Fake TTS accepts allowlisted voices; product UI keeps text primary.
- Production remains without ordinary multi-locale Azure TTS DI.
- Demo `/demo/tts` unchanged as non-evidence.

## Follow-up

1. Later spend APP if ordinary-app live multi-locale Azure TTS is required.
2. Step A6 locale eval fixtures for L1 explanation quality (and optional TTS).
3. Step A7 APO exact-commit acceptance of the Confidence Conversation slice.
