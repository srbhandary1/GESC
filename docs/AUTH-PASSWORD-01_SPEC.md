# AUTH-PASSWORD-01 — username/password-only authentication specification

**Status:** Repository implementation complete under APP-0056 / D-0164;
runtime cutover and production remain separately gated
**Prepared:** 11 August 2026
**Checkpoint:** AUTH-PASSWORD-01 only
**Production/public release:** Not authorized

> Historical baseline: ADR-0022 / AUTH-SIMPLE-01 supersedes this document's
> privileged recovery and credential-unready establishment sections. Its
> global username/password, session, cutover and tenant-authority rules remain
> authoritative where ADR-0022 does not amend them.

## 1. Authority and outcome

This specification translates accepted DR-0026 Option B,
[APP-0055](APPROVALS.md#app-0055), D-0163 and
[ADR-0021](adr/0021-username-password-only-authentication.md) into one bounded
implementation checkpoint.

The outcome is one sign-in model for every learner and operator:

1. enter a platform-global username and password;
2. receive the existing Sanctum web session or complete the existing Android
   system-browser Passport PKCE journey; and
3. re-enter the same password when a sensitive privileged web command requires
   freshness.

There is no TOTP, authenticator-app or other second factor after cutover.
Password confirmation is explicitly one-factor freshness evidence, not MFA.
Tenant membership, role, permission and appointment checks remain independent
and continue at command time.

## 2. Existing implementation inventory

The implementation must migrate, not bypass, these current surfaces:

- platform-global `users` and replaceable `user_identities` with no current
  username/password fields;
- web OTP request/verification routes and the Android system-browser OTP form;
- Sanctum `authentication_sessions`, selected-membership binding, session
  listing/revocation and short privileged lifetimes;
- Passport authorization, installation binding, rotating refresh families and
  reuse revocation;
- TOTP factors, challenges, recovery codes, client adapter/UI and seven MFA
  management endpoints;
- `auth.mfa` middleware and PostgreSQL evidence constraints used by content,
  media, learner administration, privacy and emergency commands; and
- tests and runbooks that construct or assert OTP/TOTP evidence.

No existing route, constraint or credential is weakened before the explicit
cutover in section 10.

## 3. Explicit exclusions

AUTH-PASSWORD-01 does not add:

- SAML, passkeys, PINs, security questions or device fingerprinting;
- shared organization credentials, administrator-readable passwords or
  permanent default passwords;

Google and Facebook extra sign-in for already-enrolled users is authorized
separately by ADR-0034 / APP-0071 and is outside this specification's original
exclusion of social login.
- tenant-controlled global identity search, username assignment or password
  reset;
- embedded Android/WebView credential collection or password storage on the
  client;
- new session/token lifetimes, a new OAuth grant, tenant impersonation or an
  authorization shortcut;
- production recovery delivery, production blocklist data, independent review,
  deployment, Google Play release or LPL-02 work; or
- removal of historical content-free authentication/audit evidence.

## 4. Username and credential schema

One additive, forward/rollback-safe migration introduces the pre-cutover
records. PostgreSQL constraints are authoritative and SQLite behavior is
covered by application tests.

### 4.1 `users`

Add protected fields:

- nullable `username` up to 64 ASCII characters during migration;
- nullable `username_established_at`;
- positive integer `credential_version`, default `0`; and
- nullable `credential_cutover_at`.

Database and application rules:

- canonical username matches `^[a-z][a-z0-9._-]{3,63}$` and equals its stored
  lower-case form;
- non-null username is globally unique;
- `username_established_at` is paired with username;
- `credential_version = 0` before password establishment and increments once
  per establishment, password change, recovery or security reset;
- `credential_cutover_at` requires username plus an active password credential;
  and
- username and credential fields are never mass assignable or exposed in
  tenant administration responses.

The retained global user record continues to reserve its username after
closure. If a later approved erasure policy removes that record, retain only a
domain-keyed username digest for 365 days before reuse; this checkpoint does
not erase or reuse usernames.

### 4.2 `password_credentials`

Create one current credential row per user:

- `user_id` ULID primary key and restrictive FK to `users`;
- `password_hash` up to 255 characters;
- fixed `algorithm = argon2id`;
- positive `policy_version` and `credential_version` matching the user;
- `established_at`, `changed_at`, nullable `compromised_at` and timestamps.

The row is writable only through the project-owned password credential service.
No old raw password or reusable password history is stored. A transaction locks
the user and credential row, writes the new hash/version, revokes all existing
web sessions and Android token families when applicable, and appends only safe
security evidence.

### 4.3 Password confirmation on `authentication_sessions`

Add nullable:

- `password_confirmed_at`; and
- `password_confirmed_credential_version`.

Both are present or absent together. Confirmation is usable only when the
session is active and privileged, the timestamp is no more than five minutes
old, and the stored version equals the user's current credential version.
Password/username change, recovery, security reset or any session revocation
clears it. Existing selected-organization/membership binding is unchanged.

### 4.4 `password_recovery_challenges`

Create content-minimized ordinary recovery records:

- ULID ID; nullable user ID to preserve enumeration-safe request behavior;
- keyed request lookup digest and keyed 256-bit reset-token digest;
- recovery-contact identity ID when an eligible verified contact exists;
- `pending|consumed|expired|superseded` state;
- zero-to-five failed attempts, 15-minute expiry, consumption/supersession
  instants and timestamps; and
- delivery outcome category, never the contact value or provider payload.

Only one active challenge per user is permitted. New issuance supersedes the
old challenge transactionally. Verification uses constant-time comparison and
locks the challenge/user/credential rows.

### 4.5 Privileged support recovery

Create platform-owned, content-minimized `privileged_password_recovery_cases`
and append-only `privileged_password_recovery_approvals`:

- a case binds one target user, fixed reason key, opaque external evidence
  reference/digest, state, requested time, not-before time of 24 hours,
  completion/expiry and version;
- two distinct active platform operators must each hold the new named
  capability `platform.identity_recovery.approve` at approval time;
- neither approver may be the target, approvals cannot be withdrawn or edited,
  and the second approval schedules rather than immediately completes reset;
- completion revalidates both actors/capability, target status, exact case
  version, delay and evidence digest under locks;
- completion issues one short-lived reset token to an already verified contact,
  revokes every session/token family and never permits an operator to see or set
  the new password; and
- every transition emits content-free platform audit/outbox evidence.

The capability is not granted to any organization role or ordinary seeded user
by this checkpoint. Local/test seeding may grant it only to explicit synthetic
platform-recovery fixtures. Production provisioning and operational identity
proofing remain separately gated.

## 5. Password policy and secret handling

The project-owned credential service enforces:

- 15–128 Unicode code points after consistent NFC normalization;
- ordinary Unicode and spaces, paste and password-manager support;
- no silent truncation, composition rule or periodic expiry;
- full-candidate comparison against a versioned common, contextual and known-
  compromised blocklist; and
- rejection of candidates containing the canonical username, product name or
  their obvious normalized derivatives when the full candidate appears in the
  contextual blocklist.

Hash with Laravel's Argon2id driver at no less than 65,536 KiB memory, three
iterations and one thread. Configuration may increase these values after a
target-host benchmark but fails closed below them. Store the policy version and
rehash after a successful full sign-in when parameters increase.

The blocklist is behind a project interface. Deterministic tests use a fixed
licensed-safe fixture. Nonproduction/production uses a versioned, owner-readable
local artifact referenced by absolute path and expected SHA-256; the
application never sends a candidate password to a network provider. Exact
production source/licence/freshness evidence remains a release gate.

Recovery-token digests use a separate external secret
`AUTH_RECOVERY_DIGEST_KEY`, never `APP_KEY`, source code or repository env
defaults. Raw passwords/tokens are request-memory only and redacted from
validation exceptions, problem details, request logs and monitoring.

## 6. API contract

OpenAPI and generated TypeScript clients add these exact web APIs under
`/api/v1`:

| Method/path | Purpose | Gate |
|---|---|---|
| `POST /auth/password/sign-ins` | Username/password web sign-in | Stateful browser + CSRF bootstrap + login throttles |
| `POST /auth/password/establishments` | Existing-user transition after a legacy-authenticated migration session | Migration mode only; current session; one-time establishment |
| `GET /auth/password/status` | Safe current-user credential/cutover status | Active web session; private no-store |
| `POST /auth/password/confirmations` | Re-enter current password for a five-minute privileged assertion | Privileged active web session; confirmation throttles |
| `PUT /auth/password` | Change current password | Active session + current password confirmation; global revocation after commit |
| `PUT /auth/username` | Change canonical username | Active session + current password confirmation; global revocation after commit |
| `POST /auth/password/recovery-requests` | Generic ordinary recovery request | Anonymous; enumeration-safe request throttles |
| `POST /auth/password/recoveries` | Consume token and set a new password | Anonymous; recovery throttles; global revocation |

Android changes only the server-controlled system-browser form and POST target
to username/password. `/oauth/authorize`, `/oauth/token`, PKCE, redirect/state,
installation and refresh-family contracts remain unchanged.

Platform recovery uses a separate first-party web namespace with exact
`platform.identity_recovery.approve` checks and no organization context:

| Method/path | Purpose |
|---|---|
| `POST /platform/identity-recovery-cases` | Open a content-minimized case |
| `POST /platform/identity-recovery-cases/{id}/approvals` | Append one actor approval |
| `POST /platform/identity-recovery-cases/{id}/complete` | Revalidate and issue delayed reset |

Every response carrying credential/recovery state uses `Cache-Control: private,
no-store`. Login and recovery failures use the same status, shape and generic
message for unknown, disabled, unready, locked or incorrect accounts, with
timing equalization tested rather than claimed perfect.

At cutover, OTP/TOTP endpoints and methods are removed from OpenAPI/generated
clients. Unsupported legacy requests return the same explicit retired-contract
problem without revealing account state. No compatibility shim accepts an OTP
or TOTP as password confirmation.

## 7. Throttling, session and authorization behavior

Use centralized configurable limits with these secure defaults:

- login: five attempts per normalized-account digest per 15 minutes, 20 per IP
  per 15 minutes and 100 global per minute per process/Redis partition;
- password confirmation: five failures per session and account per 15 minutes;
- recovery request: three per lookup digest and ten per IP per hour;
- recovery consumption: five attempts per challenge and ten per IP per hour;
  and
- progressive delay after failure, without a permanent attacker-triggered
  account lock.

Successful sign-in starts the existing ordinary/privileged session lifetime
based on current atomic grants. It does not auto-select an ambiguous
organization membership. Session listing/revocation and Android token-family
behavior remain unchanged.

Replace `auth.mfa` with `auth.password-confirmed` on the exact existing
protected route/command set. Update PostgreSQL constraints/triggers that bind
authoritative mutations to authentication evidence so they accept only an
active actor/session with valid password-confirmation time and credential
version. No formerly protected command is omitted.

## 8. Web, Android and localization behavior

The web sign-in page contains only username, password, show/hide control,
submit and recovery entry. It supports paste, password-manager autocomplete,
keyboard navigation, screen readers, 320-pixel layout and approved-English
fallback. It never displays whether a username exists.

Invitation codes remain on the one-time institutional enrollment/setup journey
and are not requested at routine sign-in. Mobile number is absent from routine
sign-in and may appear only in disclosed recovery-contact management.

Account Security replaces authenticator enrollment/challenge controls with:

- username and password-ready status;
- change username/password;
- confirm password when a protected action requests freshness;
- list/revoke sessions; and
- recovery/contact explanation without secret disclosure.

Android opens the same server-controlled authorization UI in the system
browser. The Capacitor layer receives only the authorization result and stores
only reviewed access/refresh material in native secure storage.

English source strings and Kannada translations require the existing locale
governance. Kannada and other non-English wording remains visibly
pending-independent-review until accepted; production uses approved-English
fallback.

## 9. Existing-user migration

Authentication mode is a centralized enum:

- `legacy_otp`: current behavior only;
- `credential_migration`: legacy-authenticated users may establish username/
  password and password-ready users may sign in by password; TOTP remains
  mandatory for protected commands; and
- `password_only`: only password sign-in/confirmation is accepted and all
  legacy sessions/tokens are revoked at cutover.

Unknown values fail closed. Production defaults to `legacy_otp` until the
signed cutover evidence is valid; tests/local environments may select an
explicit mode.

Migration rules:

1. Never manufacture a permanent password. Existing users authenticate through
   the currently accepted path and choose/receive a collision-free username
   before choosing their own password.
2. A user with a credential uses password sign-in during migration; legacy OTP
   cannot reset or replace that credential.
3. Institution/operator bulk tools may reserve candidate usernames but cannot
   set passwords. The user completes a one-time secret establishment.
4. A cutover manifest contains counts and opaque IDs/digests only: every active
   user is `credential_ready`, deliberately `cutover_excluded` with an approved
   reason/owner, or the cutover fails.
5. Cutover runs as one idempotent command under maintenance control: validate
   manifest/config/blocklist/recovery key, lock the mode, set per-user cutover
   state, revoke every web session and Android token family, revoke/clear TOTP
   material, supersede OTP challenges, switch mode and write signed content-free
   evidence.
6. Rollback before cutover removes only additive unused records. Rollback after
   cutover never restores cleared TOTP secrets or OTP challenges; it requires a
   new reviewed credential recovery/cutover decision.

## 10. TOTP/OTP retirement and retention

At verified cutover:

- mark every TOTP factor revoked and clear encrypted secrets;
- consume/supersede challenges, clear recovery-code digests and clear all MFA
  assertion columns;
- supersede every unconsumed OTP challenge and stop OTP scheduler/delivery;
- remove MFA enrollment/recovery UI and client methods;
- replace the MFA runbook with a historical/superseded notice plus the new
  password operations runbook; and
- retain only content-free historical lifecycle/audit evidence under current
  policy.

A later cleanup migration may drop obsolete secret/challenge tables and MFA
columns only after backup age-out and verification that no constraint, query,
audit relationship or rollback procedure depends on them. AUTH-PASSWORD-01 may
deactivate and clear them but does not claim physical table removal is safe on
the same cutover transaction.

## 11. Security, privacy and tenancy tests

Required automated coverage includes:

- username normalization/regex/global uniqueness and concurrent assignment;
- 14/15/128/129-code-point, Unicode/NFC, whitespace, paste, blocklist and
  non-truncation cases;
- Argon2id minimum/config failure and rehash/version behavior;
- generic/timing-bounded unknown, disabled, unready and wrong-password failures;
- account/IP/global throttles, progressive delay and no permanent lockout;
- concurrent sign-in/change/recovery, one-time token use, expiry,
  supersession, attempt exhaustion and replay;
- same-user session/version binding and stale confirmation denial;
- every formerly MFA-protected API/command denied without fresh password and
  accepted only with independent tenant/permission/appointment authority;
- cross-tenant denial before and after confirmation, membership switch,
  suspension and role/appointment revocation;
- two-distinct-operator privileged recovery, self-approval denial, expired
  capability, 24-hour delay, evidence mismatch and global revocation;
- Sanctum CSRF/cookie security and Android system-browser PKCE/state/redirect/
  refresh-family behavior;
- raw password/token/contact absence from logs, audit, outbox, errors, OpenAPI
  examples, browser storage and ordinary Capacitor preferences;
- migration manifest completeness, idempotent cutover, pre-cutover rollback and
  post-cutover fail-closed behavior; and
- PostgreSQL constraint/concurrency tests plus behaviorally equivalent SQLite
  tests.

Critical Playwright journeys cover direct learner sign-in, institutional
one-time setup then routine sign-in, operator sign-in and protected-action
confirmation, recovery genericity, 320-pixel accessibility, keyboard/axe and
Android system-browser handoff at the contract boundary.

## 12. Documentation and operational changes

Implementation must update:

- OpenAPI and generated TypeScript clients;
- all six locale catalogues and governance hashes/review state;
- `PRIVACY_DATA_INVENTORY.md` for username, password credential, recovery
  contact/challenge and support-case purpose/retention/export/erasure;
- authentication/Android/operator/security runbooks and threat model;
- session, incident, account-compromise, recovery and cutover procedures;
- STATUS, DECISIONS and QUALITY_REVIEW; and
- release gates so password-only privileged access is an explicit reviewed
  exception, never mislabeled as MFA.

## 13. Verification and exit gate

Run from repository root:

```text
make verify
make e2e
make build
POSTGRES_DB=global_english_test make backup-restore-drill
```

Run Android unit/build checks because the server-controlled authorization
journey changes. `make ai-eval` is not required unless implementation changes
AI/provider orchestration.

AUTH-PASSWORD-01 is complete only when:

1. all schema/API/client/UI/migration/security behavior above is implemented;
2. SQLite, real PostgreSQL concurrency, browser and Android contract gates pass;
3. an exact synthetic-account cutover drill and pre-cutover rollback pass;
4. no old session, OTP, TOTP or recovery code can authenticate after cutover;
5. no protected action lost its freshness, tenant or authorization gate;
6. docs and privacy/runbook contracts match runtime behavior; and
7. limitations and independent-review/production gates remain explicit.

Completion does not authorize production or Google Play release.

## 14. Implementation authorization requested

This specification was prepared under APP-0055, but DR-0026 section 5 states
that architecture approval does not directly authorize code changes. To begin
only this bounded checkpoint, reply exactly:

```text
Approve AUTH-PASSWORD-01 implementation as written.
```

Any change to one-factor scope, username rules, password policy, recovery
authority, protected-command coverage, migration mode or cutover semantics
requires revised approval before implementation.

## 15. Authorization resolution

Implementation was authorized exactly as written on 11 August 2026 under
[APP-0056](APPROVALS.md#app-0056) / D-0164. The approved pre-resolution
specification has SHA-256
`7b5d3260c619c33fd4df5cff06364860ab67536f02399ed61c5fe086519193c4`.

This authorization is limited to AUTH-PASSWORD-01. It does not authorize
production, Google Play release, LPL-02, independent-review waivers or any
change to the accepted password-only contract.

## 16. APP-0057 excluded-account recovery amendment

DR-0027 Option A was accepted on 11 August 2026 under APP-0057 / D-0169. It
amends only the post-cutover recoverability gap for an exact listed active
credential-unready exclusion with an existing verified contact. The bounded
path adds append-only marker-linked exclusion evidence, the existing
two-distinct-configured-actor and fresh-password-confirmation controls, a full
24-hour delay, and a 256-bit/15-minute/single-use establishment challenge. The
target chooses their own username and password; recovery actors cannot see or
set either. The local protected delivery adapter and commissioning pages are
disabled by default and are not authorized for production.

All other AUTH-PASSWORD-01 completion and release gates remain unchanged.
