# Android authenticated request lifecycle

**Checkpoint:** AND-AUTH-01
**Authority:** [ADR-0002](adr/0002-authentication-session-token-and-recovery-policy.md),
[ADR-0012](adr/0012-android-native-secure-credential-store.md),
[ADR-0021](adr/0021-username-password-only-authentication.md), D-0110, D-0122
and D-0164
**Package:** `com.globalenglish.app`

## Runtime boundary

| Runtime | Authentication | API transport | CSRF |
|---|---|---|---|
| Web/PWA | Sanctum cookie session | ordinary same-origin fetch | Sanctum cookie + `X-XSRF-TOKEN` |
| Production Android | Passport Authorization Code + S256 PKCE | project-owned authenticated fetch | none; cookies are omitted |
| Android with missing/unsafe mobile configuration | unavailable, fail closed | requests reject | no cookie fallback |

In `password_only` mode, the server-controlled system-browser authorization
page collects username and password. The Capacitor/WebView application never
receives either value. OTP fields and invitation codes are absent from routine
Android sign-in, while the OAuth authorize/token, S256 PKCE, state, redirect,
installation and rotating refresh-family contracts remain unchanged.

The Android transport is shared by learner, conversation, operator and SSE
clients. Private model audio and lesson video also pass through it before the UI
receives a blob URL. OAuth authorization and token exchange remain separate
unauthenticated protocol endpoints.

## Request and refresh rules

1. Resolve the request against the configured API base.
2. Reject any origin outside the configured API origin or path outside the
   `/api/v1` boundary before a bearer can be attached.
3. If memory has no access token but native secure storage has a refresh token,
   run one coalesced refresh. A terminal OAuth `400`/`401` clears the credential;
   a network or server failure preserves it for retry.
4. Replace any caller-supplied `Authorization` header with the memory-only
   bearer and force `credentials: omit`.
5. Send the request. On a 401, refresh at most once and replay an exact clone of
   the request body, method and non-authorization headers.
6. Concurrent 401 responses share refresh state. A second 401 clears native
   authentication and notifies the application shell once.

Refresh-token rotation is written successfully to encrypted native storage
before the replacement access token becomes visible in memory. A generation
guard prevents a late refresh from restoring credentials after logout.

## PKCE callback rules

- The only accepted redirect is
  `com.globalenglish.app:/oauth/callback`, with exactly one `state` and one
  `code` parameter.
- Redirect shape is checked before pending state is consumed. A wrong state is
  compared without an early exit and restored for the legitimate raced return.
- Native pending authorization is atomically read and removed by the
  project-owned secure-credential plugin; code exchanges are serialized.
- `MainActivity` retains the callback only in process memory while the WebView
  becomes ready, uses JSON-safe encoding and dispatches a volatile catch-up
  global plus event. The TypeScript bridge registers its listener before
  reading the catch-up value and ignores repeated delivery of the same state.

## Credential locations

| Value | Allowed persistent location | Web storage |
|---|---|---|
| Installation ULID | encrypted native preferences | prohibited |
| Rotating refresh token | encrypted native preferences | prohibited |
| Pending PKCE state + verifier | encrypted native preferences until atomic take | prohibited |
| Access token | JavaScript process memory only | prohibited |
| OAuth callback code | transient native/WebView process memory only | prohibited |
| Username and password | server-controlled system-browser request memory only | prohibited |

The native plugin accepts only the three persistent keys above, uses Android
Keystore-backed `EncryptedSharedPreferences`, commits credential mutations
before resolving calls, and retains only the installation ULID on logout.

## Private media boundary

Android cannot attach a bearer from a raw DOM `<audio src>` or `<video src>`.
The learner client therefore downloads private model audio and lesson video
through the authenticated transport, creates a blob URL for the media element,
and revokes it on source change or unmount. The Capacitor response workaround
strips a false `Content-Length: 0` only for JSON responses; binary media is not
decoded or rewritten.

Whole-body blob buffering is the current bounded implementation. HTTP range
delivery and large-video memory behavior remain VIDEO-01 and must be completed
before production-scale video acceptance.

## Verification and remaining evidence

Automated coverage includes the password-only system-browser form and retired
legacy OTP contract, cold restoration, refresh coalescing, exact body
replay, terminal/transient failures, late-refresh logout races, origin/path
rejection, callback duplication, atomic native take, fail-closed configuration,
authenticated audio/video object URLs and shell invalidation. PostgreSQL mobile
token-family/replay tests and Android Gradle unit/build tasks also pass.

Physical DM-01/DM-02 lifecycle and negative-storage inspection are not claimed:
`adb devices` reported no attached device on 8 August 2026. Complete and record
those checks in
[PR-014_ANDROID_DEVICE_MATRIX.md](PR-014_ANDROID_DEVICE_MATRIX.md) before F0 or
release acceptance.
