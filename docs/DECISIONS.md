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
| D-0021 | Accepted | The Accountable Product Owner, Technical Decision Owner and applicable specialist-review roles approve governed artifacts; `docs/APPROVALS.md` records durable evidence, Codex is never an approver, and independent security, privacy and tenancy review remains mandatory before production. | [ADR-0001](adr/0001-approval-authority-and-evidence.md); APP-0001 |
| D-0022 | Accepted | Web sessions, mobile OAuth token families, OTPs, installation binding and future recovery behavior use the exact lifetimes, rotation, revocation and privacy rules in ADR-0002. Recovery, phone-change and reassignment workflows remain future gated work rather than PR-003 implementation. | [ADR-0002](adr/0002-authentication-session-token-and-recovery-policy.md); APP-0001 |
| D-0023 | Accepted | Users, authentication records, organizations and permission keys are platform-global; memberships, roles, invites, cohorts and enrollments are organization-owned. Context is membership-derived and fail-closed, and Global English Direct is an ordinary tenant rather than a bypass scope. | [ADR-0003](adr/0003-resource-ownership-and-organization-context.md); APP-0001 |
| D-0024 | Accepted | PR-003 pins Sanctum 4.3.3 and Passport 13.7.5. Web uses same-origin cookie sessions; Android uses a system-browser S256 PKCE adapter, in-memory access tokens and a reviewed-native-secure-store interface for installation and refresh credentials. | Master plan §§11.1, 11.2 and 26; ADR-0002; PR-003 implementation |
| D-0025 | Accepted | Phone-only OTP transactionally assigns an eligible identity to Global English Direct. Invite-plus-OTP transactionally provisions or reuses only the verified identity and accepts the institution membership, so a new institutional invitee does not receive an unintended Direct membership. An existing Direct learner may deliberately accept an institution invite and retain both independently scoped memberships. HTTP invite acceptance binds a keyed request fingerprint and caller idempotency key to the organization-owned invite aggregate; raw phone numbers, codes, OTPs and tokens are not authoritative stored values. | Master plan §§7.3 and 26; ADR-0002; ADR-0003; PR-003 implementation |
| D-0026 | Accepted | Foundation-schema, tenant-isolation and authentication-authority tests run against PostgreSQL in `make verify` and CI. Database connections force UTC for authoritative timestamps; organization learning-day calculations still use the organization timezone. | Master plan §§11, 15 and 26; PR-003 implementation |
| D-0027 | Accepted | Identity lookup digests use a dedicated, access-controlled `AUTH_IDENTITY_DIGEST_KEY` rather than Laravel's encryption `APP_KEY`. Production fails closed for a missing, malformed or documented local-example key. The identity key is backed up and cannot be rotated in place; rotation requires a reviewed, versioned dual-read migration. | ADR-0002; PR-003 implementation and verification scope |
| D-0028 | Accepted | Versioned domain API failures use RFC 9457-style `application/problem+json`. Standards-based root Passport `/oauth/*` failures retain OAuth `application/json` bodies; repository policy validates both contracts explicitly instead of weakening either response shape. | Master plan §15; ADR-0002; D-0017; PR-003 implementation |
| D-0029 | Accepted | The product owner's 30 July 2026 contextual instruction `Continue` accepts PR-003 at revision `457bd0b5e9486cb207882af38a5a65479534d55e`, including its recorded limitations, and authorizes only PR-004. It does not represent unavailable checks as passed or authorize production or later checkpoints. | [ADR-0001](adr/0001-approval-authority-and-evidence.md); [APP-0002](APPROVALS.md#app-0002) |
| D-0030 | Accepted | UI localization is a framework-independent package separate from versioned curriculum content. `kn-IN` is requested by default, `en-IN` is the deterministic fallback, ordinary production builds use `approved-only`, and development/test or an explicit review build may use `development-preview` only with a visible notice. Fallbacks are visible and emit structured events without message text or parameters. | Master plan §§5.4, 19 and 26; PR-004 implementation |
| D-0031 | Accepted | UI catalogue releases require exact key parity, non-blank NFC messages, source-linked SHA-256 revisions and honest review metadata. The `en-IN` fallback retains source status; Kannada cannot become approved without distinct translator and reviewer evidence. Noto Sans Kannada Variable 5.3.0 is self-hosted through the design-system package, with system fallbacks. | Master plan §§5.4, 19 and 26; PR-004 implementation |
| D-0032 | Accepted | The accessible shell uses semantic landmarks, explicit mixed-language metadata, route-preserving skip focus, visible focus, reduced motion and 44 CSS-pixel targets. Automated coverage spans 320/360/412/768/1280 CSS pixels, axe in both locales and 200%/400% enlarged-root-text stress proxies; actual browser zoom, screen-reader and Android TalkBack remain human gates. | Master plan §19; [PR-004 verification scope](PR-004_ACCESSIBILITY_VERIFICATION_SCOPE.md) |
| D-0033 | Accepted | PR-004 audio and recording controls are project-owned inert presentation contracts only. They do not access media devices, play or capture audio, call networks/providers or persist learner data; real media behavior remains gated by later checkpoints and DR-0005. | Master plan §§13.3, 19 and 26; PR-004 implementation |
| D-0034 | Accepted | The product owner's 30 July 2026 contextual instruction `Continue` accepts PR-004 at revision `f9ffbe9bc3069d74ff53ddcb51d8c6972c55e216`, including its recorded limitations, and authorizes only PR-005 decision-resolution work. It does not resolve DR-0003, accept proposed ADR-0004, authorize schema/product implementation or authorize production. | [ADR-0001](adr/0001-approval-authority-and-evidence.md); [APP-0003](APPROVALS.md#app-0003) |
| D-0035 | Accepted | Curriculum and content are non-null organization-owned; cross-organization use requires an explicit grant to an immutable publication. Publication uses derived closed manifests, appointed and separated academic/language/rights authority, immutable revisions and rollback history, deterministic media-rights gates, explicit sharing/withdrawal/suspension/cloning semantics, and atomic immutable enrollment pinning. `S R Bhandary` accepted the policy and all consolidated development roles as non-independent review; independent specialist review remains a pre-production gate. | [ADR-0004](adr/0004-content-ownership-publication-and-curriculum-sharing.md); [APP-0004](APPROVALS.md#app-0004) |

## Resolved decision requests

| ID | Resolution |
|---|---|
| DR-0001 | Resolved by [ADR-0002](adr/0002-authentication-session-token-and-recovery-policy.md) under APP-0001. |
| DR-0002 | Resolved by [ADR-0003](adr/0003-resource-ownership-and-organization-context.md) under APP-0001. |
| DR-0003 | Resolved by [ADR-0004](adr/0004-content-ownership-publication-and-curriculum-sharing.md) under APP-0004. |
| DR-0008 | Resolved by [ADR-0001](adr/0001-approval-authority-and-evidence.md) under APP-0001. |
| DR-0010 | Resolved by D-0025. The canonical onboarding modes and ADR-0003's multi-organization identity model require institution-only membership for a new invite-first learner while preserving independently accepted memberships for an existing user. |

## Open decision requests before affected checkpoints

These items were first recorded in PR-000. They must be resolved before the
indicated implementation becomes authoritative.

| ID | Needed by | Decision required |
|---|---|---|
| DR-0004 | PR-007 | Offline local-data retention, shared-device isolation, logout purge and cross-device conflict policy |
| DR-0005 | PR-009 | Recording retention, consent states, provider processing boundaries and deletion workflow |
| DR-0006 | Validation Beta | Exact beta scope, sample sizes, subgroup definitions and measurable acceptance thresholds |
| DR-0007 | Commercial authorization | Responsibility and total budget when specialist content production is commissioned |
| DR-0009 | External source distribution or production release | Product source licence, distribution rights and third-party notice policy |
