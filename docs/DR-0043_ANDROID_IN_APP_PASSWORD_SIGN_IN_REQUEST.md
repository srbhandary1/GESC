# DR-0043 — In-app Android username/password sign-in

**Status:** Resolved — Option B accepted under APP-0070 / D-0263 / ADR-0033
**Needed by:** Android closed-beta testers who must sign in without leaving the app
**Authority affected:** Master plan §§1.1, 11, 11.2, 32 and PR-003; ADR-0002; ADR-0021
**Opened:** 1 September 2026
**Resolved:** 1 September 2026

## 1. Decision needed

The Accountable Product Owner required the username/password form to stay
inside the Android app. The accepted contract at ADR-0002 / ADR-0021 / master
plan §11.2 instead collected the password only in Chrome Custom Tabs through
Passport Authorization Code with S256 PKCE.

That is a security-foundation change, not a styling change. No Android
credential form, first-party mobile password route or token-family grant name
may ship until this request, its amending ADR and the same-checkpoint master-
plan amendment are recorded.

## 2. Current authoritative behavior

- Web/PWA uses Sanctum same-origin cookie sessions and CSRF at
  `POST /api/v1/auth/password/sign-ins` behind `RequireStatefulBrowserRequest`.
- Android guest sign-in opens the system browser to `/oauth/authorize`. The
  Capacitor WebView never receives the password. Tokens are the Passport family
  from `POST /oauth/token` (`authorization_code` then `refresh_token`).
- `POST /oauth/token` rejects `grant_type=password`. The public Android client
  remains a public client with no secret.
- Access tokens stay in process memory. The installation ULID, rotating
  refresh token and unused PKCE pending state stay in Keystore-backed
  EncryptedSharedPreferences. Nothing authenticating is stored in
  `localStorage`, IndexedDB or ordinary Capacitor Preferences.

## 3. Decision profiles

| Option | Guest UX | Password location | Consequence |
|---|---|---|---|
| A — Keep system-browser PKCE | Chrome Custom Tabs | Server-controlled authorize page only | Unchanged ADR-0002; testers leave the app to sign in |
| **B — In-app form, same Passport family (product-owner requested)** | React form inside the Capacitor WebView | Typed in the WebView and sent over CapacitorHttp; not persisted | Same look as the website; password is briefly in JavaScript |
| C — Native Kotlin Activity | Platform password field outside the WebView | Stays out of JavaScript | Stronger isolation; separate UI and out of this checkpoint |

Option B is not the RFC 8252 recommendation. It is accepted because the product
owner required the form to remain in the app. A later Kotlin Activity may move
the password out of JavaScript without changing the token family.

## 4. Exact Option B contract

If Option B is accepted as written, all of the following are part of the
decision.

1. Android guest sign-in uses the existing React username/password panel on
   `#sign-in`. Header and marketing Sign in navigate to that hash. They do not
   open Custom Tabs.
2. The Android adapter POSTs
   `POST /api/v1/auth/mobile/password/sign-ins` with username, password,
   `installation_id` and the public `client_id`. There is no session cookie and
   no CSRF token. The password is stripped from the request in `finally`.
3. The server verifies the password with the same `PasswordCredentialService`
   as web sign-in, then issues the existing Passport access + refresh family for
   `mobile:api`. Family creation uses a new grant name (`password_sign_in`), not
   `authorization_code` on `/oauth/token`.
4. Refresh, reuse-revoke, installation binding, lifetimes and logout remain
   ADR-0002. Refresh still uses `POST /oauth/token` `grant_type=refresh_token`.
5. `POST /oauth/token` continues to reject `grant_type=password`. PKCE
   authorize/token endpoints and the Custom Tabs plugin remain in the tree
   unused for guest sign-in.
6. Web/PWA Sanctum routes are unchanged.
7. Forgot password and invitation enrollment have first-party mobile
   equivalents so those journeys also stay in-app.
8. Responses stay enumeration-safe and throttled like web password sign-in.
9. A Kotlin Activity that keeps the password out of JavaScript is out of
   scope unless separately requested.

## 5. Options explicitly not selected

- Enabling OAuth resource-owner password credentials on `/oauth/token`.
- Storing the password in Keystore, `localStorage`, IndexedDB or Capacitor
  Preferences.
- Silently falling back to Sanctum cookies inside the WebView.
- Deleting PKCE server endpoints or the Custom Tabs plugin in this checkpoint.
