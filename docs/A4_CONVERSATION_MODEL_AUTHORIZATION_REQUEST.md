# A4 conversation-model authorization request

**Status:** Accepted under [APP-0022](APPROVALS.md#app-0022).  
**Plan step:** [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) A4  
**Governing slice auth:** [APP-0021](APPROVALS.md#app-0021)  
**ADR:** [ADR-0010](adr/0010-conversation-model-provider-policy.md) (accepted)

## Purpose

Obtain nested acceptance of ADR-0010 and a bounded APP to implement the
enterprise `ConversationModelProvider` Azure adapter with recorded HTTP fakes,
fail-closed production, and **capped live development spend** (production
closeness without production promote).

## Accepted statements

### 1) Accept ADR

```text
Accept ADR-0010 conversation-model provider policy as proposed.
```

### 2) Live spend intent (APO)

```text
We can go with live spends as i want to be as close as possible to production.
```

Interpretation recorded in APP-0022: authorize capped non-production live
Azure OpenAI calls for Confidence Conversation adapter smoke/eval; hard
ceiling **₹500 INR** (APO amendment `Amount will be Rs.500`); production
promote and Play remain unauthorized.

## Authorized scope (APP-0022)

1. Azure OpenAI / Azure AI chat deployment adapter behind
   `ConversationModelProvider`.
2. Allowlisted non-production driver `azure-openai-dev`; production remains
   `disabled`.
3. Outside-repo credential workspace `$HOME/ge-conversation-model`.
4. Recorded HTTP fake tests mapping chat JSON → `explanation_l1`.
5. Capped live development spend ≤ ₹500 INR after evidence +
   `LIVE_AUTHORIZATION.txt` phrase.
6. STATUS/DECISIONS/QUALITY_REVIEW updates.

## Explicit non-goals

- Unlimited provider spend
- Demo OpenRouter promotion into ordinary DI
- Tool/search routing
- Production promote / Play
- A5 TTS policy (separate step)
- PR-012 Role-play
