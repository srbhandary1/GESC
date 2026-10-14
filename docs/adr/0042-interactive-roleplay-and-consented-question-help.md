---
status: accepted
date: 2026-09-25
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: true
amends:
  - ADR-0007
  - ADR-0038
approval_evidence:
  - APP-0107
approval_reference: Explicit role-play and English-first question-help request, followed by approval of temporary encrypted recovery and instruction to continue
---

# ADR-0042: Interactive Role-play and consented local-language question help

## Status and authority

Accepted for bounded implementation of `STUDENT-CONVERSATION-02` under
[APP-0107](../APPROVALS.md#app-0107). The human requested AI-first alternating
Role-play, correction and retry before continuation, English-first question
answers with an explicit offer/acceptance before local-language explanation,
and equivalent web/Android behavior. The human separately approved encrypted
private recovery for up to 15 minutes per turn. This is not independent
academic, security, privacy, language or accessibility acceptance, nor evidence
that a representative pilot has run.

## Product decision

1. Interactive Role-play opens with only the AI partner's line. It never
   manufactures a learner utterance or speaks both roles as actual practice.
   A clearly labelled optional example can demonstrate a dialogue separately.
2. The learner speaks or types the next line. The model's bounded grammatical
   assessment is advisory. A meaningful grammar error produces corrected
   English plus an explanation in the learner's selected guidance language;
   the same conversational opportunity remains open for retry.
3. The AI partner answers and advances only after an acceptable learner
   response. A legitimate alternative, short contextual answer or Indian
   English variant must not fail merely for differing from a model sentence.
   Stylistic preferences must not create an endless correction gate.
4. Uncertain transcription, unsafe/malformed model output, provider failure or
   expired recovery must not imply grammatical success. Keep correction,
   transcript repair, typed fallback and safe exit available. Exiting is not a
   failed mark; it cannot be used to forge scenario completion.
5. Replace ordinal prototype objective feedback with feedback linked to actual
   learner turns and the pinned scenario/objective versions. Untested objectives
   remain explicitly unassessed. No conversation assessment writes marks,
   mastery, unlocks, review schedules, attendance or entitlements.
6. Add **Ask Swathi** inside Speak, under the same server-enforced Free Speech
   access as Friend. Questions may be in English or a supported local language.
   The initial answer is English. If the learner indicates non-understanding
   through speech/text or the explicit control, offer local-language explanation.
   Explain in that language only after affirmative acceptance; decline keeps
   English. A later question starts English-first again. Preserve the original
   native/code-mixed transcript; never grade its translation as learner English.
7. This English-first, permission-led support policy supersedes ADR-0038's
   Kannada-answer-first product policy. It does not change that ADR's provider,
   safety, entitlement, original-transcript, retention or latency safeguards.
   Kannada remains the initial local-language focus, not a claim that every
   locale/provider combination has passed live evaluation.
8. Android and web use the same React interaction and authenticated API
   contract. A main reply must carry its actual locale; local-language text
   cannot be placed in an English-labelled field or spoken with an English
   voice merely because the original answer was English.

## Recovery and privacy decision

The approved exception is a dedicated encrypted private conversation-recovery
artifact, **not** an ordinary cache, log, analytics record or transcript-history
feature. It contains only the bounded same-session context and validated
response needed for turn-taking, correction/help and exact request replay.

- Lifetime is no more than 15 minutes per turn/artifact, measured from creation;
  reads and retries do not extend expiry. Expired content is unavailable and
  automatically deleted; outage/deletion failure must remain visible.
- Delete on explicit exit and processing-consent withdrawal, with expiry as
  the crash/disconnection backstop. Integrate existing account/erasure controls.
- Bind every artifact to the exact organization, learner membership, session
  and turn; enforce same-organization-other-learner and cross-tenant denial.
- Store only opaque references, digests, expiry and content-free state in
  ordinary database records. Exclude content from ordinary caches, database
  backups, release archives, logs, audit, telemetry and browser/native
  persistent storage. Encrypt private bodies with project-owned facilities.
- A clear updated notice and explicit learner acknowledgement are required
  before the new flow processes/retains its temporary context. Do not silently
  reinterpret an older consent notice or preselect consent.
- A duplicate request replays its validated saved outcome; it must not invoke
  STT/model again, charge another logical turn, or advance dialogue twice.
  Expiry returns an honest recover/restart path, not a new hidden dispatch.
- No training, human research use, long-term memory or saved history is added.
  Existing separately consented memory/history and raw-audio rules are unchanged.

ADR-0007 is amended only to permit this explicitly noticed private temporary
context/replay artifact within the stricter 15-minute limit. Its prohibitions
on ordinary raw-content caches/logs, unconsented research and broader retention
remain in force.

## Delivery and verification

Implement one complete vertical slice under
[STUDENT-CONVERSATION-02](../STUDENT_CONVERSATION_02_SPEC.md), including additive
schema/API compatibility, generated clients, tenant/replay/expiry/deletion
tests, provider-output and prompt-injection safety tests, correction/alternative
answer evaluations, EN/KN UI, voice/text recovery, and web/Android journeys.

The representative pilot is a separate evidence activity led by S R Bhandary,
who reported availability now. Participant consent, actual participants,
reviewers and observed results must be recorded truthfully. Synthetic tests
are not representative student evidence, and research consent is separate
from agreeing to receive a local-language explanation.

## Master-plan amendment

Amends §§1.1, 4.1, 4.4, 14.2 and the §32 summary to describe AI-first correction-gated Role-play,
English-first Ask Swathi with affirmative local explanation, and the bounded
encrypted private recovery exception above. Deterministic learning authority
and the broader validation/release gates are unchanged.

## Owner clarification — 25 September 2026, DARK-TALK-05

The owner requested optional selected-language reading below Free Speech AI
bubbles. The explicit “Read in my selected language” action is affirmative
permission for that exact reply. Reuse the same scoped, expiring recovery,
safety and idempotency controls. This text-only aid does not advance the
conversation or synthesize audio; its model usage is metered separately.
Master-plan §4 now documents this bounded application of the existing policy.
No new retention, authentication, tenancy or assessment authority is introduced.

## Owner clarification — 25 September 2026, LEVELS-BRIEF-06

The owner requested short, easy AI replies to reduce comprehension burden and
speech/model usage. Master-plan §4.1 specifies one simple sentence normally,
at most two sentences/30 words/240 Unicode characters; correction guidance is
one sentence/20 words/160 characters without extra encouragement. Apply the
same limit to English and consented local-language replies before TTS. Preserve
source meaning, exact roleplay corrections and safety/assessment constraints;
do not chop text or silently score an invalid response. STT input metering and
actual speaking/listening allowances are unchanged.

## Lesson-help application — 26 September 2026

LEARN-LEVEL-08 applies the same short English-first, consented explanation and
private-recovery flow to lesson-bound tutoring through the authorized level/part
endpoint. The attached blueprint provides pinned lesson context; no client lesson
text or quiz answers are accepted as context. Lesson access governs this entry;
Speak's general Ask Swathi still requires Free Speech access. See the ADR-0025
clarification and master §4.2. This is advisory help, not the Skill Focus drill or
a new source of authoritative learning evidence.
