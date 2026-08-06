# PR-012 role-play authorization request (draft)

**Status:** Unauthorized draft. This document is not an APP, ADR or
implementation mandate. It may be considered only after Accountable Product
Owner exact-commit acceptance of PR-011 with recorded limitations.

## Purpose

Propose a bounded APP for master-plan §26 **PR-012 — Role-play framework and
pilot content integration**, analogous to the PR-008 Friend fake-provider
slice: one complete synthetic vertical slice before approved curriculum or
real LLM providers.

## Proposed authorized scope

Authorize only:

1. Server stage state machine: Observe → Rehearse → Perform → Reflect.
2. Synthetic `scenario_objectives` and completion criteria fixtures
   (`development_only` / `synthetic_unreviewed` disclosed).
3. Session snapshotting of persona and scenario versions (already present for
   Friend; extend for role-play mode).
4. Learner feedback records linked to objective and scenario evidence
   identifiers — **advisory only**; must not mutate mastery, attempts, review
   schedules or publications.
5. Learner UI for the four stages on the Practice surface.
6. Verification scope, OpenAPI updates, organization scoping and
   cross-tenant tests.

## Explicit non-goals (remain unauthorized)

- Real LLM / conversation-model providers
- Human-approved curriculum, Kannada, transcript or audio modules
- Full content-studio HTTP/UI product surface
- Ordinary live Azure Speech app binding beyond existing ADR-0008 gates
- PR-013 operator console, PR-014 production Android/Play, PR-015 production
  hardening
- Production deployment or real learner production data

## Preconditions before APP issuance

1. PR-011 exact-commit APO acceptance recorded.
2. Written verification scope committed (see
   [PR-012_ROLEPLAY_VERIFICATION_SCOPE.md](PR-012_ROLEPLAY_VERIFICATION_SCOPE.md)).
3. Content path decided: start on **disclosed synthetic** role-plays; resolve
   or time-box **DR-0007** before commissioning specialist content.
4. Open a short DR/ADR only if stage lifecycle or objective-evidence semantics
   need policy beyond the master plan (authoritative records / tenancy /
   privacy).

## Proposed APO begin-implementation statement

```text
APO authorize only PR-012 bounded synthetic role-play under
PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md and
PR-012_ROLEPLAY_VERIFICATION_SCOPE.md.
```

Interpretation:

- Authorize implementation of the bounded synthetic slice only.
- Do not accept completed PR-012 work, approved pilot modules, real LLM
  providers, production or Play.
- AI remains advisory over deterministic learning records.

## Recorder notes

When the APO issues the statement above, create a new APP entry, update
STATUS/DECISIONS/QUALITY_REVIEW, and only then begin PR-012 code on a new
checkpoint branch.
