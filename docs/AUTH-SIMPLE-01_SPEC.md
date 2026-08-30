# AUTH-SIMPLE-01 — unified role-based login and recovery specification

**Status:** Repository implementation complete under APP-0059 / D-0186 / D-0187;
production delivery and operational activation remain separately gated  
**Prepared:** 13 August 2026  
**Checkpoint:** AUTH-SIMPLE-01 only  
**Production/public release:** Not authorized

> **Successor amendment:** APP-0060 / D-0193 / ADR-0023 supersedes this
> specification's blanket privileged-command freshness statements. Routine
> operator work now uses one signed-in session plus role/permission groups;
> current-password confirmation is limited to credential, recovery-contact and
> administrator-authority changes. The recovery design in this specification
> is otherwise unchanged.

## 1. Authority and outcome

This specification translates accepted DR-0030 Option A,
[APP-0059](APPROVALS.md#app-0059), D-0186 and
[ADR-0022](adr/0022-unified-role-based-login-and-recovery.md) into one bounded
vertical slice.

Every learner, trainer, reviewer, organization administrator and platform
administrator uses the same platform-global username/password sign-in. Roles,
permissions, organization context, enrollment, level and cohort are resolved
only after authentication and never change the credential form.

The recovery outcome is equally uniform:

1. a credential-ready user requests recovery by username;
2. the server returns the same enumeration-safe response for every request;
3. an eligible user receives a random 256-bit, 15-minute, single-use link only
   through an already verified recovery contact; and
4. successful reset advances credential version and revokes every Web session
   and Android token family.

Privileged users do not enter a separate recovery case, obtain approvals or
wait 24 hours. The accepted risk is that compromise of an administrator's
password or verified recovery contact can lead to account takeover. Five-minute
same-password confirmation, command-time permission checks, global revocation,
throttling and content-free evidence remain controls, but are not MFA.

## 2. Bounded implementation

AUTH-SIMPLE-01 includes:

- one role-neutral ordinary recovery service and API;
- one-time credential setup for a reviewed credential-unready migration
  exclusion with an existing verified contact;
- one platform account-recovery capability for lost-contact support;
- additive transition evidence that preserves historical privileged recovery
  rows and approvals;
- removal of active two-actor/24-hour routes, configuration and commissioning
  pages;
- a website-inspired, accessible dedicated sign-in presentation that retains
  the repository username/password contract; and
- OpenAPI/generated clients, localization, privacy, threat/runbook and
  verification updates.

It does not add phone-number sign-in, Google/social sign-in, administrator-set
passwords, shared/default credentials, tenant-global identity search, embedded
Android credentials, production delivery, deployment, public release, Play
release or later LPL work.

## 3. Role-neutral password recovery

`POST /api/v1/auth/password/recovery-requests` no longer checks whether the
target has privileged organization permissions or a platform capability.
Eligibility is limited to an active, credential-ready user with an already
verified `phone` or `email` identity. Unknown, suspended, credential-unready,
contact-unready and delivery-unavailable states return the same HTTP 202 body.

`POST /api/v1/auth/password/recoveries` retains the existing token digest,
lookup binding, five-attempt ceiling, expiry, supersession, transaction lock,
password policy and global revocation behavior. A pending challenge is bound
to one user and recovery identity. Its raw token and contact never enter logs,
audit, outbox, analytics or browser storage.

## 4. One-time credential setup

An active user with no username or password may receive setup only when:

- the user is present in append-only `password_cutover_exclusions` evidence;
- the global password-only cutover marker is current;
- one already verified phone/email recovery identity exists; and
- one freshly password-confirmed platform actor holding
  `platform.account_recovery.manage` requests issuance with the exact reason
  `account_recovery.migration_setup` and an opaque 8–160 character external
  verification reference.

`POST /api/v1/platform/account-recovery/setups` accepts only target user ULID,
reason key and evidence reference. It creates one 256-bit, 15-minute,
single-use `account_setup` challenge, supersedes a prior pending challenge,
delivers it to the verified contact and records an append-only management
event. The actor never receives the token and cannot choose the username or
password.

`POST /api/v1/auth/password/setups` lets the target submit challenge ULID,
token, chosen username and chosen password. Consumption locks the challenge,
target and credential rows; revalidates exclusion, active state, verified
contact and issuance evidence; establishes credentials; marks the user cut
over; consumes the challenge; and globally revokes credentials. Duplicate,
expired, mismatched, collision and concurrent submissions fail safely.

The historical `excluded_credential_establishment` challenge value remains
readable for retained evidence, but no new challenge uses it. The previous
privileged case link is not required for `account_setup`.

## 5. Lost-contact support

Only an active user explicitly allowlisted through
`PLATFORM_ACCOUNT_RECOVERY_MANAGER_USER_IDS` holds
`platform.account_recovery.manage`. Organization roles do not grant it. The
actor must use an active privileged Web session and confirm the same password
within five minutes.

`PUT /api/v1/platform/account-recovery/users/{user_id}/contact` accepts:

- `contact_type`: `email` or `phone`;
- `contact`: a normalized email address or E.164 phone number;
- `reason_key`: exactly `account_recovery.verified_contact_replacement`; and
- an opaque external verification reference.

The transaction locks the target and all recovery identities, rejects
self-targeting, inactive users, reused contacts and malformed evidence,
disables former verified recovery identities, creates one replacement verified
identity using only a dedicated keyed identifier digest plus masked hint,
revokes every Web session and Android token family, clears password freshness,
and writes append-only management/audit/outbox evidence. No membership, role,
permission, enrollment, level, cohort or learning record is changed.

The former contact notification adapter is attempted after commit when a
deliverable provider reference exists. Its outcome is recorded as
`accepted`, `unavailable` or `not_possible`; notification failure never rolls
back the security change. The repository adapter is deliberately unavailable,
so production remains blocked until provider configuration, secret custody,
notification and redaction evidence pass.

## 6. Schema and historical transition

One additive migration:

- adds `account_setup` to the password recovery challenge purpose contract;
- permits `account_setup` without a privileged recovery case;
- creates append-only `account_recovery_management_events` with target, actor,
  action, safe reason/evidence digest, optional old/new identity references and
  time, plus separate append-only former-contact notification outcomes; and
- adds `superseded_at` and `supersession_reason` to privileged recovery cases.

Existing cases and approvals are never deleted or rewritten. On migration,
every incomplete privileged case is marked superseded with
`unified_account_recovery` as a content-free transition. Completed/expired/
cancelled history is unchanged. The tables remain readable only for restricted
audit and retention; no runtime route can create or advance them.

Rollback is allowed only before any new management event or `account_setup`
challenge exists. Historical cases superseded by the migration are restored
to their prior status from a transition ledger only if rollback occurs before
new evidence; otherwise rollback fails closed.

## 7. API and client contract

The public Web APIs are:

| Method/path | Purpose |
|---|---|
| `POST /auth/password/sign-ins` | One username/password sign-in for every role |
| `POST /auth/password/recovery-requests` | Enumeration-safe recovery request for every role |
| `POST /auth/password/recoveries` | Consume a role-neutral password-reset token |
| `POST /auth/password/setups` | Consume a one-time migration/account-setup token |

The platform Web APIs are:

| Method/path | Purpose |
|---|---|
| `POST /platform/account-recovery/setups` | Deliver one setup link to an eligible credential-unready account |
| `PUT /platform/account-recovery/users/{user_id}/contact` | Replace a lost verified recovery contact after external verification |

Platform routes require `auth:web`, `auth.session`,
`auth.password-confirmed` and the exact platform capability. Every response is
`Cache-Control: private, no-store`. The former three
`/platform/identity-recovery-cases` endpoints and local two-actor
commissioning routes are removed from the active contract and generated
client.

Android keeps the existing server-controlled username/password authorization,
Passport Authorization Code with S256 PKCE, exact redirect/state validation,
memory-only access token and native secure refresh-token storage. Recovery
links open the server-controlled Web surface; no raw password or token is
passed to the Capacitor bridge.

Recovery and setup delivery links use URL fragments on the dedicated
`/password-recovery.html` and `/password-establishment.html` forms. Each form
removes the fragment before user interaction, keeps challenge/token only in
page memory, initializes CSRF protection, clears secrets on completion or
unload and is excluded from service-worker caching.

## 8. Sign-in presentation

The anonymous Web experience may mirror the public Global Spoken English
website's visual pattern: a dedicated split layout, branded learning image,
clear white sign-in card, username and password fields, show/hide action,
forgot-password entry and invitation setup entry. It must not copy the legacy
site's phone-number credential, Google sign-in, insecure remember-me behavior
or third-party scripts.

The form remains accessible at 320 CSS pixels, 200–400% zoom, keyboard and
screen reader use; supports password managers and paste; respects reduced
motion; and writes no credential to localStorage, IndexedDB, service-worker
cache or ordinary Capacitor preferences.

## 9. Verification

Required automated coverage includes:

- learner and privileged-user recovery producing the same observable response
  and the same challenge lifecycle;
- unknown/inactive/unready/no-contact/delivery-failure enumeration safety;
- one-time use, expiry, supersession, failed-attempt ceiling and concurrent
  consumption on SQLite and PostgreSQL;
- immediate eligible migration setup without a privileged case, target-chosen
  credentials and unchanged multi-tenant authority;
- retired two-actor routes returning 404 and old configuration granting no
  runtime authority;
- manager capability denial, self-target denial, wrong reason/evidence,
  contact uniqueness, atomic replacement, global revocation, notification
  outcome and secret-free evidence;
- cross-tenant role/membership/enrollment snapshots remaining byte-stable;
- Sanctum CSRF/cookies, password-confirmation freshness and Android PKCE/
  secure-storage contracts;
- accessible responsive sign-in/recovery/setup browser journeys; and
- raw password, reset/setup token and contact absence from responses, logs,
  audit, outbox, generated examples and browser storage.

Run from the repository root:

```text
make verify
make e2e
make build
POSTGRES_DB=global_english_test make backup-restore-drill
```

Android unit/build checks are required because the shared authentication
contract changes. `make ai-eval` is not required because no prompt, model,
provider or AI orchestration behavior changes.

## 10. Exit and retained gates

AUTH-SIMPLE-01 is complete only when schema, API, generated client, Web UI,
tests, runbooks, privacy inventory, threat model, STATUS, DECISIONS and
QUALITY_REVIEW match the implemented behavior and the required commands pass.

Repository completion does not activate production recovery delivery. That
requires a reviewed email/SMS adapter, provider agreement and subprocessor
evidence, protected secret/configuration custody, sender/domain controls,
delivery/notification observability, replay/expiry/rate-limit/log-redaction
tests and independent security/privacy review. Production, public release,
Google Play and LPL-02 remain separately gated.
