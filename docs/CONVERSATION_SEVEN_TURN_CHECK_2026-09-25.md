# PILOT-CONVERSATION-03 — seven-turn live conversation check

Authority: the owner's 25 September request to run role-play, free speech and
Ask AI Tutor for at least seven interactions and fix discovered defects.
Bounded scope: seven synthetic typed interactions per flow, including both
Learn's lesson tutor and Speak's Ask Swathi to cover the two tutor entry points.
Inspect turn relevance, correction/retry, context continuity, role ownership,
language permission, safe fallback and continuation. Do not submit learner quiz
answers or manufacture marks/completion. No microphone use is required for this
response-quality check. Existing credentials stay in the protected external
source; only synthetic inputs/replies may appear in the test evidence.

Implementation plan: run and review live baselines; repair demonstrated defects
within existing adapters and consent/recovery boundaries; add relevant regression
coverage; run required gates and publish a guarded release if code changes;
repeat seven-turn checks on repaired flows. Existing unrelated dirty-tree work
is preserved. No production/Play-signing or schema redesign is in scope.

Status: bounded repairs deployed as `conversation-seven-20260925-r7`; live response-quality rechecks complete. Capacity and real-voice acceptance remain open. Earlier release entries below are historical.

## Baseline evidence

Twenty-eight synthetic typed interactions were sent through the live mobile-sized
web UI: seven clinic Role-play, seven Everyday talk, seven Ask Swathi and seven
Learn Ask AI Tutor. This does not represent microphone/STT or human-language review.

- Role-play corrected `I has an appointment.`, accepted its corrected retry and
  continued as receptionist for three turns. Turns 4–7 failed at the model boundary.
- Everyday talk lost the tomato/balcony context on the third question, explicitly
  asking the learner to remind it. Its legacy path supplied no session history.
- Ask Swathi answered the first three questions; its permission offer worked,
  but local explanation and the following questions failed at the model boundary.
- Learn Ask AI Tutor opened a deterministic sentence drill. It ignored an actual
  question about `can` and continued demanding the next model sentence.
- No browser exceptions occurred. Failure is not counted as a sensible AI reply.

A content-free provider probe returned a 3,000-token/minute and 3-request/minute
Azure deployment limit. Fresh management access requires MFA (AADSTS50076).
The owner attempted device sign-in but received an access-denied message; both
management resource scopes still reject the CLI refresh. No quota was changed.

## Bounded repairs

Everyday talk now uses the existing explicitly acknowledged, encrypted, scoped
15-minute recovery mechanism and up to eight recent turns of same-session
context. It remains advisory/unassessed, with optional corrections separate from
the conversational reply. Learn Ask AI Tutor opens question help; Start Skill
Focus retains the governed drill. No lesson completion is manufactured.

The Azure adapter sends only the active flow's instructions, limits output to
1,024 tokens and distinguishes rate limiting from generic transient failures.
Prompt v8 asks for actual shortening/simplification when requested. English
free-speech recording uses the existing original-language English hint. API
contracts are regenerated. Migration `2026_09_25_000476` expands PostgreSQL's
flow check constraint; rollback refuses incompatible retained rows instead of
removing them. No new provider, secrets, credential storage, marks or retention
policy are introduced. Android free-speech typing retains the shared keyboard
inset; role-play's enlarged-text exit layout is preserved.

## Verification in progress

Initial runs caught and repaired the PostgreSQL constraint and Android keyboard
layout issues, plus outdated legacy-path fixtures. Current final gate results,
live rechecks and release hashes will be recorded below before completion.


## Candidate gates and deployment

- `make verify` with PHP memory_limit=1G: Pint/PHPStan, 349 SQLite tests /
  3,251 assertions; 425 PostgreSQL tests / 5,790 assertions; 538 web tests,
  contract checks, TypeScript, lint and supporting package checks pass.
  Existing two moderate JavaScript dependency advisories remain.
- `make e2e`: 38 browser journeys and four API tests / 103 assertions pass.
- `make ai-eval`: 15 locked cases and 21 dialogue tests / 154 assertions pass
  (then a test-only membership type assertion was added for PHPStan). No speech
  benchmark run was claimed.
- `make build`, `make android-closed-beta` and APK verification pass. Existing
  bundle-size/Gradle deprecation advisories and debug signing remain.
- Guarded release `conversation-seven-20260925-r1` is active. Additive migration
  000476 ran successfully. Readiness, exact web HTML, source fingerprint and
  private recovery storage checks passed. Prior release is retained for rollback.
- Web HTML SHA-256: `f51749d853fde9c8e7452c253e319a9bbc3bde915f7f13ff208679a90e5a32a5`.
- Source fingerprint: `2d69911d18966db47d59859fefa95153dd33f81f79d3d9103bb6f56af0812aa1`.
- APK: 15,389,255 bytes, SHA-256
  `346ab32ce794d0a9ad23cdf939e14ecc8ae437fef006f20488c2e6d576995cc8`.
  The public download and preserve-data installation on realme RMX3085 Android
  13 match exactly. Physical WebView checks confirm Learn opens question help,
  Back returns to the lesson/drill, and Everyday talk requires an unchecked
  temporary-recovery acknowledgement. The phone was returned to Kannada/dark Home.

Live recheck requests are spaced at least 65 seconds apart because of the
unchanged Azure deployment quota. This is a response-quality check, not proof
of ordinary-speed or multi-user capacity. Final per-flow outcomes follow.

### Live-discovered stale correction follow-up

The first repaired Everyday talk run completed seven model turns, remembered
balcony tomatoes, the morning watering routine and sharing cherry tomatoes with
a neighbour. Turn 5 nevertheless repeated the correction from turn 4 after the
learner submitted correct English. This is recorded as a defect, not a clean
seven-turn pass.

Prompt v9 explicitly assesses only the current sentence and keeps grammatical
advice outside the main conversational reply. The orchestrator also drops a
free-speech correction when its corrected sentence already matches the current
sentence, or its claimed learner span is absent from the current sentence.
This only removes stale advisory feedback; it cannot accept/score a role-play
turn or change marks. Reproducing unit/integration tests cover preservation of
a genuine error, dropping stale feedback and keeping the main reply/usage.

Backend-only release `conversation-seven-20260925-r2` is active; the APK/web
hashes above are unchanged. Readiness and recovery-storage checks pass. The
v9 full verification passed 350 SQLite / 3,261 assertions, 425 PostgreSQL /
5,789 assertions and 538 web tests. The subsequently added integration case
also passed separately on PostgreSQL (one test / six assertions); AI evaluation
passes 15 locked cases plus 23 dialogue tests / 171 assertions. PHPStan passes
including that final test. No additional migration/configuration is required.

### Seven-turn budget and current-question follow-up

Role-play's next live round accepted the corrected retry and continued naturally
through six turns. The seventh was rejected before provider dispatch. A scoped,
content-free query of that synthetic session confirmed 12,505 model tokens used
against the existing 12,000-token session ceiling (eight learner turns allowed).
The session was explicitly exited/deleted. Neither tenant access nor Azure
quota was bypassed, and spending limits were not increased.

Prompt v10 removes redundant role-play instructions while preserving current-turn
assessment, correction-only retries, valid alternatives, AI-role ownership,
objective evidence and safety rules. Its role-play instruction block is 2,202
bytes. A subsequent v11 adapter refinement puts the current learner utterance
last, explicitly identifying the question to answer after the historical context.
This follows a free-speech recall response that drifted into generic gardening
advice instead of directly answering the question. It does not grant history or
user text any policy authority. Final prompt/adapter tests pass 15 adapter tests /
70 assertions and 15 evaluation cases plus 23 dialogue tests / 171 assertions.

Active backend-only release is `conversation-seven-20260925-r4` (v11); browser
and APK hashes remain unchanged. Readiness, web hash, source fingerprint and
private recovery storage checks pass. Live final-flow rechecks remain in progress.

## Files in this bounded repair

- API: `ConversationDialogueService`, `DialogueModelPolicy`,
  `DialogueSpeechService`, `FakeDialogueResponder`, `ConversationOrchestrator`,
  Azure model adapter/HTTP boundary, `VoiceRecordingService`, start-session
  request validation, and migration `2026_09_25_000476_allow_free_speech_dialogue`.
- Web: `SpeakRoom`, `QuestionHelpJourney`, `InteractiveDialogue`, shared
  conversation CSS, `LevelPathPanel`, and Android download metadata.
- Contracts/evidence: OpenAPI and generated contract types; corresponding API,
  provider, dialogue, learner UI and browser tests; this issue record and the
  status/decision/quality ledgers. Existing unrelated work remains untouched.

### Specific explanation target and final gates

Ask Swathi's seven-turn round returned all seven responses, shortened a request,
asked permission in English, explained in Kannada after Yes, reset to English,
and generated a relevant present-tense quiz. The local explanation nevertheless
returned to the initial question rather than explaining the immediately preceding
answer, and used awkward mixed-language examples. This was treated as a defect.

Prompt v12 and the server now pass an explicit same-session `explanation_target`
from the last informational question/answer. Permission-only responses cannot
replace that target. Local explanations are requested as short natural sentences,
with intact quoted English examples instead of English modals spliced into local
grammar. This is not independent Kannada linguistic acceptance.

The final `make verify` run passes Pint, PHPStan, contracts, lint, TypeScript,
350 SQLite tests / 3,261 assertions, 427 PostgreSQL tests / 5,799 assertions,
and 538 web tests. AI evaluation passes 15 locked cases plus 24 dialogue tests /
176 assertions; the Azure adapter passes 15 tests / 70 assertions. Active release
is `conversation-seven-20260925-r5` (v12). Same APK/web fingerprints; no further
migration or configuration change. Readiness and recovery-storage checks pass.

The Learn tutor then completed seven live questions coherently: meaning of can,
negative form, question form, explaining the erroneous third-person -s after can,
confirming the corrected sentence, a new cooking example and an appropriate
practice question. No drill gate, new marks or browser exceptions occurred.

### Preserve detailed role rules while reducing request size

A further v12 run corrected the first error, but its correct retry received a
safe fallback; a separate bounded synthetic provider retry returned an accepted,
policy-valid answer. The precise rejected field in the original response was
not retained, so no exact cause is claimed. The subsequent name turn advanced.
The queued run was stopped and its temporary session explicitly exited (200).

Candidate v13 restores the more detailed correction/retry instructions and
reduces model context by removing opaque history IDs and objective audit
IDs/digests, plus unrelated legacy envelope metadata. Learner/reply history,
assessment/action, objective keys/states, roles, current question and explanation
target remain. These audit records stay in the project database; validation,
tenant checks and spending limits are unchanged. Regression checks verify both
that conversational facts survive and that unnecessary identifiers are omitted.
Final seven-turn rechecks follow deployment of this candidate.


## Final candidate and verification — r6

The active release is `conversation-seven-20260925-r6`, prompt v13. Guarded
activation, readiness, source fingerprint, published web/APK hashes and private
recovery storage checks passed. One additive migration,
`2026_09_25_000476_allow_free_speech_dialogue`, extends the existing flow constraint.
No new secret/configuration, spending limit, authoritative mark or retention change.
The existing user-managed saved-note controls and coaching modes remain available
in free speech; they are separate from temporary conversation recovery.

Final required gates actually run:

- `make verify` with pinned toolchains and a 1 GiB PHP test limit: pass;
  350 SQLite tests / 3,261 assertions, 427 PostgreSQL / 5,801 assertions,
  539 web tests, Pint, PHPStan, contracts, lint and type checking.
- `make e2e`: 38 browser tests plus 4 API tests / 103 assertions pass.
- `make ai-eval`: 15 locked cases and 24 dialogue tests / 177 assertions pass.
  Speech benchmark was skipped and is not claimed.
- `make build`, Android closed-beta build and APK verification pass.
  Existing JS advisory/chunk-size warnings remain.

Android 1.0.2/code 3 debug APK was installed on the realme RMX3085 with app data
preserved. The installed bytes match the published 15,389,244-byte APK:
`34ae87fb15d53e34d9e857cbe8d7911b7280fb5a22c3b5efb176c8aaabfeed7f`.
Download cache key is `conversation-fix-20260925.2`. Web HTML SHA-256:
`6a574adbbb0558fd962071f85c55cf3cb37a960f51d8de6a591f5eb91018c62c`.
Release source fingerprint:
`ff725d9154bbdf357530276d5ecd7afd92078fc13553c771a0071d22dd9861d8`.

Azure management CLI refresh still requires authorized MFA (AADSTS50076);
the owner's device sign-in returned access denied and that code subsequently
expired. No quota change was made. The live response-quality checks deliberately
pace model calls by 65 seconds; they do not establish normal-speed or concurrent
learner capacity. No real microphone capture or independent Kannada linguistic
review was performed. All live conversational evidence uses synthetic text;
organization/session authorization and recovery deletion remain enforced.


### Final r6 live recheck results

A fresh seven-turn clinic role-play completed all seven turns (HTTP 201,
completed/safety ok). Turn 1 corrected agreement; turn 2 accepted the corrected
sentence; turns 3–7 continued as receptionist, retained the synthetic name,
handled an unknown appointment time, waiting time, forms and waiting location.
No stale correction or learner-voice echo appeared in these typed replies.
The scoped, content-free cost ledger records 11,080 model tokens, within the
unchanged 12,000 session ceiling; seven rows completed without failure codes.
This is evidence for this sequence, not a guarantee for arbitrary longer turns.
Exit returned 200. Browser page-error count remained zero.

The final installed APK's physical entry checks pass: Ask AI Tutor opens question
help with no canned drill; Back exposes Start Skill Focus; Everyday talk shows
coaching choices and disables Start until temporary-processing consent is checked.
CDP's initial automatic scrolling timed out; explicitly centering the target
before a normal click completed the check. No forced click was used. The phone
was restored to Kannada/dark Home. A pre-deployment authenticated test browser
also lost navigation state across restart; a fresh authenticated browser was used
for the completed AI run, and those harness timeouts are not counted as AI turns.


### Free-speech follow-up after r6

The r6 targeted free-speech run corrected both agreement errors, then returned
a safe fallback for the corrected sentence. Its third answer correctly recalled
tomatoes and happiness. This was not counted as a clean pass. The original
rejected model fields were not retained, so an exact rejection cause is not
claimed. The follow-up extends the existing strict provider response schema
from question help to free speech: unassessed, no completion/objective evidence,
English reply, and nullable optional correction with exact UI explanation locale
and no forced retry. All application safety/length/language validation stays in
place. Adapter contract version is `confidence-turn-output-2026-09-25.3`.
A regression covers these provider constraints; the existing policy test still
rejects an assessed free-speech result. The first gate attempt caught a stale
contract-version test expectation; it was updated before rerunning verification.
This backend-only follow-up does not change the installed APK or web build.


The targeted r6 Ask Swathi recheck passed all five controls/questions: initial
meaning, genuinely shorter polite request, English permission offer, Kannada
explanation of that latest request after Yes, and an English answer to the next
question. No drift back to the first question, correction card or browser error.
Exit returned 200. This supplements the earlier completed seven-turn tutor runs;
it does not constitute independent linguistic review.

Backend release `conversation-seven-20260925-r7` is active and healthy. The final
`make verify` passes 351 SQLite / 3,263 assertions, 427 PostgreSQL / 5,801,
and 539 web tests plus all static/contracts checks. `make ai-eval` again passes
15 locked cases and 24 dialogue tests / 177 assertions. The already-passing
browser E2E and web/Android build apply unchanged: this last update touches only
the server-owned provider request shape. Web/APK hashes and source fingerprint
above are unchanged. No further migration/configuration is required.


### Final r7 free-speech result and completion

A fresh seven-turn run completed all seven replies (HTTP 201, completed/safety
ok, unassessed). It corrected the deliberately wrong tomato sentence, continued
normally after the corrected sentence with no correction card, recalled tomatoes
and happiness, responded to sharing with a neighbour, recalled what was shared,
responded to morning watering, and finally combined tomatoes + morning watering
+ neighbour correctly. There were zero fallbacks and zero browser page errors.
The optional correction appeared only on the erroneous first turn. Exit returned
200, and the harness exited successfully. The scoped content-free ledger records
11,648 model tokens and seven completed rows with no failure codes, within the
unchanged 12,000-token ceiling.

Completed coverage: seven-turn role-play, seven-turn free speech, seven-turn
Learn tutor, and seven-turn Ask Swathi, with additional targeted follow-ups for
correction retry, current-question recall and latest-answer local explanation.
Earlier failed attempts are retained above and are not reported as passes. The
final role-play/free-speech runs and the Learn tutor round were coherent across
all seven interactions; Ask Swathi's discovered explanation-target defect passed
its subsequent five-control/question recheck. All bounded synthetic sessions
were explicitly exited; temporary provider diagnostic scripts were removed.

Recommended next checkpoint: restore authorized Azure management access and
size/verify provider capacity for ordinary-speed and concurrent conversations,
then run representative real-microphone English role-play and consented local-
language listener checks. The tiny provider quota, arbitrary longer session
budgets, and human linguistic quality are not certified by these paced typed runs.
