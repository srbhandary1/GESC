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
  - APP-0070
approval_reference: Exact human instruction in the active Cursor conversation accepting DR-0043 Option B
---

# ADR-0033: In-app Android username/password sign-in

## Status

Accepted on 1 September 2026. Recorded as D-0263 under
[APP-0070](../APPROVALS.md#app-0070). This ADR resolves
[DR-0043](../DR-0043_ANDROID_IN_APP_PASSWORD_SIGN_IN_REQUEST.md) by accepting
Option B. It amends [ADR-0002](0002-authentication-session-token-and-recovery-policy.md)
and [ADR-0021](0021-username-password-only-authentication.md) for Android **first
issue** only. Token lifetimes, rotation, reuse-revoke, installation binding and
reviewed native storage are unchanged. This does not authorize production or
Google Play.

## Context

ADR-0002 required the system browser and forbade an embedded Android
credential form. ADR-0021 and master plan §11.2 repeated that Passport
Authorization Code with S256 PKCE collects the password on the
server-controlled authorize page.

Testers cannot complete that journey without leaving the app. The product owner
required the existing username/password form to stay inside the app.

## Decision

1. Android first issue is a first-party password sign-in:
   `POST /api/v1/auth/mobile/password/sign-ins`. The server uses
   `PasswordCredentialService`, then issues Passport access and refresh tokens
   for the approved public Android client and `mobile:api`.
2. `MobileTokenFamilyService::trackIssuedTokens` creates a family for grant
   `password_sign_in` as well as `authorization_code`. Refresh remains
   `grant_type=refresh_token` on `POST /oauth/token`.
3. `POST /oauth/token` continues to reject `grant_type=password`.
4. Web/PWA remains Sanctum cookies + CSRF at
   `POST /api/v1/auth/password/sign-ins` with `RequireStatefulBrowserRequest`.
5. Guest Android UX mounts the in-app password form on `#sign-in` and does
   not call `beginAuthorization()`. PKCE authorize/token routes and the
   Custom Tabs plugin remain unused for guest sign-in.
6. The password is typed in the Capacitor WebView and sent over CapacitorHttp.
   It is never stored in `localStorage`, IndexedDB, ordinary Capacitor
   Preferences or Keystore. Keystore still holds only the installation ULID,
   rotating refresh token and unused PKCE pending state. The access token stays
   memory-only.
7. A native Kotlin password Activity is out of scope.

## Alternatives considered

### Alternative A — Keep system-browser PKCE

Rejected. The product owner required the form to remain in the app.

### Alternative C — Kotlin Activity

Keeps the password out of JavaScript. Out of scope unless separately requested.

## Consequences

### Positive

- Testers sign in without leaving the app.
- Web and Android share one password policy and one Passport family after
  first issue.

### Negative and trade-offs

- The password is briefly present in WebView JavaScript, which ADR-0002
  previously forbade. Independent security review remains mandatory before
  production.

## Security, privacy and tenancy impact

- No credentials in web storage or ordinary Capacitor Preferences.
- Enumeration-safe generic failures and the existing password throttles apply.
- Organization authorization remains post-authentication under ADR-0003.
- Independent security, privacy and tenancy review remains mandatory before
  production under ADR-0001.

## Authoritative records and consistency impact

Token issuance and family tracking are one transaction. Refresh rotation,
reuse-revoke and installation binding stay as ADR-0002. No schema change.

## API, client and operational impact

OpenAPI adds the first-party mobile password, mode, recovery and invitation
routes. Generated contracts update. Old APKs that still open Chrome remain
valid against PKCE until testers install a build that contains this checkpoint.

## Master-plan amendment

This ADR amends master-plan version 3.9 in the same accepted change and
advances it to version 3.10. It changes:

- §1.1 and §32 authentication defaults;
- §11 Android authentication row;
- §11.2 Capacitor Android transport;
- PR-003 Android first-issue wording.

Web Sanctum, token lifetimes, native secure storage and tenant authorization
are unchanged.

## Approval evidence

[APP-0070](../APPROVALS.md#app-0070) records the product-owner request for an
in-app form, the Option B selection and authorization to implement this
checkpoint. Independent security review remains mandatory before production.

## Follow-up

- Physical device sign-in on a newly published APK.
- Optional later Kotlin Activity if the password must leave JavaScript.
