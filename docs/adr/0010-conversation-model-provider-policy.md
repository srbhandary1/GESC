---
status: proposed
date: 2026-08-07
deciders: []
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: false
approval_evidence: []
approval_reference: null
---

# ADR-0010: Conversation-model provider for Confidence Conversation

## Status

**Proposed** under Confidence Conversation plan Step A4
([ENTERPRISE_STUDENT_PATH_PLAN.md](../ENTERPRISE_STUDENT_PATH_PLAN.md),
[APP-0021](../APPROVALS.md#app-0021)).

This ADR is **not** accepted and authorizes **no** adapter binding, credential,
live request, spend, production promote or Play release until:

1. the roles below accept this exact ADR artifact; and
2. a nested APP authorizes bounded implementation and (separately) any spend.

Required acceptance roles (development consolidation allowed; independent
specialist reviews remain mandatory before production):

- Technical Decision Owner
- Security Reviewer
- Privacy Reviewer
- Tenancy Reviewer

Accountable Product Owner must separately authorize begin-implementation and
any provider spend.

## Context

Confidence Conversation Steps A0–A3 delivered:

- structured turn contract with `assistant_reply` and
  `priority_correction.explanation_l1` for all Phase 1 UI locales;
- product Practice UI stages Warm-up → Listen → Talk → Reflect on
  **deterministic fake** conversation-model adapters;
- production DI remaining fail-closed (`CONVERSATION_PROVIDER_DRIVER=disabled`
  by default).

Demo-local OpenRouter chat (D-0094) and Azure Speech TTS (D-0092) are
presentation-only and must not become ordinary Laravel bindings.

The master plan requires:

- AI behind project-owned ports (no provider SDK types in domain);
- structured, validated, non-authoritative AI output;
- India-aligned privacy posture (DPDP) with qualified legal review before
  production;
- evaluated prompts/models with cost control;
- a later path to open-weight / on-prem without domain rewrites.

ADR-0008 already locks **Azure Speech** for STT/TTS development in
`centralindia`. Conversation-model inference is a separate capability and must
not silently reuse speech credentials or demo OpenRouter keys.

## Decision

### Selected development conversation-model stack

Subject to exact-artifact acceptance and the evidence gates below, the first
enterprise conversation-model path is:

| Capability | Locked development selection |
|---|---|
| Cloud | Microsoft Azure (client-owned subscription) |
| Product | Azure OpenAI / Azure AI model deployment exposing a **chat-completions** or **responses** HTTP API |
| Region preference | India region with documented residency for the chosen deployment (prefer alignment with Speech `centralindia` when a suitable model deployment exists; otherwise the nearest Azure OpenAI region explicitly accepted in the spend APP with DPA/subprocessor evidence) |
| Interface | Project-owned `ConversationModelProvider` adapter only |
| Output | JSON matching the Confidence Conversation turn contract (`assistant_reply` English; `priority_correction` with `explanation_l1` in session `ui_locale`) |
| Validation | Existing `ConversationOutputValidator` + curated fallback on schema/safety failure |
| Authority | Advisory only — must not mutate marks, mastery, attendance, unlocks or publications |
| Production default | Fail-closed (`disabled`) until a later promote APP |

Exact model deployment name, API version and region endpoint are **not** fixed
in this ADR. They must be recorded in the nested spend/live APP before any
live request, after a fresh provider-evidence snapshot.

### Binding and credential rules

1. Ordinary app DI may bind a real adapter only when
   `CONVERSATION_PROVIDER_DRIVER` is an allowlisted non-production value
   (for example `azure-openai-dev`) **and** environment gates pass.
2. Credentials live only in an outside-repo workspace (same pattern as
   `$HOME/ge-speech-benchmark`), mode `0600`, never in the git tree, browser
   bundle, Capacitor preferences or demo Vite middleware.
3. Demo OpenRouter (`/demo/chat`, D-0094) remains a separate presentation path
   and must never be the production or ordinary DI driver.
4. No tool/search/web browsing tools in Confidence Conversation turns for this
   ADR scope.
5. Prompt versions remain pinned in `prompt_versions` with body digests; live
   calls must send the active digest and record it on advisory suggestion rows.
6. Logs must not contain raw transcripts, full model replies, L1 explanations
   or secrets — digests and safe metadata only (PR-008/PR-010 pattern).

### Eval and promote gates

Before claiming student-helpful live quality:

1. `make ai-eval` remains green on deterministic fakes.
2. A locked live-eval subset (or recorded HTTP fixtures) proves schema validity,
   shame-ban rejection and L1 explanation presence for each UI locale under the
   capped spend APP.
3. Production promote requires a separate APP after A6 locale eval hardening and
   release reviews — not this ADR alone.

## Alternatives considered

### A. Continue fake-only forever

Rejected for the enterprise product path: shy learners need real adaptive
conversation. Fakes remain mandatory for CI and fail-closed production default.

### B. Promote demo OpenRouter free models into ordinary Laravel DI

Rejected. Free/shared routing lacks enterprise DPA certainty, residency
control, spend predictability and Indic-quality guarantees. D-0094 explicitly
forbids ordinary DI.

### C. Direct OpenAI.com API from the API servers

Deferred. Possible later if DPA/residency/legal accept, but first preference is
client-owned Azure alignment with existing Speech tenancy and India posture.

### D. On-prem / open-weight first

Deferred to the master-plan later path. Domain ports must stay provider-neutral
so this remains possible without rewriting Confidence Conversation.

## Consequences

### Positive

- Clear enterprise path distinct from demo exceptions.
- Reuses structured validation and advisory persistence already shipped.
- Preserves fail-closed production and provider portability.

### Negative and trade-offs

- Azure OpenAI India availability may force a non-`centralindia` region; that
  requires explicit APO/legal acceptance in the spend APP.
- Live Indic explanation quality will need A6 eval and bilingual review.
- Additional vendor cost and credential operations.

## Security, privacy and tenancy impact

- Organization-scoped turns only; no cross-tenant model context.
- Learner transcript text is personal data: process under ADR-0007 consent
  where audio-derived; typed turns still require purpose limitation and
  minimization.
- Provider must be contracted for no training on learner content for the
  product use case (mirror ADR-0008 posture).
- Kill switch: driver `disabled` + config flag; spend cap enforced outside the
  request path where possible.
- Abuse: rate limits and idempotency on turn create remain mandatory.

## Authoritative records and consistency impact

- AI remains non-authoritative.
- Correction suggestion rows store digests only.
- No change to mastery/attempt unlock semantics.

## API, client and operational impact

- No OpenAPI change required for the first adapter if response shape stays
  within 0.9.0 `PriorityCorrection`.
- Observability: provider key, model deployment id, latency, fallback flag,
  estimated cost microunits (existing ledger pattern).
- Rollback: set driver to `disabled` or `fake`.

## Master-plan amendment

This ADR does not amend the master plan. It selects the first managed
conversation-model provider under existing provider-port principles (§§10, 14,
26).

## Approval evidence

When accepted, record APP entry with:

- exact ADR file hash/commit;
- roles exercised;
- exact human statement;
- explicit non-authorization of unlimited spend, production promote and Play.

## Verification

- Unit/integration tests with recorded HTTP fakes for the Azure adapter.
- Production config test: real driver cannot activate without allowlisted env.
- `make ai-eval` on fakes remains green.
- Live smoke only after nested spend APP.

## Follow-up

1. Nested APP: accept this ADR + authorize adapter implementation (HTTP fakes
   first).
2. Nested APP: credential workspace + capped spend + optional one live smoke.
3. Step A5: multi-locale guidance TTS policy vs ADR-0008.
4. Step A6: locale explanation eval fixtures against live or recorded outputs.
5. Later: open-weight/on-prem benchmark without domain changes.

## Proposed APO / role acceptance statements

Technical/Security/Privacy/Tenancy (consolidated development roles):

```text
Accept ADR-0010 conversation-model provider policy as proposed.
```

Accountable Product Owner (begin adapter implementation only, after ADR
acceptance):

```text
APO authorize only ADR-0010 adapter implementation with HTTP fakes and
fail-closed production; live spend remains unauthorized until a separate APP.
```
