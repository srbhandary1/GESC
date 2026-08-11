# Password authentication operations runbook

**Checkpoint:** AUTH-PASSWORD-01
**Authority:** APP-0056, APP-0057, D-0164, D-0169, ADR-0021,
AUTH-PASSWORD-01 and DR-0027
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
- Privileged commands require the same current password to have been confirmed
  on the exact privileged web session within five minutes. This is one-factor
  freshness, not MFA; tenant, membership, permission and appointment checks
  still run at command time.
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
- two or more stable active platform users in
  `PLATFORM_IDENTITY_RECOVERY_ACTOR_USER_IDS`, provisioned and identity-proofed
  outside organization administration.

Do not use repository example secrets in production. Clear and rebuild Laravel
configuration only through the reviewed deployment procedure.

## Existing-user establishment

During `credential_migration`, an existing user performs the current legacy
sign-in once, opens **Username and password security**, chooses a globally unique username
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
3. The user supplies challenge ID, username, raw token and a policy-compliant
   new password. Five failed attempts expire the challenge.
4. Successful recovery advances the credential version and revokes all web
   sessions and Android token families.

Privileged users and platform emergency/recovery actors are ineligible for
ordinary recovery.

## Privileged recovery

1. Open a content-minimized support case using reason
   `identity_recovery.verified_support_case` and an opaque external evidence
   reference. Never put identity documents or personal data in the API.
2. Two distinct configured active platform recovery operators approve the
   exact case version and evidence digest. The target cannot approve.
3. The second approval starts a fresh 24-hour delay. Approvals are append-only.
4. At completion, the service revalidates both approvers, the target, case
   version, evidence digest, expiry and delay under database locks.
5. Completion sends a short-lived ordinary reset token to an already verified
   contact, globally revokes credentials, and writes content-free platform
   audit/outbox evidence. Operators never see or set the new password.

Any mismatch, expired capability, unavailable delivery or incomplete approval
fails closed. Escalate through the incident process; do not bypass the service
or mutate credential tables directly.

### Local excluded-account establishment under APP-0057

This procedure is limited to the local non-production cutover recorded by
D-0166 and the exact manifest approved by APP-0057. It is not a production
recovery procedure.

1. Take and verify a fresh PostgreSQL backup before applying the additive
   recovery migration or reconciling exclusions.
2. Enable `AUTH_PASSWORD_CUTOVER_EXCLUSION_RECONCILIATION_ENABLED` only in the
   local environment. Configure the exact reviewed manifest SHA-256,
   `AUTH_PASSWORD_RECOVERY_DELIVERY_DRIVER=protected-local`, and an absolute
   delivery directory below `/tmp`.
3. Run `auth:reconcile-password-cutover-exclusions --manifest=... --dry-run`,
   inspect the exact 16-user/two-ready/14-excluded result, and then run the same
   command without `--dry-run`. The manifest must be an owner-only mode-`0600`
   file. The imported rows are append-only and linked to the existing global
   cutover marker.
4. Recovery actor 1 signs in, opens **Username and password security**, confirms
   their current password, and uses `/password-recovery-commissioning.html` to
   open and approve the exact excluded-operator case. Actor 2 signs in through
   a separate session, confirms their own current password, and approves the
   same case. Neither actor may be the target or enter target credentials.
5. Wait the complete 24 hours measured from the second approval. There is no
   local time override. After the delay, either still-authorized configured
   actor reconfirms their password and completes the case. Completion creates
   one 256-bit, single-use establishment token that expires after 15 minutes.
6. Hand control to the target at `/password-establishment.html`. The raw token
   remains in an owner-only mode-`0600` local delivery file and is submitted by
   the protected localhost surface; it is not rendered or stored by the
   browser. The target chooses their own globally unique username and password.
7. Verify challenge consumption, deletion of the delivery file, target
   `credential_cutover_at`, global session/token revocation, unchanged role and
   membership authority, password sign-in, and a fresh password confirmation.
   Then disable the reconciliation switch and remove any empty delivery
   directory through the incident evidence procedure.

Wrong manifest/owner/target, fewer than two distinct actors, stale password
confirmation, early completion, expired/replayed token, username collision,
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
