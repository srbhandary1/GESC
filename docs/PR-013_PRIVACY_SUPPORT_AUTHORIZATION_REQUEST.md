# PR-013 remaining privacy and support ops — authorization request

**Status:** Authorized begin-implementation under [APP-0035](APPROVALS.md#app-0035).  
**Prior acceptance:** Bounded content-workflow + audit [APP-0029](APPROVALS.md#app-0029)
at `9220ab27baa6dc29d82caf8e9e97e94f7df7219d`.  
**Deferred under:** [APP-0034](APPROVALS.md#app-0034) evidence closure.  
**Policy:** [ADR-0007](adr/0007-recording-consent-retention-provider-processing-and-deletion-policy.md)

## Purpose

Authorize the remaining operator vertical slice so pilot staff can support
learners and honour privacy requests **without raw database access**.

## Proposed authorized scope

1. Org-scoped **support diagnostics** listing (failed sync, stuck attempts)
   using digests/safe metadata only — no raw conversation transcripts/audio.
2. Operator **account-erasure / retention** workflows aligned with ADR-0007
   (delete/export paths that do not require DBA access).
3. Bounded **PII export** for the requesting learner’s organization-scoped
   records (CSV/JSON), with audit events.
4. OpenAPI + generated clients, cross-tenant tests, STATUS / DECISIONS /
   QUALITY_REVIEW updates.

## Explicit non-goals (remain unauthorized unless separately stated)

- Consent-gated sampled speaking / transcript / audio review (requires
  ADR-0007 amendment + distinct APP)
- Full institution ops pack (attendance, notices, billing as production modules)
- Production promote of live AI spend beyond existing APPs
- F0 DoD accept or Google Play public release

## Proposed APO begin-implementation statement

```text
APO authorize only remaining PR-013 support diagnostics and privacy/account
ops under PR-013_PRIVACY_SUPPORT_AUTHORIZATION_REQUEST.md; sampled speaking
review, F0 DoD, and Play public release remain unauthorized.
```

## Engineering note

No implementation of this slice may begin until the Exact statement above is
recorded under `docs/APPROVALS.md`. Until then the gap remains **APP-deferred**
in the F0 §27 matrix.
