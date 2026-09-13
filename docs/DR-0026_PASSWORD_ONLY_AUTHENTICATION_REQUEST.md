# DR-0026 — Username/password-only authentication for every role

**Status:** Resolved — Option B accepted under APP-0055 / D-0163 / ADR-0021
**Needed by:** AUTH-PASSWORD-01 and LPL-01 human transcript reconciliation
**Authority affected:** Master plan §§7.2, 11 and 18.2; ADR-0002; DR-0013 Option A / APP-0038 / D-0123
**Opened:** 11 August 2026
**Resolved:** 11 August 2026

## 1. Decision needed

The product owner has requested username-and-password sign-in for every learner,
trainer, reviewer, organization administrator and platform administrator, with
no two-factor authentication.

That is not the current contract. Direct learners currently authenticate with a
mobile-number OTP; institutional learners use an invitation/admission path plus
OTP or one-time setup; and sensitive privileged mutations require a fresh TOTP
assertion. Removing both OTP sign-in and privileged TOTP would amend the master
plan and accepted ADR-0002, supersede DR-0013 Option A, change credential and
recovery authority, and weaken the protection around tenant administration,
privacy commands, rights confirmation, content publication and other
authoritative operations.

No login, credential, schema, API or MFA behavior changes merely because this
request exists. The current implementation remains fail closed until one option
is explicitly accepted and its replacement ADR and bounded implementation
specification are recorded.

## 2. Current authoritative behavior

- Web/PWA authentication uses Sanctum same-origin cookie sessions; Android uses
  Passport Authorization Code with S256 PKCE in the system browser.
- Mobile numbers are replaceable authentication identities, not user or tenant
  identifiers. OTPs are short-lived, single-use and stored only as keyed
  digests.
- Privileged mutations are web-only and require an independent, fresh
  authenticator-app TOTP assertion. Recovery codes cannot be replaced by SMS
  possession alone for a privileged account.
- Organization authorization, role/permission checks and current membership
  selection occur after authentication and remain separate from the sign-in
  method.

## 3. Decision profiles

| Option | Routine sign-in | Sensitive privileged actions | Consequence |
|---|---|---|---|
| **A — Username/password for everyone with retained privileged MFA (recommended)** | Every role uses a unique username and password; OTP is removed from routine sign-in | Retain fresh TOTP or replace it later with a separately reviewed passkey | Meets the requested learner simplicity while preserving the accepted defense against stolen/reused staff passwords |
| **B — Username/password only for everyone (product-owner requested)** | Every role uses only a unique username and password | Require recent re-entry of the same password, but no independent factor | Satisfies the stated product direction, but a single stolen/reused password can authorize high-impact tenant, privacy, rights and publication commands |
| C — Keep the accepted model | Learners retain current OTP/setup paths | Privileged users retain fresh TOTP | No architecture migration, but does not satisfy the product-owner request |

Option B is intentionally not the security recommendation. Re-entering the
same password is useful session-freshness evidence but is still one factor and
must never be described as MFA.

## 4. Exact Option B contract

If Option B is accepted as written, all of the following are part of the
decision.

### 4.1 Identifier and enrollment

1. Every user has one platform-global, case-insensitive username independent
   of organization membership, mobile number, display name and immutable user
   ID. The stored canonical form is lower-case ASCII and matches
   `^[a-z][a-z0-9._-]{3,63}$`.
2. Login, recovery and enrollment responses and materially observable behavior
   are enumeration-safe. Public responses do not reveal whether a username,
   mobile number or recovery contact exists, is disabled or is privileged.
3. Direct learners choose an available username during account setup.
   Institutional invitations and staff appointments authorize membership or
   role assignment, not ongoing authentication. They lead to a one-time
   password-establishment flow and never create a shared or retrievable default
   password.
4. Existing accounts use a bounded migration ceremony to claim or receive a
   username and establish a password. Current OTP/TOTP may be used only to
   authenticate that transitional ceremony before the cutover; it is not a
   post-cutover sign-in factor.
5. Username changes require an authenticated, audited account process with
   collision checks and global session/token-family revocation. Usernames are
   never reused immediately after account closure; exact reservation and
   eventual reuse policy belongs in the implementation specification.

### 4.2 Password policy and storage

1. A password contains 15 to 128 Unicode code points, accepts spaces and all
   ordinary Unicode characters, is normalized consistently using NFC before
   hashing, is never silently truncated and has no forced upper/lower/digit/
   symbol composition rule.
2. Establishment and change compare the complete candidate against a reviewed,
   versioned blocklist of common, expected, context-derived and known-compromised
   passwords. The service provides strength guidance and supports paste,
   password managers and accessible reveal controls.
3. Passwords are stored only as individually salted Argon2id hashes through a
   project-owned credential service. Parameters are benchmarked on the target
   environment, meet or exceed the reviewed OWASP floor, are versioned, and are
   upgraded after successful authentication when policy changes.
4. Raw passwords never enter logs, traces, analytics, audits, queues, browser
   storage, ordinary Capacitor preferences, support tooling or administrator
   views. No operator can retrieve or set a permanent password for another
   user.
5. Periodic password rotation is not required. A confirmed or credibly
   suspected compromise forces a reset, revokes sessions and Android token
   families, and records only content-free security evidence.

This policy follows the current [NIST SP 800-63B memorized-secret
requirements](https://pages.nist.gov/800-63-4/sp800-63b.html) and the
[OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
for a password used without MFA. Password storage follows the
[OWASP Password Storage Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html).
Those references do not make password-only privileged access equivalent to
MFA or waive independent review.

### 4.3 Sign-in, sessions and sensitive actions

1. Web/PWA continues to use Secure, HttpOnly, same-origin Sanctum cookies with
   CSRF protection. Android continues to use Passport Authorization Code with
   S256 PKCE in the system browser and reviewed native secure storage; the
   Android app never embeds, receives or stores the password.
2. Username/password verification is throttled by normalized account key,
   source/network signals and global abuse controls. Backoff avoids an easy
   permanent-lockout denial of service. Success and failure produce
   content-free, non-enumerating security events.
3. Existing session inactivity/absolute lifetimes, session listing/revocation,
   refresh-token rotation/reuse detection and installation binding remain in
   force unless a later decision explicitly changes them.
4. Every action currently protected by fresh TOTP instead requires successful
   re-entry of the current password no more than five minutes earlier, bound
   server-side to the exact web authentication session. Password confirmation
   is invalidated by password change/recovery, security reset, session
   revocation, role/membership invalidation and other existing revocation
   events.
5. Authorization, tenancy and authority checks are still evaluated at command
   time. Password confirmation does not preserve a role, membership,
   appointment, rights revision or other authorization that has ceased to be
   effective.

### 4.4 Recovery and compromise response

1. Routine sign-in is username and password only. Recovery is a distinct
   security process, not an alternate everyday login screen.
2. Ordinary learners may reset through a separately verified recovery contact
   using a cryptographically random, single-use, short-lived token/code. The
   response remains enumeration-safe and the token is stored only as a keyed
   digest. Existing mobile identity may become a recovery contact only after
   an explicit purpose/retention migration.
3. A privileged account cannot use mobile/SMS possession alone for recovery.
   It requires an audited support case with two authorized operators, evidence
   that does not expose another user's account, a time delay and notification
   through every available verified channel. This is operational recovery,
   not MFA at sign-in.
4. Successful reset or support recovery revokes all web sessions and Android
   token families, clears password-confirmation freshness, requires a new
   password on the next controlled step and emits privacy-safe audit/outbox
   evidence.
5. Security questions, reusable recovery answers, emailed plaintext passwords,
   administrator-visible passwords and shared institutional credentials are
   prohibited.

The implementation specification must threat-model and test recovery before
cutover. Removing MFA without a usable recovery design would trade login
friction for unsafe support bypasses and account loss.

## 5. Required implementation boundary after approval

Approval does not directly authorize code changes. It authorizes preparation
of one bounded `AUTH-PASSWORD-01` specification and an ADR that explicitly
amends the master plan and ADR-0002. That specification must cover, at minimum:

- additive credential/username schema, uniqueness and authoritative
  transaction rules;
- password establishment/change/confirmation/recovery APIs, OpenAPI and
  generated clients;
- accessible English/Kannada web flows and system-browser Android continuity;
- migration for every existing user class without shared/default passwords or
  account takeover through phone-number reassignment;
- replacement of fresh-TOTP middleware with exact fresh-password confirmation
  on the same protected command set;
- revocation/deprecation and later retention-governed removal of OTP/TOTP
  factors, challenges and recovery material;
- same-tenant, cross-tenant, enumeration, throttling, concurrent reset/change,
  session/token revocation and Android PKCE tests; and
- rollback/cutover evidence that never leaves some accounts authenticating
  under an undocumented mixture of policies.

AUTH-PASSWORD-01 must complete repository verification and critical E2E before
the LPL-01 reconciliation UI is changed. The existing TOTP-protected
reconciliation endpoint remains protected in the meantime; it must not be
temporarily bypassed.

## 6. Security and release consequence

Accepting Option B creates a deliberate exception to the master plan's
privileged-MFA baseline and supersedes the corresponding part of ADR-0002 and
DR-0013. It increases exposure to credential stuffing, password spraying,
phishing, password reuse and offline guessing after a credential-store breach.
The controls above reduce but do not eliminate that single-factor risk.

No option in this request authorizes production, Google Play release, real
learner data, or waiver of independent security, privacy, tenancy,
accessibility and language/content review. Reviewers must assess the accepted
exception explicitly rather than being told that password re-entry is MFA.

## 7. Resolution requested

To preserve privileged MFA while simplifying learner and staff sign-in:

```text
Approve DR-0026 Option A as written.
```

To accept username/password as the only factor for every role, including
privileged administrators, and accept the documented single-factor risk:

```text
Approve DR-0026 Option B as written and accept its documented password-only privileged-access risk.
```

To keep the current OTP/TOTP design:

```text
Reject DR-0026 and retain the accepted authentication model.
```

Any change to username scope, minimum password length, recovery, protected
action coverage, session transport or migration semantics requires revised
text before approval.

## 8. Resolution

Option B was accepted exactly as written on 11 August 2026 under
[APP-0055](APPROVALS.md#app-0055), D-0163 and
[ADR-0021](adr/0021-username-password-only-authentication.md). The exact
approved pre-resolution request has SHA-256
`847486461ffbf92610427744539bea5a3fd607b2988d8aea8a9ff54fbcd0576d`.

This acceptance selects username/password as the only sign-in and privileged
authentication factor and records the product owner's explicit acceptance of
the documented single-factor risk. In accordance with section 5, it authorizes
the replacement ADR, master-plan amendment and bounded AUTH-PASSWORD-01
specification. It does not directly authorize runtime implementation,
production, Google Play release or an independent-review waiver.
