# WEB-UX-06 — Shorter first-success decision packet

**Status:** Decision packet only. **Not implemented.** D-0293 ten-stage first-use remains product law until the Accountable Product Owner records an amendment.

**Authority required:** APP + D-0293 amendment + master-plan / UI-plan update. Option 2 must not ship under WEB-UX-01–05.

**Consent law that both options preserve:** [ADR-0007](adr/0007-recording-consent-retention-provider-processing-and-deletion-policy.md) — both recording-upload and automated-provider purposes appear immediately before first Speak/upload, never preselected. Type instead remains. Adult confirmation + `goal_key` remain the only onboarding write.

## Option 1 — Refine the ten stages (copy only)

Keep the current ordered stages from D-0293 / WEB-UX-03:

1. welcome
2. adult
3. preferences (client-only locale/a11y)
4. goal
5. placement
6. audio-output
7. consent
8. microphone
9. practice
10. acknowledgement → Home

**Change:** copy and verbs only (align WEB-UX chrome already shipped). No reorder, no skip, no consent preselect, no new authoritative fields.

**Exit:** WEB-UX-03 history Back remains; catalogues stay pending independent review.

## Option 2 — Fast path (requires D-0293 amendment)

Adult → goal → placement → one private typed/spoken turn → Home.

Language and accessibility stay in the header. Audio-output and microphone try appear contextually before first relevant use. **Both ADR-0007 purposes** appear immediately before first Speak/upload, never preselected.

If accepted: amend D-0293, update `ENTERPRISE_PRODUCT_MASTER_PLAN.md` and the UI plan, then implement. Do not ship Option 2 as a silent reorder.

## Recommendation

Stay on Option 1 until a named APP records Option 2. WEB-EXP-02 / H2 (ten-step vs fast path) is not authorized until Option 2 is accepted **and** [UX_FUNNEL_EVENT_CONTRACT.md](UX_FUNNEL_EVENT_CONTRACT.md) emission review is complete.
