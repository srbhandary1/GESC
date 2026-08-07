# Confidence Conversation — verification scope

**Status:** Authorized under [APP-0021](APPROVALS.md#app-0021) (plan Step A1)  
**Date:** 2026-08-07  
**Spec:** [CONFIDENCE_CONVERSATION_SPEC.md](CONFIDENCE_CONVERSATION_SPEC.md)  
**Plan:** [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) Steps A2–A7  
**Baseline:** PR-008–PR-011 accepted; fake conversation providers; production fail-closed

This document defines what must be proven for the enterprise Confidence
Conversation slice. Demo Vite middleware (`/demo/tts`, `/demo/chat`) is
**out of scope** and is not acceptable evidence.

---

## 1. Vertical slice under test

Prove an authenticated learner can complete, on the **ordinary product path**:

1. Warm-up (L1 reassurance + English opener copy),
2. Listen (request English model line via project speech/TTS port — fake
   allowed until A5),
3. Talk (typed and/or spoken with editable transcript),
4. Receive structured `assistant_reply_en` plus separate priority correction
   with `explanation_l1` for the active UI locale,
5. Use confidence controls (retry, type, skip, hint),
6. Reflect (L1 summary + one English carry sentence),

without mutating mastery, attempts, unlocks or publications, and with
organization isolation.

---

## 2. Step mapping

| Plan step | Verification focus |
|---|---|
| A2 | Schema, validator, fake fixtures for six UI locales, OpenAPI/types |
| A3 | Product UI stages + API wiring on fakes; a11y smoke |
| A4 | Nested — only after ADR+APP; HTTP fakes mandatory; live smoke only if APP allows |
| A5 | Done — ADR-0011 / APP-0024; text-first L1; no live multi-locale TTS spend |
| A6 | Done — `friend.l1.locale.v1` hash-locked; shame / L1 / false-correction gates |
| A7 | Exact-commit APO acceptance package |

---

## 3. Tenancy and authorization

Must pass:

- Routes require first-party auth + `LearnerAuthority`.
- Session/turn/correction resources are organization- and membership-scoped.
- Same-organization other-learner access denied.
- Cross-organization access denied.
- Client cannot supply foreign organization/membership IDs to escalate.

Evidence: integration tests extending existing conversation tenancy patterns.

---

## 4. API / contract surface (draft deltas for A2+)

Existing conversation turn APIs remain the base. Expected OpenAPI deltas:

| Area | Change |
|---|---|
| Priority correction | Add `explanation_l1` (and locale binding); deprecate sole reliance on `explanation_kn` |
| Correction `needed` | Explicit boolean; omit teaching pressure when false |
| Confidence session | Optional stage field or dedicated session type `confidence` vs friend |
| Reflect payload | Advisory summary fields (digests if persisted) |
| Coaching mode | Already present — assert Gentle default for confidence entry |

Document version bump required when HTTP responses change. Regenerate
TypeScript clients in the same checkpoint as the OpenAPI change.

Idempotency keys remain mandatory on turn mutations. Responses stay
`private, no-store` where PII-adjacent.

---

## 5. Persistence and privacy

- Continue PR-008 rule: utterance/reply plaintext request-memory-only unless a
  later ADR amends; digests in durable tables.
- Correction suggestion rows may store digests (existing PR-010
  `explanation_kn_digest` → generalize to L1 digest field in A2).
- No raw audio bytes or full transcripts in application logs.
- Memory writes only via explicit learner approve (PR-011).

---

## 6. Behaviour checks (A2–A3)

| ID | Check |
|---|---|
| CC-01 | Gentle default on confidence entry |
| CC-02 | Reply and correction rendered separately |
| CC-03 | `needed=true` requires non-empty `explanation_l1` for active locale |
| CC-04 | Fake fixtures cover `kn`, `hi`, `ml`, `ta`, `te`, `en` |
| CC-05 | Validator rejects shame-ban hits → fallback |
| CC-06 | Skip / type / retry available and do not write mastery |
| CC-07 | Transcript edit is learner-owned input to the model step |
| CC-08 | Offline/AI failure: curated fallback or deterministic path disclosed |
| CC-09 | AI advisory disclosure visible on Practice |
| CC-10 | Cross-tenant denial |

---

## 7. Evaluation checks (A6)

| ID | Check |
|---|---|
| CC-E1 | Locked suite hash; fake providers only unless A4 live APP | Pass — `friend.l1.locale.v1` |
| CC-E2 | False-correction / missing-L1 cases fail the suite | Pass — A6 metrics/gates |
| CC-E3 | Shame-language cases fail closed | Pass — per-locale shame fixtures |
| CC-E4 | Cost rollup aggregate-only; zero on fakes | Pass |
| CC-E5 | `make ai-eval` fails on regression | Pass |

---

## 8. Commands

Normally:

```text
make verify
make ai-eval   # when A6 fixtures land or prompts/orchestration change
```

Do not claim host `make verify` green unless it actually ran successfully in
the recorded environment. Docker/pinned toolchain evidence is acceptable when
host PHP/Node limitations match QUALITY_REVIEW notes.

**Never** cite `make demo` Azure/OpenRouter behaviour as A2–A7 acceptance
evidence.

---

## 9. Accessibility smoke (A3)

- Confidence controls reachable by keyboard.
- Correction card announced with clear heading (English fix vs L1 explanation).
- Do not rely on colour alone for error vs encouragement.
- Target WCAG 2.2 AA for new controls; full audit remains pre-production.

---

## 10. Explicit non-evidence

- Vite demo TTS/chat middleware (D-0092, D-0094)
- Demo Android APK (`com.globalenglish.demo`)
- Unreviewed generative Indic quality anecdotes
- Production or Play readiness claims

---

## 11. Exit for A1

A1 is complete when this file and
[CONFIDENCE_CONVERSATION_SPEC.md](CONFIDENCE_CONVERSATION_SPEC.md) are
committed under APP-0021 and the plan Active step advances to **A2**.
