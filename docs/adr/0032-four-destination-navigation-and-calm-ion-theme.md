---
status: accepted
date: 2026-08-31
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
consulted:
  - Cursor agent as author and evidence recorder, not approver
informed: []
amends_master_plan: true
approval_evidence: []
approval_reference: Exact human instruction in the active Cursor conversation accepting four-tab navigation (Home · Learn · Speak · Me) and a calmed ion theme for the UX simplification series
---

# ADR-0032: Four-destination learner navigation with a Me hub, and a calmed ion theme

## Status

Accepted on 31 August 2026. **This ADR amends
[ADR-0026](0026-five-destination-learner-navigation.md)** and master-plan §7.1.
Recorded as D-0260. It responds to direct client feedback that the mobile
learner app is overwhelming, cluttered, and has confusing flow and navigation
(inventory in [docs/UX_SIMPLIFICATION_AUDIT.md](../UX_SIMPLIFICATION_AUDIT.md)).

This ADR does not authorize production or Google Play.

## Context

ADR-0026 fixed five primary destinations (Home · Learn · Speak · Progress ·
1:1) and itself recorded the trade-off that a five-item mobile nav is denser.
Soft-launch testers confirmed that density: five tabs on a phone, a cryptic
"1:1" label for a waitlist form, account security and locale hidden behind a
header toggle with no profile destination, and a sticky (not fixed) bottom
bar. Home, Learn and Speak further overlap as three separate listen/speak
products (audit §§3–6).

Separately, the D-0250 ion theme layers a grid overlay and multiple radial
glows behind learner content. Muted text `#b9b1d4` on `--ge-color-surface`
`#13111f` already measures **9.14:1** (WCAG 2.2 AA and AAA for normal text);
perceived unreadability is competing chrome, not a failing hex.

## Decision

1. Primary learner navigation is exactly:

   `Home · Learn · Speak · Me`

2. **Home**, **Learn** and **Speak** keep their ADR-0026 definitions:
   today's recommended journey; the four-session level path (ADR-0025);
   one speaking room (everyday talk with the selected coach and a situation
   picker). Confidence Conversation and Role-play are not sibling primary
   destinations. Selectable coaches remain ADR-0031.

3. **Me** is a new destination that absorbs, in this order:
   - account summary (membership type and organization);
   - the Progress dashboard (master plan §7.3 / ADR-0028 item 5);
   - the 1:1 tuition waitlist plus governed institute contact links
     (phone / WhatsApp / email from configuration; empty values stay
     hidden; not booking, payment, or an in-app call);
   - account security (credential status, password establish/change,
     session management);
   - interface-locale selection;
   - sign out.

   Progress and 1:1 **content** is unchanged in this ADR; only location
   moves. Plain-language Progress labels are a later sub-checkpoint
   (D-0260.8).

4. Deep links keep working:
   - `#me` is the canonical Me hash;
   - `#progress` resolves to Me and lands on the Progress section;
   - `#tuition` and `#one-to-one` resolve to Me and land on the 1:1
     section;
   - `#practice` remains an alias of Speak (ADR-0026 item 6).

5. On mobile widths (max 48rem) the primary navigation is a **fixed**
   bottom tab bar: safe-area aware, minimum 48px targets, inline SVG
   icons with visible text labels (no emoji glyphs). Main content is
   padded so the bar cannot cover the primary action. On desktop widths
   it remains a grid row under the header.

6. The header simplifies to the brand plus a single account affordance
   that navigates to Me. The header locale select and the header
   account-security toggle are removed in favour of the Me sections.

7. Theme: the dark **ion studio** identity (void indigo canvas, electric
   cyan actions, magenta accent — D-0250) is retained but calmed:
   - the background grid overlay is removed;
   - layered radial body glows reduce to at most one subtle gradient;
   - panel glow/shadow intensity reduces so panels read as flat elevated
     surfaces;
   - muted text must remain at least **4.5:1** against
     `--ge-color-surface` (WCAG 2.2 AA). The current token already
     measures 9.14:1; D-0260.2 must not darken it. Re-verify after glow
     removal.

8. This does not change marks, mastery, unlock order, streak or review
   determinism (ADR-0028), recording-consent semantics (ADR-0007),
   tenancy, or any API contract. It does not authorize in-app video,
   WebRTC, payments, or production/Play.

## Alternatives considered

### Alternative A — keep five destinations and only restyle

Rejected by the Accountable Product Owner. The density complaint is about
information architecture, not only visuals; Progress and 1:1 are
low-frequency surfaces that do not justify permanent top-level tabs.

### Alternative B — replace the dark ion theme with a light theme

Rejected for this series. D-0250 stands; the ion identity is retained and
calmed. A light theme would be a separate decision with its own review.

## Consequences

### Positive

- Four thumb-reachable tabs; the waitlist is no longer a cryptic "1:1" tab.
- A findable account/profile area (Me) instead of a header toggle panel.
- Old bookmarks (`#progress`, `#tuition`) continue to work.
- Text keeps AA contrast and panels regain hierarchy without a rebrand.

### Negative and trade-offs

- Progress is one tap deeper than before (inside Me).
- Existing e2e specs and localization catalogues need nav-label updates.
- Any tester muscle memory from the five-tab build is invalidated.

## Security, privacy and tenancy impact

- No authorization or scoping change. Tuition requests stay org-scoped.
- Account security moves location only; Sanctum/PKCE flows are unchanged.
- No new data collection; locale preference handling is unchanged.
- Coach display names remain device-local presentation (ADR-0031).

## Authoritative records and consistency impact

No schema change, no migration, no API change. Purely client
presentation. Streak, delayed review, session completion and consent
records are untouched.

## API, client and operational impact

- No OpenAPI change. Generated client types are unchanged.
- Localization catalogues gain `nav.me` and Me-section labels; kn-IN
  strings remain `pending-independent-review` per D-0030/D-0210.
- Public service-worker shell cache version must bump when the shell ships.

## Master-plan amendment

**This ADR amends master-plan §7.1** from five destinations
(Home / Learn / Speak / Progress / 1:1) to four destinations
(Home / Learn / Speak / Me) as specified above, and records the
calmed-ion presentation rules. `ENTERPRISE_PRODUCT_MASTER_PLAN.md` is
updated to version **3.11** in the same change.

§7.2 (Home dominant action and streak), §7.3 screen contracts, and
§7.4 conversation-state UX are not amended here. Later D-0260
sub-checkpoints implement presentation of those sections; they do not
require further master-plan amendments unless a new product rule appears.

## Approval evidence

Accountable Product Owner instruction in the active Cursor conversation
on 31 August 2026 (Asia/Kolkata): amend ADR-0026 to four tabs
(Home, Learn, Speak, Me) with Progress and 1:1 folded into Me, and keep
the ion dark identity but calm it (remove the grid overlay, reduce glows,
improve contrast and spacing). The Cursor agent authored and recorded
this ADR and is not its approver.

Independent security, privacy and tenancy specialist review remains
required before production. Independent Kannada review of new catalogue
strings remains required before production.

## Verification

- `make verify` at each implementation checkpoint of the series.
- Playwright e2e (`apps/web/e2e/*`) updated for the four-tab nav and Me.
- Axe checks at 320/360/412 widths on redesigned screens.
- Contrast ratio of muted text on `--ge-color-surface` computed and
  recorded after the token pass (floor 4.5:1).

## Follow-up

D-0260.2 – D-0260.9 as sequenced in
[docs/UX_SIMPLIFICATION_AUDIT.md](../UX_SIMPLIFICATION_AUDIT.md).
Independent Kannada review of new catalogue strings before production.
