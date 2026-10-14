# LEARN-FLOW-07 review — 26 September 2026

Status: implemented and deployed to the invite-only pilot; final phone archive
checks are recorded below. Independent human review remains pending.

## Behaviour and files

- `RecordingTurn` and `VoicePracticePanel` remember the generic instruction as a
  non-sensitive boolean, reuse permission grants and show Send/Retry for uncertain
  transcripts. Retry deletes the pending recording. The microphone permission
  itself is still checked. Typing is an explicit alternative, hidden during review.
- `ConversationProcessingConsent`, `ConversationPrivacyPanel`, `App` and
  `InteractiveDialogue` move standing consent controls and advisory/privacy copy
  to Account > Privacy. Detailed end/delete controls live in conversation options.
  Removed engineering draft text does not change actual review status.
- `ConversationTurnService`, `LocalDialogueGreeting` and the orchestrator avoid
  model dispatch for exact tutor greetings and skip TTS for rejected replies.
  Rejection diagnostics contain only flow, policy and a bounded reason category.
  Recovery plays a committed valid reply without another model call; rejected
  output instead offers an explicit new turn. No hidden model retry is introduced.
- `deviceConversationHistory`, `DeviceConversationHistoryPanel`, shared voice/
  dialogue integration and the Android Keystore plugin/store implement optional
  encrypted text/audio history. Save successful turns and roleplay openings, then
  list/read/replay/delete in Privacy. Available audio only: no deferred TTS call
  is made merely to fill the archive. Free saved replay bypasses live-AI metering.
- Local archive limits: 4 MiB per turn, 256 MiB/2,000 files per account partition.
  Limits stop new saves with visible feedback; old history is not evicted. Keys
  and authenticated associated data separate exact user/organization/membership
  and record IDs. Files are app-private/no-backup. Account changes lock access;
  deleting all history destroys the account archive key and disables saving.
- Six catalogue drafts, public privacy/deletion pages, download metadata and
  Android version updated. Master v3.21 and ADR-0044 record the owner's explicit
  text/audio-history and free-replay choices. No server migration, new secret,
  provider route, assessment authority or cloud retention change.

## Verification

- Focused pre-history voice/controller tests: 43 passed.
- Focused archive/controller/recording checks: 56 passed before final additions.
- PostgreSQL dialogue integration: 22 tests/187 assertions passed, including
  no TTS after rejection and no model tokens for an exact greeting.
- Initial full verify reached ESLint after the PHP/contracts/security checks;
  ESLint caught one effect-cleanup warning; fixed with a disposed flag. Subsequent lint/typecheck/workspace tests
  passed (551 web tests before the final privacy and deferred-audio additions).
- Browser E2E: 45 journeys and four API tests/103 assertions passed.
- AI evaluation: 15 locked cases and 35 tests/282 assertions passed; physical
  speech benchmark not run by this command.
- Native archive tests cover encryption, tamper/path rejection, account/record
  binding, deletion, opt-out, replay idempotency and storage bounds: six passed;
  all 13 native JVM tests passed.
- Final `make verify` after the live STT repair passed: SQLite 362 tests/3,358
  assertions; PostgreSQL 440/5,963; 553 web tests, static analysis, security,
  contract generation/checks, lint and typecheck. Focused Indic adapter: 7/27.
- `make ai-eval` rerun after STT repair: 15 locked cases and 35/282 passed.
- `make build` and Android closed-beta build passed; APK v2/v3 signatures
  verified. Existing large-chunk/unlicensed-avatar exclusions remain unchanged.

## Live diagnostic evidence

Only content-free status fields were inspected from the user's current phone
session. Two recent tutor turns were completed with `safety=fallback`, rather
than a sole playback failure. The rejected provider drafts had already been
replaced, so their precise rejection cause cannot be reconstructed.

Seven new synthetic tutor questions all passed live (11–18 words), followed by
an exact “Good Morning swathi” retest (10 words, passed). This does not reproduce
or explain the prior intermittent rejection. A subsequent synthetic phone voice
turn did reproduce a rejection categorized `reply_length`; a separately scoped
retest of that synthetic question passed. Invalid output now has no TTS ledger
entry and the phone offers a new submission, without Listen again/Recover. The
exact greeting now has a tested local response independent of model variability. Provider quotas remain tiny;
these paced checks do not establish 1,000 simultaneous speaker capacity.

## Live speech repair and release

The installed phone first transcribed synthetic English “Good morning! What would
you like to practise?” into Hindi script in Ask AI Tutor. Its original-speech
adapter still used `verbatim` with automatic language detection, unlike roleplay.
All original-speech paths now request `codemix`: English words in Latin script and
Indic words in their own script, retaining automatic language detection for tutor
questions and the existing English roleplay hint. Translation is not introduced
as learner evidence. [Sarvam's mode contract](https://docs.sarvam.ai/api-reference/speech-to-text/transcribe)
supports this choice. On the same phone and synthetic audio, the retest returned
“Good Morning. What would you like to practice?” in English. Send/Retry were the
only transcript-review controls; the type field was absent. Retry deleted the
previous candidate, and the generic 25-second instruction did not recur.

Active release: `learn-flow-20260926-r2`, cloned from the verified r1 candidate
with the scoped Indic adapter repair. Public readiness and private recovery tmpfs
checks passed after atomic cutover; transient restart connection resets cleared
within the scripted retries. The previous releases remain available for rollback.
No migration, new environment variable or API response change was required.

Android **1.0.5/code 6**, 15,417,383 bytes, installed with `adb install -r` and
existing data preserved. Downloaded, installed and built APK SHA-256 all match:
`81fb00b0cb4cab9ce9d1239f4f1bf86fdaa5f4973a687fd202a80418f13963e4`.
A fresh anonymous browser visit verified the homepage download anchor links to
`?v=learn-flow-20260926.1`.
Web fingerprint: `cdbca85a0a2b118a2748db5785aa411cbfe9752d1864fe106dc35e7e3bfb6aab`.
Web index SHA-256: `b6c3c5103e173bab95535cb7a45b3d6804e98d6742bf251d456c38bd72d0014f`.
Indic adapter SHA-256: `0895f6386790e2a8d144fed2d20b0e5ecc4103eec86a26b5947709fafca45418`.

Phone checks used synthetic TTS audio fed through the app recorder and live Sarvam
STT; this is not a human microphone/accent benchmark. The exact provided greeting
also passed as typed input and recorded zero model tokens in the scoped ledger,
with ordinary Azure TTS usage still recorded. A new synthetic recorded “Good
Morning” also completed successfully; its 4.812-second reply began and completed
automatically. The rejected long answer had a model ledger entry and no TTS entry.

The physical Android archive passed initial opt-out, explicit opt-in, two saved
turns, persistence across WebView reload, text/model audio plus learner WAV,
stopping saves while retaining prior history, account-partition denial and locked
access after deactivation. Replay ran with browser networking offline and both
fetch/native HTTP blocked: learner audio 3.413 seconds and reply audio 4.812 seconds
played to completion, with **zero network attempts**. Stored no-backup files used
the encrypted format and contained none of the known synthetic text/audio markers.
Native unit tests additionally cover authentication/tampering and key erasure.
A real-device Privacy screenshot was inspected: history text/buttons are legible
in Kannada dark mode. DOM full-section screenshots were not reliable on this
WebView, so those were not accepted as visual evidence. Individual deletion
removed exactly one of two saved turns. Delete all then removed every archive
file, disabled saving, and showed no error. QA history is cleared, the synthetic
server session is closed, and the phone is restored to Kannada/dark Learn.
History remains off until the learner chooses Account > Privacy.

## Independent review and limitations

[The handoff packet](LEARN_FLOW_07_HUMAN_REVIEW.md) lists exact reviewer steps.
No independent human acceptance is fabricated. The owner must supply actual
qualified reviewers/decisions to close that gate; Codex has completed the
preparation it can perform.

History starts after explicit opt-in and saves on this Android installation,
not the web/PWA. Expired prior conversations cannot be recovered. App removal,
cleared app data or lost device keys remove history; no backup/export is provided.
Archive access requires the matching signed-in app identity. Saved playback makes
no network/provider request and can work without connectivity while that identity
is available; this is not a new offline-authentication mechanism. Server dialogue
context still expires at 15 minutes, and saved history is not automatically sent
back to the model. Remote account erasure cannot delete an offline phone's bytes;
the deletion page directs the learner to clear the local archive/app storage.

Native encrypted files do not protect an already compromised/unlocked application
process; no banking-grade security certification is claimed. Independent language,
privacy/security acceptance, provider invoice reconciliation, session-limit UX and
funded representative concurrent voice-load testing remain open.
