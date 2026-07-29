---
status: proposed
date: YYYY-MM-DD
deciders: []
consulted: []
informed: []
amends_master_plan: false
---

# ADR-NNNN: Decision title

## Status

Proposed.

## Context

Describe the problem, the active checkpoint, applicable constraints and why a
durable architecture decision is required.

## Decision

State the decision in concrete, testable terms.

## Alternatives considered

### Alternative A

Describe the option and why it was not selected.

### Alternative B

Describe the option and why it was not selected.

## Consequences

### Positive

- List expected benefits.

### Negative and trade-offs

- List costs, risks and constraints.

## Security, privacy and tenancy impact

Document:

- authorization and organization-scoping implications;
- sensitive-data collection, processing, retention and deletion;
- credential and secret handling;
- cross-tenant and abuse-case tests;
- audit and incident-response requirements.

## Authoritative records and consistency impact

Document transaction boundaries, idempotency, immutability, versioning,
concurrency and migration implications.

## API, client and operational impact

Document contract changes, generated-client updates, observability, rollout,
rollback and support requirements.

## Master-plan amendment

If this ADR changes the canonical baseline:

1. identify the exact master-plan sections affected;
2. describe the required impact note;
3. update `ENTERPRISE_PRODUCT_MASTER_PLAN.md` in the same accepted change.

Otherwise state: “This ADR does not amend the master plan.”

## Approval evidence

When this ADR is accepted, record:

- acceptance date;
- accountable decider or approval roles;
- approving issue or pull-request reference;
- impact-note and master-plan update reference when applicable.

## Verification

List the automated tests, manual evidence and root commands required to accept
the decision.

## Follow-up

List bounded follow-up work, owners and checkpoints.
