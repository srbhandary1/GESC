# DR-0030 — Unified role-based login and recovery for production

**Status:** Resolved — Option A accepted under APP-0059 / D-0186  
**Opened:** 13 August 2026  
**Needed by:** AUTH-SIMPLE-01, current administrator credential establishment,
LPL-01 transcript reconciliation and production authentication  
**Authority affected:** Master plan §§7.2, 8.2 and 11.2; ADR-0002;
ADR-0021; DR-0027 / APP-0057 / D-0169

## 1. Product-owner direction

The product owner requires the same simple authentication model in production
as in ordinary use:

> I want the same model for production as well. This is not a financial app.
> This is just a education app that does not need complexity.

The requested product model is one username/password sign-in for every user,
with access determined after authentication by roles, organization membership
and learner enrollment. Student level or tier is enrollment state, not a
different credential or login flow.

The current two-recovery-actor, fresh-confirmation, 24-hour delayed privileged
recovery is disproportionate to this product direction. It also blocked the
already-approved LPL-01 work after the password migration left the only local
organization administrator without a credential.

## 2. Boundaries that remain necessary

An education product is not a financial application, but it still controls
personal learner data, tenant membership, privacy requests, content rights and
published learning records. Simplification therefore does not remove these
baseline controls:

- Argon2id password hashing and enumeration-safe, rate-limited sign-in;
- Secure, HttpOnly Sanctum cookie sessions for Web/PWA;
- Passport Authorization Code with S256 PKCE and native secure storage for
  Android;
- organization-scoped roles, permissions and cross-tenant authorization;
- short-lived, random, single-use setup/recovery links stored only as keyed
  digests;
- global session and Android token-family revocation after credential recovery;
- content-free audit evidence; and
- no administrator-visible, shared, default or administrator-selected password.

These protections are implementation details around one simple user journey;
they do not create separate learner, staff or administrator login products.

## 3. Options

### Option A — One production login and verified-contact recovery for every role (recommended)

Adopt the product-owner model in production and non-production.

1. **One sign-in.** Every learner, trainer, reviewer, organization
   administrator and platform administrator signs in through the same global
   username/password surface. The server resolves current organization roles
   and permissions after authentication. A user chooses organization context
   only when more than one is available.
2. **Learner tiers are enrollment.** Level, tier, cohort, program and curriculum
   pinning remain deterministic enrollment/progression records. They never
   create tier-specific usernames, passwords or sign-in pages.
3. **One-time account setup.** An authorized invitation, admission or staff
   appointment may send a 256-bit, 15-minute, single-use credential-setup link
   to an already verified email or phone contact. The user chooses their own
   globally unique username and password. The inviter cannot view the link,
   token or password and cannot set a permanent password.
4. **Same recovery for every role.** Any active credential-ready user,
   including an organization or platform administrator, may request the same
   enumeration-safe reset. A 256-bit, 15-minute, single-use link is delivered
   only to the user's already verified recovery contact. Successful recovery
   advances credential version and revokes every Web session and Android token
   family.
5. **Credential-unready migration accounts.** A reviewed migration exclusion
   with an already verified contact may receive the same one-time setup link
   immediately. No recovery actors, approvals, evidence digest or 24-hour
   delay are required. Existing membership, role and permission records are
   revalidated but never created or elevated by credential setup.
6. **Lost-contact support.** One authenticated platform administrator holding
   the exact `platform.account_recovery.manage` capability may replace a
   recovery contact only after recording a bounded support reason and external
   verification reference. The platform administrator still cannot see the
   generated token or choose the username/password. The former contact is
   notified when technically possible, all sessions/tokens are revoked, and
   the action creates content-free audit evidence. Organization roles alone
   never grant global account-recovery authority.
7. **Sensitive actions.** Existing current-role/current-permission checks and
   five-minute confirmation of the same password remain for high-impact Web
   mutations. This is a small session-freshness safeguard, not MFA and not a
   second login system.
8. **Retire exceptional recovery.** Stop creating privileged recovery cases
   and approvals, remove the two-recovery-actor/24-hour APIs and commissioning
   pages, and remove recovery-only actor configuration from active runtime
   behavior. Preserve existing rows as restricted audit history; do not delete
   or rewrite accepted evidence.
9. **Current local administrator.** Cancel or supersede the still-uncompleted
   DR-0027 case through an audited transition, issue the ordinary one-time
   setup link to the already verified contact, and let the target establish
   their own credential. Do not mutate the password directly or alter tenant
   authority.
10. **Delivery gate.** Production activation requires a reviewed email/SMS
    delivery adapter, protected secret/configuration custody and successful
    delivery/replay/expiry/rate-limit/log-redaction tests. A local protected
    file remains permitted only for loopback non-production use.
11. **Contracts and migration.** AUTH-SIMPLE-01 must update schema lifecycle,
    APIs, OpenAPI/generated clients, Web/Android journeys, runbooks, privacy
    inventory and automated SQLite/PostgreSQL/browser tests. The transition is
    additive and preserves historical recovery evidence.

Security consequence accepted by this option: compromise of an
administrator's password or verified recovery contact can lead to privileged
account takeover. Session revocation, rate limits, password confirmation,
contact-change audit/notification and tenant authorization reduce impact but
do not provide an independent authentication factor.

Approval text:

```text
Approve DR-0030 Option A as written.
```

### Option B — Keep the existing privileged recovery distinction

Keep ordinary unified username/password sign-in, but retain two distinct
configured recovery actors, two fresh password confirmations, append-only
approvals and the mandatory 24-hour delay for privileged users and
credential-unready privileged migration accounts.

This preserves ADR-0021 and DR-0027 but does not satisfy the requested simple
production recovery model.

Approval text:

```text
Approve DR-0030 Option B as written.
```

## 4. Stop condition

Until one option is accepted exactly, do not weaken, bypass or directly mutate
the current recovery case, credential records, verified contact, membership,
role or permission authority. The current DR-0027 case remains valid and
uncompleted. Repository work may document and test a selected replacement only
after acceptance.

Option A acceptance authorizes a bounded AUTH-SIMPLE-01 specification and
implementation. It does not itself activate a production delivery provider,
deploy production, publish curriculum, enable AI/voice, authorize Google Play
or waive independent release review.

## 5. Resolution

Option A was accepted exactly as written on 13 August 2026 under
[APP-0059](APPROVALS.md#app-0059) / D-0186. The approved pre-resolution
request has SHA-256
`3a671fbf56fa482d1d950668ed0ef76744dbcfe90931b42e1d753667a8a51534`.

Acceptance authorizes the bounded
[AUTH-SIMPLE-01 specification](AUTH-SIMPLE-01_SPEC.md) and implementation. It
does not activate a production delivery provider, mutate tenant authority,
publish curriculum, enable AI/voice, authorize Google Play or waive
independent review.
