# Decision ledger

This ledger summarizes accepted repository decisions. A material change to the
canonical baseline requires an ADR, an impact note and a corresponding update
to the master plan.

| ID | Status | Decision | Authority |
|---|---|---|---|
| D-0001 | Accepted | `ENTERPRISE_PRODUCT_MASTER_PLAN.md` version 3.0 is the canonical product, architecture and delivery baseline. | Master plan §0 |
| D-0002 | Accepted | Work proceeds through numbered, gated checkpoints and complete vertical slices. | Master plan §§0.2, 23 and 26 |
| D-0003 | Accepted | PR-000 contains governance and documentation only; product scaffolding begins in PR-001. | Master plan §§26 and 33 |
| D-0004 | Accepted | The core architecture is a Laravel modular monolith with project-owned AI and speech adapter interfaces. | Master plan §§10, 13 and 14 |
| D-0005 | Accepted | Authoritative learning records are deterministic; AI output is advisory. | Master plan §§0.3 and 32 |
| D-0006 | Accepted | Web/PWA uses Sanctum cookie sessions and Android uses Passport Authorization Code with PKCE behind a project authentication abstraction. | Master plan §11.2 |
| D-0007 | Accepted | The initial audience is Kannada-speaking adults aged 18 or older. | Master plan §§1.1 and 18.4 |

## Decision requests before affected checkpoints

These items do not block PR-000. They must be resolved before the indicated
implementation becomes authoritative.

| ID | Needed by | Decision required |
|---|---|---|
| DR-0001 | PR-003 | Authentication token lifetimes, rotation, device binding, identity recovery and phone-number reassignment policy |
| DR-0002 | PR-003 | Platform-global versus organization-owned resource classification and organization-context enforcement |
| DR-0003 | PR-005 | Content ownership, approval roles and curriculum-sharing rules |
| DR-0004 | PR-007 | Offline local-data retention, shared-device isolation, logout purge and cross-device conflict policy |
| DR-0005 | PR-009 | Recording retention, consent states, provider processing boundaries and deletion workflow |
| DR-0006 | Validation Beta | Exact beta scope, sample sizes, subgroup definitions and measurable acceptance thresholds |
| DR-0007 | Commercial authorization | Responsibility and total budget when specialist content production is commissioned |
| DR-0008 | First accepted ADR or approved specification | Accountable approval roles and the evidence that records acceptance or approval |
