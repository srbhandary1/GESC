# DARK-TALK-05 — readable themes and optional free-talk explanation

Owner request, 25 September 2026: verify Android dark-mode tiles/text across the
learner app; offer selected-language reading beneath free-talk AI replies; explain
whether STT/TTS are included in usage calculations.

Bounded implementation:
- Capture current Android surfaces and correct mismatched background/foreground
  pairs using existing theme tokens. Check Home, Learn, Speak (all three modes,
  consent and active chat), Progress, Tuition, account and subscription surfaces
  in dark mode, with light-mode regression coverage.
- Add an explicit text-only explanation action beneath English free-talk replies.
  The server resolves the exact opening/turn from scoped private recovery; client
  text cannot select arbitrary content for translation. Preserve English replies,
  conversational position, correction policy and the 15-minute recovery lifetime.
  Reading an explanation generates no TTS or listening charge. Model usage remains
  metered. Reuse accepted explanation action, idempotency, capacity and safety gates.
- Update OpenAPI/generated clients and EN/KN interface copy; add targeted tenant,
  replay, expiry, locale, no-audio and UI/contrast regressions.
- Build and verify the shared web/Android slice. Record actual deployment and
  phone evidence separately from synthetic tests.

No billing formula, price, tenant/permission, retention or foundational schema
change. ADR-0042's explicit affirmative language-help policy and ADR-0043's audio
metering apply. Existing vendor capacity and fallback-cost reconciliation remain
separate unfinished work; this slice does not certify 1,000 simultaneous speakers.
