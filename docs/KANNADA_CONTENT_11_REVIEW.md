# KANNADA-CONTENT-11 review — 26 September 2026

## Scope and files

- `apps/web/src/learner/content/kannadaInstructions.json`: 187 Kannada reading
  variants, exact English source keys, pinned source-file SHA-256 values and review
  status. Deployment left the pack pending; APP-0109 / D-0356 later records
  S R Bhandary's sign-off of this same revision as `approved`. Covers all 17
  foundation lessons plus shared
  activity directions. No selected answers or option-index keys are included.
- `instructionSources.json`: per-concept source inventory for coverage review.
- `lessonInstruction.ts`: source-exact resolution only for Kannada in the explicit
  translation-preview policy. Checkpoint prefixes and shared pattern-noticing
  directions retain the original English practice payload. Unknown/changed source,
  English selection and approved-only builds retain the source text.
- `LevelPathPanel.tsx`: uses Kannada directions in Introduction/Practice activities,
  Conversation scenarios/goals, Interaction tasks, quick checks, checkpoint reviews
  and returned answer explanations. Correct language attributes accompany the text.
  Practice sentences, options and grammar forms remain English.
- Resolver and component tests check all 17 source inventories, source hashes,
  language switching/fallback, activity directions and a Kannada quiz that submits
  the unchanged item identifier and selected option index.
- `appRelease.json` and download documentation update the Android artifact to
  **1.0.10/code 11**; existing shared version/packaging paths are reused.

## Content and security boundaries

These are source-linked draft reading variants for the existing explicit pilot
preview; no published blueprint, existing human review, learner evidence, answer
key, mark, progression record or authoritative content hash is rewritten. Existing
UI preview disclosure remains. Engineering completion did not invent this review.
The owner's later sign-off is APP-0109 / D-0356 on this exact revision. No API/schema/migration,
configuration, provider/prompt, credential, retention or tenant authorization change.
No AI generation or translation provider calls are made at runtime.

## Verification

The read-only, Direct-organization-scoped live curriculum inventory returned 17
published concepts. All scenario, goal, Apply task, check/checkpoint prompt and
returned-rationale source strings match the pack (zero missing). No learner data
or credentials were read into the inventory. Initial focused checks caught a
feedback-prefix regression and a new test's TypeScript tuple annotation; both
were corrected before final verification.

Focused tests: 22 passed. `make e2e`: 47 browser journeys and 4 API tests/103
assertions passed. Final isolated `make android-closed-beta` and public
`VITE_UI_TRANSLATION_PREVIEW=true make build` passed, including 13 native JVM
tests. APK signature and Android 1.0.10/code 11 metadata checks passed.

Physical phone updated in place. Read-only navigation verified Kannada Introduction
activities, Conversation scenario/goals and Practice quiz instructions. Screenshot
inspection and DOM checks confirmed Kannada `lang`, intact English options and no
horizontal overflow. No quiz answer, completion or AI turn was submitted on the
phone. A follow-up Level 2 activity wait initially timed out because the activity list
was collapsed; opening its details panel verified the Kannada directions with
English should patterns intact.

The first full verify run passed SQLite but hit six PostgreSQL test-isolation
safeguards while browser tests ran concurrently; no unsafe database access was
allowed. The isolated final `make verify` passed: SQLite 363 tests/3,360 assertions,
PostgreSQL 441 tests/6,441 assertions, 566 web tests, 38 localization tests,
18 design-system tests and 4 marketing tests. Static, security, locale, contract,
lint and type checks passed. `git diff --check` passed.

APK: `global-spoken-english-1.0.10.apk`, 15,435,847 bytes; SHA-256
`f291a2357d5453a528dc66a315cd956dcb4a5154dbb92870d0feb0af2f5807dd`.
AI evaluation is not rerun because prompts, models and orchestration are unchanged.

The independent review recommended here is recorded under APP-0109 / D-0356.
That sign-off accepts this exact pack. It does not change approved-only
resolution or the English learning language.

## Release

Active release: `kannada-content-20260926-r1`. Public readiness and exact index/APK
hash checks passed; transient TLS resets during service restart recovered on retry.
Public homepage shows v1.0.10 with the versioned APK URL and download filename.
The phone reports installed version 1.0.10/code 11. Existing account data was
preserved. No migration or new environment configuration was required.
