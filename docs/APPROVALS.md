# Approval evidence ledger

This ledger records durable human approval evidence for checkpoint exits,
specifications, Architecture Decision Records (ADRs) and master-plan
amendments. It records evidence; it does not replace the source-of-truth
hierarchy in `ENTERPRISE_PRODUCT_MASTER_PLAN.md`.

Codex may author recommendations, record evidence and verify implementation.
Codex is never an approver, decider, product owner or specialist reviewer.

## Evidence rules

Every accepted approval record must contain:

1. a stable `APP-NNNN` identifier and status;
2. the decision date and time zone, without inventing a time that was not
   captured;
3. an unambiguous human approver identifier and each approval role exercised;
4. the exact human approval statement;
5. the immediately preceding proposal or decision context needed to interpret
   that statement;
6. the precise scope and artifact revisions accepted or authorized;
7. conditions, limitations and required follow-up reviews;
8. the applicable ADR, specification, issue, pull request or checkpoint;
9. whether the master plan is amended and the corresponding impact-note
   reference when it is; and
10. the recorder identity and repository revision that makes the evidence
    durable.

Approval evidence is append-only in meaning. Correct an accepted entry by
adding a new entry that identifies the record it supersedes; do not silently
change the original decision or its exact statement. A later commit may fill a
previously unavailable repository revision without changing the recorded
decision.

An approver may exercise more than one declared role during development. That
does not constitute independent review. Security-, privacy- and tenancy-
relevant behavior still requires independent specialist review before
production, even when one human held those development roles.

## Approval roles

The role authority is defined by
[ADR-0001](adr/0001-approval-authority-and-evidence.md):

- **Accountable Product Owner** — approves specifications, checkpoint
  acceptance and authorization to begin the next checkpoint.
- **Technical Decision Owner** — accepts ADRs.
- **Security Reviewer** — reviews authentication and security decisions.
- **Privacy Reviewer** — reviews personal-data and privacy decisions.
- **Tenancy Reviewer** — reviews organization ownership and isolation
  decisions.

<a id="app-0001"></a>

## APP-0001 — PR-002 acceptance, PR-003 decisions and authorization

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-07-30 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Approval reference | `Codex task 019fae0d-d5d2-7e32-b2c2-ced466b4b0cb` |
| Evidence source | Human instruction in the referenced Codex task |
| Exact human statement | `Continue` |
| Recorder | Codex, acting only as author and evidence recorder |
| Repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Context and interpretation

Immediately before the statement, Codex had:

1. presented recommended resolutions for DR-0001, DR-0002 and DR-0008;
2. stated that PR-003 implementation was blocked until those decisions and
   PR-002 acceptance were explicitly authorized;
3. supplied explicit approval wording; and
4. repeated that no files would change without approval.

The human then instructed `Continue`. In that bounded context, this is recorded
as acceptance of PR-002, acceptance of the three immediately preceding
recommended decisions and authorization to begin PR-003. It is not approval of
later checkpoints, production deployment or any unpresented scope.

### Accepted artifacts and scope

- [ADR-0001 — Approval authority and durable evidence](adr/0001-approval-authority-and-evidence.md),
  resolving DR-0008.
- [ADR-0002 — Authentication, session, token and recovery policy](adr/0002-authentication-session-token-and-recovery-policy.md),
  resolving DR-0001.
- [ADR-0003 — Resource ownership and organization-context enforcement](adr/0003-resource-ownership-and-organization-context.md),
  resolving DR-0002.
- PR-002 checkpoint acceptance with its recorded limitations.
- Authorization to execute only PR-003 within the canonical checkpoint scope.

### Conditions and limitations

- One human exercised multiple development roles. This is not independent
  specialist review.
- Independent security, privacy and tenancy specialist review is mandatory
  before production. Findings must be resolved or explicitly accepted by the
  accountable human authority and recorded here.
- This approval does not represent unavailable PR-002 environment checks as
  passed.
- The source conversation has no repository-local issue, pull-request URL,
  timestamp-of-day or personal approver name. The durable Codex task reference
  is recorded above; the approver identifier is intentionally role-based and
  does not infer a personal identity.
- Qualified legal counsel must still validate production privacy and
  regulatory behavior as required by the master plan.

<a id="app-0002"></a>

## APP-0002 — PR-003 acceptance and PR-004 authorization

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-07-30 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Role exercised | Accountable Product Owner |
| Approval reference | `Codex task 019fae0d-d5d2-7e32-b2c2-ced466b4b0cb` |
| Evidence source | Human instruction in the referenced Codex task |
| Exact human statement | `Continue` |
| Accepted implementation revision | `457bd0b5e9486cb207882af38a5a65479534d55e` |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Context and interpretation

Immediately before the statement, Codex had:

1. reported PR-003 implementation complete at the accepted implementation
   revision;
2. stated that PR-003 remained pending explicit product-owner acceptance and
   that PR-004 had not started;
3. presented the exact verification results, security/privacy/tenancy
   considerations and known limitations; and
4. supplied the exact bounded PR-004 prompt, which requires explicit PR-003
   acceptance before PR-004 begins.

The human then instructed `Continue`. Under ADR-0001's contextual-approval
rule, this accepts PR-003 with the presented evidence and limitations and
authorizes execution of only PR-004 — Localization and accessible design
system.

### Accepted scope and authorization

- PR-003 checkpoint acceptance at
  `457bd0b5e9486cb207882af38a5a65479534d55e`.
- The unavailable Docker/PostgreSQL 18.4, exact PHP 8.4.23 and remote-CI
  checks remain recorded limitations, not passing results.
- Authorization to execute only PR-004 within the canonical checkpoint scope.

### Conditions and limitations

- This approval does not authorize PR-005, production deployment or any
  later-checkpoint feature.
- It does not waive independent security, privacy, tenancy, accessibility,
  Kannada-language or legal review required before production.
- Production OTP, privileged MFA, Android native secure storage, operational
  alerts and recovery workflows remain outside PR-004.
- The source conversation provides no timestamp-of-day or personal approver
  name; neither is inferred.

<a id="app-0003"></a>

## APP-0003 — PR-004 acceptance and PR-005 decision-phase authorization

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-07-30 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Role exercised | Accountable Product Owner |
| Approval reference | `Codex task 019fae0d-d5d2-7e32-b2c2-ced466b4b0cb` |
| Evidence source | Human instruction in the referenced Codex task |
| Exact human statement | `Continue` |
| Accepted implementation revision | `f9ffbe9bc3069d74ff53ddcb51d8c6972c55e216` |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Context and interpretation

Immediately before the statement, Codex had:

1. reported PR-004 implementation complete at the accepted implementation
   revision;
2. presented the files changed, exact local verification evidence,
   security/privacy/tenancy considerations and known limitations;
3. stated that PR-004 remained pending Accountable Product Owner acceptance;
   and
4. supplied the exact bounded PR-005 prompt, which requires DR-0003 to be
   resolved through an accepted ADR and durable human evidence before schema
   or publication behavior is implemented.

The human then instructed `Continue`. Under ADR-0001's contextual-approval
rule, this accepts PR-004 with the presented evidence and limitations and
authorizes only the decision-resolution phase of PR-005.

The immediately preceding context did not present a concrete ownership,
authority, sharing, withdrawal, cloning or enrollment-pinning policy for
acceptance. The statement therefore does not resolve DR-0003, accept
[proposed ADR-0004](adr/0004-content-ownership-publication-and-curriculum-sharing.md)
or authorize PR-005 schema and product implementation.

### Accepted scope and authorization

- PR-004 checkpoint acceptance at
  `f9ffbe9bc3069d74ff53ddcb51d8c6972c55e216`.
- The recorded Kannada-language, accessibility, environment and remote-CI
  limitations remain limitations rather than passing results.
- Authorization to inspect the repository, draft the DR-0003 policy and
  prepare its human approval request.

### Conditions and limitations

- No curriculum/content migration, seed, API, generated client or product
  behavior is authorized by APP-0003.
- PR-005 implementation remains blocked until ADR-0004 (or a superseding
  concrete ADR) is accepted by the Accountable Product Owner, Technical
  Decision Owner and Tenancy Reviewer, and each appointed academic/language
  reviewer supplies their own acceptance statement or explicitly exercises
  that role as the same identified human. Durable evidence must cite the exact
  committed ADR revision and record qualifications and consolidated roles.
- PR-004's pending Kannada UI catalogue remains a draft. Checkpoint acceptance
  does not turn it into independently reviewed language content.
- This approval does not authorize production deployment, PR-006 or a later
  checkpoint.
- The source conversation provides no timestamp-of-day or personal approver
  name; neither is inferred.

<a id="app-0004"></a>

## APP-0004 — ADR-0004 acceptance and PR-005 implementation authorization

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-07-30 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; Tenancy Reviewer; Rights Confirmer; English Academic Reviewer; Kannada Reviewer |
| Approval reference | `Codex task 019fae0d-d5d2-7e32-b2c2-ced466b4b0cb` |
| Evidence source | Human instruction in the referenced Codex task |
| Accepted ADR revision | `d9d7cba4b2ccc0d9f2e8656e5fd19bab9ce9dc99` |
| English Academic Reviewer qualification/accountable basis | `MA` |
| Kannada Reviewer qualification/accountable basis | `MA` |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
Decision-owner approval:
Approve ADR-0004 at Git commit d9d7cba4b2ccc0d9f2e8656e5fd19bab9ce9dc99.
I, S R Bhandary, act as Accountable Product Owner, Technical
Decision Owner and Tenancy Reviewer for this development decision. I confirm
its role separation, authority-appointment, publication-manifest,
media-rights/successor-release, sharing, withdrawal, suspension, cloning,
immutable publication and enrollment-pinning rules. I remain the rights
confirmer [or: I delegate rights confirmation to stable human identifier].
I appoint S R Bhandary as English Academic Reviewer and
S R Bhandary as Kannada Reviewer.

English Academic Reviewer acceptance:
I,S R Bhandary, accept the English Academic Reviewer role and
ADR-0004 policy at that revision. Qualification:
MA.

Kannada Reviewer acceptance:
I, S R Bhandary accept the Kannada Reviewer role and ADR-0004
policy at that revision. Qualification:
MA.

Record any consolidated roles as non-independent review. Authorize only
PR-005 implementation after every statement and appointment is recorded.
```

### Context and interpretation

The human supplied this statement in response to the committed ADR-0004
proposal at the exact revision above. The affirmative statement `I remain the
rights confirmer` is recorded as the selected authority declaration. The
bracketed delegation alternative is preserved verbatim as part of the evidence
but was not exercised; no rights-confirmation delegate is appointed.

`MA` is recorded exactly as the qualification/accountable basis supplied for
each reviewer role. This ledger does not infer an academic subject,
institution, awarding body or additional credential.

The same identified human explicitly exercised and accepted every listed
role. Those consolidated roles are non-independent review. They are sufficient
development authority for this bounded checkpoint, but do not constitute
independent tenancy, English-academic or Kannada-language specialist review.

### Accepted artifacts, appointments and authorization

- [ADR-0004 — Content ownership, publication authority and curriculum sharing](adr/0004-content-ownership-publication-and-curriculum-sharing.md)
  is accepted at
  `d9d7cba4b2ccc0d9f2e8656e5fd19bab9ce9dc99`, resolving DR-0003.
- `S R Bhandary` remains the accountable rights confirmer; no delegate is
  appointed.
- `S R Bhandary` is appointed and accepts the English Academic Reviewer role
  with the supplied qualification/accountable basis `MA`.
- `S R Bhandary` is appointed and accepts the Kannada Reviewer role with the
  supplied qualification/accountable basis `MA`.
- Authorization extends only to PR-005 — Versioned content and publication
  foundation — within the accepted ADR and canonical master-plan scope.

### Conditions and limitations

- Every consolidated role in this record is non-independent. Independent
  tenancy, English-academic and Kannada-language review remains mandatory
  before production. Existing security, privacy, accessibility and legal
  production gates also remain in force.
- The human evidence appoints the accountable reviewers. Exercising those
  roles in product data still requires the applicable active organization
  membership, permission and scoped runtime authority appointment introduced
  by PR-005.
- The two `MA` statements are recorded without inferred specialization. An
  independent pre-production reviewer must determine whether the evidence is
  adequate for the content and release being reviewed.
- This approval authorizes implementation and verification only. It does not
  approve any future curriculum publication, waive creator/reviewer
  separation, authorize production, accept PR-005, or authorize PR-006.
- No master-plan amendment is made.

<a id="app-0005"></a>

## APP-0005 — PR-005 acceptance and PR-006 authorization

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-07-30 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Approval reference | `Codex task 019fae0d-d5d2-7e32-b2c2-ced466b4b0cb` |
| Evidence source | Human instruction in the referenced Codex task |
| Exact human statement | `I, S R Bhandary, acting as Accountable Product Owner, accept PR-005 at commit daeee1df7a7e4d31427001aedece76d8121413d6 with its recorded limitations and authorize only PR-006 — Deterministic daily-speaking slice.` |
| Accepted implementation revision | `daeee1df7a7e4d31427001aedece76d8121413d6` |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Context and interpretation

Immediately before the statement, Codex:

1. verified that the repository remained at the exact PR-005 implementation
   revision;
2. stated that a generic continuation instruction could not satisfy the
   explicit checkpoint gate;
3. repeated that PR-006 had not started; and
4. supplied the exact acceptance wording tying approval to the implementation
   revision, recorded limitations and PR-006-only authorization.

The human then supplied that exact statement. This accepts PR-005 with the
limitations recorded in `docs/STATUS.md`, `docs/QUALITY_REVIEW.md` and
`docs/PR-005_CONTENT_PUBLICATION_VERIFICATION_SCOPE.md`, and authorizes
execution of only PR-006 within the canonical master-plan scope.

### Accepted scope and authorization

- PR-005 checkpoint acceptance at
  `daeee1df7a7e4d31427001aedece76d8121413d6`.
- Authorization to execute only PR-006 — Deterministic daily-speaking slice.
- The accepted PR-005 governance, tenancy, publication, sharing, emergency,
  enrollment-pinning and recovery boundaries remain authoritative.

### Conditions and limitations

- Every PR-005 limitation remains a limitation rather than a passing result.
- PR-006 must preserve immutable publication and enrollment pins,
  organization scoping, deterministic authoritative learning records and the
  no-AI completion path.
- This approval does not authorize offline bundle/outbox implementation from
  PR-007, AI or speech-provider orchestration, production deployment or any
  later checkpoint.
- Independent tenancy, security, privacy, accessibility, English-academic,
  Kannada-language and legal production gates remain in force.
- No master-plan amendment is made.

<a id="app-0006"></a>

## APP-0006 — Phase 1 multilingual UI expansion

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-07-30 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner |
| Approval reference | `Codex task 019fb263-fe9a-77f0-a1db-e3d563707715` |
| Evidence source | Human instruction in the referenced Codex task |
| Accepted ADR | [ADR-0005](adr/0005-phase-1-multilingual-ui-language-packs.md) |
| Impact note | [Phase 1 multilingual UI expansion scope](PHASE1_MULTILINGUAL_UI_EXPANSION_SCOPE.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | Yes — master plan §§1.1, 5.4, 9, 26 and 31 |

### Exact human statement

```text
I want Malayalam (ml-IN), Tamil (ta-IN), Telugu (te-IN), and Hindi (hi-IN) for the Phase 3 language-pack rollout in phase 1.
English remains the learning language; curriculum, speech recognition, model audio, and AI explanations remain unchanged. Translations will be explicitly marked as implementation drafts pending independent human review, and production will fall back to English until approved.
```

### Context and interpretation

Immediately before the statement, Codex:

1. identified that the canonical plan placed these language packs in Phase 3;
2. explained the distinction between UI guidance, governed curriculum and
   speech/audio/AI support;
3. proposed a bounded UI-only Phase 1 scope with English retained as the
   learning and fallback language;
4. stated that draft translations would remain pending independent review and
   production would fall back to English; and
5. stated that implementation required an ADR, master-plan amendment,
   catalogue governance, fonts, tests and documentation.

The human selected that bounded scope and repeated its exclusions. This is
recorded as development approval for ADR-0005 and its same-change master-plan
amendment. It is not linguistic approval of any translation.

### Accepted scope and authorization

- Add `ml-IN`, `ta-IN`, `te-IN` and `hi-IN` to the Phase 1 interface alongside
  `kn-IN` and `en-IN`.
- Preserve `kn-IN` as the default requested UI locale and `en-IN` as the
  source and fallback.
- Record every Codex-authored translated catalogue as
  `pending-independent-review`.
- Permit draft rendering only in development, tests or an explicit review
  build with a visible notice.
- Require ordinary production to fall back to English until exact catalogue
  revisions have independent human approval.
- Add script fonts and automated UI, accessibility and governance coverage.
- Amend only the master-plan sections named in ADR-0005.

### Conditions and limitations

- English remains the learning language.
- Curriculum publication eligibility, Kannada explanation requirements,
  speech recognition, speech evaluation, model audio and AI explanations do
  not expand.
- No translated catalogue is approved by this record. Malayalam, Tamil,
  Telugu, Hindi and the existing Kannada draft each require an accountable
  translator and separate independent language reviewer before production.
- UI support must not be represented as complete curriculum or voice support.
- Manual screen-reader, browser zoom, terminology, wrapping and
  representative Android TalkBack review remain production gates.
- This approval does not accept PR-006, authorize PR-007 or production, or
  waive any existing security, privacy, tenancy, accessibility, content or
  legal gate.

<a id="app-0007"></a>

## APP-0007 — PR-006 acceptance and PR-007 decision-resolution authorization

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-07-30 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Approval reference | `Codex task 019fb263-fe9a-77f0-a1db-e3d563707715` |
| Evidence source | Human instruction in the referenced Codex task |
| Accepted implementation revision | `598ad387e65b94707564dce1e199c97deb411d82` |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
I accept PR-006 at commit 598ad38 with its recorded limitations and authorize only PR-007 decision-resolution work. Do not implement PR-007 until DR-0004 is approved.
```

### Context and interpretation

Immediately before the statement, Codex:

1. identified PR-006 as implemented and verified but unaccepted;
2. identified `598ad38` as its exact implementation commit;
3. stated that PR-007 implementation remained blocked by both checkpoint
   acceptance and DR-0004;
4. requested acceptance with the recorded PR-006 limitations; and
5. limited the next authorization to DR-0004 decision-resolution work.

The human supplied the exact requested statement. Git resolves the abbreviated
revision to `598ad387e65b94707564dce1e199c97deb411d82`.

### Accepted scope and authorization

- Accept PR-006 at the exact implementation revision above, with all
  limitations recorded in `docs/STATUS.md`, `docs/QUALITY_REVIEW.md` and
  `docs/PR-006_DETERMINISTIC_DAILY_SPEAKING_VERIFICATION_SCOPE.md`.
- Authorize only preparation and review of the PR-007 offline retention,
  shared-device isolation, logout purge and conflict policy needed to resolve
  DR-0004.
- Permit a proposed ADR, impact note and durable decision evidence.

### Conditions and limitations

- PR-007 product implementation remains prohibited until DR-0004 has an
  accepted ADR with the Technical Decision Owner, Security Reviewer, Privacy
  Reviewer and Tenancy Reviewer roles explicitly exercised.
- This record does not itself resolve DR-0004.
- No service worker, Cache Storage, IndexedDB, sync API, database migration or
  offline product behavior is authorized by this record.
- PR-008, recording upload, offline AI, provider processing, production
  deployment and later checkpoints remain unauthorized.
- Every recorded PR-006 and multilingual-interface limitation remains in
  force.
- Independent security, privacy and tenancy specialist review remains
  mandatory before production.

<a id="app-0008"></a>

## APP-0008 — ADR-0006 acceptance and PR-007 implementation authorization

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-07-30 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Approval reference | `Codex task 019fb263-fe9a-77f0-a1db-e3d563707715` |
| Evidence source | Human instruction in the referenced Codex task |
| Accepted ADR | [ADR-0006](adr/0006-offline-retention-isolation-and-conflict-policy.md) |
| Approved proposal SHA-256 | `242a3cff4a5822ebb986515b670ed7c5ed0d243aa86622d80a3254892d348fbb` |
| Impact note | [PR-007 offline policy proposal](PR-007_OFFLINE_POLICY_PROPOSAL.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
I, S R Bhandary, acting as Technical Decision Owner, Security Reviewer, Privacy Reviewer, and Tenancy Reviewer, approve ADR-0006 as written at SHA-256 242a3cff4a5822ebb986515b670ed7c5ed0d243aa86622d80a3254892d348fbb, accept its explicit trade-offs, and authorize only PR-007 implementation. This consolidated development approval is not independent production review and does not authorize PR-008 or production.
```

### Context and interpretation

The approved hash identifies the complete proposed ADR-0006 text reviewed
immediately before this statement. That proposal:

1. classified public shell, protected mission and protected learner-outbox
   data;
2. required explicit personal-device opt-in and prohibited protected
   persistence on shared devices;
3. defined encrypted opaque partitions, authorization leases, retention and
   key-first purge;
4. defined same-account-only atomic idempotent replay with PostgreSQL
   authority and no last-write-wins;
5. excluded credentials, raw audio, AI/provider data and later checkpoints;
   and
6. stated the browser, revocation, emergency-change and timed-deletion
   trade-offs.

### Accepted scope and authorization

- Accept ADR-0006 and resolve DR-0004.
- Implement only PR-007 — Offline bundle and idempotent outbox.
- Add the versioned mission bundle, service-worker shell cache, encrypted
  IndexedDB partition, visible sync state, atomic idempotent server command and
  reconnect/cache-migration coverage required by the master plan and ADR.
- Update OpenAPI/generated clients, tests and checkpoint documentation with
  any public contract change.

### Conditions and limitations

- Consolidated development roles are not independent security, privacy or
  tenancy specialist review.
- Production remains blocked pending independent specialist and applicable
  legal review.
- Raw learner audio remains volatile and local-only. PR-007 may not upload,
  persist, cache or synchronize it.
- Credentials remain outside browser storage.
- PR-008, AI/provider orchestration, recording retention/upload and production
  deployment remain unauthorized.
- PR-007 does not become accepted merely because implementation or tests pass;
  checkpoint acceptance requires a later exact-commit Accountable Product
  Owner statement.

<a id="app-0009"></a>

## APP-0009 — PR-007 acceptance without later-checkpoint authorization

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-07-30 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Approval reference | `Codex task 019fb263-fe9a-77f0-a1db-e3d563707715` |
| Evidence source | Human instruction in the referenced Codex task |
| Accepted implementation commit | `e0e2f1b90e0eb4ecf680f5243b57ec0731c32648` |
| Verification evidence | [PR-007 offline bundle verification scope](PR-007_OFFLINE_BUNDLE_VERIFICATION_SCOPE.md) and [quality review](QUALITY_REVIEW.md#pr-007--offline-bundle-and-idempotent-outbox) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
I accept PR-007 at commit e0e2f1b90e0eb4ecf680f5243b57ec0731c32648 with its recorded limitations. This does not authorize PR-008 or production.
```

### Context and interpretation

- Accept the exact PR-007 implementation commit and its recorded verification
  evidence and limitations.
- Close the PR-007 checkpoint without changing ADR-0006 or its accepted
  security, privacy, tenancy, retention and conflict policy.
- Do not authorize PR-008, production deployment or any later checkpoint.
- Do not approve draft interface translations, curriculum, model audio,
  provider processing or recording upload/persistence.

### Conditions and limitations

- Every limitation in the PR-007 verification scope and quality review remains
  in force.
- Consolidated development review under APP-0008 is not independent
  production review.
- Independent security, privacy, tenancy, accessibility, language/content and
  release review remains mandatory before production.
- Malayalam, Tamil, Telugu, Hindi and Kannada interface catalogues remain
  implementation drafts pending independent human review; ordinary production
  continues to fall back visibly to English.
- No implementation work beyond PR-007 is authorized by this record.
