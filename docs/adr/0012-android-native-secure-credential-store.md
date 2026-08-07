---
status: accepted
date: 2026-08-07
deciders:
  - S R Bhandary (Accountable Product Owner) via APP-0030 implementation constraint D-0110
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: false
approval_evidence:
  - APP-0030
approval_reference: APP-0030 PR-014 closed-beta foundations
---

# ADR-0012: Android native secure credential store

## Status

Accepted for PR-014 closed-beta foundations under
[APP-0030](../APPROVALS.md#app-0030) (D-0110). Implements the ADR-0002
requirement for reviewed native secure storage on Android.

## Context

ADR-0002 requires Android Passport PKCE credentials to persist only in a
reviewed native secure-storage implementation: installation ULID and refresh
token. The access token must remain memory-only. Ordinary Capacitor
Preferences, WebView storage, `localStorage`, IndexedDB and Cache Storage are
forbidden for credentials.

PR-014 must select and document that store without converting the demo APK
identity (`com.globalenglish.demo`).

## Decision

Use a **project-owned Capacitor plugin**
(`GlobalEnglishSecureCredentialsPlugin`) in the production Android tree
(`apps/web/android-app`, package `com.globalenglish.app`) backed by
AndroidX **`EncryptedSharedPreferences`** with a Keystore-backed master key
(`MasterKey.Builder` / AES-256-GCM).

Stored keys (only):

- installation ULID
- refresh token
- transient PKCE pending authorization (`state` + `code_verifier`)

Not stored:

- access tokens (adapter memory only)
- OTP codes, passwords, or provider secrets

System-browser authorization uses a companion project-owned plugin that opens
Chrome Custom Tabs (or the platform browser intent). Deep-link return uses
`com.globalenglish.app:/oauth/callback` and a project-owned bridge event into
the shared `AndroidPkceAuthenticationAdapter`.

Ordinary `@capacitor/preferences` must not be used for any of the above
credential material.

## Alternatives considered

### `@aparajita/capacitor-secure-storage` or similar community plugins

Rejected for this APP slice in favour of a project-owned plugin with an
explicit EncryptedSharedPreferences implementation under repository review.
Community plugins remain an option if a later security review prefers a pinned
third-party artifact with the same storage class.

### Ordinary Capacitor Preferences / WebView storage

Rejected by ADR-0002 and AGENTS.md.

### Hardware-backed biometric vault only

Deferred; closed beta does not require biometric gating of refresh material.

## Consequences

### Positive

- Credential material never enters WebView-accessible storage APIs.
- Storage choice is explicit, reviewed and testable via the TypeScript
  `NativeSecureCredentialStore` port.
- Demo APK remains free of auth storage (D-0078).

### Negative / limitations

- Physical-device proof that WebView storage and Preferences remain empty after
  login is part of the written device matrix (not fully automatable here).
- Play App Signing / public release remain unauthorized.

## Evidence

- [PR-014_ANDROID_VERIFICATION_SCOPE.md](../PR-014_ANDROID_VERIFICATION_SCOPE.md)
  AN-04 / AN-05 / AN-06
- [PR-014_ANDROID_CLOSED_BETA_BUILD.md](../PR-014_ANDROID_CLOSED_BETA_BUILD.md)
- Unit tests for the secure-store adapter and default authentication wiring
