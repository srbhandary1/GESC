# Confidence Conversation APO acceptance package

This package prepared exact-artifact Accountable Product Owner acceptance of
the Confidence Conversation enterprise slice (plan Steps A1–A7 under
[APP-0021](APPROVALS.md#app-0021)). **Accepted** under
[APP-0025](APPROVALS.md#app-0025) / D-0103 at
`5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b` with the recorded limitations
below.

## Artifact accepted

| Field | Value |
|---|---|
| Checkpoint | Confidence Conversation enterprise slice (A1–A7) |
| Branch | `codex/confidence-conversation` |
| Governing slice authorization | [APP-0021](APPROVALS.md#app-0021) |
| Nested A4 authorization | [APP-0022](APPROVALS.md#app-0022) / [ADR-0010](adr/0010-conversation-model-provider-policy.md) |
| Nested A5 authorization | [APP-0024](APPROVALS.md#app-0024) / [ADR-0011](adr/0011-confidence-conversation-guidance-tts-policy.md) |
| Acceptance record | [APP-0025](APPROVALS.md#app-0025) |
| Spec | [CONFIDENCE_CONVERSATION_SPEC.md](CONFIDENCE_CONVERSATION_SPEC.md) |
| Verification scope | [CONFIDENCE_CONVERSATION_VERIFICATION_SCOPE.md](CONFIDENCE_CONVERSATION_VERIFICATION_SCOPE.md) |
| Quality evidence | [QUALITY_REVIEW.md](QUALITY_REVIEW.md) (A1–A7 sections) |
| Exact commit | `5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b` |

## Delivered behaviour (A1–A6)

1. **A1** — Spec + verification scope for Confidence Conversation (demo
   Vite paths non-evidence).
2. **A2** — Structured turn contract: `explanation_l1` + `explanation_locale`
   for all Phase 1 UI locales; sessions require `ui_locale`; shame-language
   fail-closed; OpenAPI 0.9.0; fake fixtures for six locales.
3. **A3** — Product Practice UI: Warm-up → Listen → Talk → Reflect; Gentle
   default; retry / type / skip / hint; separate correction card; advisory
   Reflect; no mastery mutation.
4. **A4** — ADR-0010 Azure OpenAI conversation-model adapter behind
   `ConversationModelProvider`; HTTP-fake tests; production fail-closed;
   live-dev cap ₹500 INR under APP-0022; outside-repo credential workspace.
5. **A5** — ADR-0011 AI-only lesson audio; `GuidanceTtsPolicy`; product
   Listen/reply text-first; fakes + optional on-device English speech;
   live multi-locale Azure TTS spend unauthorized.
6. **A6** — Hash-locked suite `friend.l1.locale.v1` (15 cases, all UI
   locales); gates for false-correction, missing L1, shame, schema, zero
   fake cost; human-review export/import suite_hash fail-closed.

## Verification evidence (recorded this slice)

| Area | Result |
|---|---|
| A6 suite_hash | `5bf9a508d1aeaf3a329212e60cf957cc28404fabcf0d34f4f65ebb6e99223270` |
| `ai:evaluate` (fakes) | completed — 15 passed / 0 failed; cost 0; report_digest `355b4d0424fab059209d97fdd863e738a703e9a1bd6437069981fdadc42b84ca` |
| Docker PHP 8.4.24 `AiEvaluationRunnerTest` | 6/6 |
| Guidance TTS / fake TTS / Azure adapter unit-feature | 17 PHP tests passed (A5 session) |
| Localization resolver | 31 passed |
| Web conversation Vitest | 6 passed |
| CC-E1–CC-E5 | Pass (QUALITY_REVIEW A6) |
| Host `make verify` / remote GitHub Actions | **Not claimed** as green for this package (host PHP lacks `pdo_pgsql`; Docker/pinned evidence used) |
| Live Azure OpenAI smoke | Operator-gated under APP-0022; not required for slice acceptance on fakes |
| Live multi-locale Azure TTS | Unauthorized / not run |

Demo Vite `/demo/tts` and `/demo/chat` (D-0092 / D-0094) are **not** acceptance
evidence.

## Recorded limitations (must accompany acceptance)

1. Default CI/local conversation-model binding remains `fake` / `disabled`;
   production stays fail-closed. Live Azure OpenAI is allowlisted non-production
   only, operator-gated, outside-repo credentials, **₹500 INR** hard ceiling
   (APP-0022). Unlimited or production promote spend is unauthorized.
2. Live multi-locale Azure TTS spend and ordinary-app Azure Speech DI
   expansion beyond ADR-0008 / ADR-0011 remain unauthorized (APP-0024).
3. Product Listen/reply path is text-first; optional on-device English browser
   speech is interim non-evidence. AI-only lesson audio policy does not claim
   production TTS quality.
4. Evaluation cost rollup is aggregate/zero on fakes and is not a live spend
   ledger. Speech half of `make ai-eval` remains PR-009-gated and was not
   required for this acceptance evidence set.
5. UI locale catalogues for `ml`/`ta`/`te`/`hi` (and related strings) remain
   pending independent human language/content review; ordinary production
   stays `approved-only` English fallback per ADR-0005.
6. Host PHP toolchain may lack `pdo_pgsql`; full host `make verify` and remote
   GitHub Actions were not claimed green for this package. PostgreSQL /
   eval evidence used Docker PHP 8.4.24 against local Compose Postgres where
   recorded.
7. AI remains advisory and must not mutate authoritative learning records
   (marks, mastery, unlocks, publications).
8. This acceptance does **not** authorize PR-012 Role-play implementation,
   production deployment, Google Play release, real learner production data,
   or waiver of independent legal / security / privacy / tenancy /
   accessibility / language / vendor / release review.

## APO statement (recorded)

```text
APO accept Confidence Conversation slice at 5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b with its recorded limitations
```

Interpretation for the recorder (Codex is never an approver):

- Accept the exact commit only, with every recorded limitation above.
- Close plan Step A7; STATUS next checkpoint becomes consideration of a
  separate PR-012 APP only.
- Do **not** treat this as PR-012 implementation authorization, production
  authorization, or live multi-locale TTS spend authorization.

## Recorder checklist after APO statement

1. Exact commit SHA filled in this package (`5582c9ab69d6c6b4409ffade6f0f5b9d94fc4b7b`).
2. Appended [APP-0025](APPROVALS.md#app-0025) to [APPROVALS.md](APPROVALS.md).
3. Appended D-0103 to [DECISIONS.md](DECISIONS.md).
4. Updated [STATUS.md](STATUS.md) and [ENTERPRISE_STUDENT_PATH_PLAN.md](ENTERPRISE_STUDENT_PATH_PLAN.md) (A7 done; Active step = B0).
5. Appended acceptance note to [QUALITY_REVIEW.md](QUALITY_REVIEW.md).
6. Do **not** start PR-012 code until a distinct APP authorizes the bounded
   synthetic role-play slice in
   [PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md](PR-012_ROLEPLAY_AUTHORIZATION_REQUEST.md).
