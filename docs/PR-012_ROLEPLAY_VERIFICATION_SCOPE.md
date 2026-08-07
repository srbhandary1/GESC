# PR-012 role-play verification scope

**Status:** Authorized under [APP-0026](APPROVALS.md#app-0026). Bound to
[PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md](PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md).  
**Prior slice:** Confidence Conversation accepted under
[APP-0025](APPROVALS.md#app-0025).

## Authority and boundary

PR-012 may be authorized only by a distinct APP after Confidence Conversation
acceptance. The intended checkpoint is master-plan §26 PR-012:

- Observe / Rehearse / Perform / Reflect flow;
- scenario goals and completion criteria;
- pilot role-plays (**synthetic first** for this APP);
- content studio support (deferred unless the APP explicitly includes a
  minimal authoring surface);
- approved modules integrated incrementally (out of scope for the first
  synthetic APP).

Acceptance criteria from the master plan:

- scenario and persona versions are snapshotted in sessions;
- learner feedback is linked to objective and scenario evidence.

## Vertical slice (when authorized)

1. Authenticated learner starts a disclosed synthetic role-play session on the
   ordinary product Practice path.
2. Server pins immutable persona and scenario version IDs/hashes.
3. Learner progresses through Observe → Rehearse → Perform → Reflect with
   deterministic fake providers by default.
4. Scenario objectives and completion criteria are evaluated without writing
   mastery or attempt authority.
5. Reflect surfaces priority corrections (reuse `explanation_l1` /
   `explanation_locale` when a correction is needed) and objective/scenario-
   linked feedback evidence IDs.
6. Exact replay and tenancy isolation mirror PR-008 / PR-010 / Confidence
   Conversation patterns.
7. Demo Vite `/demo/*` paths remain non-evidence.

## Reuse

- Persona/scenario/session foundation — PR-008
- Orchestration, corrections, coaching modes — PR-010
- Learner memory allowlist — PR-011
- Structured L1 correction + locale eval gates — Confidence Conversation
  (APP-0025)
- Curriculum publication foundation — PR-005 domain commands only

## Schema expectations (when authorized)

- Lift or extend CHECK constraints that currently force `mode = 'friend'` for
  role-play fixtures only under `development_only` / `synthetic_unreviewed`.
- Add `scenario_objectives` (or equivalent) and session stage state.
- Feedback evidence links must not become authoritative learning records.

## Verification (when authorized)

| ID | Check |
|---|---|
| RP-01 | Stage transitions Observe → Rehearse → Perform → Reflect are enforced | Pass — RoleplaySessionTest |
| RP-02 | Persona/scenario versions snapshotted on session start | Pass |
| RP-03 | Objectives/completion criteria evaluated without mastery writes | Pass |
| RP-04 | Feedback evidence IDs link to objective + scenario (advisory) | Pass |
| RP-05 | Cross-tenant and same-org other-learner denial | Pass |
| RP-06 | OpenAPI/generated-client drift clean | Pass — OpenAPI 0.10.0 |
| RP-07 | Practice UI stage journey unit + Chromium coverage | Unit pass; Chromium not claimed this session |
| RP-08 | `make verify` (and `make ai-eval` if prompts/orchestration change) | Not claimed host/CI; focused Docker evidence recorded |

Evidence may use Docker/pinned toolchains when host PHP lacks `pdo_pgsql`; do
not claim host `make verify` green unless it actually passed.

## Explicit non-goals

- Real LLM providers beyond existing APP-0022 operator-gated gates
- Live multi-locale Azure TTS spend
- Human-approved pilot modules
- Full content studio
- Production / Play

## Exit gate

PR-012 synthetic implementation is ready for APO exact-commit acceptance only
when the authorized verification scope passes and recorded limitations preserve
that approved content, expanded live providers and production remain
separately gated.
