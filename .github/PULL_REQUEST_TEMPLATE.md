## Checkpoint and outcome

Identify the numbered checkpoint or bounded issue and summarize the delivered
behaviour.

## Linked issue or specification

Link the active issue or approved specification and any relevant accepted ADRs.

## Scope

### Included

-

### Explicitly excluded

-

## Files and contracts changed

List significant files, migrations, configuration, APIs and generated clients.

## Verification

| Command or check | Result |
|---|---|
| `make verify` | Not run — explain, or replace with result |

Do not report a command or provider call as passed unless it ran successfully.

## Acceptance and exit-gate evidence

Map each checkpoint acceptance criterion to test output, review evidence or an
explicitly recorded limitation. State whether the exit gate passed and whether
the next checkpoint is authorized to begin. Link every applicable `APP-NNNN`
record from `docs/APPROVALS.md`, identify the human approval roles exercised
and preserve any required independent-review limitation. Codex is never an
approver.

## Security, privacy and tenancy

Describe organization scoping, authorization, sensitive data, credentials,
retention, logging and cross-tenant tests. Write “No impact” only with a brief
reason.

## Authoritative records

Describe transaction, idempotency, immutability, versioning and concurrency
effects.

## Documentation checklist

- [ ] OpenAPI and generated client types updated when an API changed
- [ ] Tests added or updated with behaviour changes
- [ ] ADR added for a material architecture change
- [ ] `docs/STATUS.md` updated
- [ ] `docs/DECISIONS.md` updated
- [ ] `docs/QUALITY_REVIEW.md` updated
- [ ] Approval/acceptance evidence added to `docs/APPROVALS.md` when applicable
- [ ] Accepted ADRs link their `APP-NNNN` evidence records
- [ ] Known limitations recorded
- [ ] Next checkpoint recommended

## Review and rollback

Describe required reviewers, deployment/feature-flag state and rollback or
mitigation steps.
