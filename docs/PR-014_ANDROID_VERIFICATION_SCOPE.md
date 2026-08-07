# PR-014 Capacitor Android verification scope

**Status:** Bound to
[PR-014_ANDROID_AUTHORIZATION_REQUEST.md](PR-014_ANDROID_AUTHORIZATION_REQUEST.md).
Begin-implementation authorized under [APP-0030](APPROVALS.md#app-0030)
(plan step D1).  
**Prior slice:** PR-013 bounded operator accepted under
[APP-0029](APPROVALS.md#app-0029).  
**Build / matrix:** [PR-014_ANDROID_CLOSED_BETA_BUILD.md](PR-014_ANDROID_CLOSED_BETA_BUILD.md),
[PR-014_ANDROID_DEVICE_MATRIX.md](PR-014_ANDROID_DEVICE_MATRIX.md),
[ADR-0012](adr/0012-android-native-secure-credential-store.md).

## Authority and boundary

PR-014 may be authorized only by a distinct APP after PR-013 bounded operator
acceptance. This verification scope covers **closed-beta foundations** for the
production-path Capacitor Android app. Google Play public release and PR-015
hardening require later APPs.

Master-plan acceptance criteria this first APP must satisfy locally:

- critical learner journeys pass on the agreed device matrix;
- permission denial and interruption recover cleanly;
- no Android credential is stored in WebView storage or ordinary Capacitor
  Preferences, and logout revokes the active token family;
- signed closed-beta bundle is reproducible.

## Vertical slice (when authorized)

1. Production package identity is distinct from `com.globalenglish.demo`.
2. App loads the ordinary product web build (not `dist-demo`).
3. Learner completes system-browser Passport PKCE login and deep-link return.
4. Installation ULID + refresh live only in reviewed native secure storage;
   access token is memory-only.
5. Logout revokes the token family and clears secure credentials.
6. Negative checks: no auth credentials in WebView/`localStorage`/IndexedDB/
   Cache Storage/ordinary Capacitor Preferences (automated where feasible).
7. Critical online learner journeys (home/onboarding/mission or Confidence /
   Role-play as available) run on the documented device matrix.
8. Mic permission denial and interruption recover without credential leakage.
9. Signed closed-beta AAB/APK is reproducible from documented steps.

## Reuse

- Passport PKCE server + OpenAPI contracts — PR-003 / ADR-0002
- `AndroidPkceAuthenticationAdapter` + `NativeSecureCredentialStore` interface
- Ordinary product web UI already accepted through PR-013
- Demo Capacitor project as **reference only** — do not promote demo identity

## Explicit non-evidence

- `make demo-android` / `com.globalenglish.demo` APK (D-0078)
- Vite `/demo/*` middleware (D-0092 / D-0094)
- Emulator anecdotes without the written device matrix

## Verification (when authorized)

| ID | Check |
|---|---|
| AN-01 | Production package ID ≠ `com.globalenglish.demo` |
| AN-02 | Ordinary product build packaged (not `dist-demo`) |
| AN-03 | System-browser PKCE + deep-link return succeeds |
| AN-04 | Credentials only in reviewed native secure store (+ memory access token) |
| AN-05 | Logout revokes token family and clears secure store |
| AN-06 | No credentials in WebView / web storage / ordinary Preferences |
| AN-07 | Device matrix documented; critical journeys pass on matrix |
| AN-08 | Permission denial / interruption recover cleanly |
| AN-09 | Signed closed-beta artifact reproducible |
| AN-10 | `make verify` / targeted Android build evidence recorded (Docker/pinned OK) |

## Explicit non-goals

- Google Play public release
- PR-015 hardening
- Remaining PR-013 sampled review / privacy ops
- Production OTP cutover / real learner production data

## Exit gate

PR-014 closed-beta foundations are ready for APO exact-commit acceptance only
when AN-01–AN-10 pass for the authorized scope and recorded limitations preserve
that Play public release, PR-015 and remaining PR-013 items remain separately
gated.
