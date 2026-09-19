# PR-015 accessibility evidence package

**Authority:** [APP-0032](APPROVALS.md#app-0032) / PH-10  
**Supplements:** [PR-004_ACCESSIBILITY_VERIFICATION_SCOPE.md](PR-004_ACCESSIBILITY_VERIFICATION_SCOPE.md)

Automated axe scans alone do **not** satisfy production accessibility sign-off.

## Automated evidence (supporting)

| Journey | Spec | Status |
|---|---|---|
| Bootstrap / auth shell | `apps/web/e2e/bootstrap.spec.ts` | Reuse; run via `make e2e` when claimed |
| Learner journey | `apps/web/e2e/learner-journey.spec.ts` | Reuse |
| Conversation journey | `apps/web/e2e/conversation-journey.spec.ts` | Reuse |
| Operator console | Manual + Vitest structure; e2e axe pending | Record limitation if e2e not green |

## Manual checklist (required before production a11y claim)

| ID | Check | Learner | Operator | Android TalkBack |
|---|---|---|---|---|
| A11Y-01 | Keyboard-only completion of critical path | ☐ | ☐ | n/a |
| A11Y-02 | Visible focus; no keyboard trap | ☐ | ☐ | ☐ |
| A11Y-03 | Screen reader announces primary headings/actions | ☐ | ☐ | ☐ |
| A11Y-04 | 200% zoom / reflow usable | ☐ | ☐ | ☐ |
| A11Y-05 | Error text associated with controls | ☐ | ☐ | ☐ |
| A11Y-06 | Mic permission denial recoverable without stuck focus | ☐ | n/a | ☐ |

## Evidence log

| Path | Commit | Tester | Date | Result |
|---|---|---|---|---|
| Authenticated Kannada Home, Progress, Plans, Speak/IME and system accessibility settings on realme RMX3085 / API 33 | `1bdea67` / APK `28bda09…dcfff4c` | Codex device run via ADB | 2026-09-19 | **FAIL / partial:** split-screen preserved task and exact-320 portrait was visually bounded; enlarged text, short landscape, IME action visibility, high contrast and Plans focus restoration failed; TalkBack critical journey not run |

## Limitations recorded under APP-0032

- Manual checklist rows may remain open until specialist review.
- Operator axe e2e is not claimed unless separately green.
- This package does not waive independent accessibility review for F0.
