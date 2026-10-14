# LEARN-LEVEL-08 review — 26 September 2026

Status: deployed to the invite-only pilot as `lesson-tutor-20260926-r1`;
physical-phone lesson validation is recorded below.

## Behaviour and changed files

- `LevelPathPanel`, `LevelSessionsApi`, `WebLevelSessionsApi` and
  `QuestionHelpJourney` start Ask AI Tutor through the current level/part endpoint.
  The lesson objective stays visible; failure cannot silently open generic chat.
- `LearnerLevelSessionController`, `LevelSessionDeliveryService`, the new
  `LessonQuestionContext`, `ConversationSessionService` and
  `ConversationDialogueService` authorize the level/part and pin server-authored
  lesson objective, concept, patterns and examples in encrypted recovery context.
  No client lesson text or check answer keys are admitted. Existing general Speak
  help and Skill Focus bindings remain separate. `ConversationTurnService` and
  `DialogueSpeechService` enforce lesson entitlements on subsequent requests.
- `DialogueModelPolicy` v19, `LocalDialogueGreeting` and `FakeDialogueResponder`
  teach the supplied lesson, preserve short replies and redirect unrelated topics.
- `LevelSessionDeliveryService` records a passing Practice check and its completion
  atomically; the panel immediately enables Conversation without a second tap.
  Failed checks remain locked. Replayed passes create no duplicate completion.
- New `ReconcilePracticeCompletionsCommand` supports organization-scoped dry-run
  and explicit apply. It requires completed Introduction, a genuine passed check,
  matching current blueprint ID/hash and the same learner/membership/organization.
  It never invents a score, awards spoken mastery or overrides subscription gates.
- OpenAPI and generated contracts document lesson-help start and Practice check
  completion. Android version and homepage download metadata are updated.
  Master v3.22, ADR-0025/0042, D-0352 and the bounded specification record scope.

## Verification

- `make verify`: SQLite 363 tests/3,360 assertions; PostgreSQL 441/6,442;
  557 web tests; PHP formatting/static analysis, dependency audit, contracts,
  locale/release checks, lint and typecheck passed.
- `make e2e`: 46 browser journeys and four API tests/103 assertions passed.
- `make ai-eval`: 15 locked cases and 35 tests/282 assertions passed. No human
  speech benchmark was run by this command.
- `AzureOpenAiConversationAdapterTest`: 18 tests/76 assertions passed, including
  server-pinned lesson context in the real adapter's mocked provider payload.
- Integration coverage iterates all 17 configured levels: context binding,
  injected lesson rejection, failed/pass/replayed check, persisted completion,
  and Conversation start. Later-level progression is disabled only in this fixture
  to isolate each level's within-level prerequisites. Reconciliation coverage
  includes stale blueprint, different learner and different organization rejection.
- Browser coverage verifies Level 2's scoped tutor request and proceeding from a
  passing Practice result directly to Conversation. Consent remains explicit.
- `make android-closed-beta` and `make build` passed, including 13 native JVM tests.
  APK signature verification passed. The installed phone and public download
  match the APK hash below; the public homepage links to its new version URL.
- Early checks caught and fixed an implicit `accepted` validation rule affecting
  ordinary lesson-room requests and nullable test-fixture static-analysis errors.
  Final checks above passed after those fixes. Generated contract drift was repaired.

## Release and saved-progress repair

Android **1.0.6 / code 7**, 15,287,311 bytes. SHA-256:
`3ec66093bd760df060066b647d4cd50c145d60878994d3eff755ca3c4bd3a05d`.

Download: https://ai.globalspokenenglish.com/downloads/global-spoken-english-android.apk?v=lesson-tutor-20260926.1

Public index SHA-256:
`2f13747e6ab4b4044c79bac71b92732dc7eaee1f1091d6591e2222fccda14499`.
Source fingerprint:
`370557969cb3526de67fb29998e27a99b09f5cebff040eee757ffc4245d3c935`.

Atomic release cutover verified public readiness, index/APK hashes and the existing
memory-backed recovery mount. Readiness briefly retried during the service restart
and then passed. Phone update preserved existing account/app data.

Content-free inspection confirmed the reported Level 1 check had passed while only
Introduction had a completion record. Organization-scoped reconciliation dry-run
found one eligible completion, apply recorded one, and a second dry-run found zero.
The phone then displayed Introduction and Practice as completed. No scores,
assessment evidence or other level completions were changed.

## Security, privacy, configuration and limitations

No migration, new environment variable, secret, provider route or retention change.
Existing lesson authorization, consent, encrypted recovery, short output validation
and plan gates apply. The new completion references existing immutable check
identity; repair is repeatable and does not use AI as progression authority.

Live test details are recorded below. Automated all-level checks do
not mean all 17 levels were tested live on the current learner's phone. Prompt
adherence remains probabilistic. Existing tiny provider quotas, 1,000-speaker load
readiness, rate-card/all-attempt expense reconciliation and independent human
review remain open; this bounded fix does not certify them. Recommended next
checkpoint: provider cost reconciliation and funded representative capacity tests.

## Physical Android lesson checks

Seven live synthetic typed interactions passed through the updated phone UI and
Azure model: four in Level 1 and three in Level 2. All returned completed/ok,
8–22 words. Level 1 explained can/cannot, gave a permission example, corrected
“She can sings” to “She can sing”, and redirected a photosynthesis request to
can/cannot. Level 2 explained should, supplied an advice example and corrected
“You should sleeps early” to “You should sleep early”. Follow-ups retained the
current lesson rather than asking for a general topic. Synthetic sessions were
explicitly closed; no lesson completion was awarded for QA conversations.

Level 1's Conversation tab was enabled; opening its room rendered the recording
controls successfully after the repair. No Conversation completion was submitted. The phone retained Kannada UI/dark mode and the existing learner account.
All 17 levels have automated coverage; live checks covered only Levels 1 and 2.

One Level 1 correction had a text-only speech fallback. Content-free provider
metadata showed the other three Level 1 TTS results used Azure successfully; the
existing adapter returned project-text-first for that correction and discards its
underlying fallback exception. This cannot be diagnosed as a specific quota or
transport error from the retained metadata. All three Level 2 replies included WAV
audio; observed playback started and ended for the last two, including the analogous
“should sleep” correction. These are seven successful text responses, not seven
successful audio responses. No claim is made that intermittent provider audio
failure is fixed. Provider-attempt observability/reliability remains the next
bounded checkpoint alongside the previously open cost/capacity work.
