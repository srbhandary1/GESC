# Closed-beta pilot runbook (Android + web)

**Authority:** Foundations under [APP-0031](APPROVALS.md#app-0031) /
[APP-0033](APPROVALS.md#app-0033); evidence under
[APP-0034](APPROVALS.md#app-0034).  
**Package:** `com.globalenglish.app` only. Demo APK is non-pilot.  
**Non-authorization:** Google Play **public** listing (separate APP).

## Preconditions

1. API origin reachable from pilot devices (HTTPS preferred).
2. Current ADR-0021 in-app password authentication: Android Passport credentials
   stay in reviewed native secure storage; web uses Sanctum cookies. Retain
   registered public-client configuration behind the project authentication
   abstraction; do not instruct learners to use the obsolete browser flow.
3. Debug or upload-signed APK built from Exact commit under test
   ([PR-014_ANDROID_CLOSED_BETA_BUILD.md](PR-014_ANDROID_CLOSED_BETA_BUILD.md)).
4. `VITE_MOBILE_API_ORIGIN` baked to that API origin at web build time.
5. Consented adult pilot learners only; no production marketing claims.
6. Device matrix DM-01 and DM-02 physical passes recorded in
   [PR-014_ANDROID_DEVICE_MATRIX.md](PR-014_ANDROID_DEVICE_MATRIX.md).

## Pilot student spine

1. Install sideload APK (or Play **closed testing** track if separately
   authorized — still not public).
2. Sign in with the in-app phone/username and password screen.
3. **Learn** — one online mission with mic grant/deny paths.
4. **Speak** — Friend/free speech, interactive Role-play and Ask Swathi on the
   tested candidate; voice and typed recovery are recorded separately. Live
   providers require the existing environment approval and spend caps. Use
   [the current conversation pilot packet](STUDENT_CONVERSATION_PILOT_2026-09-25.md)
   for consent, alternation, correction/retry and local-language help tasks.
5. **Progress** — confirm can-do surfaces load.
6. Logout → confirm secure store cleared of refresh/pending; installation ULID retained.

## Negative checks (required)

- No refresh/access tokens in WebView storage or ordinary Preferences
  (see matrix AN-06).
- AI output does not mutate marks/mastery/unlocks.

## Exit criteria for pilot start

| Check | Required |
|---|---|
| Exact commit APK assemble recorded | Yes |
| DM-01 + DM-02 journeys 1–8 | Yes |
| Staging/prod-like API password-only path | Yes |
| Privacy/support ops APP (or explicit deferral accepted by APO) | Yes |
| F0 DoD Exact accept | Recommended before broad pilot; not claimed here |
| Play public listing | No |

## Incident path

Use [ops/INCIDENT_SUPPORT_RUNBOOK.md](ops/INCIDENT_SUPPORT_RUNBOOK.md). Support
must not use raw DB access for learner PII until remaining PR-013 is APP-authorized.
