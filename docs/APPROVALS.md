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


<a id="app-0035"></a>

## APP-0035 — PR-013 remaining privacy and support ops begin-implementation

| Field | Evidence |
|---|---|
| Status | Accepted (begin-implementation) |
| Decision date | 2026-08-07 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation (blanket authorization to continue) |
| Prior acceptance | [APP-0029](APPROVALS.md#app-0029) (bounded content-workflow + audit); [APP-0034](APPROVALS.md#app-0034) deferred remaining privacy/support |
| Governing request | [PR-013_PRIVACY_SUPPORT_AUTHORIZATION_REQUEST.md](PR-013_PRIVACY_SUPPORT_AUTHORIZATION_REQUEST.md) |
| Plan step | [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) PR-013 remaining ops / pre-F0 |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
APO authorize only remaining PR-013 support diagnostics and privacy/account
ops under PR-013_PRIVACY_SUPPORT_AUTHORIZATION_REQUEST.md; sampled speaking
review, F0 DoD, and Play public release remain unauthorized.
```

### Context and interpretation

Immediately before this statement:

1. APP-0034 evidence package and staging Friend path were recorded;
2. remaining PR-013 privacy/support ops were still APP-deferred;
3. the Accountable Product Owner supplied blanket authorization to continue
   and do the needful, which includes accepting the Exact begin-implementation
   statement drafted in the governing request.

Interpretation: authorize **beginning** only the remaining PR-013 support
diagnostics and privacy/account-ops vertical slice. This is **not**
acceptance of sampled speaking review, F0 Definition of Done, Google Play
public release, or waiver of independent specialist reviews.

### Authorized scope

1. Org-scoped support diagnostics listing (digests/safe metadata only).
2. Operator account-erasure / retention workflows aligned with ADR-0007.
3. Bounded org-scoped PII export (JSON summary of membership/consent/deletion
   receipt identifiers — no audio/transcripts).
4. OpenAPI + generated clients, cross-tenant tests, STATUS / DECISIONS /
   QUALITY_REVIEW updates.

### Explicit non-authorization

- Consent-gated sampled speaking / transcript / audio review
- F0 Phase 1 Definition of Done sign-off
- Google Play public listing / open production release
- Waiver of independent specialist reviews
- Production OTP cutover / real learner production data

### Conditions and limitations

- Digests and identifier summaries only; never raw conversation bodies,
  transcripts, or audio in operator responses.
- No master-plan amendment is made.


<a id="app-0037"></a>

## APP-0037 — DR-0007 synthetic pathway limitation resolution

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-07 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation (blanket authorization to continue) |
| Governing request | [DR-0007_CURRICULUM_COMMISSIONING_REQUEST.md](DR-0007_CURRICULUM_COMMISSIONING_REQUEST.md) |
| Related decision | [D-0116](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
APO resolve DR-0007: retain disclosed synthetic pathway and Role-play with
explicit F0 limitation that Phase 1 pathway DoD remains incomplete until
specialist-commissioned reviewed content lands; commissioning budget and
owner remain a separate commercial decision.
```

### Context and interpretation

Immediately before this statement:

1. DR-0007 remained open as a commercial commissioning blocker;
2. F0 pathway DoD could not claim a complete reviewed pathway;
3. the Accountable Product Owner authorized continuation and resolution of
   outstanding pre-F0 gates without inventing a commissioning budget.

Interpretation: close DR-0007 by **retaining synthetic content with an
explicit F0 limitation**. This does **not** commission specialist content,
does not mark the §27 pathway row Pass, and does not authorize F0 Exact
accept or Play public release.

### Conditions and limitations

- Commissioning budget, named owner, and review SLAs remain a separate
  commercial decision before a reviewed-pathway Pass claim.
- No master-plan amendment is made.


<a id="app-0036"></a>

## APP-0036 — PR-013 remaining privacy and support ops Exact-commit acceptance

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-07 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Human instruction in the active Cursor conversation (blanket authorization to continue) |
| Exact implementation commit accepted | `1357eb161d3754da3b8d6b888bd1e3b0a8698ff9` |
| Governing begin-implementation | [APP-0035](APPROVALS.md#app-0035) |
| Acceptance package | [PR-013_PRIVACY_SUPPORT_ACCEPTANCE_PACKAGE.md](PR-013_PRIVACY_SUPPORT_ACCEPTANCE_PACKAGE.md) |
| Related decision | [D-0117](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record |
| Master-plan amendment | None |

### Exact human statement

```text
APO accept PR-013 remaining privacy and support ops at 1357eb161d3754da3b8d6b888bd1e3b0a8698ff9 with its
recorded limitations; sampled speaking review, F0 DoD, and Play public release
remain unauthorized.
```

### Context and interpretation

Immediately before this statement:

1. APP-0035 authorized begin-implementation of remaining privacy/support ops;
2. the vertical slice was implemented and verified in
   [PR-013_PRIVACY_SUPPORT_ACCEPTANCE_PACKAGE.md](PR-013_PRIVACY_SUPPORT_ACCEPTANCE_PACKAGE.md)
   at exact commit `1357eb161d3754da3b8d6b888bd1e3b0a8698ff9`;
3. the Accountable Product Owner supplied blanket authorization to continue
   and do the needful, which includes Exact-commit acceptance of that slice.

Interpretation: accept only the remaining PR-013 support diagnostics and
privacy/account-ops vertical slice at that exact commit, with every recorded
limitation. This does **not** authorize sampled speaking review, F0 Definition
of Done, Google Play public release, or waiver of independent specialist
reviews.

### Conditions and limitations

- Digests and identifier summaries only; never raw conversation bodies,
  transcripts, or audio in operator responses.
- Host `make verify` / postgres Integration suite were not claimed green by
  this acceptance and are not waived.
- No master-plan amendment is made.

<a id="app-0038"></a>

## APP-0038 — DR-0013 Option A privileged MFA policy approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-08 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; Security Reviewer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Exact proposal artifact | [DR-0013_PRIVILEGED_MFA_FACTOR_AND_RECOVERY_REQUEST.md](DR-0013_PRIVILEGED_MFA_FACTOR_AND_RECOVERY_REQUEST.md) |
| Approved proposal SHA-256 | `78697cffea182a9aafde9eea84742883ffb4e71f616a0789b5f0f9021601013f` (before its resolution note) |
| Related decision | [D-0123](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
Approve DR-0013 Option A as written.
```

### Context and interpretation

Immediately before this statement:

1. MFA-01 inspection had established that the repository contained a
   fail-closed freshness extension point but no enrollable independent factor,
   challenge, recovery or revocation lifecycle;
2. the existing operator routes accepted web sessions and Passport bearers,
   while MFA freshness existed only on web authentication sessions;
3. DR-0013 presented three concrete factor/platform/recovery profiles and
   recommended Option A, including eight exact policy rules and their schema,
   API and client consequences; and
4. the repository had stopped before credential schema or security behavior
   changes pending this explicit decision.

The human supplied the exact approval statement requested by DR-0013. In that
bounded authentication-security context, it is recorded as consolidated
development approval by the Accountable Product Owner, Technical Decision
Owner and Security Reviewer under ADR-0001. The role-based identifier avoids
inferring a personal identity that was not supplied in this conversation.

### Accepted scope

- Accept Option A exactly as specified in the approved DR-0013 proposal:
  RFC 6238 authenticator-app TOTP for privileged web sessions, with the stated
  entropy, step, skew, confirmation, expiry, attempt, rate-limit and replay
  controls.
- Accept ten show-once, single-use recovery codes with at least 128 bits of
  entropy each and domain-separated keyed digests at rest; SMS/primary OTP
  alone cannot reset privileged MFA.
- Accept global web-session and Android token-family revocation for factor
  regeneration, replacement or revocation, and content-free append-only audit
  evidence for the factor lifecycle.
- Accept the exact mutable authoritative protected-action set in DR-0013 while
  retaining logout and user-initiated session revocation as security escape
  paths without fresh MFA.
- Accept the Phase 1 web-only privileged boundary: Passport bearer requests to
  privileged/operator endpoints are denied. Cross-platform privileged MFA
  requires a separate future decision and device evidence.
- Resolve DR-0013 and permit MFA-01 to proceed as a separately bounded
  implementation task under the active remediation plan.

### Conditions and limitations

- TOTP's lack of phishing resistance is an explicitly accepted Phase 1
  trade-off, not an unrecorded limitation.
- This approval does not claim or accept any MFA-01 implementation, migration,
  endpoint, generated client, UI, test result or production deployment.
- The implementation must preserve ADR-0002 session/token/recovery rules,
  ADR-0003 membership-derived organization authorization and ADR-0004's
  authoritative content-governance boundaries.
- One human exercised consolidated development roles. This is not independent
  specialist review. Independent security and privacy review remains mandatory
  before production under ADR-0001.
- No personal identity, specialist qualification, timestamp-of-day or
  repository commit SHA is inferred from the approval statement.

<a id="app-0053"></a>

## APP-0053 — Ubuntu invite-only soft launch

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-10 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Evidence source | Exact human instruction to implement the Ubuntu soft-launch plan in the active Cursor conversation |
| Governing plan | Ubuntu soft launch — full implemented product |
| Related decisions | [D-0155](DECISIONS.md); [D-0156](DECISIONS.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | Sequencing only: Ubuntu invite-only soft launch becomes the active delivery checkpoint; LPL-01 transcription ceremony is paused as the critical path |

### Exact human statement

```text
Approve Ubuntu invite-only soft launch as written: full implemented product, real Azure up to ₹10000, pause LPL-01 as critical path.
```

The Accountable Product Owner also directed implementation of the attached Ubuntu
soft-launch plan (full implemented product on the Ubuntu static-IP host).

### Accepted scope

1. Declare Ubuntu invite-only soft launch the active checkpoint for the
   repository-implemented learner and operator product on host
   `Real-Studioz` / static IP `117.254.108.143`.
2. Authorize non-production `APP_ENV=soft-launch` (and retain
   `conversation-model-dev` / `staging` allowlists) with
   `CONVERSATION_PROVIDER_DRIVER=azure-openai-dev` for Confidence Conversation
   and role-play coaching under a hard **₹10,000 INR** spend ceiling,
   kill switch and outside-repo evidence/secret files.
3. Allow conversation HTTP entrypoints to use live Azure in those allowlisted
   non-production environments instead of requiring deterministic fakes.
4. Treat [APP-0044](#app-0044) owner media attestation as sufficient umbrella
   evidence for publishing the soft-launch pathway through existing Media
   Library / Content Studio commands (exact hash/program rights rows still
   required; no multi-week new DR chain for owner media).
5. Pause LPL-01 transcription/dispatch ceremony as the critical path while soft
   launch is active; LPL-01 artifacts remain available but are not blocking.
6. Prefer Cloudflare HTTPS tunnel as the primary public entry for microphone
   capability; optional direct static-IP HTTP remains secondary for text checks.

### Explicit non-authorization

- Google Play public listing or store marketing
- Unlimited or uncapped Azure spend
- `APP_ENV=production` with live conversation drivers
- Claiming independent legal/specialist review completion
- Payments, LMS SSO, notices/groups moderation, or planned LPL Skill Chat /
  auto quiz-from-audio as soft-launch deliverables
- Treating synthetic demo Home cards as live product behavior

### Conditions and limitations

- Soft launch is invite-only with real adult pilot learners; it creates real
  privacy/support obligations even without Play.
- OTP may start with Mailpit/operator-relayed codes for owner-known invitees
  for at most seven days; real SMS is required to keep the soft launch open
  beyond that window.
- Home/static-IP hosting depends on local power, ISP and dual-NAT forwarding;
  tunnel mitigates reachability and HTTPS.
- One human exercised consolidated development roles. This is not independent
  specialist, legal or production review.

<a id="app-0054"></a>

## APP-0054 — Temporary disclosed Azure Speech credential use

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-10 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; consolidated development reviewers |
| Evidence source | Exact human instruction in the active Codex conversation |
| Related decisions | [D-0160](DECISIONS.md); [D-0161](DECISIONS.md) |
| Recorder | Codex, acting only as evidence recorder |

### Exact human statement

```text
place the keys for now. I will rotate it later once we have tested it.
Approved for the rest of the changes. do it.
```

### Bounded interpretation

- Retain one active Azure Speech resource credential in the existing
  outside-repository mode-`0600` secret file for the already-approved
  non-production synthetic test path. Azure authentication requires one active
  credential; the second recovery key is not duplicated into application or
  repository configuration.
- Complete the high-priority Ubuntu soft-launch changes already bounded by
  APP-0053 and record truthful test/resource evidence.
- This approval does not manufacture an independent review, authorize learner
  audio under the synthetic-benchmark policy, enable `APP_ENV=production`,
  waive the Azure OpenAI deployment/credential requirement or authorize an
  uncapped provider call.
- Because the resource keys appeared in conversation text, both remain due for
  Azure regeneration after testing and before relying on them for an ongoing
  learner service.

<a id="app-0055"></a>

## APP-0055 — DR-0026 password-only authentication architecture approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-11 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; consolidated Security, Privacy and Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Approved request | [DR-0026](DR-0026_PASSWORD_ONLY_AUTHENTICATION_REQUEST.md) Option B exactly as written |
| Approved pre-resolution DR SHA-256 | `847486461ffbf92610427744539bea5a3fd607b2988d8aea8a9ff54fbcd0576d` |
| Resulting ADR | [ADR-0021](adr/0021-username-password-only-authentication.md) |
| Related decision | [D-0163](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | Yes — authentication becomes username/password-only for every role and privileged MFA is replaced by fresh same-password confirmation |

### Exact human statement

```text
Approve DR-0026 Option B as written and accept its documented password-only privileged-access risk.
```

### Accepted scope

- Give every learner, trainer, reviewer, organization administrator and
  platform administrator one platform-global username and a password as the
  only routine sign-in factor.
- Remove OTP from ordinary sign-in and replace fresh TOTP on the exact existing
  sensitive-command set with a server-side, session-bound fresh confirmation
  of the same password no more than five minutes old.
- Apply the exact 15–128 Unicode-code-point, NFC, compromised-password
  blocklist, Argon2id, rate-limit, enumeration-safe, recovery, revocation and
  migration controls in the approved request.
- Preserve Sanctum cookie sessions for web/PWA, Passport Authorization Code
  with S256 PKCE in the Android system browser, native secure credential
  storage, current membership selection and command-time tenant authorization.

### Conditions and limitations

- APP-0055 authorizes ADR-0021, the master-plan amendment and preparation of
  the bounded AUTH-PASSWORD-01 specification. DR-0026 explicitly states that
  it does not directly authorize runtime implementation.
- A password re-entry is one-factor freshness evidence and must never be
  represented as MFA. The approver explicitly accepts the resulting privileged
  credential-stuffing, phishing, reuse, spraying and offline-guessing risk.
- Existing OTP/TOTP enforcement remains fail closed until a separately
  authorized, verified cutover completes. No bypass, shared/default password
  or direct credential mutation is authorized.
- One human exercised consolidated development roles. Independent security,
  privacy, tenancy, accessibility and language/content review, production and
  Google Play authorization remain outstanding.

<a id="app-0056"></a>

## APP-0056 — AUTH-PASSWORD-01 implementation authorization

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-11 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; consolidated development reviewers |
| Evidence source | Exact human instruction in the active Codex conversation |
| Approved specification | [AUTH-PASSWORD-01](AUTH-PASSWORD-01_SPEC.md) exactly as written |
| Approved pre-resolution specification SHA-256 | `7b5d3260c619c33fd4df5cff06364860ab67536f02399ed61c5fe086519193c4` |
| Related decision | [D-0164](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |

### Exact human statement

```text
Approve AUTH-PASSWORD-01 implementation as written.
```

### Bounded interpretation

- Implement only the schema, password services, web/Android system-browser
  flows, fresh-password command protection, ordinary and dual-authorized
  privileged recovery, migration/cutover, OTP/TOTP retirement, contracts,
  tests and documentation fixed by AUTH-PASSWORD-01.
- Preserve current OTP/TOTP enforcement until the specification's verified
  cutover, and never manufacture, log, display or share a user password.
- Production, Google Play release, LPL-02 and independent-review waivers remain
  unauthorized.

<a id="app-0057"></a>

## APP-0057 — DR-0027 excluded privileged-account recovery approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-11 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; consolidated Security, Privacy and Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Approved request | [DR-0027](DR-0027_EXCLUDED_PRIVILEGED_ACCOUNT_RECOVERY_REQUEST.md) Option A exactly as written |
| Approved pre-resolution DR SHA-256 | `84cb3405210f689df1895d48bb7a7c6d4638dc10e0c32e34db1ba2e96782a623` |
| Related decision | [D-0169](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None — this closes the already accepted excluded-user recoverability contract without changing password-only policy |

### Exact human statement

```text
Approve DR-0027 Option A as written.
```

### Accepted scope

- Preserve the completed password-only cutover and reconcile the exact reviewed
  local exclusion manifest into append-only marker-linked evidence.
- Extend the two-distinct-actor privileged recovery lifecycle to one listed,
  active, credential-unready excluded user with an already verified recovery
  identity, retaining exact evidence binding and the full 24-hour delay.
- Deliver one 256-bit, 15-minute, single-use establishment token through a
  reviewed protected local surface. The target chooses their own unique
  username and policy-compliant password; no recovery actor sees or sets it.
- Update contracts, privacy/runbooks, UI/commissioning aid and required
  SQLite/PostgreSQL/browser/security coverage, then execute only after a fresh
  backup and dry run against the local non-production database.

### Conditions and limitations

- No shorter delay, direct credential mutation, learner promotion, missing-
  contact exception, retired OTP/TOTP restore or recovery-actor access to the
  target secret is authorized.
- The implementation cannot add an unlisted exclusion or mutate organization
  membership, role, permission, curriculum or publication authority.
- Production recovery delivery, production cutover/deployment, Google Play and
  independent security/privacy/tenancy/accessibility/language-review waivers
  remain unauthorized.

<a id="app-0058"></a>

## APP-0058 — DR-0029 accelerated governed Levels 1–17 rollout approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-12 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; consolidated development reviewers |
| Evidence source | Exact human instruction in the active Codex conversation |
| Approved request | [DR-0029](DR-0029_LEVELS_1_17_LIVE_ACTIVATION_REQUEST.md) Option A exactly as written |
| Approved pre-resolution DR SHA-256 | `8f2363579a58a84d55032d6e3e4aec2b996d77503b8afa1aa9424a08baebdafb` |
| Related decision | [D-0184](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
Approve DR-0029 Option A as written.
```

### Accepted scope

- Complete LPL-01's exact Level 1 transcript reconciliation and publishable
  blueprint, then obtain the required exact-hash content reviews.
- Proceed in the existing ordered LPL checkpoints: one verified Level 1
  Learn → Talk → Check → Apply → Review slice, Levels 2–3, then the remaining
  source-complete levels in reviewed waves.
- Keep every incomplete level unavailable with an exact reason and never
  fabricate missing media, transcripts, answers or learning evidence.
- Permit qualified English/Kannada role consolidation only where ADR-0004
  allows it and only when author/translator/reviewer separation still passes.
- Enable typed AI, learner voice, immutable publication and enrollment pinning
  only after each option's separately retained provider, consent, rights,
  review, tenancy and physical-device gates pass.

### Conditions and limitations

- This approval makes only LPL-01 active. It does not accept its result or
  authorize LPL-02–LPL-07 implementation opportunistically.
- It is not a blanket review, rights, privacy, security, provider, content,
  publication, enrollment, production or Google Play waiver.
- Development-only synthetic scenarios and smoke publications cannot be
  represented as reviewed live AI learning.
- Stable-host activation remains separately incomplete and deferred to 13
  August 2026.

<a id="app-0059"></a>

## APP-0059 — DR-0030 unified login and recovery approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-13 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; consolidated Security, Privacy and Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Approved request | [DR-0030](DR-0030_UNIFIED_ROLE_BASED_LOGIN_AND_RECOVERY_REQUEST.md) Option A exactly as written |
| Approved pre-resolution DR SHA-256 | `3a671fbf56fa482d1d950668ed0ef76744dbcfe90931b42e1d753667a8a51534` |
| Resulting ADR | [ADR-0022](adr/0022-unified-role-based-login-and-recovery.md) |
| Authorized specification | [AUTH-SIMPLE-01](AUTH-SIMPLE-01_SPEC.md) |
| Related decision | [D-0186](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | Yes — one verified-contact setup/recovery path applies to every role and the exceptional two-actor/24-hour path is retired |

### Exact human statement

```text
Approve DR-0030 Option A as written.
```

### Accepted scope

- Retain one platform-global username/password sign-in for every learner,
  trainer, reviewer and administrator; resolve organization roles and learner
  enrollment only after authentication.
- Use one enumeration-safe 256-bit, 15-minute, single-use verified-contact
  setup/recovery path for every role, including reviewed credential-unready
  migration exclusions.
- Retire the active two-recovery-actor/24-hour privileged case APIs and
  commissioning pages while preserving their rows as restricted historical
  evidence.
- Permit one freshly password-confirmed platform actor with exactly
  `platform.account_recovery.manage` to replace a lost recovery contact after
  external verification evidence, former-contact notification where possible,
  global revocation and content-free audit.
- Implement only the bounded AUTH-SIMPLE-01 schema, API/client, Web/Android
  contract, tests, runbooks, privacy/threat and governance updates.

### Conditions and limitations

- No administrator may view a reset/setup token or choose/set another user's
  username or password. No shared/default credential, phone-number sign-in,
  Google/social sign-in or tenant role bypass is authorized.
- A stolen administrator password or verified recovery contact can enable
  account takeover; the approver accepts this documented one-factor risk.
- Production delivery/notification still requires a reviewed adapter, provider
  and subprocessor evidence, protected secrets, sender controls, replay/expiry/
  throttling/redaction tests and independent security/privacy review.
- This approval does not authorize production, public/Google Play release,
  learner voice/AI activation, curriculum publication or LPL-02.

<a id="app-0062"></a>

## APP-0062 — DR-0033 Levels 2–17 teaching-audio processing approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-14 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; retained Rights Confirmer |
| Evidence source | Exact human instruction in the active Cursor conversation |
| Related request | [DR-0033](DR-0033_LEVELS_2_17_TEACHING_AUDIO_PROCESSING_REQUEST.md) Option A |
| Pre-resolution DR SHA-256 | `85bbf170ee5fbaf98b33bb93d5253bed83955db309ceef2eb92cdc508171667a` |
| Selected inventory artifact | [levels-1-17-teaching-audio-hash-inventory.json](evidence/lpl-01/levels-1-17-teaching-audio-hash-inventory.json) |
| Selected inventory SHA-256 | `00b27b12a19af09bdadb5e580e5dfca998a69ab2b3e634243dfe5e5c6d695d30` |
| Related decision | [D-0203](DECISIONS.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
Approve DR-0033 Option A for the exact inventory hashes.
```

### Context and interpretation

Immediately before this statement:

1. authenticated read-only legacy inspection inventoried 67 unique teaching
   MP3s across Levels 1–17 with exact SHA-256 and byte sizes;
2. Level 1 hashes matched APP-0047 exactly and remain already authorized;
3. Levels 2–17 bytes were staged only in ephemeral local `/tmp` storage; and
4. DR-0033 recommended Option A: private non-production Media Library
   ingestion of the exact inventory hashes as `lesson_audio`, with
   program-scoped Direct pilot rights for web/Android in India.

The approval authorizes processing of the exact inventory SHA-256 values. It
does not invent hashes, authorize Assignment audio, mutate the live legacy
site, commit media bytes to Git, or authorize production/public release.

### Accepted scope

- Private non-production ingestion of the exact inventory hashes through the
  current quarantine, ffprobe inspection, ClamAV clean-scan and acceptance
  lifecycle as `lesson_audio` (ADR-0016).
- Skip re-ingestion of Level 1 hashes already accepted under APP-0047 when
  the current Media Library already holds those exact objects.
- Program-scoped rights for private Direct pilot learner delivery on web and
  Android in India after ordinary rights confirmation; sharing and
  production/public release remain prohibited.
- Later attachment into ADR-0025 four-session blueprints remains a separate
  operator command after English/Kannada gates for each level's content.

### Conditions and limitations

- One human exercised consolidated development roles. This is not independent
  production security, privacy, legal or academic/Kannada review.
- Transcription, blueprint attachment, curriculum publication and production
  remain separately gated.
- Existing tenant, permission, exact-hash, scan, retention and audit controls
  remain mandatory.
- Credentials and object URLs must not enter repository evidence.

<a id="app-0061"></a>

## APP-0061 — all-levels four-session journey and single-stroke implementation approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-14 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; consolidated Security, Privacy and Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Cursor conversation |
| Related request | [DR-0032](DR-0032_ALL_LEVELS_FOUR_SESSION_LEARNER_JOURNEY_REQUEST.md) Option A |
| Pre-resolution DR SHA-256 | `ef26f0bfbabac0c23bebf55df4475957e764272af974b41566d292870465fc79` |
| Resulting ADR | [ADR-0025](adr/0025-all-levels-four-session-learner-journey.md) |
| Related decision | [D-0201](DECISIONS.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | Yes — every level uses Introduction → Practice → Conversation → Interaction; master-plan version 3.6 |

### Exact human statement

```text
Lets go with option A. Codex takes a lot of timme to complete it. Consider you have a blanket waiver and approval and complete it all in a saingle stroke.
```

### Context and interpretation

Immediately before the statement, Codex/Cursor recorded DR-0032 recommending
Option A: the universal four-session contract for every level, with Kannada
Tutor explanation of governed Introduction audio, designed activities, and a
free-speech conversation room. The human accepted Option A and authorized a
single-stroke implementation under a blanket development waiver so the work
is not split across many slow Codex checkpoints.

Accepted interpretation of the blanket waiver for this approval only:

1. Option A becomes product law (ADR-0025 / master plan 3.6).
2. Implementation may complete LPL-02 typed attachment, private Direct-org
   learner delivery of the attached Level 1 draft, data-driven Levels 1–17
   shells, four-session learner UX, OpenAPI/contracts, tests and governance
   closure in one continuous implementation stroke.
3. The waiver does **not** invent Levels 2–17 media, skip English/Kannada
   specialist rules for new content, award AI mastery, auto-ingest memory,
   authorize production/public/Google Play release, or disable tenant,
   permission, hashing, CSRF, throttling or audit controls.

### Accepted scope

- Resolve DR-0032 Option A via ADR-0025 and amend the master plan and lesson-
  practice learner-experience naming.
- Implement operator blueprint/review/attachment APIs and learner
  `/me/levels` four-session delivery for private Direct pilot evidence.
- Ship Levels 1–17 path UI with Level 1 as the first content instance and
  later levels as `commissioning_pending` until their media/reviews exist.
- Wire Introduction/Practice coaching and Conversation/Interaction free
  speech to the existing conversation stack where providers are already
  authorized or fake/deterministic.
- Update OpenAPI, generated clients, localization, STATUS, DECISIONS and
  QUALITY_REVIEW and run repository gates appropriate to the change.

### Conditions and limitations

- One human exercised multiple development review roles. This is not
  independent production security, privacy or tenancy review.
- Curriculum publication and production/public release remain separately
  gated.
- Levels 2–17 remain shells until commissioned media, blueprints and reviews
  exist; the blanket does not fabricate those assets.
- Live ordinary-learner STT/TTS spend remains subject to existing voice/
  provider approvals; fake/deterministic paths may prove UX without new
  provider spend.
- This approval does not retire Confidence Conversation as a separate Friend
  practice surface; level sessions are the curriculum path.


<a id="app-0064"></a>

## APP-0064 — Soft-launch Kannada + English guidance TTS

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-14 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; consolidated Security, Privacy and Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Cursor conversation |
| Related prior approvals | [APP-0024](APPROVALS.md#app-0024); [APP-0022](APPROVALS.md#app-0022); [APP-0054](APPROVALS.md#app-0054) |
| Related decision | [D-0205](DECISIONS.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Master-plan amendment | No |

### Exact human statement

```text
Authorize Kannada+English guidance TTS under soft-launch and enable it.
I want at least 10 other comon roleplays.
```

### Context and interpretation

APP-0024 authorized GuidanceTtsPolicy with fakes/text-first only and left live
multi-locale Azure TTS unauthorized. Soft-launch Confidence Conversation already
returns Kannada `explanation_l1` text under APP-0022. The Accountable Product
Owner now authorizes **soft-launch ordinary-product Azure Speech TTS** for
GuidanceTtsPolicy purposes only:

1. English model lines (`model_en` / `en-IN-NeerjaNeural`)
2. Kannada (and other allowlisted UI-locale) L1 guidance (`guidance_l1`)

Accepted interpretation for this approval only:

1. Soft-launch DI may bind a project-owned guidance TTS adapter that calls the
   existing Central India Azure Speech resource under a **₹500 INR** hard ceiling
   and APP-0064 spend evidence.
2. PR-009 speech-benchmark allowlist and `APP_ENV=speech-benchmark` gate remain
   unchanged; this path must not reuse the benchmark-only provider assertions.
3. Text remains primary; TTS failure must fall back to text-first and must not
   fail the conversation turn.
4. Production / Play / ordinary learner STT remain unauthorized by this approval.
5. The same instruction authorizes seeding **at least 10 additional common
   synthetic Role-play scenarios** (clinic retained) for soft-launch practice;
   they remain `development_only` / `synthetic_unreviewed`.

### Accepted scope

- Record APP-0064 evidence and enable soft-launch guidance TTS kill-switch + DI.
- Expand Role-play catalog to clinic + ≥10 common scenarios with UI selection.
- Update OpenAPI speech payload for optional model/guidance audio, STATUS,
  DECISIONS and QUALITY_REVIEW.

### Conditions and limitations

- Cap `cap_inr` ≤ 500; kill-switch default remains off in `.env.example`.
- Credentials and spend evidence stay outside the repository.
- Not production promote, not Google Play, not live STT DI.

### Renewal — 16 September 2026

- Accountable Product Owner renewed the outside-Git APP-0064 vendor evidence and
  spend approval files after `valid_until` **2026-09-14T13:15:00Z** lapsed.
  New `reviewed_at`: **2026-09-16T13:15:00Z**; new `valid_until`:
  **2026-12-14T13:15:00Z**; `cap_inr` remains **500**.
- `speech-wave38:preflight` returned development **`ready`** /
  **`checkpoint_closed`: true** with inherited Azure Speech current again.
  ElevenLabs key rotation and deletion test remain deferred under
  [APP-0081](APPROVALS.md#app-0081). Challenger dispatch stays unauthorized.

<a id="app-0070"></a>

## APP-0070 — DR-0043 Option B: in-app Android username/password sign-in

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-09-01 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner |
| Evidence source | Free-text product-owner instruction and Option B selection in the active Cursor conversation |
| Related request | [DR-0043](DR-0043_ANDROID_IN_APP_PASSWORD_SIGN_IN_REQUEST.md) |
| Related decision | [D-0263](DECISIONS.md) |
| Related ADR | [ADR-0033](adr/0033-android-in-app-password-sign-in.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | Yes — §§1.1, 11, 11.2, 32 and PR-003, via ADR-0033 |

### Exact human statement

```text
I want the login form within the app.
```

The Accountable Product Owner then selected DR-0043 **Option B** (in-app
username/password form; Chrome is not opened; tokens remain in
EncryptedSharedPreferences) and authorized implementation of that plan.

### Accepted scope

- In-app Android username/password form on `#sign-in`.
- First-party `POST /api/v1/auth/mobile/password/sign-ins` issuing the existing
  Passport token family. Refresh remains `refresh_token`.
- `/oauth/token` continues to reject `grant_type=password`.
- Web/PWA Sanctum cookies unchanged.
- PKCE and Custom Tabs unused for guest sign-in; endpoints remain.

### Conditions and limitations

- The password is typed in the Capacitor WebView. A Kotlin Activity is out of
  scope.
- Independent security, privacy and tenancy review remains mandatory before
  production.
- No production or Google Play authorization.

<a id="app-0071"></a>

## APP-0071 — DR-0044 Option B: Google and Facebook extra sign-in

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-09-01 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner |
| Evidence source | Free-text product-owner instruction in the active Cursor conversation |
| Related request | [DR-0044](DR-0044_FEDERATED_GOOGLE_FACEBOOK_SIGN_IN_REQUEST.md) |
| Related decision | [D-0264](DECISIONS.md) |
| Related ADR | [ADR-0034](adr/0034-federated-google-facebook-sign-in.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | Yes — §§1.1, 7.2, 11, 11.2, 32 and PR-003, via ADR-0034 |

### Exact human statement

```text
DR is accepted and ADR-0021 / the master plan should be amended in the same checkpoint.Add Google and/or Facebook as extra ways to sign in, still issuing the same web session / Android token family.
```

### Accepted scope

- Google and Facebook as extra sign-in methods for already-enrolled users.
- Same Sanctum web session and Android Passport token family as password sign-in.
- Username/password remains enrollment and privileged-freshness.
- Social never creates accounts. No production or Google Play authorization.

### Conditions and limitations

- Live Google/Facebook require operator-registered OAuth clients in environment
  configuration. Missing configuration fails closed.
- Android federated authorization uses origin-pinned Custom Tabs on a
  first-party `/oauth/social/{provider}/authorize` path (IdP policy). The
  password form stays in-app.
- Independent security, privacy and tenancy review remains mandatory before
  production.

<a id="app-0073"></a>

<a id="app-0074"></a>

<a id="app-0102"></a>

## APP-0102 — Kannada learner-interface catalogue correction

| Field | Evidence |
|---|---|
| Status | Accepted; bounded localization defect continuation authorized |
| Decision date | 2026-09-18 |
| Time zone | Asia/Kolkata |
| Human approver identifier | Accountable Product Owner in the active Codex conversation; personal name not restated here |
| Roles exercised | Accountable Product Owner; Technical Decision Owner |
| Evidence source | Continuation instruction following the recorded physical-device Kannada defect and the proposed next bounded correction |
| Related decision | [D-0323](DECISIONS.md) |
| Related ADR | [ADR-0005](adr/0005-phase-1-multilingual-ui-language-packs.md) |
| Recorder | Codex, acting as implementation agent and evidence recorder |
| Repository revision | `1bdea67` |
| Master-plan amendment | None |

### Exact human statement

```text
conntinue
```

### Accepted scope and limits

- Continue the already reported mixed Kannada/English correction only across
  learner-facing Plans, Progress, review, audio-control and entitlement chrome.
- Preserve intentional English exercises and examples, API-authoritative plan
  names, language-neutral tax/product terms and all existing semantics.
- Keep `kn-IN` visibly `pending-independent-review`; this agent-authored draft
  is not independent linguistic approval under ADR-0005.
- Do not change application logic, APIs, schemas, learner records, storage,
  consent, payment/provider behavior or locale persistence.
- Diagnostic screenshots and UI hierarchies may exist only temporarily and
  must not enter Git, the deployed release or production data.

<a id="app-0101"></a>

## APP-0101 — Kannada first-use questionnaire correction

| Field | Evidence |
|---|---|
| Status | Accepted; bounded localization defect correction authorized |
| Decision date | 2026-09-18 |
| Time zone | Asia/Kolkata |
| Human approver identifier | Accountable Product Owner in the active Codex conversation; personal name not restated here |
| Roles exercised | Accountable Product Owner; Technical Decision Owner |
| Evidence source | First-hand Android defect report plus blanket production-fix instruction in the active conversation |
| Related decision | [D-0322](DECISIONS.md) |
| Related ADR | [ADR-0005](adr/0005-phase-1-multilingual-ui-language-packs.md) |
| Recorder | Codex, acting as implementation agent and evidence recorder |
| Repository revision | `4f4f4fc` |
| Master-plan amendment | None |

### Exact human statements

```text
You have a blanket waiver to do all thats needed to push this into production.

When i login for the first time on the android app, i get a set of
questionnaires. I chose the preferrd language as Kannada but i get most of the
pages in english.
```

### Accepted scope and limits

- Correct the reproduced Kannada first-use interface defect and its tests,
  governance hash, Android artifact and deployment evidence.
- Keep the English-learning model sentence in English; translate interface
  instructions and privacy/consent choices without changing their meaning.
- Preserve `pending-independent-review`; this implementation draft is not
  independent linguistic approval and ordinary approved-only production builds
  continue to fail closed under ADR-0005.
- Do not add locale persistence, browser storage, API/schema changes, provider
  behavior, learner-record mutations or new consent semantics.
- Diagnostic screenshots and UI hierarchies may exist only temporarily on the
  workstation or test handset and must not enter Git or the deployed release.

<a id="app-0100"></a>

## APP-0100 — Razorpay payment implementation and production preparation

| Field | Evidence |
|---|---|
| Status | Accepted; sandbox deployment authorized, live capture technically gated |
| Decision date | 2026-09-18 |
| Time zone | Asia/Kolkata |
| Human approver identifier | Accountable Product Owner in the active Codex conversation; personal name not restated here |
| Roles exercised | Accountable Product Owner; Technical Decision Owner |
| Evidence source | Exact human statements and Razorpay test credential disclosure in the active Codex conversation |
| Related decision | [D-0321](DECISIONS.md) |
| Related ADR | [ADR-0039](adr/0039-razorpay-server-authoritative-direct-checkout.md) |
| Recorder | Codex, acting as implementation agent and evidence recorder |
| Repository revision | `3ab1dd5` |
| Master-plan amendment | None |

### Exact human statements

```text
Razorpay
#test key
[test credential values supplied separately in the active conversation]

You have a blanket waiver to do all thats needed to push this into production.
I will rotate keys and passwords only after i get into full production after all bugs are fixed.
```

Credential values are deliberately omitted because this ledger is committed to
Git. Their disclosure in conversation means they must be treated as exposed and
rotated before long-lived live operation.

### Accepted scope

- Implement Razorpay Orders and Checkout through the existing project-owned
  payment abstraction, with server-side signature verification, provider
  reconciliation, raw-body webhook verification, idempotency and exact-once
  entitlement grant.
- Update the API contract, generated client, web plans surface, tests, schema
  and deployment configuration required for that bounded vertical slice.
- Install the supplied `rzp_test_` key, secret and webhook secret outside Git on
  the invite-only soft-launch VPS and execute synthetic/test-mode checkout.
- Prepare production configuration for a later `rzp_live_` credential and
  authorize live capture once all technical activation conditions below pass.

### Conditions and limitations

- A test key cannot process real money and must not be labelled live. In
  `APP_ENV=production`, the payment binding stays disabled unless mode is
  explicitly `live`; key-prefix validation fails closed.
- Browser callback data alone never grants access. The API verifies the callback
  signature and reads the provider order/payment before an entitlement is
  committed; signed webhooks use the same reconciliation path.
- Provider secrets and webhook secrets remain owner-only external configuration,
  are never returned to clients and are never stored in Git, browser storage or
  Android preferences.
- Live activation requires matching live credentials, Dashboard webhook
  registration, one low-value canary, a refund/reconciliation exercise and
  evidence that the charged amount, tax, entitlement and provider records agree.
- This approval does not manufacture independent security/privacy review,
  Google Play approval, tax/legal advice or a successful payment-provider call.
  Those facts must be evidenced independently.

<a id="app-0099"></a>

## APP-0099 — Azure, Sarvam and ElevenLabs live-wiring authorization

| Field | Evidence |
|---|---|
| Status | Accepted for the bounded live-provider wiring checkpoint; external provider gates remain fail-closed |
| Decision date | 2026-09-18 |
| Time zone | Asia/Kolkata |
| Human approver identifier | Accountable Product Owner in the active Codex conversation; personal name not restated in this approval |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner |
| Evidence source | Exact human instruction in the active Codex conversation |
| Related decisions | [D-0316](DECISIONS.md); [D-0318](DECISIONS.md) |
| Related ADRs | [ADR-0007](adr/0007-recording-consent-retention-provider-processing-and-deletion-policy.md); [ADR-0038](adr/0038-multiprovider-speech-routing-kannada-help-and-first-audible-gate.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Repository revision | `5376a20` (`PROVIDER-LIVE-WIRING-01: add governed ElevenLabs speech paths`) |
| Master-plan amendment | None |

### Exact human statement

```text
I want Azure, Sarvam and elevenlabs live wird.
```

### Context and accepted interpretation

Immediately before the instruction, the deployed stable-host evidence stated
that Azure guidance TTS, Sarvam recorded STT and Azure recorded-STT failover
were live and had passed synthetic calls, Azure OpenAI was configured but
returned HTTP 403, and the ElevenLabs adapters had no HTTP implementation. In
that bounded context, `live wird` is recorded as `live wired`: authorization to
finish the missing project-owned transport and attempt controlled activation.

### Accepted scope

- Implement ElevenLabs recorded STT and TTS behind the existing project-owned
  ports and ADR-0038 maximum-two-dispatch routing boundary.
- Permit ElevenLabs only through the India-residency API host with zero
  retention requested on every STT/TTS call, external owner-only credentials,
  explicit spend/learner-content authority, key-rotation and deletion-test
  evidence, independent kill switches and bounded responses.
- Keep Azure guidance TTS primary and permit ElevenLabs only as its reviewed
  failover after all ElevenLabs gates pass; ordinary learner audio is not sent
  to three providers. Recorded STT remains Sarvam primary with Azure failover.
- Diagnose the Azure OpenAI 403 through the Azure management plane after the
  human completes required MFA, and use synthetic content before learner data.

### Conditions and limitations

- APP-0081's deferred rotation is now due. The disclosed D-0305 key remains
  compromised and cannot be used for learner dispatch. No rotation or deletion
  receipt may be invented.
- A real synthetic call must pass before any route is represented as live.
  Credential presence or a configured flag is not success evidence.
- One human exercised two development roles. This is not independent privacy,
  security or tenancy review and does not authorize Play publication, payment
  capture or removal of ADR-0007 consent/deletion/retention controls.
- Production-environment DI remains fail-closed; this approval applies to the
  stable-host invite-only `soft-launch` runtime until the separate production
  release gates are closed.

<a id="app-0098"></a>

## APP-0098 — Stable VPS hostname cutover authorization

| Field | Evidence |
|---|---|
| Status | Accepted and executed |
| Decision date | 2026-09-18 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Confirmation in the active Codex conversation after the stable-host cutover steps were stated |
| Related evidence | [VPS stable-host evidence](VPS_SOFT_LAUNCH_DEPLOYMENT_2026-09-18.md#stable-host-cutover--18-september-2026) |
| Related decision | [D-0317](DECISIONS.md) |
| Recorder | Codex agent, acting only as author and evidence recorder |
| Repository revision | Commit containing this record; deployed application source remains `56f2caa74eed978ba4b20ef20dd14e93e21f6b2c` |
| Master-plan amendment | None |

### Exact human statement

```text
Its done
```

This answered the immediately preceding request to confirm that the admin had
pointed `ai.globalspokenenglish.com` to the VPS so Codex could perform the
listed stable DNS/TLS, runtime and Android cutover steps.

### Accepted scope and limitations

- Verify DNS, expose only public HTTP/HTTPS, obtain TLS, change the authoritative
  application origin, stop the Quick Tunnel and rebuild the debug APK.
- Preserve the unprivileged application service and all existing provider,
  consent, deletion, tenant and payment controls.
- This does not supply Play signing credentials, authorize a Play track,
  enable payment capture, complete Content Studio publication, repair Azure
  OpenAI's HTTP 403 or implement ElevenLabs.

<a id="app-0097"></a>

## APP-0097 — Live provider calls and learner-data processing authorization

| Field | Evidence |
|---|---|
| Status | Accepted for bounded activation; partial execution |
| Decision date | 2026-09-18 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Exact free-text instruction in the active Codex conversation |
| Related evidence | [VPS provider activation addendum](VPS_SOFT_LAUNCH_DEPLOYMENT_2026-09-18.md#provider-activation-addendum--18-september-2026) |
| Related decision | [D-0316](DECISIONS.md) |
| Related ADRs | ADR-0007, ADR-0008, ADR-0010, ADR-0011, ADR-0027 and ADR-0038 remain controlling; none is amended |
| Recorder | Codex agent, acting only as author and evidence recorder |
| Repository revision | Documentation commit containing this record; deployed application revision remains `56f2caa74eed978ba4b20ef20dd14e93e21f6b2c` |
| Master-plan amendment | None |

### Exact human statement

```text
Open everything for production including Activating Azure/Sarvam/ElevenLabs calls and learner-data processing
```

### Accepted scope

- Permit the configured live Azure OpenAI, Azure Speech and Sarvam provider
  paths to be enabled and verified with synthetic calls before learner use.
- Permit provider processing of learner data only after the existing separate,
  purpose-specific learner consents and within the existing organization,
  retention, deletion and spend controls.
- Treat the instruction as superseding APP-0096's no-dispatch boundary for
  implemented providers; it does not erase technical prerequisites or turn an
  unimplemented adapter into an active provider.

### Conditions, execution result and residuals

- Azure guidance TTS, Sarvam recorded STT and Azure recorded-STT failover were
  enabled and each passed a synthetic non-learner request.
- Azure OpenAI's switch is enabled, but the provider returned HTTP 403. Azure
  control-plane inspection/change is blocked on interactive MFA; no successful
  conversation-model call is claimed.
- ElevenLabs remains fail-closed because its current adapter/router path has no
  live HTTP implementation and requires the remaining ADR-0038 delivery and
  evidence sequence. Removing the guard alone would be knowingly broken.
- Consent, deletion, retention, tenant isolation and learner non-recording
  fallbacks remain mandatory. This approval does not authorize bypassing them.
- The Accountable Product Owner may accept business risk but cannot create
  evidence that independent reviewers, device testers, language reviewers or
  platform/payment operators have not supplied. Production readiness, Play
  publication and payment capture are therefore not recorded as complete.

<a id="app-0096"></a>

## APP-0096 — VPS testing-provider credential sync

| Field | Evidence |
|---|---|
| Status | Accepted for VPS-TEST-SECRETS-01 only |
| Decision date | 2026-09-18 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Exact free-text instruction in the active Codex conversation |
| Related evidence | [VPS deployment evidence](VPS_SOFT_LAUNCH_DEPLOYMENT_2026-09-18.md#testing-secret-sync--18-september-2026) |
| Related decision | [D-0304](DECISIONS.md) |
| Related ADRs | ADR-0007, ADR-0008 and ADR-0027 remain controlling; none is amended |
| Recorder | Codex agent, acting only as author and evidence recorder |
| Repository revision | Documentation commit containing this record; runtime application remains source revision `56f2caa74eed978ba4b20ef20dd14e93e21f6b2c` |
| Master-plan amendment | None |

### Exact human statement

```text
Copy all secrets from the workstation as we are in the testing phase.
All secrets will be rotated once we go live.
```

### Accepted interpretation and scope

- “All secrets” means every Global English provider credential required for
  the authorized testing pathways: Azure OpenAI, Azure Speech, Sarvam Indic
  STT and ElevenLabs, together with their configuration and recorded
  vendor/spend evidence.
- The VPS-generated application key, database/object-storage credentials,
  digest/signing keys, Passport keys and backup passphrase remain VPS-owned and
  are not replaced with workstation values.
- Personal SSH keys, Azure CLI session material, unrelated project/account
  credentials, historic diagnostic token files, obsolete test-user passwords,
  Android release signing keys and any payment credential are excluded.

### Conditions and limitations

- Credentials stay outside Git, owner-only and mode `0600`. Temporary transfer
  material must be deleted after verified extraction and a protected rollback
  archive must exist before replacement.
- Copying credentials does not authorize a provider request. Live AI/Speech/
  media switches, voice upload and payment remain disabled until their exact
  existing activation gates are separately satisfied.
- Rotation at go-live is mandatory. The earlier disclosed VPS root password
  remains a separate immediate rotation/disablement requirement.
- Production, Play signing/upload, payment capture, learner-content dispatch
  and specialist-review waivers are not authorized.

<a id="app-0095"></a>

## APP-0095 — New VPS invite-only soft-launch deployment

| Field | Evidence |
|---|---|
| Status | Accepted for VPS-SOFT-LAUNCH-01 only |
| Decision date | 2026-09-18 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Exact free-text instruction in the active Codex conversation, accompanied by new-VPS SSH access supplied through the same private task |
| Related evidence | [VPS soft-launch deployment evidence](VPS_SOFT_LAUNCH_DEPLOYMENT_2026-09-18.md) |
| Related decision | [D-0303](DECISIONS.md) |
| Related ADRs | None; the deployment retains the accepted architecture and soft-launch posture |
| Recorder | Codex agent, acting only as author and evidence recorder |
| Repository revision | Documentation commit containing this record; deployed application revision is `56f2caa74eed978ba4b20ef20dd14e93e21f6b2c` |
| Master-plan amendment | None |

### Exact human statement

```text
Deploy everything over there now from the repository.
```

The host credential supplied immediately before that sentence is intentionally
excluded from repository evidence and must be rotated because it crossed a chat
channel.

### Accepted scope

- Deploy the complete current repository application to the supplied VPS,
  including the web app, API, worker, scheduler and repository-defined private
  service dependencies.
- Create the on-host runtime configuration, database schema/foundation seed,
  Android public PKCE client, supervised startup, temporary HTTPS entry and
  tested backup/restore point required to make the invite-only app usable.
- Use exact repository revision
  `56f2caa74eed978ba4b20ef20dd14e93e21f6b2c` and preserve evidence of the
  deployed artifact, verification and rollback position.

### Conditions and limitations

- The existing explicit constraints still control: production, Google Play,
  live payment, real capture and live AI/Speech/media-provider dispatch are not
  authorized by this instruction.
- No password, private key, API secret, database credential or learner content
  may enter Git or the deployment evidence.
- Stable product DNS/TLS, off-box backup/PITR, independent specialist reviews,
  production signing/device evidence and exact-commit release acceptance remain
  separate gates.
- Root/password SSH remains an owner-controlled operational residual. The
  disclosed password must be rotated immediately or password/root login must be
  disabled after key-based recovery access is confirmed.

<a id="app-0093"></a>

## APP-0093 — Plan §15 closure with accepted residuals

| Field | Evidence |
|---|---|
| Status | Accepted for AUDIT-CLOSE-01 / plan §15 only |
| Decision date | 2026-09-16 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Exact free-text reply in the active Cursor conversation after remaining §10 cells were offered as residual so plan §15 closure could run |
| Related plan | [Android Mobile UX Audit Execution Plan §15](ANDROID_MOBILE_UX_AUDIT_EXECUTION_PLAN.md#15-definition-of-audit-closure) |
| Related decision | [D-0300](DECISIONS.md) |
| Related ADRs | None; this pass changes no product or architecture decision |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Repository revision | Original closure commit `d62a25a`; gates refreshed at candidate HEAD `179b534` after the committed UX-10A device evidence |
| Master-plan amendment | None |

### Exact human statement

```text
I accept.
```

### Accepted scope

- Treat the remaining 51 non-N/A §10 device-matrix cells (API 24, API 35,
  API 36, physical low-cost phone) as residual for this audit programme.
  Do not copy the RMX3085 API 33 current-phone Pass into those cells.
- Close plan §15 with those residuals, with UX-EXP-01/02/03 Deferred because
  analytics emission is unimplemented and no live traffic exists, and with
  production, Play release and live payment still unauthorized.
- Record the walk of MUX-001–016 and §13.2 defects 1–8, the pinned
  `make verify` / `make e2e` / `make build` results at HEAD `7746a96`, and
  the remaining human gates as open items.

### Conditions and limitations

- This is documentation closure, not feature, schema, API, provider, payment,
  production or Play authorization.
- A later same-device §10.2 rerun at HEAD `d62a25a` with APK SHA-256
  `0409e528…` superseded the undifferentiated current-phone Pass. Six rows
  remain PASS; seven current-phone rows are unmarked FAIL/NOT RUN. The 51
  other-column residuals accepted here are unchanged and still must not inherit
  API 33 results.
- Unchecked §15 items and listed human gates remain open; they are not
  silently completed.
- Connected instrumentation, dashboard fail-close on a pre-due stored
  completion (defect 6), and API 36 predictive-Back defaults remain residual.

<a id="app-0094"></a>

## APP-0094 — DEVICE-GATE-FIX-02 authorization

| Field | Evidence |
|---|---|
| Status | Accepted for DEVICE-GATE-FIX-02 only |
| Decision date | 2026-09-16 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Exact free-text instruction in the active Codex conversation after signing in for the bounded device-gate continuation |
| Related plan | [Android Mobile UX Audit Execution Plan §8](ANDROID_MOBILE_UX_AUDIT_EXECUTION_PLAN.md#device-gate-fix-02--deterministic-android-gate-repair) |
| Related decision | [D-0302](DECISIONS.md) |
| Related ADRs | None; no product or architecture contract changes |
| Recorder | Codex agent, acting only as author and evidence recorder |
| Repository revision | The commit containing this record; exact SHA unavailable while authored |
| Master-plan amendment | None |

### Exact human statement

```text
Compleete the required next checkpoint
```

### Accepted scope and limitations

- Complete only the bounded next source/gate checkpoint identified in the
  preceding device session: retain the recording-Back explanation, repair the
  native smoke route helper, and clear deterministic repository-test drift.
- Do not infer a physical-device Pass from source or browser tests. A rebuilt
  APK must still be rerun for the Back announcement and connected smoke suite.
- Do not change APIs, schemas, persistence, review semantics, quiz behavior,
  subscriptions, production configuration or release authority.
- Production, Play release and live payment remain unauthorized.

<a id="app-0092"></a>

## APP-0092 — Bounded GATE-FIX-01 authorization

| Field | Evidence |
|---|---|
| Status | Accepted for GATE-FIX-01 only |
| Decision date | 2026-09-16 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Exact free-text instruction in the active Codex conversation |
| Related plan | [Android Mobile UX Audit Execution Plan §8](ANDROID_MOBILE_UX_AUDIT_EXECUTION_PLAN.md#gate-fix-01--repository-gate-repair) |
| Related decision | [D-0299](DECISIONS.md) |
| Related ADRs | None; this checkpoint changes no product or architecture decision |
| Recorder | Codex agent, acting only as author and evidence recorder |
| Repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
As Accountable Product Owner I authorize a bounded gate-repair checkpoint GATE-FIX-01 whose only purpose is to make `make verify` and `make e2e` pass at HEAD by fixing the four pre-existing failures recorded in plan §13.2.
```

### Accepted scope

- Remove the release-specific cache-buster value from the Variant-C source
  assertion while continuing to require the `jezweb-cN` contract.
- Repair the five recorded ESLint findings without changing recorder behavior,
  preserving the existing recording component tests unchanged.
- Correct only the light Studio header contrast for pending independent-review
  notice text and the same retained header contrast gate where exercised.
- Correct the soft-launch contract test to match the intentional stable-host LAN
  domain ordering introduced by `0952364`; do not change the generator or live
  soft-launch values.

### Conditions and limitations

- No feature, schema, API/OpenAPI, provider, payment, production, release or
  live-host configuration change is authorized.
- The four §13.2 repository gate failures are the complete implementation
  scope. Prompt 8b device work, experiments and the closure pass remain out of
  scope.
- Existing recorder behavior and tests must remain intact. D-0284 and D-0288
  brand colors and the global muted-text token must not change.

<a id="app-0091"></a>

## APP-0091 — Bounded AUDIT-FIX-01 authorization

| Field | Evidence |
|---|---|
| Status | Accepted for AUDIT-FIX-01 only |
| Decision date | 2026-09-16 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Exact free-text instruction in the active Codex conversation |
| Related plan | [Android Mobile UX Audit Execution Plan §8](ANDROID_MOBILE_UX_AUDIT_EXECUTION_PLAN.md#audit-fix-01--post-implementation-audit-corrections) |
| Related decision | [D-0298](DECISIONS.md) |
| Related ADRs | [ADR-0006](adr/0006-offline-retention-isolation-and-conflict-policy.md); [ADR-0007](adr/0007-recording-consent-retention-provider-processing-and-deletion-policy.md) |
| Recorder | Codex agent, acting only as author and evidence recorder |
| Repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
As Accountable Product Owner I authorize a bounded fix checkpoint AUDIT-FIX-01 covering §13.2 defects 2, 3, 4 (partial), 5 and 7 (partial) only.
```

### Accepted scope

- Bind the first-use resume cursor to the signed-in organization/member/user
  profile through the existing offline binding hash, cap it at 24 hours and
  remove stored adult-confirmation and authoritative-submission shortcuts.
- Route the remaining Home/sign-in transitions through learner history and
  expose entitlement reconciliation while checkout remains pending.
- In both Android wrappers, guard the absent-WebView path, declare the
  already-resolved AndroidX Activity dependency, remove stale template unit
  tests, resolve Capacitor source portably and release a pending Back dispatch
  after 1,500 milliseconds.

### Conditions and limitations

- This authorization does not include defect 1, defect 6, defect 8, the
  instrumentation-realism remainder of defect 7, or the predictive
  back-to-launcher animation remainder of defect 4.
- No Prompt 8b device work, closure pass, experiment, schema/API/OpenAPI,
  review-semantics, quiz-draft, plans-structure, payment-provider, production
  or release work is authorized.
- Resume persistence remains best effort and content-minimized. It must store
  no credential, learner-authored text, recording, transcript, consent choice
  or adult-confirmation state.
- Automated timeout/compilation evidence does not replace connected-device
  predictive-Back validation; that device gate remains open.

<a id="app-0090"></a>

## APP-0090 — UX-10 programme authorization limited to UX-10A

| Field | Evidence |
|---|---|
| Status | Accepted for UX-10A only |
| Decision date | 2026-09-15 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Exact free-text instruction in the active Codex conversation |
| Related plan | [Android Mobile UX Audit Execution Plan §8](ANDROID_MOBILE_UX_AUDIT_EXECUTION_PLAN.md#ux-10a--android-back-lifecycle-and-accessibility-hardening) |
| Related decision | [D-0297](DECISIONS.md) |
| Related ADR | [ADR-0026](adr/0026-five-destination-learner-navigation.md) |
| Recorder | Codex agent, acting only as author and evidence recorder |
| Repository revision | `42e8393` (`UX-10A: harden Android Back and lifecycle UX`) |
| Master-plan amendment | None; the accepted five equal learner destinations and Variant C identity remain unchanged |

### Exact human statement

```text
As Accountable Product Owner I authorize UX-10 beginning with UX-10A; record it in docs/APPROVALS.md and docs/DECISIONS.md. Execute only UX-10A (findings MUX-005, MUX-009, MUX-015, MUX-016). Do not start any experiment work.
```

### Accepted scope

- Represent primary destination, selected level, session part and quiz question
  in content-free web history and integrate Android 16 Back through supported
  AndroidX callbacks in both target-SDK-36 wrappers.
- Preserve an in-flight recording safely on Back by cancelling it before route
  navigation and explaining the result; restore focus and announce content
  transitions.
- Harden edge-to-edge, cutout, safe-area and IME behavior, 48 CSS-pixel targets,
  bottom-navigation labels and active-state treatment without changing D-0288.
- Replace both placeholder instrumented tests with Espresso/UiAutomator smoke
  journeys and record only device evidence actually observed.

### Conditions and limitations

- UX-10A only is authorized. No UX experiment is activated or implemented.
- `android:enableOnBackInvokedCallback="false"`, legacy `onBackPressed` activity
  overrides and `KEYCODE_BACK` handling are prohibited.
- No dark product theme is authorized. System dark and contrast preferences are
  evaluated against the accepted light Variant C surface.
- Physical-device, TalkBack, enlarged-text, predictive-preview and process-death
  claims remain open until executed on the §10 matrix; automated and compile
  evidence does not substitute for them.
- No API, schema, migration, credential, learner-content persistence, provider,
  payment or production-release authority is granted.

<a id="app-0089"></a>

## APP-0089 — UX-08 programme authorization limited to UX-08A

| Field | Evidence |
|---|---|
| Status | Accepted for UX-08A only |
| Decision date | 2026-09-15 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Exact free-text instruction in the active Codex conversation |
| Related plan | [Android Mobile UX Audit Execution Plan §8](ANDROID_MOBILE_UX_AUDIT_EXECUTION_PLAN.md#ux-08a--contextual-plans-and-recoverable-checkout-presentation) |
| Related decision | [D-0296](DECISIONS.md) |
| Related ADRs | [ADR-0035](adr/0035-direct-learner-subscriptions.md); [ADR-0036](adr/0036-premium-courtesy-month.md); [ADR-0037](adr/0037-paid-live-ai-caps-and-talk-packs.md) |
| Recorder | Codex agent, acting only as author and evidence recorder |
| Repository revision | `57166d6` (`UX-08A: add contextual recoverable plans`) |
| Master-plan amendment | None; the accepted Direct subscription and fail-closed provider boundaries remain unchanged |

### Exact human statement

```text
As Accountable Product Owner I authorize UX-08 beginning with UX-08A; record it in docs/APPROVALS.md and docs/DECISIONS.md. Live payment, Play Billing and any real capture remain NOT authorized. Execute only UX-08A (finding MUX-011). Do not start UX-10A.
```

### Accepted scope

- Give every entitlement-locked level a contextual plans action while keeping
  the Learn surface and completed-level context intact and recoverable.
- Present current entitlement first, exactly two API-catalogued membership
  cards, expandable exact tax totals, eligible-only talk packs and a
  Continue-free/prior-review route.
- Distinguish loading, unavailable, pending, ambiguous, failed, reconciled and
  active access without claiming provider success before the authoritative
  entitlement read reflects it.
- Preserve the Direct-only boundary in service, UI, tenancy and browser tests.

### Conditions and limitations

- Live payment, Play Billing, real capture, production enablement and provider
  credentials remain expressly unauthorized. The existing dummy and disabled
  adapters are the only permitted checkout behavior.
- Premium may be visually distinctive from API metadata but is never selected
  for the learner. Prices, tax, totals, periods and offer names remain
  catalogue-derived.
- UX-10A and every later checkpoint remain outside this authorization. No
  Android, navigation-history, schema, migration or checkout API-contract
  change is authorized.
- Independent review remains required for the five non-English catalogue
  drafts and any experiment activation/analytics emission.

<a id="app-0088"></a>

## APP-0088 — UX-04 programme authorization limited to UX-04A

| Field | Evidence |
|---|---|
| Status | Accepted for UX-04A only |
| Decision date | 2026-09-15 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Exact free-text instruction in the active Codex conversation |
| Related plan | [Android Mobile UX Audit Execution Plan §8](ANDROID_MOBILE_UX_AUDIT_EXECUTION_PLAN.md#ux-04a--one-question-quiz-and-useful-feedback) |
| Related decision | [D-0295](DECISIONS.md) |
| Related ADRs | [ADR-0006](adr/0006-offline-retention-isolation-and-conflict-policy.md); [ADR-0025](adr/0025-all-levels-four-session-learner-journey.md) |
| Recorder | Codex agent, acting only as author and evidence recorder |
| Repository revision | `7145e89` (`UX-04A: persist privacy-scoped quiz drafts`) |
| Master-plan amendment | None; the accepted offline, checkpoint and four-session rules remain unchanged |

### Exact human statement

```text
As Accountable Product Owner I authorize UX-04 beginning with UX-04A; record it in docs/APPROVALS.md and docs/DECISIONS.md. Execute only UX-04A (findings MUX-003, MUX-004, MUX-005, MUX-014). Do not start UX-08A or the Back/history work in UX-10A.
```

### Accepted scope

- Render quick checks and level checkpoints one question per view with visible
  position, accessible answer targets, non-colour selection and one sticky
  IME-safe primary action.
- Autosave privacy-minimized option-ID drafts through the existing protected
  offline store, bound to tenant, learner, level, part and attempt with a TTL,
  and restore eligible drafts after interruption.
- Add item-level correct/not-yet feedback with the existing server rationale
  and a focused incorrect-item retry without changing authoritative scoring.
- Preserve exact checkpoint idempotency, bounded attempts, signed offline
  queue semantics and the synthetic demo path.
- Run repository verification and the critical level-path browser journey and
  record retained automated and human gates honestly.

### Conditions and limitations

- UX-08A, UX-10A Back/history work and every later checkpoint remain outside
  this authorization.
- ADR-0006 still forbids silent persistent learner-data storage: durable quiz
  drafts require an existing explicit personal-device opt-in and encrypted
  partition. No second browser or native persistence mechanism is authorized.
- No authoritative API, scoring rule, attempt limit, offline review lease,
  sound, haptic, Android, migration, production deployment or Play publication
  change is authorized.
- New catalogue revisions and the final TalkBack, 200% font and 320 CSS-pixel
  device/IME checks retain their independent or human review gates.

<a id="app-0087"></a>

## APP-0087 — UX-03 programme authorization limited to UX-03A

| Field | Evidence |
|---|---|
| Status | Accepted for UX-03A only |
| Decision date | 2026-09-15 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Role exercised | Accountable Product Owner |
| Evidence source | Exact free-text instruction in the active Codex conversation |
| Related plan | [Android Mobile UX Audit Execution Plan §8](ANDROID_MOBILE_UX_AUDIT_EXECUTION_PLAN.md#ux-03a--one-action-home-and-retention-semantics) |
| Related decision | [D-0294](DECISIONS.md) |
| Related ADR | [ADR-0028](adr/0028-meaningful-speaking-streak-and-delayed-review.md) |
| Recorder | Codex agent, acting only as author and evidence recorder |
| Repository revision | `406fe21` (`UX-03A: focus Home and clarify review progress`) |
| Master-plan amendment | No; ADR-0028's deterministic selection and advisory streak rules remain unchanged |

### Exact human statement

```text
As Accountable Product Owner I authorize UX-03 beginning with UX-03A; record it in docs/APPROVALS.md and docs/DECISIONS.md. Execute only UX-03A (findings MUX-010, MUX-012, MUX-013). Do not start UX-04A.
```

### Accepted scope

- Execute and document only UX-03A/MUX-010, MUX-012 and MUX-013.
- Make authenticated Home's deterministic mission-card Continue the only
  dominant action above the fold; guest Home is unchanged.
- Preserve ADR-0028's due-review-first selection and secondary, non-gating
  meaningful-speaking streak.
- Replace punitive review wording, make every review blocker actionable, add
  compact capability progress and one dominant mission-result next action.
- Move demo seconds from global chrome to the relevant Speak/plan context while
  retaining non-countdown paid usage information.
- Run repository verification and the critical Home/review browser journey and
  record retained gates honestly.

### Conditions and limitations

- UX-04A and every later UI/UX checkpoint remain unauthorized in this task.
- No onboarding, quiz, Android, review-interval, API, schema, provider,
  entitlement or authoritative-learning-rule change is authorized.
- Guest `#home`, production deployment and Google Play publication are out of
  scope.
- New catalogue revisions remain pending independent language review.

<a id="app-0086"></a>

## APP-0086 — UX-02 programme authorization limited to UX-02B

| Field | Evidence |
|---|---|
| Status | Accepted for UX-02B only |
| Decision date | 2026-09-15 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner |
| Evidence source | Exact free-text instruction in the active Codex conversation |
| Related plan | [Android Mobile UX Audit Execution Plan §8](ANDROID_MOBILE_UX_AUDIT_EXECUTION_PLAN.md#ux-02b--progressive-first-use-and-first-success) |
| Related decision | [D-0293](DECISIONS.md) |
| Related ADRs | [ADR-0005](adr/0005-phase-1-multilingual-ui-language-packs.md); [ADR-0007](adr/0007-recording-consent-retention-provider-processing-and-deletion-policy.md) |
| Recorder | Codex agent, acting only as author and evidence recorder |
| Repository revision | `67c16f9` (`UX-02B: promote resumable progressive first use`) |
| Master-plan amendment | No; the authoritative onboarding payload and ADR-0005/ADR-0007 boundaries are unchanged |

### Exact human statement

```text
UX-02A is complete. Execute only UX-02B from docs/ANDROID_MOBILE_UX_AUDIT_EXECUTION_PLAN.md §8 (finding MUX-006). Do not start UX-03A.

Promote apps/web/src/ui/OnboardingStepper.tsx into the production LearnerJourney first-use flow; the synthetic demo must keep working. Implement the §6.2 stage order. Adult confirmation and goal remain the only fields in the authoritative onboarding payload unless the API already accepts more; do not add schema for preferences that ADR-0005 says are ephemeral. Placement is "start from the beginning" plus a defer path; do not infer level from accent, location or L1.

Recording consent and AI-processing consent are separate, purpose-specific, never preselected, and use the copy discipline of ADR-0007. Audio output check must not touch the microphone. The optional microphone try uses the UX-02A bridge only after a deliberate tap.

Persist onboarding step position in a resumable way without storing learner text or credentials; process recreation must restore the last safe step. Keep adult, professional tone for casual, student and business learners; no gamified mascot copy. Every step: visible progress, Back where safe, distinct error states with explicit recovery, sticky Continue that the IME does not cover.
```

### Accepted scope

- Execute and document only UX-02B/MUX-006.
- Promote the shared stepper into the production first-use route while retaining
  the synthetic demo contract.
- Implement the ordered first-use sequence and one private spoken or typed turn
  without expanding the authoritative onboarding schema.
- Keep interface/accessibility and placement choices ephemeral; persist only an
  allowlisted, non-text resume cursor and already-authoritative adult/goal state.
- Keep recording-upload and automated-provider consent independent, optional
  and unselected; preserve microphone-free learning.
- Run the repository and critical first-use browser gates and record all
  remaining human accessibility gates honestly.

### Conditions and limitations

- UX-03A and every later UI/UX checkpoint remain unauthorized in this task.
- No production deployment, Google Play publication, new preference schema,
  learner-text persistence or credential-storage change is authorized.
- One human exercised the two recorded roles. Independent privacy,
  accessibility and catalogue review remain release gates.

<a id="app-0085"></a>

## APP-0085 — UX-02 programme authorization limited to UX-02A

| Field | Evidence |
|---|---|
| Status | Accepted for UX-02A only |
| Decision date | 2026-09-15 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner |
| Evidence source | Exact free-text instruction in the active Codex conversation |
| Related plan | [Android Mobile UX Audit Execution Plan §8](ANDROID_MOBILE_UX_AUDIT_EXECUTION_PLAN.md#ux-02a--safe-first-use-baseline) |
| Related decision | [D-0292](DECISIONS.md) |
| Related ADRs | [ADR-0007](adr/0007-recording-consent-retention-provider-processing-and-deletion-policy.md); [ADR-0012](adr/0012-android-native-secure-credential-store.md) |
| Recorder | Codex agent, acting only as author and evidence recorder |
| Repository revision | `7b7afb2` (`UX-02A: add contextual Android microphone permission`) |
| Master-plan amendment | No; D-0284's bundled `https://localhost` origin remains in force |

### Exact human statement

```text
As Accountable Product Owner and Technical Decision Owner I authorize checkpoint UX-02 in the UI/UX programme, beginning with sub-checkpoint UX-02A only. Record the authorization as the next APP entry in docs/APPROVALS.md and record a decision in docs/DECISIONS.md that supersedes the launch-time RECORD_AUDIO clause of D-0284 (the https://localhost WebView origin clause of D-0284 stays in force).

Execute only UX-02A from docs/ANDROID_MOBILE_UX_AUDIT_EXECUTION_PLAN.md §8 (findings MUX-007 and MUX-008). Do not start UX-02B.
```

### Accepted scope

- Replace launch-time microphone prompting in `com.globalenglish.app` and
  `com.globalenglish.demo` with an app-owned contextual Capacitor bridge.
- Grant WebView audio capture for the bundled `https://localhost` origin only
  after Android runtime permission is granted, while exposing granted,
  denied-once and denied-permanently outcomes to the web layer.
- Keep **Type instead** and every non-recording path available after refusal.
- Separate onboarding validation, offline, timeout and server failures and add
  explicit same-command retry versus edit-and-create-new-command recovery.
- Execute and document only UX-02A findings MUX-007 and MUX-008.

### Conditions and limitations

- UX-02B and every later UI/UX checkpoint remain unauthorized in this task.
- Android origin pinning, recording consent, retention, deletion, provider
  processing and secure-credential rules remain unchanged.
- No production deployment, Google Play publication or real learner-data use
  is authorized.
- One human exercised the two recorded roles; independent security, privacy,
  accessibility and tenancy review remain release gates.

<a id="app-0084"></a>

## APP-0084 — UX-AUDIT-00 review semantics and measurement-contract authorization

| Field | Evidence |
|---|---|
| Status | Accepted for UX-AUDIT-00 decisions and documentation only |
| Decision date | 2026-09-15 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner |
| Evidence source | Exact free-text instruction in the active Codex conversation |
| Related plan | [Android Mobile UX Audit Execution Plan §8](ANDROID_MOBILE_UX_AUDIT_EXECUTION_PLAN.md#ux-audit-00--metric-taxonomy-and-review-decision-packet) |
| Related decisions | [D-0289 and D-0290](DECISIONS.md) |
| Related evidence | [UX-AUDIT-00 decision packet](UX_AUDIT_00_DECISION_PACKET.md); [UX funnel event contract](UX_FUNNEL_EVENT_CONTRACT.md) |
| Related ADR | [ADR-0028](adr/0028-meaningful-speaking-streak-and-delayed-review.md) |
| Recorder | Codex agent, acting only as author and evidence recorder |
| Repository revision | `afc5b2d` (`UX-AUDIT-00: define review semantics and funnel contract`) |
| Master-plan amendment | No |

### Exact human statement

```text
I am the Accountable Product Owner and Technical Decision Owner (roles recorded under APP-0069). Record the following as accepted decisions in docs/DECISIONS.md using the next free D-number after D-0288, and record this authorization as the next APP entry in docs/APPROVALS.md after APP-0083:

DECISION 1 (MUX-001, on-time window): A delayed-review occurrence is "on time" when due_at <= completed_at AND completed_at falls on the same local calendar date as due_at in the occurrence's persisted timezone. Completion after that local date is "late". Completion before due_at remains not eligible (unchanged). Produce boundary examples in the packet for: due_at minus 1 second, due_at exactly, 23:59:59 local on the due date, 00:00:00 local the next day, and a UTC-vs-Asia/Kolkata date-rollover case.

DECISION 2 (MUX-002, completion versus attempt): An attempt clears the due occurrence regardless of correctness. The surfaced metric and every UI label must say "reviewed"/"attempted", never "completed"/"mastered", for a review attempt. An incorrect attempt must not set knowledge_state to mastered and must schedule the existing deterministic remediation review. Confirm in the packet that DailyMissionAttemptService::scheduleReview already does this for correct=false and state exactly what UX-07A must change.
```

### Accepted scope

- Record D-0289 (MUX-001) with the required time-boundary examples.
- Record D-0290 (MUX-002), confirm the existing correctness-independent
  clearing and incorrect-result remediation behavior, and specify UX-07A's
  later implementation obligations.
- Reconcile the unsupported UX programme ledger references, map the supplied
  35% claim, and define the content-free event contract.
- Complete only UX-AUDIT-00 as a ledger, decision and measurement-contract
  checkpoint.

### Conditions and limitations

- No application-code, schema, API or event-emission change is authorized.
- UX-07A and every other later checkpoint remain unstarted and require their
  own bounded execution.
- No production, Google Play, external analytics vendor or cross-organization
  export is authorized.
- Independent privacy, security and tenancy review remains mandatory before
  event emission.

<a id="app-0083"></a>

## APP-0083 — SPEECH-RT-01 authorization

| Field | Evidence |
|---|---|
| Status | Accepted for SPEECH-RT-01 realtime STT relay and synthetic benchmark only |
| Decision date | 2026-09-09 |
| Time zone | Asia/Kolkata |
| Human approver identifier | Accountable Product Owner in the active Cursor conversation; personal name not restated in this approval |
| Roles exercised for development | Accountable Product Owner |
| Evidence source | Exact human authorization statement in the active Cursor conversation |
| Related request | [DR-0048](DR-0048_MULTIPROVIDER_SPEECH_OBSERVABILITY_AND_LATENCY_REQUEST.md) |
| Related decision | [D-0315](DECISIONS.md) |
| Related ADR | [ADR-0038](adr/0038-multiprovider-speech-routing-kannada-help-and-first-audible-gate.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | No |

### Exact human statement

```text
I eeexplicitly authorise SPEECH-RT-01.
```

### Accepted scope

- Implement an authenticated first-party realtime STT relay and benchmark
  Sarvam Saaras realtime and ElevenLabs Scribe v2 Realtime against the
  current recorded path, as authorized by ADR-0038 / master plan §14.2.
- Keep explicit learner turn submission. Do not add always-on capture or
  full duplex. Preconnect only after voice consent. Retain recorded
  upload as the reviewed recovery route.

### Conditions and limitations

- This does not authorize SPEECH-BAKEOFF-01, vendor HTTP, secret binding,
  spend, live learner-audio dispatch or production.
- Clients never receive a provider credential or connect directly to a
  provider.
- Codex/Cursor is the recorder only and is not an approver.

<a id="app-0082"></a>

## APP-0082 — SPEECH-LAT-01 authorization

| Field | Evidence |
|---|---|
| Status | Accepted for SPEECH-LAT-01 waterfall work only |
| Decision date | 2026-09-09 |
| Time zone | Asia/Kolkata |
| Human approver identifier | Accountable Product Owner in the active Cursor conversation; personal name not restated in this approval |
| Roles exercised for development | Accountable Product Owner |
| Evidence source | Exact human authorization statement in the active Cursor conversation |
| Related request | [DR-0048](DR-0048_MULTIPROVIDER_SPEECH_OBSERVABILITY_AND_LATENCY_REQUEST.md) |
| Related decision | [D-0314](DECISIONS.md) |
| Related ADR | [ADR-0038](adr/0038-multiprovider-speech-routing-kannada-help-and-first-audible-gate.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | No |

### Exact human statement

```text
Authorize SPEECH-LAT-01
```

### Accepted scope

- Persist the turn waterfall and distinguish acknowledgement, first
  substantive response signal and first audible audio.
- Return the validated main reply without waiting for optional Kannada
  correction TTS; stream main-reply audio instead of embedding a full WAV
  in the turn JSON.
- Keep safety/schema validation before speech. Do not treat a spinner as
  the answer.

### Conditions and limitations

- This does not authorize SPEECH-RT-01, bake-off dispatch, ElevenLabs or
  Sarvam HTTP, secret binding, spend or production.
- Speaking from an incomplete model token stream remains outside this
  checkpoint and still requires a later ADR.
- Codex/Cursor is the recorder only and is not an approver.

<a id="app-0081"></a>

## APP-0081 — Defer ElevenLabs key rotation until live deployment

| Field | Evidence |
|---|---|
| Status | Accepted for development-phase Wave 3.8 close only |
| Decision date | 2026-09-09 |
| Time zone | Asia/Kolkata |
| Human approver identifier | Accountable Product Owner in the active Cursor conversation; personal name not restated in this approval |
| Roles exercised for development | Accountable Product Owner |
| Evidence source | Exact human statement in the active Cursor conversation |
| Related request | [DR-0048](DR-0048_MULTIPROVIDER_SPEECH_OBSERVABILITY_AND_LATENCY_REQUEST.md) |
| Related decision | [D-0312](DECISIONS.md) |
| Related ADR | [ADR-0038](adr/0038-multiprovider-speech-routing-kannada-help-and-first-audible-gate.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | No |

### Exact human statement

```text
elevenlabs_key will be rotated only before live deployment. I wont do it during development phase.
```

### Accepted scope

- Close Wave 3.8 for the development-phase packet.
- Defer rotation of the disclosed D-0305 key, and the paired deletion test,
  until before live deployment.
- Keep challenger dispatch fail-closed.

### Conditions and limitations

- No rotation or deletion receipt may be invented.
- Secret binding, vendor HTTP, HTTP enablement and production remain
  unauthorized.
- The disclosed key stays treated as compromised until it is rotated.
- Cursor is the recorder only and is not an approver.

<a id="app-0080"></a>

## APP-0080 — Wave 3.8 public-document residual risk

| Field | Evidence |
|---|---|
| Status | Recorded; Wave 3.8 remains open |
| Decision date | 2026-09-09 |
| Time zone | Asia/Kolkata |
| Human approver identifier | Accountable Product Owner in the active Cursor conversation; personal name not restated in this approval |
| Roles exercised for development | Accountable Product Owner |
| Evidence source | Exact human answers in the active Cursor conversation |
| Related request | [DR-0048](DR-0048_MULTIPROVIDER_SPEECH_OBSERVABILITY_AND_LATENCY_REQUEST.md) |
| Related decision | [D-0311](DECISIONS.md) |
| Related ADR | [ADR-0038](adr/0038-multiprovider-speech-routing-kannada-help-and-first-audible-gate.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | No |

### Exact human statements

```text
Still using the disclosed key; do not record a rotation receipt.
```

```text
Treat the public ElevenLabs DPA page and Sarvam Trust Center as executed contracts for Wave 3.8 (I accept that residual risk).
```

### Accepted scope

- Hash public `elevenlabs.io/dpa`, Terms of Use, `compliance.elevenlabs.io` and
  the Sarvam Trust Center into the Wave 3.8 executed-document slots.
- Record that residual risk. Do not invent a signed Enterprise counterpart.

### Conditions and limitations

- The disclosed D-0305 key stays in use; no rotation receipt was written.
- No deletion test was run or recorded.
- `compliance.elevenlabs.io` hashed as a Vanta portal shell, not a named
  subprocessor table.
- Wave 3.8, secret binding, vendor HTTP and production remain unauthorized.
- Cursor is the recorder only and is not an approver.

<a id="app-0079"></a>

## APP-0079 — Wave 3.8 account assertions and spend caps

| Field | Evidence |
|---|---|
| Status | Recorded; development-phase Wave 3.8 closed again after APP-0064 renewal (16 September 2026) |
| Decision date | 2026-09-09 |
| Time zone | Asia/Kolkata |
| Human approver identifier | Accountable Product Owner in the active Cursor conversation; personal name not restated in this approval |
| Roles exercised for development | Accountable Product Owner |
| Evidence source | Exact human answers in the active Cursor conversation |
| Related request | [DR-0048](DR-0048_MULTIPROVIDER_SPEECH_OBSERVABILITY_AND_LATENCY_REQUEST.md) |
| Related decision | [D-0309](DECISIONS.md); [D-0301](DECISIONS.md) |
| Related ADR | [ADR-0038](adr/0038-multiprovider-speech-routing-kannada-help-and-first-audible-gate.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | No |

### Exact human statements

```text
Enterprise with India residency AND Zero Retention already enabled for API (v3 Conversational + Scribe)
```

```text
Not yet — will rotate before any live test
```

```text
ElevenLabs benchmark ₹500; Sarvam monthly ₹500; alerts stay on Azure/Sarvam email (no SMS)
```

### Accepted scope

- Record the assertions and spend caps in the outside-Git Wave 3.8 packet.
- Add a fail-closed preflight that never reads secret values or sends HTTP.
- Pin the only allowed ElevenLabs host to `api.in.residency.elevenlabs.io`.

### Conditions and limitations

- These statements are Accountable Product Owner assertions, not independent
  console or DPA evidence.
- Development-phase Wave 3.8 may close while ElevenLabs key rotation and the
  deletion test remain deferred under [APP-0081](APPROVALS.md#app-0081).
  HTTP enablement and live-deployment rotation remain blocked until those
  receipts exist and a separate approval is recorded.
- Inherited APP-0064 Azure Speech evidence must stay current; it was renewed
  on **16 September 2026** through **2026-12-14T13:15:00Z** ([D-0301](DECISIONS.md)).
- Content dispatch, secret binding and vendor HTTP remain unauthorized.
- Cursor is the recorder only and is not an approver.

<a id="app-0078"></a>

## APP-0078 — SPEECH-PROVIDER-01 fail-closed authorization

| Field | Evidence |
|---|---|
| Status | Accepted for fail-closed adapter work only |
| Decision date | 2026-09-09 |
| Time zone | Asia/Kolkata |
| Human approver identifier | Accountable Product Owner in the active Cursor conversation; personal name not restated in this approval |
| Roles exercised for development | Accountable Product Owner |
| Evidence source | Exact human authorization statement in the active Cursor conversation |
| Related request | [DR-0048](DR-0048_MULTIPROVIDER_SPEECH_OBSERVABILITY_AND_LATENCY_REQUEST.md) |
| Related decision | [D-0308](DECISIONS.md) |
| Related ADR | [ADR-0038](adr/0038-multiprovider-speech-routing-kannada-help-and-first-audible-gate.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | No |

### Exact human statement

```text
I authorize SPEECH-PROVIDER-01
```

### Accepted scope

- Implement ElevenLabs and Sarvam challenger adapters behind existing
  project-owned STT/TTS ports, plus a versioned two-dispatch router.
- Keep every new vendor fail-closed: no HTTP client, no secret bind, no
  learner-content dispatch, no spend and no live route change.

### Conditions and limitations

- Wave 3.8 exact-product DPA, India/Zero Retention, no-training, deletion,
  least-privilege and capped-spend evidence remains required before any
  content dispatch.
- The D-0305 ElevenLabs test key stays outside Git, unbound, and treated as
  compromised pending rotation.
- SPEECH-PROVIDER-01 is not complete. Production remains unauthorized.
- Codex/Cursor is the recorder only and is not an approver.

<a id="app-0077"></a>

## APP-0077 — ADR-0038 multi-provider speech governance approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-09-09 |
| Time zone | Asia/Kolkata |
| Human approver identifier | Accountable Product Owner in the active Codex conversation; personal name not restated in this approval |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Evidence source | Exact human approval statement in the active Codex conversation |
| Related request | [DR-0048](DR-0048_MULTIPROVIDER_SPEECH_OBSERVABILITY_AND_LATENCY_REQUEST.md) |
| Related decision | [D-0306](DECISIONS.md) |
| Related ADR | [ADR-0038](adr/0038-multiprovider-speech-routing-kannada-help-and-first-audible-gate.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | Yes — version 3.15, §§1.1, 4.1, 5.1, 5.3, 14.1–14.8, 18.1, 18.3, 20.1, 20.4, 21.2–21.3, 22 and 32, via ADR-0038 |

### Exact human statement

```text
Approve ADR-0038 and its master-plan v3.15 amendments as proposed, exercising the Accountable Product Owner, Technical Decision Owner, Security Reviewer, Privacy Reviewer and Tenancy Reviewer roles for development. Authorize only SPEECH-OBS-01 next; this does not authorize provider calls, credentials, spend, learner-content processing or production.
```

### Accepted scope

- Accept ADR-0038's exact Kannada Help, transcript/reply contract, provider
  routing, realtime transport, retention/economics and first-audible rules.
- Amend the canonical master plan from version 3.14 to 3.15 in the same change.
- Close SPEECH-GOV-01 after documentation verification and authorize only the
  bounded SPEECH-OBS-01 implementation.

### Conditions and limitations

- No provider credential activation, provider call, provider spend,
  evaluation or learner-content processing is authorized.
- No production, public/paid learner release or Play production/public listing
  is authorized.
- Independent security, privacy/legal, tenancy, bilingual-content and
  accessibility reviews remain mandatory before production. The roles above
  are consolidated development roles and are not evidence of independence.
- Codex is the recorder only and is not an approver.

## APP-0074 — DR-0047 Option A: Paid live-AI caps and talk packs

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-09-01 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner |
| Evidence source | Free-text product-owner instruction in the active Cursor conversation |
| Related request | [DR-0047](DR-0047_PAID_LIVE_AI_CAPS_AND_TALK_PACKS_REQUEST.md) |
| Related decision | [D-0273](DECISIONS.md) |
| Related ADR | [ADR-0037](adr/0037-paid-live-ai-caps-and-talk-packs.md) |
| Related specification | [ADMIN-ENT-01](ADMIN-ENT-01_SPEC.md); [SUBSCRIPTIONS.md](SUBSCRIPTIONS.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | Yes — §§9.2, 16.5, 31, 32, via ADR-0037 |

### Exact human statement

```text
How about an option to buy more AI talk time? Some anount per hour?
add these suggestions into the app.
```

### Accepted scope

- Modules 40 minutes / Premium 90 minutes inclusive live-AI pools.
- Talk 30 / 60 / 180 prepaid packs on an active paid month.
- Dummy checkout; no Play Billing; no per-minute overage.

### Conditions and limitations

- Talk packs do not unlock Role-play and do not apply to demo or expired grants.
- Independent security, privacy and tenancy review remains mandatory before production.

<a id="app-0076"></a>

## APP-0076 — Named Phase-1 UI catalogue reviews (Malayalam, Tamil, Telugu, Hindi)

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-09-02 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised | Accountable Product Owner acting as independent UI reviewer for Malayalam, Tamil, Telugu and Hindi interface catalogues |
| Evidence source | Exact human instruction in the active Cursor conversation |
| Related prior approvals | [APP-0075](APPROVALS.md#app-0075); [APP-0006](APPROVALS.md#app-0006); [ADR-0005](adr/0005-phase-1-multilingual-ui-language-packs.md) |
| Related decision | [D-0275](DECISIONS.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Master-plan amendment | No |

### Exact human statement

```text
start gap 2
```

### Context and interpretation

Continuing the one-by-one gap closure after APP-0075 Kannada UI approval, the
Accountable Product Owner records named independent review of the current
Malayalam, Tamil, Telugu and Hindi interface catalogues at revision
`ui-*-2026-09-02.2`.

Accepted interpretation:

1. `ml-IN`, `ta-IN`, `te-IN` and `hi-IN` governance metadata move to
   `approved` with honest reviewer attribution distinct from the Codex
   implementation draft translator.
2. Ordinary `approved-only` builds resolve those locales when explicitly
   requested (API, demo, tests).
3. The soft-launch learner locale picker remains kn-IN + en-IN only under
   D-0206.
4. This is not curriculum, lesson-summary, Role-play or production/Play
   authorization.

### Accepted scope

- Update `packages/localization/src/catalogues/governance.json` for the four
  locales.
- Adjust localization and web policy tests for approved resolution.
- STATUS, DECISIONS and QUALITY_REVIEW.

### Conditions and limitations

- Not separately commissioned linguist firms; reviewer is the same product
  owner who authorized APP-0006 scope and APP-0075.
- Soft-launch UI picker is unchanged; approved catalogues are available to API
  resolution and explicit review/demo surfaces.
- Production/public release remains unauthorized.

<a id="app-0075"></a>

## APP-0075 — Named Kannada UI catalogue independent review

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-09-02 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised | Accountable Product Owner acting as independent Kannada UI reviewer |
| Evidence source | Exact human instruction in the active Cursor conversation |
| Related prior approvals | [APP-0006](APPROVALS.md#app-0006); [ADR-0005](adr/0005-phase-1-multilingual-ui-language-packs.md) |
| Related decision | [D-0274](DECISIONS.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Master-plan amendment | No |

### Exact human statement

```text
And also help me complete - Language fallback
Kannada interface text is still awaiting independent review, so English is shown.
Then lets complete them all one by one. Start with the first gap.
```

### Context and interpretation

The Accountable Product Owner completed named independent review of the current
`ui-kn-IN-2026-09-01.5` interface catalogue after spot-checking the Direct
soft-launch chrome (navigation, sign-in, learn, speak, progress and account).

Accepted interpretation:

1. `kn-IN` governance metadata moves to `approved` with honest reviewer
   attribution distinct from the Codex implementation draft translator.
2. Ordinary `approved-only` builds resolve Kannada chrome without the language
   fallback banner for the default locale.
3. Malayalam, Tamil, Telugu and Hindi UI catalogues remain
   `pending-independent-review`.
4. This is not curriculum, lesson-summary, Role-play or production/Play
   authorization.

### Accepted scope

- Update `packages/localization/src/catalogues/governance.json` for `kn-IN`.
- Adjust localization and App tests for approved Kannada resolution.
- STATUS, DECISIONS and QUALITY_REVIEW.

### Conditions and limitations

- Not a separately commissioned linguist firm; reviewer is the same product
  owner who authorized APP-0006 scope.
- Other UI locales still fall back to English under `approved-only`.
- Production/public release remains unauthorized.

<a id="app-0073"></a>

## APP-0073 — DR-0046 Option A: Premium flagship and courtesy month

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-09-01 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner |
| Evidence source | Free-text product-owner instruction in the active Cursor conversation |
| Related request | [DR-0046](DR-0046_PREMIUM_COURTESY_GRANT_REQUEST.md) |
| Related decision | [D-0267](DECISIONS.md) |
| Related ADR | [ADR-0036](adr/0036-premium-courtesy-month.md) |
| Related specification | [ADMIN-ENT-01](ADMIN-ENT-01_SPEC.md); [SUBSCRIPTIONS.md](SUBSCRIPTIONS.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | Yes — §§16.5, 31, 32, via ADR-0036 |

### Exact human statement

```text
Implement the best model you can think of. Migrate all existing students into a 1 month premium plan.
```

### Accepted scope

- Premium as the recommended Direct paid SKU; Modules remains ₹350 without Role-play.
- One complimentary Premium month for current Direct learner memberships.
- No import of the previous website’s students, passwords or progress.
- No production, Google Play, or live payment credentials.

### Conditions and limitations

- Courtesy grants apply only to `platform_direct` memberships already in this database.
- CSV username filter matches existing usernames only; email logins from the old site cannot become usernames here.
- Independent security, privacy and tenancy review remains mandatory before production.

<a id="app-0072"></a>

## APP-0072 — DR-0045 Option C: Direct subscriptions, GST and dummy in-app checkout

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-09-01 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner |
| Evidence source | Free-text product-owner instruction in the active Cursor conversation |
| Related request | [DR-0045](DR-0045_LEARNER_SUBSCRIPTION_TIERS_REQUEST.md) |
| Related decision | [D-0266](DECISIONS.md) |
| Related ADR | [ADR-0035](adr/0035-direct-learner-subscriptions.md) |
| Related specification | [ADMIN-ENT-01](ADMIN-ENT-01_SPEC.md); [SUBSCRIPTIONS.md](SUBSCRIPTIONS.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | Yes — §§8.3, 9.2, 16.5, 31, 32, via ADR-0035 |

### Exact human statement

```text
Build it all. You have the blanket approval.
```

Preceded by recorded commercial answers: first 2 modules = Level 1 and Level 2; demo = 48 hours from first sign-in; GST exclusive; demo free with one inclusive 5-minute cap; dummy GST now, owner updates via admin page; in-app checkout in the same slice.

### Accepted scope

- Direct plan catalogue, GST settings, entitlements, demo meter and dummy in-app checkout.
- Operator Subscriptions page for GSTIN/plans/grants/demo clock/meter/lockout clear.
- Learner paywall and remaining-seconds display.
- No production, Google Play, or live payment credentials.

### Conditions and limitations

- Dummy GSTIN `29AAAAA0000A1Z5` / 18% is invalid for live filing until replaced.
- Payment port `dummy` completes in-process; `disabled` and production fail closed.
- Independent security, privacy and tenancy review remains mandatory before
  production.

<a id="app-0069"></a>

## APP-0069 — DR-0038 A/B/C: daily loop, Progress dashboard, Speak latency

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-30 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner |
| Evidence source | Structured decision-form selection in the active Cursor conversation, not free text |
| Related request | [DR-0038](DR-0038_PHASE1_DAILY_LOOP_CLIENT_PLATFORM_AND_PROVIDER_REQUEST.md) |
| Related prior approvals | [APP-0068](APPROVALS.md#app-0068) |
| Related decision | [D-0219](DECISIONS.md) |
| Related ADR | [ADR-0028](adr/0028-meaningful-speaking-streak-and-delayed-review.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Master-plan amendment | Yes — §7.2 Home, via ADR-0028 |

### Exact human statement

Selection recorded on the DR-0038 sequencing question:

```text
Record your APO acceptance of DR-0038 A/B/C as D-0219 first, then I emit the Step 1 payload (Recommended)
```

Selection recorded on the preceding assessment question:

```text
No, proceed on this basis
```

### Context and interpretation

Immediately before the first statement, the Cursor agent had:

1. delivered an assessment of the product-owner "Master Orchestration
   Specification (Phase 1 – Revised)" of 30 August 2026;
2. offered explicit disagreement options covering Flutter/iOS, attendance
   tracking, 19-level naming and the teacher rename; and
3. authored [DR-0038](DR-0038_PHASE1_DAILY_LOOP_CLIENT_PLATFORM_AND_PROVIDER_REQUEST.md)
   separating that specification into seven independently decidable items.

Both statements are structured selections from an agent-authored option list.
They are durable and unambiguous for the options presented, but they are not
free-text product-owner prose. A later free-text confirmation may supersede
this record without changing the decisions accepted here.

### Accepted scope

- **DR-0038 A** — Daily Mission Home built on the existing `LearnerHomeService`,
  with a *meaningful-speaking-day* streak and deterministic delayed-review
  scheduling.
- **DR-0038 B** — Progress becomes a distinct mastery and review surface and
  stops rendering the Learn level path.
- **DR-0038 C** — Speak hold-to-talk latency reduction with no new provider.

### Confirmed by the "No, proceed on this basis" selection

- **D** — the learner client stays React PWA plus Capacitor 8; Flutter and iOS
  stay deferred under master plan §9.2.
- **F** — levels stay generically modelled; Levels 2–17 keep their APP-0065 /
  APP-0066 / APP-0067 authored content and do not revert to placeholders.
- **G** — no attendance percentage in canonical learning records; Notice Board
  stays deferred; the on-screen teacher remains Swathi (D-0218).

### Conditions and limitations

- **E is not accepted by this record.** The Sarvam driver flip stays governed
  by D-0217 and needs keys plus a reviewed secret file. ElevenLabs stays
  deferred pending a costed comparison against Azure `kn-IN-SapnaNeural`.
- Streak and delayed-review scheduling are deterministic and must never be
  written by AI output (master plan §0.3).
- No production or Google Play authorization.
- No curriculum publication for Levels 2–17; `curriculum_publication_gate`
  is unchanged. **Later:** D-0272 / Wave 7.1 published Direct lesson-practice
  Levels 1–17 for entitled learners (not graph `CurriculumPublisher`).
- Kannada UI catalogue remains `pending-independent-review`.
- The compute-profile orchestration protocol in the source specification is
  **not** accepted; DR-0038 records it as out of scope.

### Correction to DR-0038 as authored

DR-0038 stated that accepting A and B amends master plan §7.1. On inspection
that is wrong and is corrected here:

- §7.1 is navigation and is **unchanged**; the five destinations stand.
- §7.2 Home is the section amended by A, to add the streak.
- §7.3 already requires Progress to show "Objectives, speaking activity, review
  due, recurring errors and recent improvement". **B is a conformance fix, not
  an amendment.**

<a id="app-0068"></a>

## APP-0068 — Soft-launch voice-first learner STT

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-14 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; consolidated Security, Privacy and Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Cursor conversation |
| Related request | [DR-0028](DR-0028_ORDINARY_LEARNER_SPEECH_ACTIVATION_REQUEST.md) |
| Related prior approvals | [APP-0064](APPROVALS.md#app-0064); [APP-0067](APPROVALS.md#app-0067) |
| Related decision | [D-0212](DECISIONS.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Master-plan amendment | No |

### Exact human statement

```text
Typed text is only on request. Everything else sould be voice based.
```

### Context and interpretation

The Accountable Product Owner required Conversation and Role-play to be
spoken by default. Typed input remains available only after the learner
chooses Type instead.

Accepted interpretation for this approval only:

1. Invite-only Direct soft-launch may enable `VOICE_UPLOAD_ENABLED` and bind
   a project-owned Azure recorded-speech adapter on the existing Central India
   Speech resource used by APP-0064 guidance TTS.
2. Consent, review, deletion and advisory-AI rules from ADR-0007 remain.
3. Production, Play and partner tenants stay disabled.
4. This is not a commissioned privacy-firm review and not DR-0028 Phase B
   supervised smoke of two named learners.

### Accepted scope

- Voice-first Conversation/Role-play UI.
- Soft-launch VoiceFeatureGate + Azure STT DI for Direct allowlist.
- STATUS, DECISIONS, QUALITY_REVIEW.

### Conditions and limitations

- Spend shares the APP-0064 ₹500 Speech ceiling.
- Credentials stay outside Git. Soft-launch env may set `VOICE_UPLOAD_ENABLED`
  for Direct only; committed `.env.example` stays disabled.
- Kannada UI catalogue is not independently approved.
- Role-play remains `synthetic_unreviewed`.
- DR-0028 Phase B supervised smoke of two named learners is not this approval.

<a id="app-0067"></a>

## APP-0067 — Named Kannada review of authored Levels 2–17

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-14 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised | Accountable Product Owner acting as Kannada reviewer of the APP-0065 authored pack |
| Evidence source | Exact human instruction in the active Cursor conversation |
| Related prior approvals | [APP-0066](APPROVALS.md#app-0066); [APP-0065](APPROVALS.md#app-0065) |
| Related decision | [D-0211](DECISIONS.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Master-plan amendment | No |

### Exact human statement

```text
Level 2-17 Kannada approved.
```

### Context and interpretation

The Accountable Product Owner completed named Kannada review of the
APP-0065 authored Levels 2–17 `kannada_summary` pack after APP-0066 English
review.

Accepted interpretation:

1. Kannada lesson summaries for Levels 2–17 are approved for private Direct
   pilot drafts.
2. This is not an independent Kannada UI-catalogue approval.
3. Role-play remains `synthetic_unreviewed`.
4. Curriculum publication, production and Play remain unauthorized.

### Accepted scope

- Update authored blueprint Kannada review metadata to APP-0067.
- Create new Direct draft revisions (revision 4) reusing the same four audio
  parts. Evidence:
  `docs/evidence/lpl-01/levels-2-17-kannada-review-result.json`.
- STATUS, DECISIONS and QUALITY_REVIEW.

### Conditions and limitations

- Not a separately commissioned linguist firm; the reviewer is the same
  product owner who authorized APP-0065 and APP-0066.
- Not publication.
- Kannada interface chrome remains `pending-independent-review`.

<a id="app-0066"></a>

## APP-0066 — Named English academic review of authored Levels 2–17

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-14 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised | Accountable Product Owner acting as English academic reviewer of the APP-0065 authored pack |
| Evidence source | Exact human instruction in the active Cursor conversation |
| Related prior approvals | [APP-0065](APPROVALS.md#app-0065) |
| Related decision | [D-0209](DECISIONS.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Master-plan amendment | No |

### Exact human statement

```text
Levels 2–17 English approved
```

### Context and interpretation

The Accountable Product Owner completed Step 1 of the remaining human reviews
for the APP-0065 authored Levels 2–17 pack. The agent authored the English
text; this statement is the named human English academic review of that pack.

Accepted interpretation:

1. English objectives, patterns, checks, Talk and Apply for Levels 2–17 are
   approved for private Direct pilot drafts.
2. Kannada summaries are **not** independently reviewed.
3. Role-play remains `synthetic_unreviewed`.
4. Curriculum publication, production and Play remain unauthorized.

### Accepted scope

- Update authored blueprint English review metadata to APP-0066.
- Create new Direct draft revisions (revision 3) reusing the same four audio
  parts.
- STATUS, DECISIONS and QUALITY_REVIEW.

### Conditions and limitations

- Kannada `independent_review` remains false.
- Attachment still records a kannada decision row for the new hash; that row
  is authored carry-forward, not independent Kannada approval.
- Not a separately commissioned linguist firm; the reviewer is the same
  product owner who authorized APP-0065.
- Not publication.

<a id="app-0065"></a>

## APP-0065 — Authored Levels 2–17, Role-play scripts, 1:1 tuition request

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-14 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; consolidated Security, Privacy and Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Cursor conversation |
| Related request | [DR-0036](DR-0036_AUTHORED_LEVELS_ROLEPLAY_TUITION_REQUEST.md) |
| Related prior approvals | [APP-0063](APPROVALS.md#app-0063); [APP-0064](APPROVALS.md#app-0064) |
| Related decision | [D-0208](DECISIONS.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Master-plan amendment | No |

### Exact human statement

```text
Can we fix this now? In case it needs humans to fix it, i will do it now.
Else you have a blanket approval to close thwese gaps - Role-play remains a
synthetic prototype. 1:1 booking is still marketing copy. Levels 2–17 lesson
text is still provisional.
```

### Context and interpretation

The Accountable Product Owner authorized closing three disclosed quality debts
in the current private/soft-launch product, and offered to perform remaining
human review immediately.

Accepted interpretation:

1. Authored English + Kannada lesson text may replace APP-0063 templates for
   Levels 2–17 private Direct drafts. Independent named bilingual review is
   still required before publication.
2. Role-play Observe/Rehearse/Perform may receive authored scripts while
   remaining `synthetic_unreviewed`.
3. 1:1 tuition may become an org-scoped request/waitlist (no payments,
   calendar or trainer marketplace). Marketing copy must match that product.

### Accepted scope

- Authored Levels 2–17 blueprints and APP-0065 replace command.
- Role-play pedagogical scripts for the 12 catalog scenarios.
- Learner and operator tuition-request APIs, UI, OpenAPI 0.31.0, privacy
  inventory v5.
- STATUS, DECISIONS and QUALITY_REVIEW.

### Conditions and limitations

- Not curriculum publication, not production/Play.
- Not independent Kannada UI catalogue approval.
- Not specialist Role-play curriculum approval.
- Not billing, scheduling or direct trainer assignment.

<a id="app-0063"></a>

## APP-0063 — Levels 2–17 private auto-commission (provisional bilingual reviews)

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-14 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; consolidated Security, Privacy and Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Cursor conversation |
| Related request | [DR-0034](DR-0034_LEVELS_2_17_AUTO_COMMISSION_REQUEST.md) |
| Related prior approvals | [APP-0061](APPROVALS.md#app-0061); [APP-0062](APPROVALS.md#app-0062) |
| Related decision | [D-0204](DECISIONS.md) |
| Recorder | Cursor agent, acting only as author and evidence recorder |
| Master-plan amendment | No |

### Exact human statement

```text
Approve all these automatically - Levels 2–17 blueprints + English/Kannada reviews + attach 4 parts each. I will fix it later as we catch bugs after live.
```

### Context and interpretation

APP-0061 left Levels 2–17 as `commissioning_pending` shells and forbade
skipping English/Kannada specialist review. APP-0062 ingested teaching audio
hashes only. The Accountable Product Owner now authorizes **private Direct
pilot auto-commission** of Levels 2–17 so learners can open all levels before
independent bilingual review, accepting content-quality debt until later
correction.

Accepted interpretation for this approval only:

1. Create provisional lesson-practice blueprints for inventory concepts on
   Levels 2–17, auto-record English and Kannada reviews as `approved`, and
   attach four `lesson_audio` parts per level (Level 15 Practice reuses the
   combined Introduction/Practice asset).
2. Provisional metadata stubs (operator label + placeholder transcript) may be
   written where APP-0062 assets lack metadata heads, solely to satisfy
   attachment predicates.
3. Drafts remain private/unpublished. Curriculum publication, production and
   Google Play remain unauthorized.
4. Auto-approvals are **not** independent academic or Kannada specialist
   sign-off; STATUS/QUALITY must record the provisional quality debt.

### Accepted scope

- Execute private Direct-org commissioning for Levels 2–17 under this approval.
- Update `level_sessions.level_concepts`, evidence JSON, STATUS, DECISIONS and
  QUALITY_REVIEW.

### Conditions and limitations

- One human exercised consolidated development roles. This is not independent
  production security, privacy, legal or bilingual review.
- Publication and production/public release remain separately gated.
- Live AI conversation providers remain subject to existing conversation
  approvals; this approval does not enable Azure live spend or Play.

<a id="app-0060"></a>

## APP-0060 — one-login role-group operator authorization approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-14 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `S R Bhandary` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; consolidated Security, Privacy and Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Related request | [DR-0031](DR-0031_LPL01_RECONCILIATION_AUTHENTICATION_SIMPLIFICATION_REQUEST.md), resolved and broadened by the contextual instruction |
| Pre-resolution DR SHA-256 | `da8f04370560bf7c8309a33c04b646e6ca0ef4951fcf3376ba34bdff893b739b` |
| Resulting ADR | [ADR-0023](adr/0023-one-login-role-group-operator-authorization.md) |
| Related decision | [D-0193](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | Yes — routine operator work uses one signed-in session plus role/permission groups; password confirmation is narrowed to credential, recovery-contact and administrator-authority changes |

### Exact human statement

```text
Yes and also the enterprise security needs to be removed and lets go by username and password and role based groups to manage the project.
```

### Context and interpretation

Immediately before the statement, Codex proposed this exact scope:

1. one username/password login per session and one organization/role choice;
2. no repeated authentication for routine learner, trainer, administrator,
   content, media, reconciliation or publishing work;
3. a small Account settings surface for credential and device management;
4. retained invisible password hashing, HTTPS Secure/HttpOnly cookies, CSRF,
   throttling, tenant isolation, role enforcement and audit; and
5. password re-entry only for credential, recovery-contact or administrator-
   authority changes, with the completed temporary LPL page removed.

The human first answered `Yes`, then added the exact statement above. The
accepted interpretation removes the visible and repeated enterprise-style
reauthentication ceremony; it does not authorize plaintext passwords,
insecure transport, shared credentials, tenant bypass or removal of role and
permission checks.

### Accepted scope

- Amend DR-0031's one-login reconciliation recommendation into the general
  routine operator policy in ADR-0023 and master-plan version 3.5.
- Remove `auth.password-confirmed` from routine operator routes while keeping
  the exact account recovery and administrator-authority route boundary.
- Use the existing organization roles and permission grants as the project-
  management access model; add no parallel account hierarchy or schema.
- Simplify the visible Account Security wording/surface and remove the
  completed standalone LPL reconciliation aid.
- Update route-policy, integration, browser, OpenAPI/client and governance
  evidence and run the full repository gates.

### Conditions and limitations

- One human exercised multiple development review roles. This is not
  independent production security, privacy or tenancy review.
- The accepted risk is that a stolen active administrator session can perform
  more authorized routine work until expiry or revocation.
- Credential and recovery secrets remain server-verified and excluded from
  logs, audit, analytics and browser persistence.
- This approval does not authorize production/public deployment, curriculum
  review/publication results, LPL-02, learner data import or a provider call.

<a id="app-0052"></a>

## APP-0052 — LPL-01 governed transcription commissioning approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-10 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Approved request | [DR-0025](DR-0025_LPL01_TRANSCRIPTION_COMMISSIONING_REQUEST.md) Option A exactly as written |
| Approved pre-resolution DR SHA-256 | `cb489ec3f034861d256934aa0fcf5128a997af73ec6da4bd461453ebce02bdf4` |
| Resulting ADR | [ADR-0020](adr/0020-governed-curriculum-media-transcription-commissioning.md) |
| Related decision | [D-0153](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
Approve DR-0025 Option A as written.
```

### Accepted scope

- Implement a separate provider-neutral curriculum-media transcription port,
  dedicated Central India Azure Fast Transcription REST adapter, additive
  tenant-owned provenance/candidate lifecycle and first-party fresh-TOTP human
  reconciliation API exactly as fixed by DR-0025.
- Permit one sequential campaign containing only the four exact APP-0047
  asset/hash/rights tuples, with at most two attempts per asset and hard caps
  of eight attempts, 37,520,380 uploaded bytes, 2,345,026 billable input
  milliseconds and ₹100 estimated Azure service usage.
- Send only the exact accepted MP3 bytes inline under fixed `en-IN` and API
  `2025-10-15`; retain ordinary learner bindings unchanged and prohibit URL,
  batch, custom, diarization, language-ID, translation, LLM/MAI speech,
  prompting, word timestamps and fallback providers.
- Store validated provider text only as an application-encrypted seven-day
  candidate. Require a human to confirm/correct/discard/flag every candidate
  before any English metadata revision, preserving exact reconciliation
  lineage and author/reviewer separation.

### Conditions and limitations

- This approval records authority but does not itself implement schema/API,
  supply a credential, satisfy current vendor/configuration/price evidence or
  make a provider request.
- Every attempt still requires the accepted DR hash, exact manifest/run,
  current no-training/no-storage/logging-disabled/contract/subprocessor/
  Central India/price/FX evidence, external mode-`0600` secret reference and
  removable campaign permit. Drift fails closed.
- No LLM drafting, automatic metadata, English academic or Kannada approval,
  answer/quiz authority, attachment, publication, learner delivery, LPL-02,
  sharing or production/public release is authorized.
- One human exercised consolidated development roles. This is not independent
  specialist, academic/Kannada, legal or production review.

<a id="app-0051"></a>

## APP-0051 — LPL-01 pilot program and rights-authority approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-10 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Approved request | [DR-0024](DR-0024_LPL01_PILOT_PROGRAM_AND_RIGHTS_AUTHORITY_REQUEST.md) Option A exactly as written |
| Approved pre-resolution DR SHA-256 | `2b52f1c3c09206a33de7b940e2184c473d3095b89388d30fc9830386f9266538` |
| Resulting ADR | [ADR-0019](adr/0019-local-lpl-pilot-and-rights-authority-bootstrap.md) |
| Related decision | [D-0150](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
Approve DR-0024 Option A as written.
```

### Accepted scope

- Create one standalone private Direct-owned pilot program with key
  `global-spoken-english-lpl-pilot`, and no curriculum/publication head,
  enrollment, learner pin, public route or delivery entitlement.
- Add one non-system Direct role, `lpl-pilot-commissioner`, containing exactly
  `curriculum.author` and `media.rights.approve`, assigned only to membership
  `01KZGE9QRTCQE3PQ3RN31AXQSF`.
- Use one disabled-default local-only APP-0051 bootstrap with exact tenant,
  actor, membership, APP-0047 asset/hash, transaction, lock, idempotency,
  audit and outbox checks. Direct SQL, seed assignment and test authentication
  remain prohibited.
- Create one pending program-scoped `rights_confirmer` self-appointment through
  the existing APP-0004 authority service, retaining APP-0047 as its owner/
  qualification basis. The appointee must accept through ordinary selected-
  membership and fresh-TOTP web APIs.
- Confirm only the four exact APP-0047 assets through the existing Media
  Library HTTP command for later gated web/Android learner delivery in India,
  derivative commissioning enabled, sharing disabled and no supplied expiry.

### Conditions and limitations

- Disable/remove the persistent bootstrap switch immediately after one
  successful execution. The role and accepted appointment remain visible and
  revocable authoritative records.
- This approval makes no provider call and does not approve attachment,
  transcript, blueprint, answer, English/Kannada content, publication,
  enrollment, learner delivery, deployment or production/public release.
- Provider transcription remains separately gated by a finite ceiling,
  kill switch, no-training/minimum-retention settings, immutable provenance
  and human reconciliation.
- One human exercised consolidated development roles. This is not independent
  specialist, academic/Kannada, legal or production review.

<a id="app-0050"></a>

## APP-0050 — Local MFA incident recovery approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-10 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Approved request | [DR-0023](DR-0023_LOCAL_MFA_INCIDENT_RECOVERY_REQUEST.md) Option A exactly as written |
| Approved pre-resolution DR SHA-256 | `956d6d6e1513412ce662dbc70fb0f642f4f1e763626b158b76f37e70ad79b79e` |
| Resulting ADR | [ADR-0018](adr/0018-local-mfa-incident-restore-and-replay.md) |
| Related decision | [D-0148](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
Approve DR-0023 Option A as written.
```

### Accepted scope

- Preserve the current locked local database in a validated incident snapshot,
  prove the post-baseline delta contains only reviewed LPL-01/runtime records,
  then restore the exact validated pre-LPL database backup.
- Rerun reviewed migrations and foundation seed, and extend APP-0049 only to
  repeat the exact same one-shot identity binding once in restored state.
- Repeat only the ordinary OTP, exact membership and TOTP HTTP path. Permit one
  owner-only `0600` system-temporary TOTP/recovery handoff whose contents stay
  out of Git, arguments, chat, logs, audit and durable evidence.
- Use explicit project-owned ffprobe and ClamAV selectors for every four-hash
  Media Library cycle and retain fail-closed hash, scan, human acceptance,
  permission and tenancy controls.

### Conditions and limitations

- Any unexplained learner, curriculum, review, rights, publication or other
  business delta stops the restore.
- Routine cleanup may revoke ordinary sessions but must not revoke the factor.
  Lost handoff material stops work; it does not authorize SMS reset, SQL
  mutation or another restore.
- This approval creates no `media.rights.approve` grant, rights-confirmer
  appointment, rights revision, transcript approval, publication, learner
  delivery, production recovery precedent or LPL-02 authority.
- One human exercised consolidated development roles. This is not independent
  specialist, legal or production review; all retained gates remain.

<a id="app-0049"></a>

## APP-0049 — Local operator identity bootstrap approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-10 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Approved request | [DR-0022](DR-0022_LOCAL_OPERATOR_IDENTITY_BOOTSTRAP_REQUEST.md) Option A exactly as written |
| Approved pre-resolution DR SHA-256 | `0ecc159512ec28e507995eb01fe5f116221e78c8710a03b9de3269e67f3f8cb8` |
| Resulting ADR | [ADR-0017](adr/0017-local-operator-identity-bootstrap.md) |
| Related decision | [D-0146](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
Approve DR-0022 Option A as written.
```

### Accepted scope

- Add one disabled-by-default, local-environment/local-OTP-only Artisan
  command that binds a protected-file phone identity only to the exact
  existing identity-free active Direct organization administrator.
- Require exact expected user and membership ULIDs, APP-0049 reference,
  immutable tenant/role checks, identifier uniqueness, transaction locks and
  one-shot fail-closed behavior.
- Create only one normal verified phone identity through a project-owned
  service; create or change no user, membership, role, permission,
  appointment, media, rights, review or publication record.
- After binding, require ordinary OTP sign-in, selected membership and fresh
  authenticator-app TOTP for every governed media command.

### Conditions and limitations

- Raw phone, digest, OTP, TOTP secret and recovery codes must remain out of
  Git, process arguments, logs, audits, exceptions and evidence.
- The kill switch must be removed from the process after the one-shot binding.
- This local non-production exception does not define production initial-admin,
  identity reassignment, phone change or recovery behavior.
- SQL, commissioner, test-authentication and direct-domain media bypasses
  remain prohibited for LPL-01 evidence.
- One human exercised consolidated development roles. This is not independent
  specialist, legal or production review; all retained release gates remain.

<a id="app-0048"></a>

## APP-0048 — Long-form lesson-audio policy approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-10 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Approved request | [DR-0021](DR-0021_LESSON_AUDIO_MEDIA_POLICY_REQUEST.md) Option A exactly as written |
| Approved pre-resolution DR SHA-256 | `c792ea46d2ace3ed3d517d9518fdf075000228b0b877131504cbbd5cf4ee472a` |
| Resulting ADR | [ADR-0016](adr/0016-long-form-lesson-audio-media.md) |
| Related decision | [D-0145](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
Approve DR-0021 Option A as written.
```

### Accepted scope

- Add one distinct bounded `lesson_audio` media kind without changing
  `model_audio` or `lesson_video` semantics.
- Implement only the Media Library policy, adapters, operator API/UI,
  contracts, localization and tests required before LPL-01 ingestion.
- Preserve original accepted bytes and exact hashes; require real inspection,
  malware scan, human acceptance, metadata/review, rights and immutable
  publication evidence.
- Permit later private publication-pinned web/Android delivery in India under
  APP-0047; keep learner delivery outside the current LPL-01 slice.

### Conditions and limitations

- This approval does not accept unbuilt implementation or any media object,
  transcript, blueprint, answer, Kannada variant, review or publication.
- LPL-02 learner runtime, all-level import, Assignment audio, public URLs,
  sharing, offline download, production/public release and unbounded provider
  spend remain excluded.
- One human exercised consolidated development roles. This is not independent
  specialist, academic/Kannada, legal or production review.

<a id="app-0047"></a>

## APP-0047 — Exact Level 1 teaching-media processing authorization

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-10 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Role exercised for development | Accountable Product Owner and retained Rights Confirmer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Selected source artifact | [LPL-01_LEGACY_SOURCE_MAP.md](LPL-01_LEGACY_SOURCE_MAP.md) |
| Selected source-map SHA-256 | `273796e39523ae762f7d3f6f426179abaf812e7b4f68c5c7fc99772fe56eb7dc` |
| Rights authorization artifact | [LPL-01_LEVEL1_MEDIA_RIGHTS_AUTHORIZATION.md](LPL-01_LEVEL1_MEDIA_RIGHTS_AUTHORIZATION.md) |
| Rights authorization SHA-256 | `cc50ca8d13c9753bafffb195e952880014ed39bd08476601ccc428cff5f6b201` |
| Related decision | [D-0143](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
I authorize the four Level 1 teaching MP3 assets identified by exact SHA-256 in docs/LPL-01_LEGACY_SOURCE_MAP.md for non-production ingestion, transcription, and AI-assisted derivative lesson drafting through project-owned adapters. Learner delivery may target web and Android in India; sharing and production/
  > public release remain prohibited. Existing privacy, no-training, provider-retention, human English/Kannada review, and immutable publication controls remain mandatory.
```

### Context and interpretation

Immediately before this statement:

1. authenticated read-only legacy inspection identified one Level 1 `Can`
   source with four ordered teaching MP3 parts;
2. each MP3 had been read ephemerally and recorded by exact SHA-256 and byte
   size in the selected pre-authorization source map;
3. none of those MP3s was a current governed Media Library asset or had current
   per-asset processing rights; and
4. LPL-01 remained blocked before ingestion, transcription and blueprint
   review.

The split `production/` and `public release` wording is preserved exactly and
is interpreted only as production/public release remaining prohibited. The
statement authorizes processing of the four exact selected hashes; it does not
extend to Assignment audio or other legacy media.

### Accepted scope

- Private non-production ingestion through the current exact-asset quarantine,
  inspection, malware-scan and acceptance lifecycle.
- Transcription and AI-assisted derivative lesson drafting through
  project-owned adapters under existing privacy, no-training,
  provider-retention, spend-ceiling and kill-switch controls.
- Program-scoped web/Android learner-delivery rights in India for a later
  immutable publication, only after exact English academic and Kannada review.
- Exact source lineage and immutable evidence for the four selected hashes:
  Introduction, Practice, Conversation and Interaction.

### Conditions and limitations

- Sharing, cloning, public URLs and production/public release are prohibited.
- This is rights/process authorization, not academic, Kannada, accessibility,
  answer-key, blueprint, publication or release approval.
- Provider output cannot write marks, mastery, review completion or unlocks.
- Unlabeled Assignment audio remains excluded pending provenance/privacy
  classification and a separate exact authorization.
- Current tenant, appointment, permission, exact-hash, scan, attachment,
  publication, privacy and retention controls remain mandatory.
- No personal identity, specialist qualification, timestamp-of-day or commit
  SHA is inferred from the approval statement.

<a id="app-0046"></a>

## APP-0046 — DR-0020 Option A Lesson Practice Loop foundation approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-10 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Exact proposal artifact | [DR-0020_LESSON_PRACTICE_FOUNDATION_REQUEST.md](DR-0020_LESSON_PRACTICE_FOUNDATION_REQUEST.md) |
| Approved proposal SHA-256 | `698d03917a494c29c58d8d34a02f13a09271c8ac192e1bf73117a04cf0b1cab3` (before its status, resolved date and resolution section) |
| Accepted specification SHA-256 | `5bda28ff6be15bee37c32d00fb701ce776108a750db7a4ee8dccddecadd53f6e` (before its status and acceptance record) |
| Locked verification-scope SHA-256 | `a7bf02f70dc5bb602c3f2b4341718bd04d1a218c829c82a3db9998b70d1b9dd4` (before its status and acceptance annotation) |
| Proposed ADR SHA-256 | `009d8af0f3d50747c04f6c6ab75a8267b00db6e407937f28aada2d036ac51a61` (before acceptance annotations) |
| Related decision | [D-0140](DECISIONS.md) |
| Accepted ADR | [ADR-0015](adr/0015-lesson-practice-foundation.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
Approve DR-0020 Option A as written.
```

### Context and interpretation

Immediately before this statement:

1. the retained ADMIN-MEDIA PostgreSQL, full verification, E2E, build and
   backup/restore repository gates had passed;
2. LPL-00's implementation inventory confirmed that daily-speaking v1 has one
   check and 0/100 scoring, offline v1 contains a plaintext answer key,
   conversations have no lesson-practice link and no level-unlock authority
   exists;
3. DR-0020 recommended extending the existing content/publication, mission
   attempt, mastery/review, conversation and voice authorities rather than
   creating a second learning subsystem; and
4. the proposed specification, ADR and locked verification scope fixed exact
   multi-item scoring, answer isolation, speaking-evidence and unlock rules.

The human supplied the exact statement requested by DR-0020. In that bounded
context it is consolidated development approval under ADR-0001. The
role-based identifier avoids inferring a personal identity not supplied in
the approval statement.

### Accepted scope

- Accept the typed `global-english.lesson-practice-loop.v1` definition,
  immutable publication component and exact learner practice-context model.
- Reuse and generalize the existing deterministic mission attempt, mastery and
  review authority under `lesson-practice-v1`, with 3–7 weighted reviewed
  items, an exact 80-point threshold and unchanged daily-v1 evidence.
- Accept append-only lesson-stage evidence, lesson-scoped `skill-focus`
  conversation links, separate speaking-evidence lifecycle states and the
  later `level-unlock-v1` transparent decision rule.
- Lock pre-submission answer/rationale isolation for new learner, AI,
  telemetry and offline surfaces while retaining the documented daily-v1
  compatibility exception.
- Activate the bounded LPL-00 specification and verification scope and
  authorize the ordered non-production LPL checkpoints one at a time,
  beginning with LPL-01 exact inventory/content work.
- Permit bounded live-AI and repository-media analysis/drafting/evaluation
  only through project-owned adapters with exact provenance, spend ceilings,
  kill switches, rights/privacy controls and human publication review.

### Conditions and limitations

- This approval closes LPL-00 and authorizes LPL-01 only as the next ordered
  checkpoint. It does not accept unbuilt LPL-01 or LPL-02+ results.
- It does not authorize production/public release, unlimited spend, provider
  credentials in source control, fabricated missing content, automatic AI
  publication or AI-controlled scores, mastery, review schedules or unlocks.
- Existing exact media scanning, accessibility, rights, English/Kannada review,
  voice consent/retention/deletion and cross-tenant controls remain mandatory.
- One human exercised consolidated development roles. This is not independent
  specialist, academic/language, legal or production review; retained
  pre-production gates remain.
- No personal identity, specialist qualification, timestamp-of-day or commit
  SHA is inferred from the approval statement.

<a id="app-0039"></a>

## APP-0039 — DR-0014 Option A secure reference-admin parity approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-09 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Exact proposal artifact | [DR-0014_ADMIN_REFERENCE_SCOPE_AND_POLICY_REQUEST.md](DR-0014_ADMIN_REFERENCE_SCOPE_AND_POLICY_REQUEST.md) |
| Approved proposal SHA-256 | `7a88bc31444b4e99107c5b4def16c42d0efc18a07bcc587a235737934dbd4d10` (before its status and resolution note) |
| Approved specification artifact | [ADMIN_REFERENCE_PARITY_PLAN.md](ADMIN_REFERENCE_PARITY_PLAN.md) |
| Approved specification SHA-256 | `caa24f8dc581c1ed755be8c90af53bc45a6ff8d678eaccbd3b0c6278a941294b` (before its approval and gate-state annotations) |
| Accepted ADR | [ADR-0014](adr/0014-secure-reference-admin-functional-parity.md) |
| Related decision | [D-0125](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.2 §§8.3, 9.2, 16.5 and 31 |

### Exact human statement

```text
Approve DR-0014 Option A as written.
```

### Context and interpretation

Immediately before this statement:

1. ADMIN-DISC-01 had inventoried the authenticated reference portal through
   read-only requests and mapped each visible feature family to the repository;
2. the inventory showed that literal password, delete, group, personal-data,
   content and media behavior would conflict with accepted architecture and
   security/privacy/tenancy decisions;
3. DR-0014 presented secure governed parity, literal parity and current-core
   profiles, recommending Option A through fifteen exact policy rules and an
   exact four-section master-plan amendment; and
4. the repository had stopped before ADMIN schema or behavior changes pending
   this explicit product, architecture, security, privacy and tenancy decision.

The human supplied the exact statement requested by DR-0014. In that bounded
context it is consolidated development approval by the Accountable Product
Owner, Technical Decision Owner, Security Reviewer, Privacy Reviewer and
Tenancy Reviewer under ADR-0001. The role-based identifier avoids inferring a
personal identity that was not supplied in this conversation.

### Accepted scope

- Accept all fifteen rules of DR-0014 Option A exactly as written and approve
  the complete capability inventory and checkpoint order in
  `ADMIN_REFERENCE_PARITY_PLAN.md` as the feature specification.
- Accept safe domain equivalents rather than reference code, design, data,
  media or unsafe mechanics: invitation-based staff activation,
  membership-scoped roles, class-specific non-destructive lifecycle commands,
  immutable/versioned content and authoritative evidence, and typed
  configuration.
- Authorize deterministic manual or externally reconciled entitlements,
  external facilitated-session scheduling, explicitly typed groups,
  rubric-based human assignment review, moderated communications, in-app posts
  and public editorial CMS within the retained gates.
- Preserve deterministic marks, mastery, progression, attendance, permissions,
  retention and entitlements. AI remains advisory and cannot set those
  authoritative records.
- Preserve ADR-0003 organization context and cross-tenant rules, ADR-0004
  publication/review/rights authority, ADR-0007 audio/private-media controls
  and D-0123 web-only fresh-TOTP privileged mutations.
- Amend the canonical master plan to version 3.2 exactly at §§8.3, 9.2, 16.5
  and 31 through ADR-0014 in the same accepted change.
- Permit later approved bounded checkpoints to proceed without a generic
  `Continue`, while retaining their specifications, exit evidence and all
  explicitly listed later decision gates.

### Conditions and limitations

- Existing work order is unchanged: PRIV-DSR-01, REL-01 and MAINT-01 precede
  ADMIN-GOV-01, and ADMIN checkpoints then execute one at a time in the
  approved sequence.
- This approval records product scope and architecture only. It does not claim
  or accept any ADMIN feature implementation, schema, migration, endpoint,
  generated client, UI, test result, reference-data import or production
  behavior.
- Open peer-to-peer social chat, in-product conferencing/class recording,
  consumer checkout/coupons/accounting and broad attendance/LMS semantics
  remain deferred.
- Communication purpose/retention/moderation, payment-provider/spend,
  production-data migration/licensing/reconciliation, deployment and
  production release remain separate gates exactly as stated in Option A.
- One human exercised consolidated development roles. This is not independent
  specialist or production review. Independent security, privacy and tenancy
  review, qualified legal review where applicable and explicit production
  authorization remain required.
- No personal identity, specialist qualification, timestamp-of-day or
  repository commit SHA is inferred from the approval statement.

<a id="app-0040"></a>

## APP-0040 — DR-0015 Option A development-continuation boundary

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-09 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Role exercised | Accountable Product Owner |
| Evidence source | Exact human instruction in the active Codex conversation |
| Exact proposal artifact | [DR-0015_PRODUCTION_REVIEW_WAIVER_REQUEST.md](DR-0015_PRODUCTION_REVIEW_WAIVER_REQUEST.md) |
| Approved proposal SHA-256 | `a40c05bc4bd590e83102720b4eb18b1e0f0a7eb12c84c89883faf0cbffc6355d` (before its status, resolved date and resolution section) |
| Related decision | [D-0127](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
Approve DR-0015 Option A as written.
```

### Context and interpretation

Immediately before this statement:

1. the human had supplied a blanket legal and independent privacy/security/
   tenancy review-waiver instruction;
2. ADR-0001 and the master plan made those reviews mandatory pre-production
   gates and prohibited representing consolidated development review as
   independent;
3. DR-0015 separated development continuation from a material proposal to
   remove the internal production gates; and
4. Option A recommended continuing ordered non-production work without repeat
   review prompts while preserving the production gates and honest evidence.

The human supplied the exact acceptance statement requested by DR-0015. In
that bounded context, the Accountable Product Owner accepts the
development-continuation interpretation. No architecture, security, privacy,
tenancy or master-plan rule is amended.

### Accepted scope

- Continue REL-01, MAINT-01 and later approved bounded implementation, testing
  and staging work without pausing for legal or independent security, privacy
  and tenancy review.
- Do not repeatedly request those reviews during ordinary non-production
  checkpoints merely because the review evidence remains open.
- Continue to record implementation evidence, limitations and unresolved
  production gates honestly at each checkpoint.
- Preserve ADR-0001, the master plan and every existing security, privacy,
  tenancy, consent, retention, deletion and authoritative-record rule.

### Conditions and limitations

- Independent security, privacy and tenancy review and qualified legal
  validation remain mandatory before production.
- This approval is not a legal opinion, specialist-review finding, production
  acceptance, deployment authorization, Google Play public-release approval or
  permission to use real learner data in production.
- Missing review must never be represented as completed, passed or waived.
- Option B, any superseding ADR/master-plan amendment and explicit production
  authorization remain separate exact-artifact decisions.
- No personal identity, specialist qualification, timestamp-of-day or
  repository commit SHA is inferred from the approval statement.
- No master-plan amendment is made.

<a id="app-0041"></a>

## APP-0041 — DR-0016 Option A organization-administration policy approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-09 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Exact proposal artifact | [DR-0016_ADMIN_ORG_AUTHORITY_LIFECYCLE_RETENTION_REQUEST.md](DR-0016_ADMIN_ORG_AUTHORITY_LIFECYCLE_RETENTION_REQUEST.md) |
| Approved proposal SHA-256 | `79d71ab91b1e07d725c2676a42e37c763ea4096e0f3b09dd288f562fffba8541` (before its status, resolved date and resolution section) |
| Related decision | [D-0132](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
Approve DR-0016 Option A as written.
```

### Context and interpretation

Immediately before this statement:

1. ADMIN-SHELL-01 was complete and ADMIN-ORG-01 was the only ordered active
   checkpoint;
2. read-only backend, frontend and adversarial inventories found unresolved
   registry, delegation, lifecycle, selected-session, invitation-secret and
   retention choices affecting foundation schema and tenant authority;
3. AGENTS.md required the path to stop rather than infer those choices; and
4. DR-0016 recommended a narrow staff-only policy that adds no writable
   organization setting or rich trainer personal-data record.

The human supplied the exact statement requested by DR-0016. In that bounded
context it is consolidated development approval by the Accountable Product
Owner, Technical Decision Owner, Security Reviewer, Privacy Reviewer and
Tenancy Reviewer under ADR-0001. The role-based identifier avoids inferring a
personal identity not supplied in the approval statement.

### Accepted scope

- Keep organization registry metadata read-only and leave
  `organization.settings.manage` unseeded.
- Limit ADMIN-ORG membership and invitations to trainers and organization
  administrators; keep trainer profiles derived and purpose minimized.
- Apply an actor-held atomic-permission delegation ceiling, immutable system
  roles, permission-derived privileged-session policy and a concurrency-safe
  final-manager invariant.
- Issue code-only staff invitations with bounded expiry, digest-only storage
  and no secret replay, provider delivery or recipient field.
- Make deactivation a reversible membership suspension that removes roles,
  cohort assignments and current appointments and revokes only credentials
  selected for that membership; reactivation restores no authority.
- Bind web authentication sessions to the selected organization membership and
  expose only count-level credential projections.
- Retain new content-free administration history for 365 days, clear terminal
  invite digests within one hour and cap encrypted backup lifetime at 30 days,
  subject to retained immutable-evidence and pre-production legal/review gates.

### Conditions and limitations

- This approval authorizes the bounded non-production specification and
  implementation work only. It does not accept unbuilt results or authorize
  deployment, production, real production data or public release.
- Learner administration, global identity edit/recovery, MFA reset, platform
  organization lifecycle, generic configuration, rich trainer profile fields,
  provider-backed invitation delivery and new appointment kinds remain out of
  scope.
- Existing ADR-0004 qualification/appointment evidence remains private and
  immutable where referenced; prior valid approvals are not invalidated by a
  later staff suspension.
- One human exercised consolidated development roles. This is not independent
  specialist or legal review, and the retained pre-production gates remain.
- No personal identity, specialist qualification, timestamp-of-day or
  repository commit SHA is inferred from the approval statement.

<a id="app-0044"></a>

## APP-0044 — Project-owner media-rights attestation

| Field | Evidence |
|---|---|
| Status | Accepted as owner-level attestation; not a per-asset runtime approval |
| Decision date | 2026-08-09 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Roles exercised for development | Accountable Product Owner; retained Rights Confirmer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Durable evidence artifact | [ADMIN_MEDIA_RIGHTS_ATTESTATION.md](ADMIN_MEDIA_RIGHTS_ATTESTATION.md) |
| Evidence artifact SHA-256 | `51e8835828cd5d0d31bfcb074db356c76f73e7e28d387f0a7658fdf5a8b668ca` |
| Related decision/request | D-0138; [DR-0019](DR-0019_ADMIN_MEDIA_LIBRARY_RIGHTS_LIFECYCLE_REQUEST.md) |
| Recorder | Codex, acting only as evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
I have overall rights of all media used here. Proceed with the next steps.
```

### Context and interpretation

Immediately before this statement:

1. ADMIN-CONTENT-01 was repository-complete and ADMIN-MEDIA-01 was the only
   ordered next checkpoint;
2. missing exact model-audio and rights evidence deliberately blocked new
   daily-mission publications;
3. ADR-0004 already retained the Accountable Product Owner as Rights Confirmer
   but required each runtime approval to be bound to an exact asset hash,
   program, use, channel, territory, effective period, permission and accepted
   appointment; and
4. the repository had no operator upload/quarantine/scan/media-lifecycle
   specification or implementation.

The statement is therefore recorded as owner-level rights evidence and
authorization to prepare the next ordered decision/specification. It is not
expanded into facts about a file, licence term, territory, release channel or
third-party source that the human did not state.

### Accepted scope

- The statement may be referenced as an umbrella rights-holder and
  owner-controlled basis document for media the product owner supplies or
  designates for this project.
- ADMIN-MEDIA-01 may proceed through repository inspection and its required
  bounded decision/specification gate.
- Future appointed per-asset confirmations may reference the durable
  attestation while still recording exact object and allowed-use scope.

### Conditions and limitations

- This entry does not approve DR-0019, an unbuilt media feature, an uploaded
  object, legacy/reference-site import, academic/Kannada content, deployment,
  production delivery or public release.
- It creates no permission, role grant, authority appointment, rights revision
  or approved scan result and does not permit Codex or an importer to invent
  one.
- Malware scanning, exact byte/hash verification, learner-visible
  accessibility review and ADR-0004's permission-plus-appointment predicate
  remain mandatory.
- No personal name, legal entity, contract language, specialist qualification,
  timestamp-of-day or commit SHA is inferred from the statement.

<a id="app-0045"></a>

## APP-0045 — DR-0019 Option A governed-media policy approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-09 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer; retained Rights Confirmer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Exact proposal artifact | [DR-0019_ADMIN_MEDIA_LIBRARY_RIGHTS_LIFECYCLE_REQUEST.md](DR-0019_ADMIN_MEDIA_LIBRARY_RIGHTS_LIFECYCLE_REQUEST.md) |
| Approved proposal SHA-256 | `5c18ca97be6eded6d6a39882dae0f05a675827451b32aced5f0cb05dacf603b1` (before its status, resolved date and resolution section) |
| Related decision | [D-0139](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
Approve DR-0019 Option A as written.
```

### Context and interpretation

Immediately before this statement:

1. ADMIN-CONTENT-01 was repository-complete and ADMIN-MEDIA-01 was the only
   ordered active checkpoint;
2. APP-0044/D-0138 preserved the product owner's blanket media-rights
   attestation without converting it into automatic per-asset approval;
3. the implementation inventory found no operator upload/quarantine/scan,
   governed accessibility metadata, replacement/retirement or media UI; and
4. DR-0019 recommended an exact-asset, scan-gated, appointed-rights and
   immutable-successor pipeline inside the frozen ADMIN-MEDIA row.

The human supplied the exact statement requested by DR-0019. In that bounded
context it is consolidated development approval under ADR-0001. The
role-based identifier avoids inferring a personal identity not supplied in
the approval statement.

### Accepted scope

- Seed the six frozen media-library permission definitions and only the exact
  Direct setup grants selected by Option A.
- Implement resumable private quarantine, exact inspection/hash validation,
  clean scanner evidence, explicit quarantine acceptance and reconciled
  object lifecycle for model audio and lesson video.
- Add append-only technical/accessibility metadata, exact English/Kannada
  review, program-scoped appointed rights confirmation, allowlisted usage,
  immutable replacement lineage and retirement.
- Pin exact clean-scan, accessibility and rights evidence into new
  publication manifests while preserving prior immutable publications.
- Deliver the bounded `/operator/media` API, generated client, six governed
  catalogues and accessible manifest-gated Media Library/Content Studio
  handoff with full tenant, conflict and failure coverage.

### Conditions and limitations

- This approval authorizes bounded non-production specification and
  implementation work only. It does not accept unbuilt results or authorize
  deployment, production, real production data, legacy/reference import or
  public release.
- APP-0044 remains reusable umbrella evidence, not automatic rights approval.
  Each asset still requires exact hash/scope confirmation by a permitted,
  accepted in-scope `rights_confirmer` appointment.
- Images, documents, learner recordings, conversation media, cross-owner
  sharing/cloning, public preview, provider spend and later ADMIN capabilities
  remain out of scope.
- One human exercised consolidated development roles. This is not independent
  specialist or legal review, and all retained pre-production gates remain.
- No personal identity, specialist qualification, timestamp-of-day or commit
  SHA is inferred from the approval statement.

<a id="app-0043"></a>

## APP-0043 — DR-0018 Option A content-workspace policy approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-09 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Exact proposal artifact | [DR-0018_ADMIN_CONTENT_AUTHORING_PUBLICATION_REQUEST.md](DR-0018_ADMIN_CONTENT_AUTHORING_PUBLICATION_REQUEST.md) |
| Approved proposal SHA-256 | `9774813580bd9522fa9c077bbca0a3cfda9c37d834d82eb2b1ca66b34f8abd72` (before its status, resolved date and resolution section) |
| Related decision | [D-0136](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
Approve DR-0018 Option A as written.
```

### Context and interpretation

Immediately before this statement:

1. ADMIN-LEARNER-01 was repository-complete and ADMIN-CONTENT-01 was the only
   ordered next checkpoint;
2. authority and implementation inventories found that graph schema v1 has no
   level, owner draft-workspace or same-owner successor contract;
3. the operator API could not create a program/version/document/graph or exact
   graph review, while publication closure also required an omitted core
   mission and unresolved media/scheduling boundaries; and
4. DR-0018 recommended a versioned owner-workspace that preserves existing
   graph-v1/publication evidence and reuses ADR-0004 authority.

The human supplied the exact statement requested by DR-0018. In that bounded
context it is consolidated development approval by the Accountable Product
Owner, Technical Decision Owner, Security Reviewer, Privacy Reviewer and
Tenancy Reviewer under ADR-0001. The role-based identifier avoids inferring a
personal identity not supplied in the approval statement.

### Accepted scope

- Add an organization-owned, expected-version draft-workspace and append-only
  history with explicit graph-v2 levels, core delivery-bundle mission edges
  and same-owner successor lineage; existing graph-v1 publications remain
  immutable and must be forked before ADMIN editing.
- Reuse the existing curriculum permission catalogue and role packs, creator/
  reviewer separation, appointments, source/Kannada append commands, review
  evidence, rights predicates and immutable publication services.
- Limit writable payloads to bounded typed governed-text and existing daily-
  speaking-mission forms, with private non-executable preview and read-only
  handling for unknown legacy schemas.
- Expose exact structure/English/Kannada/readiness queues and guarded immediate
  publish, future schedule, explicit due activation, schedule cancellation,
  retirement and rollback with content-free audit/outbox evidence.
- Keep media creation/linking in ADMIN-MEDIA-01; new media-dependent releases
  remain visibly blocked until their exact required assets and rights exist.
- Preserve first-party web/fresh-TOTP, tenant constraints, header idempotency,
  deterministic locks, optimistic conflicts, bounded reads and no-hard-delete
  retention across the complete API-to-UI slice.

### Conditions and limitations

- This approval authorizes the bounded non-production specification and
  implementation work only. It does not accept unbuilt results or authorize
  deployment, production, real production data or public release.
- Cross-owner sharing/cloning, enrollment assignment, authority management,
  media upload/quarantine/rights management, public preview, CMS, AI drafting,
  provider/spend and reference data/media import remain out of scope.
- Scheduled activation is an explicit human publisher command in this slice;
  no background identity may invent publication or review authority.
- One human exercised consolidated development roles. This is not independent
  specialist or legal review, and ADR-0004's human academic/Kannada/rights and
  all retained pre-production gates remain.
- No personal identity, specialist qualification, timestamp-of-day or
  repository commit SHA is inferred from the approval statement.

<a id="app-0042"></a>

## APP-0042 — DR-0017 Option A learner-administration policy approval

| Field | Evidence |
|---|---|
| Status | Accepted |
| Decision date | 2026-08-09 |
| Time zone | Asia/Kolkata |
| Human approver identifier | `Codex task accountable product owner` |
| Roles exercised for development | Accountable Product Owner; Technical Decision Owner; Security Reviewer; Privacy Reviewer; Tenancy Reviewer |
| Evidence source | Exact human instruction in the active Codex conversation |
| Exact proposal artifact | [DR-0017_ADMIN_LEARNER_PROFILE_ENROLLMENT_LIFECYCLE_REQUEST.md](DR-0017_ADMIN_LEARNER_PROFILE_ENROLLMENT_LIFECYCLE_REQUEST.md) |
| Approved proposal SHA-256 | `4e64e897daf66f0476d856baa8f8e0553c474903ef6bca9e952fd2f5c5185e58` (before its status, resolved date and resolution section) |
| Related decision | [D-0134](DECISIONS.md) |
| Recorder | Codex, acting only as author and evidence recorder |
| Evidence repository revision | The commit containing this record; exact SHA was unavailable while the record was authored |
| Master-plan amendment | None |

### Exact human statement

```text
Approve DR-0017 Option A as written.
```

### Context and interpretation

Immediately before this statement:

1. ADMIN-ORG-01 was repository-complete and ADMIN-LEARNER-01 was the only
   ordered next checkpoint;
2. authority and code/test inventories found unresolved profile-correction,
   learner-role, enrollment and membership/credential lifecycle choices that
   affect personal data, tenant authority and immutable learning records;
3. AGENTS.md required runtime work to pause instead of inferring those
   foundation choices; and
4. DR-0017 recommended a minimal relationship-based policy that excludes
   support notes and global identity/account administration.

The human supplied the exact statement requested by DR-0017. In that bounded
context it is consolidated development approval by the Accountable Product
Owner, Technical Decision Owner, Security Reviewer, Privacy Reviewer and
Tenancy Reviewer under ADR-0001. The role-based identifier avoids inferring a
personal identity not supplied in the approval statement.

### Accepted scope

- Limit learner administration to current-organization learner memberships
  and safe summary fields; add no platform-global directory or identity,
  password/PIN, MFA or recovery control.
- Add code-only invitations that can grant exactly one active same-tenant role
  containing only `learning.participate`, with an optional active cohort.
- Keep adult/onboarding evidence immutable and represent governed goal changes
  as attributed append-only corrections with a versioned current projection.
- Enforce one pending/active enrollment, explicit versioned create/cohort/
  curriculum-pin/withdraw commands, immutable pins and cohort-derived trainer
  summaries; renewal creates a successor rather than reactivating history.
- Make learner suspension reversible while retaining learner-safe roles and
  learning evidence, revoking only selected credentials and restoring no
  credential; retain ADR-0006's maximum 24-hour disconnected lease.
- Use exact atomic permissions, fresh TOTP, idempotency, deterministic locking,
  tenant constraints, bounded reads and the accepted 365-day live-history,
  one-hour invitation-digest and 30-day backup boundaries.
- Keep support-note permission keys unseeded and add no note table, route, UI,
  export field or placeholder behavior.

### Conditions and limitations

- This approval authorizes the bounded non-production specification and
  implementation work only. It does not accept unbuilt results or authorize
  deployment, production, real production data or public release.
- Global user identity correction/reassignment, phone/email/admission lookup,
  password/PIN/MFA reset, global session revocation, direct learner-trainer
  assignment, support notes, learner exports and provider invitation delivery
  remain out of scope.
- Existing adult/onboarding, enrollment-pin, learning-evidence and privacy
  authority remains immutable or append-only as specified; ordinary learner
  administration cannot end a membership or erase evidence.
- One human exercised consolidated development roles. This is not independent
  specialist or legal review, and the retained pre-production gates remain.
- No personal identity, specialist qualification, timestamp-of-day or
  repository commit SHA is inferred from the approval statement.
