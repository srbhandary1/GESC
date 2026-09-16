# UX-AUDIT-00 decision and baseline packet

**Checkpoint:** UX-AUDIT-00 — Metric taxonomy and review decision packet

**Status:** Documentation scope complete on 15 September 2026; UX-07A not started

**Authority:** [APP-0084](APPROVALS.md#app-0084)

**Accepted decisions:** [D-0289](DECISIONS.md) (MUX-001) and
[D-0290](DECISIONS.md) (MUX-002)

**Event contract:** [UX_FUNNEL_EVENT_CONTRACT.md](UX_FUNNEL_EVENT_CONTRACT.md)

This packet records definitions, inventory and implementation obligations. It
does not change application code, emit analytics, alter review intervals, or
start UX-07A or another UI/UX checkpoint.

## 1. Authority and ledger reconciliation

The Accountable Product Owner and Technical Decision Owner accepted MUX-001
and MUX-002 in the instruction recorded as APP-0084. Those are the same roles
recorded for S R Bhandary under APP-0069.

### Git-history result

Repository-wide history searches found D-0297, D-0300 and D-0302 entering
`docs/UI_UX_MASTER_PLAN.md` in commits
`855c2127ed0a4ba2189925e490ccbba750072024` and
`7c1f0747374af724311fd07ffd0643ae0b47cfd2`, both titled `Checkpoint: lesson
practice loop, speech routing, and demo unlock.` No historical revision of
`docs/DECISIONS.md` contains any of those IDs. D-0294, D-0295 and D-0296 were
also progress-log-only references with no decision-ledger entry.

This is an audit-time history statement about the removed legacy references.
After UX-AUDIT-00, the newly free D-0294 identifier was assigned to the
unrelated, properly authorized UX-03A decision under APP-0087; it does not
retroactively validate the former progress-log claim.

The UI/UX companion was therefore corrected rather than retroactively
legitimized: its front matter and roadmap again identify UX-00 and UX-01 as
complete and UX-02 through UX-10 as proposed, and the six unsupported progress
rows were replaced by this reconciliation record. This agrees with the
authoritative decision ledger and `docs/STATUS.md`. It does not deny that code
may have changed in the large historical commits; it records that those
changes never received the cited decision IDs or checkpoint authorization.

## 2. Supplied research claims and repository mapping

| Supplied claim | Exact repository mapping | Source status | Baseline status |
|---|---|---|---|
| “35% profile creation drop-off” | Authenticated learner route `#home`; screen ID `learner_home_onboarding_card`; rendered by `LearnerJourney` as `OnboardingForm` only when `state.home.onboarding.completed` is false | External source was supplied as `[FILL IN: e.g. Play Console funnel / Firebase / manual estimate / unknown]`; no source was filled in and no matching repository event stream exists | **Unverified.** It is a research hypothesis, not a measured baseline and must not be used to claim uplift |
| “Abandonment-heavy at the paywall” | Candidate current Account/plan surface and future eligible plan triggers; exact trigger distribution is not instrumented | External source unknown; no repository paywall funnel events exist | **Unverified** |
| “Interface feels cluttered during quizzes” | Current question/answer/rationale/retry-or-continue sequence | Qualitative user feedback supplied in the audit request; sample, method and date unknown | Directional evidence only; not quantified |

The repository has aggregate operator pilot analytics, but those totals do not
contain the entry denominators, ordered steps, timing buckets or result
categories needed to reproduce any of these funnel claims.

### Reproducible onboarding denominator and success

- **Entry denominator:** unique organization-scoped learner memberships for
  which `onboarding_view` is accepted when the authenticated `#home`
  `learner_home_onboarding_card` becomes visible and interactable while
  `onboarding.completed=false`. Re-renders in the same flow instance do not add
  entrants.
- **Submit event:** `onboarding_submit_result`, accepted only after a submit
  request resolves, with `result_category=accepted`, `validation_rejected`,
  `authorization_rejected`, `conflict`, `transport_failure`, or
  `server_failure`.
- **Success event:** the first `onboarding_submit_result` with
  `result_category=accepted` for that flow instance, backed by the authoritative
  server response that returns onboarding as complete.
- **Reported rate:** successful entrants divided by entrants for the stated
  cohort and window. **Abandonment** is one minus that rate. Same-session and
  within-24-hour activation are separate cuts, not interchangeable
  denominators.

## 3. D-0289 / MUX-001 — on-time delayed review

A due occurrence is **reviewed on time** only when both conditions hold:

1. `due_at <= completed_at` as instants; and
2. `completed_at`, converted into the occurrence's persisted IANA timezone,
   has the same local calendar date as `due_at` converted into that timezone.

If condition 1 holds but the local completion date is later, it is **reviewed
late**. An attempt before `due_at` is not eligible and does not clear the due
occurrence. The occurrence's persisted timezone is authoritative; neither the
server timezone nor the device's current timezone may replace it.

### Required boundary examples

Unless otherwise stated, the occurrence timezone is `Asia/Kolkata` and
`due_at` is 15 September 2026 at 09:00:00 local
(`2026-09-15T03:30:00Z`).

| Completion instant | Local interpretation | Classification | Occurrence effect |
|---|---|---|---|
| `2026-09-15T03:29:59Z` | Due minus 1 second; 08:59:59 local | Not eligible | Remains open |
| `2026-09-15T03:30:00Z` | Exactly due; 09:00:00 local | Reviewed on time | Cleared by the attempt |
| `2026-09-15T18:29:59Z` | 23:59:59 local on due date | Reviewed on time | Cleared by the attempt |
| `2026-09-15T18:30:00Z` | 00:00:00 local on next date | Reviewed late | Cleared by the attempt |

UTC/date-rollover example: an occurrence due at `2026-09-15T23:30:00Z` is due
at 05:00:00 on 16 September in `Asia/Kolkata`. Completion at
`2026-09-16T18:29:59Z` is 23:59:59 on that same local date and is therefore
reviewed on time even though the two UTC calendar dates differ. Completion one
second later, at `2026-09-16T18:30:00Z`, is 00:00:00 on 17 September locally
and is reviewed late.

## 4. D-0290 / MUX-002 — review attempt semantics

An eligible attempt clears one due occurrence for the same organization,
learner and learning objective regardless of correctness. Product metrics and
learner-facing labels describe such a result as **reviewed** or **attempted**,
never as **completed** or **mastered**. Correctness remains a separate boolean
and may update deterministic mastery according to the existing policy.

### Existing implementation confirmation

`DailyMissionAttemptService` already submits in this order inside its
transaction:

1. writes the attempt submission with deterministic `knowledge_state`;
2. calls `projectMastery(correct: $knowledgeCorrect, ...)`;
3. calls `DelayedReviewScheduler::completeDue($attempt, $now)` without a
   correctness condition;
4. reconciles delayed-review occurrences; and
5. calls `scheduleReview(correct: $knowledgeCorrect, ...)`.

For `correct=false`, `projectMastery` writes `not_mastered` when creating the
state. If a row already exists, the `alreadyMastered || ! $correct` guard
returns it without promoting it. `scheduleReview` sets `due_at` to the attempt
instant and uses the policy's incorrect-result `schedule_kind`, so the existing
deterministic remediation review is scheduled immediately. This confirms the
requested behavior already exists. The accepted decision does not demote a
previously mastered objective on a later incorrect formative attempt.

`DelayedReviewScheduler::completeDue` also already enforces the before-due
guard with `where('due_at', '<=', $completedAt)` and clears at most the oldest
eligible open occurrence under organization, learner and objective scope.

### Exact UX-07A implementation obligation

UX-07A, when separately started, must:

1. change `LearnerProgressDashboardService` from its current
   `completed_at <= due_at` classifier to the two-part MUX-001 classifier using
   each occurrence's persisted `timezone`;
2. rename the API/OpenAPI/generated-client fields
   `completed_count`, `completed_on_time_count` and `completed_late_count` to
   reviewed/attempted terminology through an explicit compatibility strategy;
3. replace learner UI/localization copy such as “reviews completed on time”
   and “reviews completed late” with “reviewed on time” and “reviewed late,”
   and audit every review surface so no attempted review is called completed or
   mastered;
4. preserve `completeDue`'s correctness-independent clearing behavior and the
   current deterministic `correct=false` remediation schedule;
5. add unit/integration/API/UI coverage for all MUX-001 boundaries, the
   UTC-versus-Asia/Kolkata rollover, incorrect-attempt clearing, no false
   mastery promotion, deterministic remediation, idempotent replay and
   cross-organization isolation; and
6. update OpenAPI and generated clients with the behavior change.

No item in this list was implemented in UX-AUDIT-00.

## 5. Architecture and authority conclusion

No ADR or master-plan amendment is required for this checkpoint. ADR-0028
already makes delayed review deterministic and advisory but does not define the
on-time calendar window or claim that correctness is required to clear an
eligible occurrence. D-0289 and D-0290 clarify the read-model and UX vocabulary
without changing the deterministic learning authority, schema, tenancy model,
provider boundary or canonical mastery rule.

No DR-0049 was opened. The only product/technical ambiguity in UX-AUDIT-00 was
resolved directly by the named Accountable Product Owner and Technical
Decision Owner in APP-0084. Independent privacy, security and tenancy review
remains an implementation/release gate before event emission; it is not
invented as part of this documentation checkpoint.

## 6. Checkpoint evidence map

| UX-AUDIT-00 item | Evidence |
|---|---|
| Ledger conflict reconciled | §1 and corrected `UI_UX_MASTER_PLAN.md` |
| Profile metric mapped | §2 and event contract §5.1 |
| External sources documented | §2 |
| MUX-001 accepted with boundaries | §3, D-0289 and APP-0084 |
| MUX-002 accepted and code behavior confirmed | §4, D-0290 and APP-0084 |
| Privacy-safe event names/dimensions defined | Event contract §§3–6 |
| Retention/access/org/deletion defined | Event contract §§7–10 |
| ADR/master-amendment need assessed | §5 |
