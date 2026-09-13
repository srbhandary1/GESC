# DR-0044 — Google and Facebook as extra sign-in methods

**Status:** Resolved — Option B accepted under APP-0071 / D-0264 / ADR-0034
**Needed by:** Learners and operators who want Google or Facebook on the same
sign-in screen as username/password
**Authority affected:** Master plan §§1.1, 7.2, 11, 11.2, 32 and PR-003;
ADR-0021; AUTH-PASSWORD-01 explicit exclusions; ADR-0002
**Opened:** 1 September 2026
**Resolved:** 1 September 2026

## 1. Decision needed

The Accountable Product Owner accepted a decision request to add Google and
Facebook as extra ways to sign in while still issuing the same web Sanctum
session and the same Android Passport token family. ADR-0021 and AUTH-PASSWORD-01
currently require username/password as the only routine authentication factor
and explicitly exclude social login and OIDC federation.

That is a security-foundation change. No Google/Facebook button, IdP adapter
or federated identity table may ship until this request, its amending ADR and
the same-checkpoint master-plan amendment are recorded.

## 2. Current authoritative behavior

- Every role uses one platform-global username and password for routine sign-in.
- Invitations authorize enrollment, not continuing authentication.
- Web/PWA: Sanctum cookies + CSRF at `POST /api/v1/auth/password/sign-ins`.
- Android: in-app password form posts `POST /api/v1/auth/mobile/password/sign-ins`
  and receives the Passport family (ADR-0033). `/oauth/token` rejects
  `grant_type=password`.
- Privileged freshness is re-entry of the same password, not MFA.
- AUTH-PASSWORD-01 excludes social login, SAML, OIDC federation and passkeys.

## 3. Decision profiles

| Option | Sign-in factors | Enrollment | Consequence |
|---|---|---|---|
| A — Keep password-only | Username/password only | Unchanged | ADR-0021 unchanged |
| **B — Extra Google and Facebook (product-owner requested)** | Password remains; Google and Facebook sign in already-enrolled users | Invitation + username/password still required; social never creates an account | Same session/token family after a linked IdP assertion |
| C — Social account creation | Google/Facebook can mint a user without a username | Breaks the global username contract and invitation enrollment | Rejected |

## 4. Exact Option B contract

If Option B is accepted as written, all of the following are part of the
decision.

1. Username/password remains the enrollment credential and the only privileged
   freshness factor. Google/Facebook never replace password confirmation.
2. Google and Facebook are extra sign-in methods for users who already have a
   usable password credential. They do not enroll, invite, or create users.
3. A provider subject is linked to at most one user. A user may link at most
   one Google account and at most one Facebook account. Linking happens after
   password authentication (web account security, or an authenticated Android
   link-start). Guest social sign-in succeeds only when that subject is already
   linked.
4. Successful web federated sign-in starts the same Sanctum
   `AuthenticationSessionService` session as password sign-in.
   Successful Android federated sign-in issues the existing Passport access +
   rotating refresh family (`trackIssuedTokens` grant name `federated_sign_in`).
   Refresh remains `grant_type=refresh_token`. `/oauth/token` still rejects
   `grant_type=password`.
5. Android Google/Facebook authorization uses the origin-pinned system browser
   (Custom Tabs) on first-party `/oauth/social/{provider}/authorize`, because
   Google and Facebook forbid OAuth inside the WebView. The password form stays
   in-app. Tokens are not placed in the redirect URL; Android receives a
   single-use ticket and exchanges it at
   `POST /api/v1/auth/mobile/social/sign-ins`.
6. Provider SDK types stay behind a project-owned `FederatedIdentityProvider`
   adapter. Client IDs and secrets are environment configuration, never committed.
   Missing configuration fails closed and hides the buttons.
7. Responses stay enumeration-safe and throttled like password sign-in. Provider
   subjects are stored only as keyed digests. Email from the IdP is not used to
   auto-link or create accounts in this checkpoint.
8. No credentials in `localStorage`, IndexedDB or ordinary Capacitor Preferences.
   Android refresh + installation ULID remain in EncryptedSharedPreferences.

## 5. Options explicitly not selected

- Creating accounts from Google or Facebook.
- Auto-linking on IdP email matching a recovery contact.
- Enabling OAuth `grant_type=password`.
- Collecting Google/Facebook passwords in the WebView.
- Passkeys, SAML, Apple, or other IdPs in this checkpoint.
- Production or Google Play authorization.
