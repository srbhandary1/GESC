---
status: accepted
date: 2026-08-29
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
consulted:
  - Cursor agent as author and evidence recorder, not approver
informed: []
amends_master_plan: true
approval_evidence: []
approval_reference: Exact human instruction in the active Cursor conversation accepting DR-0037 B
---

# ADR-0026: Five-destination learner navigation with 1:1 last

## Status

Accepted on 29 August 2026. This ADR resolves
[DR-0037](../DR-0037_VOICE_LOOP_NAV_AND_TUITION_PLACEMENT_REQUEST.md) option B
and amends master-plan §7.1. Recorded as D-0216. The Speak-room teacher name
was later amended by D-0218 (Swathi).

## Context

Master plan §7.1 used four primary destinations (Home / Learn / Practice /
Progress). The 1:1 waitlist form sat on authenticated Home. After DR-0037 A,
Practice already collapsed to Levels | Speak. The product owner accepted five
items with 1:1 last so Speak is a first-class room and live help is not buried
under Home.

## Decision

1. Primary learner navigation is exactly:

   `Home · Learn · Speak · Progress · 1:1`

2. **Learn** is the four-session level path (Introduction → Practice →
   Conversation → Interaction).
3. **Speak** is one speaking room (everyday Swathi talk and a situation picker).
   Confidence Conversation and Role-play are not sibling primary destinations
   or Practice tabs.
4. **Home** keeps today’s recommended learner journey. It does not host the
   1:1 form.
5. **1:1** is the existing org-scoped waitlist request plus governed institute
   contact links (phone / WhatsApp / email from configuration). Empty contact
   values hide the corresponding link. This is not booking, payment, or an
   in-app call.
6. `#practice` remains a hash alias of Speak so existing bookmarks work.
7. This does not authorize in-app video, WebRTC, Stripe, Play Billing, or
   production/Play. ADR-0035 authorizes Direct in-app checkout through a
   project-owned payment port; Play Billing and Stripe remain excluded.
   D-0218 amends the teacher name in item 3 to Swathi.

## Alternatives considered

### Alternative A — keep 1:1 on Home

Rejected by the Accountable Product Owner (DR-0037 B accepted).

### Alternative B — six destinations including a separate Role-play item

Rejected. Role-play stays a Speak-room situation picker.

## Consequences

### Positive

- Speak and the level path are not competing tabs.
- 1:1 is findable without scrolling Home.

### Negative and trade-offs

- Five-item mobile nav is denser.
- Institute contact numbers are not committed in git; the Direct tenant must
  set env values when a number is named.

## Security, privacy and tenancy impact

- Tuition requests remain org-scoped and authorization-gated.
- Contact links are configuration, not learner PII. Invalid values are
  omitted rather than rendered.

## Authoritative records and consistency impact

No schema change. Listing responses add `institute_contact` (OpenAPI 0.33.0).

## API, client and operational impact

`GET /me/tuition-requests` includes `institute_contact`. Generated client
types are regenerated. UI catalogues `ui-*-2026-08-29.5`.

## Master-plan amendment

Amends §7.1 from four destinations (Home / Learn / Practice / Progress) to
five destinations (Home / Learn / Speak / Progress / 1:1) as specified above.
`ENTERPRISE_PRODUCT_MASTER_PLAN.md` is updated to version 3.7 in the same
change.

## Approval evidence

Accountable Product Owner instruction: accept DR-0037 B.
