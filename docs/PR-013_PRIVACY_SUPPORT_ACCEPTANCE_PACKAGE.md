# PR-013 remaining privacy and support ops — APO acceptance package

**Status:** Accepted under [APP-0036](APPROVALS.md#app-0036) at .  
**Governing begin:** [APP-0035](APPROVALS.md#app-0035)  
**Governing request:** [PR-013_PRIVACY_SUPPORT_AUTHORIZATION_REQUEST.md](PR-013_PRIVACY_SUPPORT_AUTHORIZATION_REQUEST.md)  
**Policy:** [ADR-0007](adr/0007-recording-consent-retention-provider-processing-and-deletion-policy.md)

## Delivered behaviour

1. **Support diagnostics** — `GET /api/v1/operator/support/diagnostics`  
   Org-scoped digests for stuck learner attempts, recoverable voice failures,
   and open deletion requests. No transcripts, audio, or conversation bodies.
2. **Privacy export** — `POST/GET /api/v1/operator/privacy/exports`  
   Idempotent JSON identifier summary for one learner membership (consent,
   recording, deletion receipt, session, and attempt IDs only).
3. **Account erasure** — `POST/GET /api/v1/operator/privacy/erasures`  
   ADR-0007 status machine (`pending` / `completed` / `partially_completed` /
   `restricted`) with org-scoped voice withdrawal deletion receipts.
4. Permissions seeded and attached to Direct organization-admin pack:
   `support.diagnostics.read`, `privacy.export.request`,
   `privacy.erasure.request`.
5. OpenAPI + generated contracts; operator console diagnostics panel;
   cross-tenant 404 integration coverage.

## Verification evidence

| Check | Result |
|---|---|
| `OperatorPrivacySupportTest` (sqlite path) | Pass (2 tests) |
| `OperatorConsole` unit test | Pass |
| Localization package tests | Pass (31) |
| OpenAPI Redocly lint | Pass |
| Host `make verify` / postgres Integration suite | Not claimed in this package |

## Explicit non-claims

- Sampled speaking / transcript / audio review (still ADR-0007 forbidden)
- F0 Definition of Done Exact accept
- Google Play public release
- Waiver of independent specialist reviews
- Physical Android device matrix
- Reviewed pathway content (DR-0007 commissioning)

## Proposed Exact APO accept statement

```text
APO accept PR-013 remaining privacy and support ops at 1357eb161d3754da3b8d6b888bd1e3b0a8698ff9 with its
recorded limitations; sampled speaking review, F0 DoD, and Play public release
remain unauthorized.
```
