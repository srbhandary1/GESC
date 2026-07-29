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
| D-0015 | Accepted | The product owner's 29 July 2026 instruction to continue after the PR-001 limitations were presented accepts those recorded limitations and authorizes PR-002; it does not represent the unavailable checks as having passed. | Checkpoint workflow; product-owner instruction |
| D-0016 | Accepted | `docs/api/openapi.yaml` is the OpenAPI 3.1.1 contract source. Redocly 2.41.2 validates it and Hey API 0.99.0 deterministically generates the committed TypeScript client, types and JSON schemas; exact pins and the `js-yaml` 4.3.0 security override remain until reviewed during an upgrade. | Master plan §§11, 25.2, 26 and 32; PR-002 implementation |
| D-0017 | Accepted | Versioned API failures use a stable RFC 9457-style `application/problem+json` shape with generic safe details, localized `message_key`, instance and correlation identifiers; framework exception messages, input values and raw validation text are not public contract fields. | Master plan §§18.1, 18.2 and 26; PR-002 implementation |
| D-0018 | Accepted | A syntactically valid caller UUID may continue as the request correlation ID; invalid values are replaced with a generated UUID. Structured request logs contain a fixed event plus an allowlist of method, named route, status and duration, with the correlation ID supplied by Laravel context. | Master plan §§18.2, 20.3 and 26; PR-002 implementation |
| D-0019 | Accepted | Liveness is process-only. PR-002 readiness requires PostgreSQL and Redis, returns no dependency names or diagnostics publicly, and does not gate on object storage, mail/OTP or external providers until an implemented serving path depends on them. | Master plan §§11, 20.3 and 26; PR-002 implementation |
| D-0020 | Accepted | The web application uses a generic accessible React error boundary outside `StrictMode`; root and boundary reporting expose only allowlisted failure categories, and the typed health wrapper replaces server problem details with a generic client error. | Master plan §§18.1, 19 and 26; PR-002 implementation |

## Decision requests before affected checkpoints

These items were first recorded in PR-000. They must be resolved before the
indicated implementation becomes authoritative.

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
