# PR-013 operator console authorization request

**Status:** Accepted begin-implementation under [APP-0028](APPROVALS.md#app-0028).  
**Plan step:** [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) C0→C1  
**Master plan:** §26 PR-013 — Operator console and privacy operations; §8.2 minimum  
**Verification:** [PR-013_OPERATOR_VERIFICATION_SCOPE.md](PR-013_OPERATOR_VERIFICATION_SCOPE.md)  
**Prior acceptance:** PR-012 synthetic [APP-0027](APPROVALS.md#app-0027) at
`0fb6f2953580ed6154e4d895e128c46167c6f5d4`

This document is the governing authorization request for APP-0028. It is
**begin-implementation only** — not acceptance of completed PR-013 work.

## Purpose

Authorize one complete **bounded** operator vertical slice so pilot staff can
move synthetic curriculum through review → publish and see an auditable trail
**without database access**. This is deliberately narrower than full master-plan
§8.2 / PR-013 (sampled speaking review and privacy ops stay gated).

## Preconditions (satisfied)

1. PR-012 synthetic Role-play accepted under APP-0027 with recorded limitations.
2. Curriculum authoring/review/publish domain commands already exist (test-backed;
   no operator HTTP/UI yet).
3. Append-only `audit_events` write path exists (no authorized operator read yet).
4. Organization membership types and curriculum permission keys exist in foundation
   seed (trainer / org-admin / content-review role packs still need product wiring).

## Proposed authorized scope (first APP)

Authorize only:

1. Seeded synthetic pilot organization role packs for trainer / organization_admin /
   content-reviewer using existing curriculum permission keys (extend catalogue
   only if required for audit read).
2. Thin org-scoped operator HTTP APIs wrapping existing content commands:
   draft/status list, EN/KN review decision, publish/rollback status.
3. Authorized, org-scoped **audit event list** for those privileged actions
   (digests / safe metadata only — no raw learner conversation content).
4. Minimal ordinary-product operator web shell (not Vite `/demo/*`): review queue,
   publish status, audit trail.
5. Cross-tenant isolation tests, OpenAPI/generated clients, STATUS / DECISIONS /
   QUALITY_REVIEW / plan updates.

## Explicit non-goals (remain unauthorized)

- Consent-gated sampled speaking / transcript / audio review (blocked by
  ADR-0007 until a separate amendment + APP)
- Learner support diagnostics dashboard (“learners needing help”, failed sync)
- CSV export of learner PII
- Operator-side recording deletion, AI memory ops, or account-erasure workflows
- Full content studio (media rights, glossary UI, broad program editing surface)
- Institution-operations pack (attendance, notices, billing) — master plan §8.3
- Expanding live Azure OpenAI / multi-locale TTS spend beyond existing APPs
- PR-014 production Android / Play, PR-015 production hardening
- Production deployment or real learner production data

## Accepted APO begin-implementation statement

```text
APO authorize only PR-013 bounded operator content-workflow and audit slice under
PR-013_OPERATOR_AUTHORIZATION_REQUEST.md and
PR-013_OPERATOR_VERIFICATION_SCOPE.md.
```

Recorded as [APP-0028](APPROVALS.md#app-0028) / D-0107. Active plan step = C1.
Bounded operator HTTP/UI implementation is authorized and landed under this APP;
exact-commit acceptance remains a later APO gate.

