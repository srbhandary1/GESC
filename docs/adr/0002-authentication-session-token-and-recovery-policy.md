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

# ADR-0002: Authentication, session, token and recovery policy

## Status

Accepted on 2026-07-30 under
[APP-0001](../APPROVALS.md#app-0001).
This ADR resolves DR-0001.
Amended on 2026-09-01 by
[ADR-0033](0033-android-in-app-password-sign-in.md): Android **first issue** is
a first-party in-app password sign-in. Token lifetimes, rotation, reuse-revoke,
installation binding and native secure storage are unchanged. Passport
Authorization Code with S256 PKCE remains available and unused for guest
password sign-in.
Amended on 2026-09-01 by
[ADR-0034](0034-federated-google-facebook-sign-in.md): linked Google or Facebook
may issue the same web session or Android token family. Android federated
authorization uses origin-pinned Custom Tabs; the password form stays in-app.

## Context

The master plan selects Sanctum same-origin cookie sessions for web/PWA and
Passport Authorization Code with PKCE for Android. It deliberately leaves exact
session and token lifetimes, token rotation, installation binding, identity
recovery and phone-number reassignment for this ADR before PR-003.

Phone possession can change. It proves control of an authenticator at a moment
in time; it must not become the permanent user identifier or silently transfer
an existing identity.

## Decision

### Web/PWA sessions

- Use Sanctum stateful authentication over same-origin Secure, HttpOnly and
  appropriate SameSite cookies with CSRF protection.
- Never expose a bearer token or session credential to browser JavaScript or
  store one in `localStorage` or IndexedDB.
- Ordinary sessions expire after 120 minutes of inactivity and have a
  seven-day absolute lifetime.
- Privileged sessions expire after 30 minutes of inactivity and have a 12-hour
  absolute lifetime.
- A sensitive privileged operation requires an MFA assertion no older than 15
  minutes.
- Logout, account closure, credential recovery and a security reset revoke the
  applicable sessions. Users must be able to list and revoke their sessions
  before production.

### Android OAuth tokens

- First issue uses a first-party in-app username/password form (ADR-0033).
  Linked Google or Facebook may also issue that family (ADR-0034).
  The server issues the Passport access and rotating refresh family for the
  approved public Android client. Do not use a client secret. Do not enable
  `grant_type=password` on `/oauth/token`.
- Passport Authorization Code with S256 PKCE remains implemented for the
  public client. Guest **password** sign-in does not open the system browser.
  Guest **Google/Facebook** sign-in opens origin-pinned Custom Tabs on
  `/oauth/social/{provider}/authorize` because the IdP forbids WebView OAuth.
- Validate registered redirect URIs and OAuth state when the PKCE authorize
  journey is used. Add nonce validation when an identity protocol layered on
  that flow requires it.
- Validate registered redirect URIs and OAuth state. Add nonce validation when
  an identity protocol layered on the flow requires it.
- Access tokens have a 15-minute lifetime.
- Refresh tokens are single-use and rotate on every successful refresh.
- A refresh-token family expires after 30 days of inactivity and after a
  90-day absolute lifetime, whichever occurs first.
- Refresh-token reuse revokes the entire family and produces a security audit
  event without recording the token.
- Logout, account closure, credential recovery and a security reset revoke the
  applicable token families.
- Keep the access token in memory while the app is active where practical.
  Persist credentials only in a reviewed native secure-storage implementation,
  never ordinary Capacitor preferences.

### Installation binding

Each app installation generates a random ULID that is independent of platform
or hardware identifiers. The authorization server associates the token family
with that installation identifier and requires it during refresh and
revocation. The identifier is kept with the credential material in reviewed
native secure storage.

Do not use IMEI, Android ID, advertising ID, hardware fingerprinting or another
stable device identifier. Installation binding is an additional replay signal,
not a substitute for refresh rotation, revocation or secure storage.

### OTP policy

- OTPs contain six decimal digits, expire after five minutes and are
  single-use.
- A challenge permits at most five failed validation attempts. Expiry,
  success, supersession or the attempt limit consumes the challenge.
- Request and verification endpoints use abuse controls that can be tightened
  through configuration without changing the public contract.
- Store only a keyed digest of an OTP, use constant-time comparison, and never
  log the OTP, digest, destination phone number or provider payload.
- PR-003 uses only a fake/local adapter. A real messaging provider requires a
  later reviewed configuration and privacy boundary.

### Recovery and phone-number reassignment

- A phone number is a replaceable authentication identity, not the user's
  public or database identity.
- Self-service recovery requires a one-time recovery code with at least 128
  bits of entropy or an independently verified recovery contact. Possession of
  the current phone number alone does not recover an existing account.
- Recovery codes are stored as keyed digests, are shown once and are consumed
  on use.
- A phone-number change requires a current authenticated session, verification
  of the new number, a 24-hour pending period, notification through every
  available old and new verified channel, and global session/token-family
  revocation when the change completes.
- Existing phone bindings are never automatically transferred or merged. A
  reassignment case requires an audited seven-day hold and an authorized
  support decision using evidence that does not disclose another user's
  account.
- Privileged accounts cannot use SMS-only recovery.
- Responses and logs must not reveal whether a phone number belongs to an
  account.

Session/token lifetime values and operational rate limits must be centralized
configuration with secure defaults. A relaxation is a security decision and
requires new approval evidence.

## Alternatives considered

### Long-lived mobile access tokens

Rejected because compromise would remain useful without a refresh event where
rotation and replay detection can operate.

### Non-rotating refresh tokens

Rejected because replay cannot reliably distinguish the legitimate client from
a copied credential and revoke the affected family.

### Stable hardware device identifiers

Rejected because they add privacy risk, can be unreliable or reset, and do not
replace possession of securely stored credential material.

### Phone possession as account identity and recovery

Rejected because phone numbers can be recycled, reassigned, ported or
temporarily compromised.

## Consequences

### Positive

- Web and Android share identity semantics while using platform-appropriate
  credential transport.
- Short access lifetimes and refresh rotation bound token replay.
- Account recovery does not silently transfer identity with a phone number.
- Privileged operations have an explicit freshness rule.

### Negative and trade-offs

- Rotation, token-family state, session activity and revocation require
  authoritative server records and concurrency-safe updates.
- A lost recovery code/contact may require a slower audited support path.
- Pending phone changes and reassignment holds create intentional user friction.
- Installation binding cannot prevent abuse after complete device compromise.

## Security, privacy and tenancy impact

Authentication events must not log credentials, OTPs, raw phone numbers or
recovery evidence. Phone numbers require normalized, protected storage and
purpose-bound access. Enumeration-safe responses, throttling, rotation-reuse
tests and session/token revocation tests are required. Organization
authorization is evaluated separately after authentication under ADR-0003.

Independent security and privacy specialist review remains mandatory before
production under ADR-0001.

## Authoritative records and consistency impact

OTP consumption, refresh rotation, reuse detection, phone changes, recovery and
revocation are authoritative multi-write operations. They require database
transactions, row-level concurrency control and idempotency where a client can
replay a request. Audit events record identifiers and outcome categories, not
secret material.

## API, client and operational impact

PR-003 must document the web authentication, OTP, logout, mobile authorization,
refresh and revocation contracts in OpenAPI where applicable. The shared client
authentication interface keeps Sanctum and Passport transport details in
platform adapters. Operations require alerts for abnormal OTP attempts,
recovery activity and refresh-token reuse without exposing sensitive values
before the affected path enters production; PR-003 does not need to build
production alert integrations.

## Master-plan amendment

This ADR did not amend the master plan when accepted. [ADR-0033](0033-android-in-app-password-sign-in.md)
amends master-plan §11.2 in a later accepted change.

## Approval evidence

- Evidence ID: `APP-0001`
- Approval reference: `Codex task 019fae0d-d5d2-7e32-b2c2-ced466b4b0cb`
- Acceptance date: 2026-07-30, Asia/Kolkata
- Human approver identifier: `Codex task accountable product owner`
- Roles exercised: Accountable Product Owner; Technical Decision Owner;
  Security Reviewer; Privacy Reviewer
- Exact human statement: `Continue`
- Codex role: author and evidence recorder only; never approver
- Master-plan impact: none

## Verification

PR-003 acceptance requires automated coverage for:

- web inactivity, absolute lifetime, CSRF, logout and revocation behavior;
- the PKCE S256 public-client foundation, redirect/state validation and
  rejection of a public-client secret;
- access and refresh lifetimes, single-use rotation, concurrent refresh and
  family revocation after reuse;
- native-adapter boundaries and the absence of browser credential persistence;
- OTP expiry, single use, attempt limit, enumeration safety and safe logging;
- phone-number identity being replaceable rather than the stable user key; and
- the privileged-MFA extension point expressing the required freshness policy.

Use real PostgreSQL for authoritative concurrency behavior.

Recovery-code consumption, phone-change delay and notification, reassignment
hold, global revocation after identity changes and rejection of SMS-only
privileged recovery require automated tests before each affected workflow is
implemented or exposed. They are accepted policy constraints, not additional
PR-003 backlog deliverables.

## Follow-up

- Complete independent security and privacy specialist review before
  production.
- Select and review a native secure-storage implementation before Android
  credential persistence is enabled.
- Select a real OTP provider only in its authorized production checkpoint.

## References

- [OAuth 2.0 for Native Apps (RFC 8252)](https://www.rfc-editor.org/rfc/rfc8252.html)
- [OAuth 2.0 Security Best Current Practice (RFC 9700)](https://www.rfc-editor.org/rfc/rfc9700.html)
- [NIST SP 800-63B digital identity guidelines](https://pages.nist.gov/800-63-4/sp800-63b.html)
