# Global English

Global English is a mobile-first spoken-English platform for adult learners.
English remains the learning language and Kannada remains the default
guidance language. Phase 1 also provides UI-only review previews in Malayalam,
Tamil, Telugu and Hindi. The canonical product, architecture, security and
delivery baseline is
[ENTERPRISE_PRODUCT_MASTER_PLAN.md](ENTERPRISE_PRODUCT_MASTER_PLAN.md).

## Repository authority

When repository artefacts conflict, use this order:

1. `ENTERPRISE_PRODUCT_MASTER_PLAN.md`;
2. an accepted ADR in `docs/adr/` that explicitly amends the master plan;
3. an approved specification in `docs/specs/`;
4. the active issue or task;
5. existing implementation behaviour.

Read [AGENTS.md](AGENTS.md) before making changes. The current checkpoint and
known limitations are recorded in [docs/STATUS.md](docs/STATUS.md).

## Current state

PR-006 — Deterministic daily-speaking slice is implemented, verified and
accepted at its exact implementation commit under APP-0007. PR-007 — Offline
bundle and idempotent outbox is implemented, repository-verified and accepted
at `e0e2f1b90e0eb4ecf680f5243b57ec0731c32648` under APP-0009. PR-008 —
Conversation domain with deterministic fake providers — is accepted at
`11f7b597ad2db3b8cf65a1394c1171a8280045d1` under APP-0012. PR-009 now has
its provider-neutral recording/recovery slice plus disabled-by-default Azure
Speech adapters and a locked synthetic benchmark harness. No Azure credential,
live provider request, benchmark result, learner-data use, PR-009 acceptance,
PR-010 or production is authorized. ADR-0005 additionally brings UI-only Malayalam
(`ml-IN`), Tamil (`ta-IN`), Telugu
(`te-IN`) and Hindi (`hi-IN`) implementation previews into Phase 1 alongside
Kannada (`kn-IN`) and the English (`en-IN`) source/fallback.

All five translated interface catalogues are drafts pending independent human
review. Ordinary production builds use the strict `approved-only` policy and
visibly fall back to English. Local development, tests and an explicitly
requested review build may render current drafts with a visible preview
notice. The review flag must not be enabled for a production deployment:

```text
VITE_UI_TRANSLATION_PREVIEW=true pnpm --filter @global-english/web build
```

## Prerequisites

- PHP 8.4.23;
- Composer 2.10.2;
- Node.js 24.18.1 LTS;
- pnpm 11.18.0;
- Make;
- Docker Engine with Docker Compose v2 for local services and `make dev`.

Runtime declarations live in `.php-version`, `.node-version`, `.nvmrc`,
`package.json` and the CI workflow. Composer and pnpm lockfiles are the exact
dependency authority.

## Hosting boundary

The committed Compose stack is for local development only. Its published
ports remain bound to `127.0.0.1`; it is not a production definition for AWS,
Hostinger or another VPS. A later authorized deployment checkpoint must add a
reviewed environment-specific design for TLS and ingress, managed secrets,
PostgreSQL, Redis, private object storage, queues, workers, scheduler,
backups, monitoring, restoration and rollback.

Credentials, real vendor evidence and PR-009 encrypted benchmark fixtures
must remain outside Git and outside application release artefacts. Cloud or
VPS deployment must inject secrets through the selected platform's protected
secret mechanism and create any required private state directories at deploy
time. The current repository status does not authorize production deployment;
see [docs/STATUS.md](docs/STATUS.md) and the
[PR-009 evidence handoff](docs/PR-009_SPEECH_BENCHMARK_EVIDENCE_HANDOFF.md).

## Bootstrap and verification

From the repository root:

```text
make bootstrap
make verify
make build
```

`make bootstrap` installs locked dependencies and creates ignored local
environment files without overwriting existing ones. To start PostgreSQL,
Redis, object storage, mail/OTP fakes, the API, web app and queue worker:

```text
make dev
```

For a presentation-ready local preview that needs no database, sign-in,
provider credential or published curriculum, run:

```text
make demo
```

The demo starts only the Vite development server on `127.0.0.1:5173`, opens
directly on a populated synthetic lesson and provides the deterministic
text-only Friend journey. Its authentication, lesson, result and conversation
fixtures exist only in browser memory and reset on reload. A prominent notice
states that the preview contains no learner data, real AI, approved curriculum
or production service. `VITE_LOCAL_DEMO=true` is honoured only in Vite
development mode and in the explicit `demo` distribution build mode below,
and demo output must never be treated as product, benchmark or publication
evidence.

On this prepared workstation, `make demo` automatically selects the ignored
Linux-native Node.js/pnpm toolchain under `tmp/toolchain` when it is present.
It does not depend on copied or system-wide Node modules.

To view it from another computer without exposing the application port, use
SSH local forwarding to workstation `127.0.0.1:5173` and open
`http://127.0.0.1:5173` on the viewing computer.

### Distributable demo bundle

The same disclosed synthetic demo can be built once and served as static
files for a presentation:

```text
make demo-build
make demo-serve
```

`make demo-build` runs the explicit `vite build --mode demo` distribution
into `apps/web/dist-demo`. The ordinary production build (`make build`,
`dist/`) is untouched and can never activate the demo runtime. The demo
bundle uses relative asset paths, registers no service worker, stores
nothing on the device and makes no API or provider request; the visible
synthetic-demo notice remains part of the bundle. As an explicitly requested
review artifact it renders the draft interface locales (Kannada first) with
the visible preview notice; production stays `approved-only`. Verify the
built artifact with `make demo-dist-e2e`.

### Remote client testing over the static IP

To let a client outside this Wi-Fi open the same synthetic demo, use the
opt-in launcher (loopback defaults are unchanged):

```text
make demo-client
```

This serves the built demo on `0.0.0.0:8080` and advertises
`http://117.254.108.143:8080`. Port `8000` is reserved for another local
project demo on this workstation. One-time router setup: forward TCP `8080`
on the static IP to this laptop's LAN address on port `8080`. Until that
forward exists, `make demo-client-tunnel` publishes a temporary HTTPS
Cloudflare URL. `make demo-android-remote` builds the demo APK so its
WebView loads that same static-IP origin. Full steps and limits are in
[docs/CLIENT_DEMO_RUNBOOK.md](docs/CLIENT_DEMO_RUNBOOK.md).

### Android demo APK

The Capacitor 8 wrapper in `apps/web/android` packages the same synthetic
demo bundle as an installable Android demo app (`Global English Demo`,
placeholder identifier `com.globalenglish.demo`, minimum API 24, target API
36). It is presentation packaging only: it contains no credentials, no
provider access and no learner data, and it is not the PR-014 production
Android application.

The production-path closed-beta app lives in a **separate** tree
`apps/web/android-app` (`com.globalenglish.app`) under APP-0030. See
[docs/PR-014_ANDROID_CLOSED_BETA_BUILD.md](docs/PR-014_ANDROID_CLOSED_BETA_BUILD.md)
and [docs/PR-014_ANDROID_DEVICE_MATRIX.md](docs/PR-014_ANDROID_DEVICE_MATRIX.md).
Google Play public release remains unauthorized.

Requirements: JDK 21 (`JAVA_HOME`) and an Android SDK with platform 36
(`ANDROID_HOME`). Then:

```text
make demo-android
# production-path closed beta (separate package identity):
VITE_MOBILE_API_ORIGIN=https://your-api.example make android-closed-beta
```

The demo debug APK is written to
`apps/web/android/app/build/outputs/apk/debug/app-debug.apk`. The closed-beta
production-path debug APK is written to
`apps/web/android-app/app/build/outputs/apk/debug/app-debug.apk`. Recording
in the demo mission uses the device microphone only for local playback and
retry; nothing is uploaded. On an IPv6-limited network, pass
`GRADLE_OPTS=-Djava.net.preferIPv4Stack=true` so Gradle can download its
distribution and dependencies.

Local services bind only to `127.0.0.1`. The API uses port 8000, Vite uses port
5173, Mailpit's browser interface uses port 8025, S3-compatible storage uses
port 8333 and the OTP stub uses port 8089 by default.

For the PR-008 local/testing prototype, set
`CONVERSATION_PROVIDER_DRIVER=fake`. Production and every environment other
than `local` or `testing` fail closed even if that value is supplied. The
prototype accepts bounded typed text only, uses no real provider and creates
or persists no audio.

A fresh PostgreSQL Compose volume also creates the isolated
`global_english_test` database and role used by the PostgreSQL integration
suite. For a volume created before PR-003, start the services and apply the
idempotent initializer once:

```text
docker compose exec postgres sh -lc 'psql --set ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" --file /docker-entrypoint-initdb.d/001-create-test-database.sql'
```

After migrating and seeding a local database, create ignored Passport signing
keys and a public Android client with the reviewed application redirect URI:

```text
cd apps/api
php artisan passport:keys
php artisan passport:client --public
```

Never commit Passport private keys or configure a client secret for the
Android public client. Production OTP delivery, privileged MFA and native
secure credential storage require separately reviewed implementations.
Production must also supply an independently generated, backed-up
`AUTH_IDENTITY_DIGEST_KEY`; it is intentionally separate from `APP_KEY` and
must not be rotated in place without the versioned migration described in
`docs/PR-003_TENANCY_VERIFICATION_SCOPE.md`.
Production must independently generate and access-control a minimum 32-byte
`OFFLINE_BUNDLE_SIGNING_KEY` (the `base64:` form is supported). The
local-development example key and test key must never be reused outside their
isolated environments.

## Root commands

| Command | Purpose |
|---|---|
| `make bootstrap` | Install dependencies and prepare the local environment |
| `make dev` | Run the API, web application, workers and local dependencies |
| `make demo` | Run the populated synthetic browser demo on loopback only |
| `make demo-build` | Build the distributable synthetic demo bundle into `apps/web/dist-demo` |
| `make demo-serve` | Serve the built synthetic demo bundle on `127.0.0.1:4175` |
| `make demo-client` | Serve the synthetic demo on `0.0.0.0:8080` for static-IP client testing |
| `make demo-android` | Build the Android demo APK from the synthetic demo bundle |
| `make demo-android-remote` | Build the demo APK so it opens `http://117.254.108.143:8080` |
| `make android-closed-beta` | Build the PR-014 production-path closed-beta APK (`com.globalenglish.app`; requires `VITE_MOBILE_API_ORIGIN`) |
| `make verify` | Run formatting, linting, static analysis, tests and contract checks |
| `make test` | Run all deterministic automated tests |
| `make e2e` | Run critical browser journeys |
| `make demo-e2e` | Verify the synthetic browser demo without application API calls |
| `make demo-dist-e2e` | Verify the built demo bundle without application API calls |
| `make ai-eval` | Run the locked AI and speech evaluation suite |
| `make openapi` | Validate OpenAPI policy and check generated-client drift |
| `make migrate` | Apply local migrations |
| `make seed` | Load safe development seed data |
| `make build` | Create production builds |

Use `pnpm run contracts:generate` after an intentional OpenAPI change, then
review the generated diff. `make ai-eval` runs only the locked PR-009 Azure
Speech benchmark. It fails before an HTTP request unless the dedicated
`speech-benchmark` environment, exact vendor evidence, runner commit, external
mode-`0600` secret/vendor/spend evidence files and separately approved spend
cap are present.
Routine verification uses HTTP fakes and never treats them as live benchmark
evidence.
`pnpm run locale:check` validates catalogue parity, normalization, hashes,
source linkage and review-state claims.

## Repository layout

- `apps/api/` — Laravel 13 modular monolith with operational, tenancy and
  identity foundation APIs;
- `apps/web/` — React 19.2 TypeScript/Vite localized shell, protected offline
  learner store, accessible error boundary and shared authentication adapters;
- `apps/web/android/` — Capacitor 8 demo-only Android wrapper for the
  synthetic demo bundle; not the PR-014 production Android application;
- `docs/api/` — canonical OpenAPI 3.1.1 contract;
- `docs/adr/` and `docs/APPROVALS.md` — accepted architecture decisions and
  durable human approval evidence;
- `packages/contracts/` — generated TypeScript client, types and JSON schemas;
- `packages/design-system/` — accessible multilingual presentation primitives,
  tokens and self-hosted Indian-script font integration;
- `packages/localization/` — governed UI catalogues, deterministic resolver,
  Unicode normalization and locale validation;
- `infra/local/` — local-only provider stubs;
- `compose.yaml` — PostgreSQL, Redis, SeaweedFS, Mailpit and WireMock services;
- `.github/workflows/ci.yml` — bootstrap, verification and build workflow.

The example credentials in `.env.example` files are local-development values
only. Do not reuse them outside an isolated development environment.

## Working method

- Work on one numbered checkpoint or explicitly bounded issue at a time.
- State a brief plan before material changes.
- Keep changes within the active checkpoint.
- Update API contracts and generated types whenever an API changes.
- Record material architecture decisions as ADRs.
- Update `docs/STATUS.md`, `docs/DECISIONS.md` and
  `docs/QUALITY_REVIEW.md` at the end of each checkpoint.
- Never commit secrets, real learner data or unlicensed media.

## Documentation

- [Master plan](ENTERPRISE_PRODUCT_MASTER_PLAN.md)
- [Agent instructions](AGENTS.md)
- [Current status](docs/STATUS.md)
- [Decision ledger](docs/DECISIONS.md)
- [Quality review](docs/QUALITY_REVIEW.md)
- [PR-009 benchmark evidence handoff](docs/PR-009_SPEECH_BENCHMARK_EVIDENCE_HANDOFF.md)
- [PR-004 accessibility verification scope](docs/PR-004_ACCESSIBILITY_VERIFICATION_SCOPE.md)
- [ADR template](docs/adr/0000-template.md)

Archived source plans are historical evidence only and are not authoritative.
