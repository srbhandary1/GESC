# PR-013 APO acceptance package

This package prepared exact-artifact Accountable Product Owner acceptance of
master-plan §26 **PR-013 — Operator console and privacy operations** for the
bounded **content-workflow + audit** slice authorized under
[APP-0028](APPROVALS.md#app-0028). **Accepted** under
[APP-0029](APPROVALS.md#app-0029) / D-0108 at
`9220ab27baa6dc29d82caf8e9e97e94f7df7219d` with the recorded limitations
below.

## Artifact accepted

| Field | Value |
|---|---|
| Checkpoint | PR-013 bounded operator content-workflow + audit (plan C0–C1) |
| Branch | `codex/confidence-conversation` |
| Governing begin-implementation | [APP-0028](APPROVALS.md#app-0028) |
| Acceptance record | [APP-0029](APPROVALS.md#app-0029) |
| Authorization request | [PR-013_OPERATOR_AUTHORIZATION_REQUEST.md](PR-013_OPERATOR_AUTHORIZATION_REQUEST.md) |
| Verification scope | [PR-013_OPERATOR_VERIFICATION_SCOPE.md](PR-013_OPERATOR_VERIFICATION_SCOPE.md) |
| Quality evidence | [QUALITY_REVIEW.md](QUALITY_REVIEW.md) (PR-013 C0/C1 sections) |
| Exact commit | `9220ab27baa6dc29d82caf8e9e97e94f7df7219d` |

## Delivered behaviour

1. **C0** — APP-0028 begin-implementation for content-workflow + audit only.
2. Seeded Direct-org role packs: `trainer`, `organization_admin`,
   `content_reviewer`, plus permission `audit.read`.
3. Org-scoped operator HTTP APIs wrapping existing domain commands:
   drafts list, EN/KN review, publish, rollback, audit-event list.
4. Audit responses expose digests / safe metadata only — no raw learner
   conversation, audio or transcripts (ADR-0007 preserved).
5. Ordinary-product `OperatorConsole` (not Vite `/demo/*`) for review queue,
   publication status and audit trail.
6. OpenAPI **0.11.0** + regenerated clients; localization keys in all Phase 1
   UI catalogues.
7. Cross-tenant denial and missing-permission fail-closed coverage in focused
   integration tests.

## Verification evidence (recorded this slice)

| Area | Result |
|---|---|
| PHPUnit feature (host) | `OperatorRequestValidationTest` — **2 passed** |
| PHPUnit integration (Docker PHP 8.4.24 + pdo_pgsql) | `OperatorContentWorkflowTest` + `FoundationSeederIntegrityTest` — **5 passed / 104 assertions** |
| Vitest (Node 24 Docker) | `OperatorConsole.test.tsx` — **1 passed** |
| Locale validation (Node 24) | Pass — 6 catalogues, 5 pending-review warnings |
| OpenAPI contract generate `--check` (Node 24) | Pass — contracts match OpenAPI 0.11.0 |
| OC-01–OC-07 (focused) | Pass — QUALITY_REVIEW C1 |
| OC-08 `make verify` / Chromium e2e / remote CI | **Not claimed** |

Demo Vite `/demo/*` remains non-evidence.

## Recorded limitations (must accompany acceptance)

1. This APP accepts only the **bounded content-workflow + audit** slice — not
   full master-plan §8.2 / PR-013 (pilot dashboard, support diagnostics,
   sampled speaking review, privacy ops, full content studio).
2. Consent-gated sampled speaking / transcript / audio review remains
   unauthorized under ADR-0007 until a separate ADR amendment + APP.
3. Operator-side recording deletion, AI memory ops, account erasure and learner
   PII CSV export remain unauthorized.
4. Institution-operations pack (attendance, notices, billing) remains outside
   scope (master plan §8.3).
5. UI locale operator strings (and other pending catalogues) remain pending
   independent human language/content review; ordinary production stays
   `approved-only` English fallback per ADR-0005.
6. Host `make verify`, full Chromium e2e matrix and remote CI were not claimed
   green for this package.
7. Live Azure OpenAI / multi-locale TTS spend remain under existing APP gates
   only; this acceptance does not expand them.
8. This acceptance does **not** authorize PR-014+, production, Google Play,
   real learner production data, or waiver of independent specialist reviews.

## APO statement (recorded)

```text
APO accept PR-013 bounded operator content-workflow and audit at 9220ab27baa6dc29d82caf8e9e97e94f7df7219d with its recorded limitations
```

Interpretation for the recorder (Codex is never an approver):

- Accept the exact commit only, with every recorded limitation above.
- Close plan Steps C0–C1 for this bounded APP scope.
- Do **not** treat this as sampled-review, privacy-ops, production or Play
  authorization.
- Next checkpoint consideration is a separate APP for remaining PR-013 items
  and/or PR-014.

## Recorder checklist after APO statement

1. Exact commit SHA filled (`9220ab27baa6dc29d82caf8e9e97e94f7df7219d`).
2. Appended [APP-0029](APPROVALS.md#app-0029) to [APPROVALS.md](APPROVALS.md).
3. Appended D-0108 to [DECISIONS.md](DECISIONS.md).
4. Updated [STATUS.md](STATUS.md) and
   [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md)
   (PR-013 bounded operator accepted; Active step = D0 consideration).
5. Appended acceptance note to [QUALITY_REVIEW.md](QUALITY_REVIEW.md).
6. Do **not** start sampled review, privacy ops, PR-014 or later code until a
   distinct APP authorizes that scope.
