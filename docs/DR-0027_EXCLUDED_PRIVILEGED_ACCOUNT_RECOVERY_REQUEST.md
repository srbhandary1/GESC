# DR-0027 — Post-cutover recovery for an excluded privileged account

**Status:** Resolved — Option A accepted under APP-0057 / D-0169
**Needed by:** AUTH-PASSWORD-01 closure and LPL-01 human reconciliation
**Authority affected:** AUTH-PASSWORD-01 §§4.5, 9 and 13; ADR-0021; D-0164–D-0168
**Opened:** 11 August 2026
**Resolved:** 11 August 2026

## 1. Decision needed

The local password-only cutover completed, but the two credential-ready users
are learner-only identities. Exactly one of the 14 excluded active users has a
recognized privileged organization-administrator membership and a verified
recovery contact. That excluded operator has no username or password.

The accepted recovery service can reset only an already credential-ready user:
it rejects a target with no username and cannot issue an establishment
challenge. The cutover command therefore accepted an exclusion that the
post-cutover runtime cannot recover. Direct credential mutation, learner-role
promotion and restoration of retired OTP/TOTP material are not authorized
workarounds.

## 2. Verified local evidence

- The global marker is `password_only`: 16 active users, two credential-ready
  and 14 approved exclusions.
- Both credential-ready users have one active learner membership and only
  `learning.participate`; neither has organization administration or curriculum
  authority.
- Exactly one credential-unready active user is privileged. That user has one
  active `organization_admin` membership and an already verified phone/email
  recovery identity.
- The mode-`0600` reviewed cutover manifest remains outside the repository at
  `/tmp/gse-auth-password-cutover-local-20260811.json`; it is 2,545 bytes with
  SHA-256
  `ce29c70aee9c7f02fc52e3f749cc4bbb87999b68d2886ca6cfb434526ae5f3e1`.
- The readable pre-cutover custom PostgreSQL backup remains available with the
  already recorded hash. No restore has been attempted.
- No role, membership, permission, username, password or recovery case was
  created by this investigation.

One implementation defect independent of this decision is corrected in the
working tree: the session privilege classifier now treats an explicitly
configured platform identity-recovery or content-emergency actor as
privileged, then falls back to permission-derived organization classification.
This grants no organization permission. Formatting, PHPStan, a 1-test/
2-assertion unit slice and a 5-test/9-assertion real-PostgreSQL integration
slice pass.

## 3. Options

### Option A — Preserve cutover and add governed excluded-user establishment (recommended)

Implement and execute this bounded local recovery path:

1. Add append-only exclusion evidence linked to the existing global cutover
   marker. A disabled-by-default reconciliation command may import only the
   exact mode-`0600` manifest whose digest is recorded above after revalidating
   all 16 current users, the marker counts, exclusion reasons, configured
   owners and credential-ready state. It cannot add an unlisted user or mutate
   organization authority.
2. Permit a privileged recovery case to target an active credential-unready
   user only when that user is in the reconciled exclusion evidence and has an
   already verified recovery identity. Existing credential-ready targets keep
   the current reset path.
3. Require the same two distinct configured active recovery actors, exact
   evidence digest, append-only approvals, fresh password confirmation and a
   full 24-hour delay. The target cannot approve. No local time bypass is
   allowed.
4. After the delay, deliver a new 256-bit, 15-minute, single-use establishment
   token through a reviewed local protected delivery surface. Persist only its
   keyed digest and safe lifecycle fields. Do not place the token, username or
   password in chat, logs, audit, outbox or browser storage.
5. The target uses the token to choose their own globally unique username and
   policy-compliant password. Recovery actors cannot see or set either value.
   Success marks that user cut over, revokes any residual sessions/tokens,
   consumes the challenge and writes content-free platform evidence.
6. Update OpenAPI/generated clients, privacy inventory, runbook, threat model,
   UI/local commissioning aid and SQLite/PostgreSQL/browser tests. Prove
   wrong-target, wrong-owner, one-actor, pre-delay, replay, expiry, concurrent
   establishment, username collision, cross-tenant and secret-non-disclosure
   failures.
7. Execute only against this local non-production database after a fresh
   backup and dry run. Production recovery delivery, production cutover,
   Google Play and independent-review waivers remain unauthorized.

This preserves the irreversible cutover and the accepted recovery delay. It
cannot resume LPL-01 immediately; the 24-hour clock begins only after two valid
approvals.

### Option B — Restore the pre-cutover local database and repeat transition (not recommended)

Authorize a one-time local incident restore of the exact pre-cutover backup,
strictly audit the expected rollback delta, use the former legacy ceremony to
establish the exact operator credential, and rerun cutover with a corrected
manifest. This is faster only if the former operator can still complete the
legacy claim.

Option B deliberately reconstitutes OTP/TOTP material that the accepted
post-cutover contract treats as retired and overwrites post-cutover session/
security evidence in the live local database. It therefore requires a new
incident ADR, a protected snapshot of current state, exact restore/replay
evidence and explicit acceptance of that temporary authentication rollback.
It does not permit direct SQL credential creation or learner promotion.

### Option C — Stop

Keep the local runtime password-only and do not recover or replace the excluded
operator. LPL-01 reconciliation and all protected administration remain
blocked.

## 4. Recommendation and authorization text

Option A is recommended. It fixes the missing recoverability contract without
restoring retired authentication factors or changing tenant authority.

To authorize Option A's exact bounded implementation and local execution:

```text
Approve DR-0027 Option A as written.
```

To request the faster but higher-risk restore plan instead:

```text
Approve DR-0027 Option B as written and accept its documented local authentication-rollback risk.
```

Any shorter delay, direct credential assignment, recovery-actor access to the
target password, learner promotion, missing verified-contact exception or
production use requires revised approval text.

## 5. Resolution

Option A was accepted exactly as written on 11 August 2026 under
[APP-0057](APPROVALS.md#app-0057) / D-0169. The approved pre-resolution
request has SHA-256
`84cb3405210f689df1895d48bb7a7c6d4638dc10e0c32e34db1ba2e96782a623`.

Acceptance authorizes the exact bounded repository implementation and local
non-production execution in section 3. It does not shorten the 24-hour delay,
authorize direct credential or organization-authority mutation, permit a
recovery actor to see/set the target secret, or authorize production, Google
Play or independent-review waivers.
