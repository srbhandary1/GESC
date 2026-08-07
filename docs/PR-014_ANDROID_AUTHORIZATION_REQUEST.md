# PR-014 Capacitor Android authorization request

**Status:** Begin-implementation authorized under [APP-0030](APPROVALS.md#app-0030).  
**Plan step:** [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) D0→D1  
**Master plan:** §26 PR-014 — Capacitor Android and closed beta; §11.2 auth  
**Verification:** [PR-014_ANDROID_VERIFICATION_SCOPE.md](PR-014_ANDROID_VERIFICATION_SCOPE.md)  
**Prior acceptance:** PR-013 bounded operator [APP-0029](APPROVALS.md#app-0029) at
`9220ab27baa6dc29d82caf8e9e97e94f7df7219d`

This document is **not** implementation authorization until the Accountable
Product Owner issues the exact begin-implementation statement below and a new
APP entry is recorded.

## Purpose

Authorize **PR-014 closed-beta foundations** for a production-path Capacitor
Android application — separate from the presentation demo APK
(`com.globalenglish.demo` / D-0078). Staff and pilot learners can install a
reproducible signed closed-beta build that authenticates via system-browser
Passport PKCE and stores credentials only in reviewed native secure storage.

## Preconditions (satisfied or in place)

1. PR-013 bounded operator content-workflow + audit accepted under APP-0029.
2. Server Passport Authorization Code + S256 PKCE, refresh rotation and
   installation binding already exist (PR-003 / ADR-0002).
3. TypeScript `AndroidPkceAuthenticationAdapter` +
   `NativeSecureCredentialStore` interface exist (in-memory test double only).
4. Demo Capacitor wrapper exists at `apps/web/android` for `dist-demo` only and
   must remain presentation-only (D-0078).

## Proposed authorized scope (first APP)

Authorize only:

1. Separate Android application identity for the production path
   (`com.globalenglish.app` or client-owned equivalent) — do **not** convert
   `com.globalenglish.demo` into the production app.
2. Capacitor 8 production project packaging the ordinary product web build
   (not `dist-demo`), API 36 target / minimum API 24.
3. System-browser Passport PKCE + deep-link return + shared authentication
   adapter wiring on Android.
4. Select, document and implement a **reviewed** native secure store for
   installation ULID + refresh credential only; access token remains
   in-memory (ADR-0002 / AGENTS.md).
5. Logout / security reset revokes the active token family; prove credentials
   are absent from WebView storage, `localStorage`, IndexedDB, Cache Storage
   and ordinary Capacitor Preferences.
6. Microphone / audio / connectivity / lifecycle handling for already-accepted
   learner journeys on the production package.
7. Written closed-beta **device matrix** + critical-journey verification on
   that matrix (debug/internal or closed testing track).
8. Reproducible signed closed-beta AAB/APK artifact and STATUS / DECISIONS /
   QUALITY_REVIEW / plan updates.

## Explicit non-goals (remain unauthorized)

- Google Play **public** listing / open production release
- Full Play compliance / store-listing pack beyond closed-beta needs
- PR-015 production hardening (load/soak, backup drills, staged deploy)
- Remaining PR-013 items (sampled speaking review under ADR-0007, privacy ops,
  full §8.2 console)
- Production OTP provider cutover / real learner production data
- Expanding live Azure OpenAI / multi-locale TTS spend beyond existing APPs
- Embedding secrets or provider credentials in the Android package or
  `dist-demo`
- Using the demo APK (`com.globalenglish.demo`) as the production identity

## Proposed APO begin-implementation statement

Reply exactly:

```text
APO authorize only PR-014 Capacitor Android closed-beta foundations under
PR-014_ANDROID_AUTHORIZATION_REQUEST.md and
PR-014_ANDROID_VERIFICATION_SCOPE.md.
```

Interpretation for the recorder (Codex is never an approver):

- Authorize **begin-implementation** of closed-beta production-path Android
  foundations only.
- Do **not** accept completed PR-014 work, Google Play public release, PR-015,
  remaining PR-013 sampled review/privacy ops, or real learner production data.
- Demo APK remains presentation-only (D-0078).
- Create a new APP entry, update STATUS/DECISIONS/QUALITY_REVIEW/plan Active
  step, then begin code on the authorized checkpoint branch.

## Recorder notes after APO statement

1. Append next APP to [APPROVALS.md](APPROVALS.md).
2. Append decision row to [DECISIONS.md](DECISIONS.md).
3. Update [STATUS.md](STATUS.md) and
   [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md)
   (D0 authorization done; Active = D1 implementation).
4. Append QUALITY_REVIEW note.
5. Only then implement the production-path Android closed-beta foundations.
