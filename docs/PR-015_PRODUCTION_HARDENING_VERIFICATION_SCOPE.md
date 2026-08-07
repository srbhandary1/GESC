# PR-015 production hardening verification scope

**Status:** Bound to
[PR-015_PRODUCTION_HARDENING_AUTHORIZATION_REQUEST.md](PR-015_PRODUCTION_HARDENING_AUTHORIZATION_REQUEST.md).
Begin-implementation authorized under [APP-0032](APPROVALS.md#app-0032)
(plan step E1).  
**Prior slice:** PR-014 closed-beta foundations accepted under
[APP-0031](APPROVALS.md#app-0031) at
`44b0e672e004cf3846cddabc456c21153fa4607e`.

## Authority and boundary

PR-015 may be authorized only by a distinct APP after PR-014 closed-beta
foundations acceptance. This verification scope covers the **bounded first
APP**: operations hardening foundations plus closure of APP-0031 Android
closed-beta evidence gaps.

It does **not** authorize Phase 1 Definition of Done (F0), Google Play public
release, remaining PR-013 privacy/sampled-review work, or waiver of
independent specialist reviews.

Master-plan PR-015 deliverables this first APP advances locally:

- load and soak testing (harness + recorded evidence);
- security and privacy verification (fail-closed checks already in product;
  no specialist sign-off claim);
- accessibility evidence package;
- backup/restore drill;
- monitoring and alerts skeleton + incident/support runbooks;
- staged deployment and rollback rehearsal;
- pilot analytics skeleton (aggregate-only).

## Vertical slice (when authorized)

1. Record physical device-matrix evidence for `com.globalenglish.app` critical
   journeys and permission/interruption recovery (closes APP-0031 AN-06–AN-08
   gaps).
2. Produce a reproducible signed closed-beta AAB/APK from documented steps and
   record `make verify` / Docker-pinned equivalent where feasible (AN-09/AN-10).
3. Run documented load/soak against synthetic stack at 2× pilot planning peak;
   capture latency/error summaries without learner content logs.
4. Execute one non-production backup/restore drill; record RPO/RTO result
   against planning targets.
5. Land monitoring/alert stubs + incident/support runbooks (no raw audio /
   transcripts / secrets in logs).
6. Rehearse staged deploy + rollback for API/web; record dry-run evidence.
7. Package accessibility evidence for accepted learner/operator paths
   (automated + manual checklist).
8. Expose aggregate-only pilot analytics surface (usage vs learning
   distinction skeleton; digests / safe rollups).

## Reuse

- Production Android tree `apps/web/android-app` / ADR-0012 (APP-0031)
- Operator content-workflow + audit (APP-0029)
- Existing `make verify`, e2e, ai-eval gates
- Master plan §20 service objectives as **planning targets**, not contracts

## Explicit non-evidence

- Demo APK / Vite `/demo/*` (D-0078 / D-0092 / D-0094)
- Anecdotal load tests without recorded harness parameters
- Automated accessibility scans alone as production a11y sign-off
- Production environment cutover or real learner production traffic

## Verification (when authorized)

| ID | Check |
|---|---|
| PH-01 | Device-matrix critical journeys pass on documented hardware/emulator set |
| PH-02 | On-device: no auth credentials in WebView / web storage / ordinary Preferences |
| PH-03 | Permission denial / interruption recover without credential leakage |
| PH-04 | Signed closed-beta artifact reproducible; assemble evidence recorded |
| PH-05 | `make verify` (or Docker-pinned equivalent) recorded for this APP |
| PH-06 | Load/soak harness at 2× pilot planning peak; summary evidence attached |
| PH-07 | Non-production backup/restore drill meets planning RPO/RTO or gaps recorded |
| PH-08 | Monitoring/alert stubs + incident/support runbooks present |
| PH-09 | Staged deploy + rollback dry-run evidence recorded |
| PH-10 | Accessibility evidence package for accepted critical paths |
| PH-11 | Aggregate-only pilot analytics skeleton (no PII CSV) |
| PH-12 | Cross-tenant / auth fail-closed regression still green for touched surfaces |

## Explicit non-goals

- Google Play public release
- F0 Phase 1 Definition of Done sign-off
- Remaining PR-013 sampled review / privacy ops
- Production OTP / real learner production data
- Expanding live Azure spend beyond existing APPs
- Specialist review waiver

## Exit gate

This bounded PR-015 slice is ready for APO exact-commit acceptance only when
PH-01–PH-12 pass for the authorized scope (or explicit recorded limitations
preserve unclaimed items) and Play public release, F0, and remaining PR-013
items remain separately gated.
