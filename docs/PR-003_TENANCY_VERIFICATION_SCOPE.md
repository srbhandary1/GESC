# PR-003 tenancy verification scope

This note maps the implemented PR-003 foundation to
[ADR-0003](adr/0003-resource-ownership-and-organization-context.md). It does
not amend the ADR or the enterprise master plan.

## Implemented resource matrix

The organization-owned Eloquent resources introduced by PR-003 are
`Membership`, `Role`, `Cohort`, `InstitutionInvite` and `Enrollment`.

`OrganizationIsolationTest` exercises, for every one of those resources:

- context-scoped read and list behavior;
- scoped model route binding;
- cross-organization create, update and delete rejection;
- authorization-policy `viewAny`, `view`, `create`, `update` and `delete`
  decisions; and
- mass-assignment denial for organization ownership and authoritative
  identifiers, relationships and lifecycle fields.

The schema tests use PostgreSQL to verify non-null organization ownership,
tenant-prefixed business uniqueness and composite relationship constraints.
Covered relationship categories are role-permission, membership-role,
cohort-trainer, invite-role, invite-cohort and enrollment attachments. The
identity relationship tests also bind accepted invitations, tenant audit
actors and selected mobile memberships to the same user and organization.

The registered `organization.context` middleware runs only after
`auth.first-party` on organization-owned application routes. It reconstructs
the selected active web or mobile membership, falls back only when exactly one
active membership exists, activates the request-scoped
`OrganizationContextManager` for downstream model scopes and policies, and
clears it in a `finally` block. Missing, inactive, cross-user or ambiguous
membership state fails closed. Identity discovery, context selection and
invite-redemption endpoints remain outside that middleware because they must
establish or choose context rather than consume existing tenant authority.

`OrganizationContextMiddlewareTest` exercises both web and Passport
principals through an HTTP probe route. It proves single-membership fallback,
explicit selection, ambiguity and inactive/cross-user rejection, scoped model
visibility, cleanup after the response, and that caller body/header
organization values cannot replace membership-derived authority.

## Platform-operation boundary

Creating an `OrganizationContext` with the `platform_operation` source requires
a project-issued, short-lived authorization for a named capability and an
allowlisted, non-sensitive reason key. Direct or forged authorizations fail
closed.

The context-capable foundation operation is limited to the test environment in
PR-003 because this checkpoint introduces no production platform-operator
workflow. Authentication-session and mobile-credential lifecycle capabilities
may record platform-global audit events, but they cannot establish tenant
context.

A context-capable platform operation verifies its target organization and
actor, writes append-only audit evidence, reconstructs the exact authorized
context and executes the scoped callback in one database transaction. The
context verifies the audit row's capability, reason, target organization,
actor, action, subject and correlation identifier before use. A failed callback
rolls back both authoritative writes and its audit row.

## Categories not applicable in PR-003

- Tenant CRUD routes and controllers are not introduced in this foundation
  checkpoint. The production middleware alias and an HTTP probe route verify
  request-context reconstruction and scoped downstream access; resource-route
  handler authorization tests become mandatory when a tenant CRUD route is
  added.
- No queued job, scheduled command or event handler reads or writes an
  organization-owned resource in PR-003. Job-context reconstruction and
  cross-tenant job denial are therefore not applicable yet. A future handler
  must carry the organization identifier explicitly, reconstruct a validated
  context and add both same-tenant and cross-tenant worker tests.
- No tenant-specific object-storage attachment is introduced. The attachment
  acceptance category currently means relational attachments protected by the
  composite PostgreSQL constraints listed above.
- Outbox persistence exists, but PR-003 adds no publisher or consumer handler.
  Worker context validation remains a required test at the checkpoint that
  introduces such a handler.

## Identity digest key operations

Phone and other identity lookup digests use the dedicated
`AUTH_IDENTITY_DIGEST_KEY`, not Laravel's `APP_KEY`. Production fails closed
when this key is absent, malformed, too short or still set to the documented
local-development example. The key is sensitive configuration: it must be
access-controlled, backed up and never logged or committed as a production
value.

The identity digest key must not be rotated in place. Replacing it without a
migration makes existing identities undiscoverable and can create duplicate
users. A future rotation requires a reviewed ADR and a versioned dual-read
migration: retain the prior key during the transition, write the new digest
version, backfill under controlled access, verify uniqueness, then retire the
old key. Routine `APP_KEY` encryption-key rotation does not alter identity
digests.

## Verification locations

- `apps/api/tests/Integration/OrganizationIsolationTest.php`
- `apps/api/tests/Integration/OrganizationContextMiddlewareTest.php`
- `apps/api/tests/Integration/TenantSchemaConstraintTest.php`
- `apps/api/tests/Integration/IdentityRelationshipConstraintTest.php`
- `apps/api/tests/Integration/PlatformOperationAuthorizationTest.php`

Independent security, privacy and tenancy review remains required before
production, as recorded by ADR-0001 and ADR-0003.
