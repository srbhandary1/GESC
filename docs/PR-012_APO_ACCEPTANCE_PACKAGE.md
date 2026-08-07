# PR-012 APO acceptance package

This package prepared exact-artifact Accountable Product Owner acceptance of
master-plan §26 **PR-012 — Role-play framework and pilot content integration**
for the bounded **synthetic** slice authorized under
[APP-0026](APPROVALS.md#app-0026). **Accepted** under
[APP-0027](APPROVALS.md#app-0027) / D-0106 at
`0fb6f2953580ed6154e4d895e128c46167c6f5d4` with the recorded limitations
below.

## Artifact accepted

| Field | Value |
|---|---|
| Checkpoint | PR-012 bounded synthetic Role-play (plan B0–B2) |
| Branch | `codex/confidence-conversation` |
| Governing begin-implementation | [APP-0026](APPROVALS.md#app-0026) |
| Acceptance record | [APP-0027](APPROVALS.md#app-0027) |
| Authorization request | [PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md](PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md) |
| Verification scope | [PR-012_ROLEPLAY_VERIFICATION_SCOPE.md](PR-012_ROLEPLAY_VERIFICATION_SCOPE.md) |
| Quality evidence | [QUALITY_REVIEW.md](QUALITY_REVIEW.md) (PR-012 B0/B1/B2 sections) |
| Exact commit | `0fb6f2953580ed6154e4d895e128c46167c6f5d4` |

## Delivered behaviour

1. **B0** — APP-0026 begin-implementation for synthetic Role-play only.
2. **B1** — Server stage machine Observe → Rehearse → Perform → Reflect via
   append-only `conversation_session_stage_events` (session rows remain
   immutable).
3. Synthetic clinic-reception persona/scenario + two `scenario_objectives`
   (`development_only` / `synthetic_unreviewed`).
4. Reflect writes advisory `roleplay_feedback_evidence` digests only — no
   mastery, attempt, review-schedule or publication mutation.
5. HTTP `POST /conversation-sessions/{id}/stages` with idempotency; OpenAPI
   **0.10.0** + regenerated clients.
6. Practice UI **Role-play (synthetic)** entry → `RoleplayJourney`.
7. **B2** — Content path for this APP remains disclosed synthetic; commissioned
   specialist modules stay gated by DR-0007 / separate review (D-0105).

## Verification evidence (recorded this slice)

| Area | Result |
|---|---|
| PHPUnit unit/feature (host PHP) | `RoleplayStagesTest` + stage validation — **5 passed** |
| PHPUnit integration (Docker PHP 8.4.24 + pdo_pgsql) | `RoleplaySessionTest` + `FoundationSeederIntegrityTest` — **6 passed / 42 assertions** |
| Vitest (Node 24 Docker) | `RoleplayJourney` + `ConversationJourney` — **4 passed** |
| Locale validation (Node 24) | Pass — 6 catalogues, 5 pending-review warnings |
| OpenAPI contract generate `--check` (Node 24) | Pass — contracts match OpenAPI 0.10.0 |
| Chromium e2e Role-play journey | Spec added; **not claimed green** — Playwright webServer `tsc -b` blocked by pre-existing unrelated typecheck errors in this environment |
| Host `make verify` / remote GitHub Actions | **Not claimed** |

Demo Vite `/demo/*` remains non-evidence.

## Recorded limitations (must accompany acceptance)

1. Slice is disclosed **synthetic** only (`synthetic_unreviewed`). Human-approved
   curriculum, transcript or audio modules remain unauthorized.
2. Default conversation-model binding remains `fake` / `disabled` for ordinary
   CI/local. Live Azure OpenAI remains under existing APP-0022 gates only
   (₹500 INR, operator-gated, non-production).
3. Live multi-locale Azure TTS spend and ordinary Azure Speech DI expansion
   beyond ADR-0008 / ADR-0011 remain unauthorized.
4. Objective completion evaluation is deterministic/synthetic advisory — not a
   production scoring engine.
5. UI locale role-play strings (and other pending catalogues) remain pending
   independent human language/content review; ordinary production stays
   `approved-only` English fallback per ADR-0005.
6. Host `make verify`, full Chromium e2e matrix and remote CI were not claimed
   green for this package.
7. AI / Role-play feedback remains advisory and must not mutate authoritative
   learning records.
8. This acceptance does **not** authorize PR-013+, production, Google Play,
   real learner production data, or waiver of independent specialist reviews.

## APO statement (recorded)

```text
APO accept PR-012 synthetic role-play at 0fb6f2953580ed6154e4d895e128c46167c6f5d4 with its recorded limitations
```

Interpretation for the recorder (Codex is never an approver):

- Accept the exact commit only, with every recorded limitation above.
- Close plan Steps B0–B2 for the synthetic APP scope.
- Do **not** treat this as approved curriculum, expanded live providers,
  production or Play authorization.
- Next checkpoint consideration is a separate APP for PR-013 (or content DR
  if commissioning modules).

## Recorder checklist after APO statement

1. Exact commit SHA filled (`0fb6f2953580ed6154e4d895e128c46167c6f5d4`).
2. Appended [APP-0027](APPROVALS.md#app-0027) to [APPROVALS.md](APPROVALS.md).
3. Appended D-0106 to [DECISIONS.md](DECISIONS.md).
4. Updated [STATUS.md](STATUS.md) and
   [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md)
   (PR-012 synthetic accepted; Active step = C0 consideration).
5. Appended acceptance note to [QUALITY_REVIEW.md](QUALITY_REVIEW.md).
6. Do **not** start PR-013 code until a distinct APP authorizes it.
