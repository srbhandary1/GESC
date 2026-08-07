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

PR-003 implementation and its local verification gate were complete and ready
for Accountable Product Owner review. It was subsequently accepted on 30 July
2026 under [APP-0002](APPROVALS.md#app-0002). The accepted limitations remain
limitations rather than passing results. APP-0002 authorizes only PR-004.

---

## PR-004 — Localization and accessible design system

### Scope reviewed

- governed `kn-IN` and `en-IN` UI catalogues and deterministic fallback;
- Unicode normalization, source linkage and translation-review evidence;
- mixed-language semantics, Kannada font delivery and accessible tokens;
- responsive Home/Learn/Practice/Progress shell and focus behavior;
- inert audio/recording presentation contracts;
- production-versus-preview language policy and safe fallback observability;
- unit, locale, keyboard, axe, reflow, target-size and font-resource tests;
- preservation of PR-003 API, authentication and tenancy boundaries.

### Review result

| Check | Result | Evidence |
|---|---|---|
| PR-003 acceptance and PR-004 authorization are durable | Pass | APP-0002 and D-0029 |
| UI catalogues have exact locale/key parity, deterministic hashes and current source linkage | Pass | Locale validator and governance revisions `ui-en-IN-2026-07-30.2` / `ui-kn-IN-2026-07-30.2` |
| Pending Kannada is not represented as approved | Pass | `pending-independent-review`, attributed draft, null reviewer/approval and `independentReview=false` |
| Ordinary production builds reject pending Kannada and visibly fall back to English | Pass | Policy unit tests and local strict-build browser inspection |
| Development/review preview is explicit and visibly labelled | Pass | `VITE_UI_TRANSLATION_PREVIEW` policy tests and shell preview notice |
| Fallback observability excludes text, parameters and learner data | Pass | Resolver and web reporting tests |
| Blank messages, malformed placeholders, stale hashes and dishonest review claims fail validation | Pass | Localization validator tests |
| Kannada text uses NFC without removing ZWJ/ZWNJ | Pass | Normalization tests |
| Mixed-language and brand text expose explicit language metadata | Pass | Design-system and shell tests |
| Shell landmarks, navigation, skip focus and route-title focus are keyboard operable | Pass | Component tests and Chromium journey |
| Skip focus does not destroy the active route fragment | Pass | Component and Chromium reload tests from `#learn` |
| Both locale variants have no automated axe violations | Pass | Chromium axe scans for Kannada preview and English |
| Shell reflows at 320/360/412/768/1280 CSS pixels | Pass | Chromium no-horizontal-overflow matrix |
| Enlarged text reflows at 200% and 400% stress proxies | Pass | Chromium root-text-size matrix; not claimed as actual browser zoom |
| Primary controls meet the project 44 CSS-pixel target | Pass | Chromium bounding-box assertions |
| Kannada-specific bundled WOFF2 face loads for Kannada sample text | Pass | FontFaceSet and resource-entry assertions |
| Reduced-motion behavior is honored | Pass | Chromium media-emulation assertion |
| Locale selection is ephemeral and no browser credential store is used | Pass | unit storage spies and Chromium localStorage/IndexedDB assertions |
| Audio/recording contracts perform no media, provider, network or persistence work | Pass | component tests and forbidden-API inspection |
| OpenAPI and generated clients remain unchanged | Pass | `make openapi` |
| No PR-005 or later product behavior was introduced | Pass | route, schema and repository inspection |

The first enlarged-text browser pass exposed horizontal overflow at the 400%
stress proxy. Shrinkable grid/flex tracks and explicit wrapping corrected the
defect; the complete matrix passed after the fix. An adversarial integration
review then identified and closed strict-production preview, fallback
visibility, static metadata, skip-route, validator, font-test, lint-coverage
and document-language gaps before the final gate.

### Commands and results

| Command | Result |
|---|---|
| `make verify` with pinned Node.js 24.18.1, pnpm 11.18.0 and Composer 2.10.2 | Pass on local PHP 8.4.17 with the expected 8.4.23 patch warning: Composer validation/audit, pnpm frozen install/audit, Pint, PHPStan, 36 SQLite tests/254 assertions, 93 PostgreSQL tests/1,217 assertions, locale validation, OpenAPI drift/policy, centralized ESLint, recursive TypeScript and 56 frontend/package unit tests |
| `pnpm run locale:check` | Pass for two catalogues with one expected `pending-independent-review` governance warning |
| `make openapi` | Pass: strict Redocly validation, repository API policy and reproducible generated-client drift check |
| `make e2e` | Pass: five Chromium scenarios covering both-language axe scans, keyboard/focus/route behavior, ephemeral locale and storage boundaries, width/reflow/targets, Kannada font loading and reduced motion |
| `make build` | Pass: Laravel production optimization/cache cleanup and strict Vite production build of 54 modules |
| Local strict-build browser inspection | Pass at 320 CSS pixels: visible English fallback, `lang="en-IN"`, `data-requested-locale="kn-IN"` and `scrollWidth === clientWidth` |
| Local Kannada-preview browser inspection | Pass at 1280 and 320 CSS pixels: Kannada glyphs rendered through the bundled font stack with no 320-pixel horizontal overflow |
| Local Markdown/YAML/link and Git hygiene validation | Pass: 19 in-scope Markdown files, two YAML files, local targets/anchors, canonical-input preservation and diff whitespace |

### Security, privacy and tenancy review

- Authentication, membership-derived organization context, cross-tenant
  policies and backend schema are unchanged from accepted PR-003.
- The locale selector is ephemeral and writes no browser persistence.
- Structured fallback events contain allowlisted catalogue context only; raw
  UI text, interpolation values, learner data and provider details are absent.
- Tenant-provided organization names are isolated with `<bdi lang="">` rather
  than assigned an inferred language.
- Pending or stale Kannada cannot enter an ordinary production build through
  the default resolver policy. The explicit preview build remains visibly
  marked and is not production-authorized.
- Audio/recording components are inert and introduce no microphone permission,
  audio retention, transcript, provider, network or storage path.
- No real learner data, curriculum content, captured audio, secret or
  unlicensed media was added.

### Migrations and configuration reviewed

- No backend migration, route, OpenAPI operation or generated contract changed.
- `@fontsource-variable/noto-sans-kannada` 5.3.0 and
  `@axe-core/playwright` 4.12.1 are exact dependencies in the pnpm lockfile.
- `VITE_UI_TRANSLATION_PREVIEW=true` is an explicit non-production review-build
  switch; ordinary production builds remain strict without it.
- CI installs Chromium, runs `make verify`, executes the accessibility browser
  journey and then builds the strict production bundle.
- Root lint now covers web, design-system and localization sources.

### Known limitations

- Kannada wording remains an implementation draft pending independent,
  accountable human language review.
- Automated 200%/400% root-text enlargement is a reflow stress proxy, not
  actual browser-zoom evidence.
- Manual screen-reader, actual browser-zoom and Android TalkBack review remain
  outstanding; axe alone is not an accessibility claim.
- The accessible media contracts have no real playback/capture behavior to
  evaluate yet.
- Docker/Compose, fresh PostgreSQL 18.4 and remote GitHub Actions execution
  remain unavailable locally. PostgreSQL tests used the local version 14
  service, and local PHP is 8.4.17 rather than the declared 8.4.23 patch.
- Curriculum-content language approval and publication are deliberately
  outside UI-catalogue governance and remain blocked on DR-0003.

### Exit decision

PR-004 implementation and its local verification gate are complete and ready
for Accountable Product Owner review. PR-004 is not accepted by this review.
PR-005 must not begin until explicit checkpoint acceptance is recorded and
DR-0003 is resolved through an accepted ADR with the required human evidence.

PR-004 was subsequently accepted on 30 July 2026 under
[APP-0003](APPROVALS.md#app-0003). The acceptance preserves every recorded
limitation and authorizes only PR-005 decision-resolution work.

---

## PR-005 — Decision phase for versioned content and publication

### Scope reviewed

- contextual PR-004 acceptance and the exact boundary of PR-005 authorization;
- current curriculum, content, publication, sharing and enrollment schema;
- ADR-0003 organization ownership and context enforcement;
- proposed content ownership and cross-organization availability;
- academic, language and publisher authority and separation;
- immutable revisions, publication, rollback and idempotency;
- enrollment-to-curriculum-publication pinning and migration compatibility;
- approval-evidence and checkpoint gates.

### Review result

| Check | Result | Evidence |
|---|---|---|
| PR-004 acceptance is bounded and durable | Pass | APP-0003 and D-0034 |
| APP-0003 is not misrepresented as DR-0003 approval | Pass | APP-0003 conditions and proposed ADR-0004 status |
| Current repository has no curriculum/publication implementation | Pass | route, OpenAPI, model, migration, policy, seeder and test inspection |
| Accepted ownership preserves the ordinary Direct-tenant boundary | Pass | All authored content is organization-owned; cross-organization use requires an explicit immutable-publication grant |
| Academic, translation and publisher authority are distinct | Pass | Permission plus scoped authority appointments, creator/reviewer separation and revocation semantics |
| Publication closure and media-rights gates are deterministic | Pass | Derived closed manifest, visible/hidden authoritative review, explicit rights predicate and successor-publication rule |
| Published content and rollback history are immutable | Pass | Append-only revision, review, publication and activation-event rules |
| Sharing, withdrawal and cloning have explicit semantics | Pass | ADR-0004 owner/recipient grants, continuity, suspension and lineage rules |
| Existing enrollment shape has a safe accepted migration | Pass | `pending_curriculum`, pre-activation withdrawal, all-status preflight and atomic immutable pin/activation |
| UI catalogue review is not reused as curriculum approval | Pass | ADR-0004 localization boundary |
| Adversarial governance review has unresolved high/medium findings | No | Final read-only re-review after ownership, authority, rights, manifest and enrollment corrections |
| Required human decision authority is complete | Pass | APP-0004 accepts ADR-0004 at `d9d7cba4b2ccc0d9f2e8656e5fd19bab9ce9dc99`, records every appointment and reviewer acceptance, and authorizes only PR-005 implementation |
| PR-005 schema or product behavior was introduced | No | Governance and proposed decision only |

### Repository integration findings

- Existing `enrollments` contain membership, optional cohort and status only.
  Institution invite acceptance creates them as `active`, so a direct non-null
  publication foreign key would break existing rows and accepted onboarding.
- The proposed additive path introduces `pending_curriculum`, migrates
  pre-content unpinned active rows, preserves unpinned pre-activation
  withdrawal, fails closed on inconsistent unpinned completion and makes
  curriculum pin plus activation one transaction. Only a learner invite with a
  cohort creates that pending enrollment.
- `BelongsToOrganization` and `OrganizationAuthorization` correctly fail
  closed for another organization's record. Shared-content access therefore
  needs a dedicated, allowlisted grant/access repository; tenant scopes must
  not be disabled in a controller.
- Existing audit and outbox records are suitable foundations. Publication
  needs separate command-idempotency state, aggregate locking and an expanded
  safe-identifier outbox allowlist.
- The permission catalogue currently contains eight keys and has exact-count
  tests. ADR-0004 proposes explicit owner, recipient, rights, suspension and
  pinning permissions that require deliberate seeder and test changes after
  authorization.
- Role permissions alone cannot appoint reviewers or rights confirmers. The
  proposal adds scoped, time-bounded, revocable authority appointments linked
  to durable human evidence and snapshotted by each approval.

### Commands and results

| Command | Result |
|---|---|
| Repository route/schema/model/OpenAPI inspection with `rg` and file reads | Pass: no PR-005 product implementation exists |
| Local Markdown/YAML/link validator | Pass: 23 repository Markdown files; YAML front matter, heading progression, fences, UTF-8, final newlines, local targets and anchors |
| `git diff --check` | Pass |
| `make verify` with Node.js 24.18.1, pnpm 11.18.0 and Composer 2.10.2 | Pass on local PHP 8.4.17 with the expected 8.4.23 warning: dependency validation/audits, Pint, PHPStan, 36 SQLite tests/254 assertions, 93 PostgreSQL tests/1,217 assertions, locale validation, OpenAPI/generated-client drift, ESLint, recursive TypeScript and 56 frontend/package tests |

The first full-gate attempt stopped at the toolchain check because the host
`pnpm` was 11.9.0. The declared Corepack pnpm was then placed first on `PATH`.
The next attempts reached PostgreSQL but found the local service stopped and
then its test role/database absent. PostgreSQL 14 was started temporarily, the
committed idempotent local test initializer was applied twice, and the complete
gate passed. The service was stopped after verification. These environment
failures are not represented as test failures or passing checks.

`make e2e` and `make build` were not rerun because this decision phase changes
no user journey, application code, API contract, packaging or deployment
behavior.

### Security, privacy and tenancy review

- Proposed content stays owned by a non-null organization, including material
  authored for Global English Direct.
- Recipient access requires both an explicit owner grant and
  membership-derived recipient authority. It does not introduce a null tenant,
  public-content bypass or caller-supplied organization context.
- Publisher authority cannot substitute for academic or Kannada approval.
  Codex, seed processes and synthetic test identities are not human reviewers.
- The closed-manifest and rights predicates reject omitted learner-visible
  or hidden pedagogically authoritative revisions, localization
  misclassification and unknown, stale, expired or insufficient media
  evidence. Successor rights evidence requires a new publication identity.
- Audit/outbox rules exclude content bodies, translation text, learner data,
  raw media, credentials and provider payloads.
- No real learner data, content body, media asset, secret, migration or product
  behavior was added in this decision phase.

### Known limitations

- Every ADR-0004 development role is consolidated in `S R Bhandary` and is
  therefore non-independent review.
- `MA` is recorded exactly as the qualification/accountable basis supplied for
  each reviewer role; no specialization is inferred.
- Independent tenancy, English-academic and Kannada-language specialist review
  remains required before production.
- Runtime exercise of the reviewer and rights-confirmer roles still requires
  an active organization membership, matching permission and scoped authority
  appointment.
- No PR-005 implementation or acceptance test has started.
- Existing PR-004 environment, accessibility and Kannada-review limitations
  remain unchanged.

### Exit decision

The PR-005 decision phase is accepted under APP-0004. ADR-0004 is accepted and
DR-0003 is resolved. PR-005 schema, seed, API and product implementation is
authorized within the accepted policy. This decision does not accept PR-005,
authorize PR-006 or waive independent pre-production review.

---

## PR-005 — Versioned content and publication foundation

### Scope reviewed

- organization ownership, exact tenant foreign keys and immutable owner
  identity;
- source, Kannada, graph and media-rights revision lineage;
- appointment authority, role separation and the bounded APP-0004
  consolidated-role exception;
- canonical publication closure, scheduling, activation, retirement, rollback
  and successor rights;
- explicit sharing, withdrawal, recipient isolation and clone lineage;
- enrollment migration, atomic publication pinning and retained-pin access;
- owner and named-platform emergency suspension, remediation and resumption;
- idempotency, locking, audit/outbox evidence and concurrent first-write
  behavior;
- safe synthetic seed integrity, OpenAPI enrollment-state compatibility and
  generated client drift;
- the PR-005 checkpoint boundary and exclusion of later learner, speech, AI,
  offline and Android behavior.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Every curriculum aggregate has a non-null immutable owner organization | Pass | composite tenant foreign keys, owner-change triggers and cross-tenant integration tests |
| Source, Kannada and rights history is append-only with exact lineage | Pass | immediate-parent triggers and direct mutation/nonconsecutive-parent tests |
| Same-hash Kannada evidence cannot be substituted across source identities | Pass | variant, review and publication-component composite-key rejection tests |
| Review and rights evidence uses the exact accepted authority kind/event | Pass | PostgreSQL authority triggers and wrong-kind/nonaccepted-event tests |
| Consolidated APP-0004 appointments remain bounded and non-independent | Pass with production gate | deny-by-default runtime user binding, exact evidence/qualification checks and APP-0004 limitations |
| Publication is a deterministic immutable closed manifest | Pass | manifest/component hash tests and graph/content sealing |
| Immediate and future activation histories are explicit | Pass | atomic scheduled/activated immediate events plus scheduled activation tests |
| Scheduled-only snapshots cannot be rollback targets | Pass | fail-closed rollback test with no command/event/head/audit/outbox residue |
| Rollback uses the stored manifest and exact still-valid pinned evidence | Pass | retirement restoration and later exact-review-withdrawal tests |
| Program-scoped successor rights require a new publication | Pass | old/new component evidence remains distinct and the earlier manifest is unchanged |
| Share use is explicit and recipient-isolated | Pass | preview/enrollment/clone allow/deny, withdrawal and recipient mutation/re-share tests |
| Enrollment activation creates one immutable owned/shared pin | Pass | migration, pinner, direct-update rejection and rollback-retention tests |
| Suspension stops all delivery but permits remediation without implicit resume | Pass | owner/platform, retired-pin and suspended-replacement tests |
| Emergency evidence identifies the exact actor session, remediation and prior platform audit | Pass | authentication-session foreign key, PostgreSQL evidence trigger, forged-session/audit rejection and service/event assertions |
| Signed platform authorization cannot drive a second command or duplicate replay audit | Pass | unique append-only authorization nonce plus same-command replay and cross-command reuse tests |
| Concurrent authority revocation cannot pass an in-transaction permission recheck | Pass | locked authority joins and a second PostgreSQL connection timing out on permission removal |
| Shared and pinned access use one locked authoritative snapshot | Pass | resolver-owned transactions and query assertions for authorization, grant/pin, publication, head, activation and evidence locks |
| Competing first publish/schedule has one PostgreSQL winner | Pass | forked-worker concurrency tests |
| Seed is synthetic, unpublished, atomic and replay-safe | Pass | repeat seed and conflicting/pre-existing state rejection tests |
| Later checkpoint behavior was introduced | No | no learner mission execution, speech/AI provider, offline or Android product implementation |

### Commands and results

| Command | Result |
|---|---|
| Focused PR-005 PostgreSQL suites during remediation | Pass; final serialized authority/access/platform/schema/lifecycle suite ran 44 tests with 286 assertions |
| Full PostgreSQL integration suite | Pass: 154 tests, 1,671 assertions |
| Fresh PostgreSQL migrate, seed, repeated seed | Pass |
| Four-step empty-data rollback, forward migrate and seed | Pass; populated-enrollment rollback denial is separately covered |
| `make verify` with Node.js 24.18.1, pnpm 11.18.0 and Composer 2.10.2 | Pass on local PHP 8.4.17 with the expected 8.4.23 warning: dependency validation/audits, Pint, PHPStan, 36 SQLite tests/254 assertions, 154 PostgreSQL tests/1,671 assertions, locale validation, OpenAPI/generated-client drift, ESLint, recursive TypeScript and 56 frontend/package tests |
| `make e2e` | Pass: five Chromium scenarios after rerunning outside the macOS process sandbox |
| `make build` | Pass: Laravel production optimization/cleanup and strict Vite production build, 54 modules |
| Local Markdown structure/link validator | Pass: 24 in-scope Markdown files; UTF-8, final newlines, balanced fences, heading progression and repository-local links/anchors |
| `git diff --check` and canonical-input diff | Pass; no whitespace errors and no change to `AGENTS.md` or `ENTERPRISE_PRODUCT_MASTER_PLAN.md` |

The first full-gate attempt stopped at the pinned Composer check because the
host Composer was 2.9.3. Composer 2.10.2 was downloaded to an isolated
`/private/tmp` path and the unchanged gate then passed. The first browser run
could not start Chromium because the macOS sandbox denied its Mach rendezvous
port; the approved unsandboxed rerun passed all five scenarios. Neither
environment stop is represented as a product-test failure or a passing
attempt. `make ai-eval` was not run because it intentionally fails before
PR-011 and PR-005 changes no prompt, model, provider or AI orchestration.

### Security, privacy and tenancy review

- Owner-side commands recheck membership-derived permissions inside their
  authoritative transactions and lock the authority rows used by the decision.
  Concurrent suspension or permission removal therefore waits for the
  protected command. Caller-supplied organization IDs never establish context.
- Shared access uses a dedicated recipient-context resolver. Grant use does not
  expose owner drafts, authority records, memberships or unrelated grants, and
  recipients cannot edit, publish or re-share owner records. Shared and pinned
  access decisions own their transactions, capture one trusted instant and
  lock the mutable state they evaluate.
- Exact source identities, revision lineage, appointment kinds/events and
  organization ownership have database enforcement, not service-only checks.
- Raw caller idempotency keys are aggregate-scoped and replay is bound to the
  exact actor and request. Actor mismatch fails closed.
- Existing delivery keeps its immutable publication pin. Rights expiry or
  withdrawal and explicit suspension stop delivery; later review invalidation
  blocks future activation, rollback and resumption without silently rewriting
  a live release.
- Platform emergency authority is disabled by default and is signed, named,
  target-bound, short-lived and fresh-MFA-bound. The resulting event links the
  exact platform audit record created before organization context. A unique
  audit nonce prevents one authorization from driving another command, while
  exact idempotent replay does not manufacture another audit event.
- Audit/outbox payloads contain identifiers and stable keys only. No curriculum
  bodies, Kannada text, media bytes, learner data, credentials, transcripts or
  provider payloads were introduced.
- Seed data is synthetic and unpublished. No real learner data or unlicensed
  media was added.

### Migrations and configuration reviewed

- Migrations `000020`–`000023` add curriculum structure, immutable governance
  evidence, publication/sharing history and enrollment pins.
- `000023` upgrades pre-content unpinned active rows to
  `pending_curriculum`, fails closed for invalid completed rows and refuses
  rollback once any enrollment exists.
- Publication events require exact emergency evidence for suspend/resume rows;
  PostgreSQL binds the session to the accountable actor and platform emergency
  events additionally bind the exact target/capability/action/reason/program/
  correlation audit row.
- Migration `000022` extends append-only platform audit history with a unique
  signed-authorization nonce; pre-existing audit rows remain nullable for
  migration compatibility.
- `CONTENT_GOVERNANCE_APP_0004_ACTOR_USER_ID` binds the accepted human evidence
  to a stable runtime user and is empty/deny-by-default.
- `PLATFORM_CONTENT_EMERGENCY_ACTOR_USER_IDS` is an empty/deny-by-default list
  of stable platform user ULIDs.
- OpenAPI 0.3.0 and generated clients add only the
  `pending_curriculum` enrollment status; there is no PR-005 public
  content/publication endpoint.

### Known limitations

- `S R Bhandary` holds every APP-0004 development role. Those approvals are
  explicitly non-independent; independent tenancy, English-academic and
  Kannada-language review remains mandatory before production.
- `MA` is recorded exactly for the English Academic Reviewer and Kannada
  Reviewer; no specialization is inferred.
- No production curriculum has runtime human review evidence. The synthetic
  seed is deliberately unpublished.
- Media asset import and initial pending-rights creation have no public or
  dedicated domain import command.
- Media-bearing clone requests fail closed until reviewed object-copy,
  retention and derivative-rights behavior exists.
- Scheduled activation workers and schedule cancellation are not implemented;
  the foundation exposes explicit schedule/activate commands.
- Evidence transitions use PostgreSQL table-level share locks and need
  representative production-volume benchmarking.
- Public content/publication HTTP operations remain a later bounded issue.
- Docker and the declared PostgreSQL 18.4 image were unavailable locally;
  database verification used PostgreSQL 14. Local PHP is 8.4.17 rather than
  the declared 8.4.23 patch.
- Remote CI, production runbooks and independent specialist reviews remain
  outstanding.
- No learner mission execution, attempt/mastery record, playable/captured
  audio, offline behavior, AI/provider integration or Android product UI is in
  PR-005.

### Exit decision

PR-005 implementation and local verification are complete within APP-0004 and
are ready for Accountable Product Owner review. This quality review does not
accept PR-005, authorize PR-006 or authorize production. PR-006 remains blocked
until the human explicitly accepts PR-005 at its exact implementation commit
and authorizes only PR-006.

### Subsequent checkpoint acceptance

[APP-0005](APPROVALS.md#app-0005) records `S R Bhandary`'s explicit
Accountable Product Owner acceptance of PR-005 at
`daeee1df7a7e4d31427001aedece76d8121413d6`, with every limitation above, and
authorizes only PR-006 — Deterministic daily-speaking slice.

That approval does not authorize PR-007, AI or speech-provider orchestration,
production deployment or any later checkpoint.

---

## PR-006 — Deterministic daily-speaking slice

### Scope reviewed

- minimal adult learner onboarding and allowlisted goal selection;
- active authenticated learner identity, organization context and exact
  enrollment publication pin;
- one deterministic no-AI mission contract;
- browser model-audio/transcript presentation and volatile local
  recording/playback/retry;
- submitted formative attempts, append-only responses and exact
  first/latest/best evidence;
- deterministic knowledge mastery, spoken-mastery denial and review
  scheduling;
- transaction, idempotency and concurrent-attempt behavior;
- tenant, learner, enrollment, curriculum-owner, publication and mission
  relationship protection;
- OpenAPI/generated-client compatibility and safe problem responses;
- browser contract golden path, accessibility and raw-audio
  storage/network-cleanup assertions;
- honest production-seed and test-fixture boundaries; and
- exclusion of PR-007 offline/outbox, AI/speech-provider and PR-009 recording
  upload/retention behavior.

Detailed rules and the final evidence map are maintained in
[the PR-006 verification scope](PR-006_DETERMINISTIC_DAILY_SPEAKING_VERIFICATION_SCOPE.md).

### Accepted implementation rules

| Rule | Required outcome | Current evidence |
|---|---|---|
| Learner ownership | every learner record is organization-owned and tied to the authenticated learner membership | Pass: PostgreSQL composite relationships, policy checks, same-tenant-other-learner denial and authenticated HTTP boundary |
| Enrollment authority | only an active enrollment's exact immutable publication pin can select a mission; learners cannot self-pin | Pass: pinned-access resolver and pending/completed/withdrawn, poisoned-pin and mission-mismatch tests |
| Evidence scope | summaries cover submitted attempts for the exact learner membership, enrollment, publication and mission | Pass: governed lifecycle and historical replay tests |
| First evidence | lowest server-assigned attempt number | Pass: ordered retry evidence |
| Latest evidence | highest server-assigned attempt number | Pass: ordered retry and score-regression evidence |
| Best evidence | highest deterministic score; earliest attempt number wins a tie | Pass: correct/incorrect retries and equal-score tie evidence |
| Canonical evidence | submitted attempts and responses are append-only; summary pointers remain derived | Pass: PostgreSQL mutation triggers and post-submit response rejection |
| Deterministic authority | caller input cannot set attempt number, score, mastery or review outcome | Pass: strict HTTP requests, versioned policy and direct-database forgery rejection |
| Mastery separation | a deterministic check may advance knowledge mastery; spoken mastery requires eligible spoken evidence | Pass: every no-capture and capture-receipt result remains `spoken_state=unassessed` |
| Capture receipt | local-capture receipt is non-authoritative and cannot satisfy spoken mastery | Pass: bounded receipt validation, deterministic results and progress assertions |
| Raw-audio privacy | audio bytes stay in volatile browser memory and never enter API, storage, cache, log, audit or outbox data | Pass: browser storage/network/cleanup assertions, strict payload rejection and safe-event source audit |
| Checkpoint boundary | no PR-007 offline/outbox, provider orchestration, transcription, generated voice or recording-upload workflow | Pass: source/dependency/route/storage scope audit |
| Content authority | canonical synthetic seed remains unpublished; contract fixtures create no human approval or media-rights claim | Pass: repeated seed and zero-publication assertions |
| Browser evidence | a stubbed browser journey is labeled as contract evidence, not a true full-stack published-media path | Pass: two explicitly contract-stubbed Chromium learner scenarios |

### Commands and results

| Command or check | Result |
|---|---|
| Focused PR-006 PostgreSQL suites | Pass: lifecycle/exact-one 2 tests/78 assertions; concurrency 1/24; HTTP 2/65; request validation 3/10; verified audio 4/8 |
| Fresh PostgreSQL migration and seed | Pass on the explicitly verified `global_english_test` database |
| Empty-data rollback, forward migration and repeat seed | Pass; migration `000024` reapplied once, seed remained idempotent, 21 permissions and zero publications/profiles/attempts |
| `make verify` | Pass: SQLite 39/264, PostgreSQL 163/1,848, localization 23, design system 8 and web 50; audits, formatting, PHPStan, lint, type and contract checks passed |
| `make e2e` | Pass: 7 Chromium scenarios |
| `make openapi` | Pass: OpenAPI 3.1.1 version 0.4.0 and generated clients match |
| `make build` | Pass: Laravel production cache exercise and Vite build of 63 modules |
| Local Markdown structure/link validator | Pass: 25 in-scope files, zero structural/local-link failures |
| `git diff --check` and checkpoint-scope audit | Pass; unrelated `deliverables/` and `docs/archive/` remain outside the checkpoint |

The passing PR-005 commands remain the starting baseline and are not counted
as PR-006 evidence. `make ai-eval` is not applicable because PR-006 is
forbidden from changing prompts, models, providers or AI orchestration.

### Security, privacy and tenancy review

- Organization context must be reconstructed from the accepted authenticated
  principal and active membership. Request bodies cannot supply authoritative
  organization, learner, enrollment owner, curriculum owner or publication
  scope.
- Learner commands must prove exact user-membership ownership and active
  enrollment state. Same-tenant access to another learner is denied just as
  cross-tenant access is.
- Start, response and submission paths must resolve the exact enrollment pin
  and mission. `pending_curriculum`, completed and withdrawn enrollments fail
  closed. Ordinary share withdrawal preserves an existing pin, while owner
  inactivity, current rights failure and emergency suspension deny delivery
  and new learning writes.
- Attempt-number allocation, response append, submission, summary refresh,
  mastery and review scheduling require a transactional lock boundary.
  Actor/fingerprint-bound idempotency returns exact replay and rejects changed
  requests or another actor.
- The server derives score, knowledge mastery and review timing from governed
  versioned rules. Client-supplied score, mastery, attempt number or spoken
  authority is rejected.
- Raw learner audio remains entirely in volatile browser memory. Retry,
  cancellation, route exit and unmount release media tracks and object URLs.
  No raw audio or transcript belongs in logs, metrics, requests, PostgreSQL,
  object storage, IndexedDB, service-worker cache, audit or outbox data.
- A bounded local-capture receipt is retained only as non-authoritative input
  metadata. It contains no audio, transcript, filename, blob URL, device label
  or provider data and cannot award spoken mastery.
- Cross-tenant and same-tenant-other-learner failures use safe errors that do
  not disclose record existence.

### Content, media and test-fixture review

- APP-0005 authorizes PR-006 implementation but approves no future curriculum
  publication, Kannada wording, transcript, model audio or media-rights
  evidence.
- The canonical `FoundationSeeder` must remain an unpublished
  Codex-attributed draft. It cannot manufacture human review, rights
  confirmation or a production publication.
- A browser contract stub or synthetic media fixture may demonstrate client
  states and accessibility. It is not accountable human review and is not
  evidence that a real published media asset is deliverable.
- Backend PostgreSQL integration tests remain authoritative for tenant
  ownership, publication pins, immutable evidence, idempotency, concurrency,
  mastery and review rules unless a true full-stack journey is implemented
  and separately identified.

### Migrations, contracts and configuration reviewed

- `2026_07_30_000024_create_deterministic_learning_tables.php` adds
  `learner_profiles`, `learner_goals`, `learner_attempts`, `item_responses`,
  `attempt_submissions`, `mastery_states`, `review_schedules` and
  `learning_commands`, plus the composite enrollment-pin identity required by
  their foreign keys.
- PostgreSQL partial uniqueness, checks, composite foreign keys and triggers
  enforce one open attempt, immutable snapshots, sequential responses,
  deterministic submission/mastery/review projections and append-only
  evidence. The migration refuses rollback after learner profiles or attempts
  exist; authoritative adoption therefore requires a reviewed forward fix or
  backup restoration rather than silent evidence loss.
- OpenAPI 3.1.1 document version 0.4.0 adds `getLearnerHome`,
  `completeLearnerOnboarding`, `getLearnerMission`,
  `startLearnerMissionAttempt`, `recordLearnerAttemptResponse`,
  `submitLearnerMissionAttempt`, `getLearnerProgress` and
  `streamLearnerModelAudio`. Generated TypeScript clients, types and JSON
  schemas are committed under `packages/contracts/src/generated/`.
- The current-identity response now includes the authoritative
  `membership_type`; the React journey renders only for a selected learner
  membership rather than inferring learner authority from a mutable role key.
- `config/learning.php` owns the allowlisted goals, versioned
  `daily-speaking-v1` rule selection, local-capture MIME/size/duration bounds
  and private model-audio MIME/10 MB bounds. `LEARNING_MODEL_AUDIO_DISK`
  separates governed delivery from a caller-supplied storage location.
- Interface catalogue revisions are `ui-en-IN-2026-07-30.4` and
  `ui-kn-IN-2026-07-30.4`. English is the approved source; the Codex-authored
  Kannada interface draft remains `pending-independent-review` and falls back
  to English under the production `approved-only` policy.

### Known limitations

- No production learner mission, Kannada guidance, transcript or model-audio
  asset has accountable runtime publication evidence.
- The canonical synthetic mission remains unpublished.
- Browser E2E is contract-stubbed and therefore does not prove a real
  API-to-PostgreSQL-to-governed-media path.
- PR-006 deliberately rejects publications with zero or multiple missions.
  Multi-mission objective progress requires a later persisted result snapshot
  or global objective-evidence order before this implementation can expand.
- Raw learner audio is not uploaded, retained, transcribed, analyzed or
  available for trainer review.
- A local-capture receipt cannot award spoken mastery.
- At PR-006 exit, DR-0005 remained open for recording consent states,
  retention, deletion and provider-processing boundaries. It was subsequently
  resolved by ADR-0007 under APP-0011 without authorizing PR-009.
- PR-007 offline mission bundles, service-worker caching, IndexedDB outbox and
  reconnect synchronization remain excluded.
- AI conversation, speech-to-text, generated voice, pronunciation signals and
  other provider orchestration remain excluded.
- Independent production reviews and environment limitations remain in force.
- Docker/Compose and PostgreSQL 18.4 were unavailable; local PostgreSQL 14.19
  was used. Local PHP 8.4.17 differs from pinned 8.4.23, and GitHub Actions
  did not run on a remote runner.

### Exit decision

PR-006 is implemented and locally verified. This quality review itself does
not approve a curriculum publication or authorize production.

### Subsequent checkpoint acceptance

[APP-0007](APPROVALS.md#app-0007) records `S R Bhandary`'s explicit
Accountable Product Owner acceptance of PR-006 at
`598ad387e65b94707564dce1e199c97deb411d82`, with its recorded limitations.
Only PR-007 decision-resolution work is authorized. Product implementation
remains blocked until ADR-0006 is accepted and DR-0004 is resolved.

---

## ADR-0005 — Phase 1 multilingual UI expansion

### Scope reviewed

- complete UI catalogue previews for Malayalam (`ml-IN`), Tamil (`ta-IN`),
  Telugu (`te-IN`) and Hindi (`hi-IN`);
- preservation of Kannada as the default requested interface locale and
  English as the governed source, learning language and deterministic
  fallback;
- exact catalogue parity, source linkage, hashes and honest draft governance;
- strict production fallback and visible development/review preview status;
- locale selection, BCP 47 language metadata and privacy-safe fallback events;
- self-hosted Kannada, Malayalam, Tamil, Telugu and Devanagari variable fonts;
- six-locale unit, accessibility, reflow, font and browser coverage; and
- exclusion of curriculum, speech recognition, model audio, AI explanations,
  API contracts, persistence and authoritative records.

The accepted boundary and release gates are maintained in
[the Phase 1 multilingual UI expansion scope](PHASE1_MULTILINGUAL_UI_EXPANSION_SCOPE.md).

### Review result

| Check | Result | Evidence |
|---|---|---|
| Six complete interface catalogues have exact key and placeholder parity | Pass | `locale:check` and localization validation tests; 129 keys per catalogue |
| English remains the source and fallback | Pass | resolver policy and strict production fallback tests |
| All five translations are honestly marked as implementation drafts | Pass | governance metadata has `pending-independent-review`, no reviewer and no approval time |
| Drafts render only under preview policy with a visible notice | Pass | resolver and web application tests for all four added locales |
| Locale selection remains ephemeral and credential-free | Pass | web unit and Chromium storage checks |
| Language metadata covers every supported BCP 47 locale | Pass | application and mixed-language component tests |
| Script fonts are self-hosted and loaded | Pass | pinned package lock and Chromium font-resource checks |
| Six locale states remain axe-clean and responsive | Pass | Chromium axe, width, enlarged-text and target-size checks |
| Curriculum and provider boundaries remain unchanged | Pass | source, contract and scope inspection; no OpenAPI or backend behavior change |

### Catalogue evidence

| Locale | Revision | Catalogue SHA-256 | Release status |
|---|---|---|---|
| `en-IN` | `ui-en-IN-2026-07-30.7` | `894b0f5e95ecb9347f1eeb55a8dfed1f80991996ebb8a1d3f843030cd1b8d5e9` | Source |
| `kn-IN` | `ui-kn-IN-2026-07-30.7` | `9dac4931d05f0b46903d40592179f13005753dde857caa4279e8e7942492e19a` | Pending independent review |
| `ml-IN` | `ui-ml-IN-2026-07-30.3` | `c9a73cd2e78a53ca11df9d4f7e982143e3ffa9ddd7e54a0f64352d4ad98a66ac` | Pending independent review |
| `ta-IN` | `ui-ta-IN-2026-07-30.3` | `38322af7777cac4aea5bb726a50846a25f424387f53304b1c5d3bc077881114a` | Pending independent review |
| `te-IN` | `ui-te-IN-2026-07-30.3` | `9b678d26e3c9c7084750be702ace49a46c3c213f882bfe1d7de42eca8a939dc8` | Pending independent review |
| `hi-IN` | `ui-hi-IN-2026-07-30.3` | `8f5818d1f9781c4522b4a2a2a48e5b89eac21616dc5d38c4cdcc7cdfe2430a65` | Pending independent review |

### Commands and results

| Command or check | Result |
|---|---|
| `make verify` | Wrapper blocked: GNU Make is not installed; the exact constituent commands passed with SQLite 39 tests/264 assertions, PostgreSQL 164/1,883, localization 31, design system 9 and web 54 |
| `pnpm run e2e` (`make e2e` constituent) | Pass: 8 Chromium scenarios |
| Laravel optimize/cleanup and `pnpm --filter @global-english/web build` (`make build` constituents) | Pass: strict production build of 70 modules |
| `pnpm run locale:check` | Pass: 6 catalogues with the 5 expected pending-review governance warnings |
| Changed Markdown local-link validation | Pass: 11 files, zero missing local targets |
| `git diff --check` | Pass |

`make ai-eval` is not applicable because prompts, models, providers and AI
orchestration are explicitly unchanged.

### Security, privacy and tenancy review

- No authentication, authorization, organization-scoping or tenant-owned
  record changed.
- Locale selection remains in React memory. It is not written to
  `localStorage`, IndexedDB, cookies, ordinary Capacitor preferences or the
  server.
- Fallback events contain only allowlisted catalogue metadata; they contain no
  translated message, parameter, learner data, credential or provider
  payload.
- Catalogue and font assets are static repository-governed build artifacts.
  No database migration, seed, production content, learner data or media was
  added.

### Known limitations and release gates

- Kannada, Malayalam, Tamil, Telugu and Hindi wording is an
  implementation draft authored by Codex, not a human-approved translation.
- Each exact revision and hash requires an accountable translator and a
  distinct independent reviewer competent in that language before production.
- Ordinary production remains `approved-only` and visibly falls back to
  English for every unapproved, stale, rejected or incomplete translation.
- Manual terminology, wrapping, browser zoom, screen-reader pronunciation and
  representative Android TalkBack review remains outstanding for every
  translated locale.
- UI availability does not imply curriculum, speech-recognition, model-audio,
  text-to-speech or AI-explanation support in that language.
- PR-006 is accepted separately under APP-0007. ADR-0006 and APP-0008 now
  authorize the separately reviewed PR-007 implementation; that does not
  approve any translated catalogue for production.

### Exit decision

The bounded Phase 1 multilingual UI implementation authorized by APP-0006 is
implemented and locally verified. This review does not approve any translated
catalogue for production or expand curriculum, speech, audio or AI behavior.
PR-006 is accepted separately under APP-0007; PR-007 authority is recorded
separately in APP-0008.

---

## PR-007 — Offline bundle and idempotent outbox

### Scope reviewed

- APP-0007 acceptance of PR-006 at its exact implementation commit;
- accepted ADR-0006 and APP-0008 PR-007-only implementation authority;
- signed mission-bundle and atomic reconnect-completion contracts;
- personal-device opt-in, protected browser storage, public shell caching,
  offline UI states and logout boundaries;
- exact identity, membership, tenant, enrollment, publication and policy
  binding; and
- PostgreSQL authority, idempotency, rollback and ordinary PR-006 evidence
  behavior.

### Review result

| Check | Result | Evidence |
|---|---|---|
| PR-006 acceptance is bound to the exact `598ad387e65b94707564dce1e199c97deb411d82` revision | Pass | APP-0007 |
| ADR-0006 is accepted at the exact approved proposal hash and DR-0004 is resolved | Pass | ADR-0006 and APP-0008 |
| Bundles are signed, purpose-specific, exact-account/publication bound, no-store and expire at or before 24 hours | Pass | service implementation and PostgreSQL expiry/tamper/identity tests |
| Offline completion is one atomic server-authoritative PR-006 command with exact replay and changed-replay conflict | Pass | PostgreSQL lifecycle tests and one outer command assertion |
| Invalid option or late failure leaves no partial attempt, response, mastery, review or command | Pass | PostgreSQL rollback assertions |
| Persistent storage requires explicit personal-device confirmation and is absent before opt-in | Pass | web unit and Chromium storage tests |
| Protected records use opaque partitions, non-exportable AES-GCM keys, authenticated metadata and verified model-audio bytes | Pass | browser implementation and Chromium ciphertext/key/hash checks |
| Credentials, raw learner audio, names and organization display names are excluded | Pass | request/storage allowlists, validation tests and browser payload inspection |
| Public caching excludes API, Sanctum and OAuth paths | Pass | service-worker policy and Chromium cache inspection |
| One browser lock coordinates reconnect while server idempotency remains final | Pass | client implementation, immutable command unit test and server replay tests |
| Logout blocks on pending work and otherwise purges keys before web or Android server revocation | Pass | authentication adapter ordering and blocked-logout tests |
| OpenAPI and generated clients describe both offline endpoints without drift | Pass | Redocly, policy validation and generated-client check |
| PR-008, AI/provider behavior, recording upload and production remain absent | Pass | dependency, route and scope inspection |

### Commands and results

| Command or check | Result |
|---|---|
| `make verify` | Wrapper unavailable because GNU Make is not installed; every constituent command passed |
| Composer validate/audit | Pass; no locked dependency advisory |
| Pint and PHPStan | Pass; zero static-analysis errors |
| SQLite suite | Pass: 39 tests, 264 assertions |
| PostgreSQL suite | Pass: 164 tests, 1,883 assertions |
| pnpm frozen install/audit | Pass; no known vulnerability |
| Locale/OpenAPI/generated-client/lint/typecheck | Pass; five intentional translation-review warnings only |
| Workspace unit tests | Pass: localization 31, design system 9, web 54 |
| `pnpm run e2e` | Pass: 8 Chromium journeys |
| Laravel production optimize/cleanup and Vite build | Pass: 70 transformed modules |

`make ai-eval` is not applicable because prompts, models, providers and AI
orchestration are unchanged.

### Security, privacy and tenancy review

- The lease cannot authenticate an API request; reconnect still requires the
  current first-party principal, active exact membership, tenant context,
  active enrollment and unchanged publication authority.
- The browser never decides attempt number, mastery, review timing or
  authoritative completion. Local correct/incorrect guidance is visibly
  pending until the server returns the ordinary result.
- Same-tenant other-learner access, signature tampering, lease expiry and
  caller-supplied authority fail closed. Existing organization scoping remains
  inside the project-owned learning services.
- The APP-0008 development roles are consolidated in `S R Bhandary`; this is
  explicitly not independent production review.

### Known limitations and release gates

- There is no learner-facing logout/account-management screen. The adapter
  boundary blocks logout when pending protected commands exist, but the later
  UI must offer sync, cancel or explicit permanent discard before retrying.
- Browsers without `indexedDB.databases()` fail closed for offline resume;
  they may enable the current-session download but cannot silently reopen a
  partition after restart.
- The browser cannot guarantee timed cleanup while it is not running. Cleanup
  is activation/contact driven as accepted in ADR-0006.
- Browser locks are advisory. Server transactions and idempotency remain the
  final duplicate/concurrency authority.
- The E2E journey uses contract-stubbed synthetic curriculum/media; production
  content, language, accessibility-device and release review remain open.
- The workstation has Docker Engine and Compose, but this Codex login has not
  refreshed docker-group membership. Full PostgreSQL verification used the
  available loopback PostgreSQL 14 service.
- GNU Make is absent, so documented wrapper targets were reproduced from the
  Makefile instead of invoked directly.
- GitHub Actions and representative Android device tests have not run.
- PR-007 is accepted separately under APP-0009 at
  `e0e2f1b90e0eb4ecf680f5243b57ec0731c32648`. PR-008 and production remain
  unauthorized.

### Exit decision

PR-007 is implemented and repository-verified within APP-0008, and the
Accountable Product Owner accepts the exact implementation commit
`e0e2f1b90e0eb4ecf680f5243b57ec0731c32648` with its recorded limitations
under APP-0009. That acceptance does not authorize PR-008, production or any
later implementation checkpoint.

---

## PR-008 — Conversation domain with deterministic fake providers

### Scope reviewed

- APP-0010 authority and the accepted PR-007 baseline;
- organization-scoped personas, scenarios, learner sessions and turns;
- project-owned STT, conversation-model and TTS ports;
- local/testing-only deterministic fake adapters and production fail-closed
  bindings;
- deterministic state transitions, bounded retry, idempotency, safe SSE
  status history and zero-cost usage records;
- request-memory-only typed prototype content and the no-audio boundary;
- authenticated learner Practice UI, disclosure and six-locale draft
  catalogue changes; and
- OpenAPI, generated clients, PostgreSQL protection and regression coverage.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Persona and scenario definitions are versioned, synthetic and organization scoped | Pass | migration, foundation seed and PostgreSQL seed/integrity tests |
| Sessions and turns bind the authenticated learner's exact active membership and organization | Pass | `LearnerAuthority`, composite foreign keys and same-/cross-tenant tests |
| Provider SDK types do not enter the domain | Pass | project-owned provider interfaces, DTOs and adapter bindings |
| Only an explicit fake driver in `local`/`testing` can resolve fake adapters | Pass | environment gate, disabled adapters and feature tests |
| Successful and retry/failure state transitions are deterministic | Pass | state-machine and fail-once/exhausted PostgreSQL tests |
| Exact command replay is stable and changed replay conflicts | Pass | session/turn idempotency tests and row-count assertions |
| Persistent records exclude utterance, transcript, reply and audio content | Pass | schema allowlists plus database, audit and outbox content scans |
| SSE contains only ordered safe state metadata | Pass | media-type, no-store, ordering and ownership tests |
| Usage records contain bounded latency/attempt metadata and zero fake cost | Pass | cost-ledger constraints and integration assertions |
| Conversation cannot modify attempts, mastery, progression or review schedules | Pass | before/after learning-table assertions |
| Web UI discloses fake AI, request-memory-only text and text-only fake TTS | Pass | React tests and Chromium critical journey |
| No browser audio/upload/provider-network path exists | Pass | source inspection and intercepted Chromium request assertions |
| API and generated client contracts match | Pass | strict Redocly, policy and generated-drift checks |
| PR-009, real providers and production remain absent | Pass | dependency, route, configuration and scope inspection |

### Commands and results

| Command or check | Result |
|---|---|
| Composer validate/audit | Pass; no locked dependency advisory |
| pnpm frozen install/audit | Pass; lockfile current and no known vulnerability |
| Pint and PHPStan | Pass; zero formatting or static-analysis errors |
| SQLite-safe backend suite | Pass: 42 tests, 277 assertions |
| PostgreSQL integration suite | Pass: 169 tests, 1,981 assertions |
| `pnpm run locale:check` | Pass: six catalogues and five expected pending-review warnings |
| `pnpm run openapi:check` | Pass: OpenAPI 3.1.1 version 0.6.0 and generated-client parity |
| ESLint and recursive TypeScript | Pass |
| Workspace unit tests | Pass: localization 31, design system 9 and web 57 |
| `pnpm run e2e` | Pass: 9 Chromium journeys, including the fake-provider conversation and axe scan |
| Laravel optimize/cleanup and web production build | Pass: 74 Vite modules transformed |
| `make verify` / `make build` | Wrapper blocked because GNU Make is absent; every constituent command above passed |

`make ai-eval` remains not applicable: PR-008 uses fixed deterministic fake
responses and introduces no real model, prompt selection or provider
orchestration.

### Security, privacy and tenancy review

- Every mutable conversation row carries `organization_id` and exact learner
  membership ownership. Composite constraints and application authorization
  fail closed for same-organization other learners and other tenants.
- Bounded typed text is processed only in request memory. The database stores
  SHA-256 digests and safe metadata, while audit/outbox tests prove the
  prototype utterance and fake reply do not escape.
- Fake TTS returns metadata only. No recording permission, audio bytes,
  multipart/base64 input, object-storage key, playback URL or retained audio
  is introduced.
- Provider identity, model version, state, attempt, latency and cost are
  server-owned. The fake ledger cost is constrained to zero.
- Production and non-local environments bind disabled adapters. There is no
  provider credential or external provider dependency.
- The conversation slice is advisory and has no write path to deterministic
  learning authority.

### Known limitations and release gates

- The fake STT echoes bounded typed text; it does not recognize speech. Fake
  TTS creates no audio, and the fake model returns one fixed synthetic reply.
- The event route returns a bounded history and closes. It is not a live
  multi-process broker or reconnectable production stream.
- Conversation text retention, real provider processing, consent, secure
  upload, transcript recovery and deletion were unresolved at PR-008
  implementation time. ADR-0007 now supplies the accepted policy under
  APP-0011, but PR-009 implementation remains separately unauthorized.
- The seeded persona/scenario and all five translated UI catalogues are
  synthetic implementation drafts, not production-approved content.
- The Chromium journey uses contract stubs for the web boundary; backend
  tenancy, persistence, replay and state behavior are separately proven
  against PostgreSQL.
- GNU Make is absent, so wrapper commands were reproduced from the Makefile.
  GitHub Actions, real devices, screen readers and independent production
  security/privacy/tenancy/accessibility/language/release reviews have not run.
- No public-facing server was started. Playwright used a temporary preview
  listener on `127.0.0.1:4173` and stopped it after each run.

### Exit decision

PR-008 is implemented and repository-verified within APP-0010 and is ready
for exact-commit Accountable Product Owner review. It is not accepted yet.
PR-009, real provider calls, audio upload/persistence and production remain
unauthorized.

---

## DR-0005 — Recording policy decision and acceptance

### Scope reviewed

- master-plan recording, privacy, provider, tenancy and PR-009 constraints;
- accepted ADR-0001, ADR-0002, ADR-0003 and ADR-0006 boundaries;
- accepted consent purposes and per-turn learner submission;
- upload quarantine and media-validation limits;
- raw-audio, transcript, generated-media, evidence and backup retention;
- withdrawal, deletion, provider deletion and reconciliation;
- provider eligibility, request minimization and no-training rules;
- learner transcript recovery and deterministic learning authority; and
- non-production, legal, independent-review and release gates.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Exact proposal integrity | Pass | approved SHA-256 matches the committed ADR-0007 proposal at `98c46584b298cbf18623b140dbe5dc3dbc7737e6` |
| Approval authority and evidence | Pass | APP-0011 records the exact statement and all four required development roles |
| ADR acceptance | Pass | accepted front matter links APP-0011 and preserves the approved proposal hash |
| DR-0005 resolution | Pass | resolved-decision table links ADR-0007 and APP-0011 |
| PR-009 remains unauthorized | Pass | ADR status, fail-closed rollout and repository status |
| Raw audio is temporary, private and excluded from ordinary logs/backups | Pass | data-classification, validation and retention sections |
| Consent, withdrawal and deletion are purpose-specific and measurable | Pass | consent ledger, per-turn authorization and deletion objectives |
| Provider data is minimized and secondary use is prohibited | Pass | provider-processing boundary |
| Organization and exact-membership ownership remain mandatory | Pass | tenancy and authorization section |
| Automated transcript/speech output cannot alter learning authority | Pass | transcript-recovery and authoritative-record sections |
| Production has explicit legal and independent-review gates | Pass | context and fail-closed rollout |
| No source, API, schema, configuration or environment change is authorized | Pass | documentation-only diff inspection |

### Commands and results

| Command or check | Result |
|---|---|
| Node/js-yaml front matter, heading, fence, final-newline, local-link and anchor validation | Pass: five changed Markdown files |
| Accepted-status and APP-0011 evidence assertions | Pass |
| `git diff --check` | Pass |
| Scoped status/diff inspection | Pass: only the accepted ADR and four governance records belong to this checkpoint |
| SHA-256 proposal hashing | Pass; exact hash reported with the proposal commit |

Application test, E2E, AI-evaluation and build commands are not applicable
because this checkpoint changes documentation only and authorizes no runtime
behavior.

### Security, privacy and tenancy review

The accepted policy treats learner voice and transcript content as sensitive,
purpose-limited data. It requires fail-closed adult eligibility, consent,
private encrypted media, short retention, deletion reconciliation,
content-free telemetry, project-owned provider adapters, exact tenant
ownership and same-/cross-tenant tests before any later implementation can be
accepted.

This is a development policy draft, not legal advice or independent
production review. Qualified Indian privacy counsel must confirm applicable
commencement, notice, retention, erasure, processor and transfer obligations
before production.

### Known limitations and implementation gate

- `S R Bhandary` accepted the exact proposal artifact while exercising four
  consolidated development roles; this is not independent production review.
- No real provider has been selected, reviewed, contracted or benchmarked.
- No notice or translation has independent legal or language approval.
- The accepted policy intentionally leaves PR-009 implementation, provider
  configuration, audio processing and production unauthorized.
- PR-008 exact-commit acceptance remains a separate pending checkpoint gate.

### Exit decision

ADR-0007 is accepted under APP-0011 and DR-0005 is resolved. The approval
records no PR-009 implementation authority or production authority.

---

## PR-009 — Provider-neutral secure speech and transcript recovery

### Scope reviewed

- APP-0012 authorization and accepted ADR-0007;
- separate upload/transcription and provider-processing consent purposes;
- local volatile capture, explicit submit and browser PCM/WAV conversion;
- server-side media signature/container/codec/duration/size verification;
- application-encrypted transient private storage and scheduled deletion;
- project-owned recorded-STT port with disabled and deterministic-test
  adapters only;
- transcript confidence, learner correction/discard, history-off behavior and
  deletion evidence;
- organization/exact-membership ownership, replay protection and
  deterministic-learning isolation;
- version 0.7.0 OpenAPI/generated clients and the accessible Practice UI; and
- six-locale governance with production English fallback.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Voice collection is disabled by default and production fails closed | Pass | `voice.php`, feature gate, disabled adapter and environment binding |
| Upload/provider purposes are separately granted from a versioned notice | Pass | append-only consent service/schema and UI/unit coverage |
| Client cannot choose tenant, learner, provider, model, confidence, retention or state | Pass | authenticated route inputs, server-owned configuration and services |
| Actual media is strictly bounded and inspected rather than trusting filename/extension | Pass for the local PCM/WAV slice | RIFF/WAVE inspector and malformed/codec/channel/rate tests |
| Private object keys contain no learner/tenant identifiers and stored bytes are application encrypted | Pass for local/testing | random object-key generation, `Crypt` encryption and content scan |
| Successful source is removed after transcript candidate commit | Pass | storage fake and lifecycle assertions |
| Provider outage/malformed output retries twice and fails recoverably | Pass | deterministic adversarial adapters and provider-run assertions |
| Due source/transcript content is purged with visible deletion evidence | Pass | scheduled `voice:purge-expired` service and expiry test |
| Transcript can be corrected/discarded without learning authority writes | Pass | lifecycle and before/after learning-table assertions |
| Recording access is exact-learner and cross-tenant safe | Pass | composite foreign keys and cross-tenant service test |
| Telemetry/database evidence excludes audio and transcript bodies | Pass for tested provider-neutral paths | ciphertext/digest and safe provider-run scans |
| API/generated clients, localization and accessible UI match | Pass | OpenAPI policy/drift, locale validation, React and axe Chromium tests |
| One live reviewed STT and TTS adapter has benchmark evidence | Blocked | ADR-0008 is accepted, but no live adapter, operational vendor evidence, approved credential or benchmark result exists |
| Production media storage/scanning/provider deletion is reviewed | Blocked | production and specialist reviews remain unauthorized |

### Commands and results

| Command or check | Result |
|---|---|
| `make verify` with pinned Linux-native toolchain | Pass |
| Composer validate/audit and pnpm frozen install/audit | Pass; no known locked vulnerability |
| Pint and PHPStan | Pass; zero formatting/static-analysis errors |
| SQLite backend suite | Pass: 49 tests, 288 assertions |
| PostgreSQL integration suite on `127.0.0.1:5432` | Pass: 173 tests, 2,016 assertions |
| Workspace tests | Pass: localization 31, design system 9, web 59 |
| Locale governance | Pass with five expected pending-independent-review warnings |
| OpenAPI/generated-client drift, ESLint and recursive TypeScript | Pass |
| `make e2e` equivalent (`pnpm run e2e`) | Pass: 10 Chromium journeys |
| `make build` | Pass: Laravel optimize/cleanup and 76-module Vite build |
| `make ai-eval` | Not run; ADR-0008 selects the benchmark adapter, but its implementation and operational evidence gates are not complete |

### Security, privacy and tenancy review

- Voice records, provider runs and deletion evidence are tenant owned and bind
  the exact learner membership. Consent additionally binds the actor user and
  its organization/membership composite identity.
- Candidate transcript and source audio are encrypted; database/provider-run
  records contain only digests and bounded technical metadata. The UI never
  puts voice bytes or credentials into browser persistent storage.
- Upload, provider invocation, review and deletion are rate limited or
  idempotent as applicable. Withdrawal deletes affected active content.
- Provider output remains advisory and learner-correctable. It cannot update
  marks, mastery, progression, review schedules or any deterministic learning
  record.
- All development services observed during verification publish only to
  `127.0.0.1`; Playwright's preview server was temporary and stopped.

### Known limitations and exit decision

The provider-neutral development slice is implemented and verified, but
PR-009 is not complete or ready for Accountable Product Owner acceptance.
ADR-0008 now selects the exact STT and TTS implementations and locked synthetic
benchmark. The current implementation has no live provider, TTS audio,
operational vendor evidence, separate spend approval, approved scanner,
isolated streaming quarantine worker, reviewed production object store,
provider deletion reconciliation, restore/tombstone drill or independent
production review. PR-010 and production remain unauthorized.

---

## DR-0011 — Accepted Azure Speech development provider

### Scope reviewed

- accepted ADR-0007 provider, consent, retention and deletion requirements;
- current official Azure Speech regions, data handling, REST operations,
  language/voice support, DPA, Product Terms and subprocessor process;
- current official Google Cloud and AWS data-use/region alternatives;
- open-weight Whisper, Kokoro and Piper licence/runtime considerations;
- a synthetic-only PR-009 quality, latency, cost and safe-failure benchmark;
  and
- credentials, callbacks, region, training, human-review and rollout controls.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Exact development STT/TTS stack approved | Pass | accepted ADR-0008 selects Azure Fast Transcription REST `2025-10-15`, `en-IN`, and real-time `en-IN-NeerjaNeural` TTS under APP-0013 |
| India-region service support | Documented, not account-verified | official Azure region documentation lists `centralindia` for core STT/TTS and states Speech data stays in the resource region |
| Provider content retention | Documented, not contract-verified | official Azure documentation states no retention for fast STT and real-time TTS with logging/customization off |
| No training/model improvement | Documented, not account-verified | official Azure Speech documentation plus mandatory configuration/evidence gate |
| Exact legal entity, DPA and subprocessors | Blocked pending Product Owner account evidence | public DPA/subprocessor process cannot identify the future subscription's contracting entity or acceptance |
| Credential | Missing by design | no secret or Azure account data was requested, created or written |
| Callback/replay boundary | Pass by proposed design | synchronous REST only; callback, webhook, redirects and URL input prohibited |
| Synthetic benchmark definition | Approved and implemented; not run live | 48 strings, two voices, three deterministic conditions, 288 STT clips, 96 TTS outputs and conjunctive thresholds |
| Real learner/accent validation | Explicitly not satisfied | synthetic round trip is circular and production remains blocked |
| Provider enabled or called | No | ordinary binding remains disabled and fail-closed preflight blocks missing operational evidence |

### Commands and results

| Command or check | Result |
|---|---|
| Current official provider/contract documentation review | Pass for proposal drafting; account-specific evidence remains blocked |
| Local Markdown final-newline and link-target validation | Pass for all five affected documentation files |
| `git diff --check` and trailing-whitespace scan | Pass |
| `make verify` with the pinned Linux-native toolchain | Pass |
| Composer and pnpm locked audits | Pass; no known vulnerability |
| Pint and PHPStan | Pass; zero errors |
| SQLite suite | Pass: 49 tests, 288 assertions |
| PostgreSQL suite | Pass: 173 tests, 2,016 assertions |
| Localization, design-system and web tests | Pass: 31, 9 and 59 tests |
| OpenAPI/generated-client, lint and type checking | Pass |
| Provider calls and `make ai-eval` | No provider call; command now exits 2 at preflight because credentials/evidence/spend approval are absent |

The first verification attempt used the host toolchain and stopped at its
Node.js mismatch. The next pinned-toolchain attempt reached the Composer audit
and stopped because the sandbox could not resolve Packagist. The complete
pinned-toolchain gate then ran with approved advisory-registry access and
passed. Neither stopped attempt is represented as a test failure or a provider
call.

`make e2e` and `make build` were not rerun because this decision package
changes no application source, API, user journey, packaging or deployment
behavior. The previously recorded PR-009 results remain unchanged.

### Decision result and implementation gate

ADR-0008 and its benchmark plan are accepted under APP-0013 at their exact
recorded SHA-256 values. DR-0011 is resolved, including explicit acceptance of
managed STT model rotation and the circular synthetic benchmark.

Only the remaining PR-009 adapter and locked benchmark implementation is
authorized. A dedicated Central India Azure subscription, exact
contract/DPA/subprocessor evidence, non-production credential and separate
provider-spend approval are still required before the first live synthetic
request. No benchmark pass, PR-009 acceptance, learner-data use, PR-010 or
production is approved.

---

## PR-009 — Azure Speech adapter and locked benchmark implementation

### Scope reviewed

- APP-0013's exact authorization boundary and accepted ADR-0008;
- Azure Fast Transcription REST `2025-10-15` and real-time TTS adapter code;
- endpoint, credential-reference, response-size, media and retry controls;
- exact corpus, deterministic transforms, WER/hallucination measurements,
  thresholds, operation/attempt/spend caps and aggregate safe report;
- fault-injected adapter and complete-run tests using Laravel HTTP fakes only;
- ordinary application binding, learner-data and deterministic-authority
  isolation; and
- root verification, localhost-only Chromium journeys, build and fail-closed
  benchmark preflight.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Ordinary application/provider path remains disabled | Pass | `AppServiceProvider` has no Azure binding; concrete adapters are constructed only by the benchmark runner |
| Exact Azure endpoint and configuration fail closed | Pass | HTTPS exact-host allowlist, `centralindia`, fixed `en-IN`, API, profanity, voice and format validation |
| Credential and evidence handling follows ADR-0008 | Pass for implemented workstation boundary | external, owner-readable mode-`0600`, non-repository credential, vendor-evidence and spend-approval files must match the configured hashes, IDs, region, disabled data-use settings, scope, cap and validity dates |
| Request/response content stays out of safe evidence | Pass for tests | generic exceptions, bounded bodies, hashed allowlisted provider metadata and aggregate-only report |
| Corpus and transforms are locked | Pass | exact 48-entry NFC manifest hash plus deterministic 24 kHz-to-16 kHz clean/noise/low-pass tests |
| Logical operation and spend boundaries are locked | Pass | 544 logical operations, 1,088 maximum attempts and `₹1,000` maximum approved cap validated before dispatch |
| Retry policy is bounded and category aware | Pass | only connection/408/429/5xx categories are retryable; policy, authentication, authorization, redirect, size and malformed failures are terminal |
| Complete runner behavior is deterministic | Pass for HTTP-fake evidence only | 544 logical operations, one injected transient retry, 545 fake HTTP attempts, 368 STT results and 176 TTS results |
| Required fault categories fail closed | Pass | 401, 403, 408, 413, 429, 500, 503, redirect, connection failure, malformed JSON, malformed WAV and oversized response |
| Learner/tenant/learning authority remains untouched | Pass | runner accepts only committed synthetic corpus and performs no database or application-binding write |
| Live benchmark evidence | Blocked | exact subscription/DPA/subprocessor evidence, credential reference and separate spend approval are absent |

### Commands and results

| Command or check | Result |
|---|---|
| Approved proposal hash verification | Pass: `b1c778753ab9b0b6b7b8202f8d106a8ee302485455189afce76baac361841b51` |
| Approved benchmark-plan hash verification | Pass: `817f430aabd93eb06d9ecd1b980994862e1ddfe19f3285fe184ba508204581ab` |
| Targeted Azure/benchmark/PcmWav tests | Pass: 24 tests, 88 assertions |
| `make verify` with pinned Linux-native toolchain | Pass |
| Composer and pnpm locked audits | Pass; no known vulnerability |
| Pint and PHPStan | Pass; zero errors |
| SQLite suite | Pass: 66 tests, 365 assertions |
| PostgreSQL suite | Pass: 173 tests, 2,016 assertions |
| Localization, design-system and web tests | Pass: 31, 9 and 59 tests |
| OpenAPI/generated-client, lint and type checking | Pass; no public API change or generated-client drift |
| First sandboxed `make e2e` attempt | Did not start tests: sandbox denied the loopback bind with `EPERM` |
| Approved loopback-only `make e2e` retry | Pass: 10 Chromium journeys; temporary `127.0.0.1:4173` process stopped |
| `make build` | Pass: Laravel optimize/cleanup and 76-module Vite build |
| `make ai-eval` | Expected blocked exit 2 before HTTP; missing operational evidence, secret reference, runner commit, pricing/scan evidence and spend approval |
| Live Azure calls | Zero |

The first full `make verify` attempt stopped at three PHPStan findings before
tests. The endpoint-array shape, redundant header check and Artisan
pending-command narrowing were corrected without suppressions; the complete
gate then passed. This stopped attempt is not represented as a test failure.

### Security, privacy and tenancy review

- Authentication headers are built only after complete preflight and never
  enter provider results, safe reports, exception messages or logs.
- Redirects are disabled; endpoint paths are fixed; filenames and request
  bodies contain no tenant, learner, organization or interaction identifier.
- Synthetic text/audio remains in process memory. The only writable artifact
  is a mode-`0600` aggregate report containing hashes, metrics and safe evidence
  identifiers.
- Azure adapters parse into project-owned results and expose no SDK or HTTP
  type to the conversation/voice domain.
- The existing learner upload/transcript path remains separately tenant
  scoped, consent gated and deterministic. This benchmark code has no learner
  query, tenant selector or learning-record write.

### Known limitations and exit decision

Repository implementation and mocked integration verification are complete.
They are not provider evidence and do not report benchmark success. No Azure
credential, exact subscription contract/DPA/subprocessor snapshot, price
snapshot, content-scan evidence, listener evidence or separately approved
spend exists in repository configuration, so `make ai-eval` correctly blocks
before HTTP.

The benchmark retains the explicitly accepted circular Azure-TTS-to-Azure-STT
bias and provider-managed STT rotation limitation. It cannot validate learner
speech, Karnataka accents, accessibility, pedagogy or production fitness.
PR-009 is not accepted; learner data, PR-010 and production remain
unauthorized.

The subsequent no-network handoff review added
`PR-009_SPEECH_BENCHMARK_EVIDENCE_HANDOFF.md` so operational reviewers have
the exact external JSON shapes, file permissions, environment mappings and
sequencing enforced by the implementation. Its values are explicit
placeholders; it creates no approval, credential, live request or benchmark
evidence.

That review also found the circular listener-evidence dependency now recorded
as DR-0012. The runner correctly makes no request without listener evidence,
but valid listener evidence must be based on the actual 96 Azure TTS outputs.
Resolving this requires a reviewed encrypted two-phase fixture bundle or a
reviewed bounded in-process playback/attestation mechanism. This is a
privacy/security workflow decision, not permission to weaken preflight.

Accepted ADR-0009 resolves the design through an encrypted
prepare/review/complete state machine, a token-protected loopback-only
browser-memory review utility, 24-hour expiry and deletion evidence. The
proposal preserves the 544-operation cap by reusing the 96 prepared outputs.
APP-0014 authorizes only this bounded DR-0012 implementation. It does not
authorize credentials, provider spend, live requests, benchmark success,
PR-009 acceptance, learner data, PR-010 or production.

---

## PR-009 — DR-0012 encrypted fixture and listener-review workflow

### Scope reviewed

- APP-0014's exact authorization boundary and accepted ADR-0009;
- authenticated per-fixture encryption and encrypted atomic resume state;
- exact 24-hour lifetime, external owner/mode/path/symlink checks and purge
  scheduling;
- prepare idempotency, partial-run refusal, completion replay prevention and
  resumed operation/attempt/spend accounting;
- two anonymous listener-disposition slots and exact external aggregate
  matching;
- the ephemeral loopback HTTP server, fragment token, authorization header,
  proxy/public-host refusal, cache/CSP headers and browser-memory playback;
- terminal deletion receipts, retry-visible failed deletion and tamper/expiry
  handling; and
- database, tenant, learner, public API, web/mobile bundle and provider-call
  isolation.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Locked operation plan survives phase split | Pass with HTTP fakes | prepare persists 96 logical TTS operations; complete restores them and performs 448 more for exactly 544 total |
| Duplicate spend/replay is prevented | Pass | prepared rerun performs zero calls; partial preparation cannot restart; completion atomically enters non-replayable state |
| Fixture confidentiality and integrity | Pass | separate authenticated application ciphertext, random opaque filenames, mode-`0600`, exact ciphertext/plaintext hashes and no persisted `RIFF` bytes |
| External path boundary | Pass | absolute owner-controlled mode-`0700` local root required outside repository, Laravel storage/public and web roots; symlinks/wrong modes fail closed |
| Listener privacy | Pass | only required booleans in two anonymous slots; no human identifier, contact detail, IP, browser value or free text |
| Loopback review boundary | Pass | hard-coded `127.0.0.1:0`, 256-bit fragment token, bearer authorization, proxy/public-host rejection, `no-store`, restrictive CSP and revoked object URLs |
| Expiry, corruption and deletion | Pass | exact 24-hour expiry, corrupt ciphertext rejection, hourly/manual purge, visible failed-delete receipts and immutable successful receipt |
| Completion binding | Pass | exact configuration/evidence hashes, corpus, plan, ADR hash, runner revision/commit, transform, dependency locks, fixture plan and counters revalidated before HTTP |
| Tenancy and deterministic authority | Pass | test listener observed zero database queries; workflow has no organization, learner, curriculum, marks, mastery, progression or review-schedule path |
| Public application surface | Pass | no route/OpenAPI/generated-client/web/mobile change; review utility exists only behind the benchmark command |
| Live provider evidence | Blocked | no credential, provider-spend approval, live-request authorization, listener evidence or Azure result exists |

### Commands and results

| Command or check | Result |
|---|---|
| ADR-0009 proposal hash verification | Pass: `a0848d462f9363cc2fb9a2babd2f50fd8ae7ad4ed99ee2cd3abaaeb828413e0f` |
| Targeted Azure/benchmark tests | Pass: 21 tests, 413 assertions; Laravel HTTP fakes only |
| Targeted Pint and PHPStan level 8 | Pass; zero errors |
| `make verify` with pinned Linux-native toolchain | Pass |
| Composer and pnpm locked audits | Pass; no known vulnerability |
| SQLite suite | Pass: 70 tests, 701 assertions |
| PostgreSQL suite | Pass: 173 tests, 2,016 assertions |
| Localization, design-system and web tests | Pass: 31, 9 and 59 tests |
| OpenAPI/generated-client, lint and recursive type checking | Pass |
| `make build` | Pass: Laravel optimize/cleanup and 76-module Vite build |
| `make e2e` | Pass: 10 Chromium journeys; temporary `127.0.0.1:4173` server stopped |
| `make ai-eval` | Expected blocked exit 2 before HTTP because no opaque run ID or live authorization exists |
| Live Azure calls | Zero |

The first full verification attempt used the workstation-default Node.js and
stopped at the declared-toolchain check. The first pinned attempt stopped when
the sandbox could not resolve the Composer advisory service. The pinned retry
with approved advisory-registry access passed. Neither stopped attempt is
represented as a product test failure or provider call.

### Security, privacy and tenancy review

- Plaintext fixture audio exists only in bounded provider/process memory and
  one authenticated loopback response/browser blob at a time.
- The encrypted state contains opaque fixture mapping and counters; no
  transcript, SSML, credential, evidence document, learner/tenant value or
  human reviewer identifier is written.
- Successful or ordinary failed completion deletes the bundle. A crash leaves
  encrypted content for the scheduled/manual purge, and a deletion failure
  remains visible and blocks acceptance.
- Revoked or changed provider, evidence, pricing, credential-version,
  configuration, code or dependency bindings block completion before a new
  provider request; the terminal complete path purges the bundle.
- There is no migration, database record, public endpoint, ordinary provider
  binding or change to deterministic learning authority.

### Known limitations and exit decision

The DR-0012 implementation is complete and repository-verified, but no
preparation or completion phase was run against Azure. The browser utility has
automated contract/security coverage and hard-coded loopback binding; the two
accountable listener sessions, actual audio-device behavior and independent
accessibility/security/privacy review remain human gates.

PR-009 is not accepted. Exact subscription/contract/DPA/subprocessor evidence,
a dedicated credential, reviewed price/content-scan evidence, separate
provider-spend approval, explicit live-request authorization, two accountable
listener reviews and the locked live result remain outstanding. Learner data,
PR-010 and production remain unauthorized.

---

## Demo Phase 1 dashboard shell (D-0081)

### Scope reviewed

- demo-only Phase 1 module navigation and synthetic fixtures for Attendance,
  AI Teacher, Daily Activity, My Levels and Notice Board;
- ordinary product shell unchanged when `demoMode` is false;
- no cloud AI/speech calls; scripted AI Teacher replies only;
- locale parity for new demo.phase1.* keys.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Five modules reachable from dashboard | Pass | DemoPhase1Shell unit + demo e2e |
| Levels list Pre-1/Pre-2/1–17 with rooms | Pass | fixtures + UI |
| Daily quiz 5×1 mark | Pass | in-memory scoring |
| Non-demo shell unchanged | Pass | `hidePrimaryNavigation` only when demoMode |
| No API/provider calls | Pass | demo e2e zero `/api/` |

---

## Professional client setup path (Phase A + Phase B scaffold)

### Scope reviewed

- Phase A client runbook: HTTPS tunnel preference, APK share, disclosure script
  and walkthrough ([CLIENT_DEMO_RUNBOOK.md](CLIENT_DEMO_RUNBOOK.md));
- Phase B outside-repo evidence workspace and env path map
  ([PROFESSIONAL_CLIENT_SETUP.md](PROFESSIONAL_CLIENT_SETUP.md));
- fail-closed `speech:benchmark --phase=preflight` with incomplete evidence;
- live-prepare script refusal without APO authorization file / secret.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Presentation path documented | Pass | HTTPS-first runbook + disclosure + 8-minute walkthrough |
| Secrets stay outside Git | Pass | `$HOME/ge-speech-benchmark` mode 0700/0600; empty secret file |
| Ordinary app remains fail-closed | Pass | docs require `VOICE_PROVIDER_DRIVER=disabled` and live flags false in app `.env` |
| Preflight blocks without evidence | Pass | `scripts/run-speech-benchmark-preflight.sh` → `"status":"blocked"` exit 2 |
| Live prepare blocked without APO text | Pass | `scripts/run-speech-benchmark-live-prepare.sh` exit 2; missing `LIVE_AUTHORIZATION.txt` |
| No Azure network call made | Pass | preflight/live-prepare gates only; no prepare/complete executed |

### Known limitation

Live Azure prepare/complete/`make ai-eval` remain unauthorized until the
operator supplies centralindia endpoint, secret, real evidence JSON and the
exact APO authorization sentence.

---

## APP-0015 / D-0082 — Blanket demo approval and ₹1,000 spend cap

### Scope reviewed

- APO exact statement recorded as [APP-0015](APPROVALS.md#app-0015);
- outside-repo `LIVE_AUTHORIZATION.txt` and `spend-approval.json` (`cap_inr` 1000);
- decision ledger D-0082; STATUS professional-client row updated.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Spend authorization text present | Pass | `$HOME/ge-speech-benchmark/LIVE_AUTHORIZATION.txt` |
| Spend cap file written | Pass | `evidence/spend-approval.json` approved, cap 1000 |
| Production not claimed | Pass | APP-0015 conditions; D-0082 limitations |
| No live Azure call made | Pass | secret file still empty; vendor evidence still templates only |

### Known limitation

Credential, vendor/DPA evidence and filled `speech-benchmark.env` remain
operator-supplied. This approval does not fabricate those artifacts.

---

## Local synthetic presentation demo

### Scope reviewed

- development/distribution-only activation and production refusal;
- in-memory learner identity, lesson, deterministic result/progress and Friend
  adapters;
- explicit synthetic-data and no-real-provider disclosure;
- on-device browser Web Speech as fallback; local Vite demo may use Azure
  neural TTS via `/demo/tts` when credentials are configured (D-0092);
- professional demo-scoped presentation surface;
- microphone-denial fallback and typed practice;
- localization parity/governance for demo speech/UI copy; and
- network, persistence, accessibility and existing-test regression.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Normal runtime remains unchanged | Pass | demo adapters are selected only when the explicit flag and Vite `DEV`/demo mode are true |
| Production cannot activate the demo | Pass | runtime-policy unit test denies an explicit request when `development=false` and `distribution=false` |
| Useful lesson is visible immediately | Pass | demo defaults to Home with a populated introduction mission, model text, browser TTS model speech, recording fallback, check and result |
| Conversation is clearly synthetic | Pass | deterministic Friend disclosure, rotating replies, browser TTS/STT presentation helpers, five status events and zero cost |
| No authoritative data is created | Pass | adapters are browser-memory objects; no database, API mutation, publication, enrollment or mastery authority path exists |
| No ordinary application API call occurs | Pass | Chromium journey observes zero `/api/` requests; Azure TTS (when configured) uses only local `POST /demo/tts` middleware, never ordinary Laravel routes |
| Browser persistence remains bounded | Pass | Chromium journey observes zero `localStorage` entries; demo state resets on reload |
| Accessibility | Pass | complete demo lesson/result/Friend journey has zero automated axe violations |
| Language governance | Pass with expected warnings | six catalogues have exact parity/hashes; Kannada, Malayalam, Tamil, Telugu and Hindi notices remain pending independent review |

### Commands and results

| Command or check | Result |
|---|---|
| `make verify` with the pinned Linux-native toolchain | Pass: Composer/pnpm audits, formatting, PHPStan, 70 SQLite tests/701 assertions, 173 PostgreSQL tests/2,016 assertions, locale/OpenAPI/generated-client checks, lint, TypeScript, 31 localization tests, 9 design-system tests and 72 web tests |
| `make demo-e2e` | Pass: one synthetic journey on temporary `127.0.0.1:4174` including browser TTS playback controls; server stopped |
| `make demo-dist-e2e` | Pass: dist-demo rebuild and Chromium journey on `127.0.0.1:4175` |
| `make demo-android` | Pass: debug APK rebuilt with the updated demo bundle |
| API/provider requests during browser journey | Zero |

### Security, privacy and limitations

- The demo has no real identity, phone number, credential, learner content,
  provider configuration, transcript persistence or tenant data.
- Microphone capture, when a presenter chooses it, stays under the existing
  local browser-memory practice controls and is not uploaded by the demo.
- Model speech and Friend replies use the browser Web Speech APIs when
  available. This is device speech for presentation only, not Azure/cloud
  STT/TTS, not licensed media and not PR-009 acceptance evidence.
- The demo is not evidence that the backend, database, OTP, offline sync,
  secure upload or Azure provider paths are operating. Their existing
  verification and authorization gates remain unchanged.
- Human language, accessibility/device and production reviews remain required.
  PR-009 acceptance, learner data, PR-010 and production remain unauthorized.

## Synthetic demo distribution — web bundle and Android demo APK

### Scope reviewed

- the explicit `demo` build mode boundary against the ordinary production
  build (D-0077);
- demo-runtime enrichment: Home opens on the recommended mission, a demo-only
  Progress destination, rotating deterministic Friend replies and a curated
  set of gentle pattern tips;
- storage-free behaviour of the distribution (no service worker, no
  `localStorage`, no API/provider requests);
- the Capacitor 8 Android wrapper (D-0078) and its permission surface; and
- dependency, lint and regression impact on the existing verified suites.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Ordinary production build cannot activate the demo | Pass | runtime-policy unit test denies `development=false, distribution=false`; configuration test proves production mode builds to `dist/` while only the explicit demo mode builds `dist-demo/` |
| Demo distribution activates only through the dedicated script | Pass | `build:demo` alone sets `VITE_LOCAL_DEMO=true` with `--mode demo`; no `.env` file or default mode carries the flag |
| Built artifact runs the full demo journey | Pass | Chromium journey against `vite preview` of `dist-demo`: Home mission, lesson with microphone-denial fallback, check, result, progress objectives and two Friend turns |
| No storage or network leaves the device | Pass | journey observes zero `/api/` requests, zero `localStorage` entries and zero service-worker registrations |
| Friend replies stay deterministic and advisory | Pass | unit tests pin the four-reply rotation and the four curated pattern tips; replies remain presentation text with no learning-record path |
| Accessibility | Pass | zero automated axe violations across the extended journey |
| Android APK identity and permissions | Pass | `aapt2 dump badging`: `com.globalenglish.demo`, `Global English Demo`, minSdk 24, targetSdk 36, `INTERNET`/`RECORD_AUDIO`/`MODIFY_AUDIO_SETTINGS` only, demo assets bundled |
| Dependency audit | Pass | `uuid@<11.1.1` override patches the moderate advisory in the dev-only Capacitor CLI path; `pnpm audit` reports no known vulnerabilities |
| Existing suites unchanged | Pass | full `make verify`, `make e2e` and `make build` re-run green after the change |

### Commands and results

| Command or check | Result |
|---|---|
| `make verify` with the pinned Linux-native toolchain | Pass: Composer/pnpm audits, formatting, PHPStan, 70 SQLite tests/701 assertions, 173 PostgreSQL tests/2,016 assertions, locale/OpenAPI/generated-client checks, lint, TypeScript, 31 localization tests, 9 design-system tests and 68 web tests |
| `make e2e` | Pass: 10 existing Chromium journeys |
| `make build` | Pass: Laravel production optimize/cleanup and strict production Vite build |
| `make demo-e2e` | Pass: extended synthetic journey on the development server |
| `make demo-dist-e2e` | Pass: the same journey against the built `dist-demo` artifact (three consecutive runs during stabilization) |
| `make demo-android` | Pass: Capacitor sync plus Gradle `assembleDebug` producing `app-debug.apk` (~4.9 MB) with JDK 21, AGP 8.13.0, Gradle 8.14.3 |

### Security, privacy and limitations

- The demo distribution and APK contain no credential, identity, provider
  configuration, tenant data or learner content; recordings stay in volatile
  browser memory for local playback/retry.
- `com.globalenglish.demo` is a placeholder demo identity; the client-owned
  production package identity, Passport PKCE authentication, native secure
  storage and Play release engineering remain PR-014 scope.
- The APK is debug-signed presentation packaging. It has not been executed
  on a device or emulator in this session (no `/dev/kvm` access); an
  on-device pass on representative hardware remains outstanding evidence.
- Gentle pattern tips are scripted demo content and must not be represented
  as grammar analysis, correction authority or AI capability.
- The demo distribution is not curriculum approval, benchmark evidence,
  checkpoint acceptance or production authorization; every existing PR-009
  and later gate is unchanged.


---

## Live Azure complete (APP-0015 / 2026-08-05)

### Scope reviewed

- APO instruction to mark all listener dispositions good;
- two development listener slots recorded 96/96 acceptances;
- `make ai-eval` complete phase executed against centralindia Speech;
- safe aggregate report written outside Git.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Listener all-pass recorded | Pass | aggregate 96/96/0 critical |
| Live complete executed | Pass (ran) | safe-report.json; ~₹13.22 estimated |
| Quality gate | Fail | clean/snr WER above locked thresholds; numbers category ~0.37–0.42 |
| Completeness gate | Fail | snr20/snr10 clip_count 94 (duration mismatch failures: 4) |
| Cost / listener / performance / reliability | Pass | report gates |
| PR-009 acceptance / production | Not authorized | limitations in report |

### Known limitation

Development listeners under APO instruction are not independent language review.
Failing WER does not authorize relaxing locked thresholds without a new decision.


---

## APP-0016 / D-0084 / D-0085 — Production-path authority and PR-009 remediation

### Scope reviewed

- APP-0016 blanket production-path authorization recorded with limitations;
- WER scoring normalization for spoken/digit/currency equivalence (D-0085);
- STT duration over-claim-only check;
- live prepare/review/complete re-run under ₹1,000 cap.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Number-form WER unit cases | Pass | SpeechBenchmarkPolicyTest number equivalence cases |
| Targeted PHPUnit speech suite | Pass | 22 tests |
| Live complete remediation | Pass | `make ai-eval` status passed; run `e1dc9d3c8daff9e33c20bdd00d2dd4d0` |
| All conjunctive gates | Pass | safe-report gates all true; clean WER ~2.6% |
| Cost under cap | Pass | ~₹13.21 estimated; cap ₹1,000 |
| Production/Play claimed | No | STATUS limitations; APP-0016 conditions |

### Known limitation

Formal APO PR-009 checkpoint acceptance and PR-010 authorization remain separate
human steps. Independent specialist reviews remain mandatory before production.


---

## APP-0017 — PR-009 acceptance and PR-010 authorization

### Scope reviewed

- APO exact statement accepting PR-009 and authorizing PR-010;
- prior live synthetic benchmark pass `e1dc9d3c8daff9e33c20bdd00d2dd4d0`;
- STATUS/DECISIONS updated to open only PR-010.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Exact statement recorded | Pass | [APP-0017](APPROVALS.md#app-0017) |
| PR-009 closed with limitations | Pass | APP-0017 conditions; D-0086 |
| Only PR-010 authorized | Pass | STATUS next checkpoint; no PR-011/production |
| Production/Play claimed | No | APP-0017 limitations |

### Known limitation

PR-009 remediation and related demo-path files may still be uncommitted at
record authoring; the evidence repository revision is the commit that contains
this APP-0017 record.


---

## PR-010 — AI orchestration, correction and Tutor beta

### Scope reviewed

- context allowlist, versioned prompts, structured validators, curated fallback;
- Gentle/Tutor/Fluency modes;
- separate priority correction API field and UI card with Kannada explanation;
- advisory correction digests and error-pattern counters;
- learning-record isolation tests;
- OpenAPI/contract regeneration and localization catalogue updates.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Output validator unit tests | Pass | ConversationOutputValidatorTest |
| Request validation accepts tutor mode | Pass | ConversationRequestValidationTest |
| PostgreSQL conversation + orchestration suite | Pass | 6 tests / 70 assertions via Docker PHP 8.4 + pdo_pgsql |
| SQLite unit/feature conversation filter | Pass | 5 tests / 17 assertions |
| OpenAPI lint + contract drift check | Pass | `pnpm run openapi:check` |
| PHPStan on PR-010 conversation paths | Pass | 0 errors |
| Web conversation/demo tests | Pass | 76 web tests in targeted vitest run |
| Locale governance | Pass | `pnpm run locale:check` (5 pending-review warnings) |
| Learning records unchanged by AI path | Pass | orchestration + fake-provider isolation assertions |
| Correction separate from Friend reply | Pass | API field + UI card test |
| Real model provider / production / Play | Not authorized | APP-0017; STATUS |

### Security, privacy and tenancy

- Persistent correction records store digests, not reply/correction text.
- Allowlisted model context excludes personal identifiers and learning authority.
- Organization scoping of correction/error-pattern rows follows conversation turn ownership.
- Fake adapters remain local/testing-only; production conversation providers stay fail-closed.

### Known limitations

- Host PHP lacks `pdo_pgsql`; PostgreSQL integration evidence used a Docker
  `php:8.4-cli` container against the local Compose Postgres service.
- Full `make verify` / remote CI was not completed in this session.
- No real LLM provider is wired; Tutor beta uses deterministic fake outputs.
- Kannada and preview-locale UI copy remain pending independent review.


---

## APP-0018 — PR-010 acceptance

### Scope reviewed

- APO statement accepting PR-010 with recorded limitations;
- exact implementation commit `13eb5ac56f04402a2571346e9fd8e24f1a05ea80`;
- STATUS/DECISIONS updated without authorizing PR-011.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Exact statement recorded | Pass | [APP-0018](APPROVALS.md#app-0018) |
| Exact commit cited | Pass | `13eb5ac56f04402a2571346e9fd8e24f1a05ea80` |
| PR-010 closed with limitations | Pass | APP-0018 conditions; D-0088 |
| PR-011 authorized | No | APP-0018 limitations; STATUS next checkpoint |
| Production/Play claimed | No | APP-0018 limitations |

### Known limitation

This acceptance does not waive independent specialist reviews or full
CI/`make verify` follow-up.


---

## APP-0019 — PR-011 implementation authorization

### Scope reviewed

- APO statement `APO accept PR-011.`;
- interpretation as authorization to begin unimplemented PR-011 only;
- STATUS/DECISIONS updated without accepting completed PR-011 work.

### Review result

| Check | Result | Evidence |
|---|---|---|
| Exact statement recorded | Pass | [APP-0019](APPROVALS.md#app-0019) |
| Interpreted as begin-implementation only | Pass | APP-0019 context; no PR-011 artifact exists |
| Only PR-011 authorized | Pass | STATUS next checkpoint; D-0089 |
| PR-012 / production / Play claimed | No | APP-0019 limitations |

### Known limitation

PR-011 deliverables remain unimplemented until a bounded checkpoint
implementation is completed and separately accepted.


---

## PR-011 — Evaluation harness and memory controls

### Scope reviewed

- learner-visible AI memory list/create/edit/delete + audit;
- allowlisted memory in conversation orchestration;
- locked deterministic AI evaluation suite, human-review export/import with
  fail-closed suite binding, prompt comparison and cost rollup;
- OpenAPI 0.8.0 memory routes and Practice UI panel;
- [PR-011 verification scope](PR-011_EVALUATION_MEMORY_VERIFICATION_SCOPE.md).

### Review result

| Check | Result | Evidence |
|---|---|---|
| Memory isolation / learning freeze | Pass | LearnerMemoryTest (PostgreSQL via Docker PHP 8.4.24 + pdo_pgsql) |
| AI evaluation suite pass | Pass | AiEvaluationRunnerTest |
| Human-review import accept/pending/reject + fail-closed mismatch | Pass | AiEvaluationRunnerTest import cases |
| Suite hash stability | Pass | AiEvaluationSuiteTest (host `php artisan test`) |
| HTTP auth + orchestration + memory/eval filter | Pass | 13 PostgreSQL tests / 204 assertions via Docker PHP 8.4.24 (`AiEvaluationRunnerTest\|LearnerMemoryTest\|ConversationOrchestrationTest\|LearnerHttpBoundaryTest`) |
| OpenAPI / contracts | Pass | `pnpm run openapi:check` on host Node v22.22.1 / pnpm 11.18.0 |
| PHPStan PR-011 paths | Pass | 0 errors (`app/Conversation/Evaluation`, `Memory`, `LearnerMemoryController`) |
| Web memory panel list/create/edit/delete | Pass | AiMemoryPanel vitest 1 passed |
| Locale governance | Pass | `node scripts/validate-locales.mjs` in `node:24.18.1-bookworm-slim` — 6 catalogues, 5 pending-review warnings |
| `php artisan ai:evaluate` | Pass | Docker PHP 8.4.24 against `global_english_test` after migrate/seed: status `completed`, 4 passed / 0 failed, suite_hash `9d1f8d6069af09abbc9c20dd5048af6c640a4c77c927f0f43eed3364c13dc5e6`, report_digest `407d67cc39091c5703a291c042da3ba7bb14b1f1f67f236b70a305ab883cd318`, estimated_cost_microunits 0; speech half skipped (no `SPEECH_BENCHMARK_RUN_ID`) |
| Full host `make verify` / remote CI | Not claimed | Host PHP 8.5.4 lacks `pdo_pgsql`; Node 22 cannot load locale `.ts` imports without pinned Node 24 |
| Real LLM / PR-012 / production / Play | Not authorized | APP-0019; STATUS |

### Subsequent checkpoint acceptance

[APP-0020](APPROVALS.md#app-0020) records Accountable Product Owner acceptance
of PR-011 at exact commit `220628f4d4c69284af0b1f2534e8902f3b272088` with
every recorded limitation in this section and
[PR-011_APO_ACCEPTANCE_PACKAGE.md](PR-011_APO_ACCEPTANCE_PACKAGE.md). PR-012
implementation remains unauthorized until a separate APP.

### Security, privacy and tenancy

- Memory list returns all active facts for the owning membership; no hidden active facts.
- Practice UI edit uses the existing PATCH contract; no hidden active facts.
- Audit events store digests, not fact plaintext after delete/update trails.
- Eval fixtures are synthetic; reports are aggregate digests with zero fake cost.
- Import packages must match the exact suite key and hash; unknown cases,
  missing files and malformed dispositions fail closed.
- Organization + membership FKs and same-tenant denial mirror conversation patterns.

### Known limitations

- Host PHP 8.5.4 lacks `pdo_pgsql`; PostgreSQL evidence used Docker PHP 8.4.24
  against local Compose Postgres.
- Host Node v22.22.1 cannot load locale `.ts` imports; locale governance was
  verified in `node:24.18.1-bookworm-slim`.
- Full host `make verify` / remote CI were not claimed green in this session.
- Preview-locale memory strings may reuse English pending independent review.
- Eval cost rollup remains zero on deterministic fakes and is not live spend.
- Speech half of `make ai-eval` remains PR-009-gated and was not run.
- PR-011 is accepted under APP-0020 at
  `220628f4d4c69284af0b1f2534e8902f3b272088`; PR-012 remains unauthorized.

## APP-0021 — Confidence Conversation enterprise slice authorization

### Evidence reviewed

- Exact APO statement authorizing only
  [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) Steps A1–A7;
- Prior PR-011 acceptance under APP-0020;
- Learning-flow design
  [STUDENT_AI_CONVERSATION_LEARNING_FLOW.md](STUDENT_AI_CONVERSATION_LEARNING_FLOW.md);
- Explicit non-authorization of PR-012, production, Play, and nested A4/A5
  spend until separate gates;
- Explicit statement that demo D-0092/D-0094 paths are not enterprise evidence.

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Exact APO authorize sentence recorded | Pass | [APP-0021](APPROVALS.md#app-0021) |
| Begin-implementation only (not A7 accept) | Pass | APP-0021 conditions |
| PR-012 / production / Play still unauthorized | Pass | APP-0021; STATUS next gate |
| Demo paths non-evidence | Pass | D-0095; APP-0021; plan §0 |
| Nested A4/A5 still gated | Pass | APP-0021 authorized scope |

### Outcome

A0 is complete. Implementation may proceed through A1 (spec + verification
scope) under APP-0021 without treating demo adapters as production evidence.

## Confidence Conversation — Step A1 (spec + verification scope)

### Evidence reviewed

- [CONFIDENCE_CONVERSATION_SPEC.md](CONFIDENCE_CONVERSATION_SPEC.md)
- [CONFIDENCE_CONVERSATION_VERIFICATION_SCOPE.md](CONFIDENCE_CONVERSATION_VERIFICATION_SCOPE.md)
- Existing PR-010 `PriorityCorrection` still uses `explanation_kn` only —
  A2 must generalize to `explanation_l1` for all UI locales
- OpenAPI draft deltas listed in verification scope (not yet generated)

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Spec committed under APP-0021 | Pass | SPEC file |
| Verification scope committed | Pass | VERIFICATION_SCOPE file |
| Demo excluded as evidence | Pass | verification §10 |
| Nested A4/A5 still gated | Pass | SPEC §8; APP-0021 |
| Plan Active step → A2 | Pass | ENTERPRISE_STUDENT_PATH_PLAN |

### Outcome

A1 complete. Next: A2 structured turn contract on fakes (no real LLM).
## Confidence Conversation — Step A2 (structured turn contract)

### Evidence reviewed

- `PriorityCorrection` now emits `needed`, `explanation_l1`, `explanation_locale`;
  deprecated `explanation_kn` dual-write only for `kn-IN`
- Session start requires `ui_locale`; persisted on `conversation_sessions`
- Fake provider fixtures for all six UI locales; shame-fixture → curated fallback
- OpenAPI 0.9.0 + regenerated contracts
- Migration `2026_08_07_000040_confidence_conversation_explanation_l1.php`

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| CC-03 empty L1 rejected when needed | Pass | ConversationOutputValidatorTest |
| CC-04 six UI locale fixtures | Pass | ConversationOutputValidatorTest::test_fake_provider_covers_all_ui_locales |
| Shame-language fail-closed | Pass | Validator + shame-fixture fake |
| OpenAPI / contracts | Pass | `pnpm run openapi:check` |
| PHPStan changed paths | Pass | 0 errors |
| PostgreSQL integration filter | Pass | Docker PHP 8.4.24: 19 tests / 281 assertions |
| Host unit/feature conversation | Pass | 6 tests / 55 assertions |
| Web conversation + demo fixtures | Pass | ConversationJourney / Web API / localDemo vitest |
| Real LLM / A4–A5 / PR-012 / production | Not authorized | APP-0021; D-0096 |

### Known limitations

- Prompt catalogue text still mentions “Kannada explanation” (eval suite hash
  unchanged); A6 may revise prompts with locked suite update.
- Host PHP lacks `pdo_pgsql`; PostgreSQL evidence used Docker PHP 8.4.24.
- Full host `make verify` / remote CI not claimed in this step.
- A3 UI stages (warm-up / listen / reflect) not yet implemented.

### Outcome

A2 complete. Next: A3 product UI vertical slice on fakes.

## Confidence Conversation — Step A3 (product UI vertical slice)

### Evidence reviewed

- Practice `ConversationJourney` stages: Warm-up → Listen → Talk → Reflect
- Confidence controls: retry, type instead, skip, hint; silence nudge after 8s
- Correction card uses `explanation_l1` / `explanation_locale`
- UI catalogues revision `ui-*-2026-08-07.9` with confidence copy
- Product Listen path discloses text-only fake TTS (A5 still gated)

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Stage journey unit coverage | Pass | ConversationJourney vitest 3 passed |
| Confidence controls present | Pass | Say that again / Type / Skip / Hint |
| Locale governance | Pass | validate-locales: 6 catalogues, 5 pending-review warnings |
| Demo excluded as enterprise evidence | Pass | D-0097; demoMode TTS optional only |
| Real LLM / A4–A5 / PR-012 / production | Not authorized | APP-0021 |

### Known limitations

- Listen model lines are synthetic client constants (not published curriculum).
- Product-path model/reply audio remains text-only until A5.
- Reflect summary is advisory client copy; does not write mastery.
- Full `make e2e` / host `make verify` not claimed in this step.

### Outcome

A3 complete. Next: A4 conversation-model provider ADR (no live DI without nested APP).

## Confidence Conversation — Step A4 (ADR proposed)

### Evidence reviewed

- Proposed [ADR-0010](adr/0010-conversation-model-provider-policy.md)
- Authorization draft [A4_CONVERSATION_MODEL_AUTHORIZATION_REQUEST.md](A4_CONVERSATION_MODEL_AUTHORIZATION_REQUEST.md)
- Explicit separation from demo OpenRouter (D-0094) and Speech ADR-0008
- Fail-closed production default retained

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| ADR drafted for provider choice + gates | Pass | ADR-0010 proposed |
| Nested APP required before adapter code | Pass | A4 auth request |
| Live spend unauthorized | Pass | ADR + A4 request non-goals |
| Demo path non-evidence | Pass | D-0094; ADR alternatives |

### Outcome

A4 ADR proposed. Adapter implementation blocked until ADR acceptance and nested
APO authorize statement are recorded.

## Confidence Conversation — Step A4 (accepted + adapter)

### Evidence reviewed

- Exact ADR acceptance: `Accept ADR-0010 conversation-model provider policy as proposed.`
- APO live-spend intent: production closeness with live spends
- [APP-0022](APPROVALS.md#app-0022) (₹500 INR hard development ceiling)
- Azure OpenAI adapter behind `ConversationModelProvider` with HTTP-fake tests
- Outside-repo workspace scripts under `scripts/setup-conversation-model-workspace.sh`
- Production / non-allowlisted env remain fail-closed

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| ADR-0010 accepted | Pass | APP-0022 |
| Adapter uses project-owned port only | Pass | `AzureOpenAiConversationModelProvider` |
| HTTP fakes map to `explanation_l1` | Pass | `AzureOpenAiConversationAdapterTest` |
| Production cannot authorize live | Pass | `environment_not_allowlisted` |
| Spend capped; unlimited unauthorized | Pass | APP-0022; config `maximum_spend_inr=500` |
| Demo OpenRouter not DI | Pass | D-0094; driver allowlist |
| Production promote / Play | Not authorized | APP-0022 non-goals |
| Operator live smoke | Pending evidence | Outside-repo workspace |

### Outcome

A4 complete for code + governance. Next: A5 multi-locale guidance TTS policy
(nested ADR/APP; no TTS spend under APP-0022).

## Confidence Conversation — Step A5 (in progress)

### Evidence reviewed

- Proposed [ADR-0011](adr/0011-confidence-conversation-guidance-tts-policy.md)
- [APP-0023](APPROVALS.md#app-0023) begin-implementation (fakes / text-first)
- [A5_GUIDANCE_TTS_AUTHORIZATION_REQUEST.md](A5_GUIDANCE_TTS_AUTHORIZATION_REQUEST.md)
- `GuidanceTtsPolicy` + web `guidanceTtsPolicy` voice map
- Product Listen/reply text-first with optional on-device English browser speech
- Locale catalogues bumped to `ui-*-2026-08-07.10`
- APP-0022 spend ceiling amended to ₹500

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| ADR-0011 drafted | Pass | proposed |
| APO begin A5 (fakes/text-first) | Pass | APP-0023 |
| Human lesson recordings forbidden | Pass | GuidanceTtsPolicy |
| Text-first fallback required | Pass | policy + UI copy |
| PR-009 en-IN benchmark unchanged | Pass | ADR-0011 decision table |
| Live multi-locale TTS spend | Not authorized | APP-0023 / ADR-0011 |
| ADR-0011 accepted | Pending | needs exact Accept statement |

### Outcome

A5 scaffolding in progress. Close A5 only after exact ADR-0011 acceptance.

## Confidence Conversation — Step A5 (accepted)

### Evidence reviewed

- Exact ADR acceptance: `Accept ADR-0011 Confidence Conversation guidance TTS policy as proposed.`
- Exact APO authorize: fakes and text-first fallback; live multi-locale TTS spend unauthorized
- [APP-0024](APPROVALS.md#app-0024)
- Prior scaffolding under [APP-0023](APPROVALS.md#app-0023)
- `GuidanceTtsPolicy` + fake TTS contract + product text-first UI

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| ADR-0011 accepted | Pass | APP-0024 |
| AI-only / no human lesson recordings | Pass | GuidanceTtsPolicy; ADR-0011 |
| Text-first fallback | Pass | UI + policy |
| Fakes only for multi-locale guidance | Pass | APP-0024 |
| PR-009 en-IN benchmark unchanged | Pass | ADR-0011 |
| Live multi-locale TTS spend | Not authorized | APP-0024 non-goals |
| Production / Play | Not authorized | APP-0024 |

### Outcome

A5 complete. Next: A6 locale eval fixtures + `make ai-eval` gates for L1
explanations.

## Confidence Conversation — Step A6 (accepted)

### Evidence reviewed

- Suite `friend.l1.locale.v1` / version `1`
- Locked `suite_hash` `5bf9a508d1aeaf3a329212e60cf957cc28404fabcf0d34f4f65ebb6e99223270`
- 15 cases: gentle × 6 UI locales, tutor/fluency/unsafe, shame × 6 locales
- Metrics: false_correction_count, missing_l1_count, shame_language_hits,
  schema_invalid_count, cost_ceiling_ok
- Human-review export includes `ui_locale` + `explanation_locale`
- Docker PHP 8.4.24 `AiEvaluationRunnerTest` 6/6; `ai:evaluate` completed
  15/0, report_digest `355b4d0424fab059209d97fdd863e738a703e9a1bd6437069981fdadc42b84ca`

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| CC-E1 locked suite hash; fake-only | Pass | suite_hash; APP_ENV/driver gate |
| CC-E2 false-correction / missing-L1 fail suite | Pass | metrics + case gates |
| CC-E3 shame-language fail closed | Pass | 6 shame cases → fallback |
| CC-E4 cost zero on fakes | Pass | estimated_cost_microunits 0 |
| CC-E5 make ai-eval fails on regression | Pass | status failed if any gate fails |
| All Phase 1 UI locales covered | Pass | ui_locales === ConversationUiLocales::all() |
| Live multi-locale TTS spend | Not authorized | APP-0024 |
| Live model eval | APP-0022 only | spend/evidence gates |

### Outcome

A6 complete. Next: A7 APO exact-commit acceptance of the Confidence Conversation
slice.

## Confidence Conversation — Step A7 (accepted)

### Evidence reviewed

- Exact APO statement: `APO accept Confidence Conversation slice at 5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b with its recorded limitations`
- [APP-0025](APPROVALS.md#app-0025) / D-0103
- [CONFIDENCE_CONVERSATION_APO_ACCEPTANCE_PACKAGE.md](CONFIDENCE_CONVERSATION_APO_ACCEPTANCE_PACKAGE.md)
- A1–A6 QUALITY_REVIEW sections; APP-0021 / APP-0022 / APP-0024; ADR-0010 / ADR-0011

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Acceptance package | Pass | CONFIDENCE_CONVERSATION_APO_ACCEPTANCE_PACKAGE.md |
| Limitations explicit | Pass | package § Recorded limitations |
| Exact commit SHA | Pass | `5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b` |
| APO accept statement | Pass | APP-0025 |
| PR-012 / production / Play | Not authorized | APP-0025 non-goals |
| Live multi-locale TTS spend | Not authorized | APP-0024 / APP-0025 |

### Outcome

A7 complete. Confidence Conversation enterprise slice accepted. Next: separate
APP for PR-012 Role-play (plan B0) only — do not start PR-012 code without it.

## PR-012 — Step B0 (authorization request ready)

### Evidence reviewed

- [PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md](PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md)
- [PR-012_ROLEPLAY_VERIFICATION_SCOPE.md](PR-012_ROLEPLAY_VERIFICATION_SCOPE.md)
- Preconditions: APP-0020 (PR-011) + APP-0025 (Confidence Conversation)
- Scope: synthetic Observe→Rehearse→Perform→Reflect; advisory objectives;
  fake providers default; no live TTS spend expansion

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Auth request refreshed post–APP-0025 | Pass | PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md |
| Verification scope RP-01–RP-08 | Pass | PR-012_ROLEPLAY_VERIFICATION_SCOPE.md |
| APO begin-implementation statement | Pending | exact authorize text in auth request |
| PR-012 code | Not authorized | awaiting APP |

### Outcome

B0 package ready. Record a new APP only after the exact APO authorize
statement; then advance Active step to B1 and begin synthetic implementation.

## PR-012 — Step B0 (accepted) / B1 (in progress)

### Evidence reviewed

- Exact APO statement authorizing PR-012 bounded synthetic role-play
- [APP-0026](APPROVALS.md#app-0026) / D-0104
- [PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md](PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md)
- [PR-012_ROLEPLAY_VERIFICATION_SCOPE.md](PR-012_ROLEPLAY_VERIFICATION_SCOPE.md)

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| APO begin-implementation | Pass | APP-0026 |
| Synthetic-only scope | Pass | APP-0026 authorized scope |
| Approved curriculum / live TTS expansion | Not authorized | APP-0026 non-goals |
| B1 implementation | In progress | plan Active step B1 |

### Outcome

B0 closed. Implement B1 synthetic Observe→Rehearse→Perform→Reflect slice;
exact-commit acceptance remains a later APO gate.

### B1 progress note (implementation landed; verification pending)

Landed under APP-0026 authorized synthetic scope:

- Migration `2026_08_07_000050_pr012_synthetic_roleplay.php` widens friend/roleplay
  CHECKs and adds `scenario_objectives`, append-only
  `conversation_session_stage_events`, and advisory `roleplay_feedback_evidence`.
- `RoleplayStages` + `RoleplayStageService` linear Observe→Rehearse→Perform→Reflect;
  Reflect writes digests only (no mastery).
- `ConversationSessionService` resolves friend/roleplay prototype pairs; roleplay
  starts at Observe with objectives/feedback payload fields.
- HTTP `POST /conversation-sessions/{session}/stages`, OpenAPI 0.10.0 + regenerated
  clients, FoundationSeeder clinic-reception synthetic prototype (2 objectives).
- Practice UI `RoleplayJourney` entry ("Role-play (synthetic)") beside Confidence.

Focused verification passed for this slice:

- PHPUnit unit/feature (sqlite): `RoleplayStagesTest`,
  `ConversationRequestValidationTest` stage cases — pass
- PHPUnit integration (postgres via php:8.4-cli + pdo_pgsql):
  `RoleplaySessionTest`, `FoundationSeederIntegrityTest` — pass
- Vitest (Node 24): `RoleplayJourney.test.tsx` — pass
- `pnpm run openapi:check` and `pnpm run locale:check` — pass

B1 remains open for full `make verify` / APO exact-commit acceptance; production
and Play stay unauthorized.

### B1 verification re-run + B2 + acceptance package

#### Evidence reviewed

- Host PHPUnit: `RoleplayStagesTest` + stage validation — 5 passed
- Docker PHP 8.4.24 + pdo_pgsql: `RoleplaySessionTest` +
  `FoundationSeederIntegrityTest` — 6 passed / 42 assertions
- Node 24 Docker: locale check pass; OpenAPI generate `--check` pass;
  Vitest RoleplayJourney + ConversationJourney — 4 passed
- Chromium Role-play e2e: spec added; webServer `tsc -b` blocked by
  pre-existing unrelated typecheck errors — **not claimed**
- B2 content path: D-0105 synthetic retained for APP-0026
- [PR-012_APO_ACCEPTANCE_PACKAGE.md](PR-012_APO_ACCEPTANCE_PACKAGE.md)

#### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| RP-01 stage transitions | Pass | RoleplaySessionTest |
| RP-02 persona/scenario snapshot | Pass | session start assertions |
| RP-03 no mastery writes | Pass | learningCounts unchanged |
| RP-04 advisory feedback evidence | Pass | roleplay_feedback_evidence digests |
| RP-05 cross-tenant denial | Pass | RoleplaySessionTest |
| RP-06 OpenAPI/clients | Pass | 0.10.0 + generate --check |
| RP-07 UI journey | Pass (unit) | RoleplayJourney vitest; Chromium not claimed |
| RP-08 make verify | Not claimed | host/CI limitations |
| B2 synthetic content path | Pass | D-0105 |
| Exact commit / APO accept | Pass | [APP-0027](APPROVALS.md#app-0027) at `0fb6f2953580ed6154e4d895e128c46167c6f5d4` |

#### Outcome

B1–B2 synthetic scope ready for Exact-commit APO acceptance after an authorized
commit. PR-013 remains unauthorized.

## PR-012 — Accepted (APP-0027)

### Evidence reviewed

- Exact APO statement accepting `0fb6f2953580ed6154e4d895e128c46167c6f5d4`
- [APP-0027](APPROVALS.md#app-0027) / D-0106
- [PR-012_APO_ACCEPTANCE_PACKAGE.md](PR-012_APO_ACCEPTANCE_PACKAGE.md)

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Exact-commit APO accept | Pass | APP-0027 |
| Recorded limitations retained | Pass | acceptance package |
| Approved curriculum / live TTS expansion | Not authorized | APP-0027 non-goals |
| PR-013 / production / Play | Not authorized | APP-0027 |

### Outcome

PR-012 synthetic Role-play accepted. Next: separate APP for PR-013 (C0) only.

## PR-013 — Step C0 (authorization request ready)

### Evidence reviewed

- [PR-013_OPERATOR_AUTHORIZATION_REQUEST.md](PR-013_OPERATOR_AUTHORIZATION_REQUEST.md)
- [PR-013_OPERATOR_VERIFICATION_SCOPE.md](PR-013_OPERATOR_VERIFICATION_SCOPE.md)
- Preconditions: APP-0027 (PR-012); existing curriculum commands + audit write path
- First APP scope: content review→publish + audit list; no DB access for operators
- Deferred: sampled speaking review (ADR-0007), privacy ops, CSV PII export,
  full studio, production/Play

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Auth request drafted post–APP-0027 | Pass | PR-013_OPERATOR_AUTHORIZATION_REQUEST.md |
| Verification scope OC-01–OC-08 | Pass | PR-013_OPERATOR_VERIFICATION_SCOPE.md |
| ADR-0007 sampled review deferred | Pass | auth request non-goals |
| APO begin-implementation statement | Pass | APP-0028 |
| PR-013 code | Authorized bounded slice | APP-0028 |

### Outcome

C0 closed by APP-0028. Proceed to C1 implementation.

## PR-013 — Step C1 (content-workflow + audit in progress)

### Evidence reviewed

- APP-0028 bounded operator content-workflow + audit authorization
- Seeded Direct org role packs: `trainer`, `organization_admin`, `content_reviewer`
- New permission `audit.read` for org-scoped audit digests
- Operator HTTP APIs wrapping ContentReviewCommands / CurriculumPublisher /
  CurriculumRollbackService plus draft/publication/audit list queries
- Ordinary-product `OperatorConsole` (not Vite `/demo/*`)
- OpenAPI bumped to **0.11.0**

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| OC-01 review→publish without DB access | Pass (focused) | OperatorContentWorkflowTest HTTP review→publish→rollback |
| OC-02 privileged actions write audit events | Pass (focused) | content_review.recorded + publication.activated present |
| OC-03 org-scoped audit list (digests only) | Pass (focused) | GET /operator/audit-events; no payload/transcript fields |
| OC-04 cross-tenant denial | Pass (focused) | foreign revision/version IDs → 404 |
| OC-05 missing permission fail-closed | Pass (focused) | membership without grants → 404 |
| OC-06 OpenAPI/client drift | Pass (focused) | OpenAPI 0.11.0; `pnpm run openapi:check` |
| OC-07 operator UI unit smoke | Pass (focused) | OperatorConsole.test.tsx; Chromium e2e not claimed |
| OC-08 make verify | Not claimed | focused Docker PHPUnit / Node 24 only |
| ADR-0007 no audio/transcript operator access | Pass | audit digests + console notice; no voice routes |

### Focused verification recorded this slice

| Area | Result |
|---|---|
| PHPUnit feature (host) | `OperatorRequestValidationTest` — 2 passed |
| PHPUnit integration (Docker PHP 8.4.24 + pdo_pgsql) | `OperatorContentWorkflowTest` + `FoundationSeederIntegrityTest` — 5 passed / 104 assertions |
| Vitest (Node 24 Docker) | `OperatorConsole.test.tsx` — 1 passed |
| Locale validation (Node 24) | Pass — 6 catalogues, 5 pending-review warnings |
| OpenAPI contract generate `--check` (Node 24) | Pass — contracts match OpenAPI 0.11.0 |
| Host `make verify` / Chromium e2e / remote CI | **Not claimed** |

### Outcome

C1 bounded operator content-workflow + audit slice implemented under APP-0028
with focused verification green. Sampled speaking review, privacy ops,
production and Play remain unauthorized. APO exact-commit acceptance package
drafted for Exact commit `9220ab27baa6dc29d82caf8e9e97e94f7df7219d`
([PR-013_APO_ACCEPTANCE_PACKAGE.md](PR-013_APO_ACCEPTANCE_PACKAGE.md)).

## PR-013 — Acceptance package drafted

### Evidence reviewed

- [PR-013_APO_ACCEPTANCE_PACKAGE.md](PR-013_APO_ACCEPTANCE_PACKAGE.md)
- Exact commit `9220ab27baa6dc29d82caf8e9e97e94f7df7219d`
- C1 QUALITY_REVIEW focused evidence; OC-01–OC-07 pass; OC-08 not claimed

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Acceptance package drafted | Pass | PR-013_APO_ACCEPTANCE_PACKAGE.md |
| Limitations explicit | Pass | package § Recorded limitations |
| Exact commit SHA | Pass | `9220ab27baa6dc29d82caf8e9e97e94f7df7219d` |
| APO accept statement | Pass | [APP-0029](APPROVALS.md#app-0029) |
| Sampled review / privacy ops / PR-014+ | Not authorized | APP-0029 non-goals |

### Outcome

PR-013 bounded operator content-workflow + audit **accepted** under APP-0029 at
`9220ab27baa6dc29d82caf8e9e97e94f7df7219d`. Next: separate APP for PR-014
and/or remaining PR-013 items only.

## PR-014 — Step D0 (authorization request ready)

### Evidence reviewed

- [PR-014_ANDROID_AUTHORIZATION_REQUEST.md](PR-014_ANDROID_AUTHORIZATION_REQUEST.md)
- [PR-014_ANDROID_VERIFICATION_SCOPE.md](PR-014_ANDROID_VERIFICATION_SCOPE.md)
- Preconditions: APP-0029; Passport PKCE server; Android adapter interface;
  demo Capacitor APK remains D-0078 presentation-only
- First APP scope: production-path closed-beta foundations (separate package,
  system-browser PKCE, reviewed native secure storage, device matrix,
  reproducible signed closed-beta artifact)
- Deferred: Play public release, PR-015, remaining PR-013 sampled review /
  privacy ops, production OTP cutover

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Auth request drafted post–APP-0029 | Pass | PR-014_ANDROID_AUTHORIZATION_REQUEST.md |
| Verification scope AN-01–AN-10 | Pass | PR-014_ANDROID_VERIFICATION_SCOPE.md |
| Demo APK kept non-production | Pass | D-0078; auth request non-goals |
| APO begin-implementation statement | Pass | [APP-0030](APPROVALS.md#app-0030) |
| PR-014 code | Authorized (begin) | APP-0030; Active = D1 |

### Outcome

D0 complete under APP-0030. Active step D1 implements closed-beta foundations.

## PR-014 — Step D1 (closed-beta foundations in progress)

### Evidence reviewed

- Separate production Capacitor tree `apps/web/android-app`, package
  `com.globalenglish.app` (demo `apps/web/android` / `com.globalenglish.demo`
  unchanged)
- Dual `capacitor.config.ts` via `CAPACITOR_APP=production`
- `AndroidPkceAuthenticationAdapter` wired from `defaultAuthentication` on
  native Android when `VITE_MOBILE_API_ORIGIN` is set
- Reviewed secure store: project-owned EncryptedSharedPreferences plugin
  ([ADR-0012](adr/0012-android-native-secure-credential-store.md) / D-0110)
- System browser Custom Tabs plugin + deep-link
  `com.globalenglish.app:/oauth/callback`
- [PR-014_ANDROID_DEVICE_MATRIX.md](PR-014_ANDROID_DEVICE_MATRIX.md)
- [PR-014_ANDROID_CLOSED_BETA_BUILD.md](PR-014_ANDROID_CLOSED_BETA_BUILD.md)
- `make android-closed-beta` (demo target retained)

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| AN-01 separate package ID | Pass (static) | android-app applicationId / ADR docs |
| AN-02 ordinary product `dist` | Pass (static) | CAPACITOR_APP production webDir `dist` |
| AN-03 PKCE + deep link wiring | Pass (unit) | adapter + bindAndroidPkceDeepLink tests |
| AN-04 reviewed secure store | Pass (unit + ADR) | KeystoreSecureCredentialStore; ADR-0012 |
| AN-05 logout clears + revoke path | Pass (existing adapter tests) | AndroidPkceAuthenticationAdapter.test |
| AN-06 no WebView/Preferences creds | Partial | unit proves secure-plugin-only writes; on-device matrix pending |
| AN-07 device matrix doc | Pass (doc) | PR-014_ANDROID_DEVICE_MATRIX.md; physical runs pending |
| AN-08 permission/lifecycle notes | Pass (doc) | matrix journeys; on-device pending |
| AN-09 reproducible signed artifact steps | Pass (doc) | closed-beta build doc; debug keystore limitation |
| AN-10 make verify / Android build evidence | Pending | focused vitest only; full verify / Gradle APK not claimed |
| Play public release | Not authorized | APP-0030 |

### Focused tests run (this D1 slice)

```text
vitest run src/auth/AndroidPkceAuthenticationAdapter.test.ts
  src/auth/KeystoreSecureCredentialStore.test.ts
  src/auth/defaultAuthentication.test.ts
  src/auth/createInstallationUlid.test.ts
  src/auth/bindAndroidPkceDeepLink.test.ts
  src/auth/androidPkceEnvironment.test.ts
  src/auth/capacitorAndroidConfig.test.ts
→ 7 files / 15 tests passed
```

`make verify`, `make android-closed-beta` Gradle assemble, physical device matrix
and Play upload were **not** run/claimed in this session.

### Outcome

D1 foundations landed for APO review after focused tests and documented
remaining on-device gaps. Play public release and PR-015 remain unauthorized.

## PR-014 — Step D1 acceptance package (awaiting APO)

### Evidence reviewed

- Exact implementation commit `44b0e672e004cf3846cddabc456c21153fa4607e`
- [PR-014_APO_ACCEPTANCE_PACKAGE.md](PR-014_APO_ACCEPTANCE_PACKAGE.md)
- QUALITY_REVIEW D0/D1 gate tables (AN-06 partial; AN-10 / physical matrix /
  Gradle assemble / `make verify` not claimed)

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Acceptance package drafted | Pass | PR-014_APO_ACCEPTANCE_PACKAGE.md |
| Exact commit SHA filled | Pass | `44b0e672e004cf3846cddabc456c21153fa4607e` |
| Limitations recorded (Play / matrix / verify) | Pass | acceptance package |
| APO accept statement | Pass | [APP-0031](APPROVALS.md#app-0031) |
| Play public release / PR-015 | Not authorized | APP-0031 non-goals |

### Outcome

PR-014 closed-beta foundations **accepted** under APP-0031 / D-0111 at
`44b0e672e004cf3846cddabc456c21153fa4607e`. Next: separate APP for E0
(PR-015) and/or on-device matrix / remaining PR-013 only.

## PR-015 — Step E0 (authorization request ready)

### Evidence reviewed

- [PR-015_PRODUCTION_HARDENING_AUTHORIZATION_REQUEST.md](PR-015_PRODUCTION_HARDENING_AUTHORIZATION_REQUEST.md)
- [PR-015_PRODUCTION_HARDENING_VERIFICATION_SCOPE.md](PR-015_PRODUCTION_HARDENING_VERIFICATION_SCOPE.md)
- Preconditions: APP-0031 (PR-014 foundations + unclaimed matrix/`make verify`);
  APP-0029 bounded operator; prior learner slices
- First APP scope: close APP-0031 Android evidence gaps; load/soak harness;
  backup/restore drill; monitoring/runbooks; staged deploy rehearsal; a11y
  evidence package; aggregate pilot analytics skeleton
- Deferred: Play public release; F0 §27 DoD; remaining PR-013 sampled review /
  privacy ops; specialist review waiver; real learner production data

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Auth request drafted post–APP-0031 | Pass | PR-015_PRODUCTION_HARDENING_AUTHORIZATION_REQUEST.md |
| Verification scope PH-01–PH-12 | Pass | PR-015_PRODUCTION_HARDENING_VERIFICATION_SCOPE.md |
| Bounded vs full PR-015 / F0 | Pass | auth request non-goals |
| APO begin-implementation statement | Pass | [APP-0032](APPROVALS.md#app-0032) |
| PR-015 code | Authorized (begin) | APP-0032; Active = E1 |

### Outcome

E0 complete under APP-0032. Active step E1 implements bounded operations
hardening and closed-beta evidence closure.

## PR-015 — Step E1 (implementation in progress)

### Evidence reviewed

- APP-0032 / D-0112 begin-implementation
- Verification scope PH-01–PH-12
- Implementation starting on checkpoint branch

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Begin-implementation recorded | Pass | APP-0032 |
| PH-01–PH-12 delivery | In progress | E1 |
| Play / F0 / remaining PR-013 | Not authorized | APP-0032 non-goals |

### Outcome

E1 implementation authorized and underway.

## PR-015 — Step E1 (bounded ops hardening delivery)

### Evidence reviewed

- Aggregate pilot analytics API/UI (`GET /operator/pilot-analytics`, OpenAPI
  **0.12.0**)
- Ops runbooks: backup/restore, incident/support, alerts skeleton, staged
  deploy dry-run
- Makefile targets: `load-soak`, `backup-restore-drill`, `staged-deploy-dry-run`
- Accessibility evidence package draft
- Device matrix authority updated for APP-0032 evidence closure

### Focused tests run

| Area | Result |
|---|---|
| PHPUnit integration (Docker PHP 8.4.24 + pdo_pgsql) | `OperatorContentWorkflowTest` — **2 passed / 94 assertions** |
| Vitest (Docker Node 24.18.1) | `OperatorConsole.test.tsx` — **1 passed** |
| Locale validation (Docker Node 24) | Pass — 6 catalogues, 5 pending-review warnings |
| OpenAPI contracts generate | Pass — regenerated for 0.12.0 |
| Backup/restore drill | Run when claimed in commit notes |
| Load/soak / staged-deploy dry-run / physical matrix / `make verify` | **Not claimed** unless separately recorded |

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| PH-01–PH-04 device matrix / assemble | Partial | docs + matrix template; physical/Gradle not claimed |
| PH-05 `make verify` | Not claimed | host/env limitations |
| PH-06 load/soak harness | Pass (harness) | `make load-soak` / scripts/run-load-soak.sh — needs live API to execute |
| PH-07 backup/restore | Pass (script+doc) | docs/ops + make backup-restore-drill |
| PH-08 monitoring/runbooks | Pass (docs) | ALERTS + INCIDENT runbooks |
| PH-09 staged deploy dry-run | Pass (script+doc) | make staged-deploy-dry-run |
| PH-10 a11y package | Pass (doc) | PR-015_ACCESSIBILITY_EVIDENCE_PACKAGE.md; manual rows open |
| PH-11 pilot analytics | Pass (code+tests) | OpenAPI 0.12.0 + OperatorConsole |
| PH-12 fail-closed regression | Pass (focused) | OperatorContentWorkflowTest |
| Play / F0 / remaining PR-013 | Not authorized | APP-0032 |

### Outcome

E1 delivery landed for APO exact-commit review with recorded limitations
(physical matrix, Gradle assemble, full `make verify`, live load-soak against
API, Play/F0).

## PR-015 — Step E1 acceptance package (awaiting APO)

### Evidence reviewed

- Exact implementation commit `5eca7515291ea571a7f697dcdc2da6197535f839`
- [PR-015_APO_ACCEPTANCE_PACKAGE.md](PR-015_APO_ACCEPTANCE_PACKAGE.md)
- QUALITY_REVIEW E1 gate table

### Gate checklist

| Check | Result | Evidence |
|---|---|---|
| Acceptance package drafted | Pass | PR-015_APO_ACCEPTANCE_PACKAGE.md |
| Exact commit SHA filled | Pass | `5eca7515291ea571a7f697dcdc2da6197535f839` |
| Limitations recorded | Pass | acceptance package |
| APO accept statement | Pass | [APP-0033](APPROVALS.md#app-0033) |
| Play / F0 / remaining PR-013 | Not authorized | APP-0033 non-goals |

### Outcome

PR-015 bounded operations hardening and closed-beta evidence closure
**accepted** under APP-0033 / D-0113 at
`5eca7515291ea571a7f697dcdc2da6197535f839`. Next: separate APP / release board
for F0 only.






