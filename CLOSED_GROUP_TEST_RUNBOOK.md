# Closed-group Web and Android test runbook

**Checkpoint:** CLOSED-GROUP TEST PACK — Ubuntu host  
**Authentication:** `password_only`; username and password only  
**Android package:** `com.globalenglish.app`  
**Release classification:** controlled closed-group test; not production and not Google Play

## 1. Current tester entry

Web URL:

```text
http://117.254.108.143:8080
```

The Ubuntu user service owns the API, queue worker, scheduler and the
port-8080 public HTTP listener. Check it from the repository root:

```text
make soft-launch-status
make soft-launch-ready
systemctl --user status global-english-soft-launch.service --no-pager
```

The expected authentication mode is `password_only`. Do not enable legacy OTP,
TOTP, Google sign-in or Facebook sign-in for this test.

### Static-IP HTTP limitation

This origin is plain HTTP on the WAN static IP. Sign-in cookies are issued
without the Secure flag so the browser session can work. Browser microphone
capture is not a secure context on this URL, so Speak/hold-to-talk will not
work from phones or remote browsers until a stable HTTPS hostname is enabled.
An APK must be rebuilt against this origin before testers use Android; do not
keep a previous `trycloudflare.com` or Tailscale pin.

## 2. APK artifact and controlled distribution

Persistent host path:

```text
/home/srbhandary/ge-soft-launch/artifacts/closed-group/global-spoken-english-closed-group-20260812-r4.apk
```

Build-tree copy:

```text
apps/web/android-app/app/build/outputs/apk/debug/app-debug.apk
```

The artifact is a production-path package built from ordinary `dist`, not
`dist-demo`. It uses the Android debug signing key for staff/closed-group
sideloading. It is not a Play-signed release.

Verified artifact record:

```text
Size: 8,461,998 bytes
SHA-256: d3e55fe4790cb4e7fd7cebf428fb6818f3c419df849f877039e550669366f448
Embedded HTTPS origin: https://award-bite-monica-seafood.trycloudflare.com
Package: com.globalenglish.app
Version: 1.0 (1)
Signature: Android debug certificate; APK Signature Scheme v2
```

Distribute the file only through an operator-controlled private channel or by
direct device transfer. No unauthenticated public download route is enabled.
Before installation, compare the SHA-256 value with the value in the checkpoint
completion record and do not install a mismatch.

## 3. Sideload on Android

1. Copy the APK to the tester's Android device through the controlled channel.
2. Open Android **Settings → Security/Privacy → Install unknown apps** and grant
   temporary permission only to the file manager or browser used to open it.
3. Open the APK and confirm the package/app being installed is Global spoken
   English Course. Do not install `com.globalenglish.demo`.
4. If Android reports an incompatible signature over an older local build,
   uninstall that closed-test build first. This removes its local app data.
5. Disable **Install unknown apps** again after installation.
6. Open the app. Use the top-right **Sign in** action. It must open the system
   browser. Enter only the tester's username and password there. The reviewed
   first-party public client approves the exact `mobile:api` request
   automatically and returns through `com.globalenglish.app:/oauth/callback`;
   there is no separate **Continue** consent step.
7. Never paste a tester password, callback code, access token or refresh token
   into chat, screenshots, logs or a test report.

An operator with Android platform tools may instead use:

```text
adb install -r /home/srbhandary/ge-soft-launch/artifacts/closed-group/global-spoken-english-closed-group-20260812-r4.apk
```

## 4. Create tester username/password accounts

Existing credential-ready learner accounts may start Web and Android testing
immediately. Do not share one account among testers.

To create a new invited learner after an authorized organization administrator
is credential-ready:

1. The administrator signs in to the Web URL and selects **Global English
   Direct**.
2. Open **Username and password security**, enter the administrator's current
   password, and confirm it. The confirmation is valid for five minutes and is
   the same single factor—not MFA.
3. Open **Learners → Learner invitations**.
4. Select the exact learner role containing `learning.participate`, optionally
   select a cohort, select a 24-, 72- or 168-hour expiry, and issue the
   invitation.
5. Transfer the one-time invitation code to exactly one intended tester through
   a private channel. The application shows the raw code only once; do not put
   it in chat, logs or screenshots.
6. The tester opens the Web URL, selects **Create account with invitation**,
   enters the invitation, chooses a globally unique username, and chooses their
   own 15–128-character password. The operator must not choose or receive that
   password.
7. After the success message, the tester signs in normally. The same username
   and password then work through the Android system-browser flow.

Setup is atomic: an invalid, expired, already-used or mismatched invitation
does not leave a user, credential, membership or enrollment behind. The invited
account is not automatically signed in.

New invitation-only accounts currently have no independently verified recovery
contact. Treat lost-password recovery for those test accounts as unavailable;
do not reset credentials directly in the database.

### Excluded administrator impact

The APP-0057 excluded-operator recovery case remains `waiting_delay` until
12 August 2026 at 10:44:26 UTC / 16:14:26 IST. Do not complete it early and do
not restore OTP/TOTP. This affects that excluded organization-administrator
account and therefore the ability to issue new invitations through that
account. It does not prevent the two existing credential-ready learner
accounts from testing. After the delay, follow the existing governed recovery
runbook; the target chooses their own username and password.

## 5. Web and Android smoke checklist

Record Web and Android separately for each tester/device. Record only safe
result categories and correlation IDs—never lesson answers, transcript text,
audio, credentials or tokens.

### Sign-in and shell

- [ ] Web sign-in accepts username/password and shows the selected membership.
- [ ] Android sign-in uses the system browser and returns to the production app.
- [ ] No OTP, authenticator, Google or Facebook control appears.
- [ ] Sign out ends the current session; another tester cannot inherit it.
- [ ] English fallback notice is accepted as expected while non-English UI
      catalogues remain pending independent review.

### Learn

- [ ] Open **Learn/Home** and confirm the current published learning home loads.
- [ ] Open an available published mission and play its governed media.
- [ ] Complete one deterministic activity and confirm retry/feedback behavior.
- [ ] If no reviewed Level 1 lesson is published, record **blocked: content not
      yet published**; do not treat the accessible shell as a lesson pass.

### Practice

- [ ] Open an available Practice mode (Confidence Chat, Skill Focus, Review or
      Role-play).
- [ ] Complete a typed turn and confirm advisory feedback does not change marks,
      mastery or unlocks.
- [ ] Test microphone allow and deny paths where voice is available.
- [ ] If live AI remains disabled by the host kill switch, record the safe
      unavailable/fallback result rather than claiming live-provider success.

### Progress

- [ ] Open **Progress** and confirm the page loads for the selected membership.
- [ ] Confirm completed deterministic evidence appears at most once after
      refresh/reconnect.
- [ ] Confirm practice-only AI feedback did not create marks or mastery.
- [ ] Exercise offline/reconnect only for surfaces currently marked available;
      record unsupported content as a known limit.

### Minimum device record

Record device model, Android version, connection type, app version `1.0 (1)`,
Web/Android result, safe failure category and correlation ID. Physical-device
DM-01/DM-02 evidence remains a required output of testing, not a pre-existing
pass.

## 6. Known limits and stop conditions

- No Google or Facebook sign-in; username/password is the only login policy.
- No OTP/TOTP or authenticator app may be restored.
- No Google Play listing or Play-distributed build is authorized.
- The rotating Quick Tunnel remains temporary until the DNS-only
  `ai.globalspokenenglish.com` A record, WAN 80/443 forwarding and public
  certificate are proven. It is not production infrastructure.
- The current Quick-Tunnel APK becomes obsolete when the stable origin is
  activated and must be rebuilt against that exact HTTPS hostname.
- The APK is debug-signed for sideload testing, not release-signed.
- LPL-01 human transcript reconciliation, English/Kannada review and a
  publishable Level 1 blueprint are incomplete; full Learn coverage is not yet
  available.
- Live Azure tutoring is currently disabled by the deliberate host kill switch.
- Independent security, privacy, tenancy, accessibility and language reviews,
  physical-device evidence and public-production authorization remain open.

Stop testing and notify the operator if the HTTPS certificate/origin changes,
the app asks for OTP/TOTP/social login, a tester sees another tenant's data, a
credential/token appears in a report, or authoritative progress duplicates.
