# Quality review

## PR-000 — Governance baseline

### Scope reviewed

- canonical-plan placement and authority;
- agent operating instructions;
- repository onboarding documentation;
- ADR, issue and pull-request governance;
- checkpoint status and decision traceability;
- formatting and repository hygiene conventions.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Canonical plan is at repository root | Pass | `ENTERPRISE_PRODUCT_MASTER_PLAN.md` |
| Agent instructions are concise and consistent with the plan | Pass | `AGENTS.md` |
| Source-of-truth hierarchy is explicit | Pass | `README.md` and `AGENTS.md` |
| Current checkpoint is recorded | Pass | `docs/STATUS.md` |
| Material decisions have a durable ledger | Pass | `docs/DECISIONS.md` |
| ADR structure covers required impacts | Pass | `docs/adr/0000-template.md` |
| Issue and pull-request templates enforce bounded scope | Pass | `.github/ISSUE_TEMPLATE/` and `.github/PULL_REQUEST_TEMPLATE.md` |
| Repository-local Markdown links and anchors resolve | Pass | Local link and anchor validation |
| Markdown headings and YAML front matter are structurally valid | Pass | Local structure and YAML parsing |
| No product code was introduced | Pass | Repository file inspection |
| Root verification commands are available | Not applicable | Scheduled for PR-001 |

### Security, privacy and tenancy review

- No secrets, real learner data or licensed media were added.
- Governance templates require explicit security, privacy, tenancy and
  authoritative-record impact analysis.
- Deferred decisions are recorded for authentication, resource ownership,
  offline data and recording retention.

### Known limitations

- The repository is initialized on `pr-000-governance-baseline`, but it has no
  commit, remote configuration or CI execution yet.
- Markdown was validated locally for expected structure and repository-local
  file targets; external references were not live-verified.
- Application verification commands do not exist before PR-001.

### Exit decision

PR-000 was accepted on 29 July 2026 through the product-owner instruction to
proceed with project implementation. PR-001 is authorized to begin.

---

## PR-001 — Monorepo and local platform bootstrap

### Scope reviewed

- Laravel 13 and React 19.2 framework shells;
- runtime and dependency reproducibility;
- local database, cache, object-storage, mail and OTP services;
- stable root commands and CI parity;
- backend, frontend and browser smoke tests;
- checkpoint boundaries, secret hygiene and local-network exposure.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Laravel API shell exists without business or identity models | Pass | `apps/api/`, route inspection and framework health test |
| React TypeScript/Vite shell exists without learner features | Pass | `apps/web/` and render tests |
| Runtime versions and dependencies are reproducibly declared | Pass | version files, manifests and lockfiles |
| Local service images are immutable and host ports are loopback-only | Pass, static | `compose.yaml` inspection |
| Mail and OTP development paths are non-production fakes | Pass, static | Mailpit and WireMock configuration |
| Root bootstrap, verification and build commands are executable | Pass | local command execution |
| Production dependencies have no reported security advisories | Pass | Composer and pnpm audits |
| Locked application dependency licences were inventoried | Pass with release follow-up | Composer and pnpm licence reports; final notices tracked by DR-0009 |
| PHP formatting and level-8 static analysis pass | Pass | Pint and Larastan/PHPStan |
| Backend and frontend deterministic tests pass | Pass | PHPUnit and Vitest, one test each |
| React shell browser smoke test passes | Pass | Playwright Chromium, one test |
| Production API optimization and web bundling pass | Pass | `make build` |
| CI uses the declared toolchain and root commands | Pass, static | `.github/workflows/ci.yml` |
| Compose services start and become healthy | Not run | Docker/Compose unavailable on the host |
| No secrets, generated environment files or build output are tracked | Pass | ignore and Git hygiene checks |
| No later-checkpoint feature was introduced | Pass | repository inspection |

### Commands and results

| Command | Result |
|---|---|
| `make bootstrap` | Pass using Node.js 24.18.1, pnpm 11.18.0, Composer 2.10.2 and local PHP 8.4.17; emitted the expected PHP 8.4.23 patch warning |
| `make verify` | Pass: Composer validation/audit, Pint, PHPStan, PHPUnit, frozen pnpm install, pnpm production audit, ESLint, TypeScript and Vitest |
| `make build` | Pass: Laravel production optimization and Vite production build |
| `make e2e` | Pass outside the restricted macOS sandbox: one Chromium test |
| Local Markdown validator | Pass: 12 files, balanced fences, heading progression, final newlines and all repository-local links/anchors |
| YAML/JSON syntax checks | Pass: Compose, issue-template configuration/front matter and WireMock mapping |
| Compose static policy check | Pass: expected service set, digest-pinned images and loopback host bindings |
| `git diff --check` and ignore checks | Pass: no whitespace errors; generated environments, dependencies, test/build output and SQLite state are ignored |
| Composer/pnpm licence inventory | Pass: locked application dependencies reported licences; final distribution and notice policy remains DR-0009 |

### Security, privacy and tenancy review

- Compose host ports bind to `127.0.0.1`, and containers use
  `no-new-privileges`.
- Root and API `.env.example` values are explicitly local-development
  credentials; generated `.env` files and application keys remain ignored.
- Mailpit and WireMock prevent local mail/OTP work from reaching real
  recipients.
- No learner data, licensed media, authentication credential, provider secret
  or production configuration was added.
- No tenant-owned resource exists in PR-001. Organization scoping,
  authorization and cross-tenant tests remain mandatory from PR-003.

### Known limitations

- Docker and Docker Compose are unavailable on the current host. Compose YAML
  can be checked statically, but service startup, health and persistence have
  not been demonstrated.
- The local PHP executable is 8.4.17, while repository and CI declarations pin
  8.4.23. The PHP checks passed on the compatible 8.4 series, but an exact-patch
  clean-environment run remains outstanding.
- CI configuration was inspected locally but has not executed on a GitHub
  runner.
- Final source-distribution terms and third-party notice production are deferred
  to DR-0009.
- The first sandboxed Playwright attempt was blocked by macOS Mach-port
  permissions. The same test passed outside that sandbox.
- PR-002 must add dependency-aware readiness; PR-001 provides only Laravel's
  framework liveness shell.

### Exit decision

PR-001 implementation is complete and ready for review. Acceptance remains
pending the unresolved environment checks above or an explicit reviewer
decision accepting them. PR-002 is not yet authorized.

PR-001 was subsequently accepted on 29 July 2026 when the product owner
instructed Codex to continue after these limitations were presented. The
instruction authorized PR-002 without representing the unavailable checks as
having passed.

---

## PR-002 — Contracts, health and observability

### Scope reviewed

- OpenAPI 3.1.1 source and generated TypeScript contract package;
- contract linting, policy enforcement and deterministic drift detection;
- correlation IDs, problem details and privacy-safe structured logging;
- process liveness and PostgreSQL/Redis dependency readiness;
- API exception and React render error boundaries;
- root-command and CI integration;
- checkpoint boundaries, secret hygiene and future tenancy constraints.

### Review result

| Check | Result | Evidence |
|---|---|---|
| OpenAPI 3.1.1 contract is structurally valid | Pass | Redocly strict lint of `docs/api/openapi.yaml` |
| Client, types and OpenAPI 3.1 JSON schemas are generated and exported | Pass | `packages/contracts/`, contract typecheck and web consumer test |
| Generated output is deterministic and drift is rejected | Pass | Temporary generated-file sentinel caused the expected non-zero drift result; restored output passed |
| The health-only missing-4xx exception cannot silently apply to feature operations | Pass | `scripts/validate-openapi-policy.mjs` in `make openapi` |
| Liveness does not resolve dependency probes | Pass | Backend feature test |
| Readiness checks all configured required dependencies | Pass | PostgreSQL and Redis probe wiring |
| An unavailable required dependency returns a safe `503` | Pass | Injected throwing probe test; diagnostic secret absent from body and logs |
| API errors use the stable problem-details contract in debug and non-JSON requests | Pass | 404, 405, 422 and 500 feature tests |
| Correlation UUIDs propagate from validated input or are generated | Pass | Response-header/body and sequential-request tests |
| Request logging uses fixed events and an allowlist | Pass | Log-spy tests and a live liveness request emitted one JSON event with correlation context |
| Operational responses include a no-store directive | Pass | Contract plus liveness, readiness and problem tests |
| React has an accessible generic render error boundary | Pass | Focus, alert, reload, root-callback, non-disclosure and normal-render tests |
| Web uses the generated contract package | Pass | Typed liveness wrapper and mocked-fetch tests |
| CI executes contract validation and drift checks | Pass, static | `make verify` integration and CI workflow |
| No PR-003 or later feature was introduced | Pass | Route, model, migration and repository inspection |

### Commands and results

| Command | Result |
|---|---|
| `make openapi` | Pass: strict OpenAPI lint, health-only 4xx policy and generated-client reproducibility |
| Deliberate generated-client drift run | Expected failure: the temporary sentinel was detected; removal restored a passing check |
| `make verify` with isolated Composer 2.10.2 | Pass on local PHP 8.4.17 with the expected 8.4.23 warning: Composer validation/audit, Pint, PHPStan, 12 backend tests/88 assertions, frozen pnpm install, full pnpm audit, OpenAPI checks, ESLint, contracts/web TypeScript and 8 frontend tests |
| `make build` | Pass: Laravel configuration/events/routes/views cached and fully cleared; Vite built 20 modules |
| `make e2e` | Pass after installing the pinned Playwright Chromium: one Chromium shell/error-boundary smoke test |
| Focused unavailable-dependency backend test | Pass: required-probe exception produced a generic `503` without the injected secret |
| Live liveness request on the Laravel development server | Pass: response/header correlation matched and the JSON event omitted the secret query value |
| Live readiness request with PostgreSQL forced to an unavailable loopback port | Pass: generic `503`, `Retry-After`, no-store and matching correlation; structured warnings omitted connection diagnostics |
| Full pnpm audit at high severity | Pass: no known vulnerabilities after the `js-yaml` 4.3.0 override |
| Local Markdown, YAML/JSON and repository hygiene validation | Pass; repository-local links, contract YAML, package JSON, whitespace and generated-artifact ignore rules checked |

The first repository-wide verification attempt stopped before code checks
because the host default was Composer 2.9.3. The exact Composer 2.10.2 PHAR was
downloaded from the official distribution, its published SHA-256 checksum
passed, and the same verification command then completed. The first final
Playwright attempt similarly stopped because the pinned browser was absent; the
pinned Chromium build was installed and the rerun passed.

### Security, privacy and tenancy review

- Incoming correlation values are accepted only when they are UUIDs; otherwise
  the server creates a UUID. Correlation state is placed in Laravel context for
  structured logs.
- API response and log tests use secret-like learner/transcript and database
  diagnostic fixtures and prove those values are not returned or logged.
- Public readiness responses do not name failed dependencies. Internal logs
  contain only the stable dependency label, failure type and exception class.
- Request events omit raw paths, query values, bodies, headers, IP addresses and
  user-agent strings.
- React root and boundary handlers report only allowlisted failure categories
  and render no raw error text or stack.
- No learner data, credentials, provider secret, authentication flow,
  tenant-owned resource, migration or cross-tenant behavior was added.

### Configuration and dependency review

- API logging defaults to JSON on stderr with stack traces disabled.
- Redis readiness uses a dedicated connection with 0.5-second connect/read
  limits and no retries.
- Production-build verification clears configuration first, then clears all
  optimization artefacts with the non-network `array` cache store.
- Redocly 2.41.2 and Hey API 0.99.0 are exact development pins. Their selected
  plugins generate a self-contained client, TypeScript types and JSON schemas.
- `js-yaml` is pinned and overridden to 4.3.0 to avoid the high-severity
  quadratic-processing advisory in the generator's transitive parser.
- The existing API Pint configuration is now invoked from `apps/api`; applying
  its existing rule mechanically added `declare(strict_types=1)` to the
  remaining pre-existing API PHP files.
- No migration was added.

### Known limitations

- Docker/Compose is unavailable, so healthy PostgreSQL/Redis readiness and
  container failure latency were not measured. A forced loopback connection
  refusal and injectable failures prove `503` response semantics; PostgreSQL
  still relies on the selected driver/platform connection timeout.
- The local PHP executable is 8.4.17, not the declared 8.4.23 patch. CI declares
  8.4.23, but the remote workflow has not run.
- Object storage, mail/OTP and external providers are not readiness dependencies
  before serving paths rely on them.
- The React error boundary cannot catch event-handler, arbitrary asynchronous,
  boundary-self or pre-mount static-import failures.
- Metrics, distributed tracing and an external log backend remain deployment
  choices for later operational checkpoints.
- Final distribution terms and third-party notices remain DR-0009.

### Exit decision

PR-002 was accepted on 30 July 2026 under
[APP-0001](APPROVALS.md#app-0001). The same bounded evidence accepted
ADR-0001, ADR-0002 and ADR-0003 and authorized PR-003 without representing the
recorded unavailable environment checks as passed.

---

## PR-003 — Tenancy and identity foundation

### Scope reviewed

- platform-global identity and organization records;
- organization-owned memberships, roles, invitations, cohorts and
  enrollments;
- Direct and institution invite-plus-OTP onboarding;
- organization-context derivation, tenant scopes, policies and database
  constraints;
- same-origin web sessions, CSRF, OTP policy and session revocation;
- Android public-client PKCE, installation-bound refresh rotation and family
  revocation;
- platform-operation authorization, audit/outbox evidence and idempotency;
- OpenAPI/generated clients, frontend adapters and checkpoint boundaries.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Accepted PR-003 decisions and authorization have durable human evidence | Pass | APP-0001 and ADR-0001 through ADR-0003 |
| Direct phone-only onboarding assigns the ordinary Direct tenant transactionally | Pass | Direct provisioning PostgreSQL tests |
| Invite-plus-OTP creates institution membership without unintended Direct membership | Pass | Web and mobile PostgreSQL onboarding journeys plus rollback/non-enumeration tests |
| Every introduced organization-owned model fails closed without context | Pass | Organization isolation matrix and web/mobile request-context middleware tests |
| Cross-tenant read, list, create, update, delete, scoped binding, attachment and mass assignment are denied | Pass | Policy/model matrix and PostgreSQL composite constraints |
| Non-applicable route-handler, job and object-storage categories are explicit | Pass | `docs/PR-003_TENANCY_VERIFICATION_SCOPE.md` |
| Platform operations require signed capability, safe reason, target and append-only audit evidence | Pass | Platform-operation PostgreSQL tests |
| Web sessions enforce CSRF, inactivity, absolute lifetime, logout and arbitrary/all-session revocation | Pass | Session feature/PostgreSQL tests and live same-origin login/logout smoke |
| OTPs are hashed, short-lived, single-use, superseding, attempt-limited, abuse-controlled and safely logged | Pass | OTP policy and integration tests |
| Android OAuth uses system-browser S256 PKCE without a public-client secret | Pass | Passport and Android-adapter tests |
| Refresh is single-use, installation-bound and concurrently safe with family revocation on reuse | Pass | Real-PostgreSQL forked race plus mismatch and expiry-boundary tests |
| No browser or ordinary-preferences credential persistence exists | Pass | Adapter boundary and storage-spy tests |
| Public APIs and generated clients match | Pass | Strict OpenAPI lint, explicit RFC 9457/OAuth media policy and generated-client drift check |
| No later-checkpoint learner, content, AI, speech, offline or commercial feature was introduced | Pass | Route, schema and repository inspection |

### Commands and results

| Command | Result |
|---|---|
| `make verify` with the isolated Composer 2.10.2 and pinned Node/pnpm toolchain | Pass on local PHP 8.4.17 with the expected 8.4.23 patch warning: strict Composer validation with the approved exact-pin advisory excluded, Composer/pnpm audits, Pint, PHPStan level 8, 36 SQLite tests/254 assertions, 93 PostgreSQL tests/1,217 assertions, frozen pnpm install, OpenAPI drift/policy checks, ESLint, TypeScript and 16 frontend tests |
| `make openapi` | Pass: strict Redocly validation, explicit RFC 9457 domain/OAuth JSON policy and reproducible generated TypeScript contracts |
| `make e2e` | Pass: one Chromium secure identity-shell journey |
| `make build` | Pass: Laravel production optimization/cache cleanup and Vite production build of 38 modules |
| Targeted PostgreSQL acceptance audit | Pass: 25 invite/session/Passport/concurrent/token-family tests with 761 assertions plus 3 revocation-failure tests with 15 assertions |
| Forked concurrent refresh test | Pass: one simultaneous request returned `200`, one returned OAuth `invalid_grant`, the token family was revoked and one reuse audit was appended |
| Mobile binding and boundary tests | Pass: wrong installation and exact 30-day inactivity, 90-day absolute and refresh-record expiry boundaries revoke the family |
| Live same-origin Vite/API/PostgreSQL smoke | Pass: CSRF `204`, OTP request `202`, verification `200`, authenticated `/me` `200`, logout `204`, revoked `/me` `401` with problem details |
| `php artisan route:list` for Sanctum, OAuth and `/api/v1` | Pass: cookie-CSRF, PKCE/token and first-party authentication middleware are attached to the expected routes |
| `php artisan migrate:fresh --seed --force` against the isolated PostgreSQL test database | Pass: six migrations and the idempotent Direct foundation seeder completed |
| `make check-local-auth-env` | Pass: local environment uses encrypted sessions and a dedicated identity digest key |
| PostgreSQL initializer idempotency check | Pass: `001-create-test-database.sql` completed twice with `ON_ERROR_STOP` |
| Compose/CI static policy check | Pass: five services are digest-pinned and loopback-only; PostgreSQL initializer mount and exact CI runtime/extensions are present |
| Local Markdown, YAML, link, anchor and Git hygiene checks | Pass: balanced structure, valid local targets, canonical inputs unchanged and no whitespace errors |

### Security, privacy and tenancy review

- Global English Direct is enforced as an ordinary organization tenant.
- Membership-derived context is fail-closed. Caller fields and arbitrary
  organization headers cannot establish or replace authority.
- The `organization.context` middleware resolves an active selected membership
  or exactly one active membership for both web and mobile principals, scopes
  downstream tenant models and clears context after every response.
- Composite PostgreSQL foreign keys bind tenant relationships, invite
  acceptance, audit actors and selected mobile memberships to the same
  organization and user.
- Platform-global operations require a project-issued short-lived capability,
  an allowlisted non-sensitive reason and append-only audit evidence.
- Web uses encrypted Secure, HttpOnly, SameSite cookies and CSRF protection.
  Android access tokens stay in memory; persistent credential operations exist
  only on the reviewed-native-secure-store interface.
- Identity, OTP, session, installation and refresh values are purpose-digested
  or encrypted. Raw phone numbers, codes, tokens and provider payloads are not
  stored in audit/outbox records or application logs.
- The local fixed OTP is available only in `APP_ENV=local`; production uses no
  fixed code and the fake delivery adapter fails closed outside local/testing.
- Both OAuth endpoints accept only active first-party public mobile clients
  with the expected provider and grants; HTTP Basic, PHP-auth and body client
  secrets are rejected before Passport.
- Requests without a web session resolve the Passport guard only when a bearer
  token is present, so a local install without OAuth keys fails closed with
  `401` rather than exposing a server error.
- Independent security, privacy and tenancy specialist review remains required
  before production.

### Migrations and configuration reviewed

- Six ordered migrations create identity/tenancy, tenant relationships,
  audit/outbox, web authentication, Passport and mobile token-family records.
- PostgreSQL check, uniqueness, composite foreign-key and append-only audit
  constraints supplement application scopes and policies.
- `AUTH_IDENTITY_DIGEST_KEY` is independent from `APP_KEY`, validated
  fail-closed and documented as requiring a versioned migration for rotation.
- Session/token/OTP lifetimes match ADR-0002 and remain centralized
  configuration.
- Compose initializes the isolated PostgreSQL integration-test role/database
  on a fresh volume; CI runs the PostgreSQL suite.

### Known limitations

- Docker/Compose and a fresh PostgreSQL 18.4 container are unavailable on this
  host; the local PostgreSQL verification service is version 14.
- Local PHP is 8.4.17 rather than the declared 8.4.23 patch, and the GitHub
  Actions workflow has not run remotely.
- Production OTP delivery, privileged-MFA verification, Android native secure
  storage and operational alert integrations require later reviewed
  implementations.
- Physical session-store cleanup is best effort after authoritative database
  revocation commits. Middleware remains fail-closed, but production alerting
  for cleanup failures is not integrated in PR-003.
- Recovery, phone change/reassignment, admission-ID setup and account closure
  remain gated future workflows under ADR-0002.
- No tenant-owned queued handler or object-storage record exists yet, so those
  isolation categories become executable at the checkpoint that introduces
  them.
- Independent specialist review remains outstanding.

### Exit decision

PR-003 implementation and its local verification gate are complete and ready
for Accountable Product Owner review. PR-003 is not yet accepted. PR-004 must
not begin until explicit checkpoint acceptance is recorded in
`docs/APPROVALS.md`.
