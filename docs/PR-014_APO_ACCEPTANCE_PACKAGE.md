# PR-014 APO acceptance package

This package prepared exact-artifact Accountable Product Owner acceptance of
master-plan §26 **PR-014 — Capacitor Android and closed beta** for the
**closed-beta foundations** slice authorized under
[APP-0030](APPROVALS.md#app-0030). **Accepted** under
[APP-0031](APPROVALS.md#app-0031) / D-0111 at
`44b0e672e004cf3846cddabc456c21153fa4607e` with the recorded limitations
below.

## Artifact accepted

| Field | Value |
|---|---|
| Checkpoint | PR-014 Capacitor Android closed-beta foundations (plan D0–D1) |
| Branch | `codex/confidence-conversation` |
| Governing begin-implementation | [APP-0030](APPROVALS.md#app-0030) |
| Acceptance record | [APP-0031](APPROVALS.md#app-0031) |
| Authorization request | [PR-014_ANDROID_AUTHORIZATION_REQUEST.md](PR-014_ANDROID_AUTHORIZATION_REQUEST.md) |
| Verification scope | [PR-014_ANDROID_VERIFICATION_SCOPE.md](PR-014_ANDROID_VERIFICATION_SCOPE.md) |
| Quality evidence | [QUALITY_REVIEW.md](QUALITY_REVIEW.md) (PR-014 D0/D1 sections) |
| Secure-store ADR | [ADR-0012](adr/0012-android-native-secure-credential-store.md) / D-0110 |
| Exact commit | `44b0e672e004cf3846cddabc456c21153fa4607e` |

## Delivered behaviour

1. **D0** — APP-0030 begin-implementation for closed-beta foundations only.
2. Separate production Capacitor tree `apps/web/android-app`, package
   `com.globalenglish.app` (demo `apps/web/android` /
   `com.globalenglish.demo` unchanged; D-0078 preserved).
3. Dual Capacitor config via `CAPACITOR_APP=production` packaging ordinary
   product `dist` (not `dist-demo`).
4. System-browser Custom Tabs + deep-link
   `com.globalenglish.app:/oauth/callback` wired to
   `AndroidPkceAuthenticationAdapter` when `VITE_MOBILE_API_ORIGIN` is set on
   native Android.
5. Reviewed native secure store: project-owned EncryptedSharedPreferences
   Capacitor plugin (installation ULID + refresh + transient PKCE pending
   only; access token memory-only) — ADR-0012 / D-0110.
6. Written [device matrix](PR-014_ANDROID_DEVICE_MATRIX.md) and
   [closed-beta build](PR-014_ANDROID_CLOSED_BETA_BUILD.md) docs; Makefile
   target `make android-closed-beta`.
7. Focused Vitest coverage for secure store, PKCE environment, deep-link bind,
   defaultAuthentication Android wiring and Capacitor production config.

## Verification evidence (recorded this slice)

| Area | Result |
|---|---|
| Vitest (auth closed-beta foundations) | **7 files / 15 tests passed** (see QUALITY_REVIEW D1) |
| AN-01–AN-05 (static / unit) | Pass — QUALITY_REVIEW D1 |
| AN-06 on-device WebView/Preferences absence | **Partial** — unit proves secure-plugin-only writes; physical matrix pending |
| AN-07–AN-08 device matrix / lifecycle | Pass (doc); **physical device runs not claimed** |
| AN-09 reproducible signed artifact | Pass (doc); debug-keystore limitation recorded |
| AN-10 `make verify` / Gradle APK assemble | **Not claimed** |
| Host `make verify` / Chromium e2e / remote CI | **Not claimed** |
| Google Play upload / public listing | **Not authorized / not claimed** |

Demo Vite `/demo/*` and demo APK remain non-evidence.

## Recorded limitations (must accompany acceptance)

1. This APP accepts only **closed-beta foundations** — not Google Play public
   listing, open production release, or full Play compliance/store-listing
   pack.
2. Physical device-matrix execution (AN-06 on-device, AN-07–AN-08 journey
   runs) was **not** completed in the authorizing session and remains a
   follow-up evidence obligation before treating closed-beta install as
   verified on hardware.
3. Gradle APK/AAB assemble via `make android-closed-beta` and host
   `make verify` were **not** claimed green for this package.
4. Closed-beta signing documentation may use a debug/internal keystore path;
   production Play signing / release engineering remain separately gated.
5. Remaining PR-013 items (sampled speaking review under ADR-0007, privacy
   ops, full §8.2 console) remain unauthorized.
6. PR-015 production hardening, production OTP cutover and real learner
   production data remain unauthorized.
7. Live Azure OpenAI / multi-locale TTS spend remain under existing APP gates
   only; this acceptance does not expand them.
8. Demo APK (`com.globalenglish.demo`) remains presentation-only (D-0078) and
   must not be treated as the production identity.
9. This acceptance does **not** waive independent specialist reviews before
   production.

## APO statement (recorded)

```text
APO accept PR-014 Capacitor Android closed-beta foundations at 44b0e672e004cf3846cddabc456c21153fa4607e with its recorded limitations
```

Interpretation for the recorder (Codex is never an approver):

- Accept the exact commit only, with every recorded limitation above.
- Close plan Steps D0–D1 for this closed-beta-foundations APP scope.
- Do **not** treat this as Play public release, PR-015, remaining PR-013
  sampled review/privacy ops, or real learner production authorization.
- Next checkpoint consideration is a separate APP for on-device matrix
  completion / Play closed-testing track and/or PR-015 / remaining PR-013.

## Recorder checklist after APO statement

1. Exact commit SHA filled (`44b0e672e004cf3846cddabc456c21153fa4607e`).
2. Appended [APP-0031](APPROVALS.md#app-0031) to [APPROVALS.md](APPROVALS.md).
3. Appended D-0111 to [DECISIONS.md](DECISIONS.md).
4. Updated [STATUS.md](STATUS.md) and
   [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md)
   (D1 closed-beta foundations accepted; Active step = E0 consideration).
5. Appended acceptance note to [QUALITY_REVIEW.md](QUALITY_REVIEW.md).
6. Marked this package Accepted under APP-0031.
7. Do **not** start Play public release, PR-015, or remaining PR-013 sampled
   review/privacy ops until a distinct APP authorizes that scope.
