# Production readiness closure matrix

**Status:** Execution matrix; production is not authorized  
**Updated:** 18 September 2026
**Authority:** Master plan §27, accepted ADRs, F0 gap assessment and specialist
review packet

This matrix separates repository work from evidence that only an intended
tester, independent reviewer, provider or Accountable Product Owner can supply.
A green build cannot substitute for those external gates.

| Gate | Current evidence | Pass condition | Required next authority/action |
|---|---|---|---|
| Current-host closed-group artifact | **Pass** | Production-path APK contains the active HTTPS origin and PKCE client; package/signature/integrity and protected staging verified | Rebuild after every temporary-host rotation until a stable product domain exists |
| Web and Android intended-tester journey | **Open** | Named test record passes sign-in, Learn, Practice, Progress, reconnect and sign-out on Web and physical DM-01/DM-02; Android storage-negative evidence attached | Product owner supplies the two physical devices and intended consenting tester sessions; execute the [closed-group runbook](../CLOSED_GROUP_TEST_RUNBOOK.md) |
| Live conversation model privacy | **Blocked externally** | Microsoft grants modified abuse monitoring and the resource exposes accepted `ContentLogging=false` evidence; no-call preflight, capped live authorization and supervised smoke pass | Subscription owner completes the Microsoft process; independent privacy/legal/security reviewers accept the exact provider evidence |
| Ordinary learner Speech | **Decision blocked** | DR-0028 Phase A is explicitly approved and implemented; independent privacy/legal/security prerequisites pass; separately authorized Phase B supervised smoke and deletion reconciliation pass | APO uses the exact Phase A statement in DR-0028; later supply independent evidence and exact Phase B authorization |
| Independent L1 quality | **Mechanism pass; review open** | Exact catalogue revisions are approved and every locked conversation case passes `make ai-eval-human-review` with independently attributed, locale-matched, payload-bound evidence | Appoint independent `en-IN`, `kn-IN`, `hi-IN`, `ml-IN`, `ta-IN` and `te-IN` reviewers and execute the L1 runbook |
| Reviewed learning pathway | **APP-0058 phased rollout approved; LPL-01 active** | LPL-01 has commissioned, rights-cleared, independently reviewed and published Level 1 content with remediation, delayed review and unlock evidence | Complete the exact Level 1 transcript/blueprint/review exit; do not begin LPL-02 or later waves until it passes |
| Independent production reviews | **Open** | Security, privacy/DPDP, tenancy, accessibility, language/content and legal rows each have exact-revision APP evidence with no release-blocking finding | Appoint reviewers listed in `F0_SPECIALIST_REVIEW_PACKET.md`; APO cannot self-waive them |
| Production operations | **VPS soft-launch pass; production operations open** — exact revision `56f2caa` is supervised on the new VPS, UFW/default-deny and external health pass, and an encrypted backup restores in isolation; current HTTPS is an ephemeral Quick Tunnel and backup custody remains same-host | Stable product HTTPS/DNS, production secrets, alert routing, encrypted off-box backups/PITR restore evidence, authenticated capacity/soak and incident/support ownership pass | Rotate/disable the disclosed root password, configure stable product DNS/TLS, approve off-box custody and PITR, then execute capacity/soak, alert and incident/support gates; see [deployment evidence](VPS_SOFT_LAUNCH_DEPLOYMENT_2026-09-18.md) |
| Android production distribution | **Open** | Protected release-key custody, signed AAB, Play App Signing/closed-track validation and physical release-candidate matrix pass | APO authorizes production signing and Play closed track only after preceding gates |
| F0/release acceptance | **Open** | All hard blockers above are evidenced at one exact commit; remote release gate passes; APO records exact-commit acceptance | Run the F0 consideration ceremony; public/Play release remains a separate explicit decision |

## Ordered execution

1. Run the already-built APK and Web journey with intended testers on DM-01 and
   DM-02; file content-free evidence.
2. In parallel outside the repository, complete the Microsoft model privacy
   process, appoint specialist/L1 reviewers and commission the reviewed Level
   1 pathway.
3. After explicit DR-0028 Phase A approval, implement and verify the bounded
   fail-closed Speech slice. Do not make a provider request in Phase A.
4. Import completed L1 evidence through the strict gate and repair/re-review
   every rejected or hash-invalidated item.
5. Provision the stable production environment and execute operational,
   security, accessibility and physical release-candidate evidence there.
6. Rebuild at the exact release revision, run every repository/release gate,
   then seek exact-commit F0 and release acceptance.

## Immediate stop rules

Do not enable learner Speech, the live conversation-model kill switch,
production signing, Play upload or public release merely because an earlier
row passed. A changed hostname, catalogue, prompt, model output, provider
evidence or release revision invalidates only the evidence bound to that
artifact and must be rerun.
