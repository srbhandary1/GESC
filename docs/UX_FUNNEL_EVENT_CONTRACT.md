# Privacy-safe UX funnel event contract

**Contract ID:** UX-EVENTS-01

**Status:** Accepted design contract under UX-AUDIT-00; emission not implemented

**Authority:** APP-0084; D-0289; D-0290

**Schema version:** `1`

**Owner:** Accountable Product Owner

**Review gate:** independent privacy, security and tenancy review before any
event store, endpoint, export or third-party destination is enabled

## 1. Purpose and non-authoritative boundary

This contract makes first-use, permission, quiz, plans and delayed-review
funnels reproducible without collecting lesson or learner-authored content.
Events are observational product analytics only. They must never write or
override attempts, marks, mastery, progression, entitlements, streaks, review
occurrences, consent or other authoritative records.

This checkpoint defines the contract only. No client/server emission, storage,
export, dashboard, vendor dispatch or tracking consent change is authorized.

## 2. Event envelope

Every accepted event must contain exactly this controlled envelope plus the
allowlisted event dimensions in §5:

| Field | Type/rule | Source |
|---|---|---|
| `event_id` | Server-minted ULID; idempotency key | Server |
| `event_name` | Enum from §5 | Validated request/server lifecycle |
| `schema_version` | Integer `1` | Server |
| `occurred_at` | UTC timestamp, server-bounded against receipt time | Server or validated client clock |
| `received_at` | UTC timestamp | Server |
| `organization_id` | Existing organization ULID | Authenticated organization context; never trusted from a client property |
| `learner_membership_id` | Existing learner-membership ULID | Authenticated membership context; never trusted from a client property |
| `flow_instance_id` | Random per-journey opaque ID, rotated at journey completion or after 24 hours | First-party client; contains no identity or semantics |
| `experiment_assignment_id` | Optional opaque ID for an accepted experiment | Server assignment only |

The ordinary application log may contain event name, result category and an
opaque correlation identifier only. It must not duplicate the analytics row or
its learner identifier.

## 3. Controlled dimensions

Only these cross-event dimensions are allowed. Event-specific dimensions are
further restricted in §5.

| Dimension | Allowed values/rule |
|---|---|
| `screen_id` | Controlled enum; never a URL, title or arbitrary string |
| `step_id` | Controlled journey-local enum |
| `result_category` | Controlled enum such as `accepted`, `validation_rejected`, `authorization_rejected`, `conflict`, `transport_failure`, `server_failure`, `cancelled` |
| `elapsed_bucket` | `<5s`, `5-14s`, `15-29s`, `30-59s`, `1-2m`, `2-5m`, `>5m`; no raw duration |
| `locale` | Supported UI locale code |
| `app_version` | Released app/build version, not device identity |
| `android_api_bucket` | `24-28`, `29-32`, `33-34`, `35`, `36+`, `web`, `unknown` |
| `viewport_bucket` | `<360`, `360-399`, `400-599`, `600+`; no exact dimensions |
| `network_state` | `offline`, `constrained`, `online`, `unknown`; no carrier, SSID or IP |
| `restore` | Boolean; whether the journey resumed from persisted first-party state |
| `ordinal` | Positive integer within a controlled journey; not content identity |
| `attempt_number` | Positive integer for the current authoritative item/attempt |
| `correctness` | Boolean or `not_applicable`; separate from review-attempt classification |
| `level_number` | Integer from the published level model |
| `part_key` | Controlled curriculum part enum/opaque published part key; no title/text |
| `entitlement_state` | Controlled enum returned by the authoritative entitlement service |
| `plan_code` | Existing public plan code only; no price, tax identity or payment data |
| `due_age_bucket` | `not_due`, `0d`, `1d`, `2-3d`, `4-7d`, `8-30d`, `31d+` calculated in the persisted occurrence timezone |
| `review_classification` | `not_eligible`, `reviewed_on_time`, `reviewed_late` per D-0289 |

Arbitrary properties are fail-closed. A new event or dimension requires a
versioned contract change and the same privacy/security/tenancy review gate.

## 4. Forbidden content

The following must be rejected before persistence and must not appear in event
properties, identifiers, logs, exports or error payloads:

- raw or derived audio, media bytes, object keys, waveform/fingerprint data;
- transcripts, typed answers, learner-authored text, conversation replies,
  prompts, corrections, rationales, question/option text or content titles;
- names, usernames, email addresses, phone/recovery contacts, postal addresses
  or personal facts;
- passwords, OTPs, invite/recovery codes, access/refresh tokens, cookies,
  authorization headers or provider credentials;
- card/payment credentials, transaction instruments, GST/tax identifiers or
  checkout-provider payloads;
- advertising IDs, hardware/device IDs, IP addresses, raw user agents, SSIDs,
  carrier data, precise location or fingerprinting inputs;
- accent, caste, religion, disability, employability, emotion, pronunciation
  or demographic inferences;
- arbitrary exception messages, stack traces, URLs, query strings or request
  bodies; and
- any identifier belonging to another organization or any unscoped/global
  learner export.

## 5. Event catalogue

All sequences are ordered within `flow_instance_id`. Replayed events with the
same accepted idempotency identity do not increment counts.

### 5.1 First use

| Event | When accepted | Event-specific allowed dimensions |
|---|---|---|
| `onboarding_view` | Authenticated `#home` card becomes visible and interactable while authoritative `onboarding.completed=false`; once per flow instance | `screen_id=learner_home_onboarding_card`, `locale`, `app_version`, `android_api_bucket`, `viewport_bucket`, `network_state`, `restore` |
| `onboarding_step_complete` | A controlled onboarding step passes local validation | `screen_id`, `step_id`, `elapsed_bucket` |
| `onboarding_submit_result` | The submit request resolves; `accepted` only after authoritative server confirmation | `screen_id`, `result_category`, `elapsed_bucket`, `network_state` |
| `first_lesson_start` | First authoritative lesson attempt/session starts after entry | `level_number`, `part_key`, `elapsed_bucket`, `restore` |
| `first_lesson_complete` | First lesson reaches its existing authoritative completion condition | `level_number`, `part_key`, `elapsed_bucket` |

The onboarding denominator is unique scoped learners with `onboarding_view`.
Success is unique entrants with an accepted `onboarding_submit_result` for the
same analysis cohort/window. First-lesson activation is reported separately for
same-session and within-24-hour windows.

### 5.2 Permission

| Event | When accepted | Event-specific allowed dimensions |
|---|---|---|
| `permission_rationale_view` | First-party rationale is shown before a system request | `permission_type=microphone`, `screen_id` |
| `permission_action` | Learner chooses continue, not now or typed fallback | `permission_type`, `action_category` |
| `permission_result` | Android/browser returns a controlled result | `permission_type`, `result_category=granted/denied/permanently_denied/unavailable`, `typed_fallback_selected` |

### 5.3 Quiz

| Event | When accepted | Event-specific allowed dimensions |
|---|---|---|
| `quiz_start` | Authoritative quiz attempt begins | `level_number`, `part_key`, `attempt_number`, `restore` |
| `question_view` | One question becomes visible and actionable | `level_number`, `part_key`, `ordinal` |
| `answer_submit` | Authoritative answer submission resolves | `level_number`, `part_key`, `ordinal`, `correctness`, `attempt_number`, `result_category`, `elapsed_bucket` |
| `rationale_view` | Controlled rationale UI is shown | `level_number`, `part_key`, `ordinal` |
| `retry_or_continue` | Learner chooses a controlled next action | `action_category=retry/continue/exit`, `ordinal` |
| `quiz_complete` | Existing authoritative quiz completion resolves | `level_number`, `part_key`, `attempt_number`, `result_category`, `elapsed_bucket` |

### 5.4 Plans/paywall

| Event | When accepted | Event-specific allowed dimensions |
|---|---|---|
| `paywall_trigger` | An accepted entitlement rule leads to a plan decision surface | `trigger_context`, `entitlement_state` |
| `plan_impression` | Eligible plan choices become visible | `trigger_context`, `plan_code`, `entitlement_state` |
| `plan_select` | Learner selects a public plan | `trigger_context`, `plan_code`, `entitlement_state` |
| `checkout_result` | Authorized checkout/dummy-provider operation resolves | `plan_code`, `result_category`; never provider/body/payment fields |
| `continue_free` | Learner chooses the available non-purchase path | `trigger_context`, `entitlement_state` |

Plan-selection intent and checkout success are distinct metrics. No live
payment emission is enabled by this contract.

### 5.5 Delayed review

| Event | When accepted | Event-specific allowed dimensions |
|---|---|---|
| `review_impression` | A due-review action is visible | `due_age_bucket`, `level_number`, `part_key` |
| `review_start` | An attempt associated with the review starts | `due_age_bucket`, `attempt_number`, `restore` |
| `review_result` | Attempt resolves | `due_age_bucket`, `correctness`, `review_classification`, `result_category`, `attempt_number` |
| `remediation` | Existing deterministic policy schedules remediation after an incorrect result | `due_age_bucket`, `correctness=false`, `result_category=scheduled` |
| `review_complete` | Deprecated name reserved for compatibility; MUST NOT be emitted in schema v1 | None |
| `review_attempted` | Eligible occurrence is cleared by an attempt | `due_age_bucket`, `correctness`, `review_classification=reviewed_on_time/reviewed_late`, `attempt_number` |

`review_attempted` is the funnel terminal event. The plan's earlier
`review_complete` spelling is intentionally prohibited because D-0290 accepts
attempt/reviewed semantics.

## 6. Metric rules

- Count people by distinct `(organization_id, learner_membership_id)` within a
  declared cohort and window; count journeys by distinct `flow_instance_id`.
- Publish numerator, denominator, window, cohort filters, schema version and
  deduplication rule together.
- Never combine organizations in an operator-facing result. Platform-wide
  aggregates require separate documented authority and minimum-group
  suppression.
- Do not infer learning/mastery from usage events. Demonstrated learning comes
  only from authoritative deterministic records.
- `reviewed_on_time` and `reviewed_late` are calculated from authoritative
  occurrence timestamps/timezone per D-0289, not accepted from a client.
- Correctness is independent of review-attempt clearing per D-0290.
- The supplied 35% onboarding number remains unverified until raw cohort
  provenance and a calculation matching §5.1 are produced.

## 7. Organization scoping and tenancy

- The server derives `organization_id` and `learner_membership_id` from the
  authenticated context and verifies the membership belongs to that
  organization. Client-supplied scope is ignored/rejected.
- Every write, read, aggregation, deletion and experiment assignment is scoped
  by organization. Direct learners remain members of the platform Direct
  organization; this is not global scope.
- Storage constraints and tests must prevent cross-organization association.
- Operator access uses existing organization-bound authorization; this design
  creates no new permission or export.
- Cross-tenant and identifier-tampering tests are mandatory before emission.

## 8. Access and exports

- Event-level rows are not exposed in the learner UI or ordinary operator UI.
- Designated service processes may write only after authentication,
  authorization, validation and sampling/deduplication.
- Operators may receive only organization-scoped aggregate results through a
  separately approved endpoint and existing analytics/read authorization.
- CSV/event-level exports, third-party analytics SDKs, advertising use,
  enrichment, resale and cross-organization benchmarking are prohibited.
- Support access requires a scoped operational need and must use aggregates or
  opaque event IDs; it must not reveal content or credentials.

## 9. Retention

Retention is a maximum, not a promise to keep data:

| Data class | Maximum live retention |
|---|---|
| Event-level content-free rows | 30 days from `received_at` |
| Experiment assignment mapping | Experiment end plus 30 days, never more than 90 days from assignment |
| Organization/day aggregate with no learner or flow identifier | 365 days |
| Backup copies containing event rows | Existing backup cycle, with expiry no later than 30 days after live deletion |

Expired rows are hard-deleted by organization-scoped lifecycle jobs. Retention
must be tested before emission. A future implementation may choose shorter
periods but may not lengthen these periods without accepted review and a
versioned contract update.

## 10. Deletion and lifecycle behavior

- **Learner erasure/withdrawal:** delete event-level rows and experiment
  mappings for the scoped learner membership. Previously computed aggregates
  may remain only when they contain no learner/flow identifier and cannot
  reasonably re-identify the learner; otherwise recompute/delete them.
- **Organization closure/deletion:** delete that organization's event rows,
  mappings and aggregates according to the authoritative organization
  lifecycle. No row is reassigned to a global tenant.
- **Consent withdrawal:** this event contract does not rely on voice consent
  and contains no voice content; withdrawal still stops any independently
  consent-bound event source. Learner erasure controls remain available.
- **Backups:** do not rewrite immutable backup media selectively; prevent
  restoration into live use after deletion and allow the bounded backup cycle
  to expire within 30 days.
- **Deletion evidence:** a future implementation may retain only a
  content-free deletion receipt (operation ID, organization, result, count,
  timestamps) under the existing audit/retention rules.

## 11. Pre-emission acceptance gates

- independent privacy, security and tenancy review recorded in
  `docs/APPROVALS.md` or the applicable review ledger;
- schema/migration/API and threat-model review, including fail-closed property
  allowlists and payload-size limits;
- tests for idempotency, cross-tenant denial, forbidden-property rejection,
  retention expiry, learner deletion, organization deletion and backup restore
  suppression;
- OpenAPI/generated client updates for any endpoint;
- accessibility-safe disclosure/choice if applicable; and
- `make verify`, plus critical journey E2E when emission changes behavior.

## 12. WEB-MEASURE-01 / WEB-EXP-01–03 holdbacks (21 September 2026)

Emission remains unimplemented. These experiments are preregistered against
this contract only; they must not start until independent privacy, security
and tenancy review of the contract is recorded.

| Test | Hypothesis | Status |
|---|---|---|
| H1 | One guest CTA vs dual CTAs | **Holdback.** WEB-UX-01 shipped the single-CTA variant as default. A future experiment may hold back the dual-CTA control. |
| H2 | Ten-step first-use vs fast path | **Blocked.** Requires WEB-UX-06 Option 2 and a D-0293 amendment. |
| H3 | Current Plans vs WEB-UX-05 layout | **Holdback.** WEB-UX-05 shipped the value-first layout as default. A future experiment may hold back the prior layout. |

Metrics stay in the allowlisted envelope (`screen_id`, `step_id`,
`result_category`, viewport bucket). No raw duration, copy, payment payloads
or lesson content.

