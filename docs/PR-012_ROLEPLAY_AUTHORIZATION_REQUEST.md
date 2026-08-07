# PR-012 role-play authorization request

**Status:** Accepted begin-implementation under [APP-0026](APPROVALS.md#app-0026).  
**Plan step:** [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) B0→B1  
**Master plan:** §26 PR-012 — Role-play framework and pilot content integration  
**Verification:** [PR-012_ROLEPLAY_VERIFICATION_SCOPE.md](PR-012_ROLEPLAY_VERIFICATION_SCOPE.md)  
**Prior acceptance:** Confidence Conversation [APP-0025](APPROVALS.md#app-0025) at
`5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b`; PR-011 [APP-0020](APPROVALS.md#app-0020)

This document is the governing authorization request for APP-0026. It is
**begin-implementation only** — not acceptance of completed PR-012 work.

## Purpose

Authorize one complete **synthetic** Role-play vertical slice before approved
curriculum modules or expanded live provider spend: Observe → Rehearse →
Perform → Reflect, with scenario objectives and advisory feedback evidence,
reusing Confidence Conversation / Friend foundations.

## Preconditions (satisfied)

1. PR-011 accepted under APP-0020 with recorded limitations.
2. Confidence Conversation A1–A7 accepted under APP-0025 with recorded
   limitations (structured turn + L1 explanation + locale eval gates).
3. Verification scope draft committed
   ([PR-012_ROLEPLAY_VERIFICATION_SCOPE.md](PR-012_ROLEPLAY_VERIFICATION_SCOPE.md)).
4. Content path for this APP: **disclosed synthetic** role-plays only
   (`development_only` / `synthetic_unreviewed`). Commissioned specialist
   content remains gated by DR-0007 / separate content review.

## Proposed authorized scope

Authorize only:

1. Server stage state machine: Observe → Rehearse → Perform → Reflect.
2. Synthetic `scenario_objectives` and completion criteria fixtures
   (`development_only` / `synthetic_unreviewed` disclosed in product UI).
3. Session snapshotting of persona and scenario versions (extend Friend
   patterns for role-play mode).
4. Learner feedback records linked to objective and scenario evidence
   identifiers — **advisory only**; must not mutate mastery, attempts, review
   schedules or publications.
5. Learner Practice UI for the four stages (ordinary product path, not demo
   Vite).
6. Reuse of Confidence Conversation structured correction fields
   (`explanation_l1` / `explanation_locale`) where Reflect surfaces a
   priority correction; fake conversation providers by default.
7. Verification scope, OpenAPI/generated clients, organization scoping and
   cross-tenant tests; STATUS / DECISIONS / QUALITY_REVIEW updates.

## Explicit non-goals (remain unauthorized)

- Expanding ordinary live Azure OpenAI DI or spend beyond existing APP-0022
  gates (₹500 INR, operator-gated, non-production)
- Live multi-locale Azure TTS spend / ordinary Azure Speech DI expansion
  beyond ADR-0008 / ADR-0011
- Human-approved curriculum, transcript or audio modules
- Full content-studio HTTP/UI product surface
- PR-013 operator console, PR-014 production Android/Play, PR-015 production
  hardening
- Production deployment, Google Play release or real learner production data
- Waiver of independent legal / security / privacy / tenancy / accessibility /
  language / vendor / release review

## Accepted APO begin-implementation statement

```text
APO authorize only PR-012 bounded synthetic role-play under
PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md and
PR-012_ROLEPLAY_VERIFICATION_SCOPE.md.
```

Recorded as [APP-0026](APPROVALS.md#app-0026) / D-0104. Active plan step = B1.
