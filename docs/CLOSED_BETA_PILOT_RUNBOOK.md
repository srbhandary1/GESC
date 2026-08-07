# Closed-beta pilot runbook (Android + web)

**Authority:** Foundations under [APP-0031](APPROVALS.md#app-0031) /
[APP-0033](APPROVALS.md#app-0033); evidence under
[APP-0034](APPROVALS.md#app-0034).  
**Package:** `com.globalenglish.app` only. Demo APK is non-pilot.  
**Non-authorization:** Google Play **public** listing (separate APP).

## Preconditions

1. API origin reachable from pilot devices (HTTPS preferred).
2. Passport **public** Android client with redirect
   `com.globalenglish.app:/oauth/callback`.
3. Debug or upload-signed APK built from Exact commit under test
   ([PR-014_ANDROID_CLOSED_BETA_BUILD.md](PR-014_ANDROID_CLOSED_BETA_BUILD.md)).
4. `VITE_MOBILE_API_ORIGIN` baked to that API origin at web build time.
5. Consented adult pilot learners only; no production marketing claims.
6. Device matrix DM-01 and DM-02 physical passes recorded in
   [PR-014_ANDROID_DEVICE_MATRIX.md](PR-014_ANDROID_DEVICE_MATRIX.md).

## Pilot student spine

1. Install sideload APK (or Play **closed testing** track if separately
   authorized — still not public).
2. Sign in via system-browser PKCE.
3. **Learn** — one online mission with mic grant/deny paths.
4. **Practice** — Confidence Conversation (type turns; live providers only if
   staging APP-0022 caps apply to that environment).
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
| Staging/prod-like API OTP path | Yes |
| Privacy/support ops APP (or explicit deferral accepted by APO) | Yes |
| F0 DoD Exact accept | Recommended before broad pilot; not claimed here |
| Play public listing | No |

## Incident path

Use [ops/INCIDENT_SUPPORT_RUNBOOK.md](ops/INCIDENT_SUPPORT_RUNBOOK.md). Support
must not use raw DB access for learner PII until remaining PR-013 is APP-authorized.
