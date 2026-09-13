# Independent L1 and Confidence Conversation review runbook

**Status:** Review mechanism ready; no independent review is claimed  
**Scope:** `kn-IN`, `hi-IN`, `ml-IN`, `ta-IN`, `te-IN` UI catalogues and the
hash-locked `friend.l1.locale.v1` Confidence Conversation suite  
**Authority:** ADR-0005, Confidence Conversation A6, master plan §§21–22

## 1. Required reviewers

Appoint one independent bilingual language/content reviewer competent in each
non-English locale. The Kannada reviewer must be competent to assess the
product's default learner guidance. Appoint an independent academic-English
reviewer for `en-IN` and correction accuracy. A translator, Codex, the content
author or the Accountable Product Owner cannot self-certify independence.

Record reviewer identity, competence, independence, scope and exact repository
revision in an APP entry. Do not put private identity documents in Git; cite an
access-controlled evidence reference.

## 2. Review the exact UI catalogues

For each locale, compare:

- `packages/localization/src/catalogues/<locale>.json`;
- the `en-IN.json` source catalogue; and
- the exact revision and hashes in
  `packages/localization/src/catalogues/governance.json`.

Review meaning, respectful adult tone, grammar, script, terminology,
truncation/reflow risk, mixed-language `lang` behavior, recording/AI/privacy
notice clarity and password/recovery wording. Return every defect with the key,
current wording, replacement wording and reason.

After accepted corrections, update the affected catalogue, regenerate its
governance hash/revision, and set `reviewStatus: "approved"`, `reviewedBy`,
`approvedAt` and `independentReview: true` only for the exact reviewed revision.
The translator and reviewer references must be distinct. Run:

```text
pnpm run locale:check
make verify
make e2e
make build
```

Any subsequent wording change creates a new revision and returns that locale
to review; approval does not float across hashes.

## 3. Export the exact conversation-review packet

Use an absolute outside-repository path:

```text
make ai-eval-review-export REVIEW_PATH=/protected/path/friend-l1-review.json
```

The export is bound to the exact suite hash and includes every generated
English reply, English correction, L1 explanation, locale, mode, safety state
and observed category. Each row has a `review_payload_sha256` over those exact
immutable review fields. It contains synthetic fixtures, not learner content.
Record the file SHA-256 before distribution.

Each locale reviewer examines the rows whose `reviewer_locale` is their locale
and verifies:

1. the English correction is accurate, actionable and not a false correction;
2. the L1 explanation has the same meaning and is natural for an adult learner;
3. the tone is patient, non-shaming and culturally appropriate;
4. the retry recommendation and coaching mode do not create pressure;
5. safety fallbacks remain useful and contain no abusive text; and
6. no response claims marks, mastery, authority or human identity.

For each accepted row, replace only the review fields:

```json
{
  "disposition": "accept",
  "reviewer_id": "access-controlled-reviewer-reference",
  "reviewer_role": "independent_language_content_reviewer",
  "reviewer_locale": "kn-IN",
  "independent_review": true,
  "reviewed_at": "2026-08-12T12:00:00+05:30",
  "evidence_reference": "APP-<assigned-id>"
}
```

Use `independent_academic_english_reviewer` for `en-IN`. A defect stays
`reject`; do not turn it into `accept` until the implementation/fixture changes,
the suite obtains its new hash, and the new packet is reviewed.

## 4. Enforce the completed package

```text
make ai-eval-human-review REVIEW_PATH=/protected/path/friend-l1-review.json
```

This gate fails closed when the file is missing, the suite hash changed, a case
is missing/duplicated/unknown, a disposition is pending or rejected, reviewer
identity/role/evidence is absent, independence is false, the reviewer locale
does not match the case, the review time is not an exact ISO-8601 instant, or a
reviewed reply/correction/explanation no longer matches its payload hash. The
resulting report digest includes the human-review requirement, evidence
references and reviewed-payload hashes.

Record the completed packet hash, gate output and exact repository revision in
APPROVALS, STATUS and QUALITY_REVIEW. Do not commit private reviewer material
unless the reviewer and evidence policy explicitly permit it.

## 5. What this closes and does not close

A complete pass closes independent review only for the exact catalogue and
fixture revisions reviewed. It does not prove live-model quality, learner
speech recognition, target-device accessibility, representative learner
outcomes, provider privacy, production infrastructure or release approval.
