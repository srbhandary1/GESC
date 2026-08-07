# Confidence Conversation — product specification

**Status:** Authorized under [APP-0021](APPROVALS.md#app-0021) (plan Step A1)  
**Date:** 2026-08-07  
**Plan:** [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md)  
**UX design:** [STUDENT_AI_CONVERSATION_LEARNING_FLOW.md](STUDENT_AI_CONVERSATION_LEARNING_FLOW.md)  
**Verification:** [CONFIDENCE_CONVERSATION_VERIFICATION_SCOPE.md](CONFIDENCE_CONVERSATION_VERIFICATION_SCOPE.md)

This specification bounds the enterprise Confidence Conversation slice
(Steps A2–A7). It is not an ADR. Nested A4 (real conversation model) and A5
(multi-locale guidance TTS) still require their own ADRs/APPs before live
provider spend.

---

## 1. Problem

Adult learners who lack confidence for a one-to-one with a real person need a
private, patient, two-way English practice partner that:

- speaks English models via AI TTS (no human-recorded lesson audio);
- corrects grammar without shaming;
- explains the correction in the learner’s interface language;
- never claims marks, mastery or unlocks from AI judgment.

---

## 2. Audience and locales

| Dimension | Value |
|---|---|
| Audience | Adults 18+ |
| Learning language | English (`en-IN`) |
| Interface / explanation languages | `kn-IN` (default), `hi-IN`, `ml-IN`, `ta-IN`, `te-IN`, `en-IN` |
| Speech recognition for learning turns | English (`en-IN`) |
| Lesson audio | AI TTS only |

UI catalogue availability for `ml`/`ta`/`te`/`hi` remains ADR-0005 preview
governance until language/content review; the **schema** must still carry
`explanation_l1` for the active UI locale so A2–A6 can evaluate all six.

---

## 3. Session promise

> Open in my language → warm-up → hear AI English → talk back and forth →
> one clear English fix + L1 explanation → reflect → return tomorrow.

Success metrics for a shy learner (product, not only engineering):

1. they spoke (or typed) at least once;
2. they were understood (meaning-first reply);
3. they got at most one priority improvement;
4. they can retry without pressure;
5. they know the next small step.

---

## 4. Coaching modes

| Mode | Default? | Behaviour |
|---|---|---|
| Gentle Friend | **Yes** | Natural chat; ≤1 correction after the turn; retry optional |
| Tutor | No | Shorter chat; explain; recommend retry |
| Fluency | No | Longer turns; fewer interruptions; summary at end |

Learner may change mode mid-session. Mode is stored on the turn response and
advisory suggestion digests (existing PR-010 pattern).

---

## 5. Session stages

| Stage | Purpose | Marks? |
|---|---|---|
| Warm-up | L1 reassurance; one easy English opener | No |
| Listen | AI TTS speaks 1–2 short English model lines | No |
| Rehearse | Shadow or answer once; editable transcript | No |
| Talk | 4–8 two-way Friend turns | No (AI advisory) |
| Reflect | L1 summary + one English carry sentence | No |

Deterministic daily-speaking missions (PR-006) remain the path for
authoritative attempts. Confidence Conversation must not write mastery or
unlock records.

---

## 6. Turn contract (normative for A2)

Every model turn returns a project-owned structured object. Minimum fields:

```json
{
  "assistant_reply_en": "string — spoken English reply",
  "understood_intent": "string — short safe label",
  "coaching_mode": "gentle | tutor | fluency",
  "safety_state": "ok | fallback",
  "priority_correction": {
    "needed": true,
    "learner_span": "optional span from learner text",
    "correction_en": "corrected English",
    "error_category": "grammar.tense.past",
    "explanation_l1": "explanation in active UI locale",
    "encouragement_l1": "optional short encouragement in UI locale",
    "retry_recommended": true
  }
}
```

### Migration from PR-010

PR-010 uses `explanation_kn` only. A2 must:

- introduce `explanation_l1` (+ locale tag or rely on session UI locale);
- keep backward-compatible digests during transition if needed;
- reject empty `explanation_l1` when `needed=true`;
- keep reply and correction logically separate in API and UI.

### Validation rules

- `assistant_reply_en` required, English-facing, no HTML.
- When `needed=false`, correction teaching fields may be null/omitted; do not
  pressure the learner.
- When `needed=true`, `correction_en`, `error_category`, and `explanation_l1`
  are required.
- Gentle mode: at most one priority correction object per turn.
- Shame-language ban list (non-exhaustive): insults about accent, intelligence,
  “bad English”, public ranking taunts. Hits → `safety_state=fallback` and
  curated safe turn.
- Validator failure → curated fallback turn; never raw provider text to UI.

---

## 7. Confidence controls (required UI)

Always available during Talk:

| Control | Behaviour |
|---|---|
| Say that again | Re-prompt without penalty |
| Type instead | Text input path; same turn contract |
| Skip this turn | Advance without correction pressure |
| Hint in my language | Short L1 scaffold; does not set marks |
| Edit transcript | Learner-owned text continues the turn |

Silence after ~8s → soft L1 prompt; never a timeout scold.

---

## 8. Voice and providers (policy bounds)

| Concern | A1–A3 (this APP) | Later nested gate |
|---|---|---|
| Model replies | Deterministic fake ConversationModel | A4 ADR+APP |
| English TTS for listen/reply | Existing speech ports / fakes | A5 if expanding ordinary DI |
| L1 explanation playback | Text-first acceptable | A5 optional TTS |
| Demo Vite `/demo/tts` `/demo/chat` | Not evidence | Never |

No human-recorded lesson assets in the Confidence Conversation content model.
Curriculum (when present) supplies text and objectives only for spoken lines.

---

## 9. Privacy, tenancy, authority

- Organization-scoped sessions/turns; `LearnerAuthority` on every route.
- Cross-tenant and same-org other-learner access fail closed.
- Persistent conversation stores digests / safe metadata per PR-008; no raw
  sensitive audio in ordinary logs.
- Memory facts remain learner-visible, explicit-approve only (PR-011).
- AI must not mutate `learner_attempts`, `mastery_states`, review schedules or
  publications.

---

## 10. Copy and emotional design

- Meaning-first assistant reply, then separate correction card.
- Encouragement is specific and small (“Meaning clear. One small change.”).
- Progress is private (“You spoke 4 turns today”), not leaderboards.
- Disclose AI / advisory boundary on the Practice surface.

### Shame ban-list (eval + validator)

Reject or fallback if model output contains (case-insensitive examples):

- “stupid”, “bad English”, “thick accent”, “you will never”, mocking
  transliteration of the learner’s L1;
- public comparison (“worse than other students”);
- forced exposure (“share your mistake with the class”).

---

## 11. Explicit non-goals (remain out of A1–A7 unless nested APP)

- PR-012 Role-play stage machine as the primary shy-learner entry
- Production / Play release
- Unlimited provider spend
- Dialect labeling of the learner
- Auto-ingesting private life details into memory
- Live weather/search tools inside Confidence Conversation
- Claiming free or unpaid models are flawless in all Indic languages
- Authoritative automated scoring of conversation turns

---

## 12. Acceptance for Step A7 (preview)

APO may accept the slice only when verification scope checks pass and known
limitations are recorded. Nested A4/A5 may still be “authorized but not live”
limitations if the vertical slice is complete on fakes + contracts.
