---
status: accepted
date: 2026-09-01
deciders:
  - S R Bhandary as Accountable Product Owner and Technical Decision Owner
consulted:
  - Cursor agent as author and evidence recorder, not approver
informed: []
amends_master_plan: true
approval_evidence:
  - APP-0072
approval_reference: Exact human instruction in the active Cursor conversation granting blanket approval to build DR-0045 Option C
---

# ADR-0035: Direct learner subscriptions, GST and dummy in-app checkout

## Status

Accepted on 1 September 2026 under APP-0072. Resolves
[DR-0045](../DR-0045_LEARNER_SUBSCRIPTION_TIERS_REQUEST.md) Option C.
Amends master plan version 3.12, ADR-0014 item 6 (Direct checkout only), and
ADR-0026 item 7 (in-app checkout allowed; Play Billing still excluded).
Recorded as D-0266. Display names for the paid SKUs were later set to
Modules and Premium by [ADR-0036](0036-premium-courtesy-month.md) without
changing plan codes. Bounded specification:
[ADMIN-ENT-01_SPEC.md](../ADMIN-ENT-01_SPEC.md). Catalogue:
[SUBSCRIPTIONS.md](../SUBSCRIPTIONS.md).

## Context

Direct access was invitation + membership, not a paywall. The product owner
accepted GST-exclusive ₹350 / ₹500 monthly plans, a free 48-hour Level 1–2
demo with one inclusive 5-minute live-AI cap, dummy GSTIN replaced on an
admin page, and in-app checkout in the same slice.

ADR-0014 previously forbade checkout. ADR-0026 excluded Stripe and Play
Billing. This ADR authorizes a project-owned payment port for Direct only.

## Decision

1. Subscription gates apply only to `platform_direct`. Institutional orgs
   stay invite-based.
2. Plans are organization-owned data. Default codes: `demo`,
   `monthly_modules` (₹35000 paise/month, no Role-play), 
   `monthly_modules_roleplay` (₹50000 paise/month, Role-play).
3. Demo: ₹0; Level 1–2; 48 hours from first successful sign-in after grant;
   300-second inclusive cap on Speak, Role-play, Conversation/Interaction
   live AI, STT and TTS. Authored lesson media does not consume the cap.
4. Prices are GST exclusive. Tax settings (GSTIN, rate, registered flag)
   are operator-editable. Placeholder GSTIN `29AAAAA0000A1Z5` / 18% until
   replaced. Changing GSTIN does not rewrite issued checkouts.
5. In-app checkout uses `PaymentProvider`. Dummy driver completes in-process
   for non-production. Disabled fails closed. Play Billing is not authorized.
   Provider types stay behind the port.
6. Operators may grant, adjust, revoke, restart demo clock, restart demo
   meter, and clear username sign-in throttle. They must not wipe learning
   evidence. Session revocation remains the existing membership-session
   command.
7. AI, streaks and attendance never grant entitlements. Remaining seconds
   are an entitlement counter with idempotent charge keys.

## Consequences

- Direct learners without a grant receive auto-demo on first entitlement
  read or gated request.
- OpenAPI 0.38.0. Privacy inventory `learner-privacy-v7`.
- Live payment credentials and production remain unauthorized.

## Master-plan amendment

Amends §§8.3, 16.5, 31 and 32 (Monetization) as recorded in version 3.12.
