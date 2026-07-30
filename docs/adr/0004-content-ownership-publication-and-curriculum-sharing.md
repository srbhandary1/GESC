---
status: accepted
date: 2026-07-30
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
  - S R Bhandary as Tenancy Reviewer
consulted:
  - S R Bhandary as English Academic Reviewer, qualification recorded as MA
  - S R Bhandary as Kannada Reviewer, qualification recorded as MA
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: false
approval_evidence:
  - APP-0004
approval_reference: Codex task 019fae0d-d5d2-7e32-b2c2-ced466b4b0cb
---

# ADR-0004: Content ownership, publication authority and curriculum sharing

## Status

Accepted under [APP-0004](../APPROVALS.md#app-0004).

This ADR resolves DR-0003 and authorizes implementation of only PR-005 within
this decision and the canonical master-plan scope. It does not accept PR-005,
authorize PR-006, approve a curriculum publication or authorize production.

## Context

PR-005 introduces the first authoritative curriculum and publication records.
The master plan already requires organization scoping, immutable published
versions, reviewed Kannada, explicit publication workflow, enrollment version
pinning, transactions, idempotency, audit evidence and rollback.

ADR-0003 also establishes that Global English Direct is an ordinary tenant,
not a null or bypass scope, and that later records belong to an organization
when that organization controls their authority, visibility or lifecycle.

The remaining decision is how those constraints apply to:

- Global English-authored and institution-authored curriculum;
- author, translator, reviewer and publisher authority;
- cross-organization use, withdrawal and cloning;
- immutable publication and rollback; and
- organization-owned enrollments pinned to curriculum owned by the same or a
  different organization.

These choices affect tenancy, authoritative records, academic and language
policy, and foundation schema. They cannot be inferred from implementation
convenience.

## Decision

The following is the recommended decision and becomes authoritative only if
this ADR is accepted.

### Ownership classification

- No program, curriculum, lesson, objective, content revision, localized
  variant, media-rights record or publication is platform-global.
- Global English-authored material is owned by the Global English Direct
  organization. Platform ownership of that organization does not create a
  tenancy bypass.
- Institution-authored material is owned by the authoring institution.
- Every curriculum/content table carries a non-null owner
  `organization_id`. Ownership is immutable.
- Stable permission keys remain platform-global exactly as allowed by
  ADR-0003. Locale tags and content-type identifiers are code/configuration
  constants in PR-005, not new platform-global database records.
- Cross-organization availability is represented only by an explicit grant
  to a specific immutable publication. A grant does not transfer ownership,
  make the content global or let the recipient edit the owner's records.

This classification extends ADR-0003 without changing its platform-global
resource boundary.

### Organization permissions and accountable roles

PR-005 will introduce these project-owned stable permission keys:

- `curriculum.author`;
- `curriculum.translate.kn`;
- `curriculum.review.en`;
- `curriculum.review.kn`;
- `curriculum.publish`;
- `curriculum.share.manage`;
- `curriculum.shared.read`;
- `curriculum.shared.clone`;
- `enrollments.curriculum.assign`;
- `curriculum.authority.manage`;
- `media.rights.approve`; and
- `curriculum.suspend`.

The permission definitions are platform-global under ADR-0003. Role grants and
every exercise of them are organization-owned. Their authority is not
interchangeable:

- An author/editor may create a new draft revision but cannot approve that
  English revision.
- A translator may create a Kannada variant but cannot approve a variant they
  translated.
- An English Academic Reviewer must be explicitly appointed and must be a
  different human from every author of the selected English revision.
- A Kannada Reviewer must be explicitly appointed and must be a different
  human from every translator of the selected Kannada variant.
- A Publisher may schedule, activate, retire or roll back a publication only
  when every required review is current. The publisher cannot waive, create
  on behalf of, or replace review evidence.
- To support a lean pilot, a publisher may also hold an appointed reviewer
  role, but only when that person did not author or translate any selected
  revision. The publication record must preserve every role exercised.
- One person may hold both appointed reviewer roles only when the Accountable
  Product Owner explicitly records both appointments and the person's
  qualification. That consolidation is not represented as two independent
  specialist reviews.

Owner-side authoring, translation, review, rights approval, publication,
sharing and ordinary suspension require an active membership in the owner
organization plus the specific owner-side permission. A generic platform
operation, platform employment or membership in a recipient organization
cannot publish or edit owner content.

Recipient-side preview, enrollment assignment and cloning require an active
membership in the recipient organization, the matching recipient-side
permission and a current grant for the requested use. Recipient membership
never supplies owner-side authority.

Permission assignment alone is not an academic, language or rights
appointment. PR-005 introduces an organization-owned, append-only authority
appointment for `english_academic_reviewer`, `kannada_reviewer` and
`rights_confirmer`. Each appointment records:

- appointed user and membership, authority kind and owner organization;
- scope, as the whole owner catalogue or explicit program identifiers;
- qualification/accountable-basis reference and APP-NNNN or other durable
  human-evidence reference;
- appointing membership, valid-from and optional valid-until times; and
- append-only appointment, expiry and revocation events.

Creating or revoking an appointment requires an active owner membership with
`curriculum.authority.manage`; a self-appointment is allowed only when the
linked human evidence explicitly records the consolidated roles. A review or
rights confirmation requires both the applicable permission and a valid,
in-scope appointment. Its evidence snapshots the appointment identifier and
event revision.

Ordinary expiry or role removal stops new approvals but preserves the validity
of an approval made while the appointment was valid. A for-cause revocation
may explicitly invalidate prior approvals from an effective time and scope;
that event requires a safe reason, fresh privileged MFA and audit/outbox
evidence. Publication and activation fail when any selected approval has been
invalidated. An appointee may surrender an appointment but cannot appoint,
expand or reinstate themselves solely by holding the review/right-approval
permission.

Codex, an automated seed process and synthetic test identities are not human
academic or language approvers. A Codex-authored synthetic seed remains a
draft unless later reviewed and approved by the appointed humans.

### Revision and localization model

- Programs, curriculum versions, levels, modules, objectives, prerequisite
  edges, lessons, items and mission definitions use generic internal keys.
  Public labels and counts are content, not hard-coded schema.
- English source and localized content are append-only revisions. Editing a
  draft creates a successor revision and advances a draft head; it does not
  overwrite an earlier revision.
- Every revision records its parent, sequence, content hash, author membership
  and creation time.
- A localized variant records locale, selected source revision and source
  hash, translator membership, content hash and its review evidence.
- Every selected source revision that affects instruction, target language,
  accepted answers, rubrics, feedback rationale, remediation, safety,
  prerequisites, completion or future progression is
  `pedagogically_authoritative`, whether or not the learner sees it directly.
  Only identifiers and purely operational metadata may be non-pedagogical.
  The allowed non-pedagogical record types are a project-owned code policy, not
  a caller or publisher flag. Every pedagogically authoritative revision
  requires English Academic Reviewer approval.
- Every learner-visible source revision has a governed
  `localization_requirement`:
  - `kn_required` is the default and requires an approved current Kannada
    variant;
  - `english_only_pedagogical` is allowed only for target-language material
    whose purpose would be defeated by translation, with an English Academic
    Reviewer approval and non-sensitive rationale key.
- A publisher cannot change `localization_requirement`. A new classification
  is a new source revision and requires new academic review. Instructions,
  explanations, feedback and learner actions cannot use
  `english_only_pedagogical`.
- A source successor makes every variant linked to an older source revision
  stale for a new publication. The prior variant remains historical evidence.
- English academic and Kannada review records are append-only. A later content
  change requires new review; an old approval is never copied forward.
- UI message catalogues from PR-004 remain separate from curriculum content
  revisions and cannot satisfy curriculum publication review.

### Media-rights predicate

Every selected media asset has one selected append-only rights-evidence
revision with:

- asset and owner-organization identifiers;
- rights holder and provenance reference keys;
- licence, release or ownership basis and an evidence-object SHA-256 hash;
- allowed uses, including learner delivery and, when applicable, tenant
  sharing and derivative/cloning use;
- allowed channels and territories;
- effective and expiry timestamps;
- review state, confirmer membership and confirmation time; and
- retention or deletion constraints that apply to the asset.

The Accountable Product Owner is the rights-confirmation authority unless a
specific human delegate is recorded. Runtime approval requires that human to
act through an active owner membership with `media.rights.approve` and a valid,
in-scope `rights_confirmer` appointment. A publisher, author or file uploader
cannot infer or self-create rights approval without both authorities and the
recorded confirmation.

The rights predicate is deterministic:

- evidence status is `approved`;
- evidence belongs to the asset owner and selected evidence revision;
- learner delivery is allowed for every release channel and territory;
- sharing is allowed before a share grant is issued;
- derivative use is allowed before cloning;
- the evidence is effective and unexpired at schedule and activation time; and
- the stored evidence hash and selected asset hash still match.

Unknown, pending, rejected, expired or mismatched evidence blocks the
operation. A scheduled activation rechecks the predicate. Expiry of rights for
an active publication makes the delivery predicate fail at the recorded
instant, without depending on a worker or mutable flag, and blocks new and
existing delivery. A subsequent owner-authorized or named platform suspension
event records the operational response; no unaudited background identity is
invented. All content, pins and history remain preserved until an authorized
replacement publication is activated.

A successor rights-evidence revision never changes or reactivates an existing
publication manifest. Restoring delivery with successor evidence requires a
new immutable publication identity whose manifest references that evidence,
even when its curriculum/content revisions are otherwise unchanged.

### Publication and rollback

- Publication is an aggregate operation over one curriculum version. The
  service derives a closed manifest by traversing every reachable module,
  objective, prerequisite edge, lesson, item and mission reference from the
  version root. A caller cannot publish a hand-picked partial child list.
- The canonical manifest records every selected source revision, required
  localized variant, media asset, rights-evidence revision and stable content
  hash in deterministic order. Its aggregate SHA-256 hash identifies the
  immutable release contents.
- The workflow remains `Draft` to `Linguistic review` to `Academic approval`
  to `Scheduled` to `Published` to `Retired`. State is derived from append-only
  workflow/publication events rather than destructive status rewrites.
- Publication fails unless:
  - every pedagogically authoritative English source revision, visible or
    hidden, has a current academic approval from a valid in-scope appointee
    distinct from its authors;
  - every `kn_required` source has exactly one selected, approved Kannada
    variant from a valid in-scope Kannada appointee distinct from its
    translators and linked to that exact source revision and hash;
  - every `english_only_pedagogical` source has the required academic approval
    and rationale and is not an instruction, explanation, feedback or learner
    action;
  - author/reviewer and translator/reviewer separation passes;
  - every selected media asset passes the deterministic rights predicate;
  - every selected revision belongs to the owner organization and is part of
    the selected curriculum version;
  - the derived manifest is closed, internally consistent and hash-complete;
  - no selected review, rights record or workflow prerequisite was withdrawn
    or superseded before activation; and
  - optimistic-concurrency and idempotency preconditions pass.
- There is no implicit English fallback for `kn_required` curriculum content.
  An approved `english_only_pedagogical` classification is intentional English
  content, not a localization fallback.
- The published snapshot and its component revisions are immutable. A
  correction produces new revisions and a new publication.
- Publish, schedule, activate, retire and rollback commands lock the affected
  aggregate and execute in one PostgreSQL transaction with their audit,
  idempotency and outbox records.
- A caller-supplied idempotency key is scoped to organization, operation and
  aggregate and is bound to a normalized request fingerprint. An identical
  replay returns the original result; reuse for a different request fails.
- Rollback appends an event selecting a prior immutable publication as the
  active release. It never edits or deletes either publication. The event
  records actor membership, previous and selected publication, safe reason
  key, correlation identifier and time.
- Rollback re-runs the current activation gates against the exact reviews,
  authority appointments and rights evidence pinned by the target manifest. It
  fails when that evidence is expired, withdrawn, invalidated or otherwise no
  longer sufficient. New rights evidence requires a new publication rather
  than mutation of the rollback target.
- Retiring or rolling back a release stops it being selected for new
  enrollments; it does not rewrite existing enrollment pins or learning
  evidence.

### Explicit cross-organization sharing

- Only a published curriculum release can be shared.
- A share grant is owned and managed by the curriculum owner. It identifies
  the owner organization, recipient organization, immutable publication,
  allowed uses, effective time, issuer and append-only status history.
- Issuing, changing or ordinarily withdrawing a grant requires an active owner
  membership with `curriculum.share.manage`. The recipient cannot grant,
  expand, withdraw or re-share the owner's rights.
- Supported uses, granted independently, are:
  - preview/read;
  - create new enrollment pins; and
  - clone into a new recipient-owned draft.
- Uses are denied unless explicitly granted. There is no implied access from
  organization kind, a public flag, a guessed identifier or prior access.
- Recipient preview requires `curriculum.shared.read`; curriculum assignment
  requires `enrollments.curriculum.assign`; cloning requires
  `curriculum.shared.clone`. A grant proves content availability, not actor
  authority.
- Recipient access is resolved through a dedicated shared-content query path
  using membership-derived recipient context. It must not disable tenant
  scopes or expose owner drafts, reviews, membership data or other grants.
- The recipient cannot edit, review, publish, retire or re-share the owner's
  records.

### Withdrawal and emergency suspension

- Ordinary withdrawal is append-only and prevents new previews, enrollment
  pins and clones at its effective time. It requires an active owner
  membership with `curriculum.share.manage`.
- Existing active enrollments retain read-only access to their pinned
  immutable publication until those enrollments complete or are withdrawn.
  This preserves learner continuity and does not grant access to later
  versions.
- Rights, safety or legal emergencies use a separate suspension event. An
  owner-side suspension or resumption requires `curriculum.suspend`, fresh
  privileged MFA, a safe reason key and append-only audit evidence.
- A platform-wide legal or safety intervention instead requires the named
  `content.emergency-suspend` platform capability under ADR-0003, a target
  owner organization, fresh privileged MFA, a safe reason and a platform audit
  event before context is established. It grants no edit or publication
  authority.
- Suspension stops new and existing delivery but preserves the publication,
  enrollment pin, prior evidence and audit history.
- Emergency suspension requires an affected-scope record, accountable actor
  and remediation or migration path. Resumption requires the same class of
  authority and a new event after the blocking condition is resolved. Neither
  operation deletes or rewrites history.
- PR-005 records the policy and minimum state needed for these outcomes; later
  learner delivery must enforce it before shared content can be played.

### Cloning

- Cloning is permitted only when the active share grant explicitly allows it
  and every selected asset's rights permit derivative use.
- A clone creates a new draft owned by the recipient organization with
  immutable lineage to the source publication.
- The clone receives new identifiers. It does not inherit approvals,
  publication events, share grants, reviewer claims or active-enrollment
  status.
- Every cloned source or translation must be reviewed and published under the
  recipient organization's authority before use.
- Withdrawing the source share does not delete an already valid clone, but
  rights metadata and contract terms may require an explicitly audited
  suspension. PR-005 does not infer legal rights from technical availability.

### Enrollment-to-version pinning

- An accepted organization admission may exist before curriculum assignment,
  but it uses a distinct `pending_curriculum` status and cannot deliver
  learning content or record learning evidence.
- PR-005 migrates every pre-PR-005 unpinned `active` foundation enrollment to
  `pending_curriculum`. This is safe only because no learner-content delivery,
  attempt or progression records exist before PR-005. The migration preserves
  membership, cohort and original enrollment time.
- Before changing constraints, the migration preflights every status. An
  unpinned pre-PR-005 `withdrawn` row remains a valid pre-activation
  cancellation. An unpinned `completed` row is inconsistent because no
  completion behavior existed; migration stops and requires an explicitly
  reviewed mapping to `pending_curriculum` or `withdrawn` rather than silently
  inventing a curriculum history.
- When institution invite acceptance creates a cohort enrollment for a learner,
  it creates `pending_curriculum`. Trainer, administrator and cohortless
  learner invites continue to create no enrollment.
- Transition to `active` requires `enrollments.curriculum.assign` and
  atomically assigns exactly one immutable curriculum publication and
  curriculum version. Every active enrollment is therefore pinned.
- The enrollment retains its own non-null organization ownership. It also
  records the curriculum owner organization so same-owner and explicitly
  shared pins are distinguishable.
- A same-owner pin requires a publication owned by the enrollment
  organization.
- A cross-owner pin additionally requires an active share grant for the
  enrollment organization at pin time. Database constraints tie the recorded
  owner, recipient, publication and grant identifiers together.
- The pin is immutable after activation. Rollback, retirement or a later
  publication does not silently move it.
- `pending_curriculum` is unpinned and may transition to pinned `active` or
  unpinned `withdrawn`. `active` and `completed` always require a pin.
  `withdrawn` preserves whether cancellation occurred before or after
  activation: it may be unpinned only when no activation/pin ever existed.
  Database checks and immutable activation metadata enforce these states.
- A future curriculum upgrade is an explicit, authorized, transactional and
  audited operation that preserves the prior assignment and learning
  evidence. PR-005 will not add an implicit upgrade path.

### Tenancy, audit and privacy boundaries

- Every organization-owned content model uses membership-derived context,
  permission policies, owner-prefixed indexes and business uniqueness, and
  database constraints against cross-tenant relationships.
- The deliberately cross-organization share path is isolated, allowlisted and
  tested for both owner and recipient authority. It is not a general
  cross-tenant model bypass.
- Cross-tenant tests cover read, list, create, update, delete, route binding,
  relationship attachment, publication, sharing, withdrawal, cloning,
  enrollment pinning, jobs and mass assignment where each category applies.
- Audit and outbox payloads contain stable identifiers and non-sensitive keys
  only. They exclude content bodies, translation text, learner data, raw media,
  credentials and provider payloads.
- Published content and submitted evidence are never hard-deleted by ordinary
  organization actions.

## Alternatives considered

### Platform-global Global English curriculum

Rejected because it would weaken ADR-0003's ordinary-tenant boundary and
create special publication, audit and access paths. Ownership by Global
English Direct plus explicit sharing is uniform and testable.

### Copy every shared release into the recipient organization

Rejected because copied records obscure provenance, create inconsistent
withdrawal behavior and can misrepresent the owner's review evidence as the
recipient's approval. Explicit sharing and separately governed cloning have
different semantics.

### Mutable published rows and a single `published` flag

Rejected because in-place correction, rollback and retry would lose the exact
content seen by enrolled learners and weaken auditability.

### Publisher self-certification of missing reviews

Rejected because publication authority is not academic or language authority
and cannot make stale, self-reviewed or absent evidence valid.

### Move existing enrollments whenever the active release changes

Rejected because it would change authoritative learner context without
consent, destroy reproducibility and make prior attempts ambiguous.

## Consequences

### Positive

- Global English and institution content use one tenant model.
- Publication, rollback and enrollment evidence remain reproducible.
- Cross-organization use is explicit, least-privilege and revocable.
- Language and academic approval cannot be manufactured by the publisher or
  implementation agent.
- Clones have clear provenance without inheriting unearned approval.

### Negative and trade-offs

- Explicit sharing and cross-owner enrollment pins require more composite
  constraints and authorization tests.
- Append-only revisions and events use more storage and require head/current
  projections.
- Lean operations still need appointed humans who satisfy creator/reviewer
  separation.
- Existing-enrollment continuity after ordinary withdrawal requires a
  retained-access path for immutable content.
- Emergency suspension and curriculum upgrade need later bounded workflows.

## Security, privacy and tenancy impact

Publication and sharing are privileged organization operations. Missing or
ambiguous organization context fails closed. Owner and recipient permissions
are checked independently, and public identifiers must not reveal the
existence of another organization's drafts or grants.

Curriculum bodies are not learner personal data, but audit records may identify
staff actors. Record stable membership/user identifiers rather than names,
content text or credentials. Media records store rights metadata and object
references, not unlicensed assets. Independent tenancy review remains a
pre-production gate under ADR-0001.

## Authoritative records and consistency impact

Revisions, review evidence, publication snapshots, publication events, share
events and enrollment pins are authoritative records. Multi-write transitions
use PostgreSQL transactions, aggregate locking, optimistic concurrency,
request-fingerprint-bound idempotency, audit and the transactional outbox.

Rights expiry denial is derived directly from the immutable expiry time and
needs no background actor or database mutation. When an owner or named
platform operator subsequently records suspension, that command uses the same
transaction, idempotency, audit and outbox boundary as other publication
transitions.

Published revisions, publication snapshots and history events are immutable.
Current-head projections may change transactionally, but they never replace
the append-only history from which their state can be verified.

PR-005 must use additive migrations with explicit rollback or forward-fix
notes. The enrollment migration adds `pending_curriculum`, preflights all
existing statuses, converts unpinned active foundation rows, preserves
pre-activation withdrawal, fails closed on an unpinned completed row, narrows
learner-cohort invite enrollment creation to the pending state and enforces a
valid immutable pin before activation. Its OpenAPI enum, generated client and
accepted PR-003 tests must change together.

## API, client and operational impact

Any PR-005 HTTP operations must be added to the OpenAPI 3.1.1 source and
regenerated clients. Editing and transition commands require concurrency
preconditions; replayable commands require `Idempotency-Key`. Problem responses
must not reveal cross-tenant existence or raw validation/content details.

Operational views need safe identifiers for revision, review, publication,
share, enrollment pin, actor, correlation and outbox status. Rollback and
emergency suspension require explicit reason keys and support runbooks before
production use.

## Master-plan amendment

This ADR does not amend the master plan. It makes the ownership, content
governance, publication, version-pinning and tenant-safety requirements in
master-plan §§5.4, 6, 10, 13, 15, 16, 21, 23, 25, 26, 29 and 32 testable.

## Approval evidence

[APP-0004](../APPROVALS.md#app-0004) records acceptance of this ADR at proposal
revision `d9d7cba4b2ccc0d9f2e8656e5fd19bab9ce9dc99`.

`S R Bhandary` exercised the Accountable Product Owner, Technical Decision
Owner and Tenancy Reviewer roles, remained the Rights Confirmer, appointed
themselves as English Academic Reviewer and Kannada Reviewer, and explicitly
accepted both reviewer roles. `MA` is recorded exactly as the supplied
qualification/accountable basis for each reviewer role without an inferred
specialization.

All consolidated roles are non-independent review. Independent tenancy,
English-academic and Kannada-language specialist review remains required
before production. Codex authored and recorded this decision but did not
approve it. No master-plan amendment is made.

## Verification

ADR acceptance requires:

- parsed YAML front matter and valid repository-local links;
- explicit human approval evidence for every role listed above; and
- confirmation that every acceptance cites the exact committed ADR revision.

PR-005 implementation acceptance then requires:

- real-PostgreSQL schema, state-transition, immutability, concurrency,
  idempotency, rollback, stale-translation and separation tests;
- derived-manifest closure, governed localization requirement and complete
  visible/hidden authoritative revision academic/Kannada review tests;
- authority-appointment scope, validity, surrender, ordinary expiry,
  for-cause invalidation and permission-plus-appointment tests;
- rights approval, schedule-time recheck, clock-driven expiry denial,
  successor-release, rollback revalidation, authorized suspension, sharing and
  derivative-use tests;
- owner/recipient cross-tenant denial and explicit-share allow tests;
- owner share/suspension, recipient use and named platform-emergency
  authorization tests;
- same-owner and cross-owner enrollment-pin constraint tests;
- enrollment migration preflight, pending activation, pre-activation
  withdrawal and inconsistent-completion failure tests;
- OpenAPI lint and generated-client drift checks for every API change;
- a synthetic draft bundle without real learner data or unlicensed media; and
- `make openapi`, `make verify`, `make e2e` and `make build` as applicable.

## Follow-up

- Record the accepted ADR and human evidence before PR-005 implementation.
- Implement only the PR-005 foundation after that gate passes.
- Complete independent tenancy and specialist language/academic review before
  production.
- Add learner delivery enforcement for share withdrawal and emergency
  suspension only in its authorized later checkpoint.
