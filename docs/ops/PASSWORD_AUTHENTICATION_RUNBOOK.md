# Password authentication operations runbook

**Checkpoint:** AUTH-SIMPLE-01
**Authority:** APP-0059, APP-0060, D-0186, D-0193, ADR-0022, ADR-0023 and AUTH-SIMPLE-01
**Production/public release:** Not authorized

## Modes and invariants

`AUTHENTICATION_MODE` accepts only `legacy_otp`, `credential_migration` or
`password_only`; any other value fails closed. A completed global cutover
marker is authoritative and forces `password_only` even if configuration is
stale.

- Web uses Secure, HttpOnly Sanctum cookies and CSRF protection. Android keeps
  Passport Authorization Code with S256 PKCE in the system browser.
- Passwords are 15–128 NFC-normalized Unicode code points and are stored only
  as Argon2id hashes. They must never be copied into commands, tickets, logs,
  screenshots or chat.
- Routine operator commands use the signed-in session plus current tenant,
  membership, role, permission and appointment checks. They do not require
  another password entry. Credential, recovery-contact and administrator-
  authority changes may require the same current password on the exact Web
  session within five minutes; this is one-factor freshness, not MFA.
- Password or username change and every recovery path revoke all web sessions
  and Android token families.

## Required configuration

Before `credential_migration`, configure and verify:

- a separately managed, minimum 256-bit `AUTH_RECOVERY_DIGEST_KEY`;
- an owner-readable absolute `AUTH_PASSWORD_BLOCKLIST_PATH` and exact
  `AUTH_PASSWORD_BLOCKLIST_SHA256` for the approved licensed artifact;
- Argon2id settings at or above 65,536 KiB, three iterations and one thread;
- a reviewed `PasswordRecoveryDeliveryAdapter` for verified contact delivery;
  the repository default is intentionally unavailable; and
- at least one stable, active, credential-ready platform user in
  `PLATFORM_ACCOUNT_RECOVERY_MANAGER_USER_IDS`, provisioned and
  identity-proofed outside organization administration. This allowlist grants
  only `platform.account_recovery.manage`; organization roles never grant it.

Do not use repository example secrets in production. Clear and rebuild Laravel
configuration only through the reviewed deployment procedure.

## Existing-user establishment

During `credential_migration`, an existing user performs the current legacy
sign-in once, opens **Account settings**, chooses a globally unique username
and their own password, and is signed out everywhere. They then sign in with
that username and password. An institution or operator may reserve a candidate
username, but must never choose, transmit or store the user's password.

For local commissioning, the web application is served on port `5174`. The
local fixed OTP is permitted only for the one-time pre-cutover claim ceremony
when the local environment explicitly enables it. It is not accepted as a
password and is retired after cutover.

## Ordinary recovery

1. Accept a username through the public recovery request. The response is
   always generic; do not confirm account or contact existence.
2. Delivery goes only to an already verified eligible contact. The challenge
   expires after 15 minutes, is single-use, and is superseded by a newer one.
   The reviewed provider link opens
   `/password-recovery.html#challenge_id=…&token=…`; the fragment is removed
   immediately and is never sent as a request URL or stored by the browser.
3. The user supplies challenge ID, username, raw token and a policy-compliant
   new password. Five failed attempts expire the challenge.
4. Successful recovery advances the credential version and revokes all web
   sessions and Android token families.

The same eligibility and challenge lifecycle apply to every role. Role,
organization, level, tier and cohort are resolved only after authentication.

## Lost-contact recovery

1. The configured account-recovery manager signs in and confirms the same
   current password within five minutes.
2. Complete external identity verification and retain the authoritative
   evidence outside this system. Send only an opaque 8–160 character reference
   and exact reason `account_recovery.verified_contact_replacement`.
3. Replace the contact through
   `PUT /platform/account-recovery/users/{user_id}/contact`. The manager must
   not be the target and must never see or set target credentials.
4. Verify that the old contact is disabled, the replacement is represented
   only by its keyed digest and masked hint, all target Web/Android credentials
   are revoked, former-contact notification outcome exists and tenant
   authority is unchanged.

Any mismatch or stale confirmation fails closed. Escalate through the incident
process; do not bypass the service or mutate identity/credential tables.

### Credential-unready migration setup

This procedure applies only to an active account already recorded in the exact
append-only cutover exclusion manifest with an existing verified contact.

1. Take and verify a fresh PostgreSQL backup before applying the additive
   recovery migration or reconciling exclusions. The snapshot must be created
   with owner-only mode `0600`; verify gzip readability, byte count and SHA-256
   before proceeding.
2. Enable `AUTH_PASSWORD_CUTOVER_EXCLUSION_RECONCILIATION_ENABLED` only in the
   local environment. Configure the exact reviewed manifest SHA-256,
   `AUTH_PASSWORD_RECOVERY_DELIVERY_DRIVER=protected-local`, and an absolute
   delivery directory below `/tmp`.
3. Run `auth:reconcile-password-cutover-exclusions --manifest=... --dry-run`,
   inspect the exact 16-user/two-ready/14-excluded result, and then run the same
   command without `--dry-run`. The manifest must be an owner-only mode-`0600`
   file. The imported rows are append-only and linked to the existing global
   cutover marker.
4. The configured manager signs in, confirms the current password and requests
   setup through `POST /platform/account-recovery/setups` with reason
   `account_recovery.migration_setup` and an opaque evidence reference. There
   is no second actor or delay. The manager cannot be the target and cannot
   enter or retrieve target credentials.
5. Successful delivery creates one 256-bit, single-use setup token that expires
   after 15 minutes. Reviewed provider links open
   `/password-establishment.html#challenge_id=…&token=…`. For local
   protected commissioning, hand control to the target at
   `/password-establishment.html` in protected-local commissioning. The token
   remains in an owner-only mode-`0600` local delivery file and is submitted by
   the protected localhost surface; it is not rendered or stored by the
   browser. The page uses a `same-origin` referrer policy only so Sanctum can
   recognize its localhost metadata GET as stateful; history erases any public
   setup fragment before that request, and no referrer is sent cross-origin.
   The target chooses their own globally unique username and password.
6. Verify challenge consumption, deletion of the delivery file, target
   `credential_cutover_at`, global session/token revocation, unchanged role and
   membership authority, password sign-in, and a fresh password confirmation.
   Then disable the reconciliation switch and remove any empty delivery
   directory through the incident evidence procedure.

Wrong manifest/target, unauthorized manager, stale password confirmation,
expired/replayed token, username collision,
unavailable protected delivery, or any non-loopback request fails closed. Do
not display or copy the raw token, password, contact value, transcript, or
audio into a ticket, screenshot, chat, log, audit or outbox record.

## Cutover

The manifest contains exactly one `exclusions` array. Every exclusion contains
only `user_id`, a reason matching `cutover_excluded.*`, and an approved recovery
`owner_user_id`. Every active user must be credential-ready or explicitly
excluded, and ready users cannot also be excluded.

```json
{"exclusions":[]}
```

Run the following from the repository root under a maintenance window, first
against a restored non-production database and then only after separate
production authorization:

```text
php apps/api/artisan auth:password-cutover --manifest=/absolute/reviewed-manifest.json --dry-run
php apps/api/artisan auth:password-cutover --manifest=/absolute/reviewed-manifest.json
```

The command validates configuration and inventory, marks ready users, revokes
all web/Android/OAuth credentials, deletes web session payloads, clears TOTP
secrets, invalidates MFA recovery codes/challenges, supersedes OTP challenges,
and writes a content-free append-only cutover marker. Rerunning after success is
safe and reports that cutover is already complete.

After cutover, verify password sign-in for synthetic learner and operator
accounts, password confirmation on every protected command class, legacy OTP/
TOTP `410` retirement, session/token revocation, PostgreSQL evidence constraints
and operational alerts. Do not retain the raw manifest outside the approved
restricted evidence system.

## Rollback and incident response

Before cutover, an unused additive migration may be rolled back only after
confirming there are no password credentials, recovery cases or cutover marker.
After cutover there is no automatic rollback: cleared OTP/TOTP material cannot
be reconstructed. A return to a legacy mode requires a new security decision
and reviewed credential-recovery campaign.

On suspected credential compromise, suspend the account when authorized,
revoke all web sessions and Android token families, supersede active recovery
challenges, preserve content-free security evidence, and use the appropriate
recovery path. Never reset a privileged password from organization
administration.

## Open production gates

Production requires an approved blocklist source/licence/freshness record,
recovery delivery and identity-proofing procedure, independent security/privacy/
tenancy review, clean remote CI on the exact commit, physical-device evidence,
load/restore evidence and explicit production/Google Play authorization.
