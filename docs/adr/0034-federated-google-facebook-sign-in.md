---
status: accepted
date: 2026-09-01
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
consulted:
  - Cursor agent as author and evidence recorder, not approver
informed: []
amends_master_plan: true
approval_evidence:
  - APP-0071
approval_reference: Exact human instruction in the active Cursor conversation accepting DR-0044 Option B
---

# ADR-0034: Google and Facebook as extra sign-in methods

## Status

Accepted on 1 September 2026. Recorded as D-0264 under
[APP-0071](../APPROVALS.md#app-0071). This ADR resolves
[DR-0044](../DR-0044_FEDERATED_GOOGLE_FACEBOOK_SIGN_IN_REQUEST.md) by accepting
Option B. It amends [ADR-0021](0021-username-password-only-authentication.md)
and [ADR-0002](0002-authentication-session-token-and-recovery-policy.md).
This does not authorize production or Google Play.

## Context

ADR-0021 made username/password the only routine authentication factor.
AUTH-PASSWORD-01 excluded social login and OIDC federation. The product
owner required Google and Facebook on the same sign-in screen while keeping
the existing web session and Android token family.

## Decision

1. Username/password remains required for invitation enrollment and for
   privileged password confirmation. Google and Facebook never satisfy
   password confirmation and never create, invite, or enroll a user.
2. A `federated_identities` row links one provider (`google` or `facebook`)
   plus a keyed digest of the IdP subject to exactly one user. Guest sign-in
   succeeds only when that subject is already linked. Linking requires an
   authenticated password session (web) or an authenticated Android
   link-start.
3. Web federated sign-in starts the same Sanctum session as
   `POST /api/v1/auth/password/sign-ins`.
4. Android federated sign-in issues the existing Passport family with grant name
   `federated_sign_in`. Refresh remains `refresh_token`. `/oauth/token` still
   rejects `grant_type=password`.
5. Android authorization for Google/Facebook opens origin-pinned Custom Tabs to
   first-party `/oauth/social/{google|facebook}/authorize`. The IdP redirect
   returns to the API, which issues a single-use ticket. The app exchanges it
   at `POST /api/v1/auth/mobile/social/sign-ins`. Tokens are not placed in the
   custom-scheme URL. The in-app password form is unchanged.
6. Google and Facebook stay behind `FederatedIdentityProvider`. Missing client
   configuration fails closed. Tests use a fake adapter and never call the
   live IdP.
7. Provider subjects are stored only as keyed digests. IdP email is not used
   for auto-link or account creation in this checkpoint.

## Alternatives considered

### Alternative A — Keep password-only

Rejected. The product owner required Google and Facebook.

### Alternative C — Social account creation

Rejected. It would mint users without the global username and invitation
enrollment contract.

## Consequences

### Positive

- Testers can use familiar Google/Facebook buttons after linking.
- Session and token-family transport stay unchanged after first issue.

### Negative and trade-offs

- Compromise of a linked Google or Facebook account can sign in that user
  without the Global English password. Password confirmation still protects
  privileged commands.
- Live sign-in needs operator OAuth clients and an HTTPS API origin. Staff LAN
  HTTP Custom Tabs remain fail-closed for federated authorization.
- First-time users still need invitation + username/password before linking.

## Security, privacy and tenancy impact

- Enumeration-safe generic failures and password-sign-in throttles apply.
- Organization authorization remains post-authentication under ADR-0003.
- Federated subject digests are global authentication authority, not tenant
  data. Privacy inventory records the class without exporting digests.
- Independent security, privacy and tenancy review remains mandatory before
  production under ADR-0001.

## Authoritative records and consistency impact

Linking, unlinking, and token issuance run in transactions. Uniqueness of
`(provider, subject_digest)` and `(user_id, provider)` is enforced. No change
to username, password hash, or session lifetime schema.

## API, client and operational impact

OpenAPI adds social provider, redirect, callback, link, unlink, mobile
authorization and mobile ticket-exchange routes. Generated contracts update.
Operators must set `GOOGLE_OAUTH_*` and `FACEBOOK_OAUTH_*` outside git for live
IdP use.

## Master-plan amendment

This ADR amends master-plan version 3.10 in the same accepted change and
advances it to version 3.11. It changes:

- §1.1 and §32 authentication defaults;
- §7.2 / §7.3 sign-in wording;
- §11 Android authentication row and §11.2;
- PR-003 identity-foundation wording.

Username/password enrollment, Sanctum, Passport family lifetimes, native
secure storage and tenant authorization are unchanged.

## Approval evidence

[APP-0071](../APPROVALS.md#app-0071) records the product-owner request and
authorization to implement this checkpoint.

## Follow-up

- Register live Google and Facebook OAuth clients for the HTTPS origin.
- Physical-device Google/Facebook sign-in after an HTTPS APK rebuild.
