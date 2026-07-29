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
| D-0008 | Accepted | PR-001 declares PHP 8.4.23, Composer 2.10.2, Node.js 24.18.1 and pnpm 11.18.0; committed Composer and pnpm lockfiles are the exact dependency authority. | Master plan §§7, 25.2, 26 and 31 |
| D-0009 | Accepted | Local platform dependencies use digest-pinned PostgreSQL 18.4, Redis 8.8.1, SeaweedFS 4.40, Mailpit 1.30.6 and WireMock 3.13.2 images with loopback-only host bindings. | Master plan §§12 and 26; PR-001 implementation |
| D-0010 | Accepted | SeaweedFS is the PR-001 local S3-compatible implementation; this does not select or constrain the production object-storage provider. | Master plan §§7, 26 and 32.1; PR-001 implementation |
| D-0011 | Accepted | Local mail and OTP paths terminate in Mailpit and WireMock fakes and must not contact real recipients or production providers. | Master plan §§7, 24 and 26 |
| D-0012 | Accepted | Stable commands that belong to later checkpoints fail with an explicit checkpoint message rather than reporting placeholder success. | Master plan §25.2; PR-001 implementation |
| D-0013 | Accepted | PR-001 removes Laravel's sample identity model/migration and duplicate frontend scaffold so identity remains in PR-003 and the React application remains under `apps/web`. | Master plan §§12 and 26 |
| D-0014 | Accepted | Application package metadata is `proprietary` so scaffolding does not accidentally grant an open-source licence; final distribution and third-party notice terms remain a product-owner/legal decision. | Master plan §11.1; PR-001 implementation and DR-0009 |

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
| DR-0009 | External source distribution or production release | Product source licence, distribution rights and third-party notice policy |
