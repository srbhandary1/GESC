# STUDENT-CONVERSATION-02 — real turn-taking and English-first question help

Status: deployed as `student-conversation-20260925-r2`, with Android 1.0.2/code 3
installed and published; real student outcomes pending. See
[candidate evidence](STUDENT_CONVERSATION_02_EVIDENCE_2026-09-25.md).
Authority: the 25 September 2026 user instruction, explicit temporary encrypted
recovery approval, [APP-0107](APPROVALS.md#app-0107) and
[ADR-0042](adr/0042-interactive-roleplay-and-consented-question-help.md).

## One bounded vertical slice

Replace the synthetic outcome loop with an actual learner/AI dialogue and add
question help inside Speak. Use the existing modular monolith, provider ports,
native secure authentication, web cookie sessions, locale catalogue and shared
React learner experience. Do not redesign primary navigation, billing,
curriculum governance or authoritative learning records.

### Role-play

1. Choose a situation and see the learner/AI role labels. Acknowledge the
   temporary-processing notice explicitly; refusal retains safe exit/other
   learning access. Separately grant/reuse the existing scoped automated
   provider-processing purpose before model/TTS dispatch; recovery agreement
   must not silently grant it or microphone/upload/history permissions.
   Start speaks only the AI partner's opening line.
2. Show **Your turn** with tap recording, replay/repair as supported and Type
   instead. The AI must not auto-fill or perform the learner role.
3. Submit one learner turn with stable idempotency and server-owned state.
   A real grammar error shows an English correction, the actual local-language
   explanation and a retry cue, without playing the next partner line.
4. Retry the same conversational opportunity. Accept valid alternative wording
   and contextually correct short answers, not only exact sentence matches.
   A valid response produces the next AI-only English line and returns control
   to the learner. Model uncertainty/failure must not advance the interaction.
5. Reflection reports only evidence-linked advisory outcomes. No fabricated
   first-objective-met rule. Untested objectives are unassessed. Exiting with a
   pending retry is allowed but never represents mastery or completion.

### Ask Swathi

Provider output is constrained by a strict JSON schema for this flow, including
no grammar correction and no assessed objectives. Existing local validation
remains mandatory. For Role-play retry, validated correction text is the sole
reply; any extra partner draft is discarded before persistence or audio.

1. Enter Ask Swathi under Speak. A question may be English, Kannada or a
   supported local language; preserve its original text and locale evidence.
2. Answer in simple English first, in text and optional English audio. Do not
   treat the question as an English grammar assessment.
3. **I did not understand**, in speech/text or a button, produces a permission
   offer: explain in the selected local language? It does not itself authorize
   a translated explanation.
4. Yes explicitly authorizes that explanation; No keeps English. Show and
   speak the actual response language. Do not auto-accept, leak local-language
   explanation in hidden DOM/audio, or turn a new question into a persistent
   local-language mode. Return to English-first for the next question.
5. Interpret “yes/no” as explanation permission only while an offer is pending.
   It must not alter microphone/provider/research consent, locale or access.

### Invariants

- Server owns dialogue state, retry gate, original turn linkage, role/scenario
  snapshots and entitlement. Client messages cannot assert correctness,
  objective success, provider choice, retention deadline or another tenant.
- Correct learner mistakes without shame. Never use an English translation of
  local-language speech as evidence that the learner produced English.
- Marks/mastery/progression/attendance/retention remain deterministic. AI
  assessment and scenario outcomes are explicitly advisory.
- Existing retry, cancellation, transcript repair, typed fallback and
  text-without-audio behavior must survive network/provider failure.
- The AI must never silently send the next role line while a grammar retry is
  pending. Audio playback obeys the same validated outcome as visible text.
- Keep only bounded same-session context in a dedicated encrypted private
  recovery store for at most 15 minutes per artifact. No expiry extension on
  replay; no body in logs, ordinary cache/backups or persistent client storage.
  Exit/withdrawal/erasure delete it, and exact replay dispatches no new model.
- Admit each modern turn durably before provider I/O. If the outcome cannot
  be recovered after an interruption, fail closed with
  `conversation.turn_outcome_unknown`, not another provider call or a claimed
  zero cost. One accepted logical-turn quota reservation is retained; no new
  pricing, automatic refund or extra retry charge is introduced. The learner
  can recover a committed response or explicitly end and start a new dialogue.
- Update OpenAPI/generated clients with additive compatibility for already
  installed APKs. Legacy historical feedback stays immutable; label its old
  evidence honestly rather than rewriting it as newly assessed.

## Verification matrix

| Area | Required proof |
|---|---|
| Alternation | AI-only opener; no generated learner line; wrong → correction/retry; correct/valid alternative → next partner line |
| Assessment | Evidence-linked objective results; never ordinal/hard-coded; unassessed stays unassessed; no authoritative learning writes |
| Question help | EN/L1 question → English; confusion → permission offer only; Yes → L1; No → English; next question resets English |
| Privacy | Explicit notice acknowledgement; encrypted private bodies; bounded context; no ordinary cache/log/backup/client persistence; expiry/exit/withdrawal/erasure |
| Consistency | Exact replay uses same outcome without dispatch/charge/advance; changed payload conflicts; stale/concurrent updates fail safely |
| Tenancy | Cross-organization and same-organization-other-learner session/turn/artifact access denied |
| Provider safety | Strict schema/locale; prompt-injection/unsafe/uncertain response cannot claim success or bypass retry; one primary and bounded reviewed fallback |
| Speech | Original local-language transcript retained separately from translations; right voice locale; denial/repair/timeout/text fallback; no always-on microphone |
| Shared UX | EN/KN, mobile web/desktop/native Android, 320px/200%/keyboard/scoped axe, cancellation and next-action focus |
| Gates | `make verify`, `make e2e`, `make ai-eval`, `make build`; exact Android artifact tests when packaging |

## Provider implementation reference

Sarvam's [official REST specification](https://docs.sarvam.ai/api-reference/speech-to-text/transcribe),
checked 25 September 2026, documents original-language `verbatim`/`transcribe`
and English `translate` modes for Saaras v3, plus detected `language_code`.
The new dialogue flow must request original transcription and preserve its
provenance; legacy translation cannot stand in for spoken English assessment.
Provider documentation is not live recognition-quality evidence.

## Pilot and limits

Coordinator: **S R Bhandary**, available now, explicitly supplied by the user.
This does not identify participants or constitute their consent. Use
[the pilot packet](STUDENT_CONVERSATION_PILOT_2026-09-25.md) after the candidate
passes technical gates. Record real observations and separate speaking success
from typed fallback. A small formative student cohort does not close the
broader 15–24-person UX-HUM or 40–100-person beta gates.

No live provider evaluation, participant contact, research audio retention,
payment or production-quality pass is inferred from local automation.
