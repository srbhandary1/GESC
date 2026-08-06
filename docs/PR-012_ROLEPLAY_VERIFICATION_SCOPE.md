# PR-012 role-play verification scope (draft)

**Status:** Unauthorized draft bound to
[PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md](PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md).
Not an implementation authorization.

## Authority and boundary

PR-012 may be authorized only by a future APP after PR-011 acceptance. The
intended checkpoint is master-plan §26 PR-012:

- Observe / Rehearse / Perform / Reflect flow;
- scenario goals and completion criteria;
- pilot role-plays (synthetic first);
- content studio support (deferred unless the APP explicitly includes a
  minimal authoring surface);
- approved modules integrated incrementally (out of scope for the first
  synthetic APP).

Acceptance criteria from the master plan:

- scenario and persona versions are snapshotted in sessions;
- learner feedback is linked to objective and scenario evidence.

## Vertical slice (proposed)

1. Authenticated learner starts a disclosed synthetic role-play session.
2. Server pins immutable persona and scenario version IDs/hashes.
3. Learner progresses through Observe → Rehearse → Perform → Reflect with
   deterministic fake providers.
4. Scenario objectives and completion criteria are evaluated without writing
   mastery or attempt authority.
5. Reflect surfaces priority corrections and objective/scenario-linked
   feedback evidence IDs.
6. Exact replay and tenancy isolation mirror PR-008/PR-010 patterns.

## Reuse

- Persona/scenario/session foundation — PR-008
- Orchestration, corrections, coaching modes — PR-010
- Learner memory allowlist — PR-011
- Curriculum publication foundation — PR-005 domain commands only

## Schema expectations (when authorized)

- Lift or extend CHECK constraints that currently force `mode = 'friend'` for
  role-play fixtures only under `development_only` / `synthetic_unreviewed`.
- Add `scenario_objectives` (or equivalent) and session stage state.
- Feedback evidence links must not become authoritative learning records.

## Verification (when authorized)

- PostgreSQL tenancy, stage transition, exact replay and learning-freeze
  tests;
- OpenAPI/generated-client drift;
- Practice UI stage journey unit and Chromium coverage;
- `make verify` (and `make ai-eval` if prompts change);
- STATUS / DECISIONS / QUALITY_REVIEW updates.

## Explicit non-goals

- Real LLM providers
- Human-approved pilot modules
- Full content studio
- Production / Play

## Exit gate

PR-012 synthetic implementation is ready for APO exact-commit acceptance only
when the authorized verification scope passes and recorded limitations preserve
that approved content, real providers and production remain separately gated.
