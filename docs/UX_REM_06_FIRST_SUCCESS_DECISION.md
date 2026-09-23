---
title: "UX-REM-06 shorter first-success decision packet"
status: "Option A accepted"
date: "23 September 2026"
---

# UX-REM-06 shorter first-success decision packet

## Accepted decision

The Accountable Product Owner accepted **Option A** on 23 September 2026.
The refined current sequence is therefore authoritative for this release.
The implementation already conforms to Option A, so acceptance requires no
code, API, OpenAPI, schema, migration, consent, authentication, restoration or
catalogue change. Option B is not authorized for this release.

## Decision boundary

The current ten-stage first-use sequence is accepted and implemented. Changing
its order changes when accessibility setup, placement, consent, microphone
education and the first private practice occur. That is a product decision,
not a copy-only change. Neither option below changes the authoritative adult
and goal payload, consent purposes, deterministic scoring, mastery or
credential storage.

## Option A — retain the refined ten-stage sequence (recommended)

Sequence: Welcome → adult confirmation → language/accessibility preferences →
goal → starting point → audio check → optional voice/AI consent → microphone
or typed fallback → private practice → setup summary → Home/first lesson.

- The existing `{ adult_confirmed: true, goal_key }` command remains the only
  authoritative onboarding write and keeps its stable idempotency key.
- The two voice purposes remain independent and optional. Selecting neither
  moves directly to microphone education; typed practice remains available.
- No microphone capture occurs before the recording choice and no provider
  processing occurs before the matching consent is granted.
- Browser/Android Back and encrypted option-only restoration retain their
  current tested behavior. Adult confirmation is deliberately re-requested
  after process restoration and no credentials, typed text, audio or consent
  token enters the resume record.
- Minimum deliberate progress is ten primary actions plus the required choices;
  exact median/p90 time remains a UX-HUM-03 measurement, not an estimate
  represented as observed data.
- UX-REM-03 already supplies task-specific actions and explicit completion.
  A first successful lesson ends in the simplified can-do result delivered by
  UX-REM-04.
- No API, schema, ADR or master-plan amendment is required.

Trade-off: the safety and accessibility setup is explicit before practice, but
the first useful English turn occurs later than the fast path.

## Option B — adult/goal fast path

Sequence: Welcome → adult confirmation → goal → first deterministic typed or
listen/select lesson. Language/accessibility, starting point, audio,
microphone and optional voice/AI setup appear contextually before the first
feature that needs each choice.

- The authoritative `{ adult_confirmed: true, goal_key }` command remains
  unchanged. Deferred preferences require a new explicit completion-state
  contract so the server does not falsely report all first-use setup complete.
- The first lesson must be deterministic and microphone-free. **Type instead**
  is available before any speaking prompt.
- Recording consent appears immediately before the first upload-capable
  recording. Provider-processing consent appears immediately before the first
  AI conversation. Neither may be bundled, preselected or inferred.
- Restoration must add versioned deferred-stage state while retaining the
  current tenant/member binding and option-only privacy boundary.
- Minimum deliberate progress is three primary actions plus the first lesson
  choices. Exact time/tap distributions still require the moderated prototype
  study.
- This option requires an approved product specification, API/OpenAPI changes
  for partial onboarding state, generated-client updates, migration/backfill
  review, and master-plan/ADR amendments before production implementation.

Trade-off: the first useful turn arrives earlier, but setup becomes contextual
and the authoritative meaning of “onboarding completed” must change safely.

## Recommendation

Accept Option A for this release. It preserves the reviewed privacy,
restoration and authority boundaries, while UX-REM-03/04 already remove the
largest clarity and first-result problems. Treat Option B as a separately
specified experiment only after UX-HUM-03 measures the refined current flow.

## Human evidence still required

For either option, UX-HUM-03 must represent 5–8 casual learners, 5–8 students
and 5–8 business professionals, including Kannada-first and hesitant learners.
Record voice-choice comprehension, first-use completion without facilitator
help, median/p90 time, abandonment point and invalid-submit count. Missing
human observations are not converted to automated passes.
