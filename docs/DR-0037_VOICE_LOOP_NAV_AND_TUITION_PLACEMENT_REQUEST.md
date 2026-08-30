# DR-0037 — SuperNova-style hold-to-talk loop, practical nav, 1:1 last

## Status

**A accepted** (29 August 2026, Asia/Kolkata): hold-to-talk Speak room,
optional example rather than blocking Warm-up/Listen/Observe/Rehearse, and
Practice tabs collapsed to Levels | Speak. Recorded as D-0215 amending
ADR-0007 per-turn submit. Original Maya copy; no SuperNova trademarks.

**B accepted** (29 August 2026, Asia/Kolkata): primary nav is
`Home · Learn · Speak · Progress · 1:1`. Learn is the four-session level
path; Speak is the speaking room; 1:1 is the waitlist plus governed institute
contact links. Recorded as D-0216 / ADR-0026 amending master plan §7.1. This
does not authorize production, Play, video calling, or paid subscriptions.

## Request

The current Talk / Role-play journey is cumbersome. Role-play does not feel
like a real role-play. The product owner asked to:

1. Use a speaking loop where Maya speaks, the learner **holds record**,
   releases to send the turn, Maya continues or corrects, and that repeats.
2. Rearrange the top menu so **1:1 tuition is last**, with a contact route to
   the institute (or later video after membership is confirmed).
3. Keep the learner flow close to **Supernova AI Spoken English**, then
   improvise.

## Current product (why it feels unlike role-play)

- Master plan Role-play is **Observe → Rehearse → Perform → Reflect**, still
  disclosed `synthetic_unreviewed`.
- Talk/Role-play capture is **tap start → stop → local playback → separate
  Upload** because ADR-0007 requires a learner-initiated submit after local
  review. Consent is also split across two purposes.
- Primary nav is **Home / Learn / Practice / Progress** (master plan §7.1).
  Practice then splits into Levels / Confidence Conversation / Role-play.
  The 1:1 waitlist form is currently stacked on the authenticated Home
  surface, not a first-class destination.
- 1:1 is an org-scoped **request queue**, not a booking, payment, or call.

## Recommended interpretation (if accepted)

Copy SuperNova’s **speaking interaction**, not SuperNova’s whole product.

### A. Hold-to-talk loop (Talk + Role-play Perform)

After session consent is granted once:

1. Maya speaks the prompt (existing neural TTS).
2. One large control: **press and hold to speak**; release to stop.
3. On release the client uploads that clip (standing consent + this gesture
   as the per-turn submit), transcribes, Maya replies in audio, and shows at
   most one advisory correction in the chosen language.
4. Repeat until the learner ends the scene.
5. **Type instead** remains a small overflow, not the default.
6. Observe / Rehearse / Reflect become **optional** (“Hear an example”)
   behind the live exchange, not blocking stages.

This would amend ADR-0007 **per-turn upload authorization** so that releasing
the hold control is the submit action. Local playback/retry remains available
from a “Listen again / Try again” control after a failed or unclear turn, not
as a required extra click on every successful turn.

Hold-to-talk does **not** by itself increase Azure spend versus today’s
successful uploaded turn (one STT + one model call + one TTS). It usually
shortens clips. Always-on listening would increase STT and is **out of
scope**.

### B. Practical top menu

Keep four learning destinations and add 1:1 as the last item:

`Home · Learn · Speak · Progress · 1:1`

- **Learn** — the four-session level path (Introduction → Practice →
  Conversation → Interaction).
- **Speak** — one speaking room: Maya hold-to-talk, with a scene picker for
  role-play. Do not keep Confidence Conversation and Role-play as sibling
  Practice tabs.
- **1:1** — existing waitlist request plus an institute contact link
  (phone/WhatsApp/email from governed config). No in-app video in this
  checkpoint.

This amends master plan §7.1 (four primary destinations) only if the APO
accepts five items with 1:1 last.

### C. What SuperNova alignment means here

Keep:

- rule/audio first (our Introduction/Practice lessons);
- then a **controlled spoken exchange** with the named teacher;
- then a **freer** scene (Interaction / role-play);
- L1 help;
- hold-to-talk as the only default capture.

Do **not** copy in this checkpoint: SuperNova Premium paywall, 100–300
translation drills as the Practice session, pronunciation scores as
authoritative marks, in-app human-mentor marketplace, or an in-app video
bridge.

## Out of scope unless separately accepted

- In-app video calling or WebRTC. Operator-parity already prefers **external
  meeting links**, not an in-product conferencing service.
- Paid learner subscriptions / Stripe / Play Billing. Entitlements remain
  Phase 2 / `ADMIN-ENT-01` (manual or externally reconciled grants).
- Always-on microphone or barge-in full duplex.
- Making Role-play independently reviewed or published curriculum.
- Changing marks, mastery, or unlocks based on AI speech scores.

## Decision asked of the Accountable Product Owner

1. Accept **A** (hold-to-talk loop + optional role-play example), **B** (nav
   with 1:1 last + institute contact), both, or neither.
2. Confirm 1:1 stays a **request + contact link** (recommended) versus
   postponing 1:1 until a later external-meeting-link checkpoint.
3. Name the institute contact target (phone, WhatsApp, or mailto) for the
   Direct soft-launch tenant.

Implementation of **A** is recorded under D-0215. Implementation of **B** is
recorded under D-0216 / ADR-0026. Institute contact values stay in env
(`TUITION_CONTACT_*`); empty values hide links until the Direct number is named.
