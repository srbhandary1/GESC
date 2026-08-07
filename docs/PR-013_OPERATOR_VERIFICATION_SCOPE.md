# PR-013 operator verification scope

**Status:** Accepted under [APP-0029](APPROVALS.md#app-0029) at
`9220ab27baa6dc29d82caf8e9e97e94f7df7219d`. Bound to
[PR-013_OPERATOR_AUTHORIZATION_REQUEST.md](PR-013_OPERATOR_AUTHORIZATION_REQUEST.md)
and begin-implementation [APP-0028](APPROVALS.md#app-0028).  
**Prior slice:** PR-012 synthetic accepted under
[APP-0027](APPROVALS.md#app-0027).

## Authority and boundary

PR-013 may be authorized only by a distinct APP after PR-012 synthetic
acceptance. This verification scope covers the **first bounded APP** only:
content workflow + audit views. Full master-plan PR-013 items (pilot dashboard,
sampled review, support diagnostics, privacy ops) require later APPs or ADR
amendments where policy blocks them.

Master-plan acceptance criteria that this first APP must satisfy locally:

- pilot operations for the authorized actions require no direct database access;
- privileged actions are authorized and auditable.

## Vertical slice (when authorized)

1. Authenticated operator (trainer / org-admin / content-reviewer membership)
   selects organization context.
2. Operator lists synthetic curriculum drafts/status for that organization only.
3. Operator records EN and/or KN review decisions via project-owned command
   services.
4. Operator publishes or rolls back within existing curriculum publication
   rules.
5. Operator lists append-only audit events for those actions (safe metadata /
   digests only).
6. Cross-organization access is denied; missing permission fails closed.
7. Demo Vite `/demo/*` dashboards remain non-evidence.

## Reuse

- Curriculum authoring / review / publish commands — PR-005 domain services
- Organization context + policies — tenancy foundation
- `audit_events` append-only recorder — existing write path
- Sanctum first-party session auth — existing web auth
- Cross-tenant test patterns — conversation / learner integration tests

## Schema / permission expectations (when authorized)

- Seed role packs for operator memberships on existing curriculum permission
  keys; add `audit.read` (or equivalent) only if required and documented.
- Do **not** grant trainer/operator access to learner audio or transcripts
  under ADR-0007 without a separate ADR amendment + APP.
- Prefer wrapping existing commands over inventing parallel write paths.

## Verification (when authorized)

| ID | Check |
|---|---|
| OC-01 | Operator can complete review → publish on synthetic curriculum without DB access |
| OC-02 | Privileged actions write auditable events |
| OC-03 | Operator can list org-scoped audit events for those actions |
| OC-04 | Cross-tenant denial for curriculum and audit reads/writes |
| OC-05 | Missing permission fails closed |
| OC-06 | OpenAPI/generated-client drift clean |
| OC-07 | Operator UI journey unit + Chromium smoke for review/publish/audit |
| OC-08 | `make verify` evidence recorded (Docker/pinned OK when host limited) |

## Explicit non-goals

- Sampled speaking review / audio access
- Learner PII CSV export
- Operator privacy deletion workflows
- Full content studio
- Production / Play

## Exit gate

The bounded PR-013 slice is ready for APO exact-commit acceptance only when
OC-01–OC-08 pass for the authorized scope and recorded limitations preserve that
sampled review, privacy ops, production and Play remain separately gated.
