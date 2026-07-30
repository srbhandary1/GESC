---
status: accepted
date: 2026-07-30
deciders:
  - Codex task accountable product owner
  - Codex task technical decision owner
consulted:
  - Codex as author and evidence recorder, not approver
informed: []
amends_master_plan: true
approval_evidence:
  - APP-0006
approval_reference: Codex task 019fb263-fe9a-77f0-a1db-e3d563707715
---

# ADR-0005: Bring four UI language packs into Phase 1

## Status

Accepted under [APP-0006](../APPROVALS.md#app-0006).

This ADR amends the Phase 1 interface-localization baseline. It does not
accept PR-006, authorize PR-007, approve production, or expand curriculum,
speech, audio or AI-provider checkpoints.

## Context

The canonical baseline is a Kannada-guided spoken-English product for
Kannada-speaking adults. PR-004 implemented separate UI catalogues with
`kn-IN` requested by default, `en-IN` as the deterministic fallback, honest
review metadata and a strict production policy.

The roadmap originally placed all Tamil, Telugu, Malayalam and Hindi
language-pack work in Phase 3. The product owner has requested that the UI
portion enter Phase 1 while explicitly preserving:

- English as the learning language;
- existing governed curriculum and its Kannada explanation requirements;
- existing speech-recognition and speech-evaluation scope;
- existing model-audio and AI-explanation scope; and
- independent human review before production use.

UI catalogue support can be brought forward without claiming that curriculum
or provider behavior supports those languages. That distinction must remain
visible in code, contracts and documentation.

## Decision

- Phase 1 supports interface locales `kn-IN`, `ml-IN`, `ta-IN`, `te-IN`,
  `hi-IN` and `en-IN`.
- `kn-IN` remains the default requested interface locale.
- `en-IN` remains the governed source and deterministic fallback.
- The five translated catalogues have exact source key parity, current source
  revision/hash linkage, canonical SHA-256 hashes and
  `pending-independent-review` status.
- Codex-authored translations are implementation drafts only. They have no
  reviewer, approval time or independent-review claim.
- Development, test and explicit review builds may render a current draft
  only with the visible translation-preview notice.
- Ordinary production builds use `approved-only`; pending, stale, rejected,
  incomplete or dishonestly approved translations fall back visibly to
  English.
- Each script uses a self-hosted Noto Sans variable font with system
  fallbacks. BCP 47 `lang` metadata is preserved on localized spans and the
  document.
- Locale selection remains ephemeral. This change introduces no browser
  credential or preference persistence.
- API governed-content locale enums remain `en-IN` and `kn-IN`. UI locale
  support must not silently expand curriculum publication eligibility.
- English remains the learning language. Curriculum, speech recognition,
  model audio and AI explanations are unchanged.

The exact implementation boundary and human release gates are in
[the Phase 1 multilingual UI scope](../PHASE1_MULTILINGUAL_UI_EXPANSION_SCOPE.md).

## Alternatives considered

### Keep every additional language in Phase 3

This preserves the original delivery order but does not meet the approved
request to expose the four UI drafts during Phase 1.

### Expand full language packs in Phase 1

Rejected. It would require governed curriculum translations, new appointed
language reviewers, speech benchmarks, model-audio and provider evaluation,
safety testing and materially broader content operations. The request
explicitly excludes those changes.

### Treat English copies as translated catalogues

Rejected as misleading. A locale may be supported only with real draft text
and honest review metadata, or it must fall back to English.

## Consequences

### Positive

- Phase 1 can exercise the complete UI localization and accessibility path
  for five Indian guidance languages.
- Review status, source drift and production fallback remain deterministic.
- Script fonts are bundled rather than depending on host font availability.
- Curriculum and provider boundaries remain unchanged.

### Negative and trade-offs

- Five draft catalogues now require independent linguistic and accessibility
  review before production.
- Bundle size grows because four additional script-font families are
  self-hosted.
- More locales increase selector, wrapping, font and browser test coverage.
- Phase 1 UI availability must not be marketed as complete learning-language
  support.

## Security, privacy and tenancy impact

- No authentication, authorization, credential or organization-scoping path
  changes.
- Locale selection remains in React memory only; no localStorage, IndexedDB,
  cookie or server preference is added.
- Fallback telemetry retains only the message key, requested/resolved locale,
  policy, reason and revision. It contains no message text, parameters or
  learner data.
- Catalogue assets are static application resources with no tenant-owned
  records.

## Authoritative records and consistency impact

No database schema, migration or authoritative learner record changes.
Catalogue revisions and hashes are repository-governed build artifacts.
Curriculum publication records and locale eligibility remain unchanged.

## API, client and operational impact

- No public API operation or generated client changes.
- The governed-content locale enum remains `en-IN | kn-IN`.
- The web UI gains four selector values and self-hosted font assets.
- Rollback consists of reverting this ADR's source, catalogue, font and
  documentation changes together.

## Master-plan amendment

This ADR amends:

- §1.1 to distinguish default/explanation locale from selectable UI previews;
- §5.4 to add the four Phase 1 UI locale codes and preserve curriculum
  boundaries;
- §9 to add the UI packs to Phase 1 and explicitly defer added-language
  curriculum/speech/audio/AI work;
- §26 Step 1 to expand the localization bootstrap; and
- §31 Phase 3 to retain full content/speech language-pack rollout after the
  UI-only Phase 1 foundation.

The impact note is
[the Phase 1 multilingual UI expansion scope](../PHASE1_MULTILINGUAL_UI_EXPANSION_SCOPE.md).

## Approval evidence

[APP-0006](../APPROVALS.md#app-0006) records the exact human instruction,
decision context, development authorization, master-plan amendment and
production limitations. Codex is only the author and evidence recorder.

## Verification

- exact 129-key parity and placeholder parity across six catalogues;
- NFC, revision, source-hash and catalogue-hash validation;
- pending-review and strict production-fallback resolver tests;
- selector and `lang` metadata tests for all locales;
- axe checks for all six locale states;
- Noto Sans script-font loading checks;
- 320–1280 CSS-pixel reflow, enlarged-text and 44-pixel target checks;
- `make verify`, `make e2e` and `make build`.

## Follow-up

- Obtain separate independent review for Kannada, Malayalam, Tamil, Telugu
  and Hindi exact catalogue revisions.
- Complete manual screen-reader, browser zoom, wrapping and representative
  Android TalkBack review for every approved locale.
- Treat any curriculum, speech, audio or AI language expansion as a separate
  approved language-pack checkpoint.
