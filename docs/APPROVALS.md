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

<a id="app-0010"></a>

## APP-0010 — PR-008 deterministic fake-provider implementation authorization

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-07-30 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Approval reference | `Codex task 019fb263-fe9a-77f0-a1db-e3d563707715` |
| Evidence source | Human instruction in the referenced Codex task |
| Accepted baseline | PR-007 implementation `e0e2f1b90e0eb4ecf680f5243b57ec0731c32648`, accepted under APP-0009 |
| Authorized scope | [PR-008 conversation fake-provider verification scope](PR-008_CONVERSATION_FAKE_PROVIDER_VERIFICATION_SCOPE.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
I authorize only PR-008 — Conversation domain with deterministic fake providers. This includes personas, scenarios, sessions and turns; project-owned AI/speech provider ports; fake STT, LLM and TTS adapters; a deterministic turn state machine; event/status streaming; and cost/latency record skeletons. It does not authorize real provider calls, audio upload or persistence, PR-009, production, or AI authority over deterministic learning records.
```

### Accepted scope and authorization

- Implement only PR-008 — Conversation domain with deterministic fake
  providers.
- Add organization-scoped, versioned prototype personas and scenarios,
  learner-owned sessions and turns, deterministic status events and
  privacy-safe cost/latency records.
- Add project-owned STT, conversation-model and TTS ports plus deterministic
  local/testing fake adapters.
- Deliver one complete Friend turn through the authenticated API and learner
  interface, with deterministic replay and retry coverage.
- Update OpenAPI, generated clients, automated tests and checkpoint evidence.

### Conditions and limitations

- Fake providers are enabled only in local and testing environments. Production
  fails closed.
- No provider SDK, network provider call, real model, prompt orchestration or
  provider credential is authorized.
- No audio bytes, recording upload, generated audio persistence or object
  storage is authorized.
- Prototype utterance and reply text is processed in request memory and
  returned to that request only. Persistent records contain digests and safe
  status/usage metadata, not conversation content.
- AI output cannot write marks, mastery, progression, attendance,
  entitlements, enrollment or other authoritative learning records.
- PR-009, production and every later checkpoint remain unauthorized.

<a id="app-0011"></a>

## APP-0011 — ADR-0007 recording-policy acceptance

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-07-31 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Approval reference | `Codex task 019fb263-fe9a-77f0-a1db-e3d563707715` |
| Evidence source | Human instruction in the referenced Codex task |
| Accepted ADR | [ADR-0007](adr/0007-recording-consent-retention-provider-processing-and-deletion-policy.md) |
| Approved proposal SHA-256 | `e831e09092256d18804e5c6c7292a432ecfb71ac7d52544d16fde8d751a12ff9` |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
I, S R Bhandary, acting as Technical Decision Owner, Security Reviewer, Privacy Reviewer, and Tenancy Reviewer, approve ADR-0007 as written at SHA-256 e831e09092256d18804e5c6c7292a432ecfb71ac7d52544d16fde8d751a12ff9 and accept its explicit trade-offs. This consolidated development approval is not independent production review and does not authorize PR-009 implementation or production.
```

### Context and interpretation

The approved hash identifies the complete proposed ADR-0007 text committed at
`98c46584b298cbf18623b140dbe5dc3dbc7737e6` and presented immediately before
the human statement. That proposal:

1. separated voice-upload, provider-processing and optional transcript-history
   consent purposes;
2. defined per-turn submission, private quarantine, strict media validation
   and fail-closed adult eligibility;
3. set short raw-audio, transcript and generated-media retention maximums;
4. required withdrawal, tombstones, provider deletion and reconciliation;
5. minimized provider requests and prohibited training, human review and
   secondary use in PR-009;
6. required organization plus exact-membership ownership and cross-tenant
   protection; and
7. preserved learner transcript correction without granting automated speech
   authority over deterministic learning records.

### Accepted scope

- Accept ADR-0007 and resolve DR-0005.
- Establish the policy prerequisite for a separately authorized PR-009
  specification and implementation.
- Preserve the master plan and every existing checkpoint-acceptance gate.

### Conditions and limitations

- This approval does not accept PR-008 at its implementation commit.
- This approval does not authorize PR-009 implementation, a real provider,
  learner-audio upload or persistence, provider credentials, production or a
  later checkpoint.
- Consolidated development roles are not independent security, privacy or
  tenancy specialist review.
- Qualified legal review, independent specialist review, approved
  notices/translations, provider due diligence and explicit production release
  authorization remain mandatory before production.
- No master-plan amendment is made.

<a id="app-0012"></a>

## APP-0012 — PR-008 acceptance and PR-009 implementation authorization

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-07-31 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Approval reference | `Codex task 019fb263-fe9a-77f0-a1db-e3d563707715` |
| Evidence source | Human instruction in the referenced Codex task |
| Accepted implementation commit | `11f7b597ad2db3b8cf65a1394c1171a8280045d1` |
| Governing policy | [ADR-0007](adr/0007-recording-consent-retention-provider-processing-and-deletion-policy.md) under APP-0011 |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
I, S R Bhandary, acting as Accountable Product Owner, accept PR-008 at commit 11f7b597ad2db3b8cf65a1394c1171a8280045d1 with its recorded limitations and authorize only PR-009 implementation within accepted ADR-0007. This does not authorize production or later checkpoints.
```

### Context and interpretation

Immediately before this statement, Codex:

1. showed the authenticated PR-008 Practice interface using synthetic data;
2. verified the web build and four learner/conversation Chromium journeys;
3. identified that PR-008 exact-commit acceptance remained pending;
4. identified secure recording, real speech adapters and transcript recovery
   as the PR-009 boundary; and
5. repeated that production and later checkpoints remained separately gated.

### Accepted scope and authorization

- Accept PR-008 at
  `11f7b597ad2db3b8cf65a1394c1171a8280045d1` with every recorded limitation.
- Implement only master-plan §26 PR-009 — Real speech adapters and transcript
  recovery — within accepted ADR-0007.
- Add secure recording upload/validation, consent/withdrawal, lifecycle and
  deletion controls, transcript confidence/review/recovery, text/TTS fallback,
  privacy-safe telemetry, provider-owned ports/adapters and required
  contracts/tests.

### Conditions and limitations

- Provider selection remains server owned and requires the review evidence in
  ADR-0007 before any live benchmark or provider call.
- Missing provider review, credentials, encryption, validation, deletion,
  organization context or consent must fail closed.
- Synthetic, licensed or separately consented evaluation material is required
  for any authorized non-production benchmark; real learner production data is
  prohibited.
- This record does not approve a provider, production, PR-010 or any later
  checkpoint.
- Independent security, privacy, tenancy, accessibility, language/content,
  legal, vendor and production-release reviews remain mandatory.
- No master-plan amendment is made.

<a id="app-0013"></a>

## APP-0013 — ADR-0008 and PR-009 benchmark-plan approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-07-31 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised | Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Approval reference | `Codex task 019fb263-fe9a-77f0-a1db-e3d563707715` |
| Evidence source | Human instruction in the referenced Codex task |
| Approved ADR | [ADR-0008](adr/0008-azure-speech-development-provider-and-benchmark-policy.md) |
| Approved ADR proposal SHA-256 | `b1c778753ab9b0b6b7b8202f8d106a8ee302485455189afce76baac361841b51` |
| Approved benchmark plan | [PR-009 speech provider benchmark plan](PR-009_SPEECH_PROVIDER_BENCHMARK_PLAN.md) |
| Approved benchmark-plan SHA-256 | `817f430aabd93eb06d9ecd1b980994862e1ddfe19f3285fe184ba508204581ab` |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
I, S R Bhandary, acting as Technical Decision Owner, Security Reviewer, Privacy Reviewer, and Tenancy Reviewer, approve ADR-0008 as written at SHA-256 b1c778753ab9b0b6b7b8202f8d106a8ee302485455189afce76baac361841b51 and the PR-009 benchmark plan at SHA-256 817f430aabd93eb06d9ecd1b980994862e1ddfe19f3285fe184ba508204581ab, accept the provider-managed STT model-rotation and circular synthetic-benchmark limitations, and authorize only the remaining PR-009 Azure Speech adapter and locked synthetic-benchmark implementation. No credential or live synthetic request is authorized until exact subscription/DPA/subprocessor evidence and separate provider-spend approval are recorded. This consolidated development approval does not approve benchmark success, accept PR-009, authorize learner data, PR-010, or production.
```

### Accepted scope and authorization

- Accept the exact ADR-0008 proposal and linked benchmark plan at their
  recorded SHA-256 values.
- Resolve DR-0011 for the PR-009 provider selection and benchmark policy.
- Implement only the Azure Speech STT/TTS adapters and locked synthetic
  benchmark within accepted ADR-0007 and ADR-0008.
- Accept the provider-managed STT model-rotation and circular
  synthetic-benchmark limitations as development trade-offs.

### Conditions and limitations

- No credential or live synthetic provider request is authorized until exact
  subscription, contracting-entity, agreement, DPA and subprocessor evidence
  and separate provider-spend approval are recorded.
- This approval does not represent a benchmark pass or PR-009 acceptance.
- Real learner data, PR-010, production and all later checkpoints remain
  unauthorized.
- Consolidated development roles are not independent security, privacy or
  tenancy review.
- Qualified legal review and independent security, privacy, tenancy,
  accessibility, language/content, vendor and release review remain mandatory
  before production.
- No master-plan amendment is made.

<a id="app-0014"></a>

## APP-0014 — ADR-0009 and DR-0012 implementation approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-07-31 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised | Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Approval reference | `Codex task 019fb263-fe9a-77f0-a1db-e3d563707715` |
| Evidence source | Human instruction in the referenced Codex task |
| Approved ADR | [ADR-0009](adr/0009-synthetic-tts-listener-fixture-review-workflow.md) |
| Approved ADR proposal SHA-256 | `a0848d462f9363cc2fb9a2babd2f50fd8ae7ad4ed99ee2cd3abaaeb828413e0f` |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
I, S R Bhandary, acting as Technical Decision Owner, Security Reviewer, Privacy Reviewer, and Tenancy Reviewer, approve ADR-0009 as written at SHA-256 a0848d462f9363cc2fb9a2babd2f50fd8ae7ad4ed99ee2cd3abaaeb828413e0f, accept its encrypted two-phase fixture bundle, loopback browser-memory review, and 24-hour expiry/deletion trade-offs, and authorize only DR-0012 implementation within PR-009. This does not authorize credentials, provider spend, live requests, benchmark success, PR-009 acceptance, learner data, PR-010, or production.
```

### Accepted scope and authorization

- Accept the exact ADR-0009 proposal at its recorded SHA-256.
- Resolve DR-0012 for the synthetic TTS listener-fixture workflow.
- Implement only the encrypted prepare/review/complete/purge state machine,
  loopback browser-memory review utility, fixed 24-hour expiry and
  content-free deletion evidence within PR-009.
- Preserve the locked 544-operation and 1,088-attempt caps by reusing the 96
  reviewed prepared outputs.

### Conditions and limitations

- No Azure credential, provider spend or live synthetic request is authorized.
- This approval does not represent benchmark success or PR-009 acceptance.
- Learner data, PR-010, production and all later checkpoints remain
  unauthorized.
- Consolidated development roles are not independent security, privacy or
  tenancy review.
- Qualified legal review and independent security, privacy, tenancy,
  accessibility, language/content, vendor and release review remain mandatory
  before production.
- No master-plan amendment is made.

<a id="app-0015"></a>

## APP-0015 — Blanket demo authorization and ₹1,000 provider-spend cap

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-05 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
You have a blanket approval.
A demo budget is Rs.1000.
```

### Accepted scope and authorization

- Authorize continued demo/non-production implementation of the Phase 1 learner
  shell and related synthetic product surfaces under D-0081/D-0082.
- Authorize provider spend for the locked PR-009 synthetic Azure Speech
  benchmark up to a hard cap of **₹1,000** (INR), once the remaining fail-closed
  evidence and credential gates are satisfied.
- Authorize treating APO-accepted synthetic curriculum and in-demo attendance /
  notice / trainer workflows as **presentation authority inside the disclosed
  demo only**, not as independently reviewed production curriculum or
  institutional source of record.

### Conditions and limitations

- Production deployment, Play release, and independent specialist reviews remain
  required before any production claim.
- Live Azure requests still require a real `centralindia` Speech credential,
  vendor/DPA/subprocessor evidence, and the fail-closed benchmark environment;
  this approval does not invent those artifacts.
- Real learner personal data must not be used under this demo budget.
- Consolidated APO approval is not independent security, privacy, tenancy,
  legal or language/content review.
- No master-plan amendment is made.

<a id="app-0016"></a>

## APP-0016 — Blanket production-path authorization

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-06 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
You have blanket authorization to complete this till production.
```

### Accepted scope and authorization

- Authorize continued **sequential** implementation toward Phase 1 production
  readiness under the master plan and accepted ADRs.
- Authorize further provider spend for the locked PR-009 synthetic Azure Speech
  benchmark within the existing hard cap of **₹1,000** INR unless a higher
  spend approval is later recorded.
- Authorize the next bounded checkpoint: PR-009 WER/completeness remediation
  (or an explicit fail-closed production speech hold) under D-0084.

### Conditions and limitations

- This does **not** accept PR-009 while locked quality/completeness gates fail.
- This does **not** authorize skipping independent legal, security, privacy,
  tenancy, accessibility, language/content, vendor or release review.
- This does **not** authorize immediate Google Play cutover, public production
  deployment, or real learner personal data use.
- Locked benchmark thresholds must not be silently loosened after results are
  seen; any threshold/corpus change requires a versioned plan amendment.
- Fake-passing benchmarks and invented vendor/legal evidence remain prohibited.
- No master-plan amendment is made.

<a id="app-0017"></a>

## APP-0017 — PR-009 acceptance and PR-010 implementation authorization

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-06 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Accepted PR-009 live evidence | Safe report for run `e1dc9d3c8daff9e33c20bdd00d2dd4d0` (`passed: true`) under APP-0015/APP-0016 spend authority |
| Repository baseline at authoring | `09514b8259fcb7b1c3f5d59ec6071c8e66d03fb9` plus the uncommitted PR-009 remediation and demo-path working tree that produced that pass |
| Governing policy | ADR-0007, ADR-0008, ADR-0009; D-0083–D-0085 |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
I formally accept PR-009 and authorize PR-010.
```

### Context and interpretation

Immediately before this statement:

1. the locked PR-009 synthetic Azure Speech benchmark completed with
   `status: passed` for run `e1dc9d3c8daff9e33c20bdd00d2dd4d0`;
2. D-0085 remediation (WER digit/spoken equivalence and duration over-claim
   check) was in the working tree that produced that pass;
3. APP-0016 authorized sequential production-path work without claiming Play
   cutover; and
4. ordinary application speech bindings remained fail-closed pending this
   acceptance.

### Accepted scope and authorization

- Accept PR-009 — Real speech adapters and transcript recovery — with every
  recorded limitation in STATUS, QUALITY_REVIEW, ADR-0007/0008/0009 and the
  locked benchmark plan.
- Authorize only master-plan §26 **PR-010 — AI orchestration, correction and
  Tutor beta**.
- PR-010 may deliver context allowlist, versioned prompts, structured output
  validators, conversation reply plus priority correction, Kannada
  explanation, Gentle/Tutor/Fluency modes, safety/report/fallback, and retry /
  error-pattern update — without allowing AI to mutate authoritative learning
  records.

### Conditions and limitations

- Production deployment, Google Play release and real learner production data
  remain unauthorized.
- PR-011 and all later checkpoints remain unauthorized.
- Independent legal, security, privacy, tenancy, accessibility,
  language/content, vendor and release reviews remain mandatory before
  production.
- Azure Speech remains bounded by ADR-0008/0009 fail-closed gates; ordinary
  app wiring of live speech still requires explicit configuration and must not
  bypass organization scoping, consent or retention rules.
- Development listeners and APO demo approvals are not independent language
  or specialist production review.
- No master-plan amendment is made.

<a id="app-0018"></a>

## APP-0018 — PR-010 acceptance with recorded limitations

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-06 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Exact implementation commit accepted | `13eb5ac56f04402a2571346e9fd8e24f1a05ea80` |
| Pull request | https://github.com/srbhandary1/GESC/pull/1 |
| Governing authorization | [APP-0017](APPROVALS.md#app-0017); D-0087 |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
APO accept PR-010 with its recorded limitations
```

### Context and interpretation

Immediately before this statement:

1. PR-010 was implemented under APP-0017 on commit
   `13eb5ac56f04402a2571346e9fd8e24f1a05ea80`;
2. STATUS recorded PR-010 as implemented and awaiting Accountable Product
   Owner acceptance;
3. QUALITY_REVIEW recorded schema/safety, learning-isolation, OpenAPI and UI
   correction-card evidence with host `pdo_pgsql` and full `make verify`
   limitations; and
4. the open pull request was https://github.com/srbhandary1/GESC/pull/1.

### Accepted scope

- Accept PR-010 — AI orchestration, correction and Tutor beta — at exact
  implementation commit `13eb5ac56f04402a2571346e9fd8e24f1a05ea80`, with every
  recorded limitation in STATUS, QUALITY_REVIEW, D-0087 and APP-0017.

### Conditions and limitations

- This acceptance does **not** authorize PR-011 or any later checkpoint.
- Real LLM / conversation-model providers remain unauthorized; the accepted
  slice uses deterministic fake adapters with fail-closed production bindings.
- Production deployment, Google Play release and real learner production data
  remain unauthorized.
- Independent legal, security, privacy, tenancy, accessibility,
  language/content, vendor and release reviews remain mandatory before
  production.
- AI remains advisory: it must not mutate authoritative learning records.
- Full remote CI / `make verify` on the host toolchain remains a follow-up
  verification gate and is not waived by this acceptance.
- No master-plan amendment is made.

<a id="app-0019"></a>

## APP-0019 — PR-011 implementation authorization

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-06 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Prior acceptance | [APP-0018](APPROVALS.md#app-0018) (PR-010 at `13eb5ac56f04402a2571346e9fd8e24f1a05ea80`) |
| Pull request context | https://github.com/srbhandary1/GESC/pull/1 |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
APO accept PR-011.
```

### Context and interpretation

Immediately before this statement:

1. PR-010 had been accepted under APP-0018 with recorded limitations;
2. STATUS recorded that PR-011 remained unauthorized until a separate explicit
   Accountable Product Owner authorization; and
3. PR-011 itself was not yet implemented.

Interpretation: because PR-011 has no accepted implementation artifact, this
statement authorizes **beginning implementation** of master-plan §26 PR-011
only. It is not acceptance of completed PR-011 deliverables.

### Authorized scope

- Authorize only master-plan §26 **PR-011 — Evaluation harness and memory
  controls**.
- PR-011 may deliver: locked AI/speech evaluation suite; human-review
  export/import; model/prompt comparison report; AI cost ledger;
  learner-visible memory list/edit/delete; memory policy and audit.
- Acceptance criteria remain those in the master plan: prompt/model changes
  produce a comparable evaluation report; no memory fact is hidden from the
  learner.

### Conditions and limitations

- This does **not** accept completed PR-011 work; that requires a later exact-
  artifact APO acceptance.
- PR-012 and all later checkpoints remain unauthorized.
- Real LLM / conversation-model providers remain unauthorized unless a later
  explicit decision selects and gates them within PR-011 or a later authorized
  checkpoint.
- Production deployment, Google Play release and real learner production data
  remain unauthorized.
- Independent legal, security, privacy, tenancy, accessibility,
  language/content, vendor and release reviews remain mandatory before
  production.
- AI remains advisory and must not mutate authoritative learning records.
- No master-plan amendment is made.

<a id="app-0020"></a>

## APP-0020 — PR-011 acceptance with recorded limitations

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-06 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Exact implementation commit accepted | `220628f4d4c69284af0b1f2534e8902f3b272088` |
| Governing authorization | [APP-0019](APPROVALS.md#app-0019); D-0090; D-0091 |
| Acceptance package | [PR-011_APO_ACCEPTANCE_PACKAGE.md](PR-011_APO_ACCEPTANCE_PACKAGE.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
APO accept PR-011 at 220628f4d4c69284af0b1f2534e8902f3b272088 with its recorded limitations
```

### Context and interpretation

Immediately before this statement:

1. PR-011 was implemented under APP-0019 and closed on commit
   `220628f4d4c69284af0b1f2534e8902f3b272088` (memory list/edit/delete UI,
   fail-closed evaluation import review, verification and acceptance
   packages, plus concurrent client-demo presentation work on the same tip);
2. STATUS recorded PR-011 as awaiting Accountable Product Owner exact-commit
   acceptance;
3. QUALITY_REVIEW recorded Docker PostgreSQL, OpenAPI, locale, vitest and
   `ai:evaluate` evidence with host `pdo_pgsql` / full `make verify`
   limitations; and
4. the Accountable Product Owner instructed: commit the branch, then accept
   PR-011 at that SHA.

### Accepted scope

- Accept PR-011 — Evaluation harness and memory controls — at exact
  implementation commit `220628f4d4c69284af0b1f2534e8902f3b272088`, with every
  recorded limitation in
  [PR-011_APO_ACCEPTANCE_PACKAGE.md](PR-011_APO_ACCEPTANCE_PACKAGE.md),
  STATUS, QUALITY_REVIEW, D-0090 and D-0091.

### Conditions and limitations

- This acceptance does **not** authorize PR-012 implementation. It only
  permits consideration of a separate PR-012 APP using the drafted
  [PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md](PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md).
- Real LLM / conversation-model providers remain unauthorized; the accepted
  slice uses deterministic fake adapters with fail-closed production bindings.
- Production deployment, Google Play release and real learner production data
  remain unauthorized.
- Independent legal, security, privacy, tenancy, accessibility,
  language/content, vendor and release reviews remain mandatory before
  production.
- AI remains advisory: it must not mutate authoritative learning records.
- Full remote CI / host `make verify` remains a follow-up verification gate
  and is not waived by this acceptance.
- Demo-local Azure TTS (D-0092) is presentation-only and does not amend
  ADR-0008 ordinary application bindings.
- No master-plan amendment is made.

<a id="app-0021"></a>

## APP-0021 — Confidence Conversation enterprise slice authorization

| Field | Evidence |
|---|---|
| Status | Accepted (begin-implementation) |
| Decision date | 2026-08-07 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Governing plan | [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) Steps A1–A7 |
| Learning-flow design | [STUDENT_AI_CONVERSATION_LEARNING_FLOW.md](STUDENT_AI_CONVERSATION_LEARNING_FLOW.md) |
| Prior acceptance | [APP-0020](APPROVALS.md#app-0020) (PR-011 at `220628f4d4c69284af0b1f2534e8902f3b272088`) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | Sequencing only: Confidence Conversation enterprise slice is inserted **before** PR-012 Role-play; no change to Phase 1 Definition of Done |

### Exact human statement

```text
APO authorize only Confidence Conversation enterprise slice under
docs/ENTERPRISE_STUDENT_PATH_PLAN.md Steps A1–A7; PR-012+ and production
remain unauthorized until separate APPs.
```

### Context and interpretation

Immediately before this statement:

1. PR-011 was accepted under APP-0020 with recorded limitations;
2. STATUS recorded that PR-012 remained unauthorized until a separate APP;
3. the Accountable Product Owner directed creation of an enterprise (not demo)
   student path plan and then instructed to start that plan step by step;
4. Step A0 required this exact authorize sentence before any A1–A7 work.

Interpretation: authorize **begin-implementation** of only the Confidence
Conversation enterprise slice defined by plan Steps A1–A7. This is **not**
acceptance of completed A1–A7 work, not authorization of PR-012 Role-play,
and not authorization of production or Play.

### Authorized scope

Only Steps A1–A7 under
[ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md):

1. **A1** — Spec + verification scope.
2. **A2** — Structured turn contract (English reply + L1 explanation) for all
   Phase 1 UI locales, with validators and fake-provider fixtures.
3. **A3** — Product UI vertical slice (ordinary app path, not Vite `/demo/*`):
   warm-up → listen → talk → correct → reflect.
4. **A4** — Conversation-model provider ADR + fail-closed binding (requires
   nested ADR/APP and spend/DPA gates inside A4; this APP alone does not
   approve a vendor, credential, or live spend).
5. **A5** — AI-only lesson audio / multi-locale guidance TTS policy (ADR vs
   ADR-0008; this APP alone does not approve live TTS spend).
6. **A6** — Locale eval fixtures and `make ai-eval` gates for L1 explanations.
7. **A7** — Separate APO exact-commit acceptance of the completed slice.

Working rules for A1–A7:

- Prefer Laravel modular-monolith ordinary DI, OpenAPI, tenancy tests and
  deterministic fakes until nested A4/A5 gates pass.
- Demo-local Azure TTS (D-0092) and OpenRouter chat (D-0094) remain
  presentation-only and are **not** enterprise verification evidence.
- AI remains advisory and must not mutate marks, mastery, attendance,
  entitlements or unlocks.
- No human-recorded lesson audio; spoken output is AI TTS by product intent.

### Conditions and limitations

- This does **not** accept completed Confidence Conversation work; that
  requires a later exact-artifact APO acceptance (plan Step A7).
- PR-012 and all later master-plan checkpoints remain unauthorized.
- Real LLM / conversation-model providers remain unauthorized until the
  nested A4 ADR + APP (and spend/DPA) pass.
- Ordinary Azure Speech DI expansion beyond existing ADR-0008 gates remains
  unauthorized until nested A5 ADR + APP pass.
- Production deployment, Google Play release and real learner production data
  remain unauthorized.
- Independent legal, security, privacy, tenancy, accessibility,
  language/content, vendor and release reviews remain mandatory before
  production.
- Unlimited provider spend is not authorized.
- No waiver of `make verify` / CI evidence for implementation steps.

<a id="app-0022"></a>

## APP-0022 — ADR-0010 acceptance + Azure conversation-model adapter with capped live spend

| Field | Evidence |
|---|---|
| Status | Accepted (begin-implementation + capped live development spend) |
| Decision date | 2026-08-07 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised | Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer; Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Governing plan | [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) Step A4 |
| Governing ADR | [ADR-0010](adr/0010-conversation-model-provider-policy.md) |
| Prior slice authorization | [APP-0021](APPROVALS.md#app-0021) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statements

```text
Accept ADR-0010 conversation-model provider policy as proposed.
```

```text
We can go with live spends as i want to be as close as possible to production.
```

```text
Amount will be Rs.500
```

### Context and interpretation

Immediately before these statements:

1. ADR-0010 was proposed under plan Step A4 with a draft APO line that deferred
   live spend;
2. APP-0021 authorized A1–A7 but required nested A4 ADR/APP for vendor,
   credential and spend;
3. the Accountable Product Owner accepted the ADR as proposed and directed that
   live spends proceed to stay as close as possible to production.

Interpretation:

1. Accept ADR-0010 exactly as proposed (Azure OpenAI / Azure AI chat behind
   `ConversationModelProvider`, fail-closed production, not demo OpenRouter).
2. Authorize adapter implementation with recorded HTTP fakes **and** capped
   live development spend for non-production Confidence Conversation smoke/eval.
3. The Accountable Product Owner later set the live-spend ceiling explicitly:

```text
Amount will be Rs.500
```

Interpretation of that amendment: replace the provisional ₹5,000 recorder
ceiling with a hard development ceiling of **₹500 INR**. Unlimited spend
remains unauthorized. Live phrase and spend evidence must use `cap_inr` ≤ 500
and:

`Authorize live Confidence Conversation Azure OpenAI up to ₹500`.

4. “As close as possible to production” means real Azure chat-completions
   behaviour under outside-repo credentials and evidence gates — **not**
   production APP_ENV promote, Play release or ordinary production DI.

### Authorized scope

1. Mark ADR-0010 accepted with this APP as evidence.
2. Implement Azure OpenAI conversation-model adapter behind
   `ConversationModelProvider` (project-owned types only).
3. Allowlisted non-production driver `azure-openai-dev` for
   `local` / `testing` / `conversation-model-dev` only.
4. Outside-repo workspace `$HOME/ge-conversation-model` (or
   `GE_CONVERSATION_MODEL_ROOT`) with `0600` secrets and vendor/spend evidence.
5. Live calls only when evidence preflight passes and
   `LIVE_AUTHORIZATION.txt` contains exactly:
   `Authorize live Confidence Conversation Azure OpenAI up to ₹500`.
6. Spend evidence `approval_id` must be `APP-0022` with `cap_inr` ≤ 500.
7. STATUS / DECISIONS / QUALITY_REVIEW / plan Active-step updates.

### Explicit non-authorization

- Production promote / ordinary production DI for Azure conversation-model
- Google Play release
- Unlimited or uncapped provider spend
- Demo OpenRouter (D-0094) as enterprise DI
- A5 multi-locale TTS spend (separate nested gate)
- PR-012 Role-play and later checkpoints
- Waiver of vendor DPA/subprocessor evidence files before live calls
- Waiver of independent specialist reviews before production

### Conditions and limitations

- Operator must supply real Azure deployment region, endpoint, deployment name
  and DPA evidence outside the repository before the first live call.
- Prefer India-region residency; any non-India region requires the
  `region_acceptance` evidence id to reflect explicit acceptance.
- CI and default local paths remain on deterministic fakes.
- AI remains advisory and must not mutate authoritative learning records.

<a id="app-0023"></a>

## APP-0023 — Confidence Conversation A5 begin-implementation (fakes / text-first)

| Field | Evidence |
|---|---|
| Status | Accepted (begin-implementation; superseded for A5 exit by [APP-0024](APPROVALS.md#app-0024)) |
| Decision date | 2026-08-07 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Governing plan | [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) Step A5 |
| Governing ADR | [ADR-0011](adr/0011-confidence-conversation-guidance-tts-policy.md) (proposed) |
| Prior slice authorization | [APP-0021](APPROVALS.md#app-0021) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
Then lets go with A5
```

### Context and interpretation

Immediately before this statement, the Accountable Product Owner amended
APP-0022 live spend to Rs.500 and directed continuation into plan Step A5.

Interpretation: authorize **begin-implementation** of A5 guidance TTS policy
work with deterministic fakes, text-first product UI, and AI-only lesson-audio
content rules. This does **not** accept ADR-0011 (exact Accept statement still
required for A5 exit), does **not** authorize live multi-locale Azure TTS spend
or ordinary-app Azure DI expansion, and does **not** authorize production or
Play.

### Authorized scope

1. Propose ADR-0011 amending ADR-0008 for Confidence Conversation product TTS.
2. Ship `GuidanceTtsPolicy` voice map + purpose codes.
3. Product Listen/model playback text-first; optional on-device English browser
   speech as non-evidence interim.
4. Fake TTS contract for allowlisted voices.
5. Localization + STATUS/DECISIONS/QUALITY_REVIEW/plan updates for in-progress A5.

### Explicit non-authorization

- Acceptance of ADR-0011 (requires exact Accept statement)
- Live multi-locale Azure TTS spend / ordinary DI expansion
- Demo Vite `/demo/tts` promotion into Laravel DI
- Changes to locked PR-009 en-IN benchmark allowlist
- Production / Play / PR-012+

<a id="app-0024"></a>

## APP-0024 — ADR-0011 acceptance + A5 guidance TTS policy (fakes / text-first)

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-07 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised | Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer; Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Governing plan | [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) Step A5 |
| Governing ADR | [ADR-0011](adr/0011-confidence-conversation-guidance-tts-policy.md) |
| Prior begin-implementation | [APP-0023](APPROVALS.md#app-0023) |
| Prior slice authorization | [APP-0021](APPROVALS.md#app-0021) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None (ADR-0011 amends ADR-0008 product policy for Confidence Conversation only) |

### Exact human statements

```text
Accept ADR-0011 Confidence Conversation guidance TTS policy as proposed.
```

```text
APO authorize only A5 guidance TTS policy with fakes and text-first fallback;
live multi-locale TTS spend remains unauthorized until a separate APP.
```

### Context and interpretation

Immediately before these statements:

1. ADR-0011 was proposed and A5 scaffolding shipped under APP-0023;
2. STATUS recorded that exact ADR acceptance was still required to close A5;
3. the Accountable Product Owner / consolidated development roles supplied both
   the ADR accept and APO authorize statements exactly as drafted.

Interpretation:

1. Accept ADR-0011 exactly as proposed (AI-only lesson audio; en-IN model TTS;
   optional L1 guidance voices; text-first fallback; PR-009 en-IN benchmark
   unchanged; demo D-0092 non-evidence).
2. Authorize completion of A5 under fakes and text-first product behaviour.
3. Live multi-locale Azure TTS spend and ordinary-app Azure Speech DI expansion
   remain unauthorized until a separate APP.

### Authorized scope

1. Mark ADR-0011 accepted with this APP as evidence.
2. Close plan Step A5 (policy + fakes + text-first UI already present under
   APP-0023).
3. Update STATUS / DECISIONS / QUALITY_REVIEW / plan Active step to A6.
4. Retain fail-closed ordinary multi-locale Azure TTS DI.

### Explicit non-authorization

- Live multi-locale Azure TTS spend
- Ordinary production Azure Speech DI expansion beyond ADR-0008 gates
- Demo Vite `/demo/tts` promotion into Laravel DI
- Changes to locked PR-009 en-IN benchmark allowlist
- Production / Play / PR-012+
- Waiver of A6 locale eval gates

<a id="app-0025"></a>

## APP-0025 — Confidence Conversation slice acceptance with recorded limitations

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-07 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Exact implementation commit accepted | `5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b` |
| Governing authorization | [APP-0021](APPROVALS.md#app-0021); [APP-0022](APPROVALS.md#app-0022); [APP-0024](APPROVALS.md#app-0024); D-0095–D-0102 |
| Acceptance package | [CONFIDENCE_CONVERSATION_APO_ACCEPTANCE_PACKAGE.md](CONFIDENCE_CONVERSATION_APO_ACCEPTANCE_PACKAGE.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
APO accept Confidence Conversation slice at 5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b with its recorded limitations
```

### Context and interpretation

Immediately before this statement:

1. Confidence Conversation Steps A1–A6 were implemented under APP-0021 with
   nested A4/A5 gates (APP-0022 / ADR-0010; APP-0024 / ADR-0011) and closed on
   exact commit `5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b`;
2. the A7 acceptance package recorded delivered behaviour, verification
   evidence and limitations;
3. STATUS / plan Active step was A7 awaiting exact-commit APO acceptance;
4. the Accountable Product Owner supplied the accept statement exactly as
   drafted.

Interpretation:

- Accept the Confidence Conversation enterprise slice (A1–A7) at that exact
  commit only, with every recorded limitation in the acceptance package.
- Close plan Step A7. Next checkpoint is consideration of a separate PR-012
  APP (plan B0) only.
- Do **not** authorize PR-012 implementation, production, Play, unlimited
  spend, or live multi-locale Azure TTS spend.

### Accepted scope

- Accept Confidence Conversation Steps A1–A7 at exact implementation commit
  `5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b`, with every recorded limitation in
  [CONFIDENCE_CONVERSATION_APO_ACCEPTANCE_PACKAGE.md](CONFIDENCE_CONVERSATION_APO_ACCEPTANCE_PACKAGE.md),
  STATUS, QUALITY_REVIEW, and D-0095–D-0102.

### Conditions and limitations

- This acceptance does **not** authorize PR-012 implementation. It only
  permits consideration of a separate PR-012 APP using the drafted
  [PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md](PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md).
- Default CI/local conversation-model binding remains `fake` / `disabled`;
  production stays fail-closed. Live Azure OpenAI remains allowlisted
  non-production only under APP-0022 (₹500 INR hard ceiling).
- Live multi-locale Azure TTS spend and ordinary Azure Speech DI expansion
  beyond ADR-0008 / ADR-0011 remain unauthorized (APP-0024).
- Product Listen/reply remains text-first; on-device English browser speech is
  interim non-evidence.
- UI locale catalogues pending independent language/content review retain
  ADR-0005 governance; ordinary production stays `approved-only` English
  fallback.
- Host `make verify` / remote GitHub Actions were not claimed green by this
  acceptance and are not waived.
- AI remains advisory and must not mutate authoritative learning records.
- Production deployment, Google Play release and real learner production data
  remain unauthorized.
- Independent legal, security, privacy, tenancy, accessibility,
  language/content, vendor and release reviews remain mandatory before
  production.
- Demo-local Azure TTS / OpenRouter (D-0092 / D-0094) remain presentation-only
  and are not enterprise acceptance evidence.
- No master-plan amendment is made.

<a id="app-0026"></a>

## APP-0026 — PR-012 bounded synthetic role-play begin-implementation

| Field | Evidence |
|---|---|
| Status | Accepted (begin-implementation) |
| Decision date | 2026-08-07 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Prior acceptance | [APP-0025](APPROVALS.md#app-0025) (Confidence Conversation at `5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b`) |
| Governing authorization request | [PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md](PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md) |
| Governing verification scope | [PR-012_ROLEPLAY_VERIFICATION_SCOPE.md](PR-012_ROLEPLAY_VERIFICATION_SCOPE.md) |
| Plan step | [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) B0→B1 |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
APO authorize only PR-012 bounded synthetic role-play under
PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md and
PR-012_ROLEPLAY_VERIFICATION_SCOPE.md.
```

### Context and interpretation

Immediately before this statement:

1. Confidence Conversation was accepted under APP-0025 with recorded limitations;
2. plan Active step was B0 with refreshed PR-012 authorization and verification
   drafts;
3. no PR-012 code was authorized yet;
4. the Accountable Product Owner supplied the begin-implementation statement
   exactly as drafted.

Interpretation: authorize **beginning implementation** of master-plan §26
PR-012 only as the bounded **synthetic** Role-play slice. This is not
acceptance of completed PR-012 work, approved curriculum modules, expanded
live providers, production or Play.

### Authorized scope

1. Server stage state machine: Observe → Rehearse → Perform → Reflect.
2. Synthetic `scenario_objectives` and completion criteria fixtures
   (`development_only` / `synthetic_unreviewed` disclosed).
3. Session snapshotting of persona and scenario versions for role-play mode.
4. Advisory learner feedback evidence linked to objective and scenario IDs —
   must not mutate mastery, attempts, review schedules or publications.
5. Learner Practice UI for the four stages on the ordinary product path.
6. Reuse Confidence Conversation `explanation_l1` / `explanation_locale` on
   Reflect when a priority correction is needed; fake providers by default.
7. OpenAPI/generated clients, organization scoping, cross-tenant tests, and
   STATUS / DECISIONS / QUALITY_REVIEW / plan updates (B0 done; Active = B1).

### Explicit non-authorization

- Acceptance of completed PR-012 (requires later exact-commit APO accept)
- Expanding live Azure OpenAI DI/spend beyond APP-0022 gates
- Live multi-locale Azure TTS spend / ordinary Azure Speech DI expansion
  beyond ADR-0008 / ADR-0011
- Human-approved curriculum, transcript or audio modules
- Full content-studio product surface
- PR-013 / PR-014 / PR-015
- Production / Play / real learner production data
- Waiver of independent specialist reviews before production

### Conditions and limitations

- AI remains advisory and must not mutate authoritative learning records.
- Demo Vite `/demo/*` paths remain non-evidence.
- Content path for this APP is disclosed synthetic only; commissioned content
  remains gated by DR-0007 / separate review.
- No master-plan amendment is made.

<a id="app-0027"></a>

## APP-0027 — PR-012 synthetic role-play acceptance with recorded limitations

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-07 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Exact implementation commit accepted | `0fb6f2953580ed6154e4d895e128c46167c6f5d4` |
| Governing begin-implementation | [APP-0026](APPROVALS.md#app-0026) |
| Acceptance package | [PR-012_APO_ACCEPTANCE_PACKAGE.md](PR-012_APO_ACCEPTANCE_PACKAGE.md) |
| Prior Confidence Conversation acceptance | [APP-0025](APPROVALS.md#app-0025) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
APO accept PR-012 synthetic role-play at 0fb6f2953580ed6154e4d895e128c46167c6f5d4 with its recorded limitations
```

### Context and interpretation

Immediately before this statement:

1. PR-012 bounded synthetic Role-play was implemented under APP-0026 and closed
   on exact commit `0fb6f2953580ed6154e4d895e128c46167c6f5d4`;
2. B0–B2 completed with D-0105 synthetic content path;
3. the acceptance package recorded delivered behaviour, verification evidence
   and limitations;
4. the Accountable Product Owner supplied the accept statement exactly as
   drafted with that Exact commit SHA.

Interpretation:

- Accept the PR-012 **synthetic** Role-play slice at that exact commit only,
  with every recorded limitation in the acceptance package.
- Close plan Steps B0–B2 for the synthetic APP scope.
- Do **not** authorize approved curriculum, expanded live providers,
  production, Play, or PR-013 implementation.

### Accepted scope

- Accept PR-012 bounded synthetic Role-play at exact implementation commit
  `0fb6f2953580ed6154e4d895e128c46167c6f5d4`, with every recorded limitation in
  [PR-012_APO_ACCEPTANCE_PACKAGE.md](PR-012_APO_ACCEPTANCE_PACKAGE.md),
  STATUS, QUALITY_REVIEW, and D-0104–D-0105.

### Conditions and limitations

- This acceptance does **not** authorize PR-013 implementation. It only
  permits consideration of a separate PR-013 APP.
- Disclosed synthetic content only; human-approved curriculum remains
  unauthorized (DR-0007 / D-0105).
- Default CI/local conversation-model binding remains `fake` / `disabled`;
  live Azure OpenAI remains under APP-0022 gates only (₹500 INR ceiling).
- Live multi-locale Azure TTS spend and ordinary Azure Speech DI expansion
  beyond ADR-0008 / ADR-0011 remain unauthorized.
- Host `make verify`, full Chromium e2e and remote CI were not claimed green
  by this acceptance and are not waived.
- AI / Role-play feedback remains advisory and must not mutate authoritative
  learning records.
- Production deployment, Google Play release and real learner production data
  remain unauthorized.
- Independent legal, security, privacy, tenancy, accessibility,
  language/content, vendor and release reviews remain mandatory before
  production.
- Demo Vite `/demo/*` paths remain non-evidence.
- No master-plan amendment is made.

<a id="app-0028"></a>

## APP-0028 — PR-013 bounded operator content-workflow and audit begin-implementation

| Field | Evidence |
|---|---|
| Status | Accepted (begin-implementation) |
| Decision date | 2026-08-07 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Prior acceptance | [APP-0027](APPROVALS.md#app-0027) (PR-012 synthetic at `0fb6f2953580ed6154e4d895e128c46167c6f5d4`) |
| Governing authorization request | [PR-013_OPERATOR_AUTHORIZATION_REQUEST.md](PR-013_OPERATOR_AUTHORIZATION_REQUEST.md) |
| Governing verification scope | [PR-013_OPERATOR_VERIFICATION_SCOPE.md](PR-013_OPERATOR_VERIFICATION_SCOPE.md) |
| Plan step | [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) C0→C1 |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
APO authorize only PR-013 bounded operator content-workflow and audit slice under
PR-013_OPERATOR_AUTHORIZATION_REQUEST.md and
PR-013_OPERATOR_VERIFICATION_SCOPE.md.
```

### Context and interpretation

Immediately before this statement:

1. PR-012 synthetic Role-play was accepted under APP-0027 with recorded limitations;
2. plan Active step was C0 with PR-013 authorization and verification drafts
   ready (content-workflow + audit first);
3. no PR-013 code was authorized yet;
4. the Accountable Product Owner supplied the begin-implementation statement
   exactly as drafted.

Interpretation: authorize **beginning implementation** of master-plan §26
PR-013 only as the bounded **content-workflow + audit** operator slice. This
is not acceptance of completed PR-013 work, sampled speaking review, privacy
ops, production or Play.

### Authorized scope

1. Seeded synthetic pilot organization role packs for trainer /
   organization_admin / content-reviewer using existing curriculum permission
   keys (extend catalogue only if required for audit read).
2. Thin org-scoped operator HTTP APIs wrapping existing content commands:
   draft/status list, EN/KN review decision, publish/rollback status.
3. Authorized, org-scoped audit event list for those privileged actions
   (digests / safe metadata only — no raw learner conversation content).
4. Minimal ordinary-product operator web shell (not Vite `/demo/*`): review
   queue, publish status, audit trail.
5. Cross-tenant isolation tests, OpenAPI/generated clients, and STATUS /
   DECISIONS / QUALITY_REVIEW / plan updates (C0 done; Active = C1).

### Explicit non-authorization

- Acceptance of completed PR-013 (requires later exact-commit APO accept)
- Consent-gated sampled speaking / transcript / audio review (ADR-0007)
- Learner support diagnostics dashboard
- CSV export of learner PII
- Operator-side recording deletion, AI memory ops, or account-erasure workflows
- Full content studio / institution-operations pack (§8.3)
- Expanding live Azure OpenAI / multi-locale TTS spend beyond existing APPs
- PR-014 / PR-015
- Production / Play / real learner production data
- Waiver of independent specialist reviews before production

### Conditions and limitations

- Privileged actions must be authorized, auditable and fail closed across
  tenants.
- Demo Vite `/demo/*` paths remain non-evidence.
- ADR-0007 continues to forbid trainer/operator/admin access to learner audio
  and transcripts until a separate amendment + APP.
- No master-plan amendment is made.

<a id="app-0029"></a>

## APP-0029 — PR-013 bounded operator content-workflow and audit acceptance

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-07 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Exact implementation commit accepted | `9220ab27baa6dc29d82caf8e9e97e94f7df7219d` |
| Governing begin-implementation | [APP-0028](APPROVALS.md#app-0028) |
| Acceptance package | [PR-013_APO_ACCEPTANCE_PACKAGE.md](PR-013_APO_ACCEPTANCE_PACKAGE.md) |
| Prior PR-012 acceptance | [APP-0027](APPROVALS.md#app-0027) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
APO accept PR-013 bounded operator content-workflow and audit at 9220ab27baa6dc29d82caf8e9e97e94f7df7219d with its recorded limitations
```

### Context and interpretation

Immediately before this statement:

1. PR-013 bounded operator content-workflow + audit was implemented under
   APP-0028 and closed on exact commit `9220ab27baa6dc29d82caf8e9e97e94f7df7219d`;
2. C0–C1 completed with focused OC-01–OC-07 verification;
3. the acceptance package recorded delivered behaviour, verification evidence
   and limitations;
4. the Accountable Product Owner supplied the accept statement exactly as
   drafted with that Exact commit SHA.

Interpretation:

- Accept the PR-013 **bounded content-workflow + audit** slice at that exact
  commit only, with every recorded limitation in the acceptance package.
- Close plan Steps C0–C1 for this bounded APP scope.
- Do **not** authorize sampled speaking review, privacy ops, full §8.2 console,
  production, Play, or PR-014 implementation.

### Accepted scope

- Accept PR-013 bounded operator content-workflow and audit at exact
  implementation commit `9220ab27baa6dc29d82caf8e9e97e94f7df7219d`, with every
  recorded limitation in
  [PR-013_APO_ACCEPTANCE_PACKAGE.md](PR-013_APO_ACCEPTANCE_PACKAGE.md),
  STATUS, QUALITY_REVIEW, and D-0107.

### Conditions and limitations

- This acceptance does **not** authorize remaining PR-013 items (sampled
  speaking review, support diagnostics, privacy ops, full content studio) or
  PR-014 implementation.
- ADR-0007 continues to forbid trainer/operator/admin access to learner audio
  and transcripts until a separate amendment + APP.
- Learner PII CSV export and institution-operations pack (§8.3) remain
  unauthorized.
- Host `make verify`, full Chromium e2e and remote CI were not claimed green
  by this acceptance and are not waived.
- Production deployment, Google Play release and real learner production data
  remain unauthorized.
- Independent legal, security, privacy, tenancy, accessibility,
  language/content, vendor and release reviews remain mandatory before
  production.
- Demo Vite `/demo/*` paths remain non-evidence.
- No master-plan amendment is made.

<a id="app-0030"></a>

## APP-0030 — PR-014 Capacitor Android closed-beta foundations begin-implementation

| Field | Evidence |
|---|---|
| Status | Accepted (begin-implementation) |
| Decision date | 2026-08-07 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Prior acceptance | [APP-0029](APPROVALS.md#app-0029) (PR-013 bounded operator at `9220ab27baa6dc29d82caf8e9e97e94f7df7219d`) |
| Governing authorization request | [PR-014_ANDROID_AUTHORIZATION_REQUEST.md](PR-014_ANDROID_AUTHORIZATION_REQUEST.md) |
| Governing verification scope | [PR-014_ANDROID_VERIFICATION_SCOPE.md](PR-014_ANDROID_VERIFICATION_SCOPE.md) |
| Plan step | [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) D0→D1 |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
APO authorize only PR-014 Capacitor Android closed-beta foundations under
PR-014_ANDROID_AUTHORIZATION_REQUEST.md and
PR-014_ANDROID_VERIFICATION_SCOPE.md.
```

### Context and interpretation

Immediately before this statement:

1. PR-013 bounded operator content-workflow + audit was accepted under
   APP-0029 with recorded limitations;
2. plan Active step was D0 with PR-014 authorization and verification drafts
   ready (closed-beta foundations; Play public release deferred);
3. no PR-014 code was authorized yet;
4. the Accountable Product Owner supplied the begin-implementation statement
   exactly as drafted (recorded under this APP while implementation proceeds).

Interpretation: authorize **beginning implementation** of master-plan §26
PR-014 only as **closed-beta foundations** for the production-path Capacitor
Android application. This is not acceptance of completed PR-014 work, Google
Play public release, PR-015, remaining PR-013 sampled review/privacy ops, or
real learner production data. The demo APK (`com.globalenglish.demo`, D-0078)
remains presentation-only.

### Authorized scope

1. Separate Android application identity `com.globalenglish.app` — do **not**
   convert `com.globalenglish.demo`.
2. Capacitor 8 production project packaging the ordinary product web build
   (not `dist-demo`), API 36 target / minimum API 24.
3. System-browser Passport PKCE + deep-link return + shared authentication
   adapter wiring on Android.
4. Select, document and implement a reviewed native secure store for
   installation ULID + refresh credential only; access token remains
   in-memory (ADR-0002).
5. Logout / security reset revokes the active token family; prove credentials
   are absent from WebView storage, `localStorage`, IndexedDB, Cache Storage
   and ordinary Capacitor Preferences.
6. Microphone / audio / connectivity / lifecycle handling for already-accepted
   learner journeys on the production package.
7. Written closed-beta device matrix + critical-journey verification notes.
8. Reproducible signed closed-beta AAB/APK steps documented; STATUS /
   DECISIONS / QUALITY_REVIEW / plan updates (D0 done; Active = D1).

### Explicit non-authorization

- Acceptance of completed PR-014 (requires later exact-commit APO accept)
- Google Play public listing / open production release
- Full Play compliance / store-listing pack beyond closed-beta needs
- PR-015 production hardening
- Remaining PR-013 items (sampled speaking review under ADR-0007, privacy ops,
  full §8.2 console)
- Production OTP provider cutover / real learner production data
- Expanding live Azure OpenAI / multi-locale TTS spend beyond existing APPs
- Embedding secrets or provider credentials in the Android package or
  `dist-demo`
- Using the demo APK (`com.globalenglish.demo`) as the production identity
- Waiver of independent specialist reviews before production

### Conditions and limitations

- Demo Capacitor project at `apps/web/android` remains D-0078 presentation-only.
- No master-plan amendment is made.

<a id="app-0031"></a>

## APP-0031 — PR-014 Capacitor Android closed-beta foundations acceptance

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-07 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Exact implementation commit accepted | `44b0e672e004cf3846cddabc456c21153fa4607e` |
| Governing begin-implementation | [APP-0030](APPROVALS.md#app-0030) |
| Acceptance package | [PR-014_APO_ACCEPTANCE_PACKAGE.md](PR-014_APO_ACCEPTANCE_PACKAGE.md) |
| Prior PR-013 acceptance | [APP-0029](APPROVALS.md#app-0029) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
APO accept PR-014 Capacitor Android closed-beta foundations at 44b0e672e004cf3846cddabc456c21153fa4607e with its recorded limitations
```

### Context and interpretation

Immediately before this statement:

1. PR-014 closed-beta foundations were implemented under APP-0030;
2. the Exact commit `44b0e672e004cf3846cddabc456c21153fa4607e` was recorded in
   [PR-014_APO_ACCEPTANCE_PACKAGE.md](PR-014_APO_ACCEPTANCE_PACKAGE.md) with
   every limitation (Play public release, physical matrix, Gradle assemble,
   `make verify` unclaimed);
3. QUALITY_REVIEW D0/D1 evidence was recorded;
4. the Accountable Product Owner supplied the accept statement exactly as
   drafted with that Exact commit SHA.

Interpretation:

- Accept the PR-014 **closed-beta foundations** slice at that exact commit
  only, with every recorded limitation in the acceptance package.
- Close plan Steps D0–D1 for this APP scope.
- Do **not** authorize Google Play public release, PR-015, remaining PR-013
  sampled speaking review/privacy ops, or real learner production data.

### Accepted scope

- Accept PR-014 Capacitor Android closed-beta foundations at exact
  implementation commit `44b0e672e004cf3846cddabc456c21153fa4607e`, with every
  recorded limitation in
  [PR-014_APO_ACCEPTANCE_PACKAGE.md](PR-014_APO_ACCEPTANCE_PACKAGE.md),
  STATUS, QUALITY_REVIEW, and D-0109–D-0110.

### Conditions and limitations

- Physical device-matrix execution, Gradle APK/AAB assemble and host
  `make verify` remain unclaimed follow-up evidence obligations; they are not
  waived by this acceptance.
- Google Play public listing / open production release remain unauthorized.
- Remaining PR-013 items (sampled speaking review under ADR-0007, privacy ops,
  full §8.2 console) remain unauthorized.
- PR-015 production hardening, production OTP cutover and real learner
  production data remain unauthorized.
- Demo APK (`com.globalenglish.demo`) remains presentation-only (D-0078).
- Live Azure OpenAI / multi-locale TTS spend remain under existing APP gates
  only.
- No master-plan amendment is made.

<a id="app-0032"></a>

## APP-0032 — PR-015 bounded operations hardening and closed-beta evidence closure begin-implementation

| Field | Evidence |
|---|---|
| Status | Accepted (begin-implementation) |
| Decision date | 2026-08-07 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Prior acceptance | [APP-0031](APPROVALS.md#app-0031) (PR-014 closed-beta foundations at `44b0e672e004cf3846cddabc456c21153fa4607e`) |
| Governing authorization request | [PR-015_PRODUCTION_HARDENING_AUTHORIZATION_REQUEST.md](PR-015_PRODUCTION_HARDENING_AUTHORIZATION_REQUEST.md) |
| Governing verification scope | [PR-015_PRODUCTION_HARDENING_VERIFICATION_SCOPE.md](PR-015_PRODUCTION_HARDENING_VERIFICATION_SCOPE.md) |
| Plan step | [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) E0→E1 |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
APO authorize only PR-015 bounded operations hardening and closed-beta evidence
closure under PR-015_PRODUCTION_HARDENING_AUTHORIZATION_REQUEST.md and
PR-015_PRODUCTION_HARDENING_VERIFICATION_SCOPE.md.
```

### Context and interpretation

Immediately before this statement:

1. PR-014 closed-beta foundations were accepted under APP-0031 with recorded
   limitations (physical matrix / Gradle assemble / `make verify` unclaimed);
2. plan Active step was E0 with PR-015 bounded authorization and verification
   drafts ready;
3. no PR-015 code was authorized yet;
4. the Accountable Product Owner supplied the begin-implementation statement
   exactly as drafted.

Interpretation: authorize **beginning implementation** of master-plan §26
PR-015 only as the **bounded** operations-hardening + closed-beta evidence
closure slice. This is not acceptance of completed PR-015 work, Phase 1
Definition of Done (F0), Google Play public release, remaining PR-013 sampled
review/privacy ops, or real learner production data.

### Authorized scope

1. Close APP-0031 Android closed-beta evidence gaps (device matrix, signed
   assemble, `make verify` evidence where feasible).
2. Load/soak harness against synthetic stack at 2× pilot planning peak.
3. Backup/restore drill runbook + one non-production restore rehearsal.
4. Monitoring/alerts skeleton + incident/support runbooks.
5. Staged deploy + rollback dry-run evidence for API/web.
6. Accessibility evidence package for already-accepted critical paths.
7. Aggregate-only pilot analytics skeleton (no PII CSV).
8. STATUS / DECISIONS / QUALITY_REVIEW / plan updates (E0 done; Active = E1).

### Explicit non-authorization

- Acceptance of completed PR-015 (requires later exact-commit APO accept)
- Google Play public listing / open production release
- Phase 1 Definition of Done sign-off (F0 / master plan §27)
- Remaining PR-013 items (sampled speaking review under ADR-0007, privacy ops,
  full §8.2 console)
- Production OTP provider cutover / real learner production data
- Expanding live Azure OpenAI / multi-locale TTS spend beyond existing APPs
- Waiver of independent specialist reviews
- Claiming production availability / latency SLOs as contractually met
- Promoting demo APK (`com.globalenglish.demo`) to production identity

### Conditions and limitations

- Demo Capacitor project at `apps/web/android` remains D-0078 presentation-only.
- No master-plan amendment is made.

<a id="app-0033"></a>

## APP-0033 — PR-015 bounded operations hardening and closed-beta evidence closure acceptance

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-07 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Exact implementation commit accepted | `5eca7515291ea571a7f697dcdc2da6197535f839` |
| Governing begin-implementation | [APP-0032](APPROVALS.md#app-0032) |
| Acceptance package | [PR-015_APO_ACCEPTANCE_PACKAGE.md](PR-015_APO_ACCEPTANCE_PACKAGE.md) |
| Prior PR-014 acceptance | [APP-0031](APPROVALS.md#app-0031) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
APO accept PR-015 bounded operations hardening and closed-beta evidence closure at 5eca7515291ea571a7f697dcdc2da6197535f839 with its recorded limitations
```

### Context and interpretation

Immediately before this statement:

1. PR-015 bounded operations hardening and closed-beta evidence closure were
   implemented under APP-0032;
2. the Exact commit `5eca7515291ea571a7f697dcdc2da6197535f839` was recorded in
   [PR-015_APO_ACCEPTANCE_PACKAGE.md](PR-015_APO_ACCEPTANCE_PACKAGE.md) with
   every limitation (F0, Play public release, physical matrix, `make verify`,
   live load-soak/backup execution unclaimed);
3. QUALITY_REVIEW E0/E1 evidence was recorded;
4. the Accountable Product Owner supplied the accept statement exactly as
   drafted with that Exact commit SHA.

Interpretation:

- Accept the PR-015 **bounded ops hardening + closed-beta evidence closure**
  slice at that exact commit only, with every recorded limitation in the
  acceptance package.
- Close plan Steps E0–E1 for this APP scope.
- Do **not** authorize F0 Phase 1 DoD, Google Play public release, remaining
  PR-013 sampled speaking review/privacy ops, or real learner production data.

### Accepted scope

- Accept PR-015 bounded operations hardening and closed-beta evidence closure
  at exact implementation commit `5eca7515291ea571a7f697dcdc2da6197535f839`,
  with every recorded limitation in
  [PR-015_APO_ACCEPTANCE_PACKAGE.md](PR-015_APO_ACCEPTANCE_PACKAGE.md),
  STATUS, QUALITY_REVIEW, and D-0112.

### Conditions and limitations

- Physical device-matrix execution, Gradle assemble, host `make verify`, live
  load-soak against a running API, and executed backup/restore drill evidence
  remain unclaimed follow-up obligations; they are not waived by this
  acceptance.
- Phase 1 Definition of Done (F0 / master plan §27) remains unauthorized.
- Google Play public listing / open production release remain unauthorized.
- Remaining PR-013 items (sampled speaking review under ADR-0007, privacy ops,
  full §8.2 console) remain unauthorized.
- Production OTP cutover and real learner production data remain unauthorized.
- Demo APK (`com.globalenglish.demo`) remains presentation-only (D-0078).
- Live Azure OpenAI / multi-locale TTS spend remain under existing APP gates
  only.
- No master-plan amendment is made.

<a id="app-0034"></a>

## APP-0034 — Pre-F0 Phase 1 evidence-closure begin-implementation

| Field | Evidence |
|---|---|
| Status | Accepted (begin-implementation) |
| Decision date | 2026-08-07 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation |
| Prior acceptance | [APP-0033](APPROVALS.md#app-0033) (PR-015 bounded ops hardening at `5eca7515291ea571a7f697dcdc2da6197535f839`) |
| Governing gap assessment | [F0_PHASE1_DOD_GAP_ASSESSMENT.md](F0_PHASE1_DOD_GAP_ASSESSMENT.md) |
| Plan step | [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) F0 (pre-DoD evidence) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
APO authorize only pre-F0 Phase 1 evidence-closure under
F0_PHASE1_DOD_GAP_ASSESSMENT.md; F0 Definition of Done sign-off and Play
public release remain unauthorized.
```

### Context and interpretation

Immediately before this statement:

1. PR-015 bounded ops hardening was accepted under APP-0033 with recorded
   limitations;
2. plan Active step was F0 with a drafted §27 gap assessment;
3. F0 Definition of Done was not ready to sign;
4. the Accountable Product Owner supplied the begin-implementation statement
   exactly as drafted.

Interpretation: authorize **beginning** pre-F0 evidence-closure work only
(Android closed-beta assemble/matrix gaps, load-soak and backup/restore drill
execution, §27 evidence matrix fill, and APP-deferred handling of remaining
PR-013/content blockers as listed in the gap assessment). This is **not**
acceptance of Phase 1 Definition of Done, Google Play public release, waiver
of specialist reviews, or real learner production data.

### Authorized scope

1. Close Android closed-beta evidence gaps (assemble, device matrix, on-device
   credential negatives where feasible).
2. Execute load-soak and backup/restore drills against local/staging-like
   stack; record evidence.
3. Fill a §27 evidence matrix (pass / fail / deferred per row).
4. Document remaining PR-013 / DR-0007 / specialist-review blockers without
   expanding unauthorized product scope unless separately APP-gated.
5. STATUS / DECISIONS / QUALITY_REVIEW / plan updates.

### Explicit non-authorization

- F0 Phase 1 Definition of Done sign-off
- Google Play public listing / open production release
- Waiver of independent specialist reviews
- Remaining PR-013 sampled speaking review / privacy ops (unless a distinct
  APP later authorizes them)
- Production OTP cutover / real learner production data
- Expanding live Azure spend beyond existing APPs

### Conditions and limitations

- Demo APK remains presentation-only (D-0078).
- No master-plan amendment is made.

