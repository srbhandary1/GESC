# A4 conversation-model authorization request (draft)

**Status:** Unauthorized draft bound to proposed
[ADR-0010](adr/0010-conversation-model-provider-policy.md).  
**Plan step:** [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) A4  
**Governing slice auth:** [APP-0021](APPROVALS.md#app-0021)

## Purpose

Obtain nested acceptance of ADR-0010 and a bounded APP to implement the
enterprise `ConversationModelProvider` Azure adapter **with recorded HTTP
fakes and fail-closed production**, without authorizing live spend.

## Preconditions

1. Confidence Conversation A0–A3 committed on `codex/confidence-conversation`.
2. ADR-0010 proposed and reviewed.
3. Exact role acceptance of ADR-0010 recorded.

## Proposed authorized scope (after ADR acceptance)

Authorize only:

1. Azure OpenAI (or Azure AI chat deployment) adapter behind
   `ConversationModelProvider`.
2. Allowlisted non-production driver value; production remains `disabled`.
3. Outside-repo credential workspace scaffold (no secrets in git).
4. Recorded HTTP fake tests proving schema mapping to `explanation_l1`.
5. Observability/cost ledger fields already used by fakes.
6. STATUS/DECISIONS/QUALITY_REVIEW updates.

## Explicit non-goals

- Live provider calls or spend
- Demo OpenRouter promotion into ordinary DI
- Tool/search routing
- Production promote / Play
- A5 TTS policy / A6 live locale eval (separate steps)
- PR-012 Role-play

## Proposed statements

### 1) Accept ADR

```text
Accept ADR-0010 conversation-model provider policy as proposed.
```

### 2) Begin adapter implementation (APO)

```text
APO authorize only ADR-0010 adapter implementation with HTTP fakes and
fail-closed production; live spend remains unauthorized until a separate APP.
```

## Recorder notes

After both statements:

1. Mark ADR-0010 accepted with APP evidence.
2. Create APP-0022 (or next id) for adapter implementation.
3. Only then write adapter code on this branch.
4. Do not run live model calls until a later spend APP.
