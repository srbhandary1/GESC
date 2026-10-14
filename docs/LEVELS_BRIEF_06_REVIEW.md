# LEVELS-BRIEF-06 review — 25 September 2026

## Behaviour and files

- `apps/api/config/level_sessions.php` defines starting levels `[1,2]` and rule
  `level_unlock_initial_two_v2`. `LevelSessionProgressService` uses the same
  configuration in projections and server commands, includes it in the rule hash,
  and skips redundant new unlock records for already-open starting levels.
- Later levels retain preceding-level completion, checkpoint and review rules.
  No progress is fabricated and historical records are not changed. Expired/no
  subscription, per-plan session restrictions and unavailable content remain gated.
- `DialogueModelPolicy` v18 and Azure adapter output contract v4 target one simple
  8–15-word reply, with a maximum of two sentences, 30 words and 240 Unicode
  characters. Correction guidance is one sentence/20 words/160 characters, with
  no encouragement paragraph. Overlong output takes the existing safe-output
  path; text is never chopped mid-sentence or used to fabricate assessment.
  The JSON token ceiling remains 1,024 to avoid truncating the structured envelope;
  this is a maximum reservation, not a charge for 1,024 generated tokens.
- Dialogue fixtures, policy/adapter tests, progression/tenant/review/HTTP tests
  and the browser starting-level journey cover the changed behavior.
- OpenAPI documents starting access; generated contract files were regenerated.
  Master v3.20, ADR-0025/0042 clarifications and D-0350 record the owner instruction.
  Specification: `LEVELS_BRIEF_06_SPEC.md`; STATUS/QUALITY_REVIEW updated at closure.

## Verification

- Focused API checks: **56 tests, 591 assertions** passed. Review-due regression
  checks: three tests/10 assertions passed after moving their earned-unlock
  fixture from Level 2 to Level 3.
- Final `make verify`: SQLite **361 tests/3,349 assertions**; PostgreSQL **438/
  5,951**; **545 web tests**, plus localization/design-system/marketing tests,
  static analysis, contracts and security checks passed. The initial run caught
  two obsolete tests expecting Level 1 completion to create a Level 2 unlock;
  corrected fixtures preserve review/earned-unlock coverage for Level 3.
- `make e2e`: **45 browser journeys** and four API tests/103 assertions passed.
- `make ai-eval`: **15 locked cases**, **32 dialogue tests/262 assertions** passed.
  No physical speech benchmark was run.
- `make build` and `git diff --check` passed. Existing large-bundle warning and
  excluded unlicensed 3D avatar asset remain.

## Release

Active invite-only release: `levels-brief-20260925-r2`; rollback predecessor:
`levels-brief-20260925-r1` (which introduced the access/brief-reply slice). Public JSON readiness, public web/APK fingerprints and
private recovery tmpfs checks passed. No migration, new environment variable,
provider credential, provider route, price or audio-minute formula change.

The existing Android **1.0.4/code 5** receives the server policy/access changes;
no client runtime change or APK reinstall is needed. Web build includes refreshed
contract metadata. APK SHA-256 remains
`c96865641d1c9b4da08fe0e57efa44ecea74e5f35a88df3d9b04d65a30877da8`.
Web index SHA-256:
`6b5806786c3dc66e2d375865dacb8e45bbd2041056780342662d8f287e97a897`.
Web input fingerprint:
`15e819158037e0d410c41ac7b595abb368500f748614f2626509557ab5e39a9a`.
Release patch archive SHA-256:
`32e8d17ae71ef78def2fd3b0db8d5fa692d6b867045792ecf7e12530a1c86426` (v18 backend-only refinement); initial slice archive:
`bee6b35d42afc5b58c3b27fa7298ab1f263535bf1c6100725183f514e900c47b`.

## Privacy, limits and next checkpoint

Tenant scope, authentication, privacy/retention and deterministic learning
authority remain unchanged. Test text is synthetic; phone captures stay outside
the repository in `/tmp/gse-levels-brief-audit/`. Live model tests are recorded
below after completion, separately from synthetic tests.

STT usage measures learner input audio. Shorter generated replies reduce TTS
text/audio work and constrain model output; they do not reduce previously recorded
STT input or change actual speaking/listening metering. Existing speech rate-card,
fallback reconciliation, session-limit UX and 1,000-speaker capacity work remains
open. Oversized/invalid model output may require a retry; no automatic extra model
call is added. Independent language/educational acceptance is still outstanding.
Recommended next checkpoint: provider-cost reconciliation, followed by explicit
session-limit/restart handling and funded capacity verification.

## Connected Android checks

Before release, the signed-in phone showed only Level 1 as ready. After release,
it showed Level 1 and Level 2 as ready, with no alert, and Level 2's Introduction
opened successfully. Accepted captures: `before.png`, `after.png` and
`level-2-sessions.png` in the temporary audit directory. Full-suite tests cover
Demo, both paid plans, the later-level gate and cross-organization evidence.

Live typed-input checks used the connected Android app and deployed providers:

- Free speech: a gardening question received one 16-word sentence. After the
  native-script repair below, a grammar mistake received a 10-word topic question
  plus a short Kannada correction. The reading aid displayed the matching Kannada
  question beneath that reply. Its helper timed out because it instrumented turn
  creation rather than the separate reading endpoint; the rendered text was
  independently inspected, so this is a UI observation, not a captured turn result.
- Ask AI Tutor: photosynthesis received a clear 13-word sentence. An explicit
  request for ten long sentences returned the safe fallback, not a successful
  educational answer; no long answer was displayed/spoken. The precise rejection
  cause was not diagnosed, and this adversarial case remains a quality limitation.
- Roleplay: “I has an appointment” received an 11-word correction with short
  Kannada guidance and a retry request. “I have an appointment” was accepted;
  the receptionist moved on with “Good morning! May I have your name and
  appointment time, please?” (11 words), without another correction.

These are typed-input live checks, not microphone/STT acceptance tests. Temporary
browser instrumentation captured only synthetic QA result fields outside the repo.
No automatic extra provider request was introduced to repair rejected output.

The first v17 correction trial entered the existing safe fallback. Two controlled
synthetic diagnostic requests through the same domain service (including capacity,
entitlement and provider ledger accounting) showed the provider returning a short
English correction explanation labelled `kn-IN`. Brevity was not the failure;
the existing native-script guard correctly rejected it. v18 strengthens the
selected-language/script instruction and adds regression coverage without relaxing
that guard or the reply budget. No raw real learner content was inspected/logged.

QA conversations were closed and temporary context deleted through the app.
The phone was returned to Home in Kannada, reloaded to remove instrumentation,
and the local CDP relay stopped; the Mac ADB tunnel was left intact.
