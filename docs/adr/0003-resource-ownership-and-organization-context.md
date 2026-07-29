---
status: accepted
date: 2026-07-30
deciders:
  - Codex task accountable product owner
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: false
approval_evidence:
  - APP-0001
approval_reference: Codex task 019fae0d-d5d2-7e32-b2c2-ced466b4b0cb
---

# ADR-0003: Resource ownership and organization-context enforcement

## Status

Accepted on 2026-07-30 under
[APP-0001](../APPROVALS.md#app-0001).
This ADR resolves DR-0002.

## Context

The canonical plan uses a shared PostgreSQL schema with mandatory organization
scoping, requires every organization-owned record to be scoped, indexed,
authorized and cross-tenant tested, and forbids deriving organization context
from an untrusted request field.

PR-003 needs an explicit classification of platform-global and
organization-owned resources plus one enforceable rule for resolving the
active organization.

## Decision

### Resource classification

Platform-global records are limited to:

- the organization registry;
- users and authentication identities;
- web authentication sessions, OAuth clients and mobile token-family records;
  and
- the stable permission-key catalogue.

An authentication record may retain the selected organization/membership
identifier needed to reconstruct an authorization context, but that does not
make the session or token family organization-owned.

Organization-owned records include:

- organization settings and branches;
- memberships, roles, membership-role assignments and organization invites or
  setup codes;
- learner profiles, trainer profiles and learner goals;
- cohorts, cohort-trainer assignments and enrollments; and
- every later domain record whose authority, visibility or lifecycle belongs
  to an organization.

Roles belong to organization memberships, never globally to users. Permission
keys are global definitions; a role's grant of those permissions is
organization-owned.

Global English Direct is an ordinary platform-owned organization tenant with
an immutable system key. A direct learner is transactionally assigned a
membership in that organization. The direct organization is not a bypass
scope, null tenant or privileged global context.

### Database enforcement

Every organization-owned table has a non-null `organization_id`. Its lookup
indexes and business uniqueness constraints begin with `organization_id`.
Relationships between organization-owned records use composite organization
and record identifiers where necessary so PostgreSQL rejects cross-tenant
associations.

A global user identifier may be referenced alongside `organization_id` only
through a valid organization membership where the domain relationship requires
membership. Application validation is not the sole defense for cross-tenant
foreign-key integrity.

PostgreSQL row-level security is not introduced in PR-003. It may be considered
later only through a separate accepted ADR with migration, connection-pooling,
background-job and operational analysis.

### OrganizationContext resolution

The project-owned `OrganizationContext` is derived only from:

1. an authenticated session or token plus a current, active membership;
2. a validated, single-purpose invitation/setup-code transaction while that
   transaction creates or activates a membership; or
3. an explicit platform operation whose dedicated authorization, reason and
   audit event have been verified.

After membership creation, an invitation or setup code grants no continuing
organization context.

Never trust `organization_id`, organization codes or membership identifiers
from request bodies, query strings or arbitrary headers as authorization
context. A route identifier may select a candidate organization only; it still
requires an active membership and policy authorization.

General super-administrator bypass is prohibited. Platform operations use
explicit named capabilities and an append-only privileged audit event rather
than disabling organization scopes.

### Application enforcement

- Middleware resolves one `OrganizationContext` before organization-owned
  application behavior runs and rejects missing, inactive or ambiguous
  membership.
- Scoped route binding, repositories/query objects and authorization policies
  require that context.
- Creation helpers set `organization_id` from the context and reject
  caller-supplied ownership.
- Policies check both tenant equality and the required permission.
- Jobs, commands, events and outbox messages carry the organization identifier
  explicitly; workers reconstruct and validate context rather than relying on
  process-global state.
- Cache keys, rate-limit keys, object-storage paths, metrics and safe audit
  events include a non-personal organization identifier when their contents
  are tenant-specific.

An explicit cross-tenant denial test is required for read, list, create, update,
delete, route binding, relationship attachment, job handling and mass
assignment for every organization-owned resource introduced.

## Alternatives considered

### Trust a client-supplied organization header

Rejected because authentication of a user does not authorize an arbitrary
tenant, and a header can be modified by the caller.

### Make all resources organization-owned

Rejected because one human identity and its authentication credentials must be
able to participate in multiple organizations without duplicated identities.

### Global roles on users

Rejected because permissions would leak across memberships and contradict the
master plan's membership-scoped role model.

### Treat Global English Direct as an unscoped default

Rejected because special null/bypass tenancy weakens uniform constraints and
tests.

### Add PostgreSQL row-level security now

Rejected for PR-003 because application context, connection pooling,
background work and migration behavior need a separate bounded design. Strong
schema constraints, scoped queries and policies remain mandatory.

## Consequences

### Positive

- Tenant ownership has one explicit and testable boundary.
- Cross-tenant relationships are rejected by both application behavior and
  PostgreSQL constraints.
- Direct and institutional learners use the same authorization model.
- Future jobs, caches and storage paths receive an explicit tenant key.

### Negative and trade-offs

- Composite constraints and context-aware repositories add implementation
  complexity.
- Multi-organization users need an explicit active-membership selection
  workflow.
- Platform operations require dedicated capabilities rather than a convenient
  blanket bypass.
- Database row-level security is not an additional defense in PR-003.

## Security, privacy and tenancy impact

This ADR is the minimum tenancy security boundary. Tenant equality never
substitutes for permission authorization, and permission authorization never
substitutes for tenant equality. Logs and metrics use non-personal identifiers
and do not include invite secrets, learner data or authentication credentials.
Independent tenancy, security and privacy specialist review remains mandatory
before production under ADR-0001.

## Authoritative records and consistency impact

Direct assignment, invitation redemption, membership creation, role
assignment, cohort enrollment and cross-tenant relationship changes are
transactional authoritative operations. Replayable invitation and assignment
commands require idempotency. Organization ownership cannot be mutated through
ordinary mass assignment.

## API, client and operational impact

Organization context is not accepted as an authoritative OpenAPI request field.
Where an organization appears in a route, the contract describes it as a
candidate that still requires membership authorization. Error responses do not
reveal whether a resource exists in another organization. Operational tooling
requires explicit audited platform capabilities.

## Master-plan amendment

This ADR does not amend the master plan. It makes the shared-schema tenancy
baseline in master-plan §§10, 15, 16, 18 and 32 testable for PR-003.

## Approval evidence

- Evidence ID: `APP-0001`
- Approval reference: `Codex task 019fae0d-d5d2-7e32-b2c2-ced466b4b0cb`
- Acceptance date: 2026-07-30, Asia/Kolkata
- Human approver identifier: `Codex task accountable product owner`
- Roles exercised: Accountable Product Owner; Technical Decision Owner;
  Security Reviewer; Privacy Reviewer; Tenancy Reviewer
- Exact human statement: `Continue`
- Codex role: author and evidence recorder only; never approver
- Master-plan impact: none

## Verification

PR-003 acceptance requires:

- real-PostgreSQL tests for non-null ownership, tenant-prefixed uniqueness and
  composite cross-tenant foreign-key rejection;
- policy and scoped-binding tests for every organization-owned resource;
- cross-tenant read, list, create, update, delete, attachment, job and mass-
  assignment denial tests;
- tests that request fields and arbitrary headers cannot establish or replace
  `OrganizationContext`;
- direct-organization assignment and institution-invitation transaction and
  idempotency tests; and
- explicit platform-operation authorization and safe audit-event tests.

## Follow-up

- Complete independent tenancy, security and privacy specialist review before
  production.
- Decide whether PostgreSQL row-level security adds sufficient defense to
  justify its operational complexity in a later ADR.
